<?php
/**
 * Generic archive (tags, dates, custom taxonomies, post type archives).
 *
 * The description is shown on the first page only, so paginated pages do not repeat it.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="wrap inner-page">
	<?php
	global $wp_query;
	get_template_part(
		'template-parts/components/archive-header',
		null,
		array(
			'title'       => wp_strip_all_tags( get_the_archive_title() ),
			'eyebrow'     => is_tag() ? __( 'Tag', 'nguyen-ha-editorial' ) : __( 'Archive', 'nguyen-ha-editorial' ),
			'meta'        => nh_editorial_count_label( (int) $wp_query->found_posts ),
			'description' => is_paged() ? '' : get_the_archive_description(),
		)
	);

	if ( have_posts() ) {
		get_template_part( 'template-parts/components/post-grid', null, array( 'heading' => 'h2' ) );
	} else {
		get_template_part( 'template-parts/components/empty-state', null, array( 'title' => __( 'No articles in this archive', 'nguyen-ha-editorial' ) ) );
	}
	?>
</div>
<?php
get_footer();
