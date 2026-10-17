<?php
/**
 * Fallback template: a paginated list of articles.
 *
 * @package nguyen-ha-editorial
 */

get_header();
?>
<div class="wrap inner-page">
	<?php
	get_template_part(
		'template-parts/components/archive-header',
		null,
		array(
			'title' => is_archive() ? wp_strip_all_tags( get_the_archive_title() ) : __( 'Latest articles', 'nguyen-ha-editorial' ),
		)
	);

	if ( have_posts() ) {
		get_template_part( 'template-parts/components/post-grid', null, array( 'heading' => 'h2' ) );
	} else {
		get_template_part( 'template-parts/components/empty-state' );
	}
	?>
</div>
<?php
get_footer();
