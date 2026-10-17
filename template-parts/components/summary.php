<?php
/**
 * Answer-first summary and key takeaways. Printed only when the article has that data.
 *
 * Arguments: extras (from nh_editorial_get_extras()).
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

$nh_editorial_extras = isset( $args['extras'] ) && is_array( $args['extras'] ) ? $args['extras'] : array();
$nh_editorial_answer = isset( $nh_editorial_extras['summary'] ) ? (string) $nh_editorial_extras['summary'] : '';
$nh_editorial_points = isset( $nh_editorial_extras['takeaways'] ) ? (array) $nh_editorial_extras['takeaways'] : array();
if ( '' === $nh_editorial_answer && empty( $nh_editorial_points ) ) {
	return;
}
?>
<div class="article-intro">
	<?php if ( '' !== $nh_editorial_answer ) : ?>
		<section class="summary" aria-label="<?php esc_attr_e( 'Quick answer', 'nguyen-ha-editorial' ); ?>">
			<p class="summary__label"><?php echo nh_editorial_icon( 'help' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup. ?><?php esc_html_e( 'Quick answer', 'nguyen-ha-editorial' ); ?></p>
			<p class="summary__text"><?php echo esc_html( $nh_editorial_answer ); ?></p>
		</section>
	<?php endif; ?>

	<?php if ( ! empty( $nh_editorial_points ) ) : ?>
		<section class="takeaways" aria-labelledby="takeaways-title">
			<h2 id="takeaways-title" class="takeaways__title"><?php esc_html_e( 'Key takeaways', 'nguyen-ha-editorial' ); ?></h2>
			<ul class="takeaways__list">
				<?php foreach ( $nh_editorial_points as $nh_editorial_point ) : ?>
					<li><?php echo nh_editorial_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup. ?><span><?php echo esc_html( $nh_editorial_point ); ?></span></li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>
</div>
