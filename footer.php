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
?>
</main>

<footer id="colophon" class="site-footer">
	<div class="wrap">
		<div class="footer-grid">

			<div class="footer-col footer-col--brand">
				<?php if ( is_active_sidebar( 'footer-1' ) ) : ?>
					<?php dynamic_sidebar( 'footer-1' ); ?>
				<?php else : ?>
					<a class="brand brand--footer" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"<?php echo has_custom_logo() ? '' : ' aria-label="' . esc_attr( get_bloginfo( 'name' ) ) . '"'; ?>>
						<?php echo nh_editorial_brand_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside the helper. ?>
					</a>
					<?php if ( '' !== get_bloginfo( 'description' ) ) : ?>
						<p class="footer-about"><?php echo esc_html( get_bloginfo( 'description' ) ); ?></p>
					<?php endif; ?>
					<?php get_template_part( 'template-parts/components/social-links', null, array( 'links' => $nh_editorial_social ) ); ?>
				<?php endif; ?>
			</div>

			<div class="footer-col footer-col--menu">
				<?php if ( is_active_sidebar( 'footer-2' ) ) : ?>
					<?php dynamic_sidebar( 'footer-2' ); ?>
				<?php elseif ( has_nav_menu( 'footer' ) ) : ?>
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
				<?php if ( is_active_sidebar( 'footer-3' ) ) : ?>
					<?php dynamic_sidebar( 'footer-3' ); ?>
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
				<?php if ( is_active_sidebar( 'footer-4' ) ) : ?>
					<?php dynamic_sidebar( 'footer-4' ); ?>
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
			<?php if ( '' !== (string) nh_editorial_option( 'nh_footer_text' ) ) : ?>
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
