<?php
/**
 * Accessible mega-menu walker.
 *
 * Top-level items with children get a disclosure button and a panel (class "mega-panel", hidden until
 * opened by script). The parent link stays a real link, so every hub remains reachable without
 * JavaScript. Grandchildren are listed inside the panel as columns.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

/**
 * Mega-menu walker.
 */
class NH_Editorial_Walker_Nav extends Walker_Nav_Menu {

	/**
	 * ID of the item whose submenu is being opened (for id/aria-controls).
	 *
	 * @var int[]
	 */
	private $parent_stack = array();

	/**
	 * Start a submenu list.
	 *
	 * @param string        $output Output.
	 * @param int           $depth  Depth of the parent item.
	 * @param stdClass|null $args   Menu arguments.
	 * @return void
	 */
	public function start_lvl( &$output, $depth = 0, $args = null ) {
		$parent_id = (int) end( $this->parent_stack );
		$classes   = array( 'sub-menu', 'sub-menu--level-' . ( $depth + 1 ) );
		if ( 0 === $depth ) {
			$classes[] = 'mega-panel';
			$output   .= '<ul id="' . esc_attr( 'submenu-' . $parent_id ) . '" class="' . esc_attr( implode( ' ', $classes ) ) . '" hidden>';
			return;
		}
		$output .= '<ul class="' . esc_attr( implode( ' ', $classes ) ) . '">';
	}

	/**
	 * Start a menu item: the core link plus a disclosure button for top-level parents.
	 *
	 * @param string        $output      Output.
	 * @param WP_Post       $data_object Menu item.
	 * @param int           $depth       Depth.
	 * @param stdClass|null $args        Menu arguments.
	 * @param int           $current_object_id Current object ID.
	 * @return void
	 */
	public function start_el( &$output, $data_object, $depth = 0, $args = null, $current_object_id = 0 ) {
		parent::start_el( $output, $data_object, $depth, $args, $current_object_id );

		$has_children = in_array( 'menu-item-has-children', (array) $data_object->classes, true );
		if ( $has_children ) {
			$this->parent_stack[] = (int) $data_object->ID;
			if ( 0 === $depth ) {
				$output .= nh_editorial_submenu_toggle( (int) $data_object->ID, wp_strip_all_tags( $data_object->title ) );
			}
		}
	}

	/**
	 * End a menu item.
	 *
	 * @param string        $output      Output.
	 * @param WP_Post       $data_object Menu item.
	 * @param int           $depth       Depth.
	 * @param stdClass|null $args        Menu arguments.
	 * @return void
	 */
	public function end_el( &$output, $data_object, $depth = 0, $args = null ) {
		if ( in_array( 'menu-item-has-children', (array) $data_object->classes, true ) ) {
			array_pop( $this->parent_stack );
		}
		parent::end_el( $output, $data_object, $depth, $args );
	}
}
