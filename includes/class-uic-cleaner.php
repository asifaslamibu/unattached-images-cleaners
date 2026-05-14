<?php
/**
 * Unattached images cleaner.
 *
 * @package UnattachedImagesCleaner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UIC_Cleaner {

	const PER_PAGE       = 40;
	const BATCH_LIMIT    = 100; // Max deletes per submit – plenty fast, but protects against runaway.
	const PAGE_SLUG      = 'unattached-images-cleaner';
	const ALLOWED_FORMATS = array( 'jpg', 'png', 'gif', 'webp', 'bmp' );

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu',            array( $this, 'register_submenu' ) );
		add_action( 'admin_post_uic_delete', array( $this, 'handle_delete' ) );
		add_action( 'admin_notices',         array( $this, 'maybe_show_notice' ) );
	}

	public function register_submenu() {
		add_submenu_page(
			UIC_MENU_SLUG,
			__( 'Unattached Cleaner', 'unattached-images-cleaner' ),
			__( 'Unattached Cleaner', 'unattached-images-cleaner' ),
			UIC_CAP,
			self::PAGE_SLUG,
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Base query args (filters applied separately).
	 *
	 * @param int   $paged
	 * @param bool  $deep_scan
	 * @param array $filters
	 * @return array
	 */
	private function base_query_args( $paged, $deep_scan, $filters ) {
		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_parent'    => 0,
			'post_mime_type' => 'image',
			'posts_per_page' => self::PER_PAGE,
			'paged'          => max( 1, (int) $paged ),
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$args = UIC_Helpers::apply_filters_to_query_args( $args, $filters );

		if ( $deep_scan ) {
			$exclude = $this->find_referenced_attachment_ids();
			if ( ! empty( $exclude ) ) {
				$args['post__not_in'] = $exclude;
			}
		}

		return $args;
	}

	/**
	 * Best-effort: featured-image IDs + IDs found by URL inside post_content.
	 *
	 * @return int[]
	 */
	private function find_referenced_attachment_ids() {
		global $wpdb;
		$ids = array();

		$thumb_ids = $wpdb->get_col( "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value > 0" );
		foreach ( $thumb_ids as $id ) {
			$ids[] = (int) $id;
		}

		$upload_dir = wp_get_upload_dir();
		$base_url   = isset( $upload_dir['baseurl'] ) ? $upload_dir['baseurl'] : '';

		if ( $base_url ) {
			$like = '%' . $wpdb->esc_like( $base_url ) . '%';
			$rows = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT post_content FROM {$wpdb->posts} WHERE post_status IN ('publish','draft','pending','private','future') AND post_content LIKE %s",
					$like
				)
			);
			$regex = '#' . preg_quote( $base_url, '#' ) . '/([^\s"\'<>)]+\.(?:jpe?g|png|gif|webp|svg|bmp|avif))#i';
			foreach ( $rows as $content ) {
				if ( preg_match_all( $regex, $content, $matches ) ) {
					foreach ( $matches[1] as $relative_path ) {
						$attach_id = $this->attachment_id_from_relative_path( $relative_path );
						if ( $attach_id ) {
							$ids[] = $attach_id;
						}
					}
				}
			}
		}

		return array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
	}

	private function attachment_id_from_relative_path( $relative_path ) {
		global $wpdb;
		$original = preg_replace( '/-\d+x\d+(\.\w+)$/', '$1', $relative_path );
		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
				$original
			)
		);
		return (int) $id;
	}

	public function render_admin_page() {
		if ( ! current_user_can( UIC_CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'unattached-images-cleaner' ) );
		}

		$paged     = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$deep_scan = ! empty( $_GET['deep_scan'] );
		$filters   = UIC_Helpers::parse_filters( self::ALLOWED_FORMATS );

		$query = new WP_Query( $this->base_query_args( $paged, $deep_scan, $filters ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Unattached Images Cleaner', 'unattached-images-cleaner' ); ?></h1>
			<p>
				<?php esc_html_e( 'Images not attached to any post or page. Filter by format and upload folder, then delete individually, all on this page, or all matching across pages.', 'unattached-images-cleaner' ); ?>
			</p>

			<?php
			UIC_Helpers::render_filter_form(
				self::PAGE_SLUG,
				self::ALLOWED_FORMATS,
				$filters,
				array( 'deep_scan' => $deep_scan ? '1' : '' )
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
					<input type="checkbox" name="deep_scan" value="1" <?php checked( $deep_scan ); ?> onchange="this.form.submit()" />
					<?php esc_html_e( 'Deep scan: also hide images used as featured images or referenced in post content.', 'unattached-images-cleaner' ); ?>
				</label>
			</form>

			<?php if ( ! $query->have_posts() ) : ?>
				<div class="uic-empty">
					<strong><?php esc_html_e( 'No unattached images match your filters.', 'unattached-images-cleaner' ); ?></strong>
				</div>
			<?php else : ?>
				<form id="uic-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="uic_delete" />
					<input type="hidden" name="deep_scan" value="<?php echo $deep_scan ? '1' : '0'; ?>" />
					<?php
					UIC_Helpers::render_filter_hidden_inputs( $filters );
					wp_nonce_field( 'uic_delete_action', 'uic_nonce' );
					?>

					<div class="uic-toolbar">
						<label>
							<input type="checkbox" id="uic-select-all" />
							<strong><?php esc_html_e( 'Select all on this page', 'unattached-images-cleaner' ); ?></strong>
						</label>

						<button type="submit" class="button button-primary">
							<?php esc_html_e( 'Delete Selected', 'unattached-images-cleaner' ); ?>
						</button>

						<span class="uic-divider"></span>

						<button type="submit" name="select_all_matching" value="1" class="button button-secondary">
							<?php esc_html_e( 'Delete All Matching', 'unattached-images-cleaner' ); ?>
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
									<?php echo esc_html( get_the_date() ); ?> &middot;
									<?php echo esc_html( $filesize ); ?>
								</div>
							</label>
						<?php endwhile; ?>
					</div>

					<?php $this->render_pagination( $query, $paged, $deep_scan, $filters ); ?>
				</form>
			<?php endif; ?>
			<?php wp_reset_postdata(); ?>
		</div>
		<?php
	}

	private function render_pagination( $query, $paged, $deep_scan, $filters ) {
		if ( $query->max_num_pages <= 1 ) {
			return;
		}
		$base_args = array(
			'page'      => self::PAGE_SLUG,
			'deep_scan' => $deep_scan ? 1 : false,
			'folder'    => ! empty( $filters['folder'] ) ? $filters['folder'] : false,
			'paged'     => '%#%',
		);
		// Format checkboxes need array-form query args.
		$base = add_query_arg( $base_args, admin_url( 'admin.php' ) );
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

	/**
	 * Handle deletion: either posted IDs, or all matching the current filters.
	 */
	public function handle_delete() {
		if ( ! current_user_can( UIC_CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'unattached-images-cleaner' ) );
		}
		check_admin_referer( 'uic_delete_action', 'uic_nonce' );

		@set_time_limit( 300 );

		$select_all = ! empty( $_POST['select_all_matching'] );
		$deep_scan  = ! empty( $_POST['deep_scan'] ) && '1' === (string) $_POST['deep_scan'];

		if ( $select_all ) {
			$filters = UIC_Helpers::filters_from_post( self::ALLOWED_FORMATS );
			$args    = $this->base_query_args( 1, $deep_scan, $filters );
			$ids     = UIC_Helpers::get_all_matching_ids( $args );
		} else {
			$ids = isset( $_POST['ids'] ) ? (array) wp_unslash( $_POST['ids'] ) : array();
			$ids = array_filter( array_map( 'intval', $ids ) );
		}

		$ids = array_slice( $ids, 0, self::BATCH_LIMIT );

		$deleted = 0;
		$failed  = 0;
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			// Re-verify it's still an unattached image attachment.
			if ( ! $post || 'attachment' !== $post->post_type || 0 !== (int) $post->post_parent ) {
				$failed++;
				continue;
			}
			if ( wp_delete_attachment( $id, true ) ) {
				$deleted++;
			} else {
				$failed++;
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => self::PAGE_SLUG,
					'uic_deleted' => $deleted,
					'uic_failed'  => $failed,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function maybe_show_notice() {
		if ( ! isset( $_GET['page'] ) || self::PAGE_SLUG !== $_GET['page'] ) {
			return;
		}
		if ( ! isset( $_GET['uic_deleted'] ) && ! isset( $_GET['uic_failed'] ) ) {
			return;
		}

		$deleted = isset( $_GET['uic_deleted'] ) ? (int) $_GET['uic_deleted'] : 0;
		$failed  = isset( $_GET['uic_failed'] )  ? (int) $_GET['uic_failed']  : 0;

		if ( $deleted > 0 ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html( sprintf( _n( '%d image deleted permanently.', '%d images deleted permanently.', $deleted, 'unattached-images-cleaner' ), $deleted ) )
			);
		}
		if ( $failed > 0 ) {
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html( sprintf( _n( '%d image could not be deleted.', '%d images could not be deleted.', $failed, 'unattached-images-cleaner' ), $failed ) )
			);
		}
	}
}
