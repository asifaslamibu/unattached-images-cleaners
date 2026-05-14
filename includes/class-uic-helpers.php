<?php
/**
 * Shared helpers: format constants, folder discovery, filter parsing & UI, stats.
 *
 * @package UnattachedImagesCleaner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UIC_Helpers {

	/**
	 * All supported image format keys mapped to [mime, label].
	 * Order here = display order in the filter UI.
	 *
	 * @return array<string, array{0:string,1:string}>
	 */
	public static function format_map() {
		return array(
			'jpg'  => array( 'image/jpeg', 'JPG' ),
			'png'  => array( 'image/png',  'PNG' ),
			'gif'  => array( 'image/gif',  'GIF' ),
			'webp' => array( 'image/webp', 'WebP' ),
			'bmp'  => array( 'image/bmp',  'BMP' ),
		);
	}

	/**
	 * Format keys that can be converted TO WebP (excludes webp itself + non-raster).
	 *
	 * @return string[]
	 */
	public static function convertible_format_keys() {
		return array( 'jpg', 'png', 'gif', 'bmp' );
	}

	/**
	 * Convert selected format keys to mime types.
	 *
	 * @param string[] $selected
	 * @return string[]
	 */
	public static function selected_mimes( $selected ) {
		$map   = self::format_map();
		$mimes = array();
		foreach ( (array) $selected as $key ) {
			if ( isset( $map[ $key ] ) ) {
				$mimes[] = $map[ $key ][0];
			}
		}
		return $mimes;
	}

	/**
	 * Scan the uploads dir for YYYY/MM folders (WordPress's default scheme).
	 * Cached for the request lifetime.
	 *
	 * @return string[] Folder paths like "2024/05", newest first.
	 */
	public static function get_upload_folders() {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}

		$cache      = array();
		$upload_dir = wp_get_upload_dir();
		$basedir    = isset( $upload_dir['basedir'] ) ? $upload_dir['basedir'] : '';

		if ( ! $basedir || ! is_dir( $basedir ) ) {
			return $cache;
		}

		$years = @scandir( $basedir );
		if ( ! is_array( $years ) ) {
			return $cache;
		}

		foreach ( $years as $year ) {
			if ( ! preg_match( '/^\d{4}$/', $year ) ) {
				continue;
			}
			$year_path = $basedir . '/' . $year;
			if ( ! is_dir( $year_path ) ) {
				continue;
			}
			$months = @scandir( $year_path );
			if ( ! is_array( $months ) ) {
				continue;
			}
			foreach ( $months as $month ) {
				if ( ! preg_match( '/^\d{2}$/', $month ) ) {
					continue;
				}
				if ( is_dir( $year_path . '/' . $month ) ) {
					$cache[] = $year . '/' . $month;
				}
			}
		}

		// Newest folder first.
		usort(
			$cache,
			static function ( $a, $b ) {
				return strcmp( $b, $a );
			}
		);

		return $cache;
	}

	/**
	 * Parse filter inputs from $_GET (or $_POST when handling a submit).
	 *
	 * @param string[] $allowed_formats Format keys allowed for this screen.
	 * @return array{formats: string[], folder: string}
	 */
	public static function parse_filters( $allowed_formats ) {
		$source = $_REQUEST; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filters; no state mutation.

		$formats = array();
		if ( isset( $source['formats'] ) ) {
			$raw     = (array) wp_unslash( $source['formats'] );
			$formats = array_values( array_intersect( array_map( 'sanitize_key', $raw ), $allowed_formats ) );
		} else {
			// Default: all allowed formats checked.
			$formats = $allowed_formats;
		}

		$folder = '';
		if ( isset( $source['folder'] ) ) {
			$candidate = sanitize_text_field( (string) wp_unslash( $source['folder'] ) );
			// Whitelist against actual folders to prevent path injection.
			if ( $candidate && in_array( $candidate, self::get_upload_folders(), true ) ) {
				$folder = $candidate;
			}
		}

		return array(
			'formats' => $formats,
			'folder'  => $folder,
		);
	}

	/**
	 * Merge filter values into a WP_Query args array.
	 *
	 * @param array $args
	 * @param array $filters
	 * @return array
	 */
	public static function apply_filters_to_query_args( $args, $filters ) {
		if ( ! empty( $filters['formats'] ) ) {
			$mimes = self::selected_mimes( $filters['formats'] );
			if ( ! empty( $mimes ) ) {
				$args['post_mime_type'] = $mimes;
			}
		}

		if ( ! empty( $filters['folder'] ) ) {
			$args['meta_query'] = isset( $args['meta_query'] ) ? $args['meta_query'] : array();
			$args['meta_query'][] = array(
				'key'     => '_wp_attached_file',
				'value'   => $filters['folder'] . '/',
				'compare' => 'LIKE',
			);
		}

		return $args;
	}

	/**
	 * Run a query and return ALL matching attachment IDs (no pagination).
	 * Used by "Select all matching" – capped externally by the caller's batch limit.
	 *
	 * @param array $base_args
	 * @return int[]
	 */
	public static function get_all_matching_ids( $base_args ) {
		$args = array_merge(
			$base_args,
			array(
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		unset( $args['paged'] );
		$q = new WP_Query( $args );
		return array_map( 'intval', $q->posts );
	}

	/**
	 * Render the filter form (format checkboxes + folder dropdown + apply button).
	 *
	 * @param string $page_slug       e.g. 'unattached-images-cleaner' or 'uic-webp-converter'.
	 * @param string[] $allowed_formats Format keys to expose as checkboxes.
	 * @param array  $current         Output of parse_filters().
	 * @param array  $extra_hidden    Extra hidden fields to preserve (key => value).
	 */
	public static function render_filter_form( $page_slug, $allowed_formats, $current, $extra_hidden = array() ) {
		$map     = self::format_map();
		$folders = self::get_upload_folders();
		?>
		<form method="get" action="" class="uic-filter-form">
			<input type="hidden" name="page" value="<?php echo esc_attr( $page_slug ); ?>" />
			<?php foreach ( $extra_hidden as $k => $v ) : ?>
				<input type="hidden" name="<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $v ); ?>" />
			<?php endforeach; ?>

			<div class="uic-filter-row">
				<strong><?php esc_html_e( 'Format:', 'unattached-images-cleaner' ); ?></strong>
				<?php foreach ( $allowed_formats as $key ) : if ( ! isset( $map[ $key ] ) ) continue; ?>
					<label class="uic-chip">
						<input type="checkbox" name="formats[]" value="<?php echo esc_attr( $key ); ?>"
							<?php checked( in_array( $key, $current['formats'], true ) ); ?> />
						<?php echo esc_html( $map[ $key ][1] ); ?>
					</label>
				<?php endforeach; ?>
			</div>

			<div class="uic-filter-row">
				<strong><?php esc_html_e( 'Folder:', 'unattached-images-cleaner' ); ?></strong>
				<select name="folder">
					<option value=""><?php esc_html_e( 'All folders', 'unattached-images-cleaner' ); ?></option>
					<?php foreach ( $folders as $folder ) : ?>
						<option value="<?php echo esc_attr( $folder ); ?>" <?php selected( $current['folder'], $folder ); ?>>
							<?php echo esc_html( $folder ); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<button type="submit" class="button"><?php esc_html_e( 'Apply filters', 'unattached-images-cleaner' ); ?></button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $page_slug ) ); ?>" class="button-link uic-reset">
					<?php esc_html_e( 'Reset', 'unattached-images-cleaner' ); ?>
				</a>
			</div>
		</form>
		<?php
	}

	/**
	 * Render hidden inputs so a POST handler can recover the current filter state
	 * (needed for "Select all matching" so the handler re-queries with the same filters).
	 *
	 * @param array $filters
	 */
	public static function render_filter_hidden_inputs( $filters ) {
		if ( ! empty( $filters['formats'] ) ) {
			foreach ( $filters['formats'] as $key ) {
				printf( '<input type="hidden" name="filter_formats[]" value="%s" />', esc_attr( $key ) );
			}
		}
		if ( ! empty( $filters['folder'] ) ) {
			printf( '<input type="hidden" name="filter_folder" value="%s" />', esc_attr( $filters['folder'] ) );
		}
	}

	/**
	 * Recover filter state from a POST submission (counterpart to render_filter_hidden_inputs).
	 *
	 * @param string[] $allowed_formats
	 * @return array
	 */
	public static function filters_from_post( $allowed_formats ) {
		$formats = array();
		if ( isset( $_POST['filter_formats'] ) ) {
			$raw     = (array) wp_unslash( $_POST['filter_formats'] );
			$formats = array_values( array_intersect( array_map( 'sanitize_key', $raw ), $allowed_formats ) );
		} else {
			$formats = $allowed_formats;
		}

		$folder = '';
		if ( isset( $_POST['filter_folder'] ) ) {
			$candidate = sanitize_text_field( (string) wp_unslash( $_POST['filter_folder'] ) );
			if ( $candidate && in_array( $candidate, self::get_upload_folders(), true ) ) {
				$folder = $candidate;
			}
		}

		return array(
			'formats' => $formats,
			'folder'  => $folder,
		);
	}

	/**
	 * Compute dashboard stats (fast: pure SQL counts).
	 *
	 * @return array
	 */
	public static function get_stats() {
		global $wpdb;

		$stats = array();

		$stats['total'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			 WHERE post_type = 'attachment'
			 AND post_status = 'inherit'
			 AND post_mime_type LIKE 'image/%'"
		);

		$stats['unattached'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			 WHERE post_type = 'attachment'
			 AND post_status = 'inherit'
			 AND post_parent = 0
			 AND post_mime_type LIKE 'image/%'"
		);

		$rows = $wpdb->get_results(
			"SELECT post_mime_type, COUNT(*) AS cnt FROM {$wpdb->posts}
			 WHERE post_type = 'attachment'
			 AND post_status = 'inherit'
			 AND post_mime_type LIKE 'image/%'
			 GROUP BY post_mime_type",
			ARRAY_A
		);

		$by_format = array();
		foreach ( (array) $rows as $row ) {
			$by_format[ $row['post_mime_type'] ] = (int) $row['cnt'];
		}
		$stats['by_format'] = $by_format;

		// Convertible = jpg + png + gif + bmp.
		$convertible = 0;
		foreach ( array( 'image/jpeg', 'image/png', 'image/gif', 'image/bmp' ) as $mime ) {
			$convertible += isset( $by_format[ $mime ] ) ? $by_format[ $mime ] : 0;
		}
		$stats['convertible'] = $convertible;
		$stats['webp']        = isset( $by_format['image/webp'] ) ? $by_format['image/webp'] : 0;

		return $stats;
	}

	/**
	 * Check if the server's image editor can write WebP.
	 *
	 * @return bool
	 */
	public static function server_supports_webp() {
		return (bool) wp_image_editor_supports(
			array(
				'mime_type' => 'image/webp',
				'methods'   => array( 'save' ),
			)
		);
	}
}
