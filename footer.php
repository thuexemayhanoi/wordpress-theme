<?php
/**
 * Site footer: up to four columns. A column shows its widgets when it has any, otherwise a sensible
 * default (brand and social, footer menu, popular topics, verified contact details).
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

$nh_editorial_business = nh_editorial_business();
$nh_editorial_social   = nh_editorial_social_links();
$nh_editorial_privacy  = get_privacy_policy_url();
$nh_editorial_english  = class_exists( 'NH_VIEN_Routes', false ) && NH_VIEN_Routes::live() &&
	class_exists( 'NH_VIEN_Context', false ) && NH_VIEN_Context::is_english();
$nh_editorial_home_url = $nh_editorial_english ? NH_VIEN_Routes::url() : home_url( '/' );
$nh_editorial_tagline  = get_bloginfo( 'description' );
$nh_editorial_slots    = array();
for ( $i = 1; $i <= 4; $i++ ) {
	$nh_editorial_slots[ $i ] = $nh_editorial_english ? 'footer-en-' . $i : 'footer-' . $i;
}
$nh_editorial_has_footer_menu = has_nav_menu( 'footer' );
if ( $nh_editorial_english ) {
	$nh_editorial_home_page = NH_VIEN_Routes::home_page();
	$nh_editorial_tagline   = $nh_editorial_home_page ? (string) get_post_field( 'post_excerpt', $nh_editorial_home_page->ID ) : '';
	$nh_editorial_locations = get_nav_menu_locations();
	$nh_editorial_base_menu = ! empty( $nh_editorial_locations['footer'] ) ? wp_get_nav_menu_object( $nh_editorial_locations['footer'] ) : false;
	$nh_editorial_en_menu   = $nh_editorial_base_menu ? wp_get_nav_menu_object( $nh_editorial_base_menu->slug . '-en' ) : false;
	$nh_editorial_has_footer_menu = (bool) $nh_editorial_en_menu;

	// Never link a Vietnamese-only privacy policy from the English footer.
	$nh_editorial_privacy = '';
	$nh_editorial_privacy_id = (int) get_option( 'wp_page_for_privacy_policy' );
	$nh_editorial_privacy_pair = $nh_editorial_privacy_id ? NH_VIEN_Links::for_page( $nh_editorial_privacy_id ) : null;
	if ( $nh_editorial_privacy_pair && 'publish' === get_post_status( $nh_editorial_privacy_pair['en'] ) ) {
		$nh_editorial_privacy = (string) get_permalink( $nh_editorial_privacy_pair['en'] );
	}
}
?>
</main>

<footer id="colophon" class="site-footer">
	<div class="wrap">
		<div class="footer-grid">

			<div class="footer-col footer-col--brand">
				<?php if ( is_active_sidebar( $nh_editorial_slots[ 1 ] ) ) : ?>
					<?php dynamic_sidebar( $nh_editorial_slots[ 1 ] ); ?>
				<?php else : ?>
					<a class="brand brand--footer" href="<?php echo esc_url( $nh_editorial_home_url ); ?>" rel="home">
						<?php echo nh_editorial_brand_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside the helper. ?>
					</a>
					<?php if ( '' !== $nh_editorial_tagline ) : ?>
						<p class="footer-about"><?php echo esc_html( $nh_editorial_tagline ); ?></p>
					<?php endif; ?>
					<?php get_template_part( 'template-parts/components/social-links', null, array( 'links' => $nh_editorial_social ) ); ?>
				<?php endif; ?>
			</div>

			<div class="footer-col footer-col--menu">
				<?php if ( is_active_sidebar( $nh_editorial_slots[ 2 ] ) ) : ?>
					<?php dynamic_sidebar( $nh_editorial_slots[ 2 ] ); ?>
				<?php elseif ( $nh_editorial_has_footer_menu ) : ?>
					<h2 class="footer-title"><?php esc_html_e( 'Explore', 'nguyen-ha-editorial' ); ?></h2>
					<nav class="footer-menu" aria-label="<?php esc_attr_e( 'Footer', 'nguyen-ha-editorial' ); ?>">
						<?php
						wp_nav_menu(
							array(
								'theme_location' => 'footer',
								'container'      => false,
								'menu_class'     => 'footer-menu__list',
								'depth'          => 1,
							)
						);
						?>
					</nav>
				<?php endif; ?>
			</div>

			<div class="footer-col footer-col--topics">
				<?php if ( is_active_sidebar( $nh_editorial_slots[ 3 ] ) ) : ?>
					<?php dynamic_sidebar( $nh_editorial_slots[ 3 ] ); ?>
				<?php else : ?>
					<?php $nh_editorial_topics = nh_editorial_get_hubs( 6 ); ?>
					<?php if ( ! empty( $nh_editorial_topics ) ) : ?>
						<h2 class="footer-title"><?php esc_html_e( 'Topics', 'nguyen-ha-editorial' ); ?></h2>
						<ul class="footer-list">
							<?php foreach ( $nh_editorial_topics as $nh_editorial_topic ) : ?>
								<li><a href="<?php echo esc_url( $nh_editorial_topic['url'] ); ?>"><?php echo esc_html( $nh_editorial_topic['name'] ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				<?php endif; ?>
			</div>

			<div class="footer-col footer-col--contact">
				<?php if ( is_active_sidebar( $nh_editorial_slots[ 4 ] ) ) : ?>
					<?php dynamic_sidebar( $nh_editorial_slots[ 4 ] ); ?>
				<?php elseif ( nh_editorial_has_business_contact() ) : ?>
					<?php get_template_part( 'template-parts/components/contact', null, array( 'business' => $nh_editorial_business ) ); ?>
				<?php endif; ?>
			</div>

		</div>

		<div class="footer-bottom">
			<p class="footer-copy">
				&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php echo esc_html( get_bloginfo( 'name' ) ); ?>.
				<?php esc_html_e( 'All rights reserved.', 'nguyen-ha-editorial' ); ?>
			</p>
			<?php if ( ! $nh_editorial_english && '' !== (string) nh_editorial_option( 'nh_footer_text' ) ) : ?>
				<p class="footer-note"><?php echo wp_kses( (string) nh_editorial_option( 'nh_footer_text' ), nh_editorial_footer_kses(), nh_editorial_link_protocols() ); ?></p>
			<?php endif; ?>
			<?php if ( $nh_editorial_privacy ) : ?>
				<p class="footer-legal"><a href="<?php echo esc_url( $nh_editorial_privacy ); ?>"><?php esc_html_e( 'Privacy Policy', 'nguyen-ha-editorial' ); ?></a></p>
			<?php endif; ?>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
