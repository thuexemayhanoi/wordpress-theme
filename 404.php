<?php get_header(); ?>
<div class="wrap inner-page">
<div class="paper article"><span class="eyebrow">404</span><h1><?php esc_html_e( 'Page not found', 'nguyen-ha-editorial' ); ?></h1>
<p><?php esc_html_e( 'The address may have changed, or the page no longer exists. Try searching for a topic.', 'nguyen-ha-editorial' ); ?></p>
<?php get_search_form(); ?>
<p><a class="button" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Return home', 'nguyen-ha-editorial' ); ?></a></p>
</div></div>
<?php get_footer(); ?>
