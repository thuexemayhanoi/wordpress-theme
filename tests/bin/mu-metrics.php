<?php
/**
 * Plugin Name: Nguyen Ha test metrics
 * Description: Test sites only. Appends the query count, database time, wall time and peak memory of a page to its HTML as a comment so tests/bin/measure.mjs can read them.
 * Version:     1.0.0
 * License:     GPL-2.0-or-later
 *
 * Copy this file into wp-content/mu-plugins of a disposable site, with SAVEQUERIES defined in wp-config.php.
 * It does nothing outside local development URLs.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'shutdown',
	static function () {
		global $wpdb, $timestart;

		if ( ! preg_match( '#^https?://(127\.0\.0\.1|localhost)#', home_url() ) || is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		$is_html = false;
		foreach ( headers_list() as $header ) {
			if ( 0 === stripos( $header, 'content-type: text/html' ) ) {
				$is_html = true;
			}
		}
		if ( ! $is_html ) {
			return;
		}

		$queries = is_array( $wpdb->queries ) ? $wpdb->queries : array();
		$db_time = 0.0;
		$slowest = array();
		foreach ( $queries as $query ) {
			$db_time  += (float) $query[1];
			$slowest[] = array(
				'ms'     => round( (float) $query[1] * 1000, 2 ),
				'caller' => implode( ' < ', array_slice( array_map( 'trim', explode( ',', (string) $query[2] ) ), -3 ) ),
				'sql'    => substr( preg_replace( '/\s+/', ' ', (string) $query[0] ), 0, 220 ),
			);
		}
		usort(
			$slowest,
			static function ( $a, $b ) {
				return $b['ms'] <=> $a['ms'];
			}
		);

		$report = array(
			'queries' => count( $queries ),
			'db_ms'   => round( $db_time * 1000, 1 ),
			'wall_ms' => round( ( microtime( true ) - $timestart ) * 1000, 1 ),
			'peak_mb' => round( memory_get_peak_usage() / 1048576, 1 ),
			'slowest' => array_slice( $slowest, 0, 3 ),
		);

		// ?nh-metrics=full lists every query in execution order.
		if ( isset( $_GET['nh-metrics'] ) && 'full' === $_GET['nh-metrics'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Test site only.
			$report['all'] = array();
			foreach ( $queries as $query ) {
				$report['all'][] = round( (float) $query[1] * 1000, 2 ) . 'ms ' . substr( preg_replace( '/\s+/', ' ', (string) $query[0] ), 0, 170 ) . '  <' . implode( ' < ', array_slice( array_map( 'trim', explode( ',', (string) $query[2] ) ), -2 ) ) . '>';
			}
		}

		echo "\n<!-- nh-metrics " . wp_json_encode( $report ) . " -->\n";
	},
	PHP_INT_MAX
);
