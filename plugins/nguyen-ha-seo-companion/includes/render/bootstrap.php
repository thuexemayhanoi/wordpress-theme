<?php
/**
 * Module "render": the public renderers and the fallback renderer for themes without native support.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/*
 * The classes are only needed when something is rendered, so they are loaded on first use.
 */
spl_autoload_register(
	static function ( $class_name ) {
		static $files = array(
			'NH_SEO_Renderer'          => 'class-nh-seo-renderer.php',
			'NH_SEO_Fallback_Renderer' => 'class-nh-seo-fallback-renderer.php',
		);
		if ( isset( $files[ $class_name ] ) ) {
			require_once __DIR__ . '/' . $files[ $class_name ];
		}
	}
);

/**
 * HTML of the answer-first summary of a post ("Quick answer"). Escaped, semantic, '' when there is none.
 *
 * Like the other public renderers it returns '' for a password protected post that is not unlocked.
 *
 * @param WP_Post|int|null $post Post or ID. Defaults to the current post.
 * @return string
 */
function nh_seo_render_summary( $post = null ) {
	$post = NH_SEO_Renderer::target( $post );
	if ( ! $post ) {
		return '';
	}
	return NH_SEO_Renderer::summary( nh_seo_sanitize_summary( get_post_meta( $post->ID, NH_SEO_Keys::META_SUMMARY, true ) ) );
}

/**
 * HTML of the key takeaways of a post. Escaped, semantic, '' when there are none.
 *
 * @param WP_Post|int|null $post Post or ID. Defaults to the current post.
 * @return string
 */
function nh_seo_render_takeaways( $post = null ) {
	$post = NH_SEO_Renderer::target( $post );
	if ( ! $post ) {
		return '';
	}
	return NH_SEO_Renderer::takeaways( nh_seo_sanitize_takeaways( get_post_meta( $post->ID, NH_SEO_Keys::META_TAKEAWAYS, true ) ) );
}

/**
 * HTML of the FAQ of a post. Questions are escaped and answers pass wp_kses with the allowed HTML. '' when there is none.
 *
 * @param WP_Post|int|null $post Post or ID. Defaults to the current post.
 * @return string
 */
function nh_seo_render_faq( $post = null ) {
	$post = NH_SEO_Renderer::target( $post );
	if ( ! $post ) {
		return '';
	}
	return NH_SEO_Renderer::faq( nh_seo_sanitize_faq( get_post_meta( $post->ID, NH_SEO_Keys::META_FAQ, true ) ) );
}

/**
 * HTML of the sources (references) of a post. Escaped, http(s) links only, '' when there are none.
 *
 * @param WP_Post|int|null $post Post or ID. Defaults to the current post.
 * @return string
 */
function nh_seo_render_sources( $post = null ) {
	$post = NH_SEO_Renderer::target( $post );
	if ( ! $post ) {
		return '';
	}
	return NH_SEO_Renderer::sources( nh_seo_sanitize_sources( get_post_meta( $post->ID, NH_SEO_Keys::META_SOURCES, true ) ) );
}

/**
 * HTML of the "content updated" line of a post. '' when no valid date is stored.
 *
 * @param WP_Post|int|null $post Post or ID. Defaults to the current post.
 * @return string
 */
function nh_seo_render_updated( $post = null ) {
	$post = NH_SEO_Renderer::target( $post );
	if ( ! $post ) {
		return '';
	}
	return NH_SEO_Renderer::updated( nh_seo_sanitize_date( get_post_meta( $post->ID, NH_SEO_Keys::META_UPDATED, true ) ) );
}

add_filter( 'the_content', array( 'NH_SEO_Fallback_Renderer', 'filter_content' ), 20 );
add_action( 'wp_enqueue_scripts', array( 'NH_SEO_Fallback_Renderer', 'maybe_enqueue_style' ) );
