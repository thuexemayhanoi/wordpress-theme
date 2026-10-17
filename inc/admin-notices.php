<?php
/**
 * Admin notices: a recommendation for the companion plugin and a reminder about the homepage headline.
 * Both are dismissible per user and never block anything.
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

/**
 * Whether the reminder about the homepage headline should be shown on the current admin screen.
 *
 * Until the owner types a headline, the homepage H1 and introduction come from the site title and
 * tagline. A site that has an approved or protected homepage H1 (for example one guarded against SEO
 * changes) must set the same text here before the theme goes live.
 *
 * @return bool
 */
function nh_editorial_headline_notice_visible() {
	if ( '' !== (string) nh_editorial_option( 'nh_home_headline' ) || ! current_user_can( 'edit_theme_options' ) ) {
		return false;
	}
	if ( get_user_meta( get_current_user_id(), 'nh_editorial_headline_notice_dismissed', true ) ) {
		return false;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	return $screen && in_array( $screen->id, array( 'dashboard', 'themes' ), true );
}

/**
 * Print the reminder.
 *
 * @return void
 */
function nh_editorial_headline_notice() {
	if ( ! nh_editorial_headline_notice_visible() ) {
		return;
	}

	$dismiss   = wp_nonce_url( add_query_arg( 'nh_editorial_dismiss_headline', '1' ), 'nh_editorial_dismiss_headline' );
	$customize = add_query_arg( array( 'autofocus[control]' => 'nh_home_headline' ), admin_url( 'customize.php' ) );
	?>
	<div class="notice notice-warning">
		<p>
			<strong><?php esc_html_e( 'Nguyen Ha Editorial:', 'nguyen-ha-editorial' ); ?></strong>
			<?php esc_html_e( 'the homepage headline (H1) and introduction currently come from the site title and tagline. If your homepage has an approved headline that must not change, enter the same text under Appearance, Customize, Homepage before the site goes live.', 'nguyen-ha-editorial' ); ?>
			<a href="<?php echo esc_url( $customize ); ?>"><?php esc_html_e( 'Open the Customizer', 'nguyen-ha-editorial' ); ?></a>
			<a href="<?php echo esc_url( $dismiss ); ?>"><?php esc_html_e( 'Dismiss', 'nguyen-ha-editorial' ); ?></a>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'nh_editorial_headline_notice' );

/**
 * Handle the dismiss link of the headline reminder (nonce and capability checked).
 *
 * @return void
 */
function nh_editorial_headline_notice_dismiss() {
	if ( ! isset( $_GET['nh_editorial_dismiss_headline'] ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_theme_options' ) || ! check_admin_referer( 'nh_editorial_dismiss_headline' ) ) {
		return;
	}
	update_user_meta( get_current_user_id(), 'nh_editorial_headline_notice_dismissed', 1 );
	wp_safe_redirect( remove_query_arg( array( 'nh_editorial_dismiss_headline', '_wpnonce' ) ) );
	exit;
}
add_action( 'admin_init', 'nh_editorial_headline_notice_dismiss' );
