<?php
/**
 * Navigation: primary mega menu, footer menu, and the category-based fallback menu.
 *
 * The primary menu is the WordPress menu assigned to the "primary" location, rendered by
 * NH_Editorial_Walker_Nav. When no menu is assigned the theme builds one from the category tree
 * (top-level categories as hubs, their children in the panel), cached in the versioned fragment cache,
 * so a new site has working parent/child navigation before anyone opens the menu editor.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

require_once NH_EDITORIAL_DIR . 'inc/class-nh-editorial-walker-nav.php';

/**
 * Disclosure button that opens the panel of a top-level menu item.
 *
 * @param int|string $id    Panel key; the panel element has the id "submenu-{id}".
 * @param string     $label Plain text label of the parent item.
 * @return string Safe HTML.
 */
function nh_editorial_submenu_toggle( $id, $label ) {
	/* translators: %s: menu item name. */
	$text = sprintf( __( 'Toggle submenu of %s', 'nguyen-ha-editorial' ), $label );

	return '<button type="button" class="submenu-toggle" aria-expanded="false" aria-controls="' . esc_attr( 'submenu-' . $id ) . '">'
		. nh_editorial_icon( 'chevron-down' )
		. '<span class="screen-reader-text">' . esc_html( $text ) . '</span></button>';
}

/**
 * Print the primary navigation list.
 *
 * @return void
 */
function nh_editorial_primary_menu() {
	wp_nav_menu(
		array(
			'theme_location' => 'primary',
			'container'      => false,
			'menu_class'     => 'nav-list',
			'menu_id'        => 'primary-menu',
			'depth'          => 3,
			'walker'         => new NH_Editorial_Walker_Nav(),
			'fallback_cb'    => 'nh_editorial_fallback_menu',
		)
	);
}

/**
 * Fallback menu built from the category hubs (or the top pages when there are no categories yet).
 *
 * @return void
 */
function nh_editorial_fallback_menu() {
	$hubs = nh_editorial_get_hubs( 6 );

	echo '<ul id="primary-menu" class="nav-list">';

	if ( empty( $hubs ) ) {
		$nh_editorial_en_home = class_exists( 'NH_VIEN_Routes', false ) && NH_VIEN_Routes::live() &&
			class_exists( 'NH_VIEN_Context', false ) && NH_VIEN_Context::is_english();
		$nh_editorial_home_page = $nh_editorial_en_home ? NH_VIEN_Routes::home_page() : null;
		$args = array(
			'title_li'    => '',
			'depth'       => 1,
			'number'      => 5,
			'sort_column' => 'menu_order, post_title',
		);
		if ( $nh_editorial_en_home ) {
			// An English menu fallback may only list children of the actual /en/ page.
			$args['child_of'] = $nh_editorial_home_page ? (int) $nh_editorial_home_page->ID : -1;
		}
		wp_list_pages( $args );
		echo '</ul>';
		return;
	}

	$queried   = is_category() ? get_queried_object_id() : 0;
	$ancestors = $queried ? array_map( 'intval', get_ancestors( $queried, 'category', 'taxonomy' ) ) : array();

	foreach ( $hubs as $hub ) {
		$has_children = ! empty( $hub['children'] );
		$is_current   = $queried === $hub['id'];
		$is_ancestor  = in_array( $hub['id'], $ancestors, true );
		$classes      = 'menu-item menu-item-type-taxonomy menu-item-object-category'
			. ( $has_children ? ' menu-item-has-children' : '' )
			. ( $is_current ? ' current-menu-item' : '' )
			. ( $is_ancestor ? ' current-menu-ancestor' : '' );

		echo '<li class="' . esc_attr( $classes ) . '"><a href="' . esc_url( $hub['url'] ) . '"' . ( $is_current ? ' aria-current="page"' : '' ) . '>' . esc_html( $hub['name'] ) . '</a>';

		if ( $has_children ) {
			$panel_id = 'cat-' . $hub['id'];
			echo nh_editorial_submenu_toggle( $panel_id, $hub['name'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside the helper.
			echo '<ul id="' . esc_attr( 'submenu-' . $panel_id ) . '" class="sub-menu sub-menu--level-1 mega-panel" hidden>';
			foreach ( $hub['children'] as $child ) {
				echo '<li class="menu-item menu-item-type-taxonomy menu-item-object-category"><a href="' . esc_url( $child['url'] ) . '">' . esc_html( $child['name'] ) . '</a></li>';
			}
			echo '</ul>';
		}

		echo '</li>';
	}

	echo '</ul>';
}
