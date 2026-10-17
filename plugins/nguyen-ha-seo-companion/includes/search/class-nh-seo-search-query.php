<?php
/**
 * Search through the index: integration with WP_Query.
 *
 * For every WP_Query that has a search string (the main search query, REST /wp/v2/search, REST post
 * searches, theme queries) and only when it is safe, the LIKE clauses are replaced:
 *
 *  posts_search   WHERE   MATCH(title_fold, tax_fold, body_fold) AGAINST ('+tok* +tok*' IN BOOLEAN MODE)
 *  posts_join     INNER JOIN the index on post_id
 *  posts_orderby  relevance = title x 3 + taxonomy x 2 + all columns x 1, then newest first
 *                 (only when the caller did not choose an orderby)
 *
 * The user's text enters the SQL only as folded [a-z0-9] tokens through $wpdb->prepare().
 *
 * Fallbacks, all inside the one WP_Query call:
 *  - strict (AND) finds nothing on the first page -> one retry with OR semantics ("relaxed",
 *    see nh_seo_search_was_relaxed()); still nothing -> native search;
 *  - any SQL error -> the index is paused for ten minutes and native search answers this very request.
 *
 * Native search is used without any attempt when the index is off or not ready, when no usable token remains
 * (everything shorter than three characters or an InnoDB stop-word), for phrase / exact / "-exclusion"
 * searches, for custom search columns, when the query may return anything but public posts of indexed types,
 * and in the admin (unless the query asks for published posts explicitly).
 *
 * Private posts are not indexed: users who can read them do not see them in index-backed results.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * WP_Query integration.
 */
final class NH_SEO_Search_Query {

	/**
	 * Query variable holding the plan (stage and tokens) of a query.
	 */
	const VAR_PLAN = 'nh_seo_search_plan';

	/**
	 * Query variable set when the displayed results come from the relaxed (OR) retry.
	 */
	const VAR_RELAXED = 'nh_seo_search_relaxed';

	/**
	 * Alias of the index table in the generated SQL. The SQL templates below spell it out literally (so that
	 * every query stays a plain prepared template); keep them in step with this constant.
	 */
	const ALIAS = 'nh_si';

	/**
	 * Register hooks. They run late so that they see what other plugins did to the search clause.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'posts_search', array( __CLASS__, 'filter_search' ), 20, 2 );
		add_filter( 'posts_join', array( __CLASS__, 'filter_join' ), 20, 2 );
		add_filter( 'posts_orderby', array( __CLASS__, 'filter_orderby' ), 20, 2 );
		add_filter( 'posts_results', array( __CLASS__, 'filter_results' ), 20, 2 );
	}

	/**
	 * Whether the displayed results of a query came from the relaxed (any word) retry.
	 *
	 * @param WP_Query|null $query Query, or null for the main query.
	 * @return bool
	 */
	public static function was_relaxed( $query = null ) {
		if ( ! $query instanceof WP_Query ) {
			$query = isset( $GLOBALS['wp_query'] ) ? $GLOBALS['wp_query'] : null;
		}
		return $query instanceof WP_Query && (bool) $query->get( self::VAR_RELAXED );
	}

	/**
	 * Whether searches are answered through the index right now, for the site as a whole.
	 *
	 * True when the index is built and usable (NH_SEO_Search_State::is_active(): the mode allows it, the build is
	 * complete in "auto" mode, the table exists and no error pause is running) and the filter
	 * nh_seo_search_enabled has not switched it off. That filter is normally asked about one particular query;
	 * for this site-wide question it receives an empty WP_Query, so a callback that disables the index only for
	 * some queries cannot be seen here. Individual queries can still fall back to native search (phrases,
	 * words of one or two letters, other post types), which is not reflected in this answer.
	 *
	 * @return bool
	 */
	public static function is_serving() {
		if ( ! NH_SEO_Search_State::is_active() ) {
			return false;
		}
		/** This filter is documented in NH_SEO_Search_Query::plan(). */
		return (bool) apply_filters( 'nh_seo_search_enabled', true, new WP_Query() );
	}

	/**
	 * Filter: nh_editorial_search_index_active, asked by the Nguyen Ha Editorial theme.
	 *
	 * The theme switches its search-as-you-type suggestions off on very large sites because the built-in
	 * search reads every article (2 to 3.5 s at 50,000 articles). It asks this filter whether a fast index
	 * is serving the search, and turns the suggestions back on when the answer is true.
	 *
	 * The answer is true exactly when is_serving() is. It never turns an earlier true into false: another
	 * search plugin may have reported its own index.
	 *
	 * @param bool $active Whether an index is active, as reported so far.
	 * @return bool
	 */
	public static function filter_index_active( $active = false ) {
		return (bool) $active || self::is_serving();
	}

	/**
	 * Filter: posts_search.
	 *
	 * @param string   $search Search SQL.
	 * @param WP_Query $query  Query.
	 * @return string
	 */
	public static function filter_search( $search, $query ) {
		global $wpdb;
		$plan = self::plan( $query );
		if ( ! self::uses_index( $plan ) ) {
			return $search;
		}

		// The index never holds password protected posts, and neither may its results, even from a stale row.
		return $wpdb->prepare(
			' AND ( MATCH(nh_si.title_fold, nh_si.tax_fold, nh_si.body_fold) AGAINST (%s IN BOOLEAN MODE) ) ',
			self::boolean( $plan, 'strict' === $plan['stage'] )
		) . " AND ( {$wpdb->posts}.post_password = '' ) ";
	}

	/**
	 * Filter: posts_join.
	 *
	 * @param string   $join  JOIN SQL.
	 * @param WP_Query $query Query.
	 * @return string
	 */
	public static function filter_join( $join, $query ) {
		global $wpdb;
		$plan = $query instanceof WP_Query ? $query->get( self::VAR_PLAN ) : null;
		if ( ! self::uses_index( $plan ) ) {
			return $join;
		}
		return $join . ' INNER JOIN ' . NH_SEO_Search_Index::table() . ' AS ' . self::ALIAS . ' ON ( ' . self::ALIAS . ".post_id = {$wpdb->posts}.ID ) ";
	}

	/**
	 * Filter: posts_orderby. Relevance unless the caller chose an order.
	 *
	 * @param string   $orderby ORDER BY SQL.
	 * @param WP_Query $query   Query.
	 * @return string
	 */
	public static function filter_orderby( $orderby, $query ) {
		global $wpdb;
		$plan = $query instanceof WP_Query ? $query->get( self::VAR_PLAN ) : null;
		if ( ! self::uses_index( $plan ) || ! self::wants_relevance( $query ) ) {
			return $orderby;
		}

		$any   = self::boolean( $plan, false ); // Title and taxonomy are scored per matching word.
		$match = self::boolean( $plan, 'strict' === $plan['stage'] );

		return $wpdb->prepare(
			'( MATCH(nh_si.title_fold) AGAINST (%s IN BOOLEAN MODE) * 3 + MATCH(nh_si.tax_fold) AGAINST (%s IN BOOLEAN MODE) * 2 + MATCH(nh_si.title_fold, nh_si.tax_fold, nh_si.body_fold) AGAINST (%s IN BOOLEAN MODE) ) DESC',
			$any,
			$any,
			$match
		) . ", {$wpdb->posts}.post_date DESC, {$wpdb->posts}.ID DESC";
	}

	/**
	 * Filter: posts_results. Decides, after the query ran, whether to relax, to fall back, or to accept.
	 *
	 * @param WP_Post[] $posts Posts.
	 * @param WP_Query  $query Query.
	 * @return WP_Post[]
	 */
	public static function filter_results( $posts, $query ) {
		global $wpdb;
		$plan = $query instanceof WP_Query ? $query->get( self::VAR_PLAN ) : null;
		if ( ! self::uses_index( $plan ) ) {
			return $posts;
		}

		$stage = $plan['stage'];
		$next  = $stage;

		if ( '' !== (string) $wpdb->last_error && $wpdb->last_query === $query->request ) {
			NH_SEO_Search_State::trip( $wpdb->last_error );
			// WP_Query has just cached the empty result of the failed query: drop every cached query result.
			NH_SEO_Search_State::bump_query_caches();
			$next = 'native';
		} elseif ( empty( $posts ) ) {
			$first_page = (int) $query->get( 'paged' ) <= 1 && 0 === (int) $query->get( 'offset' );
			if ( $first_page ) {
				$next = ( 'strict' === $stage && count( $plan['tokens'] ) > 1 ) ? 'relaxed' : 'native';
			} else {
				// An empty later page is either "past the end" or "this search was relaxed on page 1".
				$next = self::stage_by_probe( $plan );
			}
		}

		if ( $next === $stage ) {
			if ( 'relaxed' === $stage && ! empty( $posts ) ) {
				$query->set( self::VAR_RELAXED, 1 );
			}
			return $posts;
		}

		$plan['stage'] = $next;
		$query->set( self::VAR_PLAN, $plan );
		$query->set( self::VAR_RELAXED, 0 );
		return $query->get_posts();
	}

	/**
	 * Whether a plan means "run through the index".
	 *
	 * @param mixed $plan Plan from the query variable.
	 * @return bool
	 */
	private static function uses_index( $plan ) {
		return is_array( $plan ) && isset( $plan['stage'], $plan['tokens'] ) && in_array( $plan['stage'], array( 'strict', 'relaxed' ), true );
	}

	/**
	 * Boolean expression of a plan.
	 *
	 * @param array $plan     Plan.
	 * @param bool  $required Whether every word is required.
	 * @return string
	 */
	private static function boolean( array $plan, $required ) {
		return NH_SEO_Search_Text::boolean_expression( $plan['tokens'], $required );
	}

	/**
	 * The plan of a query, computed once and stored in the query variables.
	 *
	 * @param WP_Query $query Query.
	 * @return array{stage: string, tokens: string[]}
	 */
	private static function plan( $query ) {
		if ( ! $query instanceof WP_Query ) {
			return array(
				'stage'  => 'native',
				'tokens' => array(),
			);
		}
		$plan = $query->get( self::VAR_PLAN );
		if ( is_array( $plan ) && isset( $plan['stage'] ) ) {
			return $plan;
		}

		$plan = array(
			'stage'  => 'native',
			'tokens' => array(),
		);
		$s    = $query->get( 's' );

		/**
		 * Filters whether the search index may be used for a query.
		 *
		 * @param bool     $enabled Whether the index may be used. Default true.
		 * @param WP_Query $query   The query.
		 */
		if ( is_string( $s ) && '' !== trim( $s ) && apply_filters( 'nh_seo_search_enabled', true, $query ) && NH_SEO_Search_State::is_active() && self::is_supported( $query, $s ) ) {
			$tokens = NH_SEO_Search_Text::query_tokens( $s );
			if ( ! empty( $tokens ) ) {
				$plan = array(
					'stage'  => 'strict',
					'tokens' => $tokens,
				);
			}
		}

		$query->set( self::VAR_PLAN, $plan );
		return $plan;
	}

	/**
	 * Whether the index can answer this query exactly like native search would, apart from relevance.
	 *
	 * @param WP_Query $query  Query.
	 * @param string   $search Search string.
	 * @return bool
	 */
	private static function is_supported( $query, $search ) {
		// Phrase, exact and custom column searches have semantics the index does not reproduce.
		if ( $query->get( 'sentence' ) || $query->get( 'exact' ) || $query->get( 'search_columns' ) ) {
			return false;
		}
		// "-word" excludes posts natively; the index would turn it into an inclusion.
		if ( 1 === preg_match( '/(?:^|\s)-\S/u', $search ) ) {
			return false;
		}
		// Password queries ask for posts the index never holds.
		if ( null !== $query->get( 'post_password', null ) || null !== $query->get( 'has_password', null ) ) {
			return false;
		}
		return self::statuses_are_public( $query ) && self::types_are_indexed( $query );
	}

	/**
	 * Whether the query can only return published posts.
	 *
	 * @param WP_Query $query Query.
	 * @return bool
	 */
	private static function statuses_are_public( $query ) {
		$status = $query->get( 'post_status' );
		if ( is_string( $status ) ) {
			$status = '' === trim( $status ) ? array() : array_map( 'trim', explode( ',', $status ) );
		}
		if ( ! is_array( $status ) ) {
			return false;
		}
		if ( empty( $status ) ) {
			// Default statuses: public posts on the front end, but drafts and more in the admin.
			return ! is_admin();
		}
		return array( 'publish' ) === array_values( array_unique( $status ) );
	}

	/**
	 * Whether every post type the query searches is indexed.
	 *
	 * @param WP_Query $query Query.
	 * @return bool
	 */
	private static function types_are_indexed( $query ) {
		$types = $query->get( 'post_type' );
		if ( empty( $types ) || 'any' === $types ) {
			$types = get_post_types( array( 'exclude_from_search' => false ) );
		}
		$types = array_diff( (array) $types, array( 'attachment' ) ); // Attachments are never "publish".
		if ( empty( $types ) ) {
			return false;
		}
		return array() === array_diff( $types, NH_SEO_Search_Index::indexable_types() );
	}

	/**
	 * Whether the relevance order applies: only when the caller did not choose another order.
	 *
	 * @param WP_Query $query Query.
	 * @return bool
	 */
	private static function wants_relevance( $query ) {
		$orderby = $query->get( 'orderby' );
		if ( empty( $orderby ) ) {
			return true;
		}
		return is_string( $orderby ) && 'relevance' === strtolower( trim( $orderby ) );
	}

	/**
	 * Decide which stage should answer a later page that came back empty, by asking the index directly.
	 *
	 * @param array $plan Plan.
	 * @return string strict | relaxed | native
	 */
	private static function stage_by_probe( array $plan ) {
		if ( self::probe( $plan, true ) ) {
			return 'strict';
		}
		if ( count( $plan['tokens'] ) > 1 && self::probe( $plan, false ) ) {
			return 'relaxed';
		}
		return 'native';
	}

	/**
	 * Whether the index has at least one row for the expression. A database error pauses the index.
	 *
	 * @param array $plan     Plan.
	 * @param bool  $required Strict (true) or relaxed (false).
	 * @return bool
	 */
	private static function probe( array $plan, $required ) {
		global $wpdb;
		$suppress = $wpdb->suppress_errors( true );
		// A bounded existence check on the plugin's own table; the expression holds folded tokens only.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM %i WHERE MATCH(title_fold, tax_fold, body_fold) AGAINST (%s IN BOOLEAN MODE) LIMIT 1', NH_SEO_Search_Index::table(), self::boolean( $plan, $required ) ) );
		$error = (string) $wpdb->last_error;
		$wpdb->suppress_errors( $suppress );

		if ( '' !== $error ) {
			NH_SEO_Search_State::trip( $error );
			NH_SEO_Search_State::bump_query_caches();
			return false;
		}
		return null !== $found;
	}
}
