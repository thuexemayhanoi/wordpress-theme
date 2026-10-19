<?php
/**
 * The switch of the English side: on and off from the administration (Tools > VI/EN Links) or through the REST API,
 * stored with the Options API, with a preflight before switching on, a smoke test after it, and a way back when either
 * fails. No constant, wp-config.php line or hosting access is needed.
 *
 * @package Nguyen_Ha_VI_EN
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enable, disable, undo and report the state of the English routes.
 */
final class NH_VIEN_Settings {
	const LAST_CHANGE_OPTION = 'nh_vien_last_change_v1';

	/**
	 * Switch the English routes on: preflight, setting, rewrite rules, smoke test; back to the previous state when the
	 * rules cannot be stored or the site does not answer /en/ in English afterwards.
	 *
	 * @param bool $confirmed Whether the administrator confirmed the switch on a production site.
	 * @return array{enabled:bool,smoke:array,checks:array}|WP_Error
	 */
	public static function enable( $confirmed = false ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return self::error( 'nh_vien_forbidden', __( 'Administrator permission required.', 'nguyen-ha-vi-en' ), 403 );
		}
		if ( 'constant_off' === NH_VIEN_Routes::source() ) {
			return self::error( 'nh_vien_constant_off', __( 'Nothing was changed: wp-config.php defines NH_VIEN_ENABLE_EN_ROUTES as false, which keeps the English side off until that line is removed.', 'nguyen-ha-vi-en' ), 409 );
		}
		if ( self::is_production() && ! $confirmed ) {
			return self::error( 'nh_vien_confirm_required', __( 'Nothing was changed: this is the live site, so the switch needs the confirmation that a fresh backup exists and that the English pages were checked.', 'nguyen-ha-vi-en' ), 400 );
		}
		$checks = NH_VIEN_Routes::preflight();
		if ( ! NH_VIEN_Routes::preflight_passed( $checks ) ) {
			return self::error( 'nh_vien_preflight_failed', __( 'Nothing was changed: the preflight found a conflict; see the list of checks.', 'nguyen-ha-vi-en' ), 409, array( 'checks' => $checks ) );
		}
		$snapshot = self::snapshot();
		if ( ! self::apply_setting( '1' ) ) {
			return self::error( 'nh_vien_save_failed', __( 'The setting could not be saved.', 'nguyen-ha-vi-en' ), 500 );
		}
		NH_VIEN_Routes::reset();
		// The request that switches the routes on runs after init: register the rules now, then store them once.
		NH_VIEN_Routes::register();
		flush_rewrite_rules( false );
		NH_VIEN_Routes::reset();
		if ( ! NH_VIEN_Routes::rules_stored() ) {
			self::restore( $snapshot );
			return self::error( 'nh_vien_rewrite_failed', __( 'The rewrite rules could not be stored; the previous state was restored.', 'nguyen-ha-vi-en' ), 500 );
		}
		$smoke = self::smoke_test();
		if ( 'failed' === $smoke['result'] ) {
			self::restore( $snapshot );
			return self::error( 'nh_vien_smoke_failed', $smoke['message'], 500, array( 'smoke' => $smoke ) );
		}
		self::remember( 'enable', $snapshot );
		do_action( 'nh_vien_pairs_changed', 0, 0 );
		return array(
			'enabled' => true,
			'smoke'   => $smoke,
			'checks'  => $checks,
		);
	}

	/**
	 * Switch the English routes off: the setting, then this plugin's own rules are taken out of the stored rewrite rules
	 * (every other stored rule stays as it is). Published English pages keep their WordPress addresses.
	 *
	 * @return array{enabled:bool,rules_stored:bool}|WP_Error
	 */
	public static function disable() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return self::error( 'nh_vien_forbidden', __( 'Administrator permission required.', 'nguyen-ha-vi-en' ), 403 );
		}
		$snapshot = self::snapshot();
		if ( ! self::apply_setting( '0' ) ) {
			return self::error( 'nh_vien_save_failed', __( 'The setting could not be saved.', 'nguyen-ha-vi-en' ), 500 );
		}
		NH_VIEN_Routes::reset();
		NH_VIEN_Routes::clear_registered_rules();
		NH_VIEN_Routes::remove_stored_rules();
		NH_VIEN_Routes::reset();
		if ( NH_VIEN_Routes::rules_stored() ) {
			// The surgical removal did not take: regenerate the rules without ours.
			flush_rewrite_rules( false );
			NH_VIEN_Routes::reset();
		}
		self::remember( 'disable', $snapshot );
		do_action( 'nh_vien_pairs_changed', 0, 0 );
		if ( NH_VIEN_Routes::rules_stored() ) {
			// The gate is closed, so a stale stored rule answers 404 and nothing English is shown; still, say so.
			return self::error( 'nh_vien_rewrite_failed', __( 'The English routes are off, but the stored rewrite rules could not be refreshed: press "Store the rewrite rules".', 'nguyen-ha-vi-en' ), 500 );
		}
		return array(
			'enabled'      => false,
			'rules_stored' => false,
		);
	}

	/**
	 * Back to the state before the last switch.
	 *
	 * @return array{enabled:bool}|WP_Error
	 */
	public static function undo() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return self::error( 'nh_vien_forbidden', __( 'Administrator permission required.', 'nguyen-ha-vi-en' ), 403 );
		}
		$change = self::last_change();
		if ( ! $change ) {
			return self::error( 'nh_vien_nothing_to_undo', __( 'There is no change to undo.', 'nguyen-ha-vi-en' ), 404 );
		}
		self::restore( $change['previous'] );
		delete_option( self::LAST_CHANGE_OPTION );
		do_action( 'nh_vien_pairs_changed', 0, 0 );
		return array( 'enabled' => NH_VIEN_Routes::enabled() );
	}

	/**
	 * Ask the site itself for /en/: a request from the server to the server, judged by the marker header the English
	 * side adds to every answer and by the status.
	 *
	 * @return array{result:string,status:int,url:string,message:string} result is ok, failed or inconclusive.
	 */
	public static function smoke_test() {
		$url      = add_query_arg( 'nh_vien_probe', (string) time(), NH_VIEN_Routes::url() );
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 10,
				'redirection' => 0,
				'sslverify'   => apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook: WordPress applies it to its own loopback requests.
				'headers'     => array(
					'Cache-Control' => 'no-cache',
					'Pragma'        => 'no-cache',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return self::smoke(
				'inconclusive',
				0,
				$url,
				sprintf(
				/* translators: 1: address, 2: error message. */
					__( 'The server could not request %1$s itself (%2$s). The English routes are on: open the address in a browser to check it.', 'nguyen-ha-vi-en' ),
					$url,
					$response->get_error_message()
				)
			);
		}
		$status  = (int) wp_remote_retrieve_response_code( $response );
		$marker  = strtolower( (string) wp_remote_retrieve_header( $response, strtolower( NH_VIEN_Routes::MARKER_HEADER ) ) );
		$expects = NH_VIEN_Routes::home_page() || NH_VIEN_Links::public_en_ids() ? array( 200 ) : array( 200, 404 );
		if ( 'en' === $marker && in_array( $status, $expects, true ) ) {
			return self::smoke(
				'ok',
				$status,
				$url,
				sprintf(
				/* translators: 1: address, 2: HTTP status. */
					__( '%1$s answered in English (HTTP %2$d).', 'nguyen-ha-vi-en' ),
					$url,
					$status
				)
			);
		}
		if ( 'en' === $marker ) {
			return self::smoke(
				'failed',
				$status,
				$url,
				sprintf(
				/* translators: 1: address, 2: HTTP status. */
					__( '%1$s answered HTTP %2$d instead of 200, so the previous state was restored. Publish the English home page (or an English post) and try again.', 'nguyen-ha-vi-en' ),
					$url,
					$status
				)
			);
		}
		if ( in_array( $status, array( 200, 404 ), true ) ) {
			return self::smoke(
				'failed',
				$status,
				$url,
				sprintf(
				/* translators: 1: address, 2: HTTP status. */
					__( '%1$s was answered (HTTP %2$d) without this plugin: a page cache or another rewrite rule answered instead, so the previous state was restored. Purge the page cache and try again.', 'nguyen-ha-vi-en' ),
					$url,
					$status
				)
			);
		}
		return self::smoke(
			'inconclusive',
			$status,
			$url,
			sprintf(
			/* translators: 1: address, 2: HTTP status. */
				__( 'The server answered HTTP %2$d to its own request for %1$s (a firewall, a redirect or a protection plugin may be in the way). The English routes are on: open the address in a browser to check it.', 'nguyen-ha-vi-en' ),
				$url,
				$status
			)
		);
	}

	/**
	 * The state of the English side, for the Tools screen and the REST API.
	 *
	 * @return array<string,mixed>
	 */
	public static function status() {
		$pairs      = NH_VIEN_Links::all();
		$page_pairs = NH_VIEN_Links::page_pairs();
		$home       = NH_VIEN_Routes::home_page();
		return array(
			'version'            => NH_VIEN_VERSION,
			'enabled'            => NH_VIEN_Routes::enabled(),
			'source'             => NH_VIEN_Routes::source(),
			'environment'        => function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : '',
			'rules_stored'       => NH_VIEN_Routes::rules_stored(),
			'live'               => NH_VIEN_Routes::live(),
			'home_page'          => $home ? (int) $home->ID : 0,
			'home_url'           => NH_VIEN_Routes::url(),
			'pretty_permalinks'  => (bool) get_option( 'permalink_structure' ),
			'yoast'              => defined( 'WPSEO_VERSION' ),
			'pairs'              => is_wp_error( $pairs ) ? array() : $pairs,
			'independent_posts'  => NH_VIEN_Links::independent_ids(),
			'english_categories' => NH_VIEN_Links::declared_en_terms(),
			'page_pairs'         => is_wp_error( $page_pairs ) ? array() : $page_pairs,
			'public_en_posts'    => NH_VIEN_Links::public_en_ids(),
			'preflight'          => NH_VIEN_Routes::preflight(),
			'last_change'        => self::last_change(),
		);
	}

	/**
	 * The last switch made from the Tools screen or the REST API, if it can still be undone.
	 *
	 * @return array{action:string,previous:array{setting:string|null,rules_stored:bool},at:int,user:int}|null
	 */
	public static function last_change() {
		$change = get_option( self::LAST_CHANGE_OPTION, null );
		if ( ! is_array( $change ) || ! isset( $change['previous'] ) || ! is_array( $change['previous'] ) || ! array_key_exists( 'setting', $change['previous'] ) ) {
			return null;
		}
		return array(
			'action'   => isset( $change['action'] ) ? (string) $change['action'] : '',
			'previous' => array(
				'setting'      => $change['previous']['setting'],
				'rules_stored' => ! empty( $change['previous']['rules_stored'] ),
			),
			'at'       => isset( $change['at'] ) ? (int) $change['at'] : 0,
			'user'     => isset( $change['user'] ) ? (int) $change['user'] : 0,
		);
	}

	/**
	 * What a switch may have to go back to.
	 *
	 * @return array{setting:string|null,rules_stored:bool}
	 */
	private static function snapshot() {
		$setting = get_option( NH_VIEN_Routes::CONTROL_OPTION, null );
		return array(
			'setting'      => in_array( $setting, array( '0', '1' ), true ) ? $setting : null,
			'rules_stored' => NH_VIEN_Routes::rules_stored(),
		);
	}

	/**
	 * Go back to a snapshot: the setting, and the rewrite rules that go with it.
	 *
	 * @param array{setting:string|null,rules_stored:bool} $snapshot The state to go back to.
	 */
	private static function restore( array $snapshot ) {
		if ( null === $snapshot['setting'] ) {
			delete_option( NH_VIEN_Routes::CONTROL_OPTION );
		} else {
			self::apply_setting( $snapshot['setting'] );
		}
		NH_VIEN_Routes::reset();
		if ( NH_VIEN_Routes::enabled() ) {
			NH_VIEN_Routes::register();
			flush_rewrite_rules( false );
		} else {
			NH_VIEN_Routes::clear_registered_rules();
			NH_VIEN_Routes::remove_stored_rules();
		}
		NH_VIEN_Routes::reset();
	}

	/**
	 * Save the setting. It is read on every request, so it is autoloaded.
	 *
	 * @param string $value '1' or '0'.
	 * @return bool Whether the setting has that value now.
	 */
	private static function apply_setting( $value ) {
		if ( get_option( NH_VIEN_Routes::CONTROL_OPTION, null ) === $value ) {
			return true;
		}
		return (bool) update_option( NH_VIEN_Routes::CONTROL_OPTION, $value, true );
	}

	/**
	 * Keep what the last switch replaced, so that it can be undone.
	 *
	 * @param string                                       $action   enable or disable.
	 * @param array{setting:string|null,rules_stored:bool} $snapshot The state before the switch.
	 */
	private static function remember( $action, array $snapshot ) {
		update_option(
			self::LAST_CHANGE_OPTION,
			array(
				'action'   => $action,
				'previous' => $snapshot,
				'at'       => time(),
				'user'     => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0,
			),
			false
		);
	}

	/**
	 * Whether this is the live site (WordPress environment type production).
	 *
	 * @return bool
	 */
	private static function is_production() {
		return ! function_exists( 'wp_get_environment_type' ) || 'production' === wp_get_environment_type();
	}

	/**
	 * One smoke test outcome.
	 *
	 * @param string $result  ok, failed or inconclusive.
	 * @param int    $status  HTTP status, 0 when there was no answer.
	 * @param string $url     The address that was requested.
	 * @param string $message What happened, for the administrator.
	 * @return array{result:string,status:int,url:string,message:string}
	 */
	private static function smoke( $result, $status, $url, $message ) {
		return array(
			'result'  => $result,
			'status'  => (int) $status,
			'url'     => $url,
			'message' => $message,
		);
	}

	/**
	 * An error with the HTTP status the REST API answers with.
	 *
	 * @param string $code    Error code.
	 * @param string $message Message.
	 * @param int    $status  HTTP status.
	 * @param array  $data    Extra data.
	 * @return WP_Error
	 */
	private static function error( $code, $message, $status, array $data = array() ) {
		return new WP_Error( $code, $message, array_merge( $data, array( 'status' => $status ) ) );
	}
}
