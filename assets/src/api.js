function cfg() {
	return window.wpSquooshConfig || {};
}

function headers(json = false) {
	const h = {
		'X-WP-Nonce': cfg().nonce,
	};
	if (json) h['Content-Type'] = 'application/json';
	return h;
}

async function parseResponse(res) {
	const text = await res.text();
	let data = null;
	try {
		data = text ? JSON.parse(text) : null;
	} catch {
		data = { message: text };
	}
	if (!res.ok) {
		const msg =
			(data && (data.message || data.code)) || `HTTP ${res.status}`;
		const err = new Error(typeof msg === 'string' ? msg : JSON.stringify(msg));
		err.data = data;
		err.status = res.status;
		throw err;
	}
	return data;
}

export async function fetchSettings() {
	const res = await fetch(`${cfg().restUrl}/settings`, {
		headers: headers(),
		credentials: 'same-origin',
	});
	return parseResponse(res);
}

export async function saveSettings(settings) {
	const res = await fetch(`${cfg().restUrl}/settings`, {
		method: 'POST',
		headers: headers(true),
		credentials: 'same-origin',
		body: JSON.stringify(settings),
	});
	return parseResponse(res);
}

export async function fetchStats() {
	const res = await fetch(`${cfg().restUrl}/stats`, {
		headers: headers(),
		credentials: 'same-origin',
	});
	return parseResponse(res);
}

export async function fetchMediaPage(page = 1, perPage = 50) {
	const url = new URL(`${cfg().restUrl}/media`);
	url.searchParams.set('page', String(page));
	url.searchParams.set('per_page', String(perPage));
	const res = await fetch(url.toString(), {
		headers: headers(),
		credentials: 'same-origin',
	});
	return parseResponse(res);
}

export async function fetchMediaFile(id) {
	const res = await fetch(`${cfg().restUrl}/media/${id}/file`, {
		headers: headers(),
		credentials: 'same-origin',
	});
	if (!res.ok) {
		const text = await res.text();
		throw new Error(text || `HTTP ${res.status}`);
	}
	const mime = res.headers.get('Content-Type') || 'application/octet-stream';
	const buffer = await res.arrayBuffer();
	return { buffer, mime };
}

/**
 * @param {number} id
 * @param {Blob} blob
 * @param {object} meta
 */
export async function replaceMedia(id, blob, meta) {
	const form = new FormData();
	const ext =
		meta.mime === 'image/png'
			? 'png'
			: meta.mime === 'image/webp'
				? 'webp'
				: meta.mime === 'image/avif'
					? 'avif'
					: 'jpg';
	form.append('file', blob, `compressed.${ext}`);
	form.append('original_size', String(meta.original_size || 0));
	form.append('mime', meta.mime || blob.type);
	form.append('backup', meta.backup ? '1' : '0');
	form.append('allow_larger', meta.allow_larger ? '1' : '0');
	form.append('settings', JSON.stringify(meta.settings || {}));
	if (meta.migrate_webp) {
		form.append('migrate_webp', '1');
		form.append('keep_legacy', '1');
	} else if (meta.keep_legacy) {
		form.append('keep_legacy', '1');
	}

	const res = await fetch(`${cfg().restUrl}/media/${id}/replace`, {
		method: 'POST',
		headers: {
			'X-WP-Nonce': cfg().nonce,
		},
		credentials: 'same-origin',
		body: form,
	});
	return parseResponse(res);
}

export async function fetchLegacyPage(page = 1, perPage = 50) {
	const url = new URL(`${cfg().restUrl}/legacy`);
	url.searchParams.set('page', String(page));
	url.searchParams.set('per_page', String(perPage));
	const res = await fetch(url.toString(), {
		headers: headers(),
		credentials: 'same-origin',
	});
	return parseResponse(res);
}

export async function purgeLegacy({ ids = [], all = false } = {}) {
	const res = await fetch(`${cfg().restUrl}/legacy/purge`, {
		method: 'POST',
		headers: headers(true),
		credentials: 'same-origin',
		body: JSON.stringify(all ? { all: true } : { ids }),
	});
	return parseResponse(res);
}

export function formatBytes(bytes) {
	const n = Number(bytes) || 0;
	if (n < 1024) return `${n} B`;
	if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;
	return `${(n / (1024 * 1024)).toFixed(2)} MB`;
}
