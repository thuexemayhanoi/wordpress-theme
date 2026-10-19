<?php
/**
 * Tools > VI/EN Links. Every action is initiated by an administrator, with a nonce, and changes nothing but the
 * plugin's own settings and lists, the stored rewrite rules, or a new draft page.
 *
 * @package Nguyen_Ha_VI_EN
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tools panel: the switch of the English routes with its preflight, pairs, English categories and posts, and the
 * maintenance buttons.
 */
final class NH_VIEN_Admin {
	const PAGE         = 'nh-vien-links';
	const FLUSH_NONCE  = 'nh_vien_flush_rules';
	const HOME_NONCE   = 'nh_vien_create_home_page';
	const TOGGLE_NONCE = 'nh_vien_toggle_routes';
	const UNDO_NONCE   = 'nh_vien_undo_routes';
	const NAME_NONCE   = 'nh_vien_site_name';

	/** Register the administrator-only hooks. */
	public static function boot() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'admin_post_nh_vien_link', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_nh_vien_unlink', array( __CLASS__, 'remove' ) );
		add_action( 'admin_post_nh_vien_mark_english', array( __CLASS__, 'mark_english' ) );
		add_action( 'admin_post_nh_vien_unmark_english', array( __CLASS__, 'unmark_english' ) );
		add_action( 'admin_post_nh_vien_mark_category', array( __CLASS__, 'mark_category' ) );
		add_action( 'admin_post_nh_vien_unmark_category', array( __CLASS__, 'unmark_category' ) );
		add_action( 'admin_post_nh_vien_link_page', array( __CLASS__, 'link_page' ) );
		add_action( 'admin_post_nh_vien_unlink_page', array( __CLASS__, 'unlink_page' ) );
		add_action( 'admin_post_nh_vien_flush', array( __CLASS__, 'flush' ) );
		add_action( 'admin_post_nh_vien_home_page', array( __CLASS__, 'home_page' ) );
		add_action( 'admin_post_nh_vien_toggle', array( __CLASS__, 'toggle_routes' ) );
		add_action( 'admin_post_nh_vien_undo', array( __CLASS__, 'undo_routes' ) );
		add_action( 'admin_post_nh_vien_site_name', array( __CLASS__, 'site_name' ) );
	}

	/** Add the Tools navigation entry. */
	public static function menu() {
		add_management_page( 'VI/EN Translation Links', 'VI/EN Links', 'manage_options', self::PAGE, array( __CLASS__, 'render' ) );
	}

	/**
	 * Switch the English routes on or off from WordPress, without a wp-config.php line. Administrators only, with a
	 * nonce; on a production site switching on needs the confirmation box. The preflight, the rewrite rules, the smoke
	 * test and the rollback are the work of NH_VIEN_Settings.
	 */
	public static function toggle_routes() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Administrator permission required.', 'nguyen-ha-vi-en' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::TOGGLE_NONCE );
		$mode      = isset( $_POST['nh_vien_mode'] ) && is_string( $_POST['nh_vien_mode'] ) ? sanitize_key( wp_unslash( $_POST['nh_vien_mode'] ) ) : '';
		$confirmed = isset( $_POST['nh_vien_confirm'] ) && is_string( $_POST['nh_vien_confirm'] ) && '1' === sanitize_key( wp_unslash( $_POST['nh_vien_confirm'] ) );
		if ( 'enable' === $mode ) {
			$result = NH_VIEN_Settings::enable( $confirmed );
			if ( is_wp_error( $result ) ) {
				self::redirect( $result->get_error_code() );
			}
			self::redirect( 'inconclusive' === $result['smoke']['result'] ? 'routes_enabled_unverified' : 'routes_enabled' );
		}
		if ( 'disable' === $mode ) {
			$result = NH_VIEN_Settings::disable();
			self::redirect( is_wp_error( $result ) ? $result->get_error_code() : 'routes_disabled' );
		}
		self::redirect( 'nh_vien_invalid_mode' );
	}

	/** Back to the state before the last switch. */
	public static function undo_routes() {
		self::authorize( self::UNDO_NONCE );
		$result = NH_VIEN_Settings::undo();
		self::redirect( is_wp_error( $result ) ? $result->get_error_code() : 'undone' );
	}

	/** Save the name of the site inside English pages (empty: the Vietnamese name stays). Administrators, with a nonce. */
	public static function site_name() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Administrator permission required.', 'nguyen-ha-vi-en' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::NAME_NONCE );
		$name   = isset( $_POST['nh_vien_site_name_en'] ) && is_string( $_POST['nh_vien_site_name_en'] ) ? sanitize_text_field( wp_unslash( $_POST['nh_vien_site_name_en'] ) ) : '';
		$result = NH_VIEN_Settings::set_site_name( $name );
		self::redirect( is_wp_error( $result ) ? $result->get_error_code() : 'site_name_saved' );
	}

	/** Save a pair after confirming a valid CSRF token. */
	public static function save() {
		check_admin_referer( NH_VIEN_Links::NONCE_ACTION );
		$nonce = isset( $_POST['_wpnonce'] ) && is_string( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		$vi    = isset( $_POST['vi_post_id'] ) && is_string( $_POST['vi_post_id'] ) ? wp_unslash( $_POST['vi_post_id'] ) : '';
		$en    = isset( $_POST['en_post_id'] ) && is_string( $_POST['en_post_id'] ) ? wp_unslash( $_POST['en_post_id'] ) : '';
		self::redirect( NH_VIEN_Links::link( $vi, $en, $nonce ) );
	}

	/** Remove a pair after confirming a valid CSRF token. */
	public static function remove() {
		check_admin_referer( NH_VIEN_Links::NONCE_ACTION );
		$nonce = isset( $_POST['_wpnonce'] ) && is_string( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		$vi    = isset( $_POST['vi_post_id'] ) && is_string( $_POST['vi_post_id'] ) ? wp_unslash( $_POST['vi_post_id'] ) : '';
		self::redirect( NH_VIEN_Links::unlink( $vi, $nonce ) );
	}

	/** Designate a new article as English, without changing its body or permalink. */
	public static function mark_english() {
		check_admin_referer( NH_VIEN_Links::NONCE_ACTION );
		$nonce = isset( $_POST['_wpnonce'] ) && is_string( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		$id    = isset( $_POST['en_post_id'] ) && is_string( $_POST['en_post_id'] ) ? wp_unslash( $_POST['en_post_id'] ) : '';
		self::redirect( NH_VIEN_Links::mark_english( $id, $nonce ) );
	}

	/** Remove an independent language assignment, leaving the original article untouched. */
	public static function unmark_english() {
		check_admin_referer( NH_VIEN_Links::NONCE_ACTION );
		$nonce = isset( $_POST['_wpnonce'] ) && is_string( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		$id    = isset( $_POST['en_post_id'] ) && is_string( $_POST['en_post_id'] ) ? wp_unslash( $_POST['en_post_id'] ) : '';
		self::redirect( NH_VIEN_Links::unmark_english( $id, $nonce ) );
	}

	/** Reserve an empty WordPress category for English. */
	public static function mark_category() {
		check_admin_referer( NH_VIEN_Links::NONCE_ACTION );
		$nonce = isset( $_POST['_wpnonce'] ) && is_string( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		$id    = isset( $_POST['en_category_id'] ) && is_string( $_POST['en_category_id'] ) ? wp_unslash( $_POST['en_category_id'] ) : '';
		self::redirect( NH_VIEN_Links::mark_english_category( $id, $nonce ) );
	}

	/** Remove an English category assignment without editing the term. */
	public static function unmark_category() {
		check_admin_referer( NH_VIEN_Links::NONCE_ACTION );
		$nonce = isset( $_POST['_wpnonce'] ) && is_string( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		$id    = isset( $_POST['en_category_id'] ) && is_string( $_POST['en_category_id'] ) ? wp_unslash( $_POST['en_category_id'] ) : '';
		self::redirect( NH_VIEN_Links::unmark_english_category( $id, $nonce ) );
	}

	/** Pair two independent pages that serve equivalent visitor needs. */
	public static function link_page() {
		check_admin_referer( NH_VIEN_Links::NONCE_ACTION );
		$nonce = isset( $_POST['_wpnonce'] ) && is_string( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		$vi    = isset( $_POST['vi_page_id'] ) && is_string( $_POST['vi_page_id'] ) ? wp_unslash( $_POST['vi_page_id'] ) : '';
		$en    = isset( $_POST['en_page_id'] ) && is_string( $_POST['en_page_id'] ) ? wp_unslash( $_POST['en_page_id'] ) : '';
		self::redirect( NH_VIEN_Links::link_page( $vi, $en, $nonce ) );
	}

	/** Unlink two pages without modifying either. */
	public static function unlink_page() {
		check_admin_referer( NH_VIEN_Links::NONCE_ACTION );
		$nonce = isset( $_POST['_wpnonce'] ) && is_string( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		$vi    = isset( $_POST['vi_page_id'] ) && is_string( $_POST['vi_page_id'] ) ? wp_unslash( $_POST['vi_page_id'] ) : '';
		self::redirect( NH_VIEN_Links::unlink_page( $vi, $nonce ) );
	}

	/**
	 * Store the rewrite rules again: the English rules are added when the gate is open and removed when it is closed.
	 * A maintenance action for the rare case that another plugin or a migration dropped them.
	 */
	public static function flush() {
		self::authorize( self::FLUSH_NONCE );
		// Closing the English side must always work; opening it must not take /en/ away from a post.
		if ( NH_VIEN_Routes::enabled() && NH_VIEN_Routes::post_at_en() ) {
			self::redirect( 'address_taken' );
		}
		flush_rewrite_rules( false );
		NH_VIEN_Routes::reset();
		do_action( 'nh_vien_pairs_changed', 0, 0 );
		self::redirect( 'flushed' );
	}

	/** Create the English home page as a draft; it is never published automatically. */
	public static function home_page() {
		self::authorize( self::HOME_NONCE );
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You are not allowed to create pages.', 'nguyen-ha-vi-en' ), '', array( 'response' => 403 ) );
		}
		if ( get_page_by_path( 'en', OBJECT, 'page' ) instanceof WP_Post ) {
			self::redirect( 'home_exists' );
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_title'   => 'English',
				'post_name'    => 'en',
				'post_content' => '[nh_vien_posts]',
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			self::redirect( 'home_failed' );
		}
		NH_VIEN_Routes::reset();
		wp_safe_redirect( (string) get_edit_post_link( $id, 'raw' ) );
		exit;
	}

	/**
	 * Stop unless the current user may manage the plugin and the request carries the nonce of the action.
	 *
	 * @param string $nonce_action The nonce action of the form.
	 */
	private static function authorize( $nonce_action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Administrator permission required.', 'nguyen-ha-vi-en' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $nonce_action );
	}

	/**
	 * Redirect back to the Tools screen with a safe status token.
	 *
	 * @param true|WP_Error|string $result Saved result, or the status token itself.
	 */
	private static function redirect( $result ) {
		if ( is_string( $result ) ) {
			$status = $result;
		} else {
			$status = is_wp_error( $result ) ? $result->get_error_code() : 'saved';
		}
		if ( ! array_key_exists( $status, self::messages() ) ) {
			$status = 'nh_vien_save_failed';
		}
		wp_safe_redirect( add_query_arg( 'nh_vien_status', $status, admin_url( 'tools.php?page=' . self::PAGE ) ) );
		exit;
	}

	/**
	 * The notices the Tools screen can show, by status token.
	 *
	 * @return array<string,array{0:string,1:string}> Token => notice class and text.
	 */
	private static function messages() {
		$error = array( 'notice-error', __( 'Nothing was changed. Check the post IDs, your permissions, existing links and the nonce.', 'nguyen-ha-vi-en' ) );
		return array(
			'saved'                     => array( 'notice-success', __( 'Translation link operation completed.', 'nguyen-ha-vi-en' ) ),
			'flushed'                   => array( 'notice-success', __( 'The rewrite rules were stored again.', 'nguyen-ha-vi-en' ) ),
			'routes_enabled'            => array( 'notice-success', __( 'The English routes are on, and the server answered /en/ in English. Check the English home, the menus, the categories and the sitemap.', 'nguyen-ha-vi-en' ) ),
			'routes_enabled_unverified' => array( 'notice-warning', __( 'The English routes are on, but the server could not check /en/ by itself. Open /en/ in a browser now; if it is wrong, press "Undo the last switch".', 'nguyen-ha-vi-en' ) ),
			'routes_disabled'           => array( 'notice-success', __( 'The English routes are off: the plugin answers no /en/ address any more and the Vietnamese site is as it was. Published English pages are still reachable at their WordPress addresses.', 'nguyen-ha-vi-en' ) ),
			'undone'                    => array( 'notice-success', __( 'The state before the last switch was restored.', 'nguyen-ha-vi-en' ) ),
			'site_name_saved'           => array( 'notice-success', __( 'The English site name was saved: English pages use it in their title, Open Graph tags and structured data.', 'nguyen-ha-vi-en' ) ),
			'address_taken'             => array( 'notice-error', __( 'Nothing was changed: a published post already has the address /en/. Give that post another slug first, or the English home would take its address away.', 'nguyen-ha-vi-en' ) ),
			'home_exists'               => array( 'notice-warning', __( 'A page with the address /en/ already exists; it was left as it is.', 'nguyen-ha-vi-en' ) ),
			'home_failed'               => array( 'notice-error', __( 'The English home page could not be created.', 'nguyen-ha-vi-en' ) ),
			'nh_vien_invalid_mode'      => array( 'notice-error', __( 'Nothing was changed: unknown switch action.', 'nguyen-ha-vi-en' ) ),
			'nh_vien_confirm_required'  => array( 'notice-error', __( 'Nothing was changed: this is the live site, tick the confirmation box before switching the English routes on.', 'nguyen-ha-vi-en' ) ),
			'nh_vien_constant_off'      => array( 'notice-error', __( 'Nothing was changed: wp-config.php defines NH_VIEN_ENABLE_EN_ROUTES as false (the emergency switch). Remove that line to use this screen.', 'nguyen-ha-vi-en' ) ),
			'nh_vien_preflight_failed'  => array( 'notice-error', __( 'Nothing was changed: the preflight found a conflict, see the checks below.', 'nguyen-ha-vi-en' ) ),
			'nh_vien_rewrite_failed'    => array( 'notice-error', __( 'The rewrite rules could not be stored or refreshed. The previous state was restored; press "Store the rewrite rules" and check Settings > Permalinks.', 'nguyen-ha-vi-en' ) ),
			'nh_vien_smoke_failed'      => array( 'notice-error', __( 'The English routes were switched on, but /en/ did not answer in English, so the previous state was restored. Purge the page cache and try again; the details are in the "Last smoke test" row.', 'nguyen-ha-vi-en' ) ),
			'nh_vien_nothing_to_undo'   => array( 'notice-warning', __( 'There is no switch to undo.', 'nguyen-ha-vi-en' ) ),
			'nh_vien_forbidden'         => $error,
			'nh_vien_bad_nonce'         => $error,
			'nh_vien_invalid_ids'       => $error,
			'nh_vien_invalid_posts'     => $error,
			'nh_vien_invalid_category'  => $error,
			'nh_vien_category_conflict' => $error,
			'nh_vien_invalid_store'     => $error,
			'nh_vien_conflict'          => $error,
			'nh_vien_not_found'         => $error,
			'nh_vien_save_failed'       => $error,
		);
	}

	/** Remind administrators that the English routes are switched on but not stored yet. */
	public static function notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'plugins' ), true ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( NH_VIEN_Routes::enabled() && ! NH_VIEN_Routes::rules_stored() ) {
			echo '<div class="notice notice-warning"><p>';
			echo esc_html__( 'VI/EN: the English routes are switched on, but their rewrite rules are not stored. Open Tools > VI/EN Links and press "Store the rewrite rules".', 'nguyen-ha-vi-en' );
			echo '</p></div>';
		}
	}

	/** Render the Tools screen for permitted users. */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Administrator permission required.', 'nguyen-ha-vi-en' ) );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'VI/EN Translation Links', 'nguyen-ha-vi-en' ) . '</h1>';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status token shown to the administrator; it selects one of a fixed list of notices.
		$status   = isset( $_GET['nh_vien_status'] ) && is_string( $_GET['nh_vien_status'] ) ? sanitize_key( wp_unslash( $_GET['nh_vien_status'] ) ) : '';
		$messages = self::messages();
		if ( '' !== $status ) {
			$message = isset( $messages[ $status ] ) ? $messages[ $status ] : $messages['nh_vien_save_failed'];
			echo '<div class="notice ' . esc_attr( $message[0] ) . '"><p>' . esc_html( $message[1] ) . '</p></div>';
		}
		self::render_status();
		$pairs = NH_VIEN_Links::all();
		if ( is_wp_error( $pairs ) ) {
			echo '<p>' . esc_html__( 'The translation map is invalid; no changes are permitted until it is repaired.', 'nguyen-ha-vi-en' ) . '</p></div>';
			return;
		}
		self::render_pairs( $pairs );
		self::render_independent();
		self::render_english_categories();
		self::render_page_pairs();
		echo '</div>';
	}

	/**
	 * What the source code of the switch means, for the status table.
	 *
	 * @param string $source A code of NH_VIEN_Routes::source().
	 * @return string
	 */
	private static function source_text( $source ) {
		$texts = array(
			'setting_on'                  => __( 'On: switched on from this screen (stored in the WordPress options).', 'nguyen-ha-vi-en' ),
			'setting_off'                 => __( 'Off: switched off from this screen.', 'nguyen-ha-vi-en' ),
			'constant_on'                 => __( 'On: by the constant NH_VIEN_ENABLE_EN_ROUTES in wp-config.php (the setup of older versions); the buttons below take over the moment they are used.', 'nguyen-ha-vi-en' ),
			'constant_production_blocked' => __( 'Off: wp-config.php sets NH_VIEN_ENABLE_EN_ROUTES, but this is a production site without NH_VIEN_ALLOW_PRODUCTION. Use the button below instead of the constants.', 'nguyen-ha-vi-en' ),
			'constant_off'                => __( 'Off: wp-config.php defines NH_VIEN_ENABLE_EN_ROUTES as false (the emergency switch). Remove that line to use this screen.', 'nguyen-ha-vi-en' ),
			'unset'                       => __( 'Off: not switched on yet.', 'nguyen-ha-vi-en' ),
		);
		return isset( $texts[ $source ] ) ? $texts[ $source ] : $source;
	}

	/** Render the state of the English routes, the preflight, the switch and the maintenance buttons. */
	private static function render_status() {
		$status = NH_VIEN_Settings::status();
		$yes    = __( 'Yes', 'nguyen-ha-vi-en' );
		$no     = __( 'No', 'nguyen-ha-vi-en' );
		$home   = $status['home_page']
			? sprintf(
				/* translators: %d: page ID. */
				__( 'The published page #%d at /en/', 'nguyen-ha-vi-en' ),
				$status['home_page']
			)
			: __( 'The list of English posts (create and publish a page with the slug "en" to write your own home page)', 'nguyen-ha-vi-en' );
		$change = $status['last_change'];
		if ( $change ) {
			$last = sprintf(
				/* translators: 1: "switched on" or "switched off", 2: date and time, 3: user ID. */
				__( '%1$s on %2$s by user #%3$d; it can be undone below.', 'nguyen-ha-vi-en' ),
				'enable' === $change['action'] ? __( 'Switched on', 'nguyen-ha-vi-en' ) : __( 'Switched off', 'nguyen-ha-vi-en' ),
				$change['at'] ? wp_date( 'Y-m-d H:i', $change['at'] ) : '?',
				$change['user']
			);
		} else {
			$last = __( 'No switch recorded.', 'nguyen-ha-vi-en' );
		}
		$rows = array(
			array( __( 'English routes', 'nguyen-ha-vi-en' ), self::source_text( $status['source'] ) ),
			array( __( 'Environment type', 'nguyen-ha-vi-en' ), (string) $status['environment'] ),
			array( __( 'Rewrite rules stored', 'nguyen-ha-vi-en' ), $status['rules_stored'] ? $yes : $no ),
			array( __( 'English pages are live', 'nguyen-ha-vi-en' ), $status['live'] ? $yes : $no ),
			array( __( 'English home', 'nguyen-ha-vi-en' ), $home ),
			array( __( 'Address /en/ used by a post', 'nguyen-ha-vi-en' ), NH_VIEN_Routes::post_at_en() ? __( 'Yes: the routes cannot be switched on until that post has another slug', 'nguyen-ha-vi-en' ) : $no ),
			array( __( 'Yoast SEO active', 'nguyen-ha-vi-en' ), $status['yoast'] ? $yes : $no ),
			array( __( 'English site name', 'nguyen-ha-vi-en' ), '' !== $status['site_name_en'] ? $status['site_name_en'] : __( 'Not set: English pages show the Vietnamese site name', 'nguyen-ha-vi-en' ) ),
			array( __( 'Pretty permalinks', 'nguyen-ha-vi-en' ), $status['pretty_permalinks'] ? $yes : $no ),
			array( __( 'Last switch', 'nguyen-ha-vi-en' ), $last ),
		);
		echo '<h2>' . esc_html__( 'English routes', 'nguyen-ha-vi-en' ) . '</h2><table class="widefat striped" style="max-width:52rem"><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr><th scope="row">' . esc_html( $row[0] ) . '</th><td>' . esc_html( $row[1] ) . '</td></tr>';
		}
		echo '</tbody></table>';
		self::render_preflight( $status['preflight'] );
		echo '<p>' . esc_html__( 'The switch below is the only thing needed: no wp-config.php line, no hosting access. Switching on runs the checks above, stores the rewrite rules once, asks the server for /en/ and goes back to the previous state if that fails. Switching off takes the plugin\'s own rules out of the stored rules and leaves every Vietnamese address, post, category and menu as it is.', 'nguyen-ha-vi-en' ) . '</p>';
		if ( 'constant_off' === $status['source'] ) {
			echo '<p><strong>' . esc_html__( 'The emergency constant in wp-config.php keeps the English side off; remove it to use the switch.', 'nguyen-ha-vi-en' ) . '</strong></p>';
		} elseif ( $status['enabled'] ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:1rem 0">';
			wp_nonce_field( self::TOGGLE_NONCE );
			echo '<input type="hidden" name="action" value="nh_vien_toggle">';
			echo '<input type="hidden" name="nh_vien_mode" value="disable">';
			submit_button( __( 'Switch the English routes off', 'nguyen-ha-vi-en' ), 'secondary', 'submit', false );
			echo '</form>';
		} else {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:1rem 0">';
			wp_nonce_field( self::TOGGLE_NONCE );
			echo '<input type="hidden" name="action" value="nh_vien_toggle">';
			echo '<input type="hidden" name="nh_vien_mode" value="enable">';
			if ( 'production' === $status['environment'] ) {
				echo '<p><label><input type="checkbox" name="nh_vien_confirm" value="1"> ' . esc_html__( 'This is the live site: a fresh backup exists and the English pages, prices and contact details were checked.', 'nguyen-ha-vi-en' ) . '</label></p>';
			}
			submit_button( __( 'Switch the English routes on', 'nguyen-ha-vi-en' ), 'primary', 'submit', false );
			echo '</form>';
		}
		if ( $change ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-right:1rem">';
			wp_nonce_field( self::UNDO_NONCE );
			echo '<input type="hidden" name="action" value="nh_vien_undo">';
			submit_button( __( 'Undo the last switch', 'nguyen-ha-vi-en' ), 'secondary', 'submit', false );
			echo '</form>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-right:1rem">';
		wp_nonce_field( self::FLUSH_NONCE );
		echo '<input type="hidden" name="action" value="nh_vien_flush">';
		submit_button( __( 'Store the rewrite rules', 'nguyen-ha-vi-en' ), 'secondary', 'submit', false );
		echo '</form>';
		if ( ! get_page_by_path( 'en', OBJECT, 'page' ) ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block">';
			wp_nonce_field( self::HOME_NONCE );
			echo '<input type="hidden" name="action" value="nh_vien_home_page">';
			submit_button( __( 'Create a draft English home page', 'nguyen-ha-vi-en' ), 'secondary', 'submit', false );
			echo '</form>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:1rem 0">';
		wp_nonce_field( self::NAME_NONCE );
		echo '<input type="hidden" name="action" value="nh_vien_site_name">';
		echo '<p><label for="nh_vien_site_name_en">' . esc_html__( 'English site name: the title suffix, og:site_name and structured data of English pages (empty keeps the Vietnamese name)', 'nguyen-ha-vi-en' ) . '</label></p>';
		echo '<input type="text" class="regular-text" id="nh_vien_site_name_en" name="nh_vien_site_name_en" maxlength="' . (int) NH_VIEN_Settings::SITE_NAME_MAX . '" value="' . esc_attr( $status['site_name_en'] ) . '"> ';
		submit_button( __( 'Save the English site name', 'nguyen-ha-vi-en' ), 'secondary', 'submit', false );
		echo '</form>';
	}

	/**
	 * Render the preflight checks.
	 *
	 * @param array<int,array{id:string,ok:bool,level:string,message:string}> $checks The checks.
	 */
	private static function render_preflight( array $checks ) {
		$labels = array(
			'ok'      => __( 'OK', 'nguyen-ha-vi-en' ),
			'error'   => __( 'Conflict', 'nguyen-ha-vi-en' ),
			'warning' => __( 'Note', 'nguyen-ha-vi-en' ),
			'info'    => __( 'Info', 'nguyen-ha-vi-en' ),
		);
		echo '<h3>' . esc_html__( 'Preflight', 'nguyen-ha-vi-en' ) . '</h3><ul style="max-width:52rem">';
		foreach ( $checks as $check ) {
			$label = isset( $labels[ $check['level'] ] ) ? $labels[ $check['level'] ] : $check['level'];
			echo '<li>' . ( 'error' === $check['level'] ? '<strong>' : '' ) . esc_html( '[' . $label . '] ' . $check['message'] ) . ( 'error' === $check['level'] ? '</strong>' : '' ) . '</li>';
		}
		echo '</ul>';
	}

	/**
	 * Render the form that adds a pair and the table of the pairs there are.
	 *
	 * @param array<int,int> $pairs Vietnamese post ID => English post ID.
	 */
	private static function render_pairs( array $pairs ) {
		echo '<h2>' . esc_html__( 'Add a VI ↔ EN pair', 'nguyen-ha-vi-en' ) . '</h2>';
		echo '<p>' . esc_html__( 'Link two existing posts by ID. The Vietnamese post is never changed. An English post is shown only when both posts are published.', 'nguyen-ha-vi-en' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( NH_VIEN_Links::NONCE_ACTION );
		echo '<input type="hidden" name="action" value="nh_vien_link">';
		echo '<p><label>' . esc_html__( 'Vietnamese post ID', 'nguyen-ha-vi-en' ) . ' <input type="number" name="vi_post_id" min="1" step="1" required></label></p>';
		echo '<p><label>' . esc_html__( 'English post ID', 'nguyen-ha-vi-en' ) . ' <input type="number" name="en_post_id" min="1" step="1" required></label></p>';
		submit_button( __( 'Save pair', 'nguyen-ha-vi-en' ) );
		echo '</form><h2>' . esc_html__( 'Current pairs', 'nguyen-ha-vi-en' ) . '</h2><table class="widefat striped"><thead><tr>';
		foreach ( array( 'Vietnamese post', 'English post', 'State', 'Action' ) as $heading ) {
			echo '<th scope="col">' . esc_html( $heading ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $pairs as $vi => $en ) {
			echo '<tr><td>' . wp_kses_post( self::post_cell( $vi ) ) . '</td><td>' . wp_kses_post( self::post_cell( $en ) ) . '</td><td>' . esc_html( self::state( $vi, $en ) ) . '</td><td>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( NH_VIEN_Links::NONCE_ACTION );
			echo '<input type="hidden" name="action" value="nh_vien_unlink">';
			echo '<input type="hidden" name="vi_post_id" value="' . esc_attr( (string) $vi ) . '">';
			submit_button( __( 'Unlink', 'nguyen-ha-vi-en' ), 'secondary small', 'submit', false );
			echo '</form></td></tr>';
		}
		echo '</tbody></table>';
	}

	/** Manage articles authored directly in English, without requiring a VI/EN pair. */
	private static function render_independent() {
		echo '<h2>' . esc_html__( 'Independent English articles', 'nguyen-ha-vi-en' ) . '</h2>';
		echo '<p>' . esc_html__( 'Create an English draft, register its ID here before publishing, then complete its content. Vietnamese articles are never changed. An English article goes live only when the English routes are on, the article is published and the slug is valid. A post published with English categories only is registered here by itself.', 'nguyen-ha-vi-en' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( NH_VIEN_Links::NONCE_ACTION );
		echo '<input type="hidden" name="action" value="nh_vien_mark_english">';
		echo '<p><label>' . esc_html__( 'English article ID', 'nguyen-ha-vi-en' ) . ' <input type="number" name="en_post_id" min="1" required></label></p>';
		submit_button( __( 'Register English article', 'nguyen-ha-vi-en' ) );
		echo '</form><table class="widefat striped"><thead><tr><th>' . esc_html__( 'English article', 'nguyen-ha-vi-en' ) . '</th><th>' . esc_html__( 'Action', 'nguyen-ha-vi-en' ) . '</th></tr></thead><tbody>';
		foreach ( NH_VIEN_Links::independent_ids() as $id ) {
			echo '<tr><td>' . wp_kses_post( self::post_cell( $id ) ) . '</td><td>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( NH_VIEN_Links::NONCE_ACTION );
			echo '<input type="hidden" name="action" value="nh_vien_unmark_english">';
			echo '<input type="hidden" name="en_post_id" value="' . esc_attr( (string) $id ) . '">';
			submit_button( __( 'Unregister', 'nguyen-ha-vi-en' ), 'secondary small', 'submit', false );
			echo '</form></td></tr>';
		}
		echo '</tbody></table>';
	}

	/** Register an isolated parent/child tree of English WordPress categories. */
	private static function render_english_categories() {
		echo '<h2>' . esc_html__( 'English parent and child categories', 'nguyen-ha-vi-en' ) . '</h2>';
		echo '<p>' . esc_html__( 'First create an empty category in Posts > Categories. Register its ID here BEFORE assigning articles. Register the parent before its children. Existing Vietnamese categories are not modified.', 'nguyen-ha-vi-en' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( NH_VIEN_Links::NONCE_ACTION );
		echo '<input type="hidden" name="action" value="nh_vien_mark_category">';
		echo '<p><label>' . esc_html__( 'English category ID', 'nguyen-ha-vi-en' ) . ' <input type="number" name="en_category_id" min="1" required></label></p>';
		submit_button( __( 'Register English category', 'nguyen-ha-vi-en' ) );
		echo '</form><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Category', 'nguyen-ha-vi-en' ) . '</th><th>' . esc_html__( 'Action', 'nguyen-ha-vi-en' ) . '</th></tr></thead><tbody>';
		foreach ( NH_VIEN_Links::declared_en_terms() as $id ) {
			$term = get_term( $id, 'category' );
			$name = $term instanceof WP_Term ? $term->name : __( 'Missing category', 'nguyen-ha-vi-en' );
			echo '<tr><td>' . esc_html( '#' . $id . ' ' . $name ) . '</td><td>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( NH_VIEN_Links::NONCE_ACTION );
			echo '<input type="hidden" name="action" value="nh_vien_unmark_category">';
			echo '<input type="hidden" name="en_category_id" value="' . esc_attr( (string) $id ) . '">';
			submit_button( __( 'Unregister', 'nguyen-ha-vi-en' ), 'secondary small', 'submit', false );
			echo '</form></td></tr>';
		}
		echo '</tbody></table>';
	}

	/** Manage optional Page-to-Page relationships for equivalent English and Vietnamese pages. */
	private static function render_page_pairs() {
		echo '<h2>' . esc_html__( 'English pages / About, Prices, Contact', 'nguyen-ha-vi-en' ) . '</h2>';
		echo '<p>' . esc_html__( 'Create an English page below the English homepage /en/ using WordPress Pages. Pair with a Vietnamese page only if the pages serve the same intent; the English text may be completely original.', 'nguyen-ha-vi-en' ) . '</p>';
		$pairs = NH_VIEN_Links::page_pairs();
		if ( is_wp_error( $pairs ) ) {
			echo '<p>' . esc_html__( 'Invalid page-pair data. Resolve this before editing pairs.', 'nguyen-ha-vi-en' ) . '</p>';
			return;
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( NH_VIEN_Links::NONCE_ACTION );
		echo '<input type="hidden" name="action" value="nh_vien_link_page">';
		echo '<p><label>' . esc_html__( 'Vietnamese page ID', 'nguyen-ha-vi-en' ) . ' <input type="number" name="vi_page_id" min="1" required></label></p>';
		echo '<p><label>' . esc_html__( 'English page ID (must be under /en/)', 'nguyen-ha-vi-en' ) . ' <input type="number" name="en_page_id" min="1" required></label></p>';
		submit_button( __( 'Link equivalent pages', 'nguyen-ha-vi-en' ) );
		echo '</form><table class="widefat striped"><thead><tr><th>VI</th><th>EN</th><th>' . esc_html__( 'Action', 'nguyen-ha-vi-en' ) . '</th></tr></thead><tbody>';
		foreach ( $pairs as $vi => $en ) {
			echo '<tr><td>' . wp_kses_post( self::post_cell( $vi ) ) . '</td><td>' . wp_kses_post( self::post_cell( $en ) ) . '</td><td>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( NH_VIEN_Links::NONCE_ACTION );
			echo '<input type="hidden" name="action" value="nh_vien_unlink_page">';
			echo '<input type="hidden" name="vi_page_id" value="' . esc_attr( (string) $vi ) . '">';
			submit_button( __( 'Unlink', 'nguyen-ha-vi-en' ), 'secondary small', 'submit', false );
			echo '</form></td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * The ID, title and status of a post, with a link to its edit screen.
	 *
	 * @param int $post_id Post ID.
	 * @return string Markup; every value is escaped.
	 */
	private static function post_cell( $post_id ) {
		$post = get_post( (int) $post_id );
		if ( ! $post instanceof WP_Post ) {
			return esc_html( (string) $post_id . ' (' . __( 'missing', 'nguyen-ha-vi-en' ) . ')' );
		}
		$link = get_edit_post_link( $post->ID );
		$text = esc_html( '#' . $post->ID . ' ' . get_the_title( $post ) . ' (' . $post->post_status . ')' );
		return $link ? '<a href="' . esc_url( $link ) . '">' . $text . '</a>' : $text;
	}

	/**
	 * Why a pair is shown or hidden.
	 *
	 * @param int $vi Vietnamese post ID.
	 * @param int $en English post ID.
	 * @return string
	 */
	private static function state( $vi, $en ) {
		if ( NH_VIEN_Links::is_public_en( $en ) ) {
			return __( 'Shown', 'nguyen-ha-vi-en' );
		}
		if ( 'publish' !== get_post_status( $vi ) ) {
			return __( 'Hidden: the Vietnamese post is not published', 'nguyen-ha-vi-en' );
		}
		if ( 'publish' !== get_post_status( $en ) ) {
			return __( 'Hidden: the English post is not published', 'nguyen-ha-vi-en' );
		}
		$post = get_post( (int) $en );
		if ( $post instanceof WP_Post && ! NH_VIEN_Routes::slug_is_routable( $post->post_name ) ) {
			return __( 'Hidden: the English slug cannot be used below /en/ (letters, digits, hyphen and underscore only; not a reserved word)', 'nguyen-ha-vi-en' );
		}
		return __( 'Hidden: a page already uses this address below /en/', 'nguyen-ha-vi-en' );
	}
}
