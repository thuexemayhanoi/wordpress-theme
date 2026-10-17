<?php
/**
 * Module "data": taxonomy, post/term/user meta, REST exposure and the Topic Cluster alias sync.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-nh-seo-data-model.php';
require_once __DIR__ . '/class-nh-seo-cluster-sync.php';

/**
 * Primary Topic Cluster of a post (docs/DATA-CONTRACT.md section 2).
 *
 * The cluster chosen in `nh_primary_cluster` when it is assigned to the post; otherwise the assigned
 * cluster with the lowest term ID. Null when the post has no cluster.
 *
 * @param WP_Post|int|null $post Post or ID. Defaults to the current post.
 * @return WP_Term|null
 */
function nh_seo_get_primary_cluster( $post = null ) {
	$post = get_post( $post );
	if ( ! $post instanceof WP_Post ) {
		return null;
	}

	$terms = get_the_terms( $post, NH_SEO_Keys::TAXONOMY_CLUSTER );
	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return null;
	}

	$by_id = array();
	foreach ( $terms as $term ) {
		$by_id[ (int) $term->term_id ] = $term;
	}

	$chosen = (int) get_post_meta( $post->ID, NH_SEO_Keys::META_PRIMARY_CLUSTER, true );
	if ( $chosen && isset( $by_id[ $chosen ] ) ) {
		return $by_id[ $chosen ];
	}

	ksort( $by_id );
	return reset( $by_id );
}

NH_SEO_Data_Model::init();
NH_SEO_Cluster_Sync::init();

// The theme reads the verified business profile through this filter; the plugin supplies it.
add_filter( 'nguyen_ha_business_profile', 'nh_seo_get_business_profile' );
