<?php
/**
 * SEO plugin compatibility. This module OUTPUTS NOTHING.
 *
 * Responsibility split (details in docs/SEO-RESPONSIBILITY.md): the SEO plugin owns the document title,
 * meta description, canonical, robots, Open Graph / Twitter cards, sitemaps and the JSON-LD graph;
 * WordPress core owns the default canonical for singular pages and the robots meta API. The theme only
 * reads plugin state so that what it shows (breadcrumb, primary category) matches what the plugin
 * describes to search engines.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

/**
 * Slug of the active SEO plugin, or '' when none is detected.
 *
 * @return string
 */
function nh_editorial_seo_plugin() {
	$detected = array(
		'yoast'         => defined( 'WPSEO_VERSION' ),
		'rank-math'     => defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath', false ),
		'aioseo'        => defined( 'AIOSEO_VERSION' ),
		'seopress'      => defined( 'SEOPRESS_VERSION' ),
		'seo-framework' => defined( 'THE_SEO_FRAMEWORK_VERSION' ),
		'smartcrawl'    => defined( 'SMARTCRAWL_VERSION' ),
		'slim-seo'      => defined( 'SLIM_SEO_VER' ),
		'squirrly'      => defined( 'SQ_VERSION' ),
	);

	$slug = '';
	foreach ( $detected as $name => $active ) {
		if ( $active ) {
			$slug = $name;
			break;
		}
	}

	/**
	 * Filters the detected SEO plugin slug.
	 *
	 * @param string $slug Detected slug or ''.
	 */
	return (string) apply_filters( 'nh_editorial_seo_plugin', $slug );
}

/**
 * Whether an SEO plugin is active.
 *
 * @return bool
 */
function nh_editorial_has_seo_plugin() {
	return '' !== nh_editorial_seo_plugin();
}

/**
 * Whether the Nguyen Ha SEO Companion plugin is active.
 *
 * @return bool
 */
function nh_editorial_has_companion() {
	return defined( 'NH_SEO_VERSION' );
}

/**
 * Primary category of a post. Same algorithm as nh_seo_get_primary_category() in the companion plugin
 * (docs/DATA-CONTRACT.md §3); a parity test keeps them identical.
 *
 * 1. The Yoast / Rank Math primary category, when it is assigned to the post.
 * 2. Otherwise the deepest assigned category (ties: lowest term ID), ignoring the default
 *    "Uncategorized" unless it is the only one.
 *
 * @param WP_Post|int|null $post Post or ID. Defaults to the current post.
 * @return WP_Term|null
 */
function nh_editorial_primary_category( $post = null ) {
	$post = get_post( $post );
	if ( ! $post instanceof WP_Post ) {
		return null;
	}

	$terms = get_the_terms( $post, 'category' );
	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return null;
	}

	$by_id = array();
	foreach ( $terms as $term ) {
		$by_id[ (int) $term->term_id ] = $term;
	}

	$candidates = array(
		(int) get_post_meta( $post->ID, '_yoast_wpseo_primary_category', true ),
		(int) get_post_meta( $post->ID, 'rank_math_primary_category', true ),
	);
	if ( function_exists( 'yoast_get_primary_term_id' ) ) {
		array_unshift( $candidates, (int) yoast_get_primary_term_id( 'category', $post ) );
	}
	foreach ( $candidates as $candidate ) {
		if ( $candidate && isset( $by_id[ $candidate ] ) ) {
			return $by_id[ $candidate ];
		}
	}

	$pool = $by_id;
	if ( count( $pool ) > 1 ) {
		unset( $pool[ (int) get_option( 'default_category' ) ] );
	}

	$best       = null;
	$best_depth = -1;
	foreach ( $pool as $term ) {
		$depth = count( get_ancestors( $term->term_id, 'category', 'taxonomy' ) );
		if ( $depth > $best_depth || ( $depth === $best_depth && null !== $best && $term->term_id < $best->term_id ) ) {
			$best       = $term;
			$best_depth = $depth;
		}
	}
	return $best;
}

/**
 * Plain-text title of a post without "Protected:" / "Private:" prefixes or markup.
 *
 * @param WP_Post|int $post Post or ID.
 * @return string
 */
function nh_editorial_plain_title( $post ) {
	$post = get_post( $post );
	if ( ! $post instanceof WP_Post ) {
		return '';
	}
	$title = trim( wp_specialchars_decode( wp_strip_all_tags( get_post_field( 'post_title', $post, 'raw' ) ), ENT_QUOTES ) );
	return '' !== $title ? $title : __( '(no title)', 'nguyen-ha-editorial' );
}
