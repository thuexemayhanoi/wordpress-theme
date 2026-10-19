<?php
/**
 * English routes: the /en/ prefix, the rewrite rules behind it, and how a request for one of them is resolved.
 *
 * Nothing here runs unless the site opts in with NH_VIEN_ENABLE_EN_ROUTES (see enabled()), no rewrite rule is stored
 * or removed without an administrator pressing a button, and no existing post, page or URL is changed.
 *
 * @package Nguyen_Ha_VI_EN
 */

defined( 'ABSPATH' ) || exit;

/**
 * Gate, rules and request resolution of the English side of the site.
 */
final class NH_VIEN_Routes {
	const REWRITE   = '^en/([^/]+)/?$';
	const MULTIPAGE = '^en/([^/]+)/([0-9]{1,})/?$';
	const HOME      = '^en/?$';
	const HOME_PAGE = '^en/page/([0-9]{1,})/?$';
	const QUERY_VAR = 'nh_vien_en_slug';
	const LANG_VAR  = 'nh_vien_lang';
	const SCOPE_VAR = 'nh_vien_scope';

	/**
	 * Memo of live() for this request.
	 *
	 * @var bool|null
	 */
	private static $live = null;

	/**
	 * Memo of the English home page for this request; false while it has not been looked up.
	 *
	 * @var WP_Post|null|false
	 */
	private static $home_page = false;

	/**
	 * Gate: the English routes are switched on by configuration. The constant NH_VIEN_ENABLE_EN_ROUTES must be true,
	 * and the environment must be local, development or staging. A production site needs a second, explicit
	 * constant (NH_VIEN_ALLOW_PRODUCTION) that only its owner sets, after the staging checks.
	 *
	 * @return bool
	 */
	public static function enabled() {
		if ( ! defined( 'NH_VIEN_ENABLE_EN_ROUTES' ) || true !== NH_VIEN_ENABLE_EN_ROUTES || ! function_exists( 'wp_get_environment_type' ) ) {
			return false;
		}
		if ( in_array( wp_get_environment_type(), array( 'local', 'development', 'staging' ), true ) ) {
			return true;
		}
		return defined( 'NH_VIEN_ALLOW_PRODUCTION' ) && true === NH_VIEN_ALLOW_PRODUCTION;
	}

	/**
	 * Every rewrite rule of the English side, in the order they are registered: the specific ones first.
	 *
	 * @return array<string,string> Regular expression => query.
	 */
	public static function rules() {
		$lang = 'index.php?' . self::LANG_VAR . '=en';
		return array(
			self::HOME                                => $lang,
			self::HOME_PAGE                           => $lang . '&paged=$matches[1]',
			'^en/category/(.+?)/page/?([0-9]{1,})/?$' => $lang . '&category_name=$matches[1]&paged=$matches[2]',
			'^en/category/(.+?)/?$'                   => $lang . '&category_name=$matches[1]',
			'^en/tag/([^/]+)/page/?([0-9]{1,})/?$'    => $lang . '&tag=$matches[1]&paged=$matches[2]',
			'^en/tag/([^/]+)/?$'                      => $lang . '&tag=$matches[1]',
			self::MULTIPAGE                           => $lang . '&' . self::QUERY_VAR . '=$matches[1]&page=$matches[2]',
			self::REWRITE                             => $lang . '&' . self::QUERY_VAR . '=$matches[1]',
		);
	}

	/**
	 * Slugs that belong to the routes above, so that an English post cannot use them.
	 *
	 * @return string[]
	 */
	public static function reserved_slugs() {
		return array( 'page', 'category', 'tag', 'author', 'search', 'feed', 'embed', 'comments', 'attachment', 'trackback', 'wp-json', 'wp-admin', 'wp-content', 'wp-includes' );
	}

	/**
	 * Whether an English post with this slug can be reached at /en/{slug}/.
	 *
	 * @param mixed $slug Post slug.
	 * @return bool
	 */
	public static function slug_is_routable( $slug ) {
		return self::valid_slug( $slug ) && ! in_array( $slug, self::reserved_slugs(), true );
	}

	/**
	 * Forget what this request has looked up (a rewrite flush or a changed page changes the answers).
	 */
	public static function reset() {
		self::$live      = null;
		self::$home_page = false;
	}

	/**
	 * Whether every rule of the English side is in the rewrite rules WordPress has stored.
	 *
	 * @return bool
	 */
	public static function rules_stored() {
		$stored = get_option( 'rewrite_rules' );
		if ( ! is_array( $stored ) ) {
			return false;
		}
		foreach ( array_keys( self::rules() ) as $regex ) {
			if ( ! isset( $stored[ $regex ] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Live: the gate is open and the rules are stored. Only then does the plugin change a URL, a query or a page; until an
	 * administrator has refreshed the rules, the site behaves as if the plugin were not there.
	 *
	 * @return bool
	 */
	public static function live() {
		if ( null === self::$live ) {
			self::$live = self::enabled() && self::rules_stored();
		}
		return self::$live;
	}

	/**
	 * Register the hooks of the English side when the gate is open. Called on every request, the administration
	 * included, because the rules must be registered in the request that stores them (Settings > Permalinks).
	 */
	public static function boot() {
		if ( ! self::enabled() ) {
			// Rules stored by an earlier run must not answer with the Vietnamese home page.
			if ( self::stale_rule_stored() ) {
				add_action( 'parse_request', array( __CLASS__, 'parse_request' ) );
			}
			return;
		}
		add_action( 'init', array( __CLASS__, 'register' ), 20 );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'parse_request', array( __CLASS__, 'parse_request' ) );
	}

	/**
	 * Whether the post rule of an earlier run is still in the stored rewrite rules.
	 *
	 * @return bool
	 */
	private static function stale_rule_stored() {
		$stored = get_option( 'rewrite_rules' );
		return is_array( $stored ) && isset( $stored[ self::REWRITE ] );
	}

	/** Register the rules, ahead of the rules WordPress generates. */
	public static function register() {
		foreach ( self::rules() as $regex => $query ) {
			add_rewrite_rule( $regex, $query, 'top' );
		}
	}

	/**
	 * Expose the route query keys to WordPress request parsing.
	 *
	 * @param array $vars Existing public WordPress query vars.
	 * @return array Expanded query vars.
	 */
	public static function query_vars( $vars ) {
		$vars[] = self::LANG_VAR;
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Resolve the request when one of the rules matched; make sure no raw query variable can pose as a route.
	 *
	 * @param WP $wp WordPress request containing matched rule and query vars.
	 */
	public static function parse_request( $wp ) {
		$rules = self::rules();
		if ( ! isset( $wp->matched_rule ) || ! isset( $rules[ $wp->matched_rule ] ) ) {
			unset( $wp->query_vars[ self::LANG_VAR ], $wp->query_vars[ self::QUERY_VAR ] );
			return;
		}
		if ( ! self::enabled() ) {
			$wp->query_vars = array( 'error' => '404' );
			return;
		}
		NH_VIEN_Context::set( 'en' );
		if ( self::REWRITE === $wp->matched_rule || self::MULTIPAGE === $wp->matched_rule ) {
			self::resolve_post( $wp );
		} elseif ( self::HOME === $wp->matched_rule || self::HOME_PAGE === $wp->matched_rule ) {
			self::resolve_home( $wp );
		}
	}

	/**
	 * Resolve /en/{slug}/ to a page below /en/ when there is one, else to the English post that has the slug.
	 *
	 * @param WP $wp WordPress request.
	 */
	private static function resolve_post( $wp ) {
		$slug = isset( $wp->query_vars[ self::QUERY_VAR ] ) ? $wp->query_vars[ self::QUERY_VAR ] : '';
		if ( ! self::slug_is_routable( $slug ) ) {
			$wp->query_vars = array( 'error' => '404' );
			return;
		}
		$vars = array( self::LANG_VAR => 'en' );
		if ( isset( $wp->query_vars['page'] ) ) {
			$vars['page'] = $wp->query_vars['page'];
		}
		// A page that already lives below /en/ keeps its address.
		$page = self::page_at( 'en/' . $slug );
		if ( $page instanceof WP_Post ) {
			$wp->query_vars = $vars + array( 'page_id' => (int) $page->ID );
			return;
		}
		$id = self::post_id_for_slug( $slug );
		if ( ! $id ) {
			$wp->query_vars = array( 'error' => '404' );
			return;
		}
		$wp->query_vars = $vars + array(
			'p'             => $id,
			self::QUERY_VAR => $slug,
		);
	}

	/**
	 * Resolve /en/ (and /en/page/N/, and a search below it): the English home page when the site has one, else a list
	 * of the English posts.
	 *
	 * @param WP $wp WordPress request.
	 */
	private static function resolve_home( $wp ) {
		$searching = isset( $wp->query_vars['s'] );
		$page      = self::home_page();
		if ( $page && ! $searching ) {
			// A real page has no pagination of its own: /en/page/2/ would be a second copy of it.
			$wp->query_vars = isset( $wp->query_vars['paged'] )
				? array( 'error' => '404' )
				: array(
					self::LANG_VAR => 'en',
					'page_id'      => (int) $page->ID,
				);
			return;
		}
		if ( ! $searching && ! NH_VIEN_Links::public_en_ids() ) {
			$wp->query_vars = array( 'error' => '404' );
		}
	}

	/**
	 * The published page whose address is /en/, when the site has one: it is the English home page.
	 *
	 * @return WP_Post|null
	 */
	public static function home_page() {
		if ( false === self::$home_page ) {
			self::$home_page = self::page_at( 'en' );
		}
		return self::$home_page;
	}

	/**
	 * The published page at a path, if there is one.
	 *
	 * @param string $path Page path, for instance en/about.
	 * @return WP_Post|null
	 */
	public static function page_at( $path ) {
		$page = get_page_by_path( $path, OBJECT, 'page' );
		return $page instanceof WP_Post && 'publish' === $page->post_status ? $page : null;
	}

	/**
	 * Resolve a unique published and paired English post.
	 *
	 * @param string $slug English post slug.
	 * @return int Verified EN post identifier or zero.
	 */
	public static function post_id_for_slug( $slug ) {
		if ( ! self::slug_is_routable( $slug ) ) {
			return 0;
		}
		$found = 0;
		foreach ( NH_VIEN_Links::public_en_posts() as $id => $post ) {
			if ( $post->post_name === $slug ) {
				if ( $found ) {
					return 0;
				}
				$found = (int) $id;
			}
		}
		return $found;
	}

	/**
	 * The /en/ address of a path, with the trailing slash the site's permalink structure asks for.
	 *
	 * @param string $path Path below /en/, without a leading slash; empty for the English home.
	 * @return string
	 */
	public static function url( $path = '' ) {
		return user_trailingslashit( home_url( '/en/' . trim( $path, '/' ) ) );
	}

	/**
	 * The address of a public English post.
	 *
	 * @param int $post_id English post ID.
	 * @return string The /en/{slug}/ address, or an empty string when the post cannot be reached there.
	 */
	public static function english_url( $post_id ) {
		if ( ! self::live() ) {
			return '';
		}
		$public = NH_VIEN_Links::public_en_posts();
		if ( ! isset( $public[ (int) $post_id ] ) ) {
			return '';
		}
		return self::url( $public[ (int) $post_id ]->post_name );
	}

	/**
	 * Accept simple ASCII article slugs.
	 *
	 * @param mixed $slug Slug to validate.
	 * @return bool Whether valid.
	 */
	private static function valid_slug( $slug ) {
		return is_string( $slug ) && 1 === preg_match( '/^[a-z0-9][a-z0-9_-]{0,190}$/D', $slug );
	}
}
