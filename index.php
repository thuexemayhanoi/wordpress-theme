<?php get_header(); ?>
<div class="wrap inner-page">
<div class="section-head"><div><span class="eyebrow"><?php esc_html_e( 'Explore', 'nguyen-ha-editorial' ); ?></span>
<h1 class="page-title"><?php echo esc_html( is_home() && ! is_front_page() ? single_post_title( '', false ) : __( 'Latest articles', 'nguyen-ha-editorial' ) ); ?></h1></div></div>
<?php if ( have_posts() ) { ?>
<div class="post-grid">
<?php while ( have_posts() ) { the_post(); get_template_part( 'template-parts/content', 'card' ); } ?>
</div>
<div class="pagination"><?php the_posts_pagination( array( 'mid_size' => 2, 'prev_text' => '←', 'next_text' => '→' ) ); ?></div>
<?php } else { ?><p><?php esc_html_e( 'No articles found.', 'nguyen-ha-editorial' ); ?></p><?php } ?>
</div>
<?php get_footer(); ?>
