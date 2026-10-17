<?php
/**
 * Row of profile links with accessible names. Nothing is printed for an empty list.
 *
 * Arguments: links (items of url, label, icon).
 *
 * @package nguyen-ha-editorial
 */

$nh_editorial_links = isset( $args['links'] ) && is_array( $args['links'] ) ? $args['links'] : array();
if ( empty( $nh_editorial_links ) ) {
	return;
}
?>
<ul class="social-links">
	<?php foreach ( $nh_editorial_links as $nh_editorial_link ) : ?>
		<li>
			<a class="social-links__link" href="<?php echo esc_url( $nh_editorial_link['url'], array( 'http', 'https' ) ); ?>" rel="noopener me external">
				<?php echo nh_editorial_icon( $nh_editorial_link['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup. ?>
				<span class="screen-reader-text"><?php echo esc_html( $nh_editorial_link['label'] ); ?></span>
			</a>
		</li>
	<?php endforeach; ?>
</ul>
