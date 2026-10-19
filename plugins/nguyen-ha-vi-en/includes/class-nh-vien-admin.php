<?php
/** Explicit Tools > VI/EN Links interface; no automatic pair creation. */

defined( 'ABSPATH' ) || exit;

final class NH_VIEN_Admin {
	/** Register admin-only actions. */
	public static function boot() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_nh_vien_link', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_nh_vien_unlink', array( __CLASS__, 'remove' ) );
	}

	public static function menu() {
		add_management_page( 'VI/EN Translation Links', 'VI/EN Links', 'manage_options', 'nh-vien-links', array( __CLASS__, 'render' ) );
	}

	public static function save() {
		$nonce = isset( $_POST['_wpnonce'] ) && is_string( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		$vi    = isset( $_POST['vi_post_id'] ) && is_string( $_POST['vi_post_id'] ) ? wp_unslash( $_POST['vi_post_id'] ) : '';
		$en    = isset( $_POST['en_post_id'] ) && is_string( $_POST['en_post_id'] ) ? wp_unslash( $_POST['en_post_id'] ) : '';
		$result = NH_VIEN_Links::link( $vi, $en, $nonce );
		self::redirect( $result );
	}

	public static function remove() {
		$nonce = isset( $_POST['_wpnonce'] ) && is_string( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		$vi    = isset( $_POST['vi_post_id'] ) && is_string( $_POST['vi_post_id'] ) ? wp_unslash( $_POST['vi_post_id'] ) : '';
		$result = NH_VIEN_Links::unlink( $vi, $nonce );
		self::redirect( $result );
	}

	private static function redirect( $result ) {
		$status = is_wp_error( $result ) ? $result->get_error_code() : 'saved';
		$allowed = array( 'saved', 'nh_vien_forbidden', 'nh_vien_bad_nonce', 'nh_vien_invalid_ids', 'nh_vien_invalid_posts', 'nh_vien_invalid_store', 'nh_vien_conflict', 'nh_vien_not_found', 'nh_vien_save_failed' );
		if ( ! in_array( $status, $allowed, true ) ) {
			$status = 'nh_vien_save_failed';
		}
		wp_safe_redirect( add_query_arg( 'nh_vien_status', $status, admin_url( 'tools.php?page=nh-vien-links' ) ) );
		exit;
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Administrator permission required.', 'nguyen-ha-vi-en' ) );
		}
		$pairs = NH_VIEN_Links::all();
		echo '<div class="wrap"><h1>VI/EN Translation Links</h1>';
		echo '<p>Connect two existing WordPress posts by ID. This never edits their content, URLs, SEO fields, or language settings. No public URLs or hreflang are enabled yet.</p>';
		if ( isset( $_GET['nh_vien_status'] ) && is_string( $_GET['nh_vien_status'] ) ) {
			$status = sanitize_key( wp_unslash( $_GET['nh_vien_status'] ) );
			if ( 'saved' === $status ) {
				echo '<div class="notice notice-success"><p>Translation link operation completed.</p></div>';
			} else {
				echo '<div class="notice notice-error"><p>Link was not changed. Verify permissions, IDs, existing links and nonce.</p></div>';
			}
		}
		if ( is_wp_error( $pairs ) ) {
			echo '<p>Translation map is invalid. No changes allowed until repaired.</p></div>';
			return;
		}
		echo '<h2>Add a VI ↔ EN pair</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( NH_VIEN_Links::NONCE_ACTION );
		echo '<input type="hidden" name="action" value="nh_vien_link">';
		echo '<p><label>Vietnamese post ID <input type="number" name="vi_post_id" min="1" step="1" required></label></p>';
		echo '<p><label>English post ID <input type="number" name="en_post_id" min="1" step="1" required></label></p>';
		submit_button( 'Save pair' );
		echo '</form><h2>Current pairs</h2><table class="widefat striped"><thead><tr><th>Vietnamese post ID</th><th>English post ID</th><th>Action</th></tr></thead><tbody>';
		foreach ( $pairs as $vi => $en ) {
			echo '<tr><td>' . esc_html( (string) $vi ) . '</td><td>' . esc_html( (string) $en ) . '</td><td>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( NH_VIEN_Links::NONCE_ACTION );
			echo '<input type="hidden" name="action" value="nh_vien_unlink">';
			echo '<input type="hidden" name="vi_post_id" value="' . esc_attr( (string) $vi ) . '">';
			submit_button( 'Unlink', 'secondary small', 'submit', false );
			echo '</form></td></tr>';
		}
		echo '</tbody></table></div>';
	}
}
