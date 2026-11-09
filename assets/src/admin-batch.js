import {
	fetchStats,
	fetchMediaPage,
	fetchMediaFile,
	replaceMedia,
	saveSettings,
	formatBytes,
	fetchLegacyPage,
	purgeLegacy,
} from './api.js';
import { compressInWorker, initWorker } from './worker-client.js';

const state = {
	running: false,
	stop: false,
	mode: 'compress', // compress | migrate
	settings: { ...(window.wpSquooshConfig?.settings || {}) },
};

function $(sel) {
	return document.querySelector(sel);
}

function i18n(key, fallback = '') {
	return window.wpSquooshConfig?.i18n?.[key] || fallback;
}

function readFormSettings() {
	const form = $('#wpsquoosh-settings-form');
	const fd = new FormData(form);
	const bool = (name) => fd.get(name) === '1' || fd.get(name) === 'on';

	const uploadMode = String(fd.get('upload_mode') || 'compress');
	const safeUpload =
		uploadMode === 'off' || uploadMode === 'compress' || uploadMode === 'webp'
			? uploadMode
			: 'compress';

	return {
		jpeg_quality: Number(fd.get('jpeg_quality')) || 78,
		webp_quality: Number(fd.get('webp_quality')) || 78,
		avif_quality: Number(fd.get('avif_quality')) || 45,
		oxipng_level: Number(fd.get('oxipng_level')) || 2,
		min_savings_pct: Number(fd.get('min_savings_pct')) || 0,
		concurrency: Math.max(1, Math.min(4, Number(fd.get('concurrency')) || 1)),
		backup_enabled: bool('backup_enabled'),
		upload_mode: safeUpload,
		compress_on_upload: safeUpload !== 'off',
		allow_larger: bool('allow_larger'),
		advanced_enabled: bool('advanced_enabled'),
		max_width: Number(fd.get('max_width')) || 0,
		max_height: Number(fd.get('max_height')) || 0,
		convert_to: String(fd.get('convert_to') || ''),
	};
}

function migrateSettings(base) {
	return {
		...base,
		advanced_enabled: true,
		convert_to: 'webp',
		max_width: 0,
		max_height: 0,
		allow_larger: true,
		min_savings_pct: 0,
		backup_enabled: false,
	};
}

function log(message, cls = '') {
	const ul = $('#wpsquoosh-log');
	const li = document.createElement('li');
	if (cls) li.className = cls;
	li.textContent = message;
	ul.prepend(li);
}

function setProgress(done, total) {
	const pct = total > 0 ? Math.round((done / total) * 100) : 0;
	$('#wpsquoosh-progress-bar').style.width = `${pct}%`;
	$('#wpsquoosh-progress-text').textContent = `${done} / ${total} (${pct}%)`;
}

function setRunningUi(running) {
	state.running = running;
	$('#wpsquoosh-start').disabled = running;
	$('#wpsquoosh-migrate-webp').disabled = running;
	$('#wpsquoosh-stop').disabled = !running;
}

async function refreshStats() {
	try {
		const stats = await fetchStats();
		const root = $('#wpsquoosh-stats');
		root.querySelector('[data-stat="total"]').textContent = `${stats.total} ${i18n('images', 'images')}`;
		root.querySelector('[data-stat="optimized"]').textContent =
			`${stats.optimized} ${i18n('alreadyCompressed', 'already compressed')}`;
		root.querySelector('[data-stat="bytes"]').textContent = formatBytes(stats.bytes);
	} catch (err) {
		log(`${i18n('statsFailed', 'Stats')}: ${err.message}`, 'is-error');
	}
}

async function loadAllMedia() {
	const items = [];
	let page = 1;
	let pages = 1;
	do {
		const data = await fetchMediaPage(page, 50);
		items.push(...(data.items || []));
		pages = data.pages || 1;
		page += 1;
	} while (page <= pages);
	return items;
}

async function processOne(item, settings, migrate) {
	if (!migrate && item.same_settings && item.already_optimized) {
		return { status: 'skip', reason: i18n('sameSettings', 'same settings') };
	}

	if (migrate && item.mime === 'image/webp' && !item.pending_cleanup) {
		return { status: 'skip', reason: i18n('alreadyWebp', 'already WebP') };
	}

	const { buffer, mime } = await fetchMediaFile(item.id);
	const originalSize = buffer.byteLength;
	const sourceMime = mime || item.mime;

	const copy = buffer.slice(0);
	const result = await compressInWorker(copy, sourceMime, settings);
	const newSize = result.buffer.byteLength;
	const savedPct = originalSize > 0 ? (1 - newSize / originalSize) * 100 : 0;
	const minSave = Number(settings.min_savings_pct) || 0;

	if (!migrate && !settings.allow_larger && newSize >= originalSize) {
		return {
			status: 'skip',
			reason: `${i18n('noGain', 'no savings')} (${formatBytes(originalSize)} → ${formatBytes(newSize)})`,
		};
	}
	if (!migrate && savedPct < minSave && !settings.allow_larger) {
		return {
			status: 'skip',
			reason: `${savedPct.toFixed(1)}% < ${i18n('threshold', 'threshold')} ${minSave}%`,
		};
	}

	const blob = new Blob([result.buffer], { type: result.mime });
	const replaced = await replaceMedia(item.id, blob, {
		original_size: originalSize,
		mime: result.mime,
		backup: !migrate && !!settings.backup_enabled,
		allow_larger: migrate || !!settings.allow_larger,
		settings,
		migrate_webp: !!migrate,
		keep_legacy: !!migrate,
	});

	return { status: 'ok', replaced, savedPct, migrate };
}

async function runPool(items, settings, migrate) {
	const concurrency = settings.concurrency || 1;
	let index = 0;
	let done = 0;
	const total = items.length;
	setProgress(0, total);

	async function workerLoop() {
		while (!state.stop) {
			const i = index++;
			if (i >= items.length) return;
			const item = items[i];
			try {
				const res = await processOne(item, settings, migrate);
				done += 1;
				setProgress(done, total);
				if (res.status === 'skip') {
					log(
						`#${item.id} ${item.title}: ${i18n('ignored', 'skipped')} (${res.reason})`,
						'is-skip'
					);
				} else if (migrate) {
					const urls = res.replaced.urls_updated ?? 0;
					const legacy = (res.replaced.legacy_files || []).length;
					log(
						`#${item.id} ${item.title}: → WebP ${formatBytes(res.replaced.compressed_size)} | ${urls} ${i18n('dbReplacements', 'DB replacements')} | ${legacy} ${i18n('legacyTagged', 'legacy tagged')}`,
						'is-ok'
					);
				} else {
					log(
						`#${item.id} ${item.title}: ${formatBytes(res.replaced.original_size)} → ${formatBytes(res.replaced.compressed_size)} (−${res.replaced.saved_pct}%)`,
						'is-ok'
					);
				}
			} catch (err) {
				done += 1;
				setProgress(done, total);
				log(`#${item.id} ${item.title}: ${err.message}`, 'is-error');
			}
		}
	}

	const runners = [];
	for (let c = 0; c < concurrency; c += 1) {
		runners.push(workerLoop());
	}
	await Promise.all(runners);
}

async function onSave() {
	const settings = readFormSettings();
	try {
		state.settings = await saveSettings(settings);
		window.wpSquooshConfig.settings = state.settings;
		log(i18n('settingsSaved', 'Settings saved.'), 'is-ok');
	} catch (err) {
		log(`${i18n('saveFailed', 'Save failed')}: ${err.message}`, 'is-error');
	}
}

async function runJob(migrate) {
	if (state.running) return;

	if (migrate) {
		const ok = window.confirm(
			i18n(
				'migrateConfirm',
				'Migrate the entire Media Library to WebP and rewrite links in the database? Previous files will be kept until cleanup.'
			)
		);
		if (!ok) return;
	}

	setRunningUi(true);
	state.stop = false;
	state.mode = migrate ? 'migrate' : 'compress';

	const base = readFormSettings();
	const settings = migrate ? migrateSettings(base) : base;
	state.settings = settings;

	try {
		if (!migrate) {
			await saveSettings(settings);
		}
		await initWorker();
		log(
			migrate
				? i18n('loadingMigrate', 'WebP migration: loading Media Library…')
				: i18n('loadingLibrary', 'Loading Media Library…')
		);
		const items = await loadAllMedia();
		const eligible = migrate
			? items.filter((it) => it.mime !== 'image/webp' || it.pending_cleanup)
			: items;
		const list = migrate ? items.filter((it) => it.mime !== 'image/webp') : eligible;
		log(`${list.length} ${i18n('imagesToProcess', 'image(s) to process.')}`);
		await runPool(list, settings, migrate);
		log(
			state.stop ? i18n('stopped', 'Stopped.') : i18n('done', 'Done'),
			state.stop ? 'is-skip' : 'is-ok'
		);
		await refreshStats();
		await refreshLegacy();
	} catch (err) {
		log(`${i18n('jobFailed', 'Failed')}: ${err.message}`, 'is-error');
	} finally {
		setRunningUi(false);
	}
}

function onStop() {
	state.stop = true;
	$('#wpsquoosh-stop').disabled = true;
}

async function refreshLegacy() {
	const tbody = $('#wpsquoosh-legacy-tbody');
	if (!tbody) return;
	try {
		const data = await fetchLegacyPage(1, 100);
		const root = $('#wpsquoosh-legacy-stats');
		root.querySelector('[data-legacy="total"]').textContent =
			`${data.total} ${i18n('toClean', 'to clean')}`;
		root.querySelector('[data-legacy="bytes"]').textContent = formatBytes(data.bytes);

		if (!data.items || !data.items.length) {
			tbody.innerHTML = `<tr><td colspan="5">${escapeHtml(i18n('noLegacy', 'No tagged legacy files.'))}</td></tr>`;
			return;
		}

		tbody.innerHTML = data.items
			.map(
				(it) => `<tr>
					<th scope="row" class="check-column"><input type="checkbox" class="wpsquoosh-legacy-check" value="${it.id}" /></th>
					<td>${it.id}</td>
					<td>${escapeHtml(it.title || '')}</td>
					<td>${it.file_count}</td>
					<td>${formatBytes(it.bytes)}</td>
				</tr>`
			)
			.join('');
	} catch (err) {
		tbody.innerHTML = `<tr><td colspan="5">${escapeHtml(i18n('error', 'Error'))}: ${escapeHtml(err.message)}</td></tr>`;
	}
}

function escapeHtml(str) {
	return String(str)
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;');
}

function selectedLegacyIds() {
	return Array.from(document.querySelectorAll('.wpsquoosh-legacy-check:checked')).map((el) =>
		Number(el.value)
	);
}

async function onPurgeSelected() {
	const ids = selectedLegacyIds();
	if (!ids.length) {
		log(i18n('noSelection', 'Nothing selected to purge.'), 'is-skip');
		return;
	}
	if (
		!window.confirm(
			i18n('purgeConfirm', 'Permanently delete the selected legacy files?')
		)
	) {
		return;
	}
	try {
		const res = await purgeLegacy({ ids });
		log(
			`${i18n('purgeSummary', 'Purge')}: ${res.purged} ${i18n('mediaItems', 'media items')}, ${res.files_deleted} ${i18n('filesDeleted', 'files')}, ${formatBytes(res.bytes_freed)} ${i18n('freed', 'freed')}.`,
			'is-ok'
		);
		await refreshLegacy();
	} catch (err) {
		log(`${i18n('purgeSummary', 'Purge')}: ${err.message}`, 'is-error');
	}
}

async function onPurgeAll() {
	if (!window.wpSquooshConfig?.canManage) {
		log(i18n('purgeAdminOnly', 'Full purge is limited to administrators.'), 'is-error');
		return;
	}
	if (
		!window.confirm(
			i18n('purgeAllConfirm', 'Purge ALL tagged legacy files? This cannot be undone.')
		)
	) {
		return;
	}
	try {
		const res = await purgeLegacy({ all: true });
		log(
			`${i18n('purgeTotalSummary', 'Full purge')}: ${res.purged} ${i18n('mediaItems', 'media items')}, ${res.files_deleted} ${i18n('filesDeleted', 'files')}, ${formatBytes(res.bytes_freed)} ${i18n('freed', 'freed')}.`,
			'is-ok'
		);
		await refreshLegacy();
	} catch (err) {
		log(`${i18n('purgeSummary', 'Purge')}: ${err.message}`, 'is-error');
	}
}

document.addEventListener('DOMContentLoaded', () => {
	$('#wpsquoosh-save-settings')?.addEventListener('click', onSave);
	$('#wpsquoosh-start')?.addEventListener('click', () => runJob(false));
	$('#wpsquoosh-migrate-webp')?.addEventListener('click', () => runJob(true));
	$('#wpsquoosh-stop')?.addEventListener('click', onStop);
	$('#wpsquoosh-legacy-refresh')?.addEventListener('click', refreshLegacy);
	$('#wpsquoosh-legacy-purge-selected')?.addEventListener('click', onPurgeSelected);
	$('#wpsquoosh-legacy-purge-all')?.addEventListener('click', onPurgeAll);
	$('#wpsquoosh-legacy-check-all')?.addEventListener('change', (e) => {
		const on = e.target.checked;
		document.querySelectorAll('.wpsquoosh-legacy-check').forEach((el) => {
			el.checked = on;
		});
	});
	refreshStats();
	refreshLegacy();
	initWorker().catch((err) =>
		log(`${i18n('workerFailed', 'Worker')}: ${err.message}`, 'is-error')
	);
});
