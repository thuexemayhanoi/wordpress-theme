<?php get_header(); ?>
<div class="wrap inner-page">
<?php while ( have_posts() ) { the_post(); ?>
<div class="content-layout">
<article <?php post_class( 'paper article' ); ?> id="post-<?php the_ID(); ?>">
<?php nh_editorial_breadcrumbs(); ?>
<header class="article-header"><span class="eyebrow"><?php esc_html_e( 'Article', 'nguyen-ha-editorial' ); ?></span>
<h1><?php the_title(); ?></h1>
<?php nh_editorial_post_meta(); ?></header>
<?php if ( has_post_thumbnail() ) { ?><div class="featured"><?php the_post_thumbnail( 'large', array( 'loading' => 'eager' ) ); ?></div><?php } ?>
<div class="article-body"><?php the_content(); wp_link_pages(); ?></div>
<div class="article-footer"><div class="chips"><?php the_tags( '', ' ', '' ); ?></div></div>
</article>
<?php get_sidebar(); ?>
</div>
<nav class="post-navigation" aria-label="<?php esc_attr_e( 'Adjacent posts', 'nguyen-ha-editorial' ); ?>">
<?php $prev = get_previous_post( true ); $next = get_next_post( true );
if ( $prev ) { ?><a href="<?php echo esc_url( get_permalink( $prev ) ); ?>">← <?php echo esc_html( get_the_title( $prev ) ); ?></a><?php }
if ( $next ) { ?><a href="<?php echo esc_url( get_permalink( $next ) ); ?>"><?php echo esc_html( get_the_title( $next ) ); ?> →</a><?php } ?>
</nav>
<?php nh_editorial_related_posts();
if ( comments_open() || get_comments_number() ) { comments_template(); }
} ?>
</div>
<?php get_footer(); ?>
