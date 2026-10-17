<?php
/**
 * Static page. Pillar and Hub pages use page-templates/template-pillar.php.
 *
 * @package nguyen-ha-editorial
 */

get_header();

while ( have_posts() ) {
	the_post();
	$nh_editorial_extras = nh_editorial_get_extras( get_the_ID() );
	?>
	<div class="wrap page-wrap">
		<article <?php post_class( 'article article--page' ); ?> id="post-<?php the_ID(); ?>">
			<header class="article-header">
				<?php nh_editorial_breadcrumbs(); ?>
				<h1 class="article-title"><?php the_title(); ?></h1>
			</header>

			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="article-media">
					<?php echo nh_editorial_thumbnail( get_the_ID(), 'featured' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by core (get_the_post_thumbnail). ?>
				</figure>
			<?php endif; ?>

			<div class="article-layout">
				<?php get_template_part( 'template-parts/components/summary', null, array( 'extras' => $nh_editorial_extras ) ); ?>
				<div class="article-main">
					<div class="entry-content prose">
						<?php
						the_content();
						wp_link_pages(
							array(
								'before' => '<nav class="page-links" aria-label="' . esc_attr__( 'Page parts', 'nguyen-ha-editorial' ) . '">',
								'after'  => '</nav>',
							)
						);
						?>
					</div>
					<?php
					get_template_part( 'template-parts/components/faq', null, array( 'faq' => $nh_editorial_extras['faq'] ) );
					get_template_part( 'template-parts/components/sources', null, array( 'sources' => $nh_editorial_extras['sources'] ) );
					?>
				</div>
			</div>
		</article>

		<?php
		if ( comments_open() || get_comments_number() ) {
			comments_template();
		}
		?>
	</div>
	<?php
}

get_footer();
