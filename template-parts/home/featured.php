<?php
/**
 * Featured stories: one large story and up to three compact ones (sticky posts first).
 *
 * Arguments: ids (post IDs from nh_editorial_get_featured_ids()).
 *
 * @package nguyen-ha-editorial
 */

$nh_editorial_ids = isset( $args['ids'] ) && is_array( $args['ids'] ) ? $args['ids'] : array();
if ( empty( $nh_editorial_ids ) ) {
	return;
}
$nh_editorial_query = nh_editorial_posts_by_ids( $nh_editorial_ids );
if ( ! $nh_editorial_query->have_posts() ) {
	return;
}
?>
<section class="section section--featured" aria-labelledby="featured-title">
	<div class="wrap">
		<h2 id="featured-title" class="screen-reader-text"><?php esc_html_e( 'Featured articles', 'nguyen-ha-editorial' ); ?></h2>
		<div class="featured-grid">
			<?php
			$nh_editorial_position = 0;
			while ( $nh_editorial_query->have_posts() ) {
				$nh_editorial_query->the_post();
				if ( 0 === $nh_editorial_position ) {
					get_template_part( 'template-parts/cards/post-hero', null, array( 'heading' => 'h3' ) );
					echo '<div class="featured-grid__side">';
				} else {
					get_template_part( 'template-parts/cards/post-compact', null, array( 'heading' => 'h3' ) );
				}
				++$nh_editorial_position;
			}
			if ( $nh_editorial_position > 0 ) {
				echo '</div>';
			}
			wp_reset_postdata();
			?>
		</div>
	</div>
</section>
