<?php
/**
 * Versioned fragment cache for expensive structural queries.
 *
 * Only IDs and small term structures are cached, never rendered HTML, so titles, images and content
 * always show their current state. Every key embeds a global version; any structural change (publish,
 * unpublish, delete, date change, term assignment, term edit, pillar mapping, sticky posts, theme
 * switch) bumps the version once at shutdown, which invalidates all fragments at once without scanning
 * or deleting transients. Stale fragments expire on their own TTL. Works on shared hosting: no object
 * cache is required, but one is used automatically when present (transients delegate to it).
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

/**
 * A fresh unique cache version.
 *
 * @return string
 */
function nh_editorial_new_cache_version() {
	return uniqid( '', true );
}

/**
 * Whether the fragment cache may be used for this request.
 *
 * @return bool
 */
function nh_editorial_cache_enabled() {
	$enabled = ! ( defined( 'NH_EDITORIAL_DISABLE_CACHE' ) && NH_EDITORIAL_DISABLE_CACHE ) && ! is_customize_preview();

	/**
	 * Filters whether the fragment cache is used.
	 *
	 * @param bool $enabled Whether caching is on.
	 */
	return (bool) apply_filters( 'nh_editorial_cache_enabled', $enabled );
}

/**
 * Current global cache version. Created on first use as an autoloaded option.
 *
 * @return string
 */
function nh_editorial_cache_version() {
	$version = get_option( 'nh_editorial_cache_version', false );
	if ( false === $version || '' === $version ) {
		$version = nh_editorial_new_cache_version();
		add_option( 'nh_editorial_cache_version', $version, '', true );
	}

	/**
	 * Filters the cache version string, for example to tie it to an external cache generation.
	 *
	 * @param string $version Version.
	 */
	return (string) apply_filters( 'nh_editorial_cache_version', (string) $version );
}

/**
 * Transient name for a fragment.
 *
 * @param string $name Logical fragment name.
 * @return string
 */
function nh_editorial_cache_key( $name ) {
	return 'nh_ed_' . substr( md5( $name . '|' . nh_editorial_cache_version() ), 0, 24 );
}

/**
 * Return a cached fragment, computing and storing it on a miss.
 *
 * @param string   $name     Logical fragment name (include every input that changes the result).
 * @param callable $callback Computes the value on a miss. Must return something serializable.
 * @param int|null $ttl      Seconds; defaults to six hours.
 * @return mixed
 */
function nh_editorial_cache_remember( $name, $callback, $ttl = null ) {
	if ( ! nh_editorial_cache_enabled() ) {
		return call_user_func( $callback );
	}

	$key = nh_editorial_cache_key( $name );
	$hit = get_transient( $key );
	if ( is_array( $hit ) && array_key_exists( 'v', $hit ) ) {
		return $hit['v'];
	}

	$value = call_user_func( $callback );
	set_transient( $key, array( 'v' => $value ), null === $ttl ? 6 * HOUR_IN_SECONDS : (int) $ttl );
	return $value;
}

/**
 * Invalidate every fragment immediately.
 *
 * @return void
 */
function nh_editorial_cache_bump_now() {
	update_option( 'nh_editorial_cache_version', nh_editorial_new_cache_version(), true );
}

/**
 * Request an invalidation. Many triggers in one request cost a single write at shutdown.
 *
 * @return void
 */
function nh_editorial_cache_bump() {
	if ( ! has_action( 'shutdown', 'nh_editorial_cache_bump_now' ) ) {
		add_action( 'shutdown', 'nh_editorial_cache_bump_now', 5 );
	}
}

/**
 * Post types whose publication affects lists, navigation and menus.
 *
 * @return string[]
 */
function nh_editorial_cache_post_types() {
	return array( 'post', 'page' );
}

/**
 * Taxonomies whose terms or assignments affect lists and navigation.
 *
 * @return string[]
 */
function nh_editorial_cache_taxonomies() {
	return array( 'category', 'post_tag', 'topic_cluster' );
}

/**
 * Post status transitions.
 *
 * @param string  $new_status New status.
 * @param string  $old_status Old status.
 * @param WP_Post $post       Post.
 * @return void
 */
function nh_editorial_cache_on_transition( $new_status, $old_status, $post ) {
	if ( 'publish' !== $new_status && 'publish' !== $old_status ) {
		return;
	}
	if ( $post instanceof WP_Post && in_array( $post->post_type, nh_editorial_cache_post_types(), true ) ) {
		nh_editorial_cache_bump();
	}
}
add_action( 'transition_post_status', 'nh_editorial_cache_on_transition', 10, 3 );

/**
 * Published posts whose date, password or slug changed reorder neighbours and lists.
 *
 * @param int     $post_id     Post ID.
 * @param WP_Post $post_after  Post after the update.
 * @param WP_Post $post_before Post before the update.
 * @return void
 */
function nh_editorial_cache_on_update( $post_id, $post_after, $post_before ) {
	if ( 'publish' !== $post_after->post_status || ! in_array( $post_after->post_type, nh_editorial_cache_post_types(), true ) ) {
		return;
	}
	if ( $post_after->post_date !== $post_before->post_date || $post_after->post_password !== $post_before->post_password || $post_after->post_name !== $post_before->post_name ) {
		nh_editorial_cache_bump();
	}
}
add_action( 'post_updated', 'nh_editorial_cache_on_update', 10, 3 );

/**
 * Deleted posts.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function nh_editorial_cache_on_delete( $post_id ) {
	if ( in_array( get_post_type( $post_id ), nh_editorial_cache_post_types(), true ) ) {
		nh_editorial_cache_bump();
	}
}
add_action( 'deleted_post', 'nh_editorial_cache_on_delete' );

/**
 * Term assignments.
 *
 * @param int    $object_id Object ID.
 * @param array  $terms     Terms.
 * @param array  $tt_ids    Term taxonomy IDs.
 * @param string $taxonomy  Taxonomy.
 * @return void
 */
function nh_editorial_cache_on_set_terms( $object_id, $terms, $tt_ids, $taxonomy ) {
	if ( in_array( $taxonomy, nh_editorial_cache_taxonomies(), true ) ) {
		nh_editorial_cache_bump();
	}
}
add_action( 'set_object_terms', 'nh_editorial_cache_on_set_terms', 10, 4 );

/**
 * Term create, edit, delete.
 *
 * @param int    $term_id  Term ID.
 * @param int    $tt_id    Term taxonomy ID.
 * @param string $taxonomy Taxonomy.
 * @return void
 */
function nh_editorial_cache_on_term_change( $term_id, $tt_id, $taxonomy ) {
	if ( in_array( $taxonomy, nh_editorial_cache_taxonomies(), true ) ) {
		nh_editorial_cache_bump();
	}
}
add_action( 'created_term', 'nh_editorial_cache_on_term_change', 10, 3 );
add_action( 'edited_term', 'nh_editorial_cache_on_term_change', 10, 3 );
add_action( 'delete_term', 'nh_editorial_cache_on_term_change', 10, 3 );

/**
 * Meta keys whose change alters silos, pillars or hubs.
 *
 * @return string[]
 */
function nh_editorial_cache_meta_keys() {
	return array( 'nh_pillar_id', 'nh_topic_cluster', 'nh_primary_cluster', 'nh_hub_term' );
}

/**
 * Post and term meta changes.
 *
 * @param int|int[] $meta_id  Meta ID(s).
 * @param int       $object_id Object ID.
 * @param string    $meta_key Meta key.
 * @return void
 */
function nh_editorial_cache_on_meta( $meta_id, $object_id, $meta_key ) {
	if ( in_array( $meta_key, nh_editorial_cache_meta_keys(), true ) ) {
		nh_editorial_cache_bump();
	}
}
add_action( 'added_post_meta', 'nh_editorial_cache_on_meta', 10, 3 );
add_action( 'updated_post_meta', 'nh_editorial_cache_on_meta', 10, 3 );
add_action( 'deleted_post_meta', 'nh_editorial_cache_on_meta', 10, 3 );
add_action( 'added_term_meta', 'nh_editorial_cache_on_meta', 10, 3 );
add_action( 'updated_term_meta', 'nh_editorial_cache_on_meta', 10, 3 );
add_action( 'deleted_term_meta', 'nh_editorial_cache_on_meta', 10, 3 );

// Sticky posts, front-page settings, Customizer saves and theme activation.
add_action( 'update_option_sticky_posts', 'nh_editorial_cache_bump' );
add_action( 'update_option_show_on_front', 'nh_editorial_cache_bump' );
add_action( 'update_option_page_on_front', 'nh_editorial_cache_bump' );
add_action( 'update_option_page_for_posts', 'nh_editorial_cache_bump' );
add_action( 'update_option_default_category', 'nh_editorial_cache_bump' );
add_action( 'customize_save_after', 'nh_editorial_cache_bump' );
add_action( 'after_switch_theme', 'nh_editorial_cache_bump_now' );
