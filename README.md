# WPsquoosh

WordPress plugin that compresses Media Library images **in the browser** (WebAssembly / [jSquash](https://github.com/jamsinclair/jSquash), codecs derived from [Squoosh](https://github.com/GoogleChromeLabs/squoosh)). Images are not sent to a third-party server: only the compressed file is posted back to WordPress via the REST API.

Author: **morglaf** — license **GPLv2 or later**. Bundled codecs: Apache-2.0 (see `readme.txt` Credits).

Not affiliated with Google or the Squoosh project.

WordPress.org metadata: see [`readme.txt`](readme.txt).

## Features

- Batch compression of the Media Library (JPEG, PNG, WebP, AVIF)
- Compression on upload (same format or convert to WebP)
- Optional backup before replacement
- WebP migration with database URL rewriting
- Legacy file cleanup after migration
- Advanced mode (resize / format conversion) — use carefully

## Languages

English (source), Français (`fr_FR`), Español (`es_ES`), Deutsch (`de_DE`).

## Requirements

- WordPress 6.0+
- PHP 7.4+
- Modern browser with WebAssembly (admin)

## Installation

1. Copy the plugin folder to `wp-content/plugins/wpsquoosh`  
   (or install `wpsquoosh.zip`; the inner folder must be named `wpsquoosh`).
2. Activate **WPsquoosh**.
3. Open **Media → Compress (WPsquoosh)** (administrators).

## Development

```bash
npm install
npm run build
```

Watch mode: `npm run watch`.

On Windows, local `build-zip.ps1` (gitignored) builds assets and writes `dist/wpsquoosh.zip` without `node_modules` or JS sources.

Directory assets (icons/banners) live in `.wordpress-org/` and are not part of the plugin zip.

## Credits

Codecs based on Squoosh / jSquash. Plugin icon: [SVG Repo — Compression](https://www.svgrepo.com/svg/400118/compression).
