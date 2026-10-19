<?php
/**
 * Search engine signals of the two languages: hreflang between the posts of a pair, one canonical address per page,
 * and the English addresses in the sitemap, the breadcrumbs and the structured data of Yoast SEO.
 *
 * Yoast SEO stays in charge: this class uses its documented filters and never prints a second canonical, title or
 * sitemap. Without an SEO plugin it prints the two tags WordPress does not print itself (hreflang, and the canonical
 * address of English lists).
 *
 * @package Nguyen_Ha_VI_EN
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hreflang, canonical addresses and Yoast SEO integration.
 */
final class NH_VIEN_Seo {
	/**
	 * The English archives of the terms that Yoast SEO put into the sitemap being built, by taxonomy: the terms that both
	 * languages use get their English archive as an extra entry after the Vietnamese ones.
	 *
	 * @var array<string,array<string,bool>>
	 */
	private static $english_entries = array();

	/** Register the SEO hooks when the English side is switched on. */
	public static function boot() {
		if ( ! NH_VIEN_Routes::enabled() ) {
			return;
		}
		add_action( 'wp_head', array( __CLASS__, 'print_hreflang' ), 2 );
		add_action( 'wp_head', array( __CLASS__, 'print_canonical' ), 2 );
		// Yoast SEO. The filters are harmless when it is not active.
		add_filter( 'wpseo_dynamic_permalinks_enabled', array( __CLASS__, 'dynamic_permalinks' ) );
		add_filter( 'wpseo_canonical', array( __CLASS__, 'canonical' ), 10, 2 );
		add_filter( 'wpseo_opengraph_url', array( __CLASS__, 'canonical' ), 10, 2 );
		add_filter( 'wpseo_title', array( __CLASS__, 'title' ) );
		add_filter( 'wpseo_breadcrumb_links', array( __CLASS__, 'breadcrumbs' ) );
		add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', array( __CLASS__, 'exclude_posts' ) );
		add_filter( 'wpseo_sitemap_entry', array( __CLASS__, 'sitemap_entry' ), 10, 3 );
		add_filter( 'wpseo_sitemap_post_content', array( __CLASS__, 'sitemap_home' ) );
		add_filter( 'wpseo_sitemap_category_content', array( __CLASS__, 'sitemap_categories' ) );
		add_filter( 'wpseo_sitemap_post_tag_content', array( __CLASS__, 'sitemap_tags' ) );
		// The sitemap WordPress builds itself, used when no SEO plugin provides one.
		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'core_sitemap_args' ), 10, 2 );
		add_filter( 'wp_sitemaps_taxonomies_query_args', array( __CLASS__, 'core_sitemap_term_args' ), 10, 2 );
	}

	/**
	 * Whether an SEO plugin that prints canonical addresses is active.
	 *
	 * @return bool
	 */
	public static function seo_plugin_active() {
		$active = defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || defined( 'THE_SEO_FRAMEWORK_VERSION' );

		/**
		 * Filters whether an SEO plugin takes care of the canonical addresses of English lists.
		 *
		 * @param bool $active Whether a known SEO plugin is active.
		 */
		return (bool) apply_filters( 'nh_vien_seo_plugin_active', $active );
	}

	/**
	 * The two addresses of the page being answered when it has a counterpart in the other language: the posts of a
	 * pair, and the two home pages. Any other page has none, so it gets no hreflang.
	 *
	 * @return array{vi: string, en: string}|null
	 */
	public static function counterparts() {
		if ( ! NH_VIEN_Routes::live() || is_404() ) {
			return null;
		}
		$english = NH_VIEN_Context::is_english();
		if ( is_singular( 'page' ) ) {
			$pair = NH_VIEN_Links::for_page( (int) get_queried_object_id() );
			if ( $pair && 'publish' === get_post_status( $pair['vi'] ) && 'publish' === get_post_status( $pair['en'] ) ) {
				$english_page = get_page_uri( $pair['en'] );
				$vi_url       = (string) get_permalink( $pair['vi'] );
				$en_url       = (string) get_permalink( $pair['en'] );
				if ( 0 === strpos( (string) $english_page, 'en/' ) && $vi_url && $en_url ) {
					return array(
						'vi' => $vi_url,
						'en' => $en_url,
					);
				}
			}
		}
		if ( is_singular( 'post' ) ) {
			$id      = (int) get_queried_object_id();
			$en_id   = $english ? $id : NH_VIEN_Links::en_for_vi( $id );
			$vi_id   = $english ? NH_VIEN_Links::vi_for_en( $id ) : $id;
			$en      = $en_id ? NH_VIEN_Routes::english_url( $en_id ) : '';
			$vi      = $vi_id ? (string) get_permalink( $vi_id ) : '';
			$counted = '' !== $en && '' !== $vi && ( $english ? NH_VIEN_Links::is_public_en( $id ) : true );
			return $counted ? array(
				'vi' => $vi,
				'en' => $en,
			) : null;
		}
		$en_home = NH_VIEN_Urls::en_home();
		if ( '' === $en_home ) {
			return null;
		}
		$home = $english ? self::is_english_home() : ( is_front_page() && ! is_paged() );
		return $home ? array(
			'vi' => NH_VIEN_Urls::vi_home(),
			'en' => $en_home,
		) : null;
	}

	/**
	 * Whether the page being answered is the first page of the English home: the real page at /en/ or the list.
	 *
	 * @return bool
	 */
	private static function is_english_home() {
		global $wp_query;
		if ( ! NH_VIEN_Context::is_english() || ! $wp_query instanceof WP_Query || is_paged() ) {
			return false;
		}
		$page = NH_VIEN_Routes::home_page();
		if ( $page && is_page() ) {
			return (int) get_queried_object_id() === (int) $page->ID;
		}
		return 'home' === $wp_query->get( 'nh_vien_view' );
	}

	/** Print hreflang for the two versions of a page that has both. */
	public static function print_hreflang() {
		$pair = self::counterparts();
		if ( ! $pair ) {
			return;
		}
		$tags = array(
			'vi'        => $pair['vi'],
			'en'        => $pair['en'],
			'x-default' => $pair['vi'],
		);
		foreach ( $tags as $code => $url ) {
			echo '<link rel="alternate" hreflang="' . esc_attr( $code ) . '" href="' . esc_url( $url ) . '" />' . "\n";
		}
	}

	/** Without an SEO plugin, print the canonical address of the English lists, which WordPress does not print. */
	public static function print_canonical() {
		if ( ! NH_VIEN_Routes::live() || ! NH_VIEN_Context::is_english() || self::seo_plugin_active() || ! ( is_archive() || is_home() ) || is_search() ) {
			return;
		}
		echo '<link rel="canonical" href="' . esc_url( NH_VIEN_Urls::current_url() ) . '" />' . "\n";
	}

	/**
	 * Yoast SEO builds its canonical address, Open Graph address and structured data from the address it stored for
	 * the indexable. Inside English pages, ask WordPress for the address instead, which is the English one.
	 *
	 * @param bool $enabled Whether Yoast SEO already works that way.
	 * @return bool
	 */
	public static function dynamic_permalinks( $enabled ) {
		return $enabled || ( NH_VIEN_Context::is_english() && NH_VIEN_Routes::live() );
	}

	/**
	 * The canonical and Open Graph address of English pages. A canonical set by hand in Yoast SEO is left alone.
	 *
	 * @param string $canonical    The address Yoast SEO built.
	 * @param object $presentation The Yoast SEO presentation of the page.
	 * @return string
	 */
	public static function canonical( $canonical, $presentation = null ) {
		global $wp_query;
		if ( ! NH_VIEN_Routes::live() || ! NH_VIEN_Context::is_english() || ! $wp_query instanceof WP_Query || is_search() ) {
			return $canonical;
		}
		if ( 'home' === $wp_query->get( 'nh_vien_view' ) ) {
			return NH_VIEN_Urls::current_url();
		}
		if ( is_singular( 'post' ) && NH_VIEN_Links::is_public_en( get_queried_object_id() ) && '' === self::custom_canonical( $presentation ) ) {
			return (string) get_permalink( get_queried_object_id() );
		}
		return $canonical;
	}

	/**
	 * The canonical address an editor typed in Yoast SEO for the page, if any.
	 *
	 * @param object|null $presentation The Yoast SEO presentation of the page.
	 * @return string
	 */
	private static function custom_canonical( $presentation ) {
		try {
			$custom = is_object( $presentation ) && is_object( $presentation->model ) ? $presentation->model->canonical : '';
		} catch ( Throwable $error ) {
			$custom = '';
		}
		return is_string( $custom ) ? $custom : '';
	}

	/**
	 * The title of the English list at /en/, which no indexable describes.
	 *
	 * @param string $title The title Yoast SEO built.
	 * @return string
	 */
	public static function title( $title ) {
		global $wp_query;
		if ( NH_VIEN_Routes::live() && NH_VIEN_Context::is_english() && $wp_query instanceof WP_Query && 'home' === $wp_query->get( 'nh_vien_view' ) ) {
			return __( 'Latest articles', 'nguyen-ha-vi-en' ) . ' - ' . get_bloginfo( 'name' );
		}
		return $title;
	}

	/**
	 * Inside English pages the breadcrumb of Yoast SEO links to English addresses and starts at the English home. This
	 * is the list Yoast SEO also writes into its BreadcrumbList, so the page and the structured data stay the same.
	 *
	 * @param array $crumbs The crumbs Yoast SEO built.
	 * @return array
	 */
	public static function breadcrumbs( $crumbs ) {
		if ( ! is_array( $crumbs ) || ! NH_VIEN_Routes::live() || ! NH_VIEN_Context::is_english() ) {
			return $crumbs;
		}
		foreach ( $crumbs as $index => $crumb ) {
			if ( ! is_array( $crumb ) ) {
				continue;
			}
			if ( isset( $crumb['term_id'], $crumb['taxonomy'] ) ) {
				$link = get_term_link( (int) $crumb['term_id'], (string) $crumb['taxonomy'] );
				if ( ! is_wp_error( $link ) ) {
					$crumbs[ $index ]['url'] = $link;
				}
			} elseif ( isset( $crumb['id'] ) ) {
				$link = get_permalink( (int) $crumb['id'] );
				if ( $link ) {
					$crumbs[ $index ]['url'] = $link;
				}
			} elseif ( 0 === $index ) {
				$crumbs[ $index ]['url']  = NH_VIEN_Routes::url();
				$crumbs[ $index ]['text'] = __( 'Home', 'nguyen-ha-vi-en' );
			}
		}
		return $crumbs;
	}

	/**
	 * Leave the English posts that may not be shown yet out of the sitemap.
	 *
	 * @param array $ids Post IDs excluded so far.
	 * @return array
	 */
	public static function exclude_posts( $ids ) {
		if ( ! NH_VIEN_Routes::live() ) {
			return $ids;
		}
		return array_merge( (array) $ids, array_diff( NH_VIEN_Links::en_ids(), NH_VIEN_Links::public_en_ids() ) );
	}

	/**
	 * Yoast SEO lists every category and tag with its address without /en/. A term that only English posts use has no
	 * Vietnamese archive (that address answers 404): its entry gets the English address instead, so that the sitemap is
	 * never empty because of the language and never lists a missing page. A term that both languages use keeps its
	 * entry, and its English archive is added after the others (see sitemap_terms()). Yoast has already left out the
	 * terms it keeps out of the search results.
	 *
	 * @param array|false $url    The sitemap entry.
	 * @param string      $type   What the entry is for: post, term or user.
	 * @param mixed       $term   The post, term or user.
	 * @return array|false
	 */
	public static function sitemap_entry( $url, $type, $term = null ) {
		if ( 'term' !== $type || ! is_array( $url ) || ! $term instanceof WP_Term || ! NH_VIEN_Routes::live() ) {
			return $url;
		}
		$english = NH_VIEN_Urls::english_term_url( $term );
		if ( isset( NH_VIEN_Links::en_only_term_ids()[ (int) $term->term_id ] ) ) {
			if ( '' === $english ) {
				// Only English posts that may not be shown yet use the term: it has no archive in either language.
				return array();
			}
			$url['loc'] = $english;
			return $url;
		}
		if ( '' !== $english ) {
			self::$english_entries[ $term->taxonomy ][ $english ] = true;
		}
		return $url;
	}

	/**
	 * In the sitemap of WordPress the posts are listed with the English posts that may be shown and without those that
	 * may not; the language restriction of lists does not apply to a sitemap.
	 *
	 * @param array  $args      The arguments of the posts query.
	 * @param string $post_type The post type of the sitemap.
	 * @return array
	 */
	public static function core_sitemap_args( $args, $post_type ) {
		if ( 'post' !== $post_type || ! is_array( $args ) || ! NH_VIEN_Routes::live() ) {
			return $args;
		}
		$args[ NH_VIEN_Routes::SCOPE_VAR ] = 'off';
		$args['post__not_in']              = array_merge(
			isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array(),
			array_diff( NH_VIEN_Links::en_ids(), NH_VIEN_Links::public_en_ids() )
		);
		return $args;
	}

	/**
	 * In the sitemap of categories and tags of WordPress, leave out the terms that only English posts fill: their address
	 * without /en/ answers 404. The sitemap of WordPress does not list the English archives; without an SEO plugin they
	 * are found through the links of the pages.
	 *
	 * @param array  $args     The arguments of the terms query.
	 * @param string $taxonomy The taxonomy of the sitemap.
	 * @return array
	 */
	public static function core_sitemap_term_args( $args, $taxonomy ) {
		if ( ! is_array( $args ) || ! in_array( $taxonomy, array( 'category', 'post_tag' ), true ) || ! NH_VIEN_Routes::live() ) {
			return $args;
		}
		$only = array_keys( NH_VIEN_Links::en_only_term_ids() );
		if ( $only ) {
			$excluded        = isset( $args['exclude'] ) ? array_map( 'intval', (array) $args['exclude'] ) : array();
			$args['exclude'] = array_values( array_unique( array_merge( $excluded, $only ) ) );
		}
		return $args;
	}

	/**
	 * Add the English home to the sitemap of posts when it is the list at /en/ (a real page at /en/ is in the sitemap
	 * of pages like any other page).
	 *
	 * @param string $content Extra sitemap entries so far.
	 * @return string
	 */
	public static function sitemap_home( $content ) {
		if ( ! NH_VIEN_Routes::live() || NH_VIEN_Routes::home_page() || ! NH_VIEN_Links::public_en_ids() ) {
			return $content;
		}
		return $content . self::sitemap_entry_xml( NH_VIEN_Routes::url() );
	}

	/**
	 * Add the English category archives to the sitemap of categories.
	 *
	 * @param string $content Extra sitemap entries so far.
	 * @return string
	 */
	public static function sitemap_categories( $content ) {
		return $content . self::sitemap_terms( 'category' );
	}

	/**
	 * Add the English tag archives to the sitemap of tags.
	 *
	 * @param string $content Extra sitemap entries so far.
	 * @return string
	 */
	public static function sitemap_tags( $content ) {
		return $content . self::sitemap_terms( 'post_tag' );
	}

	/**
	 * Sitemap entries for the English archives of the terms that both languages use, collected while Yoast SEO built the
	 * entries of the sitemap that is being finished.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return string
	 */
	private static function sitemap_terms( $taxonomy ) {
		if ( ! NH_VIEN_Routes::live() || empty( self::$english_entries[ $taxonomy ] ) ) {
			return '';
		}
		$xml = '';
		foreach ( array_keys( self::$english_entries[ $taxonomy ] ) as $url ) {
			$xml .= self::sitemap_entry_xml( $url );
		}
		unset( self::$english_entries[ $taxonomy ] );
		return $xml;
	}

	/**
	 * One sitemap entry.
	 *
	 * @param string $url Absolute address.
	 * @return string
	 */
	private static function sitemap_entry_xml( $url ) {
		return "\t<url>\n\t\t<loc>" . esc_url( $url ) . "</loc>\n\t</url>\n";
	}
}
