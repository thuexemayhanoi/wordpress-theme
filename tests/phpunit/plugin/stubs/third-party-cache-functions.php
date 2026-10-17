<?php
/**
 * SIMULATED page-cache plugin functions for the cache module tests.
 *
 * WP Rocket, W3 Total Cache and WP Super Cache were NOT installed or executed. These stand-ins only record the
 * calls the cache module makes to the function names those plugins publish, so the tests can prove WHAT is
 * handed over and WHEN. They cannot prove that the real plugins purge anything.
 *
 * Calls are appended to $GLOBALS['nh_test_cache_calls'] as array( integration, argument ). A function throws
 * when $GLOBALS['nh_test_cache_throw'] holds its integration name. Loading this file is what makes the
 * functions "exist": tests that need the plugins to be absent run before it is required.
 *
 * @package nguyen-ha-seo-companion
 */

if ( ! function_exists( 'rocket_clean_files' ) ) {
	/**
	 * Simulated WP Rocket function: accepts one URL or a list of URLs.
	 *
	 * @param string|string[] $urls URLs.
	 * @return void
	 * @throws RuntimeException When the test asks this integration to fail.
	 */
	function rocket_clean_files( $urls ) {
		$GLOBALS['nh_test_cache_calls'][] = array( 'wp-rocket', $urls );
		if ( isset( $GLOBALS['nh_test_cache_throw'] ) && 'wp-rocket' === $GLOBALS['nh_test_cache_throw'] ) {
			throw new RuntimeException( 'simulated WP Rocket failure' );
		}
	}
}

if ( ! function_exists( 'w3tc_flush_url' ) ) {
	/**
	 * Simulated W3 Total Cache function: one URL.
	 *
	 * @param string $url URL.
	 * @return void
	 * @throws RuntimeException When the test asks this integration to fail.
	 */
	function w3tc_flush_url( $url ) {
		$GLOBALS['nh_test_cache_calls'][] = array( 'w3-total-cache', $url );
		if ( isset( $GLOBALS['nh_test_cache_throw'] ) && 'w3-total-cache' === $GLOBALS['nh_test_cache_throw'] ) {
			throw new RuntimeException( 'simulated W3 Total Cache failure' );
		}
	}
}

if ( ! function_exists( 'wpsc_delete_url_cache' ) ) {
	/**
	 * Simulated WP Super Cache function: one URL.
	 *
	 * @param string $url URL.
	 * @return void
	 * @throws RuntimeException When the test asks this integration to fail.
	 */
	function wpsc_delete_url_cache( $url ) {
		$GLOBALS['nh_test_cache_calls'][] = array( 'wp-super-cache', $url );
		if ( isset( $GLOBALS['nh_test_cache_throw'] ) && 'wp-super-cache' === $GLOBALS['nh_test_cache_throw'] ) {
			throw new RuntimeException( 'simulated WP Super Cache failure' );
		}
	}
}
