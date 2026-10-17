<?php
/**
 * Latest articles from the largest topics (top-level categories), one section each.
 *
 * Arguments: skip (post IDs already shown above, never repeated here).
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

$nh_editorial_count = (int) nh_editorial_option( 'nh_home_pillars' );
if ( $nh_editorial_count < 1 ) {
	return;
}
$nh_editorial_skip = isset( $args['skip'] ) ? array_map( 'absint', (array) $args['skip'] ) : array();

foreach ( array_slice( nh_editorial_get_hubs( $nh_editorial_count ), 0, $nh_editorial_count ) as $nh_editorial_hub ) {
	$nh_editorial_term = get_term( $nh_editorial_hub['id'], 'category' );
	if ( ! $nh_editorial_term instanceof WP_Term ) {
		continue;
	}

	$nh_editorial_ids = array_values( array_diff( nh_editorial_get_supporting_ids( $nh_editorial_term, 3 + count( $nh_editorial_skip ) ), $nh_editorial_skip ) );
	$nh_editorial_ids = array_slice( $nh_editorial_ids, 0, 3 );
	if ( empty( $nh_editorial_ids ) ) {
		continue;
	}

	$nh_editorial_query = nh_editorial_posts_by_ids( $nh_editorial_ids );
	$nh_editorial_slug  = 'topic-' . $nh_editorial_hub['id'];
	?>
	<section class="section section--topic" aria-labelledby="<?php echo esc_attr( $nh_editorial_slug ); ?>">
		<div class="wrap">
			<div class="section-head">
				<div class="section-head__text">
					<h2 id="<?php echo esc_attr( $nh_editorial_slug ); ?>" class="section-head__title"><?php echo esc_html( $nh_editorial_hub['name'] ); ?></h2>
					<?php if ( '' !== $nh_editorial_hub['description'] ) : ?>
						<p class="section-head__desc"><?php echo esc_html( $nh_editorial_hub['description'] ); ?></p>
					<?php endif; ?>
				</div>
				<a class="section-head__link" href="<?php echo esc_url( $nh_editorial_hub['url'] ); ?>">
					<?php
					/* translators: %s: number of articles, for example "12 articles". */
					echo esc_html( sprintf( __( 'View all (%s)', 'nguyen-ha-editorial' ), nh_editorial_count_label( $nh_editorial_hub['count'] ) ) );
					?>
					<?php echo nh_editorial_icon( 'arrow-right' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup. ?>
				</a>
			</div>
			<div class="grid grid--cards">
				<?php
				while ( $nh_editorial_query->have_posts() ) {
					$nh_editorial_query->the_post();
					get_template_part(
						'template-parts/cards/post-card',
						null,
						array(
							'heading'  => 'h3',
							'category' => false,
						)
					);
				}
				wp_reset_postdata();
				?>
			</div>
		</div>
	</section>
	<?php
}
