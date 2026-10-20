<?php
/**
 * Regression: the English menu never links to an empty category that serves 404.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

/** Empty EN archive navigation guard. */
class NH_Test_English_Menu_SEO extends NH_Unit_Test_Case {

	/**
	 * Create a WordPress-like navigation item without writing menu data.
	 *
	 * @param int    $id          Menu item ID.
	 * @param string $item_type   WordPress menu item type.
	 * @param string $object_type Referenced WordPress object type.
	 * @param int    $object_id   Referenced WordPress object ID.
	 * @param int    $parent_id   Parent menu item ID.
	 * @param array  $classes     WordPress menu item classes.
	 * @return object Test menu item.
	 */
	private function menu_item( $id, $item_type, $object_type, $object_id, $parent_id = 0, $classes = array() ) {
		return (object) array(
			'ID'               => $id,
			'type'             => $item_type,
			'object'           => $object_type,
			'object_id'        => $object_id,
			'menu_item_parent' => $parent_id,
			'classes'          => $classes,
		);
	}

	/** Drop empty English archives but keep the valid English landing page. */
	public function test_empty_english_archives_disappear_but_parent_page_survives_without_toggle() {
		$items = array(
			$this->menu_item( 1768, 'post_type', 'page', 1764, 0, array( 'menu-item-has-children' ) ),
			$this->menu_item( 1773, 'taxonomy', 'category', 49, 1768 ),
			$this->menu_item( 1774, 'taxonomy', 'category', 50, 1768 ),
			$this->menu_item( 1771, 'post_type', 'page', 1759 ),
		);
		$kept  = nh_editorial_prune_empty_en_archive_items( $items, array() );
		$this->assertSame( array( 1768, 1771 ), array_map( 'intval', wp_list_pluck( $kept, 'ID' ) ) );
		$this->assertNotContains( 'menu-item-has-children', $kept[0]->classes );
	}

	/** Once EN child content is public, keep its category and ancestor automatically. */
	public function test_published_child_english_category_keeps_its_ancestor_and_accessible_toggle() {
		$items = array(
			$this->menu_item( 1780, 'taxonomy', 'category', 46, 0, array( 'menu-item-has-children' ) ),
			$this->menu_item( 1781, 'taxonomy', 'category', 49, 1780 ),
			$this->menu_item( 1782, 'taxonomy', 'category', 50, 1780 ),
		);
		$kept  = nh_editorial_prune_empty_en_archive_items(
			$items,
			array(
				'category:46' => true,
				'category:49' => true,
			)
		);
		$this->assertSame( array( 1780, 1781 ), array_map( 'intval', wp_list_pluck( $kept, 'ID' ) ) );
		$this->assertContains( 'menu-item-has-children', $kept[0]->classes );
	}

	/** Unrelated English pages and custom navigation URLs must be retained. */
	public function test_unrelated_page_links_and_custom_links_are_not_removed() {
		$items = array(
			$this->menu_item( 1, 'post_type', 'page', 1758 ),
			$this->menu_item( 2, 'custom', 'custom', 0 ),
			$this->menu_item( 3, 'taxonomy', 'post_tag', 99 ),
		);
		$kept  = nh_editorial_prune_empty_en_archive_items( $items, array() );
		$this->assertSame( array( 1, 2 ), array_map( 'intval', wp_list_pluck( $kept, 'ID' ) ) );
	}
}
