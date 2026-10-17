<?php
/**
 * Schema presentation: keep what is visible identical to what structured data describes.
 *
 * The theme never prints JSON-LD. The SEO plugin (or the companion plugin) does, from the same data the
 * templates show: the same primary category for the breadcrumb, the same FAQ items, the same dates, the
 * same author. This module holds the helpers that make the visible side consistent and verifiable.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

/**
 * Published and updated dates of an article for visible display.
 *
 * @param WP_Post|int|null $post Post (defaults to the current post).
 * @return array{published: array{iso: string, label: string}, updated: array{iso: string, label: string}|null}
 */
function nh_editorial_article_dates( $post = null ) {
	$post = get_post( $post );
	if ( ! $post instanceof WP_Post ) {
		return array(
			'published' => array(
				'iso'   => '',
				'label' => '',
			),
			'updated'   => null,
		);
	}

	return array(
		'published' => array(
			'iso'   => get_the_date( DATE_W3C, $post ),
			'label' => get_the_date( '', $post ),
		),
		'updated'   => nh_editorial_updated_date( $post ),
	);
}

/**
 * Whether the FAQ of a post is shown on the page. The theme always shows an existing FAQ, which is
 * what allows the companion plugin to describe it as FAQPage structured data.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function nh_editorial_has_visible_faq( $post_id = 0 ) {
	$extras = nh_editorial_get_extras( $post_id );
	return ! empty( $extras['faq'] );
}
