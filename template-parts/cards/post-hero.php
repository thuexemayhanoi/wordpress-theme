<?php
/**
 * Large featured story. Its image claims the LCP slot (eager, high priority).
 *
 * Arguments: heading (title tag, default h2).
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

$nh_editorial_hero  = wp_parse_args(
	isset( $args ) && is_array( $args ) ? $args : array(),
	array( 'heading' => 'h2' )
);
$nh_editorial_tag   = nh_editorial_heading_tag( $nh_editorial_hero['heading'] );
$nh_editorial_thumb = nh_editorial_thumbnail( get_the_ID(), 'hero' );
?>
<article <?php post_class( 'card card--hero' ); ?>>
	<a class="card__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
		<?php if ( '' !== $nh_editorial_thumb ) : ?>
			<?php echo $nh_editorial_thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by core (get_the_post_thumbnail). ?>
		<?php else : ?>
			<span class="card__placeholder"><?php echo nh_editorial_card_placeholder_label(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by helper. ?></span>
		<?php endif; ?>
	</a>
	<div class="card__body">
		<?php
		nh_editorial_post_meta(
			array(
				'category' => true,
				'date'     => true,
				'reading'  => true,
			)
		);
		?>
		<<?php echo esc_html( $nh_editorial_tag ); ?> class="card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></<?php echo esc_html( $nh_editorial_tag ); ?>>
		<p class="card__excerpt"><?php echo esc_html( get_the_excerpt() ); ?></p>
	</div>
</article>
