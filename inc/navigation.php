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
		wp_list_pages(
			array(
				'title_li'    => '',
				'depth'       => 1,
				'number'      => 5,
				'sort_column' => 'menu_order, post_title',
			)
		);
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


/**
 * Hide English navigation links to archives without a published English article.
 *
 * WordPress navigation items are persistent taxonomy references. English categories are intentionally
 * registered before the writer publishes any posts, and the VI/EN plugin correctly serves a 404 for
 * an empty English archive. Do not advertise those 404s in the navigation. Leave the stored menu and
 * all term IDs, parents and permalinks intact so that links return automatically as EN posts go live.
 *
 * @param array $items WordPress menu items.
 * @param array $visible Term keys, e.g. category:49 => true, with ancestor categories included.
 * @return array Filtered items.
 */
function nh_editorial_prune_empty_en_archive_items( $items, $visible ) {
	$kept = array();
	foreach ( (array) $items as $item ) {
		if (
			is_object( $item )
			&& 'taxonomy' === (string) ( $item->type ?? '' )
			&& in_array( (string) ( $item->object ?? '' ), array( 'category', 'post_tag' ), true )
			&& ! isset( $visible[ (string) $item->object . ':' . (int) $item->object_id ] )
		) {
			continue;
		}
		$kept[] = $item;
	}

	// Core already set menu-item-has-children before this filter. An English top-level
	// page whose empty category children were removed must not keep a dead toggle.
	$parents = array();
	foreach ( $kept as $item ) {
		if ( is_object( $item ) && ! empty( $item->menu_item_parent ) ) {
			$parents[ (int) $item->menu_item_parent ] = true;
		}
	}
	foreach ( $kept as $item ) {
		if ( ! is_object( $item ) || empty( $item->classes ) || isset( $parents[ (int) $item->ID ] ) ) {
			continue;
		}
		$item->classes = array_values( array_diff( (array) $item->classes, array( 'menu-item-has-children' ) ) );
	}

	return $kept;
}

/**
 * Apply the English archive guard only to the English frontend. Vietnamese menus are untouched.
 *
 * @param array $items Navigation items.
 * @param mixed $args  wp_nav_menu() arguments (unused).
 * @return array Navigation items.
 */
function nh_editorial_guard_english_menu_archives( $items, $args = null ) {
	if (
		! is_array( $items )
		|| ! class_exists( 'NH_VIEN_Routes' )
		|| ! class_exists( 'NH_VIEN_Context' )
		|| ! class_exists( 'NH_VIEN_Links' )
		|| ! NH_VIEN_Routes::live()
		|| ! NH_VIEN_Context::is_english()
	) {
		return $items;
	}

	$english_ids = NH_VIEN_Links::public_en_ids();
	$visible     = array();
	if ( $english_ids ) {
		$terms = wp_get_object_terms( $english_ids, array( 'category', 'post_tag' ) );
		if ( is_wp_error( $terms ) ) {
			// Never silently delete navigation when a taxonomy lookup fails.
			return $items;
		}
		foreach ( $terms as $term ) {
			$visible[ $term->taxonomy . ':' . (int) $term->term_id ] = true;
			if ( 'category' === $term->taxonomy ) {
				foreach ( get_ancestors( $term->term_id, 'category', 'taxonomy' ) as $ancestor ) {
					$visible[ 'category:' . (int) $ancestor ] = true;
				}
			}
		}
	}

	return nh_editorial_prune_empty_en_archive_items( $items, $visible );
}
add_filter( 'wp_nav_menu_objects', 'nh_editorial_guard_english_menu_archives', 20, 2 );
