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
	// Every structural fragment (hubs, categories, featured and related posts) depends on
	// the request language when the native VI/EN plugin is active. Never share VI and EN
	// transient keys; a cached Vietnamese hub must not be rendered on an English page.
	$scope = 'default';
	if ( class_exists( 'NH_VIEN_Routes' ) && class_exists( 'NH_VIEN_Context' ) && NH_VIEN_Routes::live() ) {
		$scope = NH_VIEN_Context::is_english() ? 'en' : 'vi';
	}

	/**
	 * Filter the cache namespace when external presentation context changes the data.
	 *
	 * @param string $scope Request-language cache namespace.
	 */
	$scope = (string) apply_filters( 'nh_editorial_cache_scope', $scope );

	return 'nh_ed_' . substr( md5( $name . '|' . $scope . '|' . nh_editorial_cache_version() ), 0, 24 );
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
	// Every save of a published post also fires this hook with publish -> publish. Nothing cached changes then
	// (nh_editorial_cache_on_update() looks at the fields that do): bumping would empty every list after each typo fix.
	if ( $new_status === $old_status || ( 'publish' !== $new_status && 'publish' !== $old_status ) ) {
		return;
	}
	if ( $post instanceof WP_Post && in_array( $post->post_type, nh_editorial_cache_post_types(), true ) ) {
		nh_editorial_cache_bump();
	}
}
add_action( 'transition_post_status', 'nh_editorial_cache_on_transition', 10, 3 );

/**
 * Published posts whose date, password, slug, author or place in a page tree changed reorder neighbours, lists and counts.
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
	foreach ( array( 'post_date', 'post_password', 'post_name', 'post_author', 'post_parent', 'menu_order' ) as $field ) {
		if ( $post_after->$field !== $post_before->$field ) {
			nh_editorial_cache_bump();
			return;
		}
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
 * WordPress fires this hook on every save, even when nothing changed, and drafts are not public, so
 * only a real change on published content invalidates the cache.
 *
 * @param int    $object_id  Object ID.
 * @param array  $terms      Terms.
 * @param array  $tt_ids     Term taxonomy IDs.
 * @param string $taxonomy   Taxonomy.
 * @param bool   $append     Whether the terms were appended to the existing ones.
 * @param array  $old_tt_ids Term taxonomy IDs before the change (empty when appending).
 * @return void
 */
function nh_editorial_cache_on_set_terms( $object_id, $terms, $tt_ids, $taxonomy, $append = false, $old_tt_ids = array() ) {
	if ( ! in_array( $taxonomy, nh_editorial_cache_taxonomies(), true ) ) {
		return;
	}

	$post = get_post( $object_id );
	if ( $post instanceof WP_Post && 'publish' !== $post->post_status ) {
		return;
	}

	if ( ! $append ) {
		$new = array_map( 'intval', (array) $tt_ids );
		$old = array_map( 'intval', (array) $old_tt_ids );
		sort( $new );
		sort( $old );
		if ( $new === $old ) {
			return;
		}
	}

	nh_editorial_cache_bump();
}
add_action( 'set_object_terms', 'nh_editorial_cache_on_set_terms', 10, 6 );

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

// A deleted user's posts are reassigned with direct SQL, which changes the author counts without a post hook.
add_action( 'deleted_user', 'nh_editorial_cache_bump' );
add_action( 'remove_user_from_blog', 'nh_editorial_cache_bump' );

// Sticky posts, front-page settings, Customizer saves and theme activation.
add_action( 'update_option_sticky_posts', 'nh_editorial_cache_bump' );
add_action( 'update_option_show_on_front', 'nh_editorial_cache_bump' );
add_action( 'update_option_page_on_front', 'nh_editorial_cache_bump' );
add_action( 'update_option_page_for_posts', 'nh_editorial_cache_bump' );
add_action( 'update_option_default_category', 'nh_editorial_cache_bump' );
add_action( 'customize_save_after', 'nh_editorial_cache_bump' );
add_action( 'after_switch_theme', 'nh_editorial_cache_bump_now' );

/**
 * Purge full-page LiteSpeed cache once after an approved theme version release.
 *
 * Git-based theme deployments do not always fire WordPress core's upgrader
 * hooks. An older HTML page can therefore still display the old VI/EN
 * switcher in the EN hero after the new template has been deployed.
 *
 * Only the site's administrator can trigger the purge, through an authenticated
 * admin or REST request. Anonymous page views never write an option or purge
 * caches. The stored version makes subsequent requests free of purge work.
 *
 * @return void
 */
function nh_editorial_purge_litespeed_after_release() {
	if ( ! current_user_can( 'manage_options' ) || ! has_action( 'litespeed_purge_all' ) ) {
		return;
	}

	$version = (string) NH_EDITORIAL_VERSION;
	$key     = 'nh_editorial_litespeed_purged_version';
	if ( $version === (string) get_option( $key, '' ) ) {
		return;
	}

	// Record first so simultaneous admin requests cannot repeatedly purge.
	update_option( $key, $version, false );
	do_action( 'litespeed_purge_all', 'Nguyen Ha theme deployment ' . $version );
}
add_action( 'admin_init', 'nh_editorial_purge_litespeed_after_release', 99 );
add_action( 'rest_api_init', 'nh_editorial_purge_litespeed_after_release', 99 );

