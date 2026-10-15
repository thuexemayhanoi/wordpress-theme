<aside class="sidebar" aria-label="<?php esc_attr_e( 'Sidebar', 'nguyen-ha-editorial' ); ?>">
<?php if ( is_active_sidebar( 'article-sidebar' ) ) {
    dynamic_sidebar( 'article-sidebar' );
} else { ?>
<section class="sidebar-box"><h2><?php esc_html_e( 'Search', 'nguyen-ha-editorial' ); ?></h2><?php get_search_form(); ?></section>
<section class="sidebar-box"><h2><?php esc_html_e( 'Topics', 'nguyen-ha-editorial' ); ?></h2><ul>
<?php wp_list_categories( array( 'title_li' => '', 'number' => 8, 'orderby' => 'count', 'order' => 'DESC' ) ); ?>
</ul></section>
<?php } ?></aside>
