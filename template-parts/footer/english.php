<?php
/**
 * English-only footer using the same Nguyen Ha grid and verified business profile.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

$nh_en_business = nh_editorial_business();
$nh_en_social   = nh_editorial_social_links();
$nh_en_home     = class_exists( 'NH_VIEN_Routes' ) ? NH_VIEN_Routes::url() : home_url( '/' );
$nh_en_page     = class_exists( 'NH_VIEN_Routes' ) ? NH_VIEN_Routes::home_page() : null;
$nh_en_tagline  = $nh_en_page instanceof WP_Post ? (string) $nh_en_page->post_excerpt : '';
$nh_en_menu     = wp_get_nav_menu_object( 'footer-en' );
?>
</main>
<footer id="colophon" class="site-footer">
	<div class="wrap">
		<div class="footer-grid">
			<div class="footer-col footer-col--brand">
				<?php if ( is_active_sidebar( 'footer-en-1' ) ) : ?>
					<?php dynamic_sidebar( 'footer-en-1' ); ?>
				<?php else : ?>
					<a class="brand brand--footer" href="<?php echo esc_url( $nh_en_home ); ?>" rel="home">
						<?php echo nh_editorial_brand_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside the helper. ?>
					</a>
					<?php if ( '' !== trim( $nh_en_tagline ) ) : ?>
						<p class="footer-about"><?php echo esc_html( $nh_en_tagline ); ?></p>
					<?php endif; ?>
					<?php get_template_part( 'template-parts/components/social-links', null, array( 'links' => $nh_en_social ) ); ?>
				<?php endif; ?>
			</div>
			<div class="footer-col footer-col--menu">
				<?php if ( is_active_sidebar( 'footer-en-2' ) ) : ?>
					<?php dynamic_sidebar( 'footer-en-2' ); ?>
				<?php else : ?>
					<h2 class="footer-title"><?php esc_html_e( 'Explore', 'nguyen-ha-editorial' ); ?></h2>
					<nav class="footer-menu" aria-label="<?php esc_attr_e( 'Footer', 'nguyen-ha-editorial' ); ?>">
						<?php
						if ( $nh_en_menu ) {
							wp_nav_menu(
								array(
									'menu'       => $nh_en_menu,
									'container'  => false,
									'menu_class' => 'footer-menu__list',
									'depth'      => 1,
								)
							);
						} else {
							NH_VIEN_Content::fallback_english_menu(
								array(
									'menu_class' => 'footer-menu__list',
									'menu_id'    => 'english-footer-menu',
								)
							);
						}
						?>
					</nav>
				<?php endif; ?>
			</div>
			<div class="footer-col footer-col--topics">
				<?php if ( is_active_sidebar( 'footer-en-3' ) ) : ?>
					<?php dynamic_sidebar( 'footer-en-3' ); ?>
				<?php else : ?>
					<?php $nh_en_topics = nh_editorial_get_hubs( 6 ); ?>
					<?php if ( $nh_en_topics ) : ?>
						<h2 class="footer-title"><?php esc_html_e( 'Topics', 'nguyen-ha-editorial' ); ?></h2>
						<ul class="footer-list">
							<?php foreach ( $nh_en_topics as $nh_en_topic ) : ?>
								<li><a href="<?php echo esc_url( $nh_en_topic['url'] ); ?>"><?php echo esc_html( $nh_en_topic['name'] ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				<?php endif; ?>
			</div>
			<div class="footer-col footer-col--contact">
				<?php if ( is_active_sidebar( 'footer-en-4' ) ) : ?>
					<?php dynamic_sidebar( 'footer-en-4' ); ?>
				<?php elseif ( nh_editorial_has_business_contact() ) : ?>
					<?php get_template_part( 'template-parts/components/contact', null, array( 'business' => $nh_en_business ) ); ?>
				<?php endif; ?>
			</div>
		</div>
		<div class="footer-bottom">
			<p class="footer-copy">
				&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php echo esc_html( get_bloginfo( 'name' ) ); ?>.
				<?php esc_html_e( 'All rights reserved.', 'nguyen-ha-editorial' ); ?>
			</p>
		</div>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
