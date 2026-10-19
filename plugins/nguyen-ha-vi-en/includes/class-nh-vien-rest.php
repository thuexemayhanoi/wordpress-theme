<?php
/**
 * REST API of the plugin, for administrators only (application passwords work): the state of the English side, the
 * switch, and the registrations the Tools screen offers (English categories, English posts, VI/EN pairs of posts and
 * pages). It lets a release pipeline do what an administrator does on the Tools screen, with the same checks.
 *
 * @package Nguyen_Ha_VI_EN
 */

defined( 'ABSPATH' ) || exit;

/**
 * Routes below /wp-json/nh-vien/v1/.
 */
final class NH_VIEN_Rest {
	const NS = 'nh-vien/v1';

	/** Register the routes when the REST API loads; nothing runs on an ordinary page. */
	public static function boot() {
		add_action( 'rest_api_init', array( __CLASS__, 'register' ) );
	}

	/** Register every route; each one needs manage_options. */
	public static function register() {
		$admin = array( __CLASS__, 'permission' );
		$id    = array(
			'required'          => true,
			'type'              => 'integer',
			'minimum'           => 1,
			'sanitize_callback' => 'absint',
		);
		register_rest_route(
			self::NS,
			'/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'status' ),
				'permission_callback' => $admin,
			)
		);
		register_rest_route(
			self::NS,
			'/routes',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'routes' ),
				'permission_callback' => $admin,
				'args'                => array(
					'enabled' => array(
						'required' => true,
						'type'     => 'boolean',
					),
					'confirm' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/routes/undo',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'undo' ),
				'permission_callback' => $admin,
			)
		);
		register_rest_route(
			self::NS,
			'/settings',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'settings' ),
				'permission_callback' => $admin,
				'args'                => array(
					'site_name_en' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/categories',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'mark_category' ),
				'permission_callback' => $admin,
				'args'                => array( 'id' => $id ),
			)
		);
		register_rest_route(
			self::NS,
			'/categories/(?P<id>[1-9][0-9]*)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( __CLASS__, 'unmark_category' ),
				'permission_callback' => $admin,
				'args'                => array( 'id' => $id ),
			)
		);
		register_rest_route(
			self::NS,
			'/posts',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'mark_post' ),
				'permission_callback' => $admin,
				'args'                => array( 'id' => $id ),
			)
		);
		register_rest_route(
			self::NS,
			'/posts/(?P<id>[1-9][0-9]*)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( __CLASS__, 'unmark_post' ),
				'permission_callback' => $admin,
				'args'                => array( 'id' => $id ),
			)
		);
		register_rest_route(
			self::NS,
			'/pairs',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'link_posts' ),
				'permission_callback' => $admin,
				'args'                => array(
					'vi' => $id,
					'en' => $id,
				),
			)
		);
		register_rest_route(
			self::NS,
			'/pairs/(?P<vi>[1-9][0-9]*)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( __CLASS__, 'unlink_posts' ),
				'permission_callback' => $admin,
				'args'                => array( 'vi' => $id ),
			)
		);
		register_rest_route(
			self::NS,
			'/pages',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'link_pages' ),
				'permission_callback' => $admin,
				'args'                => array(
					'vi' => $id,
					'en' => $id,
				),
			)
		);
		register_rest_route(
			self::NS,
			'/pages/(?P<vi>[1-9][0-9]*)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( __CLASS__, 'unlink_pages' ),
				'permission_callback' => $admin,
				'args'                => array( 'vi' => $id ),
			)
		);
	}

	/**
	 * Administrators only.
	 *
	 * @return true|WP_Error
	 */
	public static function permission() {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		return new WP_Error(
			'nh_vien_forbidden',
			__( 'Administrator permission required.', 'nguyen-ha-vi-en' ),
			array( 'status' => function_exists( 'rest_authorization_required_code' ) ? rest_authorization_required_code() : 403 )
		);
	}

	/**
	 * GET /status.
	 *
	 * @return array<string,mixed>
	 */
	public static function status() {
		return NH_VIEN_Settings::status();
	}

	/**
	 * POST /routes {enabled, confirm}: switch the English routes on or off, with the preflight, the smoke test and the
	 * rollback of the Tools screen.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function routes( $request ) {
		$enabled = rest_sanitize_boolean( $request->get_param( 'enabled' ) );
		$confirm = rest_sanitize_boolean( $request->get_param( 'confirm' ) );
		$result  = $enabled ? NH_VIEN_Settings::enable( $confirm ) : NH_VIEN_Settings::disable();
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array(
			'result' => $result,
			'status' => NH_VIEN_Settings::status(),
		);
	}

	/**
	 * POST /routes/undo.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public static function undo() {
		$result = NH_VIEN_Settings::undo();
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array(
			'result' => $result,
			'status' => NH_VIEN_Settings::status(),
		);
	}

	/**
	 * POST /settings {site_name_en}: the name of the site inside English pages; an empty name removes it.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function settings( $request ) {
		$result = NH_VIEN_Settings::set_site_name( $request->get_param( 'site_name_en' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array(
			'result' => $result,
			'status' => NH_VIEN_Settings::status(),
		);
	}

	/**
	 * POST /categories {id}: reserve an empty category (whose parent is English too) for English.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function mark_category( $request ) {
		return self::answer( NH_VIEN_Links::mark_english_category( (int) $request->get_param( 'id' ), self::nonce() ), 'english_categories' );
	}

	/**
	 * DELETE /categories/{id}.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function unmark_category( $request ) {
		return self::answer( NH_VIEN_Links::unmark_english_category( (int) $request->get_param( 'id' ), self::nonce() ), 'english_categories' );
	}

	/**
	 * POST /posts {id}: register a draft or pending post as an independent English post, before it is published.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function mark_post( $request ) {
		return self::answer( NH_VIEN_Links::mark_english( (int) $request->get_param( 'id' ), self::nonce() ), 'independent_posts' );
	}

	/**
	 * DELETE /posts/{id}.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function unmark_post( $request ) {
		return self::answer( NH_VIEN_Links::unmark_english( (int) $request->get_param( 'id' ), self::nonce() ), 'independent_posts' );
	}

	/**
	 * POST /pairs {vi, en}: pair a Vietnamese post with its English translation.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function link_posts( $request ) {
		return self::answer( NH_VIEN_Links::link( (int) $request->get_param( 'vi' ), (int) $request->get_param( 'en' ), self::nonce() ), 'pairs' );
	}

	/**
	 * DELETE /pairs/{vi}.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function unlink_posts( $request ) {
		return self::answer( NH_VIEN_Links::unlink( (int) $request->get_param( 'vi' ), self::nonce() ), 'pairs' );
	}

	/**
	 * POST /pages {vi, en}: pair a Vietnamese page with the English page below /en/ that serves the same purpose.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function link_pages( $request ) {
		return self::answer( NH_VIEN_Links::link_page( (int) $request->get_param( 'vi' ), (int) $request->get_param( 'en' ), self::nonce() ), 'page_pairs' );
	}

	/**
	 * DELETE /pages/{vi}.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function unlink_pages( $request ) {
		return self::answer( NH_VIEN_Links::unlink_page( (int) $request->get_param( 'vi' ), self::nonce() ), 'page_pairs' );
	}

	/**
	 * A nonce for the registration methods of NH_VIEN_Links, which ask for one: here the authenticated request of an
	 * administrator is the proof of intent (REST requests with cookies carry their own nonce, checked by WordPress).
	 *
	 * @return string
	 */
	private static function nonce() {
		return wp_create_nonce( NH_VIEN_Links::NONCE_ACTION );
	}

	/**
	 * The answer to a registration: the list it changed, or the error with an HTTP status.
	 *
	 * @param true|WP_Error $result What NH_VIEN_Links said.
	 * @param string        $key    The key of the status that holds the list.
	 * @return array<string,mixed>|WP_Error
	 */
	private static function answer( $result, $key ) {
		if ( is_wp_error( $result ) ) {
			$code     = $result->get_error_code();
			$statuses = array(
				'nh_vien_forbidden'         => 403,
				'nh_vien_bad_nonce'         => 403,
				'nh_vien_not_found'         => 404,
				'nh_vien_conflict'          => 409,
				'nh_vien_category_conflict' => 409,
				'nh_vien_invalid_store'     => 500,
				'nh_vien_save_failed'       => 500,
			);
			return new WP_Error( $code, $result->get_error_message(), array( 'status' => isset( $statuses[ $code ] ) ? $statuses[ $code ] : 400 ) );
		}
		$status = NH_VIEN_Settings::status();
		return array(
			'saved' => true,
			$key    => isset( $status[ $key ] ) ? $status[ $key ] : array(),
		);
	}
}
