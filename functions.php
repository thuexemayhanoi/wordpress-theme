<?php
/**
 * Nguyen Ha Editorial: theme bootstrap.
 *
 * This file only defines constants and loads the modules in inc/. Each module owns one
 * responsibility (see docs/ARCHITECTURE.md). The theme presents content: SEO titles, meta
 * descriptions, canonicals, robots, Open Graph and the JSON-LD graph belong to the SEO plugin
 * (see docs/SEO-RESPONSIBILITY.md); business and editorial data that must survive a theme change
 * belongs to the companion plugin (see docs/DATA-CONTRACT.md).
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

// Keep in sync with the Version header in style.css (a unit test enforces it).
define( 'NH_EDITORIAL_VERSION', '2.0.5' );
define( 'NH_EDITORIAL_DIR', trailingslashit( get_template_directory() ) );
define( 'NH_EDITORIAL_URI', trailingslashit( get_template_directory_uri() ) );

$nh_editorial_modules = array(
	'config',
	'color',
	'icons',
	'extras',
	'seo-compat',
	'cache',
	'setup',
	'customizer',
	'assets',
	'performance',
	'topics',
	'seo-priority',
	'related-posts',
	'breadcrumbs',
	'toc',
	'author',
	'navigation',
	'menu-taxonomy',
	'search',
	'template-tags',
	'schema-presentation',
	'admin-notices',
);

foreach ( $nh_editorial_modules as $nh_editorial_module ) {
	require_once NH_EDITORIAL_DIR . 'inc/' . $nh_editorial_module . '.php';
}

unset( $nh_editorial_modules, $nh_editorial_module );
