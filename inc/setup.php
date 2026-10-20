<?php
/**
 * Theme setup: supports, menus, widget areas, small output filters.
 *
 * Menu locations (primary, footer) and the sidebar id (article-sidebar) are unchanged from v1 so that
 * existing menu assignments and widgets keep working after the upgrade.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register theme features.
 *
 * @return void
 */
function nh_editorial_setup() {
	load_theme_textdomain( 'nguyen-ha-editorial', NH_EDITORIAL_DIR . 'languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'customize-selective-refresh-widgets' );
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 60,
			'width'       => 200,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);
	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' )
	);

	/*
	 * The theme prints a visible breadcrumb that is the same trail the SEO plugin describes (see
	 * docs/DATA-CONTRACT.md), so Yoast SEO may include its BreadcrumbList in its structured data even when
	 * its own breadcrumb setting is off. It only adds a piece to Yoast's single graph: no second script.
	 */
	if ( apply_filters( 'nh_editorial_support_seo_breadcrumbs', true ) ) {
		add_theme_support( 'yoast-seo-breadcrumbs' );
	}

	/*
	 * Tells the Vietnamese-English plugin that this theme fires nh_editorial_after_skip_link (header.php), so that the
	 * language switcher comes after the skip link and not before it. Nothing prints without the plugin.
	 */
	add_theme_support( 'nguyen-ha-vi-en-switcher' );

	// Tells the companion plugin which editorial blocks this theme renders itself.
	add_theme_support(
		'nguyen-ha-companion',
		array(
			'summary'   => true,
			'takeaways' => true,
			'faq'       => true,
			'sources'   => true,
			'updated'   => true,
		)
	);

	add_editor_style( 'assets/css/editor.css' );

	register_nav_menus(
		array(
			'primary' => __( 'Primary Navigation', 'nguyen-ha-editorial' ),
			'footer'  => __( 'Footer Navigation', 'nguyen-ha-editorial' ),
		)
	);
}
add_action( 'after_setup_theme', 'nh_editorial_setup' );

/**
 * The VI/EN Core plugin owns all translated destinations and current-language state.
 * This theme places its sole language switcher in the header on every frontend page.
 * Never print the plugin's second bar above the site header.
 *
 * @param bool $print_top_bar Whether the plugin normally prints a separate bar.
 * @return bool
 */
function nh_editorial_header_switcher_auto_placement( $print_top_bar ) {
	if ( is_admin() || ! class_exists( 'NH_VIEN_Switcher' ) ) {
		return $print_top_bar;
	}
	return NH_VIEN_Switcher::alternates() ? false : $print_top_bar;
}
add_filter( 'nh_vien_switcher_auto_placement', 'nh_editorial_header_switcher_auto_placement' );

/**
 * Return the native VI/EN switcher for the header without duplicating its URLs.
 *
 * Works on the homepages, posts, archives and other published pages. The plugin
 * returns no links while the English site is disabled or unavailable.
 *
 * @return string Plugin-escaped navigation markup, or an empty string.
 */
function nh_editorial_header_language_switcher_markup() {
	if ( is_admin() || ! class_exists( 'NH_VIEN_Switcher' ) ) {
		return '';
	}
	return NH_VIEN_Switcher::shortcode();
}


/**
 * Content width for embeds and legacy image sizing.
 *
 * @return void
 */
function nh_editorial_content_width() {
	// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Standard theme global.
	$GLOBALS['content_width'] = (int) apply_filters( 'nh_editorial_content_width', 760 );
}
add_action( 'after_setup_theme', 'nh_editorial_content_width', 0 );

/**
 * Register widget areas.
 *
 * @return void
 */
function nh_editorial_widgets() {
	register_sidebar(
		array(
			'name'          => __( 'Article Sidebar', 'nguyen-ha-editorial' ),
			'id'            => 'article-sidebar',
			'description'   => __( 'Widgets displayed beside the table of contents on articles.', 'nguyen-ha-editorial' ),
			'before_widget' => '<section id="%1$s" class="widget sidebar-box %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		)
	);

	for ( $i = 1; $i <= 4; $i++ ) {
		register_sidebar(
			array(
				/* translators: %d: footer column number. */
				'name'          => sprintf( __( 'Footer Column %d', 'nguyen-ha-editorial' ), $i ),
				'id'            => 'footer-' . $i,
				'description'   => __( 'When this column has widgets they replace its default content.', 'nguyen-ha-editorial' ),
				'before_widget' => '<section id="%1$s" class="widget footer-widget %2$s">',
				'after_widget'  => '</section>',
				'before_title'  => '<h2 class="widget-title footer-title">',
				'after_title'   => '</h2>',
			)
		);
		register_sidebar(
			array(
				/* translators: %d: English footer column number. */
				'name'          => sprintf( 'English %s', sprintf( __( 'Footer Column %d', 'nguyen-ha-editorial' ), $i ) ),
				'id'            => 'footer-en-' . $i,
				'description'   => 'English: ' . __( 'When this column has widgets they replace its default content.', 'nguyen-ha-editorial' ),
				'before_widget' => '<section id="%1$s" class="widget footer-widget %2$s">',
				'after_widget'  => '</section>',
				'before_title'  => '<h2 class="widget-title footer-title">',
				'after_title'   => '</h2>',
			)
		);
	}
}
add_action( 'widgets_init', 'nh_editorial_widgets' );

/**
 * Excerpt length used by cards (words).
 *
 * @param int $length Default length.
 * @return int
 */
function nh_editorial_excerpt_length( $length ) {
	return is_admin() ? $length : (int) apply_filters( 'nh_editorial_excerpt_length', 26 );
}
add_filter( 'excerpt_length', 'nh_editorial_excerpt_length', 999 );

/**
 * Excerpt ellipsis.
 *
 * @param string $more Default more string.
 * @return string
 */
function nh_editorial_excerpt_more( $more ) {
	return is_admin() ? $more : '…';
}
add_filter( 'excerpt_more', 'nh_editorial_excerpt_more', 999 );

/**
 * Clean archive headings: "Category: Foo" becomes "Foo".
 *
 * @return string
 */
function nh_editorial_archive_title_prefix() {
	return '';
}
add_filter( 'get_the_archive_title_prefix', 'nh_editorial_archive_title_prefix' );

/**
 * Force the light scheme when dark mode is disabled in the Customizer.
 *
 * @param string $output Existing attributes.
 * @return string
 */
function nh_editorial_language_attributes( $output ) {
	if ( ! nh_editorial_option( 'nh_dark_mode' ) ) {
		$output .= ' data-theme="light"';
	}
	return $output;
}
add_filter( 'language_attributes', 'nh_editorial_language_attributes' );

/**
 * Body classes that drive layout.
 *
 * @param string[] $classes Classes.
 * @return string[]
 */
function nh_editorial_body_class( $classes ) {
	if ( nh_editorial_option( 'nh_sticky_header' ) ) {
		$classes[] = 'has-sticky-header';
	}
	if ( is_singular() && ! is_front_page() ) {
		$classes[] = 'is-article';
	}
	return $classes;
}
add_filter( 'body_class', 'nh_editorial_body_class' );

/**
 * Load the comment reply script only where it is needed.
 *
 * @return void
 */
function nh_editorial_comment_reply_script() {
	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'nh_editorial_comment_reply_script' );
