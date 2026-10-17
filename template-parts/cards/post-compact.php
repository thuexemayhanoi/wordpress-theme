<?php
/**
 * Compact article row: small thumbnail, title and date. Used beside the featured story.
 *
 * Arguments: heading (title tag, default h3).
 *
 * @package nguyen-ha-editorial
 */

$nh_editorial_compact = wp_parse_args(
	isset( $args ) && is_array( $args ) ? $args : array(),
	array( 'heading' => 'h3' )
);
$nh_editorial_tag   = nh_editorial_heading_tag( $nh_editorial_compact['heading'] );
$nh_editorial_thumb = nh_editorial_thumbnail( get_the_ID(), 'thumb', false );
?>
<article <?php post_class( 'card card--compact' ); ?> id="post-<?php the_ID(); ?>">
	<a class="card__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
		<?php if ( '' !== $nh_editorial_thumb ) : ?>
			<?php echo $nh_editorial_thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by core (get_the_post_thumbnail). ?>
		<?php else : ?>
			<span class="card__placeholder"><?php echo nh_editorial_icon( 'book' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup. ?></span>
		<?php endif; ?>
	</a>
	<div class="card__body">
		<<?php echo esc_html( $nh_editorial_tag ); ?> class="card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></<?php echo esc_html( $nh_editorial_tag ); ?>>
		<?php nh_editorial_post_meta( array( 'date' => true ) ); ?>
	</div>
</article>
