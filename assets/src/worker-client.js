/**
 * Shared Web Worker client for compression.
 */

let worker = null;
let seq = 0;
/** @type {Map<number, { resolve: Function, reject: Function }>} */
const pending = new Map();

function getConfig() {
	return window.wpCompactConfig || {};
}

function ensureWorker() {
	if (worker) return worker;
	const cfg = getConfig();
	worker = new Worker(cfg.workerUrl, { type: 'module' });
	worker.onmessage = (event) => {
		const msg = event.data || {};
		const entry = pending.get(msg.id);
		if (!entry) return;
		pending.delete(msg.id);
		if (msg.type === 'error') {
			entry.reject(new Error(msg.error || 'Worker error'));
			return;
		}
		entry.resolve(msg);
	};
	worker.onerror = (err) => {
		for (const [, entry] of pending) {
			entry.reject(err.error || new Error('Worker crashed'));
		}
		pending.clear();
		worker = null;
	};
	return worker;
}

function call(payload, transfer = []) {
	const id = ++seq;
	return new Promise((resolve, reject) => {
		pending.set(id, { resolve, reject });
		ensureWorker().postMessage({ ...payload, id }, transfer);
	});
}

let initPromise = null;

export function initWorker() {
	if (!initPromise) {
		const cfg = getConfig();
		initPromise = call({ type: 'init', wasmUrl: cfg.wasmUrl }).catch((err) => {
			initPromise = null;
			throw err;
		});
	}
	return initPromise;
}

/**
 * @param {ArrayBuffer} buffer
 * @param {string} mime
 * @param {Record<string, unknown>} settings
 */
export async function compressInWorker(buffer, mime, settings) {
	await initWorker();
	const cfg = getConfig();
	const msg = await call(
		{
			type: 'compress',
			buffer,
			mime,
			settings,
			wasmUrl: cfg.wasmUrl,
		},
		[buffer]
	);
	return {
		buffer: msg.buffer,
		mime: msg.mime,
		width: msg.width,
		height: msg.height,
	};
}
