<?php
/**
 * English routes: the /en/ prefix, the rewrite rules behind it, and how a request for one of them is resolved.
 *
 * Nothing here runs unless the site opts in (see enabled(): a setting an administrator saves under Tools > VI/EN Links,
 * or the constants of the versions before 0.5.1), no rewrite rule is stored without an administrator action (and only
 * this plugin's own rules are taken out again, when it is switched off or deactivated), and no existing post, page or
 * URL is changed.
 *
 * @package Nguyen_Ha_VI_EN
 */

defined( 'ABSPATH' ) || exit;

/**
 * Gate, rules and request resolution of the English side of the site.
 */
final class NH_VIEN_Routes {
	const REWRITE        = '^en/([^/]+)/?$';
	const MULTIPAGE      = '^en/([^/]+)/([0-9]{1,})/?$';
	const HOME           = '^en/?$';
	const HOME_PAGE      = '^en/page/([0-9]{1,})/?$';
	const QUERY_VAR      = 'nh_vien_en_slug';
	const LANG_VAR       = 'nh_vien_lang';
	const SCOPE_VAR      = 'nh_vien_scope';
	const CONTROL_OPTION = 'nh_vien_routes_enabled_v1';
	const MARKER_HEADER  = 'X-NH-VIEN-Lang';

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
	 * Where the decision to switch the English side on or off comes from, in this order:
	 *
	 * 1. `constant_off`: wp-config.php defines NH_VIEN_ENABLE_EN_ROUTES as false. An emergency switch for a site whose
	 *    administration cannot be reached; it wins over everything else.
	 * 2. `setting_on` / `setting_off`: the setting an administrator saved under Tools > VI/EN Links (option
	 *    nh_vien_routes_enabled_v1, '1' or '0'). No hosting access is needed for it.
	 * 3. Without a saved setting, the constants of the versions before 0.5.1: `constant_on` when
	 *    NH_VIEN_ENABLE_EN_ROUTES is true and the environment is local, development or staging, or on production also
	 *    NH_VIEN_ALLOW_PRODUCTION is true; `constant_production_blocked` when the first constant is set on production
	 *    without the second; `unset` when nothing is set.
	 *
	 * @return string One of the codes above.
	 */
	public static function source() {
		if ( defined( 'NH_VIEN_ENABLE_EN_ROUTES' ) && false === NH_VIEN_ENABLE_EN_ROUTES ) {
			return 'constant_off';
		}
		$setting = get_option( self::CONTROL_OPTION, null );
		if ( '1' === $setting ) {
			return 'setting_on';
		}
		if ( '0' === $setting ) {
			return 'setting_off';
		}
		if ( ! defined( 'NH_VIEN_ENABLE_EN_ROUTES' ) || true !== NH_VIEN_ENABLE_EN_ROUTES || ! function_exists( 'wp_get_environment_type' ) ) {
			return 'unset';
		}
		if ( in_array( wp_get_environment_type(), array( 'local', 'development', 'staging' ), true ) ) {
			return 'constant_on';
		}
		return defined( 'NH_VIEN_ALLOW_PRODUCTION' ) && true === NH_VIEN_ALLOW_PRODUCTION ? 'constant_on' : 'constant_production_blocked';
	}

	/**
	 * Gate: whether the English routes are switched on (see source() for how that is decided).
	 *
	 * @return bool
	 */
	public static function enabled() {
		return in_array( self::source(), array( 'setting_on', 'constant_on' ), true );
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
	 * The checks that must pass before the English routes are switched on. Running them changes nothing.
	 *
	 * @return array<int,array{id:string,ok:bool,level:string,message:string}> Each check with its outcome: level is
	 *                                                                           ok, error, warning or info.
	 */
	public static function preflight() {
		$checks   = array();
		$checks[] = self::check(
			'permalinks',
			(bool) get_option( 'permalink_structure' ),
			'error',
			__( 'Pretty permalinks are on.', 'nguyen-ha-vi-en' ),
			__( 'Pretty permalinks are off: choose a permalink structure under Settings > Permalinks first.', 'nguyen-ha-vi-en' )
		);
		$checks[] = self::check(
			'post_at_en',
			! self::post_at_en(),
			'error',
			__( 'No published post uses the address /en/.', 'nguyen-ha-vi-en' ),
			__( 'A published post already has the address /en/. Give that post another slug first, or the English home would take its address away.', 'nguyen-ha-vi-en' )
		);
		$taken    = array();
		foreach ( self::reserved_slugs() as $slug ) {
			if ( null !== self::page_at( 'en/' . $slug ) ) {
				$taken[] = '/en/' . $slug . '/';
			}
		}
		$checks[] = self::check(
			'reserved_pages',
			! $taken,
			'error',
			__( 'No published page below /en/ uses a reserved address (page, category, tag, feed, ...).', 'nguyen-ha-vi-en' ),
			sprintf(
				/* translators: %s: comma separated list of addresses. */
				__( 'These published pages would be hidden by the English routes, give them other slugs first: %s', 'nguyen-ha-vi-en' ),
				implode( ', ', $taken )
			)
		);
		$foreign  = self::foreign_rules();
		$checks[] = self::check(
			'foreign_rules',
			! $foreign,
			'error',
			__( 'No other rewrite rule claims /en/.', 'nguyen-ha-vi-en' ),
			sprintf(
				/* translators: %s: comma separated list of regular expressions. */
				__( 'Another plugin or theme already answers /en/ with these rewrite rules; switch it off first: %s', 'nguyen-ha-vi-en' ),
				implode( ', ', $foreign )
			)
		);
		$slugs    = self::rewrite_slug_conflicts();
		$checks[] = self::check(
			'rewrite_slugs',
			! $slugs,
			'error',
			__( 'No post type or taxonomy is registered with the address /en/.', 'nguyen-ha-vi-en' ),
			sprintf(
				/* translators: %s: comma separated list of post types and taxonomies. */
				__( 'These post types or taxonomies use /en/ as their address and would collide with the English routes: %s', 'nguyen-ha-vi-en' ),
				implode( ', ', $slugs )
			)
		);
		$home     = self::home_page();
		$checks[] = self::check(
			'home_page',
			null !== $home,
			'warning',
			sprintf(
				/* translators: %d: page ID. */
				__( 'The published page #%d is the English home at /en/.', 'nguyen-ha-vi-en' ),
				$home ? (int) $home->ID : 0
			),
			__( 'No published page has the address /en/: /en/ lists the English posts, and answers 404 while there are none.', 'nguyen-ha-vi-en' )
		);
		$checks[] = self::check(
			'environment',
			true,
			'info',
			sprintf(
				/* translators: %s: WordPress environment type. */
				__( 'Environment type: %s.', 'nguyen-ha-vi-en' ),
				function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : '?'
			),
			''
		);
		$checks[] = self::check(
			'seo_plugin',
			true,
			'info',
			defined( 'WPSEO_VERSION' )
				? __( 'Yoast SEO is active: canonical addresses, hreflang pairs and the sitemap follow the English addresses.', 'nguyen-ha-vi-en' )
				: __( 'Yoast SEO is not active: the plugin prints hreflang and the canonical address of English lists itself.', 'nguyen-ha-vi-en' ),
			''
		);
		return $checks;
	}

	/**
	 * Whether a preflight found no error (warnings and information do not block).
	 *
	 * @param array<int,array{id:string,ok:bool,level:string,message:string}> $checks The checks of preflight().
	 * @return bool
	 */
	public static function preflight_passed( array $checks ) {
		foreach ( $checks as $check ) {
			if ( ! $check['ok'] && 'error' === $check['level'] ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * One preflight check.
	 *
	 * @param string $id    Identifier.
	 * @param bool   $ok    Whether it passed.
	 * @param string $level Level when it did not pass: error, warning or info.
	 * @param string $pass  Message when it passed.
	 * @param string $fail  Message when it did not.
	 * @return array{id:string,ok:bool,level:string,message:string}
	 */
	private static function check( $id, $ok, $level, $pass, $fail ) {
		return array(
			'id'      => $id,
			'ok'      => (bool) $ok,
			'level'   => $ok && 'info' !== $level ? 'ok' : $level,
			'message' => $ok ? $pass : $fail,
		);
	}

	/**
	 * Rewrite rules below /en/ that are not this plugin's: stored by WordPress, or registered in this request by another
	 * plugin or theme.
	 *
	 * @return string[] Regular expressions.
	 */
	private static function foreign_rules() {
		global $wp_rewrite;
		$ours  = self::rules();
		$found = array();
		$sets  = array( get_option( 'rewrite_rules' ) );
		if ( is_object( $wp_rewrite ) ) {
			foreach ( array( 'extra_rules_top', 'extra_rules' ) as $property ) {
				if ( isset( $wp_rewrite->$property ) ) {
					$sets[] = $wp_rewrite->$property;
				}
			}
		}
		foreach ( $sets as $rules ) {
			if ( ! is_array( $rules ) ) {
				continue;
			}
			foreach ( $rules as $regex => $query ) {
				$regex = (string) $regex;
				if ( ! preg_match( '#^\^?en(/|\$|\?|$)#', $regex ) ) {
					continue;
				}
				// Our own rule, stored by an earlier switch, is not a conflict; the same regex with another query is.
				if ( isset( $ours[ $regex ] ) && $ours[ $regex ] === $query ) {
					continue;
				}
				$found[ $regex ] = true;
			}
		}
		return array_keys( $found );
	}

	/**
	 * Post types and taxonomies whose rewrite slug is /en/ or below it.
	 *
	 * @return string[] Names.
	 */
	private static function rewrite_slug_conflicts() {
		$found = array();
		$lists = array();
		if ( function_exists( 'get_post_types' ) ) {
			$lists[] = get_post_types( array(), 'objects' );
		}
		if ( function_exists( 'get_taxonomies' ) ) {
			$lists[] = get_taxonomies( array(), 'objects' );
		}
		foreach ( $lists as $objects ) {
			foreach ( (array) $objects as $name => $object ) {
				$rewrite = is_object( $object ) && isset( $object->rewrite ) ? $object->rewrite : null;
				$slug    = is_array( $rewrite ) && isset( $rewrite['slug'] ) ? trim( (string) $rewrite['slug'], '/' ) : '';
				if ( 'en' === $slug || 0 === strpos( $slug, 'en/' ) ) {
					$found[] = (string) $name;
				}
			}
		}
		return $found;
	}

	/**
	 * Clear the runtime rewrite entries after switching the English section off.
	 * Preserve every other registered rewrite rule.
	 *
	 * @return void
	 */
	public static function clear_registered_rules() {
		global $wp_rewrite;
		if ( ! is_object( $wp_rewrite ) ) {
			return;
		}
		$keys = array_fill_keys( array_keys( self::rules() ), true );
		foreach ( array( 'extra_rules_top', 'extra_rules' ) as $property ) {
			if ( isset( $wp_rewrite->$property ) && is_array( $wp_rewrite->$property ) ) {
				$wp_rewrite->$property = array_diff_key( $wp_rewrite->$property, $keys );
			}
		}
	}

	/**
	 * Take this plugin's own rules out of the stored rewrite rules. Only keys of this plugin are removed, the other
	 * stored rules stay exactly as they are, and nothing is written when none of ours is stored.
	 *
	 * @return bool Whether the stored rules were written.
	 */
	public static function remove_stored_rules() {
		$stored = get_option( 'rewrite_rules' );
		if ( ! is_array( $stored ) ) {
			return false;
		}
		$left = array_diff_key( $stored, self::rules() );
		if ( count( $left ) === count( $stored ) ) {
			return false;
		}
		return (bool) update_option( 'rewrite_rules', $left );
	}

	/**
	 * On deactivation, take this plugin's own rules out of the stored rewrite rules. Without the plugin nothing would
	 * answer them: every /en/ address would show the Vietnamese home page.
	 *
	 * @return void
	 */
	public static function deactivate() {
		self::remove_stored_rules();
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
		add_filter( 'wp_headers', array( __CLASS__, 'headers' ) );
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
	 * Mark every English answer with a response header, so that a cache, a monitor or the smoke test of the Tools screen
	 * can tell that the English side answered the request (404 answers of the English side included).
	 *
	 * @param array $headers The headers WordPress is about to send.
	 * @return array
	 */
	public static function headers( $headers ) {
		if ( is_array( $headers ) && NH_VIEN_Context::is_english() ) {
			$headers[ self::MARKER_HEADER ] = 'en';
		}
		return $headers;
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
	 * Whether a published post (not a page) already has the address /en/. The English home would take that address away
	 * from it, so the administration refuses to switch the routes on while it exists.
	 *
	 * @return bool
	 */
	public static function post_at_en() {
		$found = get_posts(
			array(
				'name'                => 'en',
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'posts_per_page'      => 1,
				'fields'              => 'ids',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'suppress_filters'    => true,
				self::SCOPE_VAR       => 'off',
			)
		);
		return ! empty( $found );
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
