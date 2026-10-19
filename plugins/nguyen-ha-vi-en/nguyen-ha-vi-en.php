<?php
/**
 * Plugin Name: Nguyen Ha VI-EN Core
 * Description: VI/EN editorial links with explicitly gated staging-only English route preview.
 * Version: 0.3.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Nguyen Ha Editorial
 * License: GPL-2.0-or-later
 * Text Domain: nguyen-ha-vi-en
 *
 * @package Nguyen_Ha_VI_EN
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'NH_VIEN_VERSION' ) ) {
	define( 'NH_VIEN_VERSION', '0.3.0' );
}

if ( ! defined( 'NH_VIEN_PLUGIN_FILE' ) ) {
	define( 'NH_VIEN_PLUGIN_FILE', __FILE__ );
}

require_once __DIR__ . '/includes/class-nh-vien-languages.php';
require_once __DIR__ . '/includes/class-nh-vien-links.php';
require_once __DIR__ . '/includes/class-nh-vien-admin.php';
require_once __DIR__ . '/includes/class-nh-vien-routes.php';
require_once __DIR__ . '/includes/class-nh-vien-switcher.php';
require_once __DIR__ . '/includes/class-nh-vien-plugin.php';

/**
 * Obtain the inert plugin instance for future modules.
 *
 * @return NH_VIEN_Plugin
 */
function nh_vien() {
	return NH_VIEN_Plugin::instance();
}

// Only explicit administrator link/unlink actions can update the plugin-owned option.
add_action( 'plugins_loaded', array( 'NH_VIEN_Plugin', 'boot' ), 20 );
