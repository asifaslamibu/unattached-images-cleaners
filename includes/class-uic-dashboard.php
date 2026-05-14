<?php
/**
 * Plugin Dashboard – overview, stats, quick links.
 *
 * @package UnattachedImagesCleaner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UIC_Dashboard {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 5 );
	}

	/**
	 * Register the top-level "Media Tools" menu with three children.
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Media Tools', 'unattached-images-cleaner' ),
			__( 'Media Tools', 'unattached-images-cleaner' ),
			UIC_CAP,
			UIC_MENU_SLUG,
			array( $this, 'render' ),
			'dashicons-format-image',
			81
		);

		// Renames the auto-created first child from "Media Tools" to "Dashboard".
		add_submenu_page(
			UIC_MENU_SLUG,
			__( 'Dashboard', 'unattached-images-cleaner' ),
			__( 'Dashboard', 'unattached-images-cleaner' ),
			UIC_CAP,
			UIC_MENU_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Render the dashboard.
	 */
	public function render() {
		if ( ! current_user_can( UIC_CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'unattached-images-cleaner' ) );
		}

		$stats    = UIC_Helpers::get_stats();
		$supports = UIC_Helpers::server_supports_webp();
		$folders  = UIC_Helpers::get_upload_folders();
		?>
		<div class="wrap uic-dashboard">
			<h1><?php esc_html_e( 'Media Tools Dashboard', 'unattached-images-cleaner' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Manage your media library: clean up unattached images and bulk-convert to WebP.', 'unattached-images-cleaner' ); ?>
			</p>

			<div class="uic-stats">
				<div class="uic-stat">
					<div class="uic-stat-value"><?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?></div>
					<div class="uic-stat-label"><?php esc_html_e( 'Total images', 'unattached-images-cleaner' ); ?></div>
				</div>
				<div class="uic-stat uic-stat-warning">
					<div class="uic-stat-value"><?php echo esc_html( number_format_i18n( $stats['unattached'] ) ); ?></div>
					<div class="uic-stat-label"><?php esc_html_e( 'Unattached', 'unattached-images-cleaner' ); ?></div>
				</div>
				<div class="uic-stat">
					<div class="uic-stat-value"><?php echo esc_html( number_format_i18n( $stats['convertible'] ) ); ?></div>
					<div class="uic-stat-label"><?php esc_html_e( 'Convertible to WebP', 'unattached-images-cleaner' ); ?></div>
				</div>
				<div class="uic-stat uic-stat-success">
					<div class="uic-stat-value"><?php echo esc_html( number_format_i18n( $stats['webp'] ) ); ?></div>
					<div class="uic-stat-label"><?php esc_html_e( 'Already WebP', 'unattached-images-cleaner' ); ?></div>
				</div>
			</div>

			<h2><?php esc_html_e( 'Breakdown by format', 'unattached-images-cleaner' ); ?></h2>
			<table class="widefat striped uic-format-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Format', 'unattached-images-cleaner' ); ?></th>
						<th style="text-align:right;"><?php esc_html_e( 'Count', 'unattached-images-cleaner' ); ?></th>
						<th style="text-align:right;"><?php esc_html_e( 'Share', 'unattached-images-cleaner' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php
				$map = UIC_Helpers::format_map();
				foreach ( $map as $key => $info ) {
					$mime  = $info[0];
					$label = $info[1];
					$count = isset( $stats['by_format'][ $mime ] ) ? $stats['by_format'][ $mime ] : 0;
					$pct   = $stats['total'] > 0 ? round( $count / $stats['total'] * 100, 1 ) : 0;
					?>
					<tr>
						<td><strong><?php echo esc_html( $label ); ?></strong> <code><?php echo esc_html( $mime ); ?></code></td>
						<td style="text-align:right;"><?php echo esc_html( number_format_i18n( $count ) ); ?></td>
						<td style="text-align:right;">
							<?php echo esc_html( $pct ); ?>%
							<span class="uic-bar" style="--p:<?php echo esc_attr( $pct ); ?>%"></span>
						</td>
					</tr>
					<?php
				}
				?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Tools', 'unattached-images-cleaner' ); ?></h2>
			<div class="uic-tools">
				<a class="uic-tool-card" href="<?php echo esc_url( admin_url( 'admin.php?page=unattached-images-cleaner' ) ); ?>">
					<span class="dashicons dashicons-trash"></span>
					<h3><?php esc_html_e( 'Unattached Cleaner', 'unattached-images-cleaner' ); ?></h3>
					<p><?php esc_html_e( 'Find and permanently delete images that aren\'t linked to any post or page. Filter by format and upload folder, select all matching, or pick individually.', 'unattached-images-cleaner' ); ?></p>
					<span class="button button-primary"><?php esc_html_e( 'Open Cleaner', 'unattached-images-cleaner' ); ?></span>
				</a>

				<a class="uic-tool-card" href="<?php echo esc_url( admin_url( 'admin.php?page=uic-webp-converter' ) ); ?>">
					<span class="dashicons dashicons-images-alt2"></span>
					<h3><?php esc_html_e( 'WebP Converter', 'unattached-images-cleaner' ); ?></h3>
					<p><?php esc_html_e( 'Bulk-convert JPEG, PNG, GIF, and BMP images to WebP. Choose quality, replace originals or keep alongside, filter by folder.', 'unattached-images-cleaner' ); ?></p>
					<span class="button button-primary"><?php esc_html_e( 'Open Converter', 'unattached-images-cleaner' ); ?></span>
				</a>
			</div>

			<h2><?php esc_html_e( 'Environment', 'unattached-images-cleaner' ); ?></h2>
			<table class="widefat striped uic-env-table">
				<tr>
					<td><?php esc_html_e( 'WebP support', 'unattached-images-cleaner' ); ?></td>
					<td>
						<?php if ( $supports ) : ?>
							<span class="uic-ok">✓ <?php esc_html_e( 'Available (server can write WebP)', 'unattached-images-cleaner' ); ?></span>
						<?php else : ?>
							<span class="uic-bad">✗ <?php esc_html_e( 'Not available – GD or Imagick lacks WebP write support', 'unattached-images-cleaner' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Image editor', 'unattached-images-cleaner' ); ?></td>
					<td>
						<?php
						$editor = _wp_image_editor_choose();
						echo $editor ? esc_html( $editor ) : esc_html__( 'None available', 'unattached-images-cleaner' );
						?>
					</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Upload folders detected', 'unattached-images-cleaner' ); ?></td>
					<td><?php echo esc_html( count( $folders ) ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'PHP / WP version', 'unattached-images-cleaner' ); ?></td>
					<td><?php echo esc_html( PHP_VERSION . ' / ' . get_bloginfo( 'version' ) ); ?></td>
				</tr>
			</table>
		</div>
		<?php
	}
}
