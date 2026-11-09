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
	<h1><?php echo esc_html__( 'WPsquoosh — Compression d’images', 'wpsquoosh' ); ?></h1>
	<p class="description">
		<?php echo esc_html__( 'La compression s’exécute dans votre navigateur (WebAssembly). Les images ne sont pas envoyées à un serveur tiers.', 'wpsquoosh' ); ?>
	</p>

	<div id="wpsquoosh-stats" class="wpsquoosh-stats" aria-live="polite">
		<span class="wpsquoosh-stat" data-stat="total">—</span>
		<span class="wpsquoosh-stat" data-stat="optimized">—</span>
		<span class="wpsquoosh-stat" data-stat="bytes">—</span>
	</div>

	<form id="wpsquoosh-settings-form" class="wpsquoosh-panel">
		<h2><?php echo esc_html__( 'Réglages (mode sûr)', 'wpsquoosh' ); ?></h2>
		<div class="wpsquoosh-grid">
			<label>
				<?php echo esc_html__( 'Qualité JPEG', 'wpsquoosh' ); ?>
				<input type="number" name="jpeg_quality" min="1" max="100" value="<?php echo esc_attr( (string) $settings['jpeg_quality'] ); ?>" />
			</label>
			<label>
				<?php echo esc_html__( 'Qualité WebP', 'wpsquoosh' ); ?>
				<input type="number" name="webp_quality" min="1" max="100" value="<?php echo esc_attr( (string) $settings['webp_quality'] ); ?>" />
			</label>
			<label>
				<?php echo esc_html__( 'Niveau Oxipng (0–6)', 'wpsquoosh' ); ?>
				<input type="number" name="oxipng_level" min="0" max="6" value="<?php echo esc_attr( (string) $settings['oxipng_level'] ); ?>" />
			</label>
			<label>
				<?php echo esc_html__( 'Gain minimum (%)', 'wpsquoosh' ); ?>
				<input type="number" name="min_savings_pct" min="0" max="50" step="0.5" value="<?php echo esc_attr( (string) $settings['min_savings_pct'] ); ?>" />
			</label>
			<label>
				<?php echo esc_html__( 'Concurrence', 'wpsquoosh' ); ?>
				<input type="number" name="concurrency" min="1" max="4" value="<?php echo esc_attr( (string) $settings['concurrency'] ); ?>" />
			</label>
			<label class="wpsquoosh-check">
				<input type="checkbox" name="backup_enabled" value="1" <?php checked( ! empty( $settings['backup_enabled'] ) ); ?> />
				<?php echo esc_html__( 'Créer une backup avant remplacement', 'wpsquoosh' ); ?>
			</label>
			<label>
				<?php echo esc_html__( 'À l’ajout d’images (upload)', 'wpsquoosh' ); ?>
				<select name="upload_mode">
					<option value="off" <?php selected( $settings['upload_mode'], 'off' ); ?>><?php echo esc_html__( 'Désactivé', 'wpsquoosh' ); ?></option>
					<option value="compress" <?php selected( $settings['upload_mode'], 'compress' ); ?>><?php echo esc_html__( 'Compression seule (même format)', 'wpsquoosh' ); ?></option>
					<option value="webp" <?php selected( $settings['upload_mode'], 'webp' ); ?>><?php echo esc_html__( 'Convertir en WebP', 'wpsquoosh' ); ?></option>
				</select>
			</label>
			<label class="wpsquoosh-check">
				<input type="checkbox" name="allow_larger" value="1" <?php checked( ! empty( $settings['allow_larger'] ) ); ?> />
				<?php echo esc_html__( 'Autoriser un fichier plus gros (non recommandé)', 'wpsquoosh' ); ?>
			</label>
		</div>

		<details class="wpsquoosh-advanced" <?php echo ! empty( $settings['advanced_enabled'] ) ? 'open' : ''; ?>>
			<summary><?php echo esc_html__( 'Mode avancé (risqué)', 'wpsquoosh' ); ?></summary>
			<div class="wpsquoosh-warning" role="alert">
				<?php echo esc_html__( 'Changer le format ou les dimensions peut casser le site (thèmes, CSS, URLs). Utilisez uniquement si vous savez ce que vous faites.', 'wpsquoosh' ); ?>
			</div>
			<div class="wpsquoosh-grid">
				<label class="wpsquoosh-check">
					<input type="checkbox" name="advanced_enabled" value="1" <?php checked( ! empty( $settings['advanced_enabled'] ) ); ?> />
					<?php echo esc_html__( 'Activer le mode avancé pour ce job', 'wpsquoosh' ); ?>
				</label>
				<label>
					<?php echo esc_html__( 'Largeur max (0 = garder)', 'wpsquoosh' ); ?>
					<input type="number" name="max_width" min="0" value="<?php echo esc_attr( (string) $settings['max_width'] ); ?>" />
				</label>
				<label>
					<?php echo esc_html__( 'Hauteur max (0 = garder)', 'wpsquoosh' ); ?>
					<input type="number" name="max_height" min="0" value="<?php echo esc_attr( (string) $settings['max_height'] ); ?>" />
				</label>
				<label>
					<?php echo esc_html__( 'Convertir vers', 'wpsquoosh' ); ?>
					<select name="convert_to">
						<option value="" <?php selected( $settings['convert_to'], '' ); ?>><?php echo esc_html__( 'Aucun (garder le format)', 'wpsquoosh' ); ?></option>
						<option value="webp" <?php selected( $settings['convert_to'], 'webp' ); ?>>WebP</option>
						<option value="avif" <?php selected( $settings['convert_to'], 'avif' ); ?>>AVIF</option>
					</select>
				</label>
				<label>
					<?php echo esc_html__( 'Qualité AVIF (0–63 legacy → mappé en %)', 'wpsquoosh' ); ?>
					<input type="number" name="avif_quality" min="0" max="100" value="<?php echo esc_attr( (string) $settings['avif_quality'] ); ?>" />
				</label>
			</div>
		</details>

		<p class="wpsquoosh-actions">
			<button type="button" class="button" id="wpsquoosh-save-settings"><?php echo esc_html__( 'Enregistrer les réglages', 'wpsquoosh' ); ?></button>
			<button type="button" class="button button-primary" id="wpsquoosh-start"><?php echo esc_html__( 'Compresser toute la médiathèque', 'wpsquoosh' ); ?></button>
			<button type="button" class="button button-secondary" id="wpsquoosh-migrate-webp"><?php echo esc_html__( 'Migrer vers WebP + maj liens', 'wpsquoosh' ); ?></button>
			<button type="button" class="button" id="wpsquoosh-stop" disabled><?php echo esc_html__( 'Arrêter', 'wpsquoosh' ); ?></button>
		</p>
		<p class="description" id="wpsquoosh-migrate-hint">
			<?php echo esc_html__( 'La migration convertit en WebP, réécrit les URLs en base, et conserve les anciens fichiers tagués pour nettoyage ultérieur.', 'wpsquoosh' ); ?>
		</p>
	</form>

	<div class="wpsquoosh-panel">
		<h2><?php echo esc_html__( 'Progression', 'wpsquoosh' ); ?></h2>
		<div class="wpsquoosh-progress">
			<div id="wpsquoosh-progress-bar" class="wpsquoosh-progress-bar" style="width:0%"></div>
		</div>
		<p id="wpsquoosh-progress-text">0 / 0</p>
		<ul id="wpsquoosh-log" class="wpsquoosh-log" aria-live="polite"></ul>
	</div>

	<div class="wpsquoosh-panel" id="wpsquoosh-cleanup-panel">
		<h2><?php echo esc_html__( 'Nettoyage des fichiers legacy', 'wpsquoosh' ); ?></h2>
		<p class="description">
			<?php echo esc_html__( 'Après une migration WebP, les anciens JPG/PNG (et miniatures) restent sur le disque. Vérifiez le site, puis purgez-les ici.', 'wpsquoosh' ); ?>
		</p>
		<div id="wpsquoosh-legacy-stats" class="wpsquoosh-stats">
			<span class="wpsquoosh-stat" data-legacy="total">—</span>
			<span class="wpsquoosh-stat" data-legacy="bytes">—</span>
		</div>
		<p class="wpsquoosh-actions">
			<button type="button" class="button" id="wpsquoosh-legacy-refresh"><?php echo esc_html__( 'Actualiser', 'wpsquoosh' ); ?></button>
			<button type="button" class="button" id="wpsquoosh-legacy-purge-selected"><?php echo esc_html__( 'Supprimer la sélection', 'wpsquoosh' ); ?></button>
			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<button type="button" class="button button-link-delete" id="wpsquoosh-legacy-purge-all"><?php echo esc_html__( 'Tout purger', 'wpsquoosh' ); ?></button>
			<?php endif; ?>
		</p>
		<table class="widefat striped" id="wpsquoosh-legacy-table">
			<thead>
				<tr>
					<td class="check-column"><input type="checkbox" id="wpsquoosh-legacy-check-all" /></td>
					<th><?php echo esc_html__( 'ID', 'wpsquoosh' ); ?></th>
					<th><?php echo esc_html__( 'Titre', 'wpsquoosh' ); ?></th>
					<th><?php echo esc_html__( 'Fichiers', 'wpsquoosh' ); ?></th>
					<th><?php echo esc_html__( 'Poids', 'wpsquoosh' ); ?></th>
				</tr>
			</thead>
			<tbody id="wpsquoosh-legacy-tbody">
				<tr><td colspan="5"><?php echo esc_html__( 'Chargement…', 'wpsquoosh' ); ?></td></tr>
			</tbody>
		</table>
	</div>
</div>
