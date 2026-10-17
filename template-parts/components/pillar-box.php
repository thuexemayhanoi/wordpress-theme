<?php
/**
 * Topic box: the article's silo, a link to its Pillar guide, and a link up to its Hub.
 *
 * Arguments: silo (nh_editorial_get_silo()), pillar (nh_editorial_get_pillar()), hub (nh_editorial_get_hub()).
 * Only links to published, public pages; nothing is printed when there is nothing to link to.
 *
 * @package nguyen-ha-editorial
 */

$nh_editorial_silo   = isset( $args['silo'] ) && is_array( $args['silo'] ) ? $args['silo'] : null;
$nh_editorial_pillar = isset( $args['pillar'] ) && is_array( $args['pillar'] ) ? $args['pillar'] : null;
$nh_editorial_hub    = isset( $args['hub'] ) && is_array( $args['hub'] ) ? $args['hub'] : null;

if ( ! $nh_editorial_pillar && ! $nh_editorial_hub ) {
	return;
}
?>
<aside class="pillar-box" aria-label="<?php esc_attr_e( 'Topic guide', 'nguyen-ha-editorial' ); ?>">
	<div class="pillar-box__icon" aria-hidden="true"><?php echo nh_editorial_icon( 'book' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup. ?></div>
	<div class="pillar-box__body">
		<?php if ( $nh_editorial_silo ) : ?>
			<p class="pillar-box__label">
				<?php
				/* translators: %s: topic name. */
				echo esc_html( sprintf( __( 'Part of the topic: %s', 'nguyen-ha-editorial' ), $nh_editorial_silo['label'] ) );
				?>
			</p>
		<?php endif; ?>

		<?php if ( $nh_editorial_pillar ) : ?>
			<p class="pillar-box__title">
				<a href="<?php echo esc_url( $nh_editorial_pillar['url'] ); ?>"><?php echo esc_html( $nh_editorial_pillar['title'] ); ?></a>
			</p>
			<?php if ( '' !== $nh_editorial_pillar['excerpt'] ) : ?>
				<p class="pillar-box__text"><?php echo esc_html( $nh_editorial_pillar['excerpt'] ); ?></p>
			<?php endif; ?>
			<a class="pillar-box__link" href="<?php echo esc_url( $nh_editorial_pillar['url'] ); ?>">
				<?php esc_html_e( 'Read the complete guide', 'nguyen-ha-editorial' ); ?>
				<?php echo nh_editorial_icon( 'arrow-right' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup. ?>
			</a>
		<?php endif; ?>

		<?php if ( $nh_editorial_hub ) : ?>
			<p class="pillar-box__hub">
				<a href="<?php echo esc_url( $nh_editorial_hub['url'] ); ?>">
					<?php
					/* translators: %s: category name. */
					echo esc_html( sprintf( __( 'More articles in %s', 'nguyen-ha-editorial' ), $nh_editorial_hub['name'] ) );
					?>
				</a>
			</p>
		<?php endif; ?>
	</div>
</aside>
