<?php
/**
 * Caches that hold lists or links of both languages: what to purge when the pairs change or the English side is
 * switched on or off.
 *
 * Every integration is optional: the fragment cache of the Nguyen Ha Editorial theme, the page cache purge of the
 * Nguyen Ha SEO Companion plugin, and the whole-site purge of the common page caches (LiteSpeed Cache, WP Rocket,
 * W3 Total Cache, WP Super Cache) run only when the plugin in question is active; the plugin works without them.
 *
 * @package Nguyen_Ha_VI_EN
 */

defined( 'ABSPATH' ) || exit;

/**
 * Purge hooks of the VI/EN pairs and of the switch.
 */
final class NH_VIEN_Cache {
	/** Listen for changes of the pairs. */
	public static function boot() {
		add_action( 'nh_vien_pairs_changed', array( __CLASS__, 'pairs_changed' ), 10, 2 );
	}

	/**
	 * A pair was added or removed, or the English side was switched on or off (both IDs zero): the lists and links of
	 * both languages are out of date. A switch changes every page of the English side and the language switcher of
	 * every Vietnamese page, so the page cache of the whole site is purged then.
	 *
	 * @param int $vi_id Vietnamese post ID, or 0 when the change concerns no single pair.
	 * @param int $en_id English post ID, or 0 when the change concerns no single pair.
	 */
	public static function pairs_changed( $vi_id = 0, $en_id = 0 ) {
		if ( function_exists( 'nh_editorial_cache_bump' ) ) {
			nh_editorial_cache_bump();
		}
		if ( class_exists( 'NH_SEO_Cache' ) && is_callable( array( 'NH_SEO_Cache', 'queue_post' ) ) ) {
			foreach ( array( (int) $vi_id, (int) $en_id ) as $post_id ) {
				if ( $post_id > 0 ) {
					NH_SEO_Cache::queue_post( $post_id );
				}
			}
		}
		if ( 0 === (int) $vi_id && 0 === (int) $en_id ) {
			self::purge_all();
		}
	}

	/**
	 * Purge the page cache of the whole site, through the public API of each common page cache plugin that is active.
	 */
	public static function purge_all() {
		if ( has_action( 'litespeed_purge_all' ) ) {
			do_action( 'litespeed_purge_all' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public hook of the LiteSpeed Cache plugin.
		}
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}
		/**
		 * Fires after the English side was switched on or off, for page caches the plugin does not know.
		 */
		do_action( 'nh_vien_purge_all' );
	}
}
