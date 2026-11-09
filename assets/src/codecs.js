/**
 * jSquash codec wrappers (Squoosh-derived WASM).
 */

import decodeJpeg, { init as initJpegDecode } from '@jsquash/jpeg/decode';
import encodeJpeg, { init as initJpegEncode } from '@jsquash/jpeg/encode';
import decodePng, { init as initPngDecode } from '@jsquash/png/decode';
import encodePng, { init as initPngEncode } from '@jsquash/png/encode';
import decodeWebp, { init as initWebpDecode } from '@jsquash/webp/decode';
import encodeWebp, { init as initWebpEncode } from '@jsquash/webp/encode';
import decodeAvif, { init as initAvifDecode } from '@jsquash/avif/decode';
import encodeAvif, { init as initAvifEncode } from '@jsquash/avif/encode';
import resize, { initResize } from '@jsquash/resize';

// Force single-thread oxipng (no dynamic import / SharedArrayBuffer).
import initOxipngWasm, {
	optimise as oxipngOptimiseBytes,
	optimise_raw as oxipngOptimiseRaw,
} from '@jsquash/oxipng/codec/pkg/squoosh_oxipng.js';

let wasmBase = '';
let inited = false;

/**
 * @param {string} baseUrl Trailing-slash URL to wasm assets.
 */
export async function initCodecs(baseUrl) {
	if (inited) return;
	wasmBase = baseUrl.endsWith('/') ? baseUrl : `${baseUrl}/`;

	const locate = (file) => `${wasmBase}${file}`;

	await Promise.all([
		initJpegDecode(undefined, { locateFile: locate }),
		initJpegEncode(undefined, { locateFile: locate }),
		initWebpDecode(undefined, { locateFile: locate }),
		initWebpEncode(undefined, { locateFile: locate }),
		initAvifDecode(undefined, { locateFile: locate }),
		initAvifEncode(undefined, { locateFile: locate }),
		initPngDecode(locate('squoosh_png_bg.wasm')),
		initPngEncode(locate('squoosh_png_bg.wasm')),
		initOxipngWasm(locate('squoosh_oxipng_bg.wasm')),
		initResize(locate('squoosh_resize_bg.wasm')),
	]);

	inited = true;
}

/**
 * @param {string} mime
 * @param {ArrayBuffer} buffer
 * @returns {Promise<ImageData>}
 */
export async function decodeImage(mime, buffer) {
	switch (mime) {
		case 'image/jpeg':
			return decodeJpeg(buffer);
		case 'image/png':
			return decodePng(buffer);
		case 'image/webp':
			return decodeWebp(buffer);
		case 'image/avif':
			return decodeAvif(buffer);
		default:
			throw new Error(`MIME non supporté: ${mime}`);
	}
}

/**
 * @param {ImageData} image
 * @param {{ maxWidth?: number, maxHeight?: number }} opts
 */
export async function maybeResize(image, opts = {}) {
	const maxW = Number(opts.maxWidth) || 0;
	const maxH = Number(opts.maxHeight) || 0;
	if (!maxW && !maxH) return image;

	let width = image.width;
	let height = image.height;
	const scaleW = maxW > 0 ? maxW / width : 1;
	const scaleH = maxH > 0 ? maxH / height : 1;
	const scale = Math.min(scaleW, scaleH, 1);
	if (scale >= 1) return image;

	width = Math.max(1, Math.round(width * scale));
	height = Math.max(1, Math.round(height * scale));

	return resize(image, {
		width,
		height,
		method: 'lanczos3',
		fitMethod: 'stretch',
	});
}

/**
 * @param {ImageData} image
 * @param {string} targetMime
 * @param {Record<string, unknown>} settings
 * @returns {Promise<ArrayBuffer>}
 */
export async function encodeImage(image, targetMime, settings) {
	switch (targetMime) {
		case 'image/jpeg':
			return encodeJpeg(image, {
				quality: Number(settings.jpeg_quality) || 78,
			});
		case 'image/webp':
			return encodeWebp(image, {
				quality: Number(settings.webp_quality) || 78,
			});
		case 'image/avif': {
			// Newer @jsquash/avif uses quality 0–100 (map from legacy cqLevel-ish setting).
			const q = Number(settings.avif_quality);
			const quality = Number.isFinite(q) && q <= 63 ? Math.round(100 - (q / 63) * 100) : (q || 50);
			return encodeAvif(image, { quality: Math.max(1, Math.min(100, quality)) });
		}
		case 'image/png': {
			// Prefer oxipng on raw pixels when possible.
			const level = Number(settings.oxipng_level);
			const buf = oxipngOptimiseRaw(
				image.data,
				image.width,
				image.height,
				Number.isFinite(level) ? level : 2,
				false,
				false
			);
			return u8ToArrayBuffer(buf);
		}
		default:
			throw new Error(`Encode MIME non supporté: ${targetMime}`);
	}
}

/**
 * Lossless-ish PNG path: optimise existing PNG bytes when not resizing/converting.
 * @param {ArrayBuffer} pngBuffer
 * @param {number} level
 */
/**
 * @param {Uint8Array} u8
 * @returns {ArrayBuffer}
 */
function u8ToArrayBuffer(u8) {
	return u8.buffer.slice(u8.byteOffset, u8.byteOffset + u8.byteLength);
}

export async function optimisePngBuffer(pngBuffer, level = 2) {
	const out = oxipngOptimiseBytes(new Uint8Array(pngBuffer), level, false, false);
	return u8ToArrayBuffer(out);
}

/**
 * Resolve output mime for safe vs advanced mode.
 * @param {string} sourceMime
 * @param {Record<string, unknown>} settings
 */
export function resolveTargetMime(sourceMime, settings) {
	if (settings.advanced_enabled && settings.convert_to === 'webp') {
		return 'image/webp';
	}
	if (settings.advanced_enabled && settings.convert_to === 'avif') {
		return 'image/avif';
	}
	return sourceMime;
}

/**
 * Full compress pipeline.
 * @param {{ buffer: ArrayBuffer, mime: string, settings: Record<string, unknown> }} job
 */
export async function compressBuffer(job) {
	const { buffer, mime, settings } = job;
	const targetMime = resolveTargetMime(mime, settings);
	const advanced = !!settings.advanced_enabled;
	const needsResize =
		advanced && ((Number(settings.max_width) || 0) > 0 || (Number(settings.max_height) || 0) > 0);
	const converting = targetMime !== mime;

	// Fast path: PNG optimise without decode when same format & no resize.
	if (mime === 'image/png' && !converting && !needsResize) {
		const out = await optimisePngBuffer(buffer, Number(settings.oxipng_level) || 2);
		return { buffer: out, mime: targetMime, width: 0, height: 0 };
	}

	let image = await decodeImage(mime, buffer);
	if (needsResize) {
		image = await maybeResize(image, {
			maxWidth: Number(settings.max_width) || 0,
			maxHeight: Number(settings.max_height) || 0,
		});
	}

	const encoded = await encodeImage(image, targetMime, settings);
	return {
		buffer: encoded,
		mime: targetMime,
		width: image.width,
		height: image.height,
	};
}
