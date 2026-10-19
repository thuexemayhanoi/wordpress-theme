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
		if ( NH_VIEN_Context::is_english() ) {
			$allowed = NH_VIEN_Links::public_en_ids();
			$wanted  = array_filter( array_map( 'intval', (array) $query->get( 'post__in' ) ) );
			$ids     = $wanted ? array_values( array_intersect( $wanted, $allowed ) ) : $allowed;
			$query->set( 'post__in', $ids ? $ids : array( 0 ) );
			self::flag_english_home( $query );
			return;
		}
		$english = NH_VIEN_Links::en_ids();
		if ( $english ) {
			$excluded = array_map( 'intval', array_filter( (array) $query->get( 'post__not_in' ) ) );
			$query->set( 'post__not_in', array_values( array_unique( array_merge( $excluded, $english ) ) ) );
		}
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
		if ( 'en' === $language ) {
			$ids              = NH_VIEN_Links::public_en_ids();
			$args['post__in'] = $ids ? $ids : array( 0 );
		} elseif ( 'vi' === $language ) {
			$english = NH_VIEN_Links::en_ids();
			if ( $english ) {
				$excluded             = isset( $args['post__not_in'] ) ? array_map( 'intval', (array) $args['post__not_in'] ) : array();
				$args['post__not_in'] = array_values( array_unique( array_merge( $excluded, $english ) ) );
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
