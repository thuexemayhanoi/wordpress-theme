<?php get_header(); ?>
<div class="wrap inner-page">
<header class="archive-intro"><span class="eyebrow"><?php esc_html_e( 'Search results', 'nguyen-ha-editorial' ); ?></span>
<h1 class="page-title"><?php printf( esc_html__( 'Results for: %s', 'nguyen-ha-editorial' ), esc_html( get_search_query() ) ); ?></h1><?php get_search_form(); ?></header>
<?php if ( have_posts() ) { ?><div class="post-grid"><?php while ( have_posts() ) { the_post(); get_template_part( 'template-parts/content', 'card' ); } ?></div>
<div class="pagination"><?php the_posts_pagination( array( 'mid_size' => 2, 'prev_text' => '←', 'next_text' => '→' ) ); ?></div>
<?php } else { ?><p><?php esc_html_e( 'No matching articles found. Try another keyword.', 'nguyen-ha-editorial' ); ?></p><?php } ?>
</div><?php get_footer(); ?>
