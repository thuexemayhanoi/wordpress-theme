<?php
/**
 * "Browse by topic": the Hub cards (top-level categories, with Pillar links when mapped).
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

$nh_editorial_hubs = nh_editorial_get_hubs( 8 );
if ( empty( $nh_editorial_hubs ) ) {
	return;
}
?>
<section class="section section--hubs" id="topics" aria-labelledby="topics-title">
	<div class="wrap">
		<div class="section-head">
			<div class="section-head__text">
				<h2 id="topics-title" class="section-head__title"><?php esc_html_e( 'Browse by topic', 'nguyen-ha-editorial' ); ?></h2>
				<p class="section-head__desc"><?php esc_html_e( 'Start from a topic and follow the guides inside it.', 'nguyen-ha-editorial' ); ?></p>
			</div>
		</div>
		<?php
		get_template_part(
			'template-parts/components/hubs-grid',
			null,
			array(
				'hubs'    => $nh_editorial_hubs,
				'heading' => 'h3',
			)
		);
		?>
	</div>
</section>
