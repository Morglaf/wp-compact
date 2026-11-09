<?php
/**
 * Batch compression page.
 *
 * @package WPSquoosh
 */

defined( 'ABSPATH' ) || exit;

$settings = \WPSquoosh\Settings::get_all();
?>
<div class="wrap wpsquoosh-wrap">
	<h1><?php echo esc_html__( 'WPsquoosh — Image compression', 'wpsquoosh' ); ?></h1>
	<p class="description">
		<?php echo esc_html__( 'Compression runs in your browser (WebAssembly). Images are not sent to a third-party server.', 'wpsquoosh' ); ?>
	</p>

	<div id="wpsquoosh-stats" class="wpsquoosh-stats" aria-live="polite">
		<span class="wpsquoosh-stat" data-stat="total">—</span>
		<span class="wpsquoosh-stat" data-stat="optimized">—</span>
		<span class="wpsquoosh-stat" data-stat="bytes">—</span>
	</div>

	<form id="wpsquoosh-settings-form" class="wpsquoosh-panel">
		<h2><?php echo esc_html__( 'Settings (safe mode)', 'wpsquoosh' ); ?></h2>
		<div class="wpsquoosh-grid">
			<label>
				<?php echo esc_html__( 'JPEG quality', 'wpsquoosh' ); ?>
				<input type="number" name="jpeg_quality" min="1" max="100" value="<?php echo esc_attr( (string) $settings['jpeg_quality'] ); ?>" />
			</label>
			<label>
				<?php echo esc_html__( 'WebP quality', 'wpsquoosh' ); ?>
				<input type="number" name="webp_quality" min="1" max="100" value="<?php echo esc_attr( (string) $settings['webp_quality'] ); ?>" />
			</label>
			<label>
				<?php echo esc_html__( 'Oxipng level (0–6)', 'wpsquoosh' ); ?>
				<input type="number" name="oxipng_level" min="0" max="6" value="<?php echo esc_attr( (string) $settings['oxipng_level'] ); ?>" />
			</label>
			<label>
				<?php echo esc_html__( 'Minimum savings (%)', 'wpsquoosh' ); ?>
				<input type="number" name="min_savings_pct" min="0" max="50" step="0.5" value="<?php echo esc_attr( (string) $settings['min_savings_pct'] ); ?>" />
			</label>
			<label>
				<?php echo esc_html__( 'Concurrency', 'wpsquoosh' ); ?>
				<input type="number" name="concurrency" min="1" max="4" value="<?php echo esc_attr( (string) $settings['concurrency'] ); ?>" />
			</label>
			<label class="wpsquoosh-check">
				<input type="checkbox" name="backup_enabled" value="1" <?php checked( ! empty( $settings['backup_enabled'] ) ); ?> />
				<?php echo esc_html__( 'Create a backup before replacing', 'wpsquoosh' ); ?>
			</label>
			<label>
				<?php echo esc_html__( 'On image upload', 'wpsquoosh' ); ?>
				<select name="upload_mode">
					<option value="off" <?php selected( $settings['upload_mode'], 'off' ); ?>><?php echo esc_html__( 'Disabled', 'wpsquoosh' ); ?></option>
					<option value="compress" <?php selected( $settings['upload_mode'], 'compress' ); ?>><?php echo esc_html__( 'Compress only (same format)', 'wpsquoosh' ); ?></option>
					<option value="webp" <?php selected( $settings['upload_mode'], 'webp' ); ?>><?php echo esc_html__( 'Convert to WebP', 'wpsquoosh' ); ?></option>
				</select>
			</label>
			<label class="wpsquoosh-check">
				<input type="checkbox" name="allow_larger" value="1" <?php checked( ! empty( $settings['allow_larger'] ) ); ?> />
				<?php echo esc_html__( 'Allow a larger file (not recommended)', 'wpsquoosh' ); ?>
			</label>
		</div>

		<details class="wpsquoosh-advanced" <?php echo ! empty( $settings['advanced_enabled'] ) ? 'open' : ''; ?>>
			<summary><?php echo esc_html__( 'Advanced mode (risky)', 'wpsquoosh' ); ?></summary>
			<div class="wpsquoosh-warning" role="alert">
				<?php echo esc_html__( 'Changing format or dimensions can break the site (themes, CSS, hardcoded URLs). Use only if you know what you are doing.', 'wpsquoosh' ); ?>
			</div>
			<div class="wpsquoosh-grid">
				<label class="wpsquoosh-check">
					<input type="checkbox" name="advanced_enabled" value="1" <?php checked( ! empty( $settings['advanced_enabled'] ) ); ?> />
					<?php echo esc_html__( 'Enable advanced mode for this job', 'wpsquoosh' ); ?>
				</label>
				<label>
					<?php echo esc_html__( 'Max width (0 = keep)', 'wpsquoosh' ); ?>
					<input type="number" name="max_width" min="0" value="<?php echo esc_attr( (string) $settings['max_width'] ); ?>" />
				</label>
				<label>
					<?php echo esc_html__( 'Max height (0 = keep)', 'wpsquoosh' ); ?>
					<input type="number" name="max_height" min="0" value="<?php echo esc_attr( (string) $settings['max_height'] ); ?>" />
				</label>
				<label>
					<?php echo esc_html__( 'Convert to', 'wpsquoosh' ); ?>
					<select name="convert_to">
						<option value="" <?php selected( $settings['convert_to'], '' ); ?>><?php echo esc_html__( 'None (keep format)', 'wpsquoosh' ); ?></option>
						<option value="webp" <?php selected( $settings['convert_to'], 'webp' ); ?>>WebP</option>
						<option value="avif" <?php selected( $settings['convert_to'], 'avif' ); ?>>AVIF</option>
					</select>
				</label>
				<label>
					<?php echo esc_html__( 'AVIF quality (0–63 legacy → mapped to %)', 'wpsquoosh' ); ?>
					<input type="number" name="avif_quality" min="0" max="100" value="<?php echo esc_attr( (string) $settings['avif_quality'] ); ?>" />
				</label>
			</div>
		</details>

		<p class="wpsquoosh-actions">
			<button type="button" class="button" id="wpsquoosh-save-settings"><?php echo esc_html__( 'Save settings', 'wpsquoosh' ); ?></button>
			<button type="button" class="button button-primary" id="wpsquoosh-start"><?php echo esc_html__( 'Compress entire Media Library', 'wpsquoosh' ); ?></button>
			<button type="button" class="button button-secondary" id="wpsquoosh-migrate-webp"><?php echo esc_html__( 'Migrate to WebP + update links', 'wpsquoosh' ); ?></button>
			<button type="button" class="button" id="wpsquoosh-stop" disabled><?php echo esc_html__( 'Stop', 'wpsquoosh' ); ?></button>
		</p>
		<p class="description" id="wpsquoosh-migrate-hint">
			<?php echo esc_html__( 'Migration converts to WebP, rewrites URLs in the database, and keeps previous files tagged for later cleanup.', 'wpsquoosh' ); ?>
		</p>
	</form>

	<div class="wpsquoosh-panel">
		<h2><?php echo esc_html__( 'Progress', 'wpsquoosh' ); ?></h2>
		<div class="wpsquoosh-progress">
			<div id="wpsquoosh-progress-bar" class="wpsquoosh-progress-bar" style="width:0%"></div>
		</div>
		<p id="wpsquoosh-progress-text">0 / 0</p>
		<ul id="wpsquoosh-log" class="wpsquoosh-log" aria-live="polite"></ul>
	</div>

	<div class="wpsquoosh-panel" id="wpsquoosh-cleanup-panel">
		<h2><?php echo esc_html__( 'Legacy file cleanup', 'wpsquoosh' ); ?></h2>
		<p class="description">
			<?php echo esc_html__( 'After a WebP migration, previous JPG/PNG files (and thumbnails) remain on disk. Review the site, then purge them here.', 'wpsquoosh' ); ?>
		</p>
		<div id="wpsquoosh-legacy-stats" class="wpsquoosh-stats">
			<span class="wpsquoosh-stat" data-legacy="total">—</span>
			<span class="wpsquoosh-stat" data-legacy="bytes">—</span>
		</div>
		<p class="wpsquoosh-actions">
			<button type="button" class="button" id="wpsquoosh-legacy-refresh"><?php echo esc_html__( 'Refresh', 'wpsquoosh' ); ?></button>
			<button type="button" class="button" id="wpsquoosh-legacy-purge-selected"><?php echo esc_html__( 'Delete selected', 'wpsquoosh' ); ?></button>
			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<button type="button" class="button button-link-delete" id="wpsquoosh-legacy-purge-all"><?php echo esc_html__( 'Purge all', 'wpsquoosh' ); ?></button>
			<?php endif; ?>
		</p>
		<table class="widefat striped" id="wpsquoosh-legacy-table">
			<thead>
				<tr>
					<td class="check-column"><input type="checkbox" id="wpsquoosh-legacy-check-all" /></td>
					<th><?php echo esc_html__( 'ID', 'wpsquoosh' ); ?></th>
					<th><?php echo esc_html__( 'Title', 'wpsquoosh' ); ?></th>
					<th><?php echo esc_html__( 'Files', 'wpsquoosh' ); ?></th>
					<th><?php echo esc_html__( 'Size', 'wpsquoosh' ); ?></th>
				</tr>
			</thead>
			<tbody id="wpsquoosh-legacy-tbody">
				<tr><td colspan="5"><?php echo esc_html__( 'Loading…', 'wpsquoosh' ); ?></td></tr>
			</tbody>
		</table>
	</div>
</div>
