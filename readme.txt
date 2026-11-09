=== WPsquoosh ===
Contributors: morglaf
Tags: images, compression, webp, media library, optimization
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Compress Media Library images in your browser with Squoosh-derived WASM codecs. No third-party upload of your images.

== Description ==

WPsquoosh compresses WordPress Media Library images **in the browser** using WebAssembly codecs derived from [Squoosh](https://github.com/GoogleChromeLabs/squoosh) via [jSquash](https://github.com/jamsinclair/jSquash). Images are not sent to a third-party server; only the compressed file is posted back to your WordPress site through the REST API.

**Features**

* Batch compression of the Media Library (JPEG, PNG, WebP, AVIF)
* Optional compression on upload (same format or convert to WebP)
* Optional backup before replacing originals
* WebP migration with database URL rewriting
* Legacy file cleanup after migration
* Advanced mode (resize / format conversion) — use with care

**Languages**

English (source), Français, Español, Deutsch.

**Trademark notice**

WPsquoosh is an independent plugin and is **not** affiliated with, endorsed by, or sponsored by Google or the Squoosh project. “Squoosh” refers to the open-source image compression project whose codecs are used under compatible licenses.

== Installation ==

1. Upload the `wpsquoosh` folder to `/wp-content/plugins/`, or install the zip from the Plugins screen.
2. Activate **WPsquoosh** through the Plugins menu.
3. Open **Media → Compress (WPsquoosh)** (administrators) to configure settings and run batch jobs.
4. Upload compression follows the saved settings whenever a user with upload capability adds images.

== Frequently Asked Questions ==

= Are my images uploaded to an external service? =

No. Compression runs locally in the admin browser. WordPress only receives the compressed file via your site’s REST API.

= Who can change settings or migrate to WebP? =

Saving settings, batch jobs, WebP migration, and legacy cleanup require the `manage_options` capability (typically administrators). Replacing an individual attachment still requires permission to edit that attachment.

= What happens during WebP migration? =

Files are converted to WebP, attachment metadata is updated, URLs in the database can be rewritten, and previous files are tagged for later cleanup. Review your site carefully before purging legacy files.

= Are backups kept on uninstall? =

Yes. Optional backup files under uploads and attachment meta are left in place so you do not lose originals. Only the plugin settings option is removed on uninstall.

= How do I rebuild front-end assets? =

Development sources and build tools live in the plugin repository. Run `npm install` then `npm run build`. See the project README for details.

== Changelog ==

= 1.0.0 =
* Initial public release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.

== Credits ==

* Image codecs based on Squoosh / jSquash (Apache License 2.0).
* Plugin icon: “Compression” from [SVG Repo](https://www.svgrepo.com/svg/400118/compression).
* Author: morglaf.
