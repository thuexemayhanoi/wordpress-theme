<?php get_header(); ?>
<section class="hero"><div class="wrap hero-grid">
<div class="hero-card">
<span class="eyebrow"><?php esc_html_e( 'Vietnam • Travel • Mobility', 'nguyen-ha-editorial' ); ?></span>
<h1><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>
<p><?php echo esc_html( get_bloginfo( 'description' ) ?: __( 'Practical guides, helpful experiences and useful information for readers.', 'nguyen-ha-editorial' ) ); ?></p>
<a class="button" href="#newest"><?php esc_html_e( 'Explore articles →', 'nguyen-ha-editorial' ); ?></a>
</div>
<div class="hero-aside"><span class="eyebrow"><?php esc_html_e( 'Find something useful', 'nguyen-ha-editorial' ); ?></span>
<h2><?php esc_html_e( 'What would you like to read?', 'nguyen-ha-editorial' ); ?></h2>
<?php get_search_form(); ?>
<div class="chips">
<?php $topics = get_categories( array( 'number' => 5, 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC' ) ); foreach ( $topics as $topic ) { ?><a class="chip" href="<?php echo esc_url( get_category_link( $topic->term_id ) ); ?>"><?php echo esc_html( $topic->name ); ?></a><?php } ?>
</div></div></div></section>
<?php if ( 'page' === get_option( 'show_on_front' ) ) {
    while ( have_posts() ) { the_post();
        if ( trim( get_the_content() ) ) { ?>
        <div class="wrap paper editor-intro"><?php the_content(); ?></div>
        <?php }
    }
} ?>
<section class="section" id="newest"><div class="wrap">
<div class="section-head"><div><span class="eyebrow"><?php esc_html_e( 'From the blog', 'nguyen-ha-editorial' ); ?></span>
<h2><?php esc_html_e( 'Newest stories', 'nguyen-ha-editorial' ); ?></h2>
<p><?php esc_html_e( 'Updated articles from our editorial library.', 'nguyen-ha-editorial' ); ?></p></div></div>
<?php
$q = new WP_Query( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 9, 'ignore_sticky_posts' => true, 'no_found_rows' => true ) );
if ( $q->have_posts() ) { echo '<div class="post-grid">'; while ( $q->have_posts() ) { $q->the_post(); get_template_part( 'template-parts/content', 'card' ); } echo '</div>'; }
wp_reset_postdata();
?>
</div></section>
<?php get_footer(); ?>
