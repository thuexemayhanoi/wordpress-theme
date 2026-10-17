<?php
/**
 * Magazine homepage.
 *
 * Works in both reading modes:
 * - "Your latest posts": the main query is the paginated post list. Page 1 shows the hero, topics and
 *   the list; pages 2+ show only the list, so page 2 never repeats page 1 and older posts stay reachable.
 * - "A static page": the page content is shown, followed by the featured stories, topics and a list of
 *   the latest articles.
 *
 * The template owns exactly one H1 (the introduction; on pages 2+ the list heading).
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

get_header();

$nh_editorial_posts_mode = is_home();

if ( $nh_editorial_posts_mode && is_paged() ) {
	?>
	<div class="wrap inner-page">
		<?php
		get_template_part(
			'template-parts/components/archive-header',
			null,
			array(
				/* translators: %d: page number. */
				'title' => sprintf( __( 'Latest articles, page %d', 'nguyen-ha-editorial' ), max( 2, (int) get_query_var( 'paged' ) ) ),
			)
		);
		if ( have_posts() ) {
			get_template_part( 'template-parts/components/post-grid', null, array( 'heading' => 'h2' ) );
		} else {
			get_template_part( 'template-parts/components/empty-state', null, array( 'title' => __( 'No more articles', 'nguyen-ha-editorial' ) ) );
		}
		?>
	</div>
	<?php
	get_footer();
	return;
}

$nh_editorial_featured = nh_editorial_get_featured_ids( 4 );

// The latest list is known up front, so a topic section never repeats an article that list shows.
if ( $nh_editorial_posts_mode ) {
	$nh_editorial_latest_ids = array_map( 'intval', wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );
} else {
	$nh_editorial_latest_ids = nh_editorial_get_latest_ids( (int) nh_editorial_option( 'nh_home_latest' ), $nh_editorial_featured );
}

get_template_part( 'template-parts/home/intro' );

if ( ! $nh_editorial_posts_mode ) {
	while ( have_posts() ) {
		the_post();
		if ( '' !== trim( wp_strip_all_tags( get_the_content() ) ) ) {
			?>
			<section class="section section--page-content">
				<div class="wrap">
					<div class="page-content prose"><?php the_content(); ?></div>
				</div>
			</section>
			<?php
		}
	}
}

get_template_part( 'template-parts/home/featured', null, array( 'ids' => $nh_editorial_featured ) );
get_template_part( 'template-parts/home/hubs' );
get_template_part( 'template-parts/home/topic-sections', null, array( 'skip' => array_merge( $nh_editorial_featured, $nh_editorial_latest_ids ) ) );
?>

<section class="section section--latest" id="latest" aria-labelledby="latest-title">
	<div class="wrap">
		<?php
		$nh_editorial_posts_page = (int) get_option( 'page_for_posts' );
		get_template_part(
			'template-parts/components/section-head',
			null,
			array(
				'title'      => __( 'Latest articles', 'nguyen-ha-editorial' ),
				'id'         => 'latest-title',
				'link_url'   => ( ! $nh_editorial_posts_mode && $nh_editorial_posts_page ) ? (string) get_permalink( $nh_editorial_posts_page ) : '',
				'link_label' => __( 'All articles', 'nguyen-ha-editorial' ),
			)
		);

		if ( $nh_editorial_posts_mode ) {
			if ( have_posts() ) {
				get_template_part(
					'template-parts/components/post-grid',
					null,
					array(
						'heading' => 'h3',
						'skip'    => $nh_editorial_featured,
					)
				);
			} else {
				get_template_part( 'template-parts/components/empty-state', null, array( 'title' => __( 'No articles yet', 'nguyen-ha-editorial' ) ) );
			}
		} else {
			$nh_editorial_latest = nh_editorial_posts_by_ids( $nh_editorial_latest_ids );
			if ( $nh_editorial_latest->have_posts() ) {
				echo '<div class="grid grid--cards">';
				while ( $nh_editorial_latest->have_posts() ) {
					$nh_editorial_latest->the_post();
					get_template_part( 'template-parts/cards/post-card', null, array( 'heading' => 'h3' ) );
				}
				echo '</div>';
				wp_reset_postdata();
			}
		}
		?>
	</div>
</section>

<?php if ( ! empty( nh_editorial_cta() ) ) : ?>
<div class="wrap section section--cta">
	<?php get_template_part( 'template-parts/components/cta', null, array( 'class' => 'cta--home' ) ); ?>
</div>
<?php endif; ?>

<?php
get_footer();
