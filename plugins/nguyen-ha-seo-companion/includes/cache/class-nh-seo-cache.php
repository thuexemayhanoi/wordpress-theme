<?php
/**
 * Page-cache purging after content changes.
 *
 * During the request every relevant change only records WHAT changed (post IDs, a few URLs that cannot be
 * recomputed later, such as the previous permalink of a renamed post). At shutdown everything is resolved from
 * the final database state, de-duplicated and handed to the cache plugins ONCE. Triggers: publish, update of a
 * published post, unpublish, trash and delete, term changes that affect archives.
 *
 * Dispatch targets, each used only when it exists: LiteSpeed Cache (action litespeed_purge_url), WP Rocket
 * (rocket_clean_files), W3 Total Cache (w3tc_flush_url), WP Super Cache (wpsc_delete_url_cache) and the generic
 * action nh_seo_purge_urls( array $urls ). Nothing here makes a remote request, and the setting
 * "Purge page caches when content changes" switches the whole module off.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * Purge collector and dispatcher.
 */
final class NH_SEO_Cache {

	/**
	 * Posts whose URLs must be resolved at shutdown (IDs as keys).
	 *
	 * @var array<int, true>
	 */
	private static $posts = array();

	/**
	 * URLs that were captured at event time (old permalinks, URLs of deleted posts, term archives).
	 *
	 * @var array<string, string>
	 */
	private static $urls = array();

	/**
	 * Term archive URLs captured before a term is deleted, keyed "taxonomy:term_id".
	 *
	 * @var array<string, string[]>
	 */
	private static $term_urls = array();

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'transition_post_status', array( __CLASS__, 'on_transition_post_status' ), 10, 3 );
		add_action( 'post_updated', array( __CLASS__, 'on_post_updated' ), 10, 3 );
		add_action( 'wp_trash_post', array( __CLASS__, 'on_wp_trash_post' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'on_before_delete_post' ), 10, 2 );
		add_action( 'set_object_terms', array( __CLASS__, 'on_set_object_terms' ), 10, 6 );
		add_action( 'created_term', array( __CLASS__, 'on_created_term' ), 10, 3 );
		add_action( 'edit_terms', array( __CLASS__, 'on_edit_terms' ), 10, 2 );
		add_action( 'edited_term', array( __CLASS__, 'on_edited_term' ), 10, 3 );
		add_action( 'pre_delete_term', array( __CLASS__, 'on_pre_delete_term' ), 10, 2 );
		add_action( 'delete_term', array( __CLASS__, 'on_delete_term' ), 10, 3 );
	}

	/**
	 * Whether automatic purging is switched on in the settings.
	 *
	 * @return bool
	 */
	public static function enabled() {
		$settings = nh_seo_get_settings();
		return ! empty( $settings['purge_caches'] );
	}

	/**
	 * Hook: transition_post_status. Anything that involves "publish" changes public pages.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       Post.
	 * @return void
	 */
	public static function on_transition_post_status( $new_status, $old_status, $post ) {
		if ( $post instanceof WP_Post && ( 'publish' === $new_status || 'publish' === $old_status ) ) {
			self::queue_post( $post->ID );
		}
	}

	/**
	 * Hook: post_updated. A published post may have changed address (slug, date, categories): the old address is
	 * stale too, and it cannot be recomputed after the change.
	 *
	 * @param int     $post_id     Post ID.
	 * @param WP_Post $post_after  Post after the update.
	 * @param WP_Post $post_before Post before the update.
	 * @return void
	 */
	public static function on_post_updated( $post_id, $post_after, $post_before ) {
		if ( ! self::enabled() || ! $post_before instanceof WP_Post || 'publish' !== $post_before->post_status || ! is_post_type_viewable( $post_before->post_type ) ) {
			return;
		}
		$old = get_permalink( $post_before );
		if ( $old ) {
			self::queue_url( $old );
		}
	}

	/**
	 * Hook: wp_trash_post (fires before the post is trashed, while its address and terms still exist).
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function on_wp_trash_post( $post_id ) {
		self::capture_published_post( get_post( $post_id ) );
	}

	/**
	 * Hook: before_delete_post. A deleted post cannot be resolved at shutdown, so everything is captured now.
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Post.
	 * @return void
	 */
	public static function on_before_delete_post( $post_id, $post = null ) {
		self::capture_published_post( $post instanceof WP_Post ? $post : get_post( $post_id ) );
		unset( self::$posts[ absint( $post_id ) ] );
	}

	/**
	 * Hook: set_object_terms. A post that gains or loses terms appears in, or disappears from, their archives.
	 *
	 * @param int    $object_id  Object ID.
	 * @param array  $terms      Terms passed in.
	 * @param array  $tt_ids     Term taxonomy IDs now assigned.
	 * @param string $taxonomy   Taxonomy.
	 * @param bool   $append     Whether the terms were appended.
	 * @param array  $old_tt_ids Term taxonomy IDs before.
	 * @return void
	 */
	public static function on_set_object_terms( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
		if ( ! self::enabled() || ! is_taxonomy_viewable( $taxonomy ) || 'publish' !== get_post_status( $object_id ) ) {
			return;
		}
		$changed = array_unique( array_merge( array_diff( (array) $tt_ids, (array) $old_tt_ids ), array_diff( (array) $old_tt_ids, (array) $tt_ids ) ) );
		if ( empty( $changed ) ) {
			return;
		}
		foreach ( $changed as $tt_id ) {
			$term = get_term_by( 'term_taxonomy_id', (int) $tt_id, $taxonomy );
			if ( $term instanceof WP_Term ) {
				self::queue_urls( NH_SEO_Cache_Urls::for_term( $term ) );
			}
		}
		self::queue_post( $object_id );
	}

	/**
	 * Hook: created_term.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy.
	 * @return void
	 */
	public static function on_created_term( $term_id, $tt_id, $taxonomy ) {
		self::queue_term_archives( $term_id, $taxonomy );
	}

	/**
	 * Hook: edit_terms (before the update): the old address of the archive.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy.
	 * @return void
	 */
	public static function on_edit_terms( $term_id, $taxonomy ) {
		self::queue_term_archives( $term_id, $taxonomy );
	}

	/**
	 * Hook: edited_term (after the update): the new address of the archive.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy.
	 * @return void
	 */
	public static function on_edited_term( $term_id, $tt_id, $taxonomy ) {
		self::queue_term_archives( $term_id, $taxonomy );
	}

	/**
	 * Hook: pre_delete_term. Remember the archive addresses while the term still exists.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy.
	 * @return void
	 */
	public static function on_pre_delete_term( $term_id, $taxonomy ) {
		if ( ! self::enabled() || ! is_taxonomy_viewable( $taxonomy ) ) {
			return;
		}
		self::$term_urls[ $taxonomy . ':' . absint( $term_id ) ] = NH_SEO_Cache_Urls::for_term( (int) $term_id, $taxonomy );
	}

	/**
	 * Hook: delete_term. Purge what was remembered, plus the home page (menus and widgets list terms).
	 *
	 * @param int    $term     Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy.
	 * @return void
	 */
	public static function on_delete_term( $term, $tt_id, $taxonomy ) {
		$key = $taxonomy . ':' . absint( $term );
		if ( isset( self::$term_urls[ $key ] ) ) {
			self::queue_urls( self::$term_urls[ $key ] );
			self::queue_urls( NH_SEO_Cache_Urls::home_urls() );
			unset( self::$term_urls[ $key ] );
		}
	}

	/**
	 * Queue the archive URLs of a term (and its ancestors).
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy.
	 * @return void
	 */
	private static function queue_term_archives( $term_id, $taxonomy ) {
		if ( ! self::enabled() || ! is_taxonomy_viewable( $taxonomy ) ) {
			return;
		}
		self::queue_urls( NH_SEO_Cache_Urls::for_term( (int) $term_id, $taxonomy ) );
	}

	/**
	 * Capture everything a published post affects, right now.
	 *
	 * @param WP_Post|null $post Post.
	 * @return void
	 */
	private static function capture_published_post( $post ) {
		if ( ! self::enabled() || ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
			return;
		}
		self::queue_urls( NH_SEO_Cache_Urls::for_post( $post ) );
		self::queue_post( $post->ID );
	}

	/**
	 * Queue a post: its URLs are resolved from the final state at shutdown.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function queue_post( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id || ! self::enabled() ) {
			return;
		}
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || 'revision' === $post->post_type || ! is_post_type_viewable( $post->post_type ) ) {
			return;
		}
		self::$posts[ $post_id ] = true;
		add_action( 'shutdown', array( __CLASS__, 'flush' ), 20 );
	}

	/**
	 * Queue one URL.
	 *
	 * @param string $url URL.
	 * @return void
	 */
	public static function queue_url( $url ) {
		self::queue_urls( array( $url ) );
	}

	/**
	 * Queue URLs.
	 *
	 * @param array<int, mixed> $urls URLs.
	 * @return void
	 */
	public static function queue_urls( array $urls ) {
		if ( ! self::enabled() ) {
			return;
		}
		foreach ( NH_SEO_Cache_Urls::clean( $urls ) as $url ) {
			self::$urls[ $url ] = $url;
		}
		if ( ! empty( self::$urls ) ) {
			add_action( 'shutdown', array( __CLASS__, 'flush' ), 20 );
		}
	}

	/**
	 * Post IDs and URLs waiting for the shutdown flush.
	 *
	 * @return array{posts: int[], urls: string[]}
	 */
	public static function pending() {
		return array(
			'posts' => array_keys( self::$posts ),
			'urls'  => array_values( self::$urls ),
		);
	}

	/**
	 * Forget everything that was collected without purging (tests).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$posts     = array();
		self::$urls      = array();
		self::$term_urls = array();
	}

	/**
	 * Every URL to purge: captured URLs plus the URLs of the queued posts as they are now. De-duplicated, capped.
	 *
	 * @return string[]
	 */
	public static function collect() {
		$urls = array_values( self::$urls );
		foreach ( array_keys( self::$posts ) as $post_id ) {
			$urls = array_merge( $urls, NH_SEO_Cache_Urls::for_post( $post_id ) );
		}
		$urls = NH_SEO_Cache_Urls::clean( $urls );

		/**
		 * Filters the largest number of URLs one purge may hand to the cache plugins.
		 *
		 * @param int $max Default 300.
		 */
		$max = max( 1, (int) apply_filters( 'nh_seo_purge_max_urls', 300 ) );
		return array_slice( $urls, 0, $max );
	}

	/**
	 * Purge once. Runs at shutdown.
	 *
	 * @return void
	 */
	public static function flush() {
		if ( empty( self::$posts ) && empty( self::$urls ) ) {
			return;
		}
		$enabled = self::enabled();
		$urls    = $enabled ? self::collect() : array();
		self::reset();

		if ( $enabled && ! empty( $urls ) ) {
			self::dispatch( $urls );
		}
	}

	/**
	 * Hand URLs to every cache integration that exists. A failing cache plugin never stops the others.
	 *
	 * @param string[] $urls URLs.
	 * @return void
	 */
	public static function dispatch( array $urls ) {
		$urls = NH_SEO_Cache_Urls::clean( $urls );
		if ( empty( $urls ) ) {
			return;
		}

		/**
		 * Fires with the URLs that must be purged from page caches. Hook here to support a cache this plugin
		 * does not know. Receives a list of absolute URLs.
		 *
		 * @param string[] $urls URLs.
		 */
		do_action( 'nh_seo_purge_urls', $urls );

		$targets = array();
		if ( function_exists( 'rocket_clean_files' ) ) {
			$targets['wp-rocket'] = static function () use ( $urls ) {
				rocket_clean_files( $urls );
			};
		}
		if ( has_action( 'litespeed_purge_url' ) ) {
			$targets['litespeed'] = static function () use ( $urls ) {
				foreach ( $urls as $url ) {
					do_action( 'litespeed_purge_url', $url ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public hook of the LiteSpeed Cache plugin.
				}
			};
		}
		if ( function_exists( 'w3tc_flush_url' ) ) {
			$targets['w3-total-cache'] = static function () use ( $urls ) {
				foreach ( $urls as $url ) {
					w3tc_flush_url( $url );
				}
			};
		}
		if ( function_exists( 'wpsc_delete_url_cache' ) ) {
			$targets['wp-super-cache'] = static function () use ( $urls ) {
				foreach ( $urls as $url ) {
					wpsc_delete_url_cache( $url );
				}
			};
		}

		foreach ( $targets as $name => $purge ) {
			try {
				$purge();
			} catch ( Throwable $error ) {
				/**
				 * Fires when a cache plugin throws while it is being purged. The other caches are still purged.
				 *
				 * @param string    $name  Integration: wp-rocket, litespeed, w3-total-cache or wp-super-cache.
				 * @param Throwable $error The error.
				 */
				do_action( 'nh_seo_purge_failed', $name, $error );
			}
		}
	}

	/**
	 * URLs affected by a post right now, for tools such as WP-CLI.
	 *
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	public static function urls_for_post( $post_id ) {
		return NH_SEO_Cache_Urls::for_post( $post_id );
	}
}
