<?php
/**
 * Article card used by every list: archives, search, related articles, homepage.
 *
 * Arguments: heading (title tag, default h3), priority (bool, first card: eager image), category (bool),
 * excerpt (bool), phrase (search phrase whose words are highlighted in the title and excerpt).
 * The whole card is clickable through the title link; the image link is decorative and hidden from
 * assistive technology and the tab order, so there is exactly one link per card for them.
 *
 * @package nguyen-ha-editorial
 */

$nh_editorial_card = wp_parse_args(
	isset( $args ) && is_array( $args ) ? $args : array(),
	array(
		'heading'  => 'h3',
		'priority' => false,
		'category' => true,
		'excerpt'  => true,
		'phrase'   => '',
	)
);
$nh_editorial_tag   = nh_editorial_heading_tag( $nh_editorial_card['heading'] );
$nh_editorial_thumb = nh_editorial_thumbnail( get_the_ID(), 'card', $nh_editorial_card['priority'] ? true : false );
?>
<article <?php post_class( 'card card--post' ); ?> id="post-<?php the_ID(); ?>">
	<a class="card__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
		<?php if ( '' !== $nh_editorial_thumb ) : ?>
			<?php echo $nh_editorial_thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by core (get_the_post_thumbnail). ?>
		<?php else : ?>
			<span class="card__placeholder"><?php echo nh_editorial_icon( 'book' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup. ?></span>
		<?php endif; ?>
	</a>
	<div class="card__body">
		<?php
		nh_editorial_post_meta(
			array(
				'category' => (bool) $nh_editorial_card['category'],
				'date'     => true,
			)
		);
		?>
		<<?php echo esc_html( $nh_editorial_tag ); ?> class="card__title">
			<a href="<?php the_permalink(); ?>">
				<?php
				if ( '' !== $nh_editorial_card['phrase'] ) {
					echo nh_editorial_highlight( nh_editorial_plain_title( get_the_ID() ), $nh_editorial_card['phrase'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside the helper.
				} else {
					the_title();
				}
				?>
			</a>
		</<?php echo esc_html( $nh_editorial_tag ); ?>>
		<?php if ( $nh_editorial_card['excerpt'] ) : ?>
			<p class="card__excerpt">
				<?php
				$nh_editorial_excerpt = html_entity_decode( wp_strip_all_tags( get_the_excerpt() ), ENT_QUOTES, 'UTF-8' );
				echo '' !== $nh_editorial_card['phrase']
					? nh_editorial_highlight( $nh_editorial_excerpt, $nh_editorial_card['phrase'] ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside the helper.
					: esc_html( $nh_editorial_excerpt );
				?>
			</p>
		<?php endif; ?>
	</div>
</article>
