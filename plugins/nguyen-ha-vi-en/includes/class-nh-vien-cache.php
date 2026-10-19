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
	/** Listen for changes of the pairs, and, when the English side is on, add the English pages to what a post change purges. */
	public static function boot() {
		add_action( 'nh_vien_pairs_changed', array( __CLASS__, 'pairs_changed' ), 10, 2 );
		if ( NH_VIEN_Routes::enabled() ) {
			add_filter( 'nh_seo_purge_post_urls', array( __CLASS__, 'purge_urls' ), 10, 2 );
		}
	}

	/**
	 * The Nguyen Ha SEO Companion purges the pages a post change makes stale, with the Vietnamese archives in mind. For a post
	 * of a pair it must also purge the other side (its language switcher and hreflang name this post), and for an English post
	 * the English home and the English archives of its categories and tags.
	 *
	 * @param string[] $urls The addresses the companion collected.
	 * @param WP_Post  $post The post that changed.
	 * @return string[]
	 */
	public static function purge_urls( $urls, $post ) {
		if ( ! is_array( $urls ) || ! $post instanceof WP_Post || 'post' !== $post->post_type || ! NH_VIEN_Routes::live() ) {
			return $urls;
		}
		$english = NH_VIEN_Links::is_en( $post->ID );
		$other   = $english ? NH_VIEN_Links::vi_for_en( $post->ID ) : NH_VIEN_Links::en_for_vi( $post->ID );
		if ( $other ) {
			$urls[] = get_permalink( $other );
		}
		if ( $english ) {
			$urls[] = NH_VIEN_Routes::url();
			foreach ( array( 'category', 'post_tag' ) as $taxonomy ) {
				$terms = get_the_terms( $post, $taxonomy );
				foreach ( is_array( $terms ) ? $terms : array() as $term ) {
					$urls[] = NH_VIEN_Urls::english_term_url( $term );
				}
			}
		}
		return array_values( array_filter( $urls ) );
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
