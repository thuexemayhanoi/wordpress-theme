<?php
/**
 * Topic Cluster alias sync: turns the `nh_topic_cluster` alias written by workflows into a term.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * Applies the cluster alias (a slug or a name) to a post.
 *
 * The rules of docs/DATA-CONTRACT.md section 2:
 *
 *  - the term is found by slug, then by name;
 *  - when it does not exist and the setting "Auto-create clusters" is on, it is created;
 *  - the term is added to the post (existing terms are never removed) and stored as the primary cluster.
 *
 * An alias is applied once, when it is written or changed: the alias that was applied last is remembered
 * in a protected meta key, so saving the post again never overrides a primary cluster chosen by an editor
 * and never re-adds a cluster an editor removed. Writing the alias again through the REST API (or any
 * other code path that fires the meta hooks) applies it again on purpose.
 *
 * The sync runs with the privileges of the current user: a logged-in user needs the capability to assign
 * terms in the taxonomy. Without a user (WP-CLI, cron, importers) it is allowed, because those code paths
 * are not reachable by visitors.
 */
final class NH_SEO_Cluster_Sync {

	/**
	 * Protected meta key that remembers the alias applied last.
	 *
	 * @var string
	 */
	const SYNCED_META = '_nh_topic_cluster_synced';

	/**
	 * Posts that are being synced right now (recursion guard).
	 *
	 * @var array<int, bool>
	 */
	private static $busy = array();

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'added_post_meta', array( __CLASS__, 'on_meta_saved' ), 10, 4 );
		add_action( 'updated_post_meta', array( __CLASS__, 'on_meta_saved' ), 10, 4 );
		add_action( 'deleted_post_meta', array( __CLASS__, 'on_meta_deleted' ), 10, 4 );
		add_action( 'save_post', array( __CLASS__, 'on_save_post' ), 20, 2 );
		add_action( 'rest_after_insert_post', array( __CLASS__, 'on_rest_after_insert' ), 20, 2 );
	}

	/**
	 * The alias meta was added or changed: apply it.
	 *
	 * @param int    $meta_id    Meta row ID.
	 * @param int    $post_id    Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @return void
	 */
	public static function on_meta_saved( $meta_id, $post_id, $meta_key, $meta_value = null ) {
		unset( $meta_id, $meta_value );
		if ( NH_SEO_Keys::META_CLUSTER_ALIAS === $meta_key ) {
			self::sync( $post_id, true );
		}
	}

	/**
	 * The alias meta was deleted: forget the remembered alias. Terms are never removed.
	 *
	 * @param int[]  $meta_ids   Deleted meta row IDs.
	 * @param int    $post_id    Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @return void
	 */
	public static function on_meta_deleted( $meta_ids, $post_id, $meta_key, $meta_value = null ) {
		unset( $meta_ids, $meta_value );
		if ( NH_SEO_Keys::META_CLUSTER_ALIAS === $meta_key ) {
			delete_post_meta( $post_id, self::SYNCED_META );
		}
	}

	/**
	 * Safety net for posts whose alias was stored without firing the meta hooks (imports, direct writes).
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public static function on_save_post( $post_id, $post = null ) {
		unset( $post );
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		self::sync( $post_id, false );
	}

	/**
	 * After a REST write: when the request carried the alias, apply it again whatever the order in which
	 * the controller saved terms and meta.
	 *
	 * @param WP_Post         $post    Post.
	 * @param WP_REST_Request $request Request.
	 * @return void
	 */
	public static function on_rest_after_insert( $post, $request = null ) {
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		$meta     = $request instanceof WP_REST_Request ? $request->get_param( 'meta' ) : null;
		$explicit = is_array( $meta ) && array_key_exists( NH_SEO_Keys::META_CLUSTER_ALIAS, $meta );
		self::sync( $post->ID, $explicit );
	}

	/**
	 * Apply the post's cluster alias.
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $force   Apply even when this alias was applied before.
	 * @return int|false The primary cluster term ID that was applied, or false when nothing was applied.
	 */
	public static function sync( $post_id, $force = false ) {
		$post_id = absint( $post_id );
		if ( ! $post_id || isset( self::$busy[ $post_id ] ) ) {
			return false;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post
			|| wp_is_post_revision( $post )
			|| wp_is_post_autosave( $post )
			|| ! is_object_in_taxonomy( $post->post_type, NH_SEO_Keys::TAXONOMY_CLUSTER )
		) {
			return false;
		}

		$alias = NH_SEO_Data_Model::sanitize_cluster_alias( get_post_meta( $post_id, NH_SEO_Keys::META_CLUSTER_ALIAS, true ) );
		if ( '' === $alias ) {
			return false;
		}
		if ( ! $force && (string) get_post_meta( $post_id, self::SYNCED_META, true ) === $alias ) {
			return false;
		}
		if ( ! self::can_assign() ) {
			return false;
		}

		self::$busy[ $post_id ] = true;
		try {
			return self::apply( $post_id, $alias );
		} finally {
			unset( self::$busy[ $post_id ] );
		}
	}

	/**
	 * Find or create the term and attach it to the post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $alias   Sanitized alias.
	 * @return int|false Term ID, or false.
	 */
	private static function apply( $post_id, $alias ) {
		$term    = self::find_term( $alias );
		$created = false;

		if ( ! $term && self::can_create_term( $post_id, $alias ) ) {
			// Term functions expect slashed text: without wp_slash() a backslash in the name is dropped.
			$result = wp_insert_term( wp_slash( $alias ), NH_SEO_Keys::TAXONOMY_CLUSTER );
			if ( is_wp_error( $result ) ) {
				// A term with that name already exists (for example with different accents): use it.
				$term_id = (int) $result->get_error_data( 'term_exists' );
			} else {
				$term_id = (int) $result['term_id'];
				$created = true;
			}
			$term = $term_id ? get_term( $term_id, NH_SEO_Keys::TAXONOMY_CLUSTER ) : null;
			$term = $term instanceof WP_Term ? $term : null;
		}
		if ( ! $term ) {
			return false;
		}

		$term_id  = (int) $term->term_id;
		$assigned = has_term( $term_id, NH_SEO_Keys::TAXONOMY_CLUSTER, $post_id );
		if ( ! $assigned ) {
			$result = wp_set_object_terms( $post_id, array( $term_id ), NH_SEO_Keys::TAXONOMY_CLUSTER, true );
			if ( is_wp_error( $result ) ) {
				return false;
			}
		}

		$primary_changed = (int) get_post_meta( $post_id, NH_SEO_Keys::META_PRIMARY_CLUSTER, true ) !== $term_id;
		if ( $primary_changed ) {
			update_post_meta( $post_id, NH_SEO_Keys::META_PRIMARY_CLUSTER, $term_id );
		}
		// update_post_meta() unslashes its value: slash it, or a backslash in the name is lost and the next save re-applies the cluster.
		update_post_meta( $post_id, self::SYNCED_META, wp_slash( $alias ) );

		if ( $created || ! $assigned || $primary_changed ) {
			/**
			 * Fires after a cluster alias was applied to a post and something changed.
			 *
			 * @param int  $post_id Post ID.
			 * @param int  $term_id Topic Cluster term ID that is now the primary cluster.
			 * @param bool $created Whether the term was created by this sync.
			 */
			do_action( 'nh_seo_cluster_synced', $post_id, $term_id, $created );
		}

		return $term_id;
	}

	/**
	 * Find a cluster term by slug, then by name.
	 *
	 * @param string $alias Alias.
	 * @return WP_Term|null
	 */
	public static function find_term( $alias ) {
		$slug = sanitize_title( $alias );
		if ( '' !== $slug ) {
			$term = get_term_by( 'slug', $slug, NH_SEO_Keys::TAXONOMY_CLUSTER );
			if ( $term instanceof WP_Term ) {
				return $term;
			}
		}

		$term = get_term_by( 'name', wp_slash( $alias ), NH_SEO_Keys::TAXONOMY_CLUSTER );
		return $term instanceof WP_Term ? $term : null;
	}

	/**
	 * Whether the current context may attach cluster terms to posts.
	 *
	 * @return bool
	 */
	private static function can_assign() {
		if ( ! is_user_logged_in() ) {
			return true; // WP-CLI, cron and importers have no user and cannot be reached by visitors.
		}
		$taxonomy = get_taxonomy( NH_SEO_Keys::TAXONOMY_CLUSTER );
		return $taxonomy && current_user_can( $taxonomy->cap->assign_terms );
	}

	/**
	 * Whether a missing cluster may be created: the setting must be on.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $alias   Alias.
	 * @return bool
	 */
	private static function can_create_term( $post_id, $alias ) {
		$settings = nh_seo_get_settings();
		$allowed  = ! empty( $settings['auto_cluster_terms'] );

		/**
		 * Filters whether a missing Topic Cluster may be created from a post's alias.
		 *
		 * Creating a flat-taxonomy term needs the same capability as assigning it, which the sync already
		 * checked. Return false to restrict creation further (for example to administrators).
		 *
		 * @param bool   $allowed Whether the term may be created.
		 * @param string $alias   Alias (slug or name).
		 * @param int    $post_id Post ID.
		 */
		return (bool) apply_filters( 'nh_seo_cluster_can_create_term', $allowed, $alias, $post_id );
	}
}
