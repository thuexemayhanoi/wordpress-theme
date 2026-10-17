<?php
/**
 * Section heading with optional eyebrow, intro text and "view all" link.
 *
 * Arguments: title (required), tag (h2|h3), id (heading id for aria-labelledby), eyebrow, text,
 * link_url, link_label.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

$nh_editorial_head = wp_parse_args(
	isset( $args ) && is_array( $args ) ? $args : array(),
	array(
		'title'      => '',
		'tag'        => 'h2',
		'id'         => '',
		'eyebrow'    => '',
		'text'       => '',
		'link_url'   => '',
		'link_label' => '',
	)
);
if ( '' === $nh_editorial_head['title'] ) {
	return;
}
$nh_editorial_tag = nh_editorial_heading_tag( $nh_editorial_head['tag'] );
?>
<div class="section-head">
	<div class="section-head__text">
		<?php if ( '' !== $nh_editorial_head['eyebrow'] ) : ?>
			<p class="eyebrow"><?php echo esc_html( $nh_editorial_head['eyebrow'] ); ?></p>
		<?php endif; ?>
		<<?php echo esc_html( $nh_editorial_tag ); ?><?php echo '' !== $nh_editorial_head['id'] ? ' id="' . esc_attr( $nh_editorial_head['id'] ) . '"' : ''; ?> class="section-head__title"><?php echo esc_html( $nh_editorial_head['title'] ); ?></<?php echo esc_html( $nh_editorial_tag ); ?>>
		<?php if ( '' !== $nh_editorial_head['text'] ) : ?>
			<p class="section-head__desc"><?php echo esc_html( $nh_editorial_head['text'] ); ?></p>
		<?php endif; ?>
	</div>
	<?php if ( '' !== $nh_editorial_head['link_url'] && '' !== $nh_editorial_head['link_label'] ) : ?>
		<a class="section-head__link" href="<?php echo esc_url( $nh_editorial_head['link_url'] ); ?>">
			<?php echo esc_html( $nh_editorial_head['link_label'] ); ?>
			<?php echo nh_editorial_icon( 'arrow-right' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup. ?>
		</a>
	<?php endif; ?>
</div>
