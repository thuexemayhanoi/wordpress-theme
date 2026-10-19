<?php
/**
 * What each language shows in lists: the home page, categories, tags, search, related posts and previous/next links.
 *
 * Vietnamese lists leave the English posts out, English lists show the English posts that may be shown and nothing
 * else. Single posts are never touched here, and neither are the administration, the REST API, AJAX, cron, XML-RPC
 * and WP-CLI, so an editor still finds every post.
 *
 * @package Nguyen_Ha_VI_EN
 */

defined( 'ABSPATH' ) || exit;

/**
 * Per-language scoping of the front-end queries.
 */
final class NH_VIEN_Query {
	/** Register the query hooks when the English side is switched on. */
	public static function boot() {
		if ( ! NH_VIEN_Routes::enabled() ) {
			return;
		}
		add_action( 'pre_get_posts', array( __CLASS__, 'scope' ) );
		add_filter( 'get_previous_post_where', array( __CLASS__, 'adjacent_where' ), 10, 5 );
		add_filter( 'get_next_post_where', array( __CLASS__, 'adjacent_where' ), 10, 5 );
		add_action( 'wp', array( __CLASS__, 'send_empty_404' ), 1 );
		add_filter( 'get_terms', array( __CLASS__, 'filter_terms' ), 10, 3 );
		add_filter( 'rest_post_search_query', array( __CLASS__, 'rest_search_query' ), 10, 2 );
		add_filter( 'get_the_archive_title', array( __CLASS__, 'archive_title' ) );
		add_filter( 'document_title_parts', array( __CLASS__, 'document_title_parts' ) );
	}

	/**
	 * Restrict a list query to the posts of the language of the request.
	 *
	 * @param WP_Query $query The query about to run.
	 */
	public static function scope( $query ) {
		if ( ! $query instanceof WP_Query || ! self::applies( $query ) ) {
			return;
		}
		$english = NH_VIEN_Context::is_english();
		$scoped  = self::scoped_ids( $query->get( 'post__in' ), $query->get( 'post__not_in' ), $english, self::may_return_pages( $query ) );
		foreach ( $scoped as $key => $ids ) {
			if ( null !== $ids ) {
				$query->set( $key, $ids );
			}
		}
		if ( $english ) {
			self::flag_english_home( $query );
		}
	}

	/**
	 * The post__in and post__not_in of a list once the language is applied. WP_Query applies post__in and ignores
	 * post__not_in when both are given, so the exclusions of the caller (the post being read, the posts already listed) are
	 * taken out of the list here, and the language is never lost to a list that names its own posts.
	 *
	 * English lists keep the English posts that may be shown (and the English pages, when the query may return pages),
	 * Vietnamese lists leave every English post out (and the English pages, likewise).
	 *
	 * @param mixed $post_in     The post__in of the query.
	 * @param mixed $post_not_in The post__not_in of the query.
	 * @param bool  $english     Whether the list is an English one.
	 * @param bool  $with_pages  Whether the query may return pages.
	 * @return array{post__in:?int[],post__not_in:?int[]} The values to set; null means leave the query as it is.
	 */
	public static function scoped_ids( $post_in, $post_not_in, $english, $with_pages = false ) {
		$wanted   = self::ids( $post_in );
		$excluded = self::ids( $post_not_in );
		if ( $english ) {
			$allowed = NH_VIEN_Links::public_en_ids();
			if ( $with_pages ) {
				$allowed = array_merge( $allowed, NH_VIEN_Routes::english_page_ids() );
			}
			// A list that names posts, none of them a real ID (post__in = array( 0 ), what a theme uses for "nothing"), is empty.
			if ( self::names_posts( $post_in ) && ! $wanted ) {
				$ids = array();
			} else {
				$ids = $wanted ? array_intersect( $wanted, $allowed ) : $allowed;
			}
			$ids = array_values( array_diff( $ids, $excluded ) );
			return array(
				'post__in'     => $ids ? $ids : array( 0 ),
				'post__not_in' => null,
			);
		}
		$hidden = NH_VIEN_Links::en_ids();
		if ( $with_pages ) {
			$hidden = array_merge( $hidden, NH_VIEN_Routes::english_page_ids() );
		}
		if ( ! $hidden ) {
			return array(
				'post__in'     => null,
				'post__not_in' => null,
			);
		}
		if ( $wanted ) {
			$ids = array_values( array_diff( $wanted, $excluded, $hidden ) );
			return array(
				'post__in'     => $ids ? $ids : array( 0 ),
				'post__not_in' => null,
			);
		}
		return array(
			'post__in'     => null,
			'post__not_in' => array_values( array_unique( array_merge( $excluded, $hidden ) ) ),
		);
	}

	/**
	 * Whether a post__in names anything at all, even IDs that match no post: an empty value ('' is what a query that sets no
	 * post__in gives) names nothing.
	 *
	 * @param mixed $value What a query holds in post__in.
	 * @return bool
	 */
	private static function names_posts( $value ) {
		foreach ( (array) $value as $entry ) {
			if ( '' !== $entry && null !== $entry && false !== $entry ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * A list of IDs as WP_Query reads them (absolute integers), once each, without the zeros.
	 *
	 * @param mixed $value What a query holds in post__in or post__not_in: an array, or nothing.
	 * @return int[]
	 */
	private static function ids( $value ) {
		return array_values(
			array_unique(
				array_filter(
					array_map(
						static function ( $id ) {
							return abs( (int) $id );
						},
						(array) $value
					)
				)
			)
		);
	}

	/**
	 * Whether a query can return pages: a search, a query for any type, or one that names the page type.
	 *
	 * @param WP_Query $query The query about to run.
	 * @return bool
	 */
	private static function may_return_pages( $query ) {
		$types = $query->get( 'post_type' );
		if ( '' === $types || null === $types ) {
			return $query->is_search();
		}
		return in_array( 'page', (array) $types, true ) || in_array( 'any', (array) $types, true );
	}

	/**
	 * The suggestions of a search box: when the request says which language the page is in (NH_VIEN_Urls::rest_url()),
	 * they list the posts of that language only. A request without the marker is answered as before.
	 *
	 * @param array           $args    The arguments of the posts query.
	 * @param WP_REST_Request $request The request to the REST search route.
	 * @return array
	 */
	public static function rest_search_query( $args, $request ) {
		if ( ! is_array( $args ) || ! $request instanceof WP_REST_Request || ! NH_VIEN_Routes::live() ) {
			return $args;
		}
		$language = $request->get_param( NH_VIEN_Routes::LANG_VAR );
		if ( 'en' === $language || 'vi' === $language ) {
			$scoped = self::scoped_ids(
				isset( $args['post__in'] ) ? $args['post__in'] : array(),
				isset( $args['post__not_in'] ) ? $args['post__not_in'] : array(),
				'en' === $language,
				true
			);
			foreach ( $scoped as $key => $ids ) {
				if ( null !== $ids ) {
					$args[ $key ] = $ids;
				}
			}
		}
		return $args;
	}

	/**
	 * Whether the query is a front-end list of posts that has to be scoped.
	 *
	 * @param WP_Query $query The query about to run.
	 * @return bool
	 */
	private static function applies( $query ) {
		if ( ! NH_VIEN_Routes::live() || 'off' === $query->get( NH_VIEN_Routes::SCOPE_VAR ) || self::outside_the_front_end() ) {
			return false;
		}
		if ( $query->is_singular() || $query->is_attachment() ) {
			return false;
		}
		$types = $query->get( 'post_type' );
		if ( '' === $types || null === $types ) {
			return true;
		}
		$types = (array) $types;
		return in_array( 'post', $types, true ) || in_array( 'any', $types, true );
	}

	/**
	 * Whether the code runs for the administration or for a machine, where editors and integrations need every post.
	 *
	 * @return bool
	 */
	private static function outside_the_front_end() {
		return is_admin()
			|| wp_doing_ajax()
			|| wp_doing_cron()
			|| ( defined( 'REST_REQUEST' ) && REST_REQUEST )
			|| defined( 'XMLRPC_REQUEST' )
			|| ( defined( 'WP_CLI' ) && WP_CLI );
	}

	/**
	 * The list at /en/ is not the blog home of the Vietnamese site: it must not pick the front-page template or be read
	 * as the home page by an SEO plugin, so it is presented as an archive of its own.
	 *
	 * @param WP_Query $query The main query of an English request.
	 */
	private static function flag_english_home( $query ) {
		if ( $query->is_main_query() && $query->is_home() ) {
			$query->is_home    = false;
			$query->is_archive = true;
			$query->set( 'nh_vien_view', 'home' );
		}
	}

	/**
	 * Keep the previous and next links of a post inside its language.
	 *
	 * @param string       $where         The WHERE clause of the adjacent post query.
	 * @param bool         $in_same_term  Unused.
	 * @param array|string $excluded_terms Unused.
	 * @param string       $taxonomy      Unused.
	 * @param WP_Post|null $post          The post the links are for.
	 * @return string
	 */
	public static function adjacent_where( $where, $in_same_term = false, $excluded_terms = '', $taxonomy = 'category', $post = null ) {
		if ( ! NH_VIEN_Routes::live() || is_admin() || ! $post instanceof WP_Post || 'post' !== $post->post_type ) {
			return $where;
		}
		if ( NH_VIEN_Context::is_english() ) {
			$ids = NH_VIEN_Links::public_en_ids();
			return $where . ' AND p.ID IN (' . implode( ',', array_map( 'intval', $ids ? $ids : array( 0 ) ) ) . ')';
		}
		$english = NH_VIEN_Links::en_ids();
		return $english ? $where . ' AND p.ID NOT IN (' . implode( ',', array_map( 'intval', $english ) ) . ')' : $where;
	}

	/**
	 * Lists of categories and tags show the terms of the language of the page: English pages the terms that have
	 * English posts, Vietnamese pages every term except those whose posts are all English.
	 *
	 * @param array $terms      The terms found.
	 * @param array $taxonomies The taxonomies that were asked for.
	 * @param array $args       The arguments of the term query.
	 * @return array
	 */
	public static function filter_terms( $terms, $taxonomies = array(), $args = array() ) {
		if ( ! is_array( $terms ) || ! $terms || ! NH_VIEN_Routes::live() || self::outside_the_front_end() ) {
			return $terms;
		}
		// Yoast builds XML sitemaps with get_terms(). Do not hide English-only terms before its
		// wpseo_sitemap_entry callback can rewrite their URLs to the /en/ archives.
		global $wp_query;
		if ( defined( 'WPSEO_VERSION' ) && $wp_query instanceof WP_Query && $wp_query->get( 'sitemap' ) ) {
			return $terms;
		}
		if ( ! empty( $args['include'] ) || ( isset( $args[ NH_VIEN_Routes::SCOPE_VAR ] ) && 'off' === $args[ NH_VIEN_Routes::SCOPE_VAR ] ) ) {
			return $terms;
		}
		// English pages keep the terms that have English posts; Vietnamese pages drop the terms that have nothing else.
		$english = NH_VIEN_Context::is_english();
		$set     = $english ? NH_VIEN_Links::en_term_ids() : NH_VIEN_Links::en_only_term_ids();
		if ( ! $english && ! $set ) {
			return $terms;
		}
		$kept = array();
		foreach ( $terms as $term ) {
			if ( $term instanceof WP_Term && in_array( $term->taxonomy, array( 'category', 'post_tag' ), true ) ) {
				$in_set = isset( $set[ (int) $term->term_id ] );
				if ( $english ? ! $in_set : $in_set ) {
					continue;
				}
			}
			$kept[] = $term;
		}
		return count( $kept ) === count( $terms ) ? $terms : $kept;
	}

	/**
	 * Answer 404 for a list that has nothing to show because of the language: an English category, tag or home page
	 * without English posts, or a Vietnamese category or tag whose posts are all English. An empty page would be a
	 * soft 404. A search without results is not a missing page.
	 */
	public static function send_empty_404() {
		global $wp_query;
		if ( ! NH_VIEN_Routes::live() || ! $wp_query instanceof WP_Query || $wp_query->is_404() || $wp_query->have_posts() ) {
			return;
		}
		if ( NH_VIEN_Context::is_english() ) {
			if ( $wp_query->is_category() || $wp_query->is_tag() || 'home' === $wp_query->get( 'nh_vien_view' ) ) {
				self::not_found();
			}
			return;
		}
		$term = $wp_query->is_category() || $wp_query->is_tag() ? get_queried_object() : null;
		if ( $term instanceof WP_Term && self::has_posts_of_any_language( $term ) ) {
			self::not_found();
		}
	}

	/**
	 * Whether a term has published posts at all (those of its child categories included), counted without the
	 * language restriction. A Vietnamese archive that is empty although this is true is empty only because its posts
	 * are English.
	 *
	 * @param WP_Term $term The category or tag.
	 * @return bool
	 */
	private static function has_posts_of_any_language( $term ) {
		$found = get_posts(
			array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'posts_per_page'      => 1,
				'fields'              => 'ids',
				'orderby'             => 'none',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'suppress_filters'    => true,
				'nh_vien_scope'       => 'off',
				'tax_query'           => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- One indexed lookup, only for an archive that came back empty.
					array(
						'taxonomy'         => $term->taxonomy,
						'field'            => 'term_id',
						'terms'            => array( (int) $term->term_id ),
						'include_children' => true,
					),
				),
			)
		);
		return ! empty( $found );
	}

	/** Answer the current request with a 404 page, and keep core from redirecting a ?p= address to the post. */
	public static function not_found() {
		global $wp_query;
		if ( $wp_query instanceof WP_Query ) {
			$wp_query->set_404();
		}
		status_header( 404 );
		nocache_headers();
		add_filter( 'redirect_canonical', '__return_false' );
	}

	/**
	 * Name the list at /en/.
	 *
	 * @param string $title The archive title WordPress built.
	 * @return string
	 */
	public static function archive_title( $title ) {
		global $wp_query;
		if ( $wp_query instanceof WP_Query && 'home' === $wp_query->get( 'nh_vien_view' ) ) {
			return __( 'Latest articles', 'nguyen-ha-vi-en' );
		}
		return $title;
	}

	/**
	 * Give the list at /en/ a document title of its own when no SEO plugin does.
	 *
	 * @param array $parts The parts of the document title.
	 * @return array
	 */
	public static function document_title_parts( $parts ) {
		global $wp_query;
		if ( is_array( $parts ) && $wp_query instanceof WP_Query && 'home' === $wp_query->get( 'nh_vien_view' ) ) {
			$parts['title'] = __( 'Latest articles', 'nguyen-ha-vi-en' );
		}
		return $parts;
	}
}
