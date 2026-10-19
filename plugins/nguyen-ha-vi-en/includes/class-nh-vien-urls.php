<?php
/**
 * Addresses: where an English post, an English category and the English home page live, and what happens to a request
 * for the ordinary WordPress address of an English post.
 *
 * An English post that may be shown has one address, /en/{slug}/, and every link WordPress builds for it (theme
 * lists, canonical URL, sitemaps, feeds, the admin's "View post") uses it. A request for the ordinary address of that
 * post is redirected there with a 301, so the post exists once. Posts that may not be shown yet (their Vietnamese
 * partner is not published) answer 404 to visitors on every address.
 *
 * @package Nguyen_Ha_VI_EN
 */

defined( 'ABSPATH' ) || exit;

/**
 * Permalinks and redirects of the English side.
 */
final class NH_VIEN_Urls {
	/**
	 * Whether the page body is being built: between the end of the head and the footer.
	 *
	 * @var bool
	 */
	private static $body_phase = false;

	/** Register the address hooks when the English side is switched on. */
	public static function boot() {
		if ( ! NH_VIEN_Routes::enabled() ) {
			return;
		}
		add_filter( 'post_link', array( __CLASS__, 'post_link' ), 10, 3 );
		add_filter( 'term_link', array( __CLASS__, 'term_link' ), 10, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'guard_native_url' ), 1 );
		add_action( 'wp_head', array( __CLASS__, 'open_body_phase' ), 9999 );
		add_action( 'wp_footer', array( __CLASS__, 'close_body_phase' ), 0 );
		add_filter( 'home_url', array( __CLASS__, 'home_url' ), 10, 3 );
		add_filter( 'rest_url', array( __CLASS__, 'rest_url' ), 10, 2 );
	}

	/**
	 * The address of an English post that may be shown is /en/{slug}/.
	 *
	 * @param string  $permalink The address WordPress built.
	 * @param WP_Post $post      The post.
	 * @param bool    $leavename Whether the slug is left as a placeholder (the editor's permalink box).
	 * @return string
	 */
	public static function post_link( $permalink, $post, $leavename = false ) {
		if ( $leavename || ! $post instanceof WP_Post || ! NH_VIEN_Routes::live() || ! NH_VIEN_Links::is_public_en( $post->ID ) ) {
			return $permalink;
		}
		$url = NH_VIEN_Routes::english_url( $post->ID );
		return '' !== $url ? $url : $permalink;
	}

	/**
	 * Inside English pages a category or tag that has English posts links to its English archive.
	 *
	 * @param string  $url  The address WordPress built.
	 * @param WP_Term $term The term.
	 * @return string
	 */
	public static function term_link( $url, $term ) {
		if ( ! NH_VIEN_Context::is_english() || ! $term instanceof WP_Term || ! NH_VIEN_Routes::live() ) {
			return $url;
		}
		$english = self::english_term_url( $term );
		return '' !== $english ? $english : $url;
	}

	/**
	 * The English archive address of a category or tag that has English posts.
	 *
	 * @param WP_Term $term The term.
	 * @return string The /en/ address, or an empty string when the term has no English archive.
	 */
	public static function english_term_url( $term ) {
		$bases = array(
			'category' => 'category',
			'post_tag' => 'tag',
		);
		if ( ! $term instanceof WP_Term || ! isset( $bases[ $term->taxonomy ] ) || ! isset( NH_VIEN_Links::en_term_ids()[ (int) $term->term_id ] ) ) {
			return '';
		}
		$slugs = array();
		if ( is_taxonomy_hierarchical( $term->taxonomy ) ) {
			foreach ( array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) ) as $ancestor_id ) {
				$ancestor = get_term( $ancestor_id, $term->taxonomy );
				if ( $ancestor instanceof WP_Term ) {
					$slugs[] = $ancestor->slug;
				}
			}
		}
		$slugs[] = $term->slug;
		return NH_VIEN_Routes::url( $bases[ $term->taxonomy ] . '/' . implode( '/', $slugs ) );
	}

	/**
	 * A request for the ordinary address of an English post: redirect to the English address, or answer 404 when the post
	 * may not be shown yet. Editors and previews are left alone.
	 */
	public static function guard_native_url() {
		if ( ! NH_VIEN_Routes::live() || NH_VIEN_Context::is_english() || ! is_singular( 'post' ) || is_preview() ) {
			return;
		}
		$id = (int) get_queried_object_id();
		if ( ! NH_VIEN_Links::is_en( $id ) ) {
			return;
		}
		$target = NH_VIEN_Routes::english_url( $id );
		if ( '' !== $target ) {
			wp_safe_redirect( self::with_request_arguments( $target ), 301 );
			exit;
		}
		if ( ! current_user_can( 'edit_post', $id ) ) {
			NH_VIEN_Query::not_found();
		}
	}

	/**
	 * The Vietnamese home address, whatever language the page is in.
	 *
	 * @return string
	 */
	public static function vi_home() {
		return set_url_scheme( trailingslashit( (string) get_option( 'home' ) ) );
	}

	/**
	 * The English home address, or an empty string when the site has no English home page and no English post to list.
	 *
	 * @return string
	 */
	public static function en_home() {
		if ( ! NH_VIEN_Routes::live() ) {
			return '';
		}
		return NH_VIEN_Routes::home_page() || NH_VIEN_Links::public_en_ids() ? NH_VIEN_Routes::url() : '';
	}

	/**
	 * The address of the page being answered, without the query string.
	 *
	 * @return string
	 */
	public static function current_url() {
		global $wp;
		$request = isset( $wp->request ) ? trim( (string) $wp->request, '/' ) : '';
		return '' === $request ? self::vi_home() : user_trailingslashit( home_url( '/' . $request ) );
	}

	/**
	 * The English address with the arguments of the request that are not WordPress query variables (campaign tags and
	 * the like). Query variables such as p or name only said which post was meant.
	 *
	 * @param string $target The English address.
	 * @return string
	 */
	private static function with_request_arguments( $target ) {
		global $wp;
		$raw = isset( $_SERVER['QUERY_STRING'] ) ? wp_unslash( $_SERVER['QUERY_STRING'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Parsed into arguments and encoded again below; it only decides which arguments are carried over.
		if ( ! is_string( $raw ) || '' === $raw ) {
			return $target;
		}
		wp_parse_str( $raw, $arguments );
		$arguments = array_diff_key( $arguments, array_flip( (array) $wp->public_query_vars ) );
		return $arguments ? esc_url_raw( add_query_arg( urlencode_deep( $arguments ), $target ) ) : $target;
	}

	/** The head is done: from here on the home address of an English page is the English home. */
	public static function open_body_phase() {
		self::$body_phase = true;
	}

	/** The footer starts: the home address is the plain one again. */
	public static function close_body_phase() {
		self::$body_phase = false;
	}

	/**
	 * Inside the body of an English page the home link (the logo, the search form, "Return home") is the English home.
	 * It is deliberately not changed in the head, where SEO plugins build the identifiers of the site and of its
	 * organisation, which must stay the same in both languages.
	 *
	 * @param string      $url         The home address.
	 * @param string      $path        The path that was asked for.
	 * @param string|null $orig_scheme The scheme that was asked for.
	 * @return string
	 */
	public static function home_url( $url, $path = '', $orig_scheme = null ) {
		if ( self::$body_phase && '/' === $path && null === $orig_scheme && NH_VIEN_Context::is_english() && NH_VIEN_Routes::live() ) {
			return NH_VIEN_Routes::url();
		}
		return $url;
	}

	/**
	 * The suggestions under a search box come from the REST search route, which knows nothing about the page that asks.
	 * Inside the body of a page, the address of that route says which language the page is in, so that a Vietnamese page
	 * is not offered English articles and the other way round (see NH_VIEN_Query::rest_search_query()). Without English
	 * posts there is nothing to tell apart and the address is left as it is.
	 *
	 * @param string $url  The REST address.
	 * @param string $path The route that was asked for.
	 * @return string
	 */
	public static function rest_url( $url, $path = '' ) {
		if ( self::$body_phase && 'wp/v2/search' === trim( (string) $path, '/' ) && NH_VIEN_Routes::live() && NH_VIEN_Links::en_ids() ) {
			return add_query_arg( NH_VIEN_Routes::LANG_VAR, NH_VIEN_Context::language(), $url );
		}
		return $url;
	}
}
