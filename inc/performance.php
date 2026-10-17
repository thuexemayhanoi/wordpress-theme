<?php
/**
 * Performance utilities: image attributes, LCP handling, core asset trimming.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

// Print core block CSS only for blocks that are actually on the page (supported since WordPress 5.8).
add_filter( 'should_load_separate_core_block_assets', '__return_true' );

/**
 * Whether the next large image may claim the LCP slot (eager, high priority). Only the first caller
 * per request gets true, so exactly one image is prioritised and everything else stays lazy.
 *
 * @return bool
 */
function nh_editorial_claim_lcp() {
	static $claimed = false;
	if ( $claimed ) {
		return false;
	}
	$claimed = true;
	return true;
}

/**
 * Image presets per layout context: WordPress size, the "sizes" attribute that matches the CSS layout,
 * and whether the image is a candidate for the Largest Contentful Paint.
 *
 * No custom image sizes are registered on purpose: existing uploads keep working without regenerating
 * thumbnails and uploads do not create extra files.
 *
 * @return array<string, array{size: string, sizes: string, lcp: bool}>
 */
function nh_editorial_image_contexts() {
	return array(
		'hero'     => array(
			'size'  => 'large',
			'sizes' => '(min-width: 1100px) 740px, 100vw',
			'lcp'   => true,
		),
		'featured' => array(
			'size'  => 'large',
			'sizes' => '(min-width: 1100px) 1000px, 100vw',
			'lcp'   => true,
		),
		'card'     => array(
			'size'  => 'medium_large',
			'sizes' => '(min-width: 1100px) 360px, (min-width: 640px) 45vw, 92vw',
			'lcp'   => false,
		),
		'thumb'    => array(
			'size'  => 'thumbnail',
			'sizes' => '112px',
			'lcp'   => false,
		),
	);
}

/**
 * Attributes for a post thumbnail in a given context.
 *
 * @param string    $context  hero | featured | card | thumb.
 * @param bool|null $priority True asks for the LCP slot (eager, high priority) regardless of context,
 *                            false never does, null follows the context preset.
 * @return array<string, string>
 */
function nh_editorial_image_attributes( $context, $priority = null ) {
	$contexts = nh_editorial_image_contexts();
	$preset   = isset( $contexts[ $context ] ) ? $contexts[ $context ] : $contexts['card'];

	$attributes = array(
		'sizes'    => $preset['sizes'],
		'decoding' => 'async',
	);

	$wants_lcp = null === $priority ? $preset['lcp'] : (bool) $priority;
	if ( $wants_lcp && nh_editorial_claim_lcp() ) {
		$attributes['loading']       = 'eager';
		$attributes['fetchpriority'] = 'high';
	} else {
		$attributes['loading'] = 'lazy';
	}

	/**
	 * Filters thumbnail attributes for a context.
	 *
	 * @param array  $attributes Attributes.
	 * @param string $context    Context.
	 */
	return apply_filters( 'nh_editorial_image_attributes', $attributes, $context );
}

/**
 * Responsive thumbnail HTML for a post, or '' when it has none.
 *
 * Width and height come from the attachment so the browser reserves the space (no layout shift).
 *
 * @param int       $post_id  Post ID.
 * @param string    $context  hero | featured | card | thumb.
 * @param bool|null $priority See nh_editorial_image_attributes().
 * @return string Safe HTML from core.
 */
function nh_editorial_thumbnail( $post_id, $context = 'card', $priority = null ) {
	if ( ! has_post_thumbnail( $post_id ) ) {
		return '';
	}
	$contexts = nh_editorial_image_contexts();
	$size     = isset( $contexts[ $context ] ) ? $contexts[ $context ]['size'] : 'medium_large';

	return get_the_post_thumbnail( $post_id, $size, nh_editorial_image_attributes( $context, $priority ) );
}
