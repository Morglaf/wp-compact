<?php
/**
 * Batch compression page.
 *
 * @package WpCompact
 */

defined( 'ABSPATH' ) || exit;

$settings = \WpCompact\Settings::get_all();
?>
<div class="wrap wp-compact-wrap">
	<h1><?php echo esc_html__( 'wp-compact — Image compression', 'wp-compact' ); ?></h1>
	<p class="description">
		<?php echo esc_html__( 'Compression runs in your browser (WebAssembly). Images are not sent to a third-party server.', 'wp-compact' ); ?>
	</p>

	<div id="wp-compact-stats" class="wp-compact-stats" aria-live="polite">
		<span class="wp-compact-stat" data-stat="total">—</span>
		<span class="wp-compact-stat" data-stat="optimized">—</span>
		<span class="wp-compact-stat" data-stat="bytes">—</span>
	</div>

	<form id="wp-compact-settings-form" class="wp-compact-panel">
		<h2><?php echo esc_html__( 'Settings (safe mode)', 'wp-compact' ); ?></h2>
		<div class="wp-compact-grid">
			<label>
				<?php echo esc_html__( 'JPEG quality', 'wp-compact' ); ?>
				<input type="number" name="jpeg_quality" min="1" max="100" value="<?php echo esc_attr( (string) $settings['jpeg_quality'] ); ?>" />
			</label>
			<label>
				<?php echo esc_html__( 'WebP quality', 'wp-compact' ); ?>
				<input type="number" name="webp_quality" min="1" max="100" value="<?php echo esc_attr( (string) $settings['webp_quality'] ); ?>" />
			</label>
			<label>
				<?php echo esc_html__( 'Oxipng level (0–6)', 'wp-compact' ); ?>
				<input type="number" name="oxipng_level" min="0" max="6" value="<?php echo esc_attr( (string) $settings['oxipng_level'] ); ?>" />
			</label>
			<label>
				<?php echo esc_html__( 'Minimum savings (%)', 'wp-compact' ); ?>
				<input type="number" name="min_savings_pct" min="0" max="50" step="0.5" value="<?php echo esc_attr( (string) $settings['min_savings_pct'] ); ?>" />
			</label>
			<label>
				<?php echo esc_html__( 'Concurrency', 'wp-compact' ); ?>
				<input type="number" name="concurrency" min="1" max="4" value="<?php echo esc_attr( (string) $settings['concurrency'] ); ?>" />
			</label>
			<label class="wp-compact-check">
				<input type="checkbox" name="backup_enabled" value="1" <?php checked( ! empty( $settings['backup_enabled'] ) ); ?> />
				<?php echo esc_html__( 'Create a backup before replacing', 'wp-compact' ); ?>
			</label>
			<label>
				<?php echo esc_html__( 'On image upload', 'wp-compact' ); ?>
				<select name="upload_mode">
					<option value="off" <?php selected( $settings['upload_mode'], 'off' ); ?>><?php echo esc_html__( 'Disabled', 'wp-compact' ); ?></option>
					<option value="compress" <?php selected( $settings['upload_mode'], 'compress' ); ?>><?php echo esc_html__( 'Compress only (same format)', 'wp-compact' ); ?></option>
					<option value="webp" <?php selected( $settings['upload_mode'], 'webp' ); ?>><?php echo esc_html__( 'Convert to WebP', 'wp-compact' ); ?></option>
				</select>
			</label>
			<label class="wp-compact-check">
				<input type="checkbox" name="allow_larger" value="1" <?php checked( ! empty( $settings['allow_larger'] ) ); ?> />
				<?php echo esc_html__( 'Allow a larger file (not recommended)', 'wp-compact' ); ?>
			</label>
		</div>

		<details class="wp-compact-advanced" <?php echo ! empty( $settings['advanced_enabled'] ) ? 'open' : ''; ?>>
			<summary><?php echo esc_html__( 'Advanced mode (risky)', 'wp-compact' ); ?></summary>
			<div class="wp-compact-warning" role="alert">
				<?php echo esc_html__( 'Changing format or dimensions can break the site (themes, CSS, hardcoded URLs). Use only if you know what you are doing.', 'wp-compact' ); ?>
			</div>
			<div class="wp-compact-grid">
				<label class="wp-compact-check">
					<input type="checkbox" name="advanced_enabled" value="1" <?php checked( ! empty( $settings['advanced_enabled'] ) ); ?> />
					<?php echo esc_html__( 'Enable advanced mode for this job', 'wp-compact' ); ?>
				</label>
				<label>
					<?php echo esc_html__( 'Max width (0 = keep)', 'wp-compact' ); ?>
					<input type="number" name="max_width" min="0" value="<?php echo esc_attr( (string) $settings['max_width'] ); ?>" />
				</label>
				<label>
					<?php echo esc_html__( 'Max height (0 = keep)', 'wp-compact' ); ?>
					<input type="number" name="max_height" min="0" value="<?php echo esc_attr( (string) $settings['max_height'] ); ?>" />
				</label>
				<label>
					<?php echo esc_html__( 'Convert to', 'wp-compact' ); ?>
					<select name="convert_to">
						<option value="" <?php selected( $settings['convert_to'], '' ); ?>><?php echo esc_html__( 'None (keep format)', 'wp-compact' ); ?></option>
						<option value="webp" <?php selected( $settings['convert_to'], 'webp' ); ?>>WebP</option>
						<option value="avif" <?php selected( $settings['convert_to'], 'avif' ); ?>>AVIF</option>
					</select>
				</label>
				<label>
					<?php echo esc_html__( 'AVIF quality (0–63 legacy → mapped to %)', 'wp-compact' ); ?>
					<input type="number" name="avif_quality" min="0" max="100" value="<?php echo esc_attr( (string) $settings['avif_quality'] ); ?>" />
				</label>
			</div>
		</details>

		<p class="wp-compact-actions">
			<button type="button" class="button" id="wp-compact-save-settings"><?php echo esc_html__( 'Save settings', 'wp-compact' ); ?></button>
			<button type="button" class="button button-primary" id="wp-compact-start"><?php echo esc_html__( 'Compress entire Media Library', 'wp-compact' ); ?></button>
			<button type="button" class="button button-secondary" id="wp-compact-migrate-webp"><?php echo esc_html__( 'Migrate to WebP + update links', 'wp-compact' ); ?></button>
			<button type="button" class="button" id="wp-compact-stop" disabled><?php echo esc_html__( 'Stop', 'wp-compact' ); ?></button>
		</p>
		<p class="description" id="wp-compact-migrate-hint">
			<?php echo esc_html__( 'Migration converts to WebP, rewrites URLs in the database, and keeps previous files tagged for later cleanup.', 'wp-compact' ); ?>
		</p>
	</form>

	<div class="wp-compact-panel">
		<h2><?php echo esc_html__( 'Progress', 'wp-compact' ); ?></h2>
		<div class="wp-compact-progress">
			<div id="wp-compact-progress-bar" class="wp-compact-progress-bar" style="width:0%"></div>
		</div>
		<p id="wp-compact-progress-text">0 / 0</p>
		<ul id="wp-compact-log" class="wp-compact-log" aria-live="polite"></ul>
	</div>

	<div class="wp-compact-panel" id="wp-compact-cleanup-panel">
		<h2><?php echo esc_html__( 'Legacy file cleanup', 'wp-compact' ); ?></h2>
		<p class="description">
			<?php echo esc_html__( 'After a WebP migration, previous JPG/PNG files (and thumbnails) remain on disk. Review the site, then purge them here.', 'wp-compact' ); ?>
		</p>
		<div id="wp-compact-legacy-stats" class="wp-compact-stats">
			<span class="wp-compact-stat" data-legacy="total">—</span>
			<span class="wp-compact-stat" data-legacy="bytes">—</span>
		</div>
		<p class="wp-compact-actions">
			<button type="button" class="button" id="wp-compact-legacy-refresh"><?php echo esc_html__( 'Refresh', 'wp-compact' ); ?></button>
			<button type="button" class="button" id="wp-compact-legacy-purge-selected"><?php echo esc_html__( 'Delete selected', 'wp-compact' ); ?></button>
			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<button type="button" class="button button-link-delete" id="wp-compact-legacy-purge-all"><?php echo esc_html__( 'Purge all', 'wp-compact' ); ?></button>
			<?php endif; ?>
		</p>
		<table class="widefat striped" id="wp-compact-legacy-table">
			<thead>
				<tr>
					<td class="check-column"><input type="checkbox" id="wp-compact-legacy-check-all" /></td>
					<th><?php echo esc_html__( 'ID', 'wp-compact' ); ?></th>
					<th><?php echo esc_html__( 'Title', 'wp-compact' ); ?></th>
					<th><?php echo esc_html__( 'Files', 'wp-compact' ); ?></th>
					<th><?php echo esc_html__( 'Size', 'wp-compact' ); ?></th>
				</tr>
			</thead>
			<tbody id="wp-compact-legacy-tbody">
				<tr><td colspan="5"><?php echo esc_html__( 'Loading…', 'wp-compact' ); ?></td></tr>
			</tbody>
		</table>
	</div>
</div>
