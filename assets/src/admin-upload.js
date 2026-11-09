import { compressInWorker, initWorker } from './worker-client.js';
import { formatBytes } from './api.js';

const SUPPORTED = ['image/jpeg', 'image/png', 'image/webp'];

/** Native File/Blob already compressed — skip our FilesAdded steal. */
const readyBlobs = new WeakSet();

function rawSettings() {
	return window.wpSquooshConfig?.settings || {};
}

/**
 * @returns {'off'|'compress'|'webp'}
 */
function uploadMode() {
	const s = rawSettings();
	if (s.upload_mode === 'off' || s.upload_mode === 'compress' || s.upload_mode === 'webp') {
		return s.upload_mode;
	}
	return s.compress_on_upload === false ? 'off' : 'compress';
}

function workerSettings() {
	const s = rawSettings();
	const mode = uploadMode();
	if (mode === 'webp') {
		return {
			...s,
			advanced_enabled: true,
			convert_to: 'webp',
			max_width: 0,
			max_height: 0,
			allow_larger: true,
			min_savings_pct: 0,
		};
	}
	return {
		...s,
		advanced_enabled: false,
		convert_to: '',
		max_width: 0,
		max_height: 0,
	};
}

function notice(text) {
	let el = document.getElementById('wpsquoosh-upload-notice');
	if (!el) {
		el = document.createElement('div');
		el.id = 'wpsquoosh-upload-notice';
		el.className = 'wpsquoosh-upload-notice notice notice-info inline';
		const anchor =
			document.querySelector('.media-frame-content') ||
			document.querySelector('.media-modal .media-frame') ||
			document.querySelector('.uploader-inline-content') ||
			document.querySelector('.uploader-inline') ||
			document.querySelector('.media-modal') ||
			document.querySelector('#wpbody-content .wrap') ||
			document.querySelector('#wpbody-content') ||
			document.body;
		anchor.prepend(el);
	}
	el.textContent = text;
}

/**
 * Resolve MIME even when browser leaves type empty (common for WebP).
 * @param {File|Blob} file
 * @param {string} [fallbackName]
 */
function resolveMime(file, fallbackName = '') {
	const t = (file && file.type) || '';
	if (SUPPORTED.includes(t)) return t;
	const name = String((file && file.name) || fallbackName || '').toLowerCase();
	if (name.endsWith('.webp')) return 'image/webp';
	if (name.endsWith('.png')) return 'image/png';
	if (name.endsWith('.jpg') || name.endsWith('.jpeg')) return 'image/jpeg';
	return t;
}

/**
 * @param {string} name
 * @param {string} mime
 */
function renameForMime(name, mime) {
	const base = String(name || 'image').replace(/\.[^.]+$/, '') || 'image';
	if (mime === 'image/webp') return `${base}.webp`;
	if (mime === 'image/png') return `${base}.png`;
	if (mime === 'image/jpeg') return `${base}.jpg`;
	return name;
}

/**
 * @param {unknown} v
 * @returns {boolean}
 */
function isBlobLike(v) {
	return (
		!!v &&
		typeof v === 'object' &&
		typeof v.size === 'number' &&
		(typeof v.arrayBuffer === 'function' || typeof v.slice === 'function')
	);
}

/**
 * @param {object} plfile
 * @returns {File|null}
 */
function extractNativeFile(plfile) {
	/** @type {Blob|null} */
	let native = null;

	try {
		if (typeof plfile.getNative === 'function') {
			const n = plfile.getNative();
			if (isBlobLike(n)) native = /** @type {Blob} */ (n);
		}
	} catch {
		native = null;
	}

	if (!native) {
		try {
			const src = typeof plfile.getSource === 'function' ? plfile.getSource() : null;
			if (src && typeof src.getSource === 'function') {
				const inner = src.getSource();
				if (isBlobLike(inner)) native = /** @type {Blob} */ (inner);
			} else if (isBlobLike(src)) {
				native = /** @type {Blob} */ (src);
			}
		} catch {
			/* ignore */
		}
	}

	if (!native) return null;

	const name = plfile.name || (native instanceof File ? native.name : 'image.jpg');
	const type = resolveMime(native, name) || plfile.type || 'application/octet-stream';

	try {
		const sliced = typeof native.slice === 'function' ? native.slice(0, native.size, type) : native;
		return new File([sliced], name, { type, lastModified: Date.now() });
	} catch {
		try {
			return new File([native], name, { type, lastModified: Date.now() });
		} catch {
			return native instanceof File ? native : null;
		}
	}
}

/**
 * @param {File} file
 * @returns {Promise<File>}
 */
async function maybeCompressFile(file) {
	const mode = uploadMode();
	if (mode === 'off') return file;

	const mime = resolveMime(file);
	if (!SUPPORTED.includes(mime)) return file;

	// Normalize type for the worker (empty MIME breaks codec selection).
	const typed =
		file.type === mime
			? file
			: new File([file], file.name, { type: mime, lastModified: file.lastModified || Date.now() });

	const s = workerSettings();
	const originalSize = typed.size;
	const buffer = await typed.arrayBuffer();
	const result = await compressInWorker(buffer, mime, s);
	const newSize = result.buffer.byteLength;
	const outMime = mode === 'webp' ? 'image/webp' : result.mime || mime;

	const savedPct = originalSize > 0 ? (1 - newSize / originalSize) * 100 : 0;
	const minSave = Number(s.min_savings_pct) || 0;

	if (mode === 'compress') {
		if (newSize >= originalSize && !s.allow_larger) {
			notice(
				`WPsquoosh: ${typed.name} non remplacé (pas de gain, ${formatBytes(originalSize)}).`
			);
			return typed;
		}
		if (savedPct < minSave && !s.allow_larger) {
			notice(
				`WPsquoosh: ${typed.name} ignoré (gain ${savedPct.toFixed(1)}% < ${minSave}%).`
			);
			return typed;
		}
	}

	const outName = renameForMime(typed.name, outMime);
	const compressed = new File([result.buffer], outName, {
		type: outMime,
		lastModified: Date.now(),
	});

	const modeLabel = mode === 'webp' && mime !== 'image/webp' ? '→ WebP ' : '';
	notice(
		`WPsquoosh: ${typed.name} ${modeLabel}${formatBytes(originalSize)} → ${formatBytes(newSize)} (−${savedPct.toFixed(1)}%).`
	);
	return compressed;
}

/**
 * @param {object} uploader
 * @param {object[]} files
 */
function stealAndRequeue(uploader, files) {
	/** @type {{ plName: string, file: File }[]} */
	const pending = [];

	for (const f of Array.from(files)) {
		let native = null;
		try {
			native = typeof f.getNative === 'function' ? f.getNative() : null;
		} catch {
			native = null;
		}

		if (native && readyBlobs.has(native)) {
			continue;
		}

		const extracted = extractNativeFile(f);
		if (!extracted) {
			notice(
				`WPsquoosh: lecture impossible pour ${f.name || 'fichier'} — upload original sans compression.`
			);
			continue;
		}

		const idx = files.indexOf(f);
		if (idx >= 0) {
			files.splice(idx, 1);
		}
		try {
			uploader.removeFile(f);
		} catch {
			/* ignore */
		}

		pending.push({ plName: f.name || extracted.name, file: extracted });
	}

	if (!pending.length) return;

	try {
		uploader.stop();
	} catch {
		/* ignore */
	}

	notice(`WPsquoosh: compression de ${pending.length} image(s)…`);

	(async () => {
		try {
			await initWorker();

			for (const item of pending) {
				try {
					const mime = resolveMime(item.file);
					if (!SUPPORTED.includes(mime)) {
						readyBlobs.add(item.file);
						uploader.addFile(item.file);
						continue;
					}
					const compressed = await maybeCompressFile(item.file);
					readyBlobs.add(compressed);
					uploader.addFile(compressed);
				} catch (err) {
					notice(
						`WPsquoosh: échec sur ${item.plName} (${err.message}) — envoi de l’original.`
					);
					readyBlobs.add(item.file);
					try {
						uploader.addFile(item.file);
					} catch {
						/* ignore */
					}
				}
			}
		} catch (err) {
			notice(`WPsquoosh: ${err.message}`);
			for (const item of pending) {
				readyBlobs.add(item.file);
				try {
					uploader.addFile(item.file);
				} catch {
					/* ignore */
				}
			}
		} finally {
			try {
				uploader.start();
			} catch {
				/* ignore */
			}
		}
	})();
}

/**
 * Bind FilesAdded on a live plupload instance (featured image / media modal).
 * @param {object} up
 */
function bindPluploadInstance(up) {
	if (!up || up.__wpsquooshBound) return;
	up.__wpsquooshBound = true;

	up.bind(
		'FilesAdded',
		(uploader, files) => {
			if (uploadMode() === 'off') return;
			if (!files || !files.length) return;

			const needsWork = files.some((f) => {
				try {
					const n = f.getNative && f.getNative();
					return !(n && readyBlobs.has(n));
				} catch {
					return true;
				}
			});
			if (!needsWork) return;

			stealAndRequeue(uploader, files);
		},
		null,
		1000
	);
}

/**
 * Try to find plupload instances attached to wp.media frames / uploader windows.
 */
function bindOpenMediaUploaders() {
	try {
		const wp = window.wp;
		if (!wp || !wp.media) return;

		const tryBind = (uploaderWrap) => {
			if (!uploaderWrap) return;
			// wp.Uploader instance has .uploader (plupload)
			if (uploaderWrap.uploader && typeof uploaderWrap.uploader.bind === 'function') {
				bindPluploadInstance(uploaderWrap.uploader);
			}
			if (typeof uploaderWrap.bind === 'function' && uploaderWrap.files) {
				bindPluploadInstance(uploaderWrap);
			}
		};

		if (wp.media.frame) {
			const frame = wp.media.frame;
			if (frame.uploader && frame.uploader.uploader) {
				tryBind(frame.uploader);
			}
			if (frame.views) {
				/* ignore */
			}
		}

		// Active modal uploader window
		document.querySelectorAll('.media-modal, .supports-drag-drop').forEach(() => {
			/* presence only — binding is via prototype / UploaderWindow */
		});
	} catch {
		/* ignore */
	}
}

function patchWpUploader() {
	if (typeof window.wp === 'undefined' || !window.wp.Uploader) return false;

	const proto = window.wp.Uploader.prototype;
	if (!proto.__wpsquooshPatched) {
		proto.__wpsquooshPatched = true;
		const originalInit = proto.init;
		proto.init = function patchedInit() {
			originalInit.apply(this, arguments);
			if (this.uploader) {
				bindPluploadInstance(this.uploader);
			}
		};
	}

	return true;
}

/**
 * Patch media modal UploaderWindow (featured image, Gutenberg, etc.).
 */
function patchUploaderWindow() {
	const wp = window.wp;
	if (!wp || !wp.media || !wp.media.view || !wp.media.view.UploaderWindow) {
		return false;
	}

	const UW = wp.media.view.UploaderWindow;
	if (UW.prototype.__wpsquooshPatched) return true;
	UW.prototype.__wpsquooshPatched = true;

	const originalReady = UW.prototype.ready;
	UW.prototype.ready = function wpsquooshReady() {
		const result = originalReady ? originalReady.apply(this, arguments) : undefined;
		try {
			if (this.uploader) {
				// this.uploader is often a wp.Uploader
				if (this.uploader.uploader) {
					bindPluploadInstance(this.uploader.uploader);
				} else if (this.uploader.bind) {
					bindPluploadInstance(this.uploader);
				}
			}
		} catch {
			/* ignore */
		}
		bindOpenMediaUploaders();
		return result;
	};

	return true;
}

function patchInputFallback() {
	if (document.documentElement.dataset.wpsquooshInputPatched === '1') return;
	document.documentElement.dataset.wpsquooshInputPatched = '1';

	document.addEventListener(
		'change',
		async (event) => {
			const input = event.target;
			if (!(input instanceof HTMLInputElement) || input.type !== 'file') return;
			if (!input.files || !input.files.length) return;
			if (input.dataset.wpsquooshHandled === '1') return;
			if (uploadMode() === 'off') return;

			const list = Array.from(input.files);
			const needs = list.some((f) => SUPPORTED.includes(resolveMime(f)));
			if (!needs) return;

			// Broader: any file input inside media modal / featured image UI.
			const inMediaUi =
				input.closest('.media-modal') ||
				input.closest('.uploader-inline') ||
				input.closest('.upload-window') ||
				input.closest('.media-frame') ||
				input.id === 'async-upload' ||
				input.name === 'async-upload' ||
				input.closest('#html-upload-ui') ||
				input.closest('.uploader-html') ||
				input.classList.contains('moxie-shim-html5') ||
				(input.parentElement && input.parentElement.classList.contains('moxie-shim'));

			if (!inMediaUi) return;

			event.stopImmediatePropagation();
			event.preventDefault();

			try {
				await initWorker();
				const dt = new DataTransfer();
				for (const file of list) {
					try {
						const out = await maybeCompressFile(file);
						readyBlobs.add(out);
						dt.items.add(out);
					} catch {
						dt.items.add(file);
					}
				}
				input.dataset.wpsquooshHandled = '1';
				input.files = dt.files;
				input.dispatchEvent(new Event('change', { bubbles: true }));
			} catch (err) {
				notice(`WPsquoosh: ${err.message}`);
			} finally {
				setTimeout(() => {
					delete input.dataset.wpsquooshHandled;
				}, 0);
			}
		},
		true
	);
}

/**
 * Re-bind whenever a media modal opens (featured image, gallery, etc.).
 */
function watchMediaModalOpen() {
	if (document.documentElement.dataset.wpsquooshModalWatch === '1') return;
	document.documentElement.dataset.wpsquooshModalWatch = '1';

	const observer = new MutationObserver(() => {
		if (document.querySelector('.media-modal')) {
			patchWpUploader();
			patchUploaderWindow();
			bindOpenMediaUploaders();
		}
	});
	observer.observe(document.body, { childList: true, subtree: true });

	document.addEventListener(
		'click',
		() => {
			setTimeout(() => {
				patchWpUploader();
				patchUploaderWindow();
				bindOpenMediaUploaders();
			}, 300);
		},
		true
	);
}

function boot(attempt = 0) {
	if (uploadMode() === 'off') return;

	const upOk = patchWpUploader();
	const winOk = patchUploaderWindow();
	patchInputFallback();
	watchMediaModalOpen();
	bindOpenMediaUploaders();

	if ((!upOk || !winOk) && attempt < 50) {
		setTimeout(() => boot(attempt + 1), 200);
		return;
	}

	initWorker().catch(() => {});
}

// Run ASAP (footer script) + on DOM ready.
boot();
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', () => boot());
} else {
	boot();
}
