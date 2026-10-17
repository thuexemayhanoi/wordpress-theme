<?php
/**
 * CLI module bootstrap: `wp nh-seo reindex | index-status | purge`.
 *
 * The classes are always loaded (they are plain PHP and need nothing from WP-CLI); the commands are
 * registered only when WP-CLI is running. See docs/COMPANION-PLUGIN.md, "Module cli".
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/interface-nh-seo-cli-output.php';
require_once __DIR__ . '/class-nh-seo-cli-output-wpcli.php';
require_once __DIR__ . '/class-nh-seo-cli-command.php';

if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI' ) ) {
	WP_CLI::add_command( 'nh-seo', 'NH_SEO_CLI_Command' );
}
