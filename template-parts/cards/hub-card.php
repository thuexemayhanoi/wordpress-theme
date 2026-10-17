<?php
/**
 * Hub card: one category (a Hub or Silo entry point) with its strongest children and Pillar link.
 *
 * Arguments: hub (item from nh_editorial_get_hubs()), heading (title tag, default h3).
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

$nh_editorial_hub = isset( $args['hub'] ) && is_array( $args['hub'] ) ? $args['hub'] : array();
if ( empty( $nh_editorial_hub ) ) {
	return;
}
$nh_editorial_tag = nh_editorial_heading_tag( isset( $args['heading'] ) ? $args['heading'] : 'h3' );
?>
<article class="card card--hub">
	<div class="card__body">
		<p class="meta"><?php echo esc_html( nh_editorial_count_label( $nh_editorial_hub['count'] ) ); ?></p>
		<<?php echo esc_html( $nh_editorial_tag ); ?> class="card__title"><a href="<?php echo esc_url( $nh_editorial_hub['url'] ); ?>"><?php echo esc_html( $nh_editorial_hub['name'] ); ?></a></<?php echo esc_html( $nh_editorial_tag ); ?>>
		<?php if ( '' !== $nh_editorial_hub['description'] ) : ?>
			<p class="card__excerpt"><?php echo esc_html( $nh_editorial_hub['description'] ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $nh_editorial_hub['children'] ) ) : ?>
			<ul class="chips chips--small">
				<?php foreach ( $nh_editorial_hub['children'] as $nh_editorial_child ) : ?>
					<li><a class="chip" href="<?php echo esc_url( $nh_editorial_child['url'] ); ?>"><?php echo esc_html( $nh_editorial_child['name'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php if ( ! empty( $nh_editorial_hub['pillar'] ) ) : ?>
			<a class="card__link" href="<?php echo esc_url( $nh_editorial_hub['pillar']['url'] ); ?>">
				<?php esc_html_e( 'Read the complete guide', 'nguyen-ha-editorial' ); ?>
				<?php echo nh_editorial_icon( 'arrow-right' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup. ?>
			</a>
		<?php endif; ?>
	</div>
</article>
