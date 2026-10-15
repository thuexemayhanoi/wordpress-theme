</main>
<footer class="site-footer"><div class="wrap">
<div class="footer-grid"><div>
<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></a>
<p><?php echo esc_html( get_bloginfo( 'description' ) ); ?></p></div>
<nav class="footer-menu" aria-label="<?php esc_attr_e( 'Footer menu', 'nguyen-ha-editorial' ); ?>">
<?php if ( has_nav_menu( 'footer' ) ) { wp_nav_menu( array( 'theme_location' => 'footer', 'container' => false, 'depth' => 1 ) ); } ?>
</nav></div>
<div class="footer-bottom">&copy; <?php echo esc_html( date_i18n( 'Y' ) ); ?> <?php echo esc_html( get_bloginfo( 'name' ) ); ?>. <?php esc_html_e( 'All rights reserved.', 'nguyen-ha-editorial' ); ?></div>
</div></footer>
<?php wp_footer(); ?>
</body></html>
