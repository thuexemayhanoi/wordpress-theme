<?php
/**
 * Posts page (used when the front page is a static page): a paginated list of all articles.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="wrap inner-page">
	<?php
	get_template_part(
		'template-parts/components/archive-header',
		null,
		array(
			'title'   => single_post_title( '', false ) ? single_post_title( '', false ) : __( 'Latest articles', 'nguyen-ha-editorial' ),
			'eyebrow' => __( 'Articles', 'nguyen-ha-editorial' ),
		)
	);

	if ( have_posts() ) {
		get_template_part( 'template-parts/components/post-grid', null, array( 'heading' => 'h2' ) );
	} else {
		get_template_part( 'template-parts/components/empty-state', null, array( 'title' => __( 'No articles yet', 'nguyen-ha-editorial' ) ) );
	}
	?>
</div>
<?php
get_footer();
