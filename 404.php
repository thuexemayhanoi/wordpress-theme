<?php
/**
 * 404 page: a clear message, search, popular topics and the latest articles. WordPress sends the 404
 * status; no robots directive is added here.
 *
 * @package nguyen-ha-editorial
 */

get_header();

$nh_editorial_latest = nh_editorial_posts_by_ids( nh_editorial_get_latest_ids( 3 ) );
?>
<div class="wrap inner-page">
	<header class="archive-header archive-header--404">
		<p class="eyebrow">404</p>
		<h1 class="archive-title"><?php esc_html_e( 'Page not found', 'nguyen-ha-editorial' ); ?></h1>
		<p class="archive-lead"><?php esc_html_e( 'The address may have changed or the page may no longer exist. Search for what you need or start from a topic.', 'nguyen-ha-editorial' ); ?></p>
	</header>

	<?php get_template_part( 'template-parts/components/empty-state', null, array( 'title' => __( 'Search the site', 'nguyen-ha-editorial' ) ) ); ?>

	<?php if ( $nh_editorial_latest->have_posts() ) : ?>
		<section class="section" aria-labelledby="latest-404">
			<?php
			get_template_part(
				'template-parts/components/section-head',
				null,
				array(
					'title' => __( 'Latest articles', 'nguyen-ha-editorial' ),
					'id'    => 'latest-404',
				)
			);
			?>
			<div class="grid grid--cards">
				<?php
				while ( $nh_editorial_latest->have_posts() ) {
					$nh_editorial_latest->the_post();
					get_template_part( 'template-parts/cards/post-card', null, array( 'heading' => 'h3' ) );
				}
				wp_reset_postdata();
				?>
			</div>
		</section>
	<?php endif; ?>

	<p><a class="button" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Return home', 'nguyen-ha-editorial' ); ?></a></p>
</div>
<?php
get_footer();
