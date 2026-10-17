<?php
/**
 * Plugin Name:       Nguyen Ha SEO Companion
 * Plugin URI:        https://github.com/thuexemayhanoi/wordpress-theme
 * Description:       Companion plugin for the Nguyen Ha Editorial theme. Keeps SEO and editorial data independent of the theme: topic clusters, answer-first summary, FAQ and sources, verified business profile, schema extensions, internal search index and cache purging.
 * Version:           1.0.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Nguyen Ha Web Team
 * Author URI:        https://github.com/thuexemayhanoi
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nguyen-ha-seo-companion
 * Domain Path:       /languages
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'NH_SEO_VERSION' ) ) {
	return; // Already loaded (for example when the plugin folder was copied twice).
}

define( 'NH_SEO_VERSION', '1.0.0' );
define( 'NH_SEO_DB_VERSION', '1' );
define( 'NH_SEO_FILE', __FILE__ );
define( 'NH_SEO_DIR', plugin_dir_path( __FILE__ ) );
define( 'NH_SEO_URL', plugin_dir_url( __FILE__ ) );

require_once NH_SEO_DIR . 'includes/class-nh-seo-keys.php';
require_once NH_SEO_DIR . 'includes/functions.php';
require_once NH_SEO_DIR . 'includes/class-nh-seo-plugin.php';

register_activation_hook( __FILE__, array( 'NH_SEO_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'NH_SEO_Plugin', 'deactivate' ) );

NH_SEO_Plugin::boot();
