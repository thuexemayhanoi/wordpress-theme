<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * Removes the plugin's options, transients, scheduled events and search index table. It never removes
 * post meta, term meta, user meta, terms or content: editorial data survives the removal of the plugin.
 *
 * @package nguyen-ha-seo-companion
 */

// Only WordPress may run this file. A direct request has neither constant and stops here.
if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-nh-seo-uninstall.php';

NH_SEO_Uninstall::run();
