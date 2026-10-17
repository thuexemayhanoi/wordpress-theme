<?php
/**
 * Removal of the plugin's own storage.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * Deletes what the plugin created for itself and nothing else: its options, its transients, its scheduled
 * events and the search index table.
 *
 * It NEVER touches post meta, term meta, user meta, terms, taxonomies or any content: the editorial data
 * (summaries, FAQs, sources, clusters, Pillars, author details) must survive the removal of the plugin.
 *
 * This file is only loaded by uninstall.php (and by the tests), never during normal requests.
 */
final class NH_SEO_Uninstall {

	/**
	 * Prefix of every option and transient the plugin owns (see docs/DATA-CONTRACT.md).
	 *
	 * @var string
	 */
	const PREFIX = 'nh_seo_';

	/**
	 * Name of the search index table without the site prefix.
	 *
	 * @var string
	 */
	const SEARCH_TABLE = 'nh_search_index';

	/**
	 * Run the uninstall. Only does anything when WordPress is uninstalling a plugin.
	 *
	 * @return bool True when the data was removed, false when the call was refused.
	 */
	public static function run() {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			return false;
		}
		self::remove_everywhere();
		return true;
	}

	/**
	 * Remove the data of the current site, or of every site of a network.
	 *
	 * @return void
	 */
	public static function remove_everywhere() {
		if ( ! is_multisite() ) {
			self::remove_site_data();
			return;
		}

		$offset = 0;
		$batch  = 100;
		do {
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => $batch,
					'offset' => $offset,
				)
			);
			$found    = count( $site_ids );
			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::remove_site_data();
				restore_current_blog();
			}
			$offset += $batch;
		} while ( $batch === $found );

		self::remove_network_options();
	}

	/**
	 * On a network, site options and site transients live in the network table, not in each site's options.
	 *
	 * @return void
	 */
	private static function remove_network_options() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off lookup of network option names by prefix.
		$names = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT meta_key FROM {$wpdb->sitemeta} WHERE site_id = %d AND ( meta_key LIKE %s OR meta_key LIKE %s OR meta_key LIKE %s )",
				get_current_network_id(),
				$wpdb->esc_like( self::PREFIX ) . '%',
				$wpdb->esc_like( '_site_transient_' . self::PREFIX ) . '%',
				$wpdb->esc_like( '_site_transient_timeout_' . self::PREFIX ) . '%'
			)
		);

		foreach ( (array) $names as $name ) {
			if ( 0 === strpos( $name, '_site_transient_' . self::PREFIX ) ) {
				delete_site_transient( substr( $name, strlen( '_site_transient_' ) ) );
			}
			delete_site_option( $name );
		}
	}

	/**
	 * Remove the plugin's options, transients, scheduled events and search table of the current site.
	 *
	 * @return void
	 */
	public static function remove_site_data() {
		self::remove_options();
		self::remove_scheduled_events();
		self::drop_search_table();
	}

	/**
	 * Delete options and transients whose name starts with the plugin prefix.
	 *
	 * @return void
	 */
	private static function remove_options() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off lookup of option names by prefix.
		$names = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( self::PREFIX ) . '%',
				$wpdb->esc_like( '_transient_' . self::PREFIX ) . '%',
				$wpdb->esc_like( '_transient_timeout_' . self::PREFIX ) . '%',
				$wpdb->esc_like( '_site_transient_' . self::PREFIX ) . '%',
				$wpdb->esc_like( '_site_transient_timeout_' . self::PREFIX ) . '%'
			)
		);

		foreach ( (array) $names as $name ) {
			if ( 0 === strpos( $name, '_transient_' . self::PREFIX ) ) {
				delete_transient( substr( $name, strlen( '_transient_' ) ) );
			} elseif ( 0 === strpos( $name, '_site_transient_' . self::PREFIX ) ) {
				delete_site_transient( substr( $name, strlen( '_site_transient_' ) ) );
			}
			// Plain options, and any transient timeout row that is left over, go through the options API.
			delete_option( $name );
		}
	}

	/**
	 * Unschedule every cron event whose hook starts with the plugin prefix, whatever its arguments.
	 *
	 * @return void
	 */
	private static function remove_scheduled_events() {
		$crons = _get_cron_array();
		if ( ! is_array( $crons ) ) {
			return;
		}

		$hooks = array();
		foreach ( $crons as $events ) {
			if ( ! is_array( $events ) ) {
				continue;
			}
			foreach ( array_keys( $events ) as $hook ) {
				if ( 0 === strpos( (string) $hook, self::PREFIX ) ) {
					$hooks[ $hook ] = true;
				}
			}
		}
		foreach ( array_keys( $hooks ) as $hook ) {
			wp_unschedule_hook( $hook );
		}
	}

	/**
	 * Drop the internal search index table of the current site.
	 *
	 * @return void
	 */
	private static function drop_search_table() {
		global $wpdb;

		$table = $wpdb->prefix . self::SEARCH_TABLE;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name is the site prefix plus a constant, and DROP TABLE cannot use placeholders.
		$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $table ) . '`' );
	}
}
