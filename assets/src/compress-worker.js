import { initCodecs, compressBuffer } from './codecs.js';

let ready = false;

self.onmessage = async (event) => {
	const msg = event.data || {};
	const { id, type } = msg;

	try {
		if (type === 'init') {
			await initCodecs(msg.wasmUrl || '');
			ready = true;
			self.postMessage({ id, type: 'ready' });
			return;
		}

		if (type === 'compress') {
			if (!ready) {
				await initCodecs(msg.wasmUrl || '');
				ready = true;
			}
			const result = await compressBuffer({
				buffer: msg.buffer,
				mime: msg.mime,
				settings: msg.settings || {},
			});
			self.postMessage(
				{
					id,
					type: 'result',
					mime: result.mime,
					width: result.width,
					height: result.height,
					buffer: result.buffer,
				},
				[result.buffer]
			);
			return;
		}

		self.postMessage({ id, type: 'error', error: `Type inconnu: ${type}` });
	} catch (err) {
		self.postMessage({
			id,
			type: 'error',
			error: err && err.message ? err.message : String(err),
		});
	}
};
