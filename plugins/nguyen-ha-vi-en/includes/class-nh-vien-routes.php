<?php
/**
 * Staging-only EN route preview; never changes database URLs or existing posts.
 * @package Nguyen_Ha_VI_EN
 */
defined( 'ABSPATH' ) || exit;

final class NH_VIEN_Routes {
	const REWRITE = '^en/([^/]+)/?$';
	const QUERY_VAR = 'nh_vien_en_slug';

	/** Gate routes behind an explicit staging-only configuration. */
	public static function enabled() {
		return defined( 'NH_VIEN_ENABLE_EN_ROUTES' )
			&& true === NH_VIEN_ENABLE_EN_ROUTES
			&& function_exists( 'wp_get_environment_type' )
			&& in_array( wp_get_environment_type(), array( 'local', 'development', 'staging' ), true );
	}

	public static function boot() {
		if ( ! self::enabled() ) {
			return;
		}
		add_action( 'init', array( __CLASS__, 'register' ), 20 );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'parse_request', array( __CLASS__, 'parse_request' ) );
		add_filter( 'redirect_canonical', array( __CLASS__, 'prevent_core_redirect' ), 10, 2 );
	}

	public static function register() {
		add_rewrite_rule( self::REWRITE, 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
	}

	public static function query_vars( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/** Map only URLs matched by our exact rewrite rule; query strings cannot spoof it. */
	public static function parse_request( $wp ) {
		if ( ! self::enabled() || ! isset( $wp->matched_rule ) || self::REWRITE !== $wp->matched_rule ) {
			return;
		}
		$slug = isset( $wp->query_vars[ self::QUERY_VAR ] ) ? $wp->query_vars[ self::QUERY_VAR ] : '';
		if ( ! self::valid_slug( $slug ) ) {
			$wp->query_vars = array( 'error' => '404' );
			return;
		}
		// Do not hijack an existing page at /en/{slug}/.
		$page = get_page_by_path( 'en/' . $slug, OBJECT, 'page' );
		if ( $page instanceof WP_Post ) {
			$wp->query_vars = array( 'page_id' => (int) $page->ID );
			return;
		}
		$id = self::post_id_for_slug( $slug );
		if ( ! $id ) {
			$wp->query_vars = array( 'error' => '404' );
			return;
		}
		$wp->query_vars = array( 'p' => $id, self::QUERY_VAR => $slug );
	}

	/** Published, unique, paired EN post or zero. */
	public static function post_id_for_slug( $slug ) {
		if ( ! self::valid_slug( $slug ) ) {
			return 0;
		}
		$matches = get_posts( array(
			'name' => $slug,
			'post_type' => 'post',
			'post_status' => 'publish',
			'posts_per_page' => 2,
			'fields' => 'ids',
			'no_found_rows' => true,
			'suppress_filters' => true,
		) );
		if ( ! is_array( $matches ) || 1 !== count( $matches ) ) {
			return 0;
		}
		$id = (int) reset( $matches );
		$post = get_post( $id );
		$pair = NH_VIEN_Links::for_post( $id );
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || $post->post_name !== $slug || ! is_array( $pair ) || (int) $pair['en'] !== $id ) {
			return 0;
		}
		$vi = get_post( (int) $pair['vi'] );
		return $vi instanceof WP_Post && 'publish' === $vi->post_status ? $id : 0;
	}

	/** English URL; never alters get_permalink() or a database value. */
	public static function english_url( $post_id ) {
		if ( ! self::enabled() ) {
			return '';
		}
		$post = get_post( (int) $post_id );
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || ! self::valid_slug( $post->post_name ) ) {
			return '';
		}
		if ( get_page_by_path( 'en/' . $post->post_name, OBJECT, 'page' ) instanceof WP_Post ) {
			return '';
		}
		if ( (int) $post_id !== self::post_id_for_slug( $post->post_name ) ) {
			return '';
		}
		return home_url( '/en/' . $post->post_name . '/' );
	}

	/** Disable core's automatic redirect only for a verified EN URL. */
	public static function prevent_core_redirect( $redirect_url, $requested_url ) {
		if ( ! self::enabled() || ! is_singular( 'post' ) ) {
			return $redirect_url;
		}
		$slug = get_query_var( self::QUERY_VAR, '' );
		return is_string( $slug ) && self::post_id_for_slug( $slug ) === (int) get_queried_object_id()
			? false : $redirect_url;
	}

	private static function valid_slug( $slug ) {
		return is_string( $slug ) && 1 === preg_match( '/^[a-z0-9][a-z0-9_-]{0,190}$/D', $slug );
	}
}
