<?php
/**
 * Tools > VI/EN Links. Every action is initiated by an administrator, with a nonce, and changes nothing but the
 * plugin's own pair list, the stored rewrite rules, or a new draft page.
 *
 * @package Nguyen_Ha_VI_EN
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tools panel: pairs, status of the English routes, and the two maintenance buttons.
 */
final class NH_VIEN_Admin {
	const PAGE        = 'nh-vien-links';
	const FLUSH_NONCE = 'nh_vien_flush_rules';
	const HOME_NONCE  = 'nh_vien_create_home_page';
	const TOGGLE_NONCE = 'nh_vien_toggle_routes';

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
	}

	/** Add the Tools navigation entry. */
	public static function menu() {
		add_management_page( 'VI/EN Translation Links', 'VI/EN Links', 'manage_options', self::PAGE, array( __CLASS__, 'render' ) );
	}


	/**
	 * Switch English routing on/off without requiring wp-config.php edits.
	 * This form has a per-action nonce and is restricted to administrators.
	 * If the rewrite refresh fails, the original setting is restored.
	 *
	 * @return void
	 */
	public static function toggle_routes() {
		self::authorize( self::TOGGLE_NONCE );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
		$action = isset( $_POST['nh_vien_mode'] ) && is_string( $_POST['nh_vien_mode'] ) ? sanitize_key( wp_unslash( $_POST['nh_vien_mode'] ) ) : '';
		if ( ! in_array( $action, array( 'enable', 'disable' ), true ) ) {
			self::redirect( 'nh_vien_invalid_mode' );
		}
		$enable = 'enable' === $action;
		if ( $enable && NH_VIEN_Routes::post_at_en() ) {
			self::redirect( 'address_taken' );
		}
		if ( $enable && ! get_option( 'permalink_structure' ) ) {
			self::redirect( 'nh_vien_pretty_links_required' );
		}
		$previous = get_option( NH_VIEN_Routes::CONTROL_OPTION, null );
		$value    = $enable ? '1' : '0';
		if ( $previous !== $value && ! update_option( NH_VIEN_Routes::CONTROL_OPTION, $value, false ) ) {
			self::redirect( 'nh_vien_save_failed' );
		}

		if ( $enable ) {
			// Admin-post is invoked after init, so explicitly register the rules
			// before rebuilding the WordPress rewrite option on this request.
			NH_VIEN_Routes::register();
		} else {
			// The rules could already have been registered on this same request
			// before the user pressed Disable. Remove only ours from WP_Rewrite.
			NH_VIEN_Routes::clear_registered_rules();
		}
		flush_rewrite_rules( false );
		NH_VIEN_Routes::reset();
		$valid = $enable ? NH_VIEN_Routes::rules_stored() : ! NH_VIEN_Routes::rules_stored();
		if ( ! $valid ) {
			if ( null === $previous ) {
				delete_option( NH_VIEN_Routes::CONTROL_OPTION );
			} else {
				update_option( NH_VIEN_Routes::CONTROL_OPTION, $previous, false );
			}
			if ( NH_VIEN_Routes::enabled() ) {
				NH_VIEN_Routes::register();
			} else {
				NH_VIEN_Routes::clear_registered_rules();
			}
			flush_rewrite_rules( false );
			NH_VIEN_Routes::reset();
			self::redirect( 'nh_vien_rewrite_failed' );
		}
		do_action( 'nh_vien_pairs_changed', 0, 0 );
		self::redirect( $enable ? 'routes_enabled' : 'routes_disabled' );
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
	 * This is the only place where the plugin writes the rewrite rules.
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
			'routes_enabled'            => array( 'notice-success', __( 'English routing is enabled. Check the English homepage, menu and SEO.', 'nguyen-ha-vi-en' ) ),
			'routes_disabled'           => array( 'notice-success', __( 'English routing is off. Published WordPress pages can still be reached at their WordPress URLs.', 'nguyen-ha-vi-en' ) ),
			'nh_vien_invalid_mode'      => array( 'notice-error', __( 'Invalid English routing action.', 'nguyen-ha-vi-en' ) ),
			'nh_vien_pretty_links_required' => array( 'notice-error', __( 'Pretty permalinks are required. Set these under Settings > Permalinks.', 'nguyen-ha-vi-en' ) ),
			'nh_vien_rewrite_failed'    => array( 'notice-error', __( 'Could not verify English rewrite rules; the previous setting was restored.', 'nguyen-ha-vi-en' ) ),
			'address_taken'             => array( 'notice-error', __( 'Nothing was changed: a published post already has the address /en/. Give that post another slug first, or the English home would take its address away.', 'nguyen-ha-vi-en' ) ),
			'home_exists'               => array( 'notice-warning', __( 'A page with the address /en/ already exists; it was left as it is.', 'nguyen-ha-vi-en' ) ),
			'home_failed'               => array( 'notice-error', __( 'The English home page could not be created.', 'nguyen-ha-vi-en' ) ),
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
			echo esc_html__( 'VI/EN: the English routes are switched on, but their rewrite rules are not stored yet. Open Tools > VI/EN Links and press "Store the rewrite rules".', 'nguyen-ha-vi-en' );
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

	/** Render the status of the English routes and the two maintenance buttons. */
	private static function render_status() {
		$enabled = NH_VIEN_Routes::enabled();
		$stored  = NH_VIEN_Routes::rules_stored();
		$page    = NH_VIEN_Routes::home_page();
		$yes     = __( 'Yes', 'nguyen-ha-vi-en' );
		$no      = __( 'No', 'nguyen-ha-vi-en' );
		$home    = $page
			? __( 'A published page at /en/', 'nguyen-ha-vi-en' )
			: __( 'The list of English posts (create a page with the address /en/ to write your own home page)', 'nguyen-ha-vi-en' );
		$rows    = array(
			array( __( 'Switched on by configuration', 'nguyen-ha-vi-en' ), $enabled ? $yes : $no . ' (' . __( 'use the enable button below', 'nguyen-ha-vi-en' ) . ')' ),
			array( __( 'Environment type', 'nguyen-ha-vi-en' ), function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : '?' ),
			array( __( 'Rewrite rules stored', 'nguyen-ha-vi-en' ), $stored ? $yes : $no ),
			array( __( 'English pages are live', 'nguyen-ha-vi-en' ), $enabled && $stored ? $yes : $no ),
			array( __( 'English home', 'nguyen-ha-vi-en' ), $home ),
			array( __( 'Address /en/ used by a post', 'nguyen-ha-vi-en' ), NH_VIEN_Routes::post_at_en() ? __( 'Yes: the rules cannot be stored until that post has another slug', 'nguyen-ha-vi-en' ) : $no ),
			array( __( 'Yoast SEO active', 'nguyen-ha-vi-en' ), defined( 'WPSEO_VERSION' ) ? $yes : $no ),
			array( __( 'Pretty permalinks', 'nguyen-ha-vi-en' ), get_option( 'permalink_structure' ) ? $yes : $no ),
		);
		echo '<h2>' . esc_html__( 'Status', 'nguyen-ha-vi-en' ) . '</h2><table class="widefat striped" style="max-width:52rem"><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr><th scope="row">' . esc_html( $row[0] ) . '</th><td>' . esc_html( $row[1] ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p>' . esc_html__( 'Manage the English section from WordPress without editing hosting files. Saving a new status refreshes the URL rules automatically; existing Vietnamese pages and posts are untouched.', 'nguyen-ha-vi-en' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:1rem 0">';
		wp_nonce_field( self::TOGGLE_NONCE );
		echo '<input type="hidden" name="action" value="nh_vien_toggle">';
		echo '<input type="hidden" name="nh_vien_mode" value="' . esc_attr( $enabled ? 'disable' : 'enable' ) . '">';
		submit_button( $enabled ? __( 'Disable English routing', 'nguyen-ha-vi-en' ) : __( 'Enable English routing', 'nguyen-ha-vi-en' ), $enabled ? 'secondary' : 'primary', 'submit', false );
		echo '</form>';
		echo '<p>' . esc_html__( 'Back up WordPress first and check the English homepage, menus, categories, Yoast sitemap and Vietnamese content. Disabling English routing does not unpublish independently created WordPress pages.', 'nguyen-ha-vi-en' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-right:1rem">';
		wp_nonce_field( self::FLUSH_NONCE );
		echo '<input type="hidden" name="action" value="nh_vien_flush">';
		submit_button( __( 'Store the rewrite rules', 'nguyen-ha-vi-en' ), 'primary', 'submit', false );
		echo '</form>';
		if ( ! get_page_by_path( 'en', OBJECT, 'page' ) ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block">';
			wp_nonce_field( self::HOME_NONCE );
			echo '<input type="hidden" name="action" value="nh_vien_home_page">';
			submit_button( __( 'Create a draft English home page', 'nguyen-ha-vi-en' ), 'secondary', 'submit', false );
			echo '</form>';
		}
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
		echo '<p>' . esc_html__( 'Create an English draft, register its ID here before publishing, then complete its content. Vietnamese articles are never changed. An English article goes live only when the language routes are enabled, the article is published and the slug is valid.', 'nguyen-ha-vi-en' ) . '</p>';
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
