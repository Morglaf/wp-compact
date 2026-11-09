import * as esbuild from 'esbuild';
import { cpSync, mkdirSync, readdirSync, existsSync, writeFileSync, rmSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const dist = join(__dirname, 'assets', 'dist');
const wasmDest = join(dist, 'wasm');
const watch = process.argv.includes('--watch');

rmSync(dist, { recursive: true, force: true });
mkdirSync(wasmDest, { recursive: true });

function walkCopyWasm(dir) {
	if (!existsSync(dir)) return;
	for (const entry of readdirSync(dir, { withFileTypes: true })) {
		const full = join(dir, entry.name);
		if (entry.isDirectory()) {
			walkCopyWasm(full);
			continue;
		}
		if (!entry.name.endsWith('.wasm')) continue;
		// Prefer non-parallel / non-mt / non-simd variants when colliding on basename.
		const dest = join(wasmDest, entry.name);
		if (existsSync(dest)) {
			const prefer =
				!full.includes('pkg-parallel') &&
				!full.includes('_mt') &&
				!full.includes('_simd');
			if (!prefer) continue;
		}
		cpSync(full, dest);
	}
}

const packages = [
	'@jsquash/jpeg',
	'@jsquash/png',
	'@jsquash/oxipng',
	'@jsquash/webp',
	'@jsquash/avif',
	'@jsquash/resize',
];

for (const pkg of packages) {
	walkCopyWasm(join(__dirname, 'node_modules', pkg));
}

const wasmFiles = readdirSync(wasmDest).filter((f) => f.endsWith('.wasm'));
writeFileSync(join(wasmDest, 'manifest.json'), JSON.stringify(wasmFiles, null, 2));

cpSync(join(__dirname, 'assets', 'src', 'admin.css'), join(dist, 'admin.css'));
writeFileSync(join(dist, 'index.php'), "<?php\n// Silence is golden.\n");
writeFileSync(join(wasmDest, 'index.php'), "<?php\n// Silence is golden.\n");

const common = {
	bundle: true,
	target: ['es2020'],
	sourcemap: true,
	logLevel: 'info',
};

const workerBuild = {
	...common,
	entryPoints: [join(__dirname, 'assets', 'src', 'compress-worker.js')],
	outfile: join(dist, 'compress-worker.js'),
	format: 'esm',
	platform: 'browser',
};

const batchBuild = {
	...common,
	entryPoints: [join(__dirname, 'assets', 'src', 'admin-batch.js')],
	outfile: join(dist, 'admin-batch.js'),
	format: 'iife',
	platform: 'browser',
};

const uploadBuild = {
	...common,
	entryPoints: [join(__dirname, 'assets', 'src', 'admin-upload.js')],
	outfile: join(dist, 'admin-upload.js'),
	format: 'iife',
	platform: 'browser',
};

async function run() {
	if (watch) {
		const ctxs = await Promise.all([
			esbuild.context(workerBuild),
			esbuild.context(batchBuild),
			esbuild.context(uploadBuild),
		]);
		await Promise.all(ctxs.map((c) => c.watch()));
		console.log('Watching…');
	} else {
		await Promise.all([
			esbuild.build(workerBuild),
			esbuild.build(batchBuild),
			esbuild.build(uploadBuild),
		]);
		console.log('Build complete. WASM files:', wasmFiles.length);
	}
}

run().catch((err) => {
	console.error(err);
	process.exit(1);
});
