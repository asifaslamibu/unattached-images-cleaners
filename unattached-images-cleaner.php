<?php
/**
 * Plugin Name:       Media Tools – Cleaner & WebP Converter
 * Plugin URI:        https://example.com/media-tools
 * Description:       Dashboard for managing media: bulk-delete unattached images, bulk-convert JPEG/PNG/GIF/BMP to WebP. Filter by format and upload folder.
 * Version:           1.2.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Your Name
 * License:           GPL-2.0-or-later
 * Text Domain:       unattached-images-cleaner
 *
 * @package UnattachedImagesCleaner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'UIC_VERSION',     '1.2.0' );
define( 'UIC_PLUGIN_FILE', __FILE__ );
define( 'UIC_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'UIC_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'UIC_CAP',         'manage_options' );
define( 'UIC_MENU_SLUG',   'uic-dashboard' );

require_once UIC_PLUGIN_DIR . 'includes/class-uic-helpers.php';
require_once UIC_PLUGIN_DIR . 'includes/class-uic-assets.php';
require_once UIC_PLUGIN_DIR . 'includes/class-uic-dashboard.php';
require_once UIC_PLUGIN_DIR . 'includes/class-uic-cleaner.php';
require_once UIC_PLUGIN_DIR . 'includes/class-uic-webp.php';

UIC_Assets::instance();
UIC_Dashboard::instance();
UIC_Cleaner::instance();
UIC_WebP_Converter::instance();
