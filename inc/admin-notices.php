<?php
/**
 * Admin notice recommending the companion plugin. Dismissible per user; never blocks anything.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the notice should be shown on the current admin screen.
 *
 * @return bool
 */
function nh_editorial_companion_notice_visible() {
	if ( nh_editorial_has_companion() || ! current_user_can( 'manage_options' ) ) {
		return false;
	}
	if ( get_user_meta( get_current_user_id(), 'nh_editorial_companion_notice_dismissed', true ) ) {
		return false;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	return $screen && in_array( $screen->id, array( 'dashboard', 'themes', 'plugins' ), true );
}

/**
 * Print the notice.
 *
 * @return void
 */
function nh_editorial_companion_notice() {
	if ( ! nh_editorial_companion_notice_visible() ) {
		return;
	}

	$dismiss = wp_nonce_url( add_query_arg( 'nh_editorial_dismiss_companion', '1' ), 'nh_editorial_dismiss_companion' );
	?>
	<div class="notice notice-info">
		<p>
			<strong><?php esc_html_e( 'Nguyen Ha Editorial:', 'nguyen-ha-editorial' ); ?></strong>
			<?php esc_html_e( 'the theme works on its own. Install and activate the "Nguyen Ha SEO Companion" plugin to edit summaries, FAQs and sources, manage Topic Clusters and the business profile, add structured data extras and use the fast internal search index.', 'nguyen-ha-editorial' ); ?>
			<a href="<?php echo esc_url( $dismiss ); ?>"><?php esc_html_e( 'Dismiss', 'nguyen-ha-editorial' ); ?></a>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'nh_editorial_companion_notice' );

/**
 * Handle the dismiss link (nonce and capability checked).
 *
 * @return void
 */
function nh_editorial_companion_notice_dismiss() {
	if ( ! isset( $_GET['nh_editorial_dismiss_companion'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'nh_editorial_dismiss_companion' ) ) {
		return;
	}
	update_user_meta( get_current_user_id(), 'nh_editorial_companion_notice_dismissed', 1 );
	wp_safe_redirect( remove_query_arg( array( 'nh_editorial_dismiss_companion', '_wpnonce' ) ) );
	exit;
}
add_action( 'admin_init', 'nh_editorial_companion_notice_dismiss' );
