<?php
/**
 * Complete, language-safe taxonomy navigation for Nguyen Ha Editorial.
 *
 * Menus maintained in WordPress keep their original pages and SEO posts.
 * The one extra disclosure item contains the current, hierarchical, published
 * category tree. New published categories appear automatically on next cache
 * invalidation, without writing nav_menu_item records for each term.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

/**
 * Collect term IDs actually used by publicly visible EN articles, together
 * with every ancestor needed for a navigable taxonomy tree.
 *
 * @return array<int,bool>
 */
function nh_editorial_menu_english_visible_terms() {
	return (array) nh_editorial_cache_remember(
		'complete_categories_en_v1',
		function () {
			if ( ! class_exists( 'NH_VIEN_Links' ) || ! class_exists( 'NH_VIEN_Routes' ) || ! NH_VIEN_Routes::live() ) {
				return array();
			}
			$post_ids = array_map( 'absint', (array) NH_VIEN_Links::public_en_ids() );
			if ( ! $post_ids ) {
				return array();
			}
			$ids = wp_get_object_terms( $post_ids, 'category', array( 'fields' => 'ids' ) );
			if ( is_wp_error( $ids ) ) {
				return array();
			}
			$visible = array();
			foreach ( $ids as $id ) {
				$id = (int) $id;
				if ( 1 === $id ) {
					continue; // Never feature the default Uncategorized bucket.
				}
				$visible[ $id ] = true;
				foreach ( get_ancestors( $id, 'category', 'taxonomy' ) as $ancestor ) {
					$visible[ (int) $ancestor ] = true;
				}
			}
			return $visible;
		},
		HOUR_IN_SECONDS
	);
}

/**
 * Category label is deliberately 2-3 words, without changing its actual
 * taxonomy name, slug, page title or SEO metadata.
 *
 * @param WP_Term $term Category.
 * @param bool    $english Current page language.
 * @return string
 */
function nh_editorial_menu_category_label( $term, $english ) {
	$labels = array(
		3  => 'Thuê xe máy',
		9  => 'Khu vực',
		15 => 'Chủ đề khác',
		46 => 'Hanoi Motorbike Rental',
		47 => 'Hanoi Travel',
		48 => 'Rider Safety',
		49 => 'Manual Motorbikes',
		50 => 'Automatic Scooters',
		51 => '50cc Motorbikes',
		52 => 'Electric Scooters',
		53 => 'Hanoi Areas',
		54 => 'Motorbike Routes',
		55 => 'Rental Paperwork',
		56 => 'Road Safety',
		60 => 'Hoan Kiem',
		61 => 'Old Quarter',
		62 => 'Long Bien',
		63 => 'Ba Dinh',
		64 => 'Gia Lam',
		65 => 'Honda Yamaha Comparisons',
		66 => 'Bike Maintenance',
		67 => 'Navigation Apps',
		68 => 'Traffic Parking',
		69 => 'Monthly Rentals',
	);
	if ( isset( $labels[ $term->term_id ] ) ) {
		return $labels[ $term->term_id ];
	}

	$name  = html_entity_decode( (string) $term->name, ENT_QUOTES, get_bloginfo( 'charset' ) );
	$words = preg_split( '/\s+/u', trim( $name ) );
	if ( count( $words ) <= 3 ) {
		return $name;
	}

	// Shorten only menu text; articles and taxonomy archive titles remain full.
	$short = array_slice( $words, 0, 3 );
	if ( in_array( strtolower( end( $short ) ), array( '&', 'and', 'và' ), true ) ) {
		array_pop( $short );
	}
	return implode( ' ', $short );
}

/**
 * Sort real WordPress categories into a language-specific parent/child tree.
 * EN terms with no published EN article are not linked (their archive may 404).
 *
 * @param bool $en English site?
 * @return array Parent ID => list of WP_Term.
 */
function nh_editorial_menu_category_tree( $en ) {
	$terms = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC' ) );
	if ( is_wp_error( $terms ) || ! $terms ) {
		return array();
	}

	$en_visible = nh_editorial_menu_english_visible_terms();
	$visible    = $en ? $en_visible : array();
	if ( ! $en ) {
		foreach ( $terms as $term ) {
			$id = (int) $term->term_id;
			if ( 1 === $id || isset( $en_visible[ $id ] ) || $term->count < 1 ) {
				continue;
			}
			$visible[ $id ] = true;
			foreach ( get_ancestors( $id, 'category', 'taxonomy' ) as $ancestor ) {
				if ( ! isset( $en_visible[ (int) $ancestor ] ) ) {
					$visible[ (int) $ancestor ] = true;
				}
			}
		}
	}

	$map = array();
	foreach ( $terms as $term ) {
		$id = (int) $term->term_id;
		if ( 1 === $id || ! isset( $visible[ $id ] ) || ( $en && empty( $en_visible[ $id ] ) ) ) {
			continue;
		}
		$parent          = isset( $visible[ (int) $term->parent ] ) ? (int) $term->parent : 0;
		$map[ $parent ][] = $term;
	}

	// Keep money-page category groups ahead of auxiliary travel subjects.
	if ( isset( $map[0] ) ) {
		$order = $en ? array( 46 => 0, 48 => 1, 47 => 2 ) : array( 3 => 0, 9 => 1, 15 => 2 );
		usort(
			$map[0],
			function ( $a, $b ) use ( $order ) {
				$aa = isset( $order[ (int) $a->term_id ] ) ? $order[ (int) $a->term_id ] : 100;
				$bb = isset( $order[ (int) $b->term_id ] ) ? $order[ (int) $b->term_id ] : 100;
				return $aa === $bb ? strcmp( (string) $a->name, (string) $b->name ) : $aa - $bb;
			}
		);
	}

	return $map;
}

/**
 * Render the actual taxonomy ancestry, up to six levels (current site max four).
 * No invented paths, offsite links, empty English archives or category duplication.
 *
 * @param array $tree Hierarchical terms.
 * @param int   $parent Parent term ID, zero at the root.
 * @param int   $depth Current tree depth.
 * @param bool  $en Language.
 * @return string Already escaped HTML.
 */
function nh_editorial_menu_tree_markup( $tree, $parent, $depth, $en ) {
	if ( $depth > 6 || empty( $tree[ $parent ] ) ) {
		return '';
	}
	$items = '';
	foreach ( $tree[ $parent ] as $term ) {
		$url = get_term_link( $term );
		if ( is_wp_error( $url ) || ! $url ) {
			continue;
		}
		$children = nh_editorial_menu_tree_markup( $tree, (int) $term->term_id, $depth + 1, $en );
		$is_here  = is_category( (int) $term->term_id );
		$items   .= '<li class="menu-item menu-item-type-taxonomy menu-item-object-category'
			. ( $children ? ' menu-item-has-children' : '' )
			. ( $is_here ? ' current-menu-item' : '' ) . '"><a href="' . esc_url( $url ) . '"'
			. ( $is_here ? ' aria-current="page"' : '' ) . '>'
			. esc_html( nh_editorial_menu_category_label( $term, $en ) ) . '</a>' . $children . '</li>';
	}
	if ( '' === $items ) {
		return '';
	}
	$level = $depth + 1;
	return '<ul class="sub-menu sub-menu--level-' . (int) $level
		. ( 0 === $depth ? ' mega-panel mega-panel--all-categories' : '' )
		. '"' . ( 0 === $depth ? ' id="submenu-nh-all-categories"' : '' ) . '>'
		. $items . '</ul>';
}

/**
 * Add one complete category panel to the existing primary menu, without
 * changing WordPress menu records, sitemap settings or category permalinks.
 *
 * @param string   $items  Core-rendered menu items.
 * @param stdClass $args   wp_nav_menu arguments.
 * @return string
 */
function nh_editorial_menu_append_categories( $items, $args ) {
	if ( is_admin() || ! is_object( $args ) || 'primary' !== (string) ( $args->theme_location ?? '' ) ) {
		return $items;
	}
	$en = function_exists( 'nh_editorial_seo_is_en' ) && nh_editorial_seo_is_en();
	if ( $en && ( ! class_exists( 'NH_VIEN_Links' ) || ! NH_VIEN_Links::public_en_ids() ) ) {
		return $items;
	}
	$tree = nh_editorial_menu_category_tree( $en );
	$html = nh_editorial_menu_tree_markup( $tree, 0, 0, $en );
	if ( '' === $html ) {
		return $items;
	}
	$title = $en ? 'Categories' : 'Danh mục';
	$home  = $en ? get_page_by_path( 'en/guides', OBJECT, 'page' ) : get_page_by_path( 'cam-nang', OBJECT, 'page' );
	$link  = $home instanceof WP_Post && 'publish' === $home->post_status
		? get_permalink( $home )
		: ( $en ? home_url( '/en/' ) : home_url( '/' ) );

	// Same disclosure classes as the native mega-menu, with unique aria ID.
	$button = nh_editorial_submenu_toggle( 'nh-all-categories', $title );
	$html   = str_replace( 'class="sub-menu sub-menu--level-1 mega-panel', 'class="sub-menu sub-menu--level-1 mega-panel', $html );
	// Submenu stays hidden until the accessible toggle is activated.
	$html   = str_replace( 'id="submenu-nh-all-categories">', 'id="submenu-nh-all-categories" hidden>', $html );

	return $items
		. '<li class="menu-item menu-item-has-children menu-item--all-categories">'
		. '<a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a>'
		. $button . $html . '</li>';
}
add_filter( 'wp_nav_menu_items', 'nh_editorial_menu_append_categories', 40, 2 );
