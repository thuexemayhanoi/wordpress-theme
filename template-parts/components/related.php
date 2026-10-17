<?php
/**
 * Related articles: same topic first, then the same category, then broader categories.
 *
 * Arguments: post_id (defaults to the current post).
 *
 * @package nguyen-ha-editorial
 */

$nh_editorial_post_id = isset( $args['post_id'] ) ? absint( $args['post_id'] ) : (int) get_the_ID();
$nh_editorial_ids     = nh_editorial_get_related_ids( $nh_editorial_post_id );
if ( empty( $nh_editorial_ids ) ) {
	return;
}

$nh_editorial_related = nh_editorial_posts_by_ids( $nh_editorial_ids );
if ( ! $nh_editorial_related->have_posts() ) {
	return;
}
?>
<section class="section related" aria-labelledby="related-title">
	<div class="section-head">
		<div class="section-head__text">
			<h2 id="related-title" class="section-head__title"><?php esc_html_e( 'Related articles', 'nguyen-ha-editorial' ); ?></h2>
		</div>
	</div>
	<div class="grid grid--cards">
		<?php
		while ( $nh_editorial_related->have_posts() ) {
			$nh_editorial_related->the_post();
			get_template_part(
				'template-parts/cards/post-card',
				null,
				array(
					'heading'  => 'h3',
					'category' => true,
				)
			);
		}
		wp_reset_postdata();
		?>
	</div>
</section>
