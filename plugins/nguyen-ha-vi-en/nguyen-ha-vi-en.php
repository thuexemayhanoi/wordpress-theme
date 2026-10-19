<?php
/**
 * Plugin Name: Nguyen Ha VI-EN Core
 * Description: Safe, independent VI/EN foundation. Features are intentionally inactive until separately implemented and verified.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Nguyen Ha Editorial
 * License: GPL-2.0-or-later
 * Text Domain: nguyen-ha-vi-en
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'NH_VIEN_VERSION' ) ) {
	define( 'NH_VIEN_VERSION', '0.1.0' );
}

if ( ! defined( 'NH_VIEN_PLUGIN_FILE' ) ) {
	define( 'NH_VIEN_PLUGIN_FILE', __FILE__ );
}

require_once __DIR__ . '/includes/class-nh-vien-languages.php';
require_once __DIR__ . '/includes/class-nh-vien-plugin.php';

/**
 * Obtain the inert plugin instance for future modules.
 *
 * @return NH_VIEN_Plugin
 */
function nh_vien() {
	return NH_VIEN_Plugin::instance();
}

// No activation handler, rewrite rule, SEO hook, database write, or URL change.
add_action( 'plugins_loaded', array( 'NH_VIEN_Plugin', 'boot' ), 20 );
