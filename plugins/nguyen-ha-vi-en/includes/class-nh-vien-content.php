<?php
/**
 * English content helpers: the menu of English pages and the [nh_vien_posts] shortcode for an English home page.
 *
 * @package Nguyen_Ha_VI_EN
 */

defined( 'ABSPATH' ) || exit;

/**
 * Menus and lists for the English side.
 */
final class NH_VIEN_Content {
	/** Register the hooks when the English side is switched on. */
	public static function boot() {
		if ( ! NH_VIEN_Routes::enabled() ) {
			return;
		}
		add_filter( 'wp_nav_menu_args', array( __CLASS__, 'nav_menu_args' ) );
		add_shortcode( 'nh_vien_posts', array( __CLASS__, 'posts_shortcode' ) );
		add_filter( 'template_include', array( __CLASS__, 'english_home_template' ), 30 );
		add_filter( 'pre_wp_nav_menu', array( __CLASS__, 'prevent_vietnamese_menu' ), 10, 2 );
	}


	/**
	 * Reuse Nguyen Ha Editorial's existing magazine homepage for the published /en/ page.
	 * The Vietnamese homepage keeps its original template and settings. Other themes keep their template.
	 *
	 * @param string $template WordPress-selected template.
	 * @return string The editorial front-page template only on the English homepage.
	 */
	public static function english_home_template( $template ) {
		if ( ! NH_VIEN_Routes::live() || ! NH_VIEN_Context::is_english() || ! is_page() ||
			'wordpress-theme' !== get_stylesheet() ) {
			return $template;
		}
		$home = NH_VIEN_Routes::home_page();
		if ( ! $home instanceof WP_Post || (int) get_queried_object_id() !== (int) $home->ID ) {
			return $template;
		}
		$front = get_theme_file_path( 'front-page.php' );
		return is_readable( $front ) ? $front : $template;
	}

	/**
	 * Inside English pages a theme location shows the English menu of the menu assigned to it, when one exists: the
	 * menu with the same slug and the suffix -en (menu "primary" has the English menu "primary-en"). Locations
	 * without such a menu keep their menu.
	 *
	 * @param array $args The arguments of wp_nav_menu().
	 * @return array
	 */
	public static function nav_menu_args( $args ) {
		if ( ! is_array( $args ) || empty( $args['theme_location'] ) || ! NH_VIEN_Context::is_english() || ! NH_VIEN_Routes::live() ) {
			return $args;
		}
		$locations = get_nav_menu_locations();
		$location  = (string) $args['theme_location'];
		$menu      = ! empty( $locations[ $location ] ) ? wp_get_nav_menu_object( $locations[ $location ] ) : false;
		$english   = $menu ? wp_get_nav_menu_object( $menu->slug . '-en' ) : false;
		if ( $english ) {
			$args['menu'] = $english;
		}
		return $args;
	}


	/**
	 * Do not fall back to the assigned Vietnamese menu on an English Editorial page.
	 * English menus are named with the original menu slug plus -en. Until such a menu exists,
	 * the primary header uses English category hubs and the footer remains empty.
	 *
	 * @param string|null $output Short-circuit return value for wp_nav_menu().
	 * @param stdClass    $args   Menu arguments.
	 * @return string|null
	 */
	public static function prevent_vietnamese_menu( $output, $args ) {
		if ( null !== $output || ! NH_VIEN_Routes::live() || ! NH_VIEN_Context::is_english() ||
			'wordpress-theme' !== get_stylesheet() || ! is_object( $args ) || empty( $args->theme_location ) ) {
			return $output;
		}
		$location = (string) $args->theme_location;
		if ( ! in_array( $location, array( 'primary', 'footer' ), true ) ) {
			return $output;
		}
		$locations = get_nav_menu_locations();
		$base      = ! empty( $locations[ $location ] ) ? wp_get_nav_menu_object( $locations[ $location ] ) : false;
		$english   = $base ? wp_get_nav_menu_object( $base->slug . '-en' ) : false;
		if ( $english ) {
			return $output;
		}
		if ( 'primary' === $location && function_exists( 'nh_editorial_fallback_menu' ) ) {
			ob_start();
			nh_editorial_fallback_menu();
			return (string) ob_get_clean();
		}
		return '';
	}

	/**
	 * The latest English posts that may be shown, for an English home page built by hand.
	 *
	 * Attributes: count (1 to 50, default 12), category (a category slug).
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string Escaped markup, or an empty string when there is nothing to list.
	 */
	public static function posts_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'count'    => 12,
				'category' => '',
			),
			$atts,
			'nh_vien_posts'
		);
		$ids  = NH_VIEN_Routes::live() ? NH_VIEN_Links::public_en_ids() : array();
		if ( ! $ids ) {
			return '';
		}
		$query = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'post__in'            => $ids,
			'posts_per_page'      => max( 1, min( 50, (int) $atts['count'] ) ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'nh_vien_scope'       => 'off',
		);
		if ( '' !== (string) $atts['category'] ) {
			$query['category_name'] = sanitize_title( (string) $atts['category'] );
		}
		$posts = get_posts( $query );
		if ( ! $posts ) {
			return '';
		}
		$items = '';
		foreach ( $posts as $post ) {
			$items .= '<li class="nh-vien-posts__item"><a href="' . esc_url( (string) get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a> <time datetime="' . esc_attr( get_the_date( 'c', $post ) ) . '">' . esc_html( get_the_date( '', $post ) ) . '</time></li>';
		}
		return '<ul class="nh-vien-posts" lang="en">' . $items . '</ul>';
	}
}
