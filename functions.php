<?php
/**
 * Nguyen Ha Editorial theme setup.
 *
 * @package nguyen-ha-editorial
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
function nh_editorial_setup() {
    load_theme_textdomain( 'nguyen-ha-editorial', get_template_directory() . '/languages' );
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'automatic-feed-links' );
    add_theme_support( 'responsive-embeds' );
    add_theme_support( 'align-wide' );
    add_theme_support( 'custom-logo', array( 'height' => 60, 'width' => 180, 'flex-height' => true, 'flex-width' => true ) );
    add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
    register_nav_menus( array(
        'primary' => __( 'Primary Navigation', 'nguyen-ha-editorial' ),
        'footer' => __( 'Footer Navigation', 'nguyen-ha-editorial' ),
    ) );
}
add_action( 'after_setup_theme', 'nh_editorial_setup' );

function nh_editorial_assets() {
    $version = wp_get_theme()->get( 'Version' );
    wp_enqueue_style( 'nh-editorial-style', get_stylesheet_uri(), array(), $version );
    wp_enqueue_script( 'nh-editorial-script', get_template_directory_uri() . '/assets/js/theme.js', array(), $version, true );
}
add_action( 'wp_enqueue_scripts', 'nh_editorial_assets' );

function nh_editorial_widgets() {
    register_sidebar( array(
        'name' => __( 'Article Sidebar', 'nguyen-ha-editorial' ),
        'id' => 'article-sidebar',
        'description' => __( 'Widgets displayed alongside articles and archive pages.', 'nguyen-ha-editorial' ),
        'before_widget' => '<section class="sidebar-box widget">',
        'after_widget' => '</section>',
        'before_title' => '<h2 class="widget-title">',
        'after_title' => '</h2>',
    ) );
}
add_action( 'widgets_init', 'nh_editorial_widgets' );

function nh_editorial_excerpt_length( $length ) {
    return is_admin() ? $length : 26;
}
add_filter( 'excerpt_length', 'nh_editorial_excerpt_length', 999 );

function nh_editorial_post_meta() {
    echo '<div class="meta">';
    echo '<time datetime="' . esc_attr( get_the_date( DATE_W3C ) ) . '">' . esc_html( get_the_date() ) . '</time>';
    $cat = get_the_category();
    if ( $cat ) {
        echo '<span aria-hidden="true">·</span><a href="' . esc_url( get_category_link( $cat[0]->term_id ) ) . '">' . esc_html( $cat[0]->name ) . '</a>';
    }
    echo '</div>';
}
function nh_editorial_breadcrumbs() {
    if ( is_front_page() ) {
        return;
    }
    echo '<nav class="breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumbs', 'nguyen-ha-editorial' ) . '">';
    echo '<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Home', 'nguyen-ha-editorial' ) . '</a> &rsaquo; ';
    if ( is_single() ) {
        $cat = get_the_category();
        if ( $cat ) {
            echo '<a href="' . esc_url( get_category_link( $cat[0]->term_id ) ) . '">' . esc_html( $cat[0]->name ) . '</a> &rsaquo; ';
        }
        echo '<span>' . esc_html( get_the_title() ) . '</span>';
    } elseif ( is_page() ) {
        echo '<span>' . esc_html( get_the_title() ) . '</span>';
    } else {
        echo '<span>' . esc_html( wp_strip_all_tags( get_the_archive_title() ) ) . '</span>';
    }
    echo '</nav>';
}
function nh_editorial_related_posts() {
    if ( ! is_single() ) {
        return;
    }
    $categories = wp_get_post_categories( get_the_ID() );
    if ( empty( $categories ) ) {
        return;
    }
    $q = new WP_Query( array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => 3,
        'post__not_in' => array( get_the_ID() ),
        'category__in' => $categories,
        'ignore_sticky_posts' => true,
        'no_found_rows' => true,
    ) );
    if ( $q->have_posts() ) {
        echo '<section class="section" aria-label="' . esc_attr__( 'Related articles', 'nguyen-ha-editorial' ) . '"><div class="section-head"><h2>' . esc_html__( 'Related articles', 'nguyen-ha-editorial' ) . '</h2></div><div class="post-grid">';
        while ( $q->have_posts() ) {
            $q->the_post();
            get_template_part( 'template-parts/content', 'card' );
        }
        echo '</div></section>';
    }
    wp_reset_postdata();
}
