<?php
/**
 * Admin notices that survive a redirect (term and profile screens save and redirect).
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * A tiny per-user queue of plain-text notices, stored in a short-lived transient and shown once on the
 * next plugin-related admin screen. Plain text only: messages are escaped on output.
 */
final class NH_SEO_Admin_Notices {

	/**
	 * Transient name prefix.
	 *
	 * @var string
	 */
	const TRANSIENT_PREFIX = 'nh_seo_notices_';

	/**
	 * Notice types WordPress styles.
	 *
	 * @var string[]
	 */
	const TYPES = array( 'error', 'warning', 'success', 'info' );

	/**
	 * Queue a notice for the current user.
	 *
	 * @param string $message Plain text message.
	 * @param string $type    error | warning | success | info.
	 * @return void
	 */
	public static function add( $message, $type = 'warning' ) {
		$user_id = get_current_user_id();
		$message = trim( wp_strip_all_tags( (string) $message ) );
		if ( ! $user_id || '' === $message ) {
			return;
		}

		$queue = get_transient( self::TRANSIENT_PREFIX . $user_id );
		$queue = is_array( $queue ) ? $queue : array();

		$queue[] = array(
			'type'    => in_array( $type, self::TYPES, true ) ? $type : 'warning',
			'message' => $message,
		);
		set_transient( self::TRANSIENT_PREFIX . $user_id, array_slice( $queue, -10 ), 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Print and clear the queued notices of the current user, on plugin-related screens only.
	 *
	 * @return void
	 */
	public static function render() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( '' === NH_SEO_Admin_Assets::context( $screen, $screen instanceof WP_Screen ? (string) $screen->id : '' ) ) {
			return;
		}

		$queue = get_transient( self::TRANSIENT_PREFIX . $user_id );
		if ( empty( $queue ) || ! is_array( $queue ) ) {
			return;
		}
		delete_transient( self::TRANSIENT_PREFIX . $user_id );

		foreach ( $queue as $notice ) {
			if ( empty( $notice['message'] ) ) {
				continue;
			}
			$type = isset( $notice['type'] ) && in_array( $notice['type'], self::TYPES, true ) ? $notice['type'] : 'warning';
			echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $notice['message'] ) . '</p></div>';
		}
	}
}
