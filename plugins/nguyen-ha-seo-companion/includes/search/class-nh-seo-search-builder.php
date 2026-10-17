<?php
/**
 * Full and partial index builds, in bounded batches.
 *
 * One batch re-indexes up to 200 posts (filter nh_seo_search_batch_size, 10 to 500) walking the post IDs in
 * ascending order with a keyset cursor, never an OFFSET, and stops early when it has used its time budget.
 * Running the same batch twice gives the same index (rows are replaced by primary key), so a browser that
 * retries a request after a network error cannot corrupt anything.
 *
 * "ready" is set only when a build started with start() has walked every post. A second start() supersedes
 * the first (build token), so two administrators cannot interleave.
 *
 * Used by the Tools panel (AJAX) and WP-CLI.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * Index builder.
 */
final class NH_SEO_Search_Builder {

	/**
	 * Default posts per batch.
	 */
	const DEFAULT_BATCH = 200;

	/**
	 * Seconds one batch may run before it hands control back.
	 */
	const TIME_BUDGET = 15;

	/**
	 * Clamp a requested batch size.
	 *
	 * @param int $requested Requested size, 0 for the default.
	 * @return int 10 to 500.
	 */
	public static function batch_size( $requested = 0 ) {
		$size = absint( $requested ) > 0 ? absint( $requested ) : self::DEFAULT_BATCH;

		/**
		 * Filters the number of posts one index batch handles.
		 *
		 * @param int $size Requested batch size.
		 */
		$size = (int) apply_filters( 'nh_seo_search_batch_size', $size );
		return min( 500, max( 10, $size ) );
	}

	/**
	 * Start a full rebuild: create the table when needed, empty it, and remember the build.
	 *
	 * @return array<string, mixed>|WP_Error The new state (with the build token), or an error.
	 */
	public static function start() {
		if ( ! NH_SEO_Search_Index::ensure_table() ) {
			$message = __( 'The index table could not be created. InnoDB full-text indexes need MySQL 5.6 or MariaDB 10.0.5 or newer.', 'nguyen-ha-seo-companion' );
			NH_SEO_Search_State::save( array( 'last_error' => $message ) );
			return new WP_Error( 'nh_seo_search_table', $message );
		}

		NH_SEO_Search_Index::truncate();
		NH_SEO_Search_State::clear_breaker();

		$state = NH_SEO_Search_State::save(
			array(
				'ready'      => false,
				'building'   => true,
				'build'      => wp_generate_password( 12, false ),
				'total'      => NH_SEO_Search_Index::count_indexable(),
				'indexed'    => 0,
				'cursor'     => 0,
				'last_run'   => time(),
				'last_error' => '',
				'stale'      => false,
				'gap'        => false,
			)
		);
		NH_SEO_Search_State::bump_query_caches();
		return $state;
	}

	/**
	 * Index the next batch of posts.
	 *
	 * @param int           $after_id Cursor returned by the previous batch (0 to begin).
	 * @param int           $size     Batch size (0 for the default).
	 * @param string        $build    Build token from start(); required while a build is running.
	 * @param string[]|null $types    Restrict to these post types (a partial pass that never sets "ready").
	 * @return array<string, mixed>|WP_Error cursor, processed, indexed, total, done, percent; or an error.
	 */
	public static function run_batch( $after_id = 0, $size = 0, $build = '', $types = null ) {
		$state = NH_SEO_Search_State::get();

		if ( $state['building'] && null === $types && ! hash_equals( $state['build'], (string) $build ) ) {
			return new WP_Error( 'nh_seo_search_superseded', __( 'Another index build has started. This one was stopped.', 'nguyen-ha-seo-companion' ) );
		}
		if ( ! NH_SEO_Search_Index::ensure_table() ) {
			return new WP_Error( 'nh_seo_search_table', __( 'The index table does not exist and could not be created.', 'nguyen-ha-seo-companion' ) );
		}

		$size  = self::batch_size( $size );
		$after = absint( $after_id );

		/**
		 * Filters the seconds one index batch may run before it hands control back to the caller.
		 *
		 * @param int $seconds Default 15.
		 */
		$budget = (float) apply_filters( 'nh_seo_search_time_budget', self::TIME_BUDGET );
		// One more ID than the batch needs tells whether anything is left after this batch.
		$ids    = NH_SEO_Search_Index::next_indexable_ids( $after, $size + 1, $types );
		$ended  = count( $ids ) <= $size;
		$ids    = array_slice( $ids, 0, $size );
		$cursor = $after;
		$count  = 0;
		$begin  = microtime( true );

		// One query for the posts, one for their terms and one for their meta, instead of three per post.
		$posts  = empty( $ids ) ? array() : get_posts(
			array(
				'post__in'               => $ids,
				'post_type'              => 'any',
				'post_status'            => 'publish',
				'orderby'                => 'post__in',
				'posts_per_page'         => count( $ids ),
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'suppress_filters'       => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => true,
			)
		);
		$loaded = array();
		foreach ( $posts as $post ) {
			$loaded[ $post->ID ] = $post;
		}

		foreach ( $ids as $post_id ) {
			// At least one post per batch, so that a batch always makes progress.
			if ( $count > 0 && ( microtime( true ) - $begin ) > $budget ) {
				$ended = false;
				break;
			}
			NH_SEO_Search_Indexer::reindex_post( isset( $loaded[ $post_id ] ) ? $loaded[ $post_id ] : $post_id );
			$cursor = $post_id;
			++$count;
		}

		$indexed = NH_SEO_Search_Index::count();
		$done    = $ended && count( $ids ) === $count;

		$changes = array(
			'indexed'  => $indexed,
			'last_run' => time(),
		);
		if ( null === $types ) {
			$changes['cursor'] = $cursor;
		}
		if ( $done && null === $types && $state['building'] ) {
			$changes['ready']      = true;
			$changes['building']   = false;
			$changes['build']      = '';
			$changes['last_error'] = '';
		}
		$state = NH_SEO_Search_State::save( $changes );

		if ( $done ) {
			NH_SEO_Search_State::bump_query_caches();
		}

		return array(
			'cursor'    => $cursor,
			'processed' => $count,
			'indexed'   => $indexed,
			'total'     => $state['total'],
			'done'      => $done,
			'percent'   => $done ? 100 : ( $state['total'] > 0 ? min( 99, (int) floor( 100 * $indexed / $state['total'] ) ) : 0 ),
			'ready'     => $state['ready'],
		);
	}

	/**
	 * Rebuild the whole index synchronously, batch by batch (WP-CLI and tests).
	 *
	 * @param int           $size     Batch size.
	 * @param callable|null $progress Called after every batch with the batch result.
	 * @return array<string, mixed>|WP_Error Last batch result, or an error.
	 */
	public static function rebuild( $size = 0, $progress = null ) {
		$state = self::start();
		if ( is_wp_error( $state ) ) {
			return $state;
		}
		return self::walk( $size, $state['build'], null, $progress );
	}

	/**
	 * Re-index only some post types without emptying the index, then prune leftovers. Never sets "ready".
	 *
	 * @param string[]      $types    Post types.
	 * @param int           $size     Batch size.
	 * @param callable|null $progress Called after every batch with the batch result.
	 * @return array<string, mixed>|WP_Error Last batch result, or an error.
	 */
	public static function reindex_types( array $types, $size = 0, $progress = null ) {
		$result = self::walk( $size, '', array_values( $types ), $progress );
		if ( ! is_wp_error( $result ) ) {
			NH_SEO_Search_Index::prune();
		}
		return $result;
	}

	/**
	 * Run batches until the walk is done. The loop is bounded: the cursor must advance on every pass.
	 *
	 * @param int           $size     Batch size.
	 * @param string        $build    Build token.
	 * @param string[]|null $types    Post types or null.
	 * @param callable|null $progress Callback.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function walk( $size, $build, $types, $progress ) {
		$cursor = 0;
		$result = array();
		// Posts published during the walk are caught by the hooks, so the number of passes is bounded by what existed.
		$limit = (int) ceil( max( 1, NH_SEO_Search_Index::count_indexable( $types ) ) / self::batch_size( $size ) ) + 50;

		for ( $pass = 0; $pass < $limit; $pass++ ) {
			$result = self::run_batch( $cursor, $size, $build, $types );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			if ( is_callable( $progress ) ) {
				call_user_func( $progress, $result );
			}
			if ( $result['done'] ) {
				return $result;
			}
			if ( $result['cursor'] <= $cursor && 0 === $result['processed'] ) {
				break; // No progress: stop instead of looping.
			}
			$cursor = $result['cursor'];

			// Long CLI walks must not keep every post in memory.
			if ( function_exists( 'wp_cache_flush_runtime' ) && ! wp_using_ext_object_cache() ) {
				wp_cache_flush_runtime();
			}
		}
		return $result;
	}

	/**
	 * Empty the index and forget the state.
	 *
	 * @return void
	 */
	public static function clear() {
		if ( NH_SEO_Search_Index::table_exists() ) {
			NH_SEO_Search_Index::truncate();
		}
		NH_SEO_Search_State::reset();
		NH_SEO_Search_State::clear_breaker();
		NH_SEO_Search_State::bump_query_caches();
	}
}
