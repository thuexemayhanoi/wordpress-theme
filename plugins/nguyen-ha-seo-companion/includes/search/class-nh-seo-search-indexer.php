<?php
/**
 * Keeps the search index in step with the content.
 *
 * Changes are collected during the request and written ONCE at shutdown, from the final state of each post
 * (REST requests save meta and terms after the post row; classic saves run many save_post callbacks). A
 * request never re-indexes more than a handful of posts inline: the rest goes to WP-Cron in chunks.
 *
 *  - save, publish, meta and term changes of a post       queued, written at shutdown;
 *  - unpublish, trash and delete                           the row is removed immediately;
 *  - rename of a term                                      WP-Cron walks the term's posts in batches;
 *  - deletion of a term                                    the affected posts are queued (inline cap, then cron).
 *
 * Revisions, autosaves, drafts, private and password protected posts never get a row.
 *
 * Nothing keeps the index in step while the plugin is inactive: activation compares the index with the content
 * (repair_after_gap()), and the status reports updates that WP-Cron has not run (queued_updates()).
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * Incremental indexing.
 */
final class NH_SEO_Search_Indexer {

	/**
	 * WP-Cron hook: re-index the posts of one term, in batches.
	 */
	const CRON_TERM = 'nh_seo_search_reindex_term';

	/**
	 * WP-Cron hook: re-index an explicit chunk of post IDs.
	 */
	const CRON_IDS = 'nh_seo_search_reindex_ids';

	/**
	 * Posts per WP-Cron batch.
	 */
	const CRON_BATCH = 100;

	/**
	 * Most chunk events one change may schedule before the index is flagged as stale.
	 */
	const MAX_CRON_EVENTS = 100;

	/**
	 * Seconds after which a queued index update counts as overdue (WP-Cron does not seem to run).
	 */
	const OVERDUE_SECONDS = HOUR_IN_SECONDS;

	/**
	 * Post IDs waiting for the shutdown flush: site ID => post ID => true. A post ID means nothing without its site
	 * on a network, where code may switch_to_blog() before it saves a post.
	 *
	 * @var array<int, array<int, true>>
	 */
	private static $queue = array();

	/**
	 * Term names captured before an update, keyed "taxonomy:term_id".
	 *
	 * @var array<string, string>
	 */
	private static $old_term_names = array();

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'save_post', array( __CLASS__, 'on_save_post' ), 99, 2 );
		add_action( 'transition_post_status', array( __CLASS__, 'on_transition_post_status' ), 10, 3 );
		add_action( 'deleted_post', array( __CLASS__, 'on_deleted_post' ), 10, 2 );
		add_action( 'added_post_meta', array( __CLASS__, 'on_post_meta' ), 10, 3 );
		add_action( 'updated_post_meta', array( __CLASS__, 'on_post_meta' ), 10, 3 );
		add_action( 'deleted_post_meta', array( __CLASS__, 'on_deleted_post_meta' ), 10, 3 );
		add_action( 'set_object_terms', array( __CLASS__, 'on_object_terms' ), 10, 4 );
		add_action( 'deleted_term_relationships', array( __CLASS__, 'on_deleted_term_relationships' ), 10, 3 );
		add_action( 'edit_terms', array( __CLASS__, 'on_edit_terms' ), 10, 2 );
		add_action( 'edited_term', array( __CLASS__, 'on_edited_term' ), 10, 3 );
		add_action( 'delete_term', array( __CLASS__, 'on_delete_term' ), 10, 5 );
		add_action( self::CRON_TERM, array( __CLASS__, 'cron_reindex_term' ), 10, 3 );
		add_action( self::CRON_IDS, array( __CLASS__, 'cron_reindex_ids' ), 10, 1 );
	}

	/**
	 * Unschedule every pending event (plugin deactivation). The index and its data are kept.
	 *
	 * @return void
	 */
	public static function on_deactivate() {
		wp_unschedule_hook( self::CRON_TERM );
		wp_unschedule_hook( self::CRON_IDS );
	}

	/**
	 * Plugin activation: bring a ready index back in step with the content.
	 *
	 * Nothing maintained the index while the plugin was inactive, so posts published, edited, unpublished or deleted
	 * in that time (an AI publishing workflow, an import, another administrator) left rows missing, outdated or
	 * orphaned. A small gap is repaired right away, in this request. A larger one flags the index as not matching
	 * the content: in "auto" mode searches use native search again until an administrator rebuilds it. An index that
	 * was never built, or is being built, is left alone: building is always an explicit action.
	 *
	 * A term renamed while the plugin was inactive is not noticed (the posts did not change): rebuild after that.
	 *
	 * @return void
	 */
	public static function repair_after_gap() {
		$state = NH_SEO_Search_State::get();
		if ( ! $state['ready'] || $state['building'] || ! NH_SEO_Search_Index::table_exists() ) {
			return;
		}
		NH_SEO_Search_Index::prune();

		/**
		 * Filters how many posts the repair at activation may re-index in that one request. More than that and the
		 * index is flagged as not matching the content instead.
		 *
		 * @param int $limit Default 200.
		 */
		$limit = max( 1, (int) apply_filters( 'nh_seo_search_activation_repair_limit', 200 ) );
		$ids   = NH_SEO_Search_Index::unsynced_ids( $limit + 1 );

		if ( count( $ids ) > $limit ) {
			NH_SEO_Search_State::save( array( 'gap' => true ) );
		} else {
			foreach ( $ids as $post_id ) {
				self::reindex_post( $post_id );
			}
		}
		NH_SEO_Search_State::bump_query_caches();
	}

	/**
	 * Index updates that sit in the WP-Cron queue, and how many of them are long overdue.
	 *
	 * @return array{queued: int, overdue: int}
	 */
	public static function queued_updates() {
		$queued  = 0;
		$overdue = 0;
		$late    = time() - self::OVERDUE_SECONDS;
		foreach ( (array) _get_cron_array() as $timestamp => $hooks ) {
			foreach ( array( self::CRON_TERM, self::CRON_IDS ) as $hook ) {
				if ( empty( $hooks[ $hook ] ) ) {
					continue;
				}
				$count   = count( $hooks[ $hook ] );
				$queued += $count;
				if ( (int) $timestamp < $late ) {
					$overdue += $count;
				}
			}
		}
		return array(
			'queued'  => $queued,
			'overdue' => $overdue,
		);
	}

	/**
	 * Hook: save_post.
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Post.
	 * @return void
	 */
	public static function on_save_post( $post_id, $post = null ) {
		self::queue( $post_id, $post );
	}

	/**
	 * Hook: transition_post_status. Leaving "publish" removes the row at once.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       Post.
	 * @return void
	 */
	public static function on_transition_post_status( $new_status, $old_status, $post ) {
		if ( ! $post instanceof WP_Post || ! self::is_indexed_type( $post->post_type ) ) {
			return;
		}
		if ( 'publish' === $new_status ) {
			self::queue( $post->ID, $post );
		} elseif ( 'publish' === $old_status && NH_SEO_Search_Index::table_exists() ) {
			NH_SEO_Search_Index::delete_post( $post->ID );
			self::forget( $post->ID );
		}
	}

	/**
	 * Hook: deleted_post.
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Deleted post.
	 * @return void
	 */
	public static function on_deleted_post( $post_id, $post = null ) {
		self::forget( absint( $post_id ) );
		if ( $post instanceof WP_Post && ! self::is_indexed_type( $post->post_type ) ) {
			return;
		}
		if ( NH_SEO_Search_Index::table_exists() ) {
			NH_SEO_Search_Index::delete_post( $post_id );
		}
	}

	/**
	 * Hook: added_post_meta and updated_post_meta.
	 *
	 * @param int    $meta_id  Meta ID.
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Meta key.
	 * @return void
	 */
	public static function on_post_meta( $meta_id, $post_id, $meta_key ) {
		if ( in_array( $meta_key, NH_SEO_Search_Document::watched_meta_keys(), true ) ) {
			self::queue( $post_id );
		}
	}

	/**
	 * Hook: deleted_post_meta (receives the array of deleted meta IDs).
	 *
	 * @param int[]  $meta_ids Meta IDs.
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Meta key.
	 * @return void
	 */
	public static function on_deleted_post_meta( $meta_ids, $post_id, $meta_key ) {
		self::on_post_meta( 0, $post_id, $meta_key );
	}

	/**
	 * Hook: set_object_terms.
	 *
	 * @param int    $object_id Object ID.
	 * @param array  $terms     Terms passed in.
	 * @param array  $tt_ids    Term taxonomy IDs now assigned.
	 * @param string $taxonomy  Taxonomy.
	 * @return void
	 */
	public static function on_object_terms( $object_id, $terms, $tt_ids, $taxonomy ) {
		if ( in_array( $taxonomy, NH_SEO_Search_Index::indexable_taxonomies(), true ) ) {
			self::queue( $object_id );
		}
	}

	/**
	 * Hook: deleted_term_relationships.
	 *
	 * @param int    $object_id Object ID.
	 * @param array  $tt_ids    Term taxonomy IDs removed.
	 * @param string $taxonomy  Taxonomy.
	 * @return void
	 */
	public static function on_deleted_term_relationships( $object_id, $tt_ids, $taxonomy ) {
		if ( in_array( $taxonomy, NH_SEO_Search_Index::indexable_taxonomies(), true ) ) {
			self::queue( $object_id );
		}
	}

	/**
	 * Hook: edit_terms (before a term is updated). Remember the old name.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy.
	 * @return void
	 */
	public static function on_edit_terms( $term_id, $taxonomy ) {
		if ( ! in_array( $taxonomy, NH_SEO_Search_Index::indexable_taxonomies(), true ) ) {
			return;
		}
		$term = get_term( $term_id, $taxonomy );
		if ( $term instanceof WP_Term ) {
			self::$old_term_names[ $taxonomy . ':' . (int) $term_id ] = $term->name;
		}
	}

	/**
	 * Hook: edited_term. A renamed term changes the taxonomy words of every post that has it: WP-Cron walks them.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy.
	 * @return void
	 */
	public static function on_edited_term( $term_id, $tt_id, $taxonomy ) {
		if ( ! in_array( $taxonomy, NH_SEO_Search_Index::indexable_taxonomies(), true ) ) {
			return;
		}
		$key = $taxonomy . ':' . (int) $term_id;
		$old = isset( self::$old_term_names[ $key ] ) ? self::$old_term_names[ $key ] : null;
		unset( self::$old_term_names[ $key ] );

		$term = get_term( $term_id, $taxonomy );
		if ( $term instanceof WP_Term && null !== $old && $old === $term->name ) {
			return; // Description, slug or parent changed: no indexed word did.
		}
		self::schedule_term( $taxonomy, (int) $term_id, 0 );
	}

	/**
	 * Hook: delete_term. The posts that had the term lose its words.
	 *
	 * @param int    $term         Term ID.
	 * @param int    $tt_id        Term taxonomy ID.
	 * @param string $taxonomy     Taxonomy.
	 * @param mixed  $deleted_term Deleted term object.
	 * @param array  $object_ids   IDs of the objects that had the term.
	 * @return void
	 */
	public static function on_delete_term( $term, $tt_id, $taxonomy, $deleted_term = null, $object_ids = array() ) {
		if ( in_array( $taxonomy, NH_SEO_Search_Index::indexable_taxonomies(), true ) ) {
			self::queue_many( (array) $object_ids );
		}
	}

	/**
	 * Whether a post type is indexed.
	 *
	 * @param string $post_type Post type.
	 * @return bool
	 */
	public static function is_indexed_type( $post_type ) {
		return in_array( $post_type, NH_SEO_Search_Index::indexable_types(), true );
	}

	/**
	 * Queue one post for the shutdown flush.
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Post when already loaded.
	 * @return void
	 */
	public static function queue( $post_id, $post = null ) {
		$post_id = absint( $post_id );
		$site_id = get_current_blog_id();
		if ( ! $post_id || isset( self::$queue[ $site_id ][ $post_id ] ) ) {
			return;
		}
		$post = $post instanceof WP_Post ? $post : get_post( $post_id );
		if ( ! $post instanceof WP_Post || ! self::is_indexed_type( $post->post_type ) ) {
			return; // Also skips revisions and autosaves: their post type is "revision".
		}
		self::$queue[ $site_id ][ $post_id ] = true;
		add_action( 'shutdown', array( __CLASS__, 'flush' ), 5 );
	}

	/**
	 * Take a post of the current site out of the queue.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private static function forget( $post_id ) {
		$site_id = get_current_blog_id();
		unset( self::$queue[ $site_id ][ $post_id ] );
		if ( empty( self::$queue[ $site_id ] ) ) {
			unset( self::$queue[ $site_id ] );
		}
	}

	/**
	 * Queue several posts.
	 *
	 * @param array<int, mixed> $post_ids Post IDs.
	 * @return void
	 */
	public static function queue_many( array $post_ids ) {
		foreach ( $post_ids as $post_id ) {
			self::queue( $post_id );
		}
	}

	/**
	 * Post IDs of the current site waiting for the flush.
	 *
	 * @return int[]
	 */
	public static function pending() {
		$site_id = get_current_blog_id();
		return isset( self::$queue[ $site_id ] ) ? array_keys( self::$queue[ $site_id ] ) : array();
	}

	/**
	 * Forget queued work without doing it (tests).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$queue          = array();
		self::$old_term_names = array();
	}

	/**
	 * Write the queued posts. Runs at shutdown.
	 *
	 * At most 50 posts are written inline (filter nh_seo_search_inline_limit) and never for longer than a few
	 * seconds; whatever is left goes to WP-Cron in chunks. On a network each site's posts are written in that
	 * site, whichever site is current when the request ends.
	 *
	 * @return void
	 */
	public static function flush() {
		if ( empty( self::$queue ) ) {
			return;
		}
		$queue       = self::$queue;
		self::$queue = array();

		foreach ( $queue as $site_id => $posts ) {
			self::flush_site( (int) $site_id, array_keys( $posts ) );
		}
	}

	/**
	 * Write the queued posts of one site.
	 *
	 * @param int   $site_id Site ID.
	 * @param int[] $ids     Post IDs.
	 * @return void
	 */
	private static function flush_site( $site_id, array $ids ) {
		$switched = is_multisite() && get_current_blog_id() !== $site_id;
		if ( $switched ) {
			if ( ! get_site( $site_id ) instanceof WP_Site ) {
				return; // The site was deleted during the request.
			}
			switch_to_blog( $site_id );
		}

		try {
			if ( ! NH_SEO_Search_Index::table_exists() ) {
				return;
			}

			/**
			 * Filters how many posts one request may re-index inline.
			 *
			 * @param int $limit Default 50.
			 */
			$limit   = max( 1, (int) apply_filters( 'nh_seo_search_inline_limit', 50 ) );
			$started = microtime( true );
			$rest    = array();

			foreach ( $ids as $position => $post_id ) {
				if ( $position >= $limit || ( microtime( true ) - $started ) > 4 ) {
					$rest = array_slice( $ids, $position );
					break;
				}
				self::reindex_post( $post_id );
			}

			if ( ! empty( $rest ) ) {
				self::schedule_ids( $rest );
			}
			NH_SEO_Search_State::bump_query_caches();
		} finally {
			if ( $switched ) {
				restore_current_blog();
			}
		}
	}

	/**
	 * Bring the row of one post in line with the post: written when public, removed otherwise.
	 *
	 * @param WP_Post|int $post Post or ID.
	 * @return string indexed | removed
	 */
	public static function reindex_post( $post ) {
		$post_id = $post instanceof WP_Post ? $post->ID : absint( $post );
		$post    = get_post( $post_id );

		if ( $post instanceof WP_Post && NH_SEO_Search_Index::is_indexable( $post ) ) {
			NH_SEO_Search_Index::upsert( $post->ID, NH_SEO_Search_Document::build( $post ) );
			return 'indexed';
		}
		NH_SEO_Search_Index::delete_post( $post_id );
		return 'removed';
	}

	/**
	 * Schedule explicit chunks of post IDs.
	 *
	 * @param int[] $ids Post IDs.
	 * @return void
	 */
	private static function schedule_ids( array $ids ) {
		$chunks = array_chunk( array_values( array_unique( array_map( 'absint', $ids ) ) ), self::CRON_BATCH );
		foreach ( $chunks as $index => $chunk ) {
			if ( $index >= self::MAX_CRON_EVENTS ) {
				NH_SEO_Search_State::save( array( 'stale' => true ) );
				return;
			}
			wp_schedule_single_event( time() + 10 + ( 2 * $index ), self::CRON_IDS, array( $chunk ) );
		}
	}

	/**
	 * Schedule the next batch of a term walk.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param int    $term_id  Term ID.
	 * @param int    $after_id Only posts above this ID.
	 * @return void
	 */
	public static function schedule_term( $taxonomy, $term_id, $after_id ) {
		wp_schedule_single_event( time() + 10, self::CRON_TERM, array( (string) $taxonomy, absint( $term_id ), absint( $after_id ) ) );
	}

	/**
	 * WP-Cron: re-index one batch of the posts that have a term, then schedule the next batch when it was full.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param int    $term_id  Term ID.
	 * @param int    $after_id Cursor.
	 * @return int Number of posts handled.
	 */
	public static function cron_reindex_term( $taxonomy, $term_id, $after_id = 0 ) {
		global $wpdb;
		if ( ! NH_SEO_Search_Index::table_exists() ) {
			return 0;
		}

		// Reads WordPress's own term tables by primary key, bounded by LIMIT.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT tr.object_id FROM {$wpdb->term_relationships} AS tr INNER JOIN {$wpdb->term_taxonomy} AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tt.term_id = %d AND tt.taxonomy = %s AND tr.object_id > %d ORDER BY tr.object_id ASC LIMIT %d",
				absint( $term_id ),
				(string) $taxonomy,
				absint( $after_id ),
				self::CRON_BATCH
			)
		);
		$ids = array_map( 'intval', (array) $ids );

		foreach ( $ids as $post_id ) {
			self::reindex_post( $post_id );
		}
		if ( count( $ids ) >= self::CRON_BATCH ) {
			self::schedule_term( $taxonomy, $term_id, (int) end( $ids ) );
		}
		NH_SEO_Search_State::bump_query_caches();
		return count( $ids );
	}

	/**
	 * WP-Cron: re-index an explicit chunk of posts.
	 *
	 * @param array<int, mixed> $ids Post IDs.
	 * @return int Number of posts handled.
	 */
	public static function cron_reindex_ids( $ids ) {
		if ( ! is_array( $ids ) || ! NH_SEO_Search_Index::table_exists() ) {
			return 0;
		}
		$ids = array_slice( array_map( 'absint', $ids ), 0, self::CRON_BATCH );
		foreach ( $ids as $post_id ) {
			self::reindex_post( $post_id );
		}
		NH_SEO_Search_State::bump_query_caches();
		return count( $ids );
	}
}
