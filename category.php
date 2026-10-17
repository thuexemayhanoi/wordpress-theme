<?php
/**
 * Category archive: the Hub of a topic.
 *
 * Page 1 shows the category description, the Pillar guide (when mapped), the child categories as
 * sub-hubs and the articles. Pages 2+ show only the articles, so paginated URLs never duplicate the
 * introduction. Titles and meta descriptions stay with the SEO plugin.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

get_header();

global $wp_query;
$nh_editorial_term   = get_queried_object();
$nh_editorial_page_1 = ! is_paged();
$nh_editorial_pillar = ( $nh_editorial_page_1 && $nh_editorial_term instanceof WP_Term ) ? nh_editorial_get_term_pillar( $nh_editorial_term ) : null;
$nh_editorial_kids   = ( $nh_editorial_page_1 && $nh_editorial_term instanceof WP_Term ) ? nh_editorial_get_child_hubs( $nh_editorial_term->term_id ) : array();
?>
<div class="wrap inner-page">
	<?php
	get_template_part(
		'template-parts/components/archive-header',
		null,
		array(
			'title'       => single_cat_title( '', false ),
			'eyebrow'     => ( $nh_editorial_term instanceof WP_Term && $nh_editorial_term->parent ) ? __( 'Subtopic', 'nguyen-ha-editorial' ) : __( 'Topic', 'nguyen-ha-editorial' ),
			'meta'        => nh_editorial_count_label( (int) $wp_query->found_posts ),
			'description' => $nh_editorial_page_1 ? category_description() : '',
		)
	);
	?>

	<?php if ( $nh_editorial_pillar ) : ?>
		<?php
		get_template_part(
			'template-parts/components/pillar-box',
			null,
			array(
				'silo'   => array( 'label' => single_cat_title( '', false ) ),
				'pillar' => $nh_editorial_pillar,
				'hub'    => null,
			)
		);
		?>
	<?php endif; ?>

	<?php if ( ! empty( $nh_editorial_kids ) ) : ?>
		<section class="section section--subtopics" aria-labelledby="subtopics-title">
			<?php
			get_template_part(
				'template-parts/components/section-head',
				null,
				array(
					'title' => __( 'Subtopics', 'nguyen-ha-editorial' ),
					'tag'   => 'h2',
					'id'    => 'subtopics-title',
				)
			);
			?>
			<?php get_template_part( 'template-parts/components/hubs-grid', null, array( 'hubs' => $nh_editorial_kids ) ); ?>
		</section>
	<?php endif; ?>

	<section class="section section--articles" aria-labelledby="articles-title">
		<div class="section-head">
			<div class="section-head__text">
				<h2 id="articles-title" class="section-head__title"><?php esc_html_e( 'Articles', 'nguyen-ha-editorial' ); ?></h2>
			</div>
		</div>
		<?php
		if ( have_posts() ) {
			get_template_part( 'template-parts/components/post-grid', null, array( 'heading' => 'h3' ) );
		} else {
			get_template_part( 'template-parts/components/empty-state', null, array( 'title' => __( 'No articles in this topic yet', 'nguyen-ha-editorial' ) ) );
		}
		?>
	</section>
</div>
<?php
get_footer();
