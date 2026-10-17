<?php
/**
 * Grid of hub cards (top-level categories, or the children of a category).
 *
 * Arguments: hubs (items from nh_editorial_get_hubs()/nh_editorial_get_child_hubs()), heading (h2|h3).
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

$nh_editorial_hubs = isset( $args['hubs'] ) && is_array( $args['hubs'] ) ? $args['hubs'] : array();
if ( empty( $nh_editorial_hubs ) ) {
	return;
}
$nh_editorial_heading = isset( $args['heading'] ) ? (string) $args['heading'] : 'h3';
?>
<div class="grid grid--hubs">
	<?php
	foreach ( $nh_editorial_hubs as $nh_editorial_hub ) {
		get_template_part(
			'template-parts/cards/hub-card',
			null,
			array(
				'hub'     => $nh_editorial_hub,
				'heading' => $nh_editorial_heading,
			)
		);
	}
	?>
</div>
