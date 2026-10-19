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
		add_filter( 'template_include', array( __CLASS__, 'english_home_template' ), 30 );
		add_filter( 'the_title', array( __CLASS__, 'escape_english_title' ), 99 );
		add_shortcode( 'nh_vien_posts', array( __CLASS__, 'posts_shortcode' ) );
	}



	/**
	 * WordPress' the_title() does not escape post titles for HTML. The editorial
	 * cards use that function directly; English headings must never emit user-provided
	 * HTML. Preserve Vietnamese presentation by applying only to live EN routes.
	 *
	 * @param string $title Original post or page title.
	 * @return string Escaped English title or untouched Vietnamese title.
	 */
	public static function escape_english_title( $title ) {
		if ( ! is_string( $title ) || is_admin() || ! NH_VIEN_Routes::live() || ! NH_VIEN_Context::is_english() ) {
			return $title;
		}
		return esc_html( $title );
	}

	/**
	 * Reuse the active Editorial theme's layout for the English home page while keeping the
	 * ordinary WordPress page.php fallback for other themes and all Vietnamese routes.
	 *
	 * @param string $template WordPress' selected PHP template.
	 * @return string Selected template path.
	 */
	public static function english_home_template( $template ) {
		if ( ! NH_VIEN_Routes::live() || ! NH_VIEN_Context::is_english() || ! is_page() ) {
			return $template;
		}
		$home = NH_VIEN_Routes::home_page();
		if ( ! $home instanceof WP_Post || (int) get_queried_object_id() !== (int) $home->ID ) {
			return $template;
		}
		$editorial = locate_template( 'page-templates/template-english-home.php', false, false );
		return $editorial ? $editorial : $template;
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
		// English navigation has its own stable slug per theme location (primary-en,
		// footer-en). Also recognize the old Vietnamese-menu-slug-en convention so
		// existing setups keep working without renaming either menu.
		$english = wp_get_nav_menu_object( $location . '-en' );
		if ( ! $english && $menu ) {
			$english = wp_get_nav_menu_object( $menu->slug . '-en' );
		}
		if ( $english ) {
			$args['menu'] = $english;
		} else {
			// Do not fall back to the Vietnamese menu before an English menu is created.
			$args['menu']           = -1;
			$args['theme_location'] = '';
			$args['fallback_cb']    = array( __CLASS__, 'fallback_english_menu' );
		}
		return $args;
	}

	/**
	 * Render a small English-only menu while its editorial replacement is not set.
	 *
	 * @param array $args WordPress menu fallback arguments.
	 * @return void
	 */
	public static function fallback_english_menu( $args ) {
		$items = array(
			array(
				'label' => 'Home',
				'url'   => NH_VIEN_Routes::url(),
			),
		);
		$below = NH_VIEN_Routes::pages_below_en();
		foreach (
			array(
				'about'   => 'About',
				'prices'  => 'Prices',
				'contact' => 'Contact',
			) as $slug => $label
		) {
			if ( isset( $below[ $slug ] ) ) {
				$items[] = array(
					'label' => $label,
					'url'   => (string) get_permalink( $below[ $slug ] ),
				);
			}
		}
		foreach ( NH_VIEN_Links::declared_en_terms() as $id ) {
			$term = get_term( $id, 'category' );
			if ( ! $term instanceof WP_Term || (int) $term->parent ) {
				continue;
			}
			$link = get_term_link( $term );
			if ( ! is_wp_error( $link ) ) {
				$items[] = array(
					'label' => $term->name,
					'url'   => $link,
				);
			}
		}
		$class = isset( $args['menu_class'] ) ? $args['menu_class'] : 'nav-list';
		$id    = isset( $args['menu_id'] ) ? $args['menu_id'] : 'english-navigation';
		echo '<ul class="' . esc_attr( $class ) . '" id="' . esc_attr( $id ) . '">';
		foreach ( $items as $item ) {
			echo '<li class="menu-item"><a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['label'] ) . '</a></li>';
		}
		echo '</ul>';
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
