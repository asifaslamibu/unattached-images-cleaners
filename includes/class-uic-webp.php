<?php
/**
 * WebP converter.
 *
 * @package UnattachedImagesCleaner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UIC_WebP_Converter {

	const PER_PAGE        = 30;
	const BATCH_LIMIT     = 20;
	const DEFAULT_Q       = 80;
	const PAGE_SLUG       = 'uic-webp-converter';
	// Only formats that make sense to convert to WebP.
	const ALLOWED_FORMATS = array( 'jpg', 'png', 'gif', 'bmp' );

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu',                  array( $this, 'register_submenu' ) );
		add_action( 'admin_post_uic_webp_convert', array( $this, 'handle_convert' ) );
		add_action( 'admin_notices',               array( $this, 'maybe_show_notice' ) );
	}

	public function register_submenu() {
		add_submenu_page(
			UIC_MENU_SLUG,
			__( 'WebP Converter', 'unattached-images-cleaner' ),
			__( 'WebP Converter', 'unattached-images-cleaner' ),
			UIC_CAP,
			self::PAGE_SLUG,
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Base query for convertible images (filters applied).
	 *
	 * @param int   $paged
	 * @param bool  $unattached_only
	 * @param array $filters
	 * @return array
	 */
	private function base_query_args( $paged, $unattached_only, $filters ) {
		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => UIC_Helpers::selected_mimes( self::ALLOWED_FORMATS ),
			'posts_per_page' => self::PER_PAGE,
			'paged'          => max( 1, (int) $paged ),
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		if ( $unattached_only ) {
			$args['post_parent'] = 0;
		}

		// apply_filters_to_query_args overrides post_mime_type if formats are selected.
		return UIC_Helpers::apply_filters_to_query_args( $args, $filters );
	}

	public function render_admin_page() {
		if ( ! current_user_can( UIC_CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'unattached-images-cleaner' ) );
		}

		$supports = UIC_Helpers::server_supports_webp();
		$paged    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$only_un  = ! empty( $_GET['unattached_only'] );
		$filters  = UIC_Helpers::parse_filters( self::ALLOWED_FORMATS );

		$query = new WP_Query( $this->base_query_args( $paged, $only_un, $filters ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WebP Converter', 'unattached-images-cleaner' ); ?></h1>
			<p>
				<?php esc_html_e( 'Bulk-convert JPEG, PNG, GIF, and BMP images to WebP. WebP files are typically 25–35% smaller at equivalent visual quality.', 'unattached-images-cleaner' ); ?>
			</p>

			<?php if ( ! $supports ) : ?>
				<div class="uic-error">
					<strong><?php esc_html_e( 'WebP not supported on this server.', 'unattached-images-cleaner' ); ?></strong><br>
					<?php esc_html_e( 'Your PHP image library (GD or Imagick) does not have WebP write support. Ask your host to enable it.', 'unattached-images-cleaner' ); ?>
				</div>
			<?php endif; ?>

			<div class="uic-warn">
				<strong><?php esc_html_e( 'Before you start:', 'unattached-images-cleaner' ); ?></strong>
				<ul style="margin:6px 0 0 18px;list-style:disc;">
					<li><?php esc_html_e( 'Take a full backup. Replace mode deletes the original file and its generated thumbnails.', 'unattached-images-cleaner' ); ?></li>
					<li><?php esc_html_e( 'Hard-coded URLs to the old file in post content / theme options / page-builder data must be updated separately.', 'unattached-images-cleaner' ); ?></li>
					<li><?php
					/* translators: %d: batch limit */
					printf( esc_html__( 'Processing is limited to %d images per submission to avoid PHP timeouts. Submit again to continue.', 'unattached-images-cleaner' ), self::BATCH_LIMIT );
					?></li>
				</ul>
			</div>

			<?php
			UIC_Helpers::render_filter_form(
				self::PAGE_SLUG,
				self::ALLOWED_FORMATS,
				$filters,
				array( 'unattached_only' => $only_un ? '1' : '' )
			);
			?>

			<form method="get" action="" style="margin:6px 0 14px;">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
				<?php foreach ( $filters['formats'] as $f ) : ?>
					<input type="hidden" name="formats[]" value="<?php echo esc_attr( $f ); ?>" />
				<?php endforeach; ?>
				<?php if ( ! empty( $filters['folder'] ) ) : ?>
					<input type="hidden" name="folder" value="<?php echo esc_attr( $filters['folder'] ); ?>" />
				<?php endif; ?>
				<label>
					<input type="checkbox" name="unattached_only" value="1" <?php checked( $only_un ); ?> onchange="this.form.submit()" />
					<?php esc_html_e( 'Show only unattached images', 'unattached-images-cleaner' ); ?>
				</label>
			</form>

			<?php if ( ! $query->have_posts() ) : ?>
				<div class="uic-empty">
					<strong><?php esc_html_e( 'No images match your filters.', 'unattached-images-cleaner' ); ?></strong>
				</div>
			<?php else : ?>
				<form id="uic-webp-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="uic_webp_convert" />
					<input type="hidden" name="unattached_only" value="<?php echo $only_un ? '1' : '0'; ?>" />
					<?php
					UIC_Helpers::render_filter_hidden_inputs( $filters );
					wp_nonce_field( 'uic_webp_convert_action', 'uic_nonce' );
					?>

					<div class="uic-toolbar">
						<label>
							<input type="checkbox" id="uic-select-all" />
							<strong><?php esc_html_e( 'Select all on this page', 'unattached-images-cleaner' ); ?></strong>
						</label>

						<span class="uic-quality">
							<label for="uic-quality"><?php esc_html_e( 'Quality:', 'unattached-images-cleaner' ); ?></label>
							<input id="uic-quality" type="range" name="quality" min="40" max="100" step="1" value="<?php echo esc_attr( self::DEFAULT_Q ); ?>" />
							<output id="uic-quality-output"><?php echo esc_html( self::DEFAULT_Q ); ?></output>
						</span>

						<span>
							<label>
								<input type="radio" name="mode" value="replace" checked />
								<?php esc_html_e( 'Replace originals', 'unattached-images-cleaner' ); ?>
							</label>
							&nbsp;&nbsp;
							<label>
								<input type="radio" name="mode" value="keep" />
								<?php esc_html_e( 'Keep originals', 'unattached-images-cleaner' ); ?>
							</label>
						</span>

						<button type="submit" class="button button-primary" <?php disabled( ! $supports ); ?>>
							<?php esc_html_e( 'Convert Selected', 'unattached-images-cleaner' ); ?>
						</button>

						<span class="uic-divider"></span>

						<button type="submit" name="select_all_matching" value="1" class="button button-secondary" <?php disabled( ! $supports ); ?>>
							<?php esc_html_e( 'Convert All Matching', 'unattached-images-cleaner' ); ?>
						</button>

						<span class="description">
							<?php
							printf(
								esc_html__( 'Matching: %1$s · batch limit %2$s', 'unattached-images-cleaner' ),
								esc_html( number_format_i18n( (int) $query->found_posts ) ),
								esc_html( number_format_i18n( self::BATCH_LIMIT ) )
							);
							?>
						</span>
					</div>

					<div class="uic-grid">
						<?php while ( $query->have_posts() ) : $query->the_post(); ?>
							<?php
							$id        = get_the_ID();
							$thumb_url = wp_get_attachment_image_url( $id, 'medium' );
							if ( ! $thumb_url ) {
								$thumb_url = wp_get_attachment_url( $id );
							}
							$file_path = get_attached_file( $id );
							$filename  = $file_path ? wp_basename( $file_path ) : '';
							$filesize  = ( $file_path && file_exists( $file_path ) ) ? size_format( (int) filesize( $file_path ) ) : '—';
							$mime      = (string) get_post_mime_type( $id );
							?>
							<label class="uic-card">
								<input type="checkbox" name="ids[]" value="<?php echo esc_attr( $id ); ?>" />
								<img src="<?php echo esc_url( $thumb_url ); ?>" alt="" loading="lazy" />
								<div class="uic-filename"><?php echo esc_html( $filename ); ?></div>
								<div class="uic-meta">
									<?php echo esc_html( strtoupper( str_replace( 'image/', '', $mime ) ) ); ?> &middot;
									<?php echo esc_html( $filesize ); ?>
								</div>
							</label>
						<?php endwhile; ?>
					</div>

					<?php $this->render_pagination( $query, $paged, $only_un, $filters ); ?>
				</form>
			<?php endif; ?>
			<?php wp_reset_postdata(); ?>
		</div>
		<?php
	}

	private function render_pagination( $query, $paged, $only_un, $filters ) {
		if ( $query->max_num_pages <= 1 ) {
			return;
		}
		$base = add_query_arg(
			array(
				'page'            => self::PAGE_SLUG,
				'unattached_only' => $only_un ? 1 : false,
				'folder'          => ! empty( $filters['folder'] ) ? $filters['folder'] : false,
				'paged'           => '%#%',
			),
			admin_url( 'admin.php' )
		);
		if ( ! empty( $filters['formats'] ) ) {
			foreach ( $filters['formats'] as $f ) {
				$base = add_query_arg( array( 'formats[]' => $f ), $base );
			}
		}

		$links = paginate_links(
			array(
				'base'      => $base,
				'format'    => '',
				'current'   => $paged,
				'total'     => $query->max_num_pages,
				'prev_text' => __( '« Previous', 'unattached-images-cleaner' ),
				'next_text' => __( 'Next »', 'unattached-images-cleaner' ),
			)
		);
		if ( $links ) {
			echo '<div class="tablenav"><div class="tablenav-pages" style="margin:18px 0;">' . wp_kses_post( $links ) . '</div></div>';
		}
	}

	public function handle_convert() {
		if ( ! current_user_can( UIC_CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'unattached-images-cleaner' ) );
		}
		check_admin_referer( 'uic_webp_convert_action', 'uic_nonce' );

		if ( ! UIC_Helpers::server_supports_webp() ) {
			wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'uic_unsupp' => 1 ), admin_url( 'admin.php' ) ) );
			exit;
		}

		@set_time_limit( 300 );

		$select_all = ! empty( $_POST['select_all_matching'] );
		$only_un    = ! empty( $_POST['unattached_only'] ) && '1' === (string) $_POST['unattached_only'];
		$quality    = isset( $_POST['quality'] ) ? max( 1, min( 100, (int) $_POST['quality'] ) ) : self::DEFAULT_Q;
		$mode       = ( isset( $_POST['mode'] ) && 'keep' === $_POST['mode'] ) ? 'keep' : 'replace';

		if ( $select_all ) {
			$filters = UIC_Helpers::filters_from_post( self::ALLOWED_FORMATS );
			$args    = $this->base_query_args( 1, $only_un, $filters );
			$ids     = UIC_Helpers::get_all_matching_ids( $args );
		} else {
			$ids = isset( $_POST['ids'] ) ? (array) wp_unslash( $_POST['ids'] ) : array();
			$ids = array_filter( array_map( 'intval', $ids ) );
		}

		$ids = array_slice( $ids, 0, self::BATCH_LIMIT );

		$converted = 0;
		$skipped   = 0;
		$failed    = 0;
		foreach ( $ids as $id ) {
			$r = $this->convert_attachment( $id, $quality, $mode );
			if ( true === $r ) {
				$converted++;
			} elseif ( 'skipped' === $r ) {
				$skipped++;
			} else {
				$failed++;
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'          => self::PAGE_SLUG,
					'uic_converted' => $converted,
					'uic_skipped'   => $skipped,
					'uic_failed'    => $failed,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Convert a single attachment to WebP.
	 *
	 * @param int    $id
	 * @param int    $quality
	 * @param string $mode 'replace' | 'keep'
	 * @return true|string|false true | 'skipped' | false
	 */
	private function convert_attachment( $id, $quality, $mode ) {
		$file = get_attached_file( $id );
		if ( ! $file || ! file_exists( $file ) ) {
			return false;
		}

		$mime = get_post_mime_type( $id );
		if ( 'image/webp' === $mime ) {
			return 'skipped';
		}
		if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/gif', 'image/bmp' ), true ) ) {
			return 'skipped';
		}

		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) ) {
			return false;
		}
		$editor->set_quality( $quality );

		$new_file = preg_replace( '/\.(jpe?g|png|gif|bmp)$/i', '.webp', $file );
		if ( $new_file === $file ) {
			$new_file = $file . '.webp';
		}

		$saved = $editor->save( $new_file, 'image/webp' );
		if ( is_wp_error( $saved ) || empty( $saved['path'] ) || ! file_exists( $saved['path'] ) ) {
			return false;
		}

		if ( 'keep' === $mode ) {
			return true;
		}

		// Replace mode.
		$old_metadata = wp_get_attachment_metadata( $id );
		$file_dir     = trailingslashit( dirname( $file ) );
		if ( ! empty( $old_metadata['sizes'] ) && is_array( $old_metadata['sizes'] ) ) {
			foreach ( $old_metadata['sizes'] as $size_data ) {
				if ( ! empty( $size_data['file'] ) ) {
					$size_file = $file_dir . $size_data['file'];
					if ( file_exists( $size_file ) && $size_file !== $saved['path'] ) {
						@unlink( $size_file );
					}
				}
			}
		}
		if ( $file !== $saved['path'] && file_exists( $file ) ) {
			@unlink( $file );
		}

		$upload_dir = wp_get_upload_dir();
		$basedir    = trailingslashit( $upload_dir['basedir'] );
		$relative   = ltrim( str_replace( $basedir, '', $saved['path'] ), '/\\' );
		update_post_meta( $id, '_wp_attached_file', $relative );

		$new_guid = trailingslashit( $upload_dir['baseurl'] ) . $relative;
		wp_update_post(
			array(
				'ID'             => $id,
				'post_mime_type' => 'image/webp',
				'guid'           => $new_guid,
			)
		);

		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		$new_metadata = wp_generate_attachment_metadata( $id, $saved['path'] );
		if ( ! empty( $new_metadata ) ) {
			wp_update_attachment_metadata( $id, $new_metadata );
		}

		return true;
	}

	public function maybe_show_notice() {
		if ( ! isset( $_GET['page'] ) || self::PAGE_SLUG !== $_GET['page'] ) {
			return;
		}

		if ( ! empty( $_GET['uic_unsupp'] ) ) {
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html__( 'Conversion aborted: WebP is not supported on this server.', 'unattached-images-cleaner' )
			);
			return;
		}

		if ( ! isset( $_GET['uic_converted'] ) && ! isset( $_GET['uic_failed'] ) && ! isset( $_GET['uic_skipped'] ) ) {
			return;
		}

		$converted = (int) ( $_GET['uic_converted'] ?? 0 );
		$skipped   = (int) ( $_GET['uic_skipped']   ?? 0 );
		$failed    = (int) ( $_GET['uic_failed']    ?? 0 );

		if ( $converted > 0 ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html( sprintf( _n( '%d image converted to WebP.', '%d images converted to WebP.', $converted, 'unattached-images-cleaner' ), $converted ) )
			);
		}
		if ( $skipped > 0 ) {
			printf(
				'<div class="notice notice-info is-dismissible"><p>%s</p></div>',
				esc_html( sprintf( _n( '%d image skipped.', '%d images skipped.', $skipped, 'unattached-images-cleaner' ), $skipped ) )
			);
		}
		if ( $failed > 0 ) {
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html( sprintf( _n( '%d image could not be converted.', '%d images could not be converted.', $failed, 'unattached-images-cleaner' ), $failed ) )
			);
		}
	}
}
