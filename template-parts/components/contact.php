<?php
/**
 * Verified contact details from the companion plugin's business profile. Nothing is hard-coded here:
 * every line appears only if the owner entered it.
 *
 * Arguments: business (from nh_editorial_business()).
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

$nh_editorial_business = isset( $args['business'] ) && is_array( $args['business'] ) ? $args['business'] : array();
if ( empty( $nh_editorial_business ) ) {
	return;
}

$nh_editorial_phone_digits = preg_replace( '/[^0-9+]/', '', $nh_editorial_business['phone'] );
$nh_editorial_address      = array_filter(
	array(
		$nh_editorial_business['street'],
		$nh_editorial_business['locality'],
		$nh_editorial_business['region'],
		$nh_editorial_business['postal'],
	),
	'strlen'
);
$nh_editorial_title        = '' !== $nh_editorial_business['name'] ? $nh_editorial_business['name'] : __( 'Contact', 'nguyen-ha-editorial' );
?>
<h2 class="footer-title"><?php echo esc_html( $nh_editorial_title ); ?></h2>
<ul class="contact-list">
	<?php if ( '' !== $nh_editorial_phone_digits ) : ?>
		<li>
			<?php echo nh_editorial_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup. ?>
			<a href="<?php echo esc_url( 'tel:' . $nh_editorial_phone_digits, array( 'tel' ) ); ?>"><?php echo esc_html( $nh_editorial_business['phone'] ); ?></a>
		</li>
	<?php endif; ?>
	<?php if ( '' !== $nh_editorial_business['email'] ) : ?>
		<li>
			<?php echo nh_editorial_icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup. ?>
			<a href="<?php echo esc_url( 'mailto:' . $nh_editorial_business['email'], array( 'mailto' ) ); ?>"><?php echo esc_html( $nh_editorial_business['email'] ); ?></a>
		</li>
	<?php endif; ?>
	<?php if ( ! empty( $nh_editorial_address ) ) : ?>
		<li>
			<?php echo nh_editorial_icon( 'map-pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup. ?>
			<span>
				<?php echo esc_html( implode( ', ', $nh_editorial_address ) ); ?>
				<?php if ( '' !== $nh_editorial_business['has_map'] ) : ?>
					<a class="contact-list__map" href="<?php echo esc_url( $nh_editorial_business['has_map'], array( 'http', 'https' ) ); ?>" rel="noopener external"><?php esc_html_e( 'Map', 'nguyen-ha-editorial' ); ?></a>
				<?php endif; ?>
			</span>
		</li>
	<?php endif; ?>
	<?php if ( ! empty( $nh_editorial_business['hours'] ) ) : ?>
		<li>
			<?php echo nh_editorial_icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup. ?>
			<span>
				<?php foreach ( $nh_editorial_business['hours'] as $nh_editorial_hours ) : ?>
					<span class="contact-list__hours"><?php echo esc_html( $nh_editorial_hours ); ?></span>
				<?php endforeach; ?>
			</span>
		</li>
	<?php endif; ?>
</ul>
