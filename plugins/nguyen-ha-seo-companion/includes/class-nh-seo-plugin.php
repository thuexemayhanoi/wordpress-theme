<?php
/**
 * Plugin bootstrap and module loader.
 *
 * Each module lives in includes/<module>/bootstrap.php and owns its own hooks. Loading happens at
 * include time (not on plugins_loaded) so that module activation callbacks are registered before
 * WordPress fires the activation hook in the very same request.
 *
 * Modules (owner of each folder is documented in docs/COMPANION-PLUGIN.md):
 *   data    taxonomy, post/term/user meta, REST exposure, topic-cluster alias sync
 *   admin   meta boxes, term and profile fields, settings page, admin notices
 *   render  fallback rendering of summary, takeaways, FAQ and sources when the theme does not render them
 *   schema  JSON-LD ownership detection, fallback graph, FAQPage and LocalBusiness extras
 *   search  internal search index table, indexing hooks, query integration, reindex tools
 *   cache   page-cache purge integration
 *   cli     WP-CLI commands
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * Loads modules and routes lifecycle events to them.
 */
final class NH_SEO_Plugin {

	/**
	 * Modules in load order.
	 *
	 * @var string[]
	 */
	const MODULES = array( 'data', 'admin', 'render', 'schema', 'search', 'cache', 'cli' );

	/**
	 * Whether boot() already ran.
	 *
	 * @var bool
	 */
	private static $booted = false;

	/**
	 * Load every module that exists on disk.
	 *
	 * @return void
	 */
	public static function boot() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		foreach ( self::MODULES as $module ) {
			$file = NH_SEO_DIR . 'includes/' . $module . '/bootstrap.php';
			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}

		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_upgrade' ), 20 );
		add_action( 'init', array( __CLASS__, 'load_textdomain' ), 1 );

		/**
		 * Fires once every module has been loaded.
		 */
		do_action( 'nh_seo_loaded' );
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	public static function load_textdomain() {
		load_plugin_textdomain( 'nguyen-ha-seo-companion', false, dirname( plugin_basename( NH_SEO_FILE ) ) . '/languages' );
	}

	/**
	 * Run module upgrade routines when the stored DB version differs from the code version.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$stored = (string) get_option( 'nh_seo_db_version', '' );
		if ( NH_SEO_DB_VERSION === $stored ) {
			return;
		}

		/**
		 * Modules create or migrate their storage here (for example the search index table).
		 *
		 * @param string $stored Previously stored DB version ('' on first run).
		 * @param string $target Version shipped with this code.
		 */
		do_action( 'nh_seo_upgrade', $stored, NH_SEO_DB_VERSION );

		update_option( 'nh_seo_db_version', NH_SEO_DB_VERSION, true );
	}

	/**
	 * Activation: modules prepare storage. Never creates or changes content.
	 *
	 * @return void
	 */
	public static function activate() {
		/**
		 * Fires on plugin activation.
		 */
		do_action( 'nh_seo_activate' );
		do_action( 'nh_seo_upgrade', (string) get_option( 'nh_seo_db_version', '' ), NH_SEO_DB_VERSION );
		update_option( 'nh_seo_db_version', NH_SEO_DB_VERSION, true );
	}

	/**
	 * Deactivation: modules unschedule their events. Data is kept.
	 *
	 * @return void
	 */
	public static function deactivate() {
		/**
		 * Fires on plugin deactivation.
		 */
		do_action( 'nh_seo_deactivate' );
	}
}
