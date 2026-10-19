<?php
/**
 * Caches that hold lists or links of both languages: what to purge when the pairs change.
 *
 * Both integrations are optional: they run only when the Nguyen Ha Editorial theme (fragment cache) or the Nguyen Ha
 * SEO Companion plugin (page cache purge) is active, and the plugin works without them.
 *
 * @package Nguyen_Ha_VI_EN
 */

defined( 'ABSPATH' ) || exit;

/**
 * Purge hooks of the VI/EN pairs.
 */
final class NH_VIEN_Cache {
	/** Listen for changes of the pairs. */
	public static function boot() {
		add_action( 'nh_vien_pairs_changed', array( __CLASS__, 'pairs_changed' ), 10, 2 );
	}

	/**
	 * A pair was added or removed: the lists and links of both languages are out of date.
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
	}
}
