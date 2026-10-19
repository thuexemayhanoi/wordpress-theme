<?php
/**
 * Plugin Name: Nguyen Ha VI-EN Core
 * Description: Native Vietnamese and English sections for one WordPress site: paired posts, /en/ addresses, language switcher, hreflang and Yoast SEO integration. Off until the site opts in.
 * Version: 0.5.1
 * Requires at least: 6.3
 * Requires PHP: 7.4
 * Author: Nguyen Ha Editorial
 * License: GPL-2.0-or-later
 * Text Domain: nguyen-ha-vi-en
 *
 * @package Nguyen_Ha_VI_EN
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'NH_VIEN_VERSION' ) ) {
	define( 'NH_VIEN_VERSION', '0.5.1' );
}

if ( ! defined( 'NH_VIEN_PLUGIN_FILE' ) ) {
	define( 'NH_VIEN_PLUGIN_FILE', __FILE__ );
}

require_once __DIR__ . '/includes/class-nh-vien-languages.php';
require_once __DIR__ . '/includes/class-nh-vien-links.php';
require_once __DIR__ . '/includes/class-nh-vien-admin.php';
require_once __DIR__ . '/includes/class-nh-vien-routes.php';
require_once __DIR__ . '/includes/class-nh-vien-context.php';
require_once __DIR__ . '/includes/class-nh-vien-urls.php';
require_once __DIR__ . '/includes/class-nh-vien-query.php';
require_once __DIR__ . '/includes/class-nh-vien-cache.php';
require_once __DIR__ . '/includes/class-nh-vien-content.php';
require_once __DIR__ . '/includes/class-nh-vien-seo.php';
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

// Without this plugin nothing answers its stored rewrite rules, so deactivating it takes its own rules out again.
register_deactivation_hook( __FILE__, array( 'NH_VIEN_Routes', 'deactivate' ) );
