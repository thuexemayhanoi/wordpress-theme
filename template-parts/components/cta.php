<?php
/**
 * Call-to-action band. Printed only when label and link are configured in the Customizer.
 *
 * Arguments: class (extra CSS class).
 *
 * @package nguyen-ha-editorial
 */

$nh_editorial_cta = nh_editorial_cta();
if ( empty( $nh_editorial_cta ) ) {
	return;
}
$nh_editorial_cta_class = isset( $args['class'] ) ? (string) $args['class'] : '';
?>
<aside class="cta <?php echo esc_attr( $nh_editorial_cta_class ); ?>" aria-label="<?php esc_attr_e( 'Get in touch', 'nguyen-ha-editorial' ); ?>">
	<?php if ( '' !== $nh_editorial_cta['text'] ) : ?>
		<p class="cta__text"><?php echo esc_html( $nh_editorial_cta['text'] ); ?></p>
	<?php endif; ?>
	<a class="button cta__button" href="<?php echo esc_url( $nh_editorial_cta['url'], nh_editorial_link_protocols() ); ?>"><?php echo esc_html( $nh_editorial_cta['label'] ); ?></a>
</aside>
