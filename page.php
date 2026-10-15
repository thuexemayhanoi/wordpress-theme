<?php get_header(); ?>
<div class="wrap inner-page">
<?php while ( have_posts() ) { the_post(); ?>
<article <?php post_class( 'paper article' ); ?>>
<?php nh_editorial_breadcrumbs(); ?>
<header class="article-header"><h1><?php the_title(); ?></h1></header>
<div class="article-body"><?php the_content(); wp_link_pages(); ?></div>
</article>
<?php if ( comments_open() || get_comments_number() ) { comments_template(); } ?>
<?php } ?>
</div>
<?php get_footer(); ?>
