<?php
/**
 * Previous / next article inside the same silo (topic cluster or category).
 *
 * Arguments: adjacent (from nh_editorial_get_adjacent_ids()).
 *
 * @package nguyen-ha-editorial
 */

$nh_editorial_adjacent = isset( $args['adjacent'] ) && is_array( $args['adjacent'] ) ? $args['adjacent'] : array();
$nh_editorial_prev     = ! empty( $nh_editorial_adjacent['prev'] ) && nh_editorial_is_linkable( $nh_editorial_adjacent['prev'] ) ? (int) $nh_editorial_adjacent['prev'] : 0;
$nh_editorial_next     = ! empty( $nh_editorial_adjacent['next'] ) && nh_editorial_is_linkable( $nh_editorial_adjacent['next'] ) ? (int) $nh_editorial_adjacent['next'] : 0;
if ( ! $nh_editorial_prev && ! $nh_editorial_next ) {
	return;
}
$nh_editorial_label = isset( $nh_editorial_adjacent['label'] ) ? (string) $nh_editorial_adjacent['label'] : '';
?>
<nav class="post-nav" aria-label="<?php esc_attr_e( 'More in this topic', 'nguyen-ha-editorial' ); ?>">
	<?php if ( $nh_editorial_prev ) : ?>
		<a class="post-nav__link post-nav__link--prev" href="<?php echo esc_url( get_permalink( $nh_editorial_prev ) ); ?>" rel="prev">
			<span class="post-nav__label"><?php echo nh_editorial_icon( 'arrow-left' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup. ?><?php esc_html_e( 'Previous', 'nguyen-ha-editorial' ); ?></span>
			<span class="post-nav__title"><?php echo esc_html( nh_editorial_plain_title( $nh_editorial_prev ) ); ?></span>
		</a>
	<?php endif; ?>
	<?php if ( $nh_editorial_next ) : ?>
		<a class="post-nav__link post-nav__link--next" href="<?php echo esc_url( get_permalink( $nh_editorial_next ) ); ?>" rel="next">
			<span class="post-nav__label"><?php esc_html_e( 'Next', 'nguyen-ha-editorial' ); ?><?php echo nh_editorial_icon( 'arrow-right' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup. ?></span>
			<span class="post-nav__title"><?php echo esc_html( nh_editorial_plain_title( $nh_editorial_next ) ); ?></span>
		</a>
	<?php endif; ?>
	<?php if ( '' !== $nh_editorial_label ) : ?>
		<p class="post-nav__topic screen-reader-text">
			<?php
			/* translators: %s: topic name. */
			echo esc_html( sprintf( __( 'Articles in %s', 'nguyen-ha-editorial' ), $nh_editorial_label ) );
			?>
		</p>
	<?php endif; ?>
</nav>
