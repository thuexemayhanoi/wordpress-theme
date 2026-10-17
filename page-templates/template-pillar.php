<?php
/**
 * Template Name: Pillar / Hub page
 * Template Post Type: page
 *
 * A Pillar or Hub page: long-form guide with an automatic table of contents, followed by the
 * sub-topics and the most recent supporting articles of the topic it is mapped to (the "nh_hub_term"
 * page setting, or a category/cluster that lists this page as its Pillar). Supporting articles link
 * back to this page from their topic box, so the hub-and-spoke links are two-way.
 *
 * @package nguyen-ha-editorial
 */

get_header();

while ( have_posts() ) {
	the_post();

	$nh_editorial_page_id = get_the_ID();
	$nh_editorial_article = nh_editorial_prepare_article();
	$nh_editorial_extras  = nh_editorial_get_extras( $nh_editorial_page_id );
	$nh_editorial_has_toc = ! empty( $nh_editorial_article['toc'] );
	$nh_editorial_term    = nh_editorial_get_hub_term( $nh_editorial_page_id );
	$nh_editorial_kids    = ( $nh_editorial_term && 'category' === $nh_editorial_term->taxonomy ) ? nh_editorial_get_child_hubs( $nh_editorial_term->term_id ) : array();
	$nh_editorial_ids     = $nh_editorial_term ? nh_editorial_get_supporting_ids( $nh_editorial_term, 12 ) : array();
	?>
	<div class="wrap article-wrap">
		<article <?php post_class( 'article article--pillar' ); ?> id="post-<?php the_ID(); ?>">
			<header class="article-header">
				<?php nh_editorial_breadcrumbs(); ?>
				<p class="eyebrow"><?php esc_html_e( 'Complete guide', 'nguyen-ha-editorial' ); ?></p>
				<h1 class="article-title"><?php the_title(); ?></h1>
				<?php
				nh_editorial_post_meta(
					array(
						'updated' => true,
						'reading' => true,
					)
				);
				?>
			</header>

			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="article-media">
					<?php echo nh_editorial_thumbnail( $nh_editorial_page_id, 'featured' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by core (get_the_post_thumbnail). ?>
				</figure>
			<?php endif; ?>

			<div class="article-layout<?php echo $nh_editorial_has_toc ? ' has-toc' : ''; ?>">
				<?php get_template_part( 'template-parts/components/summary', null, array( 'extras' => $nh_editorial_extras ) ); ?>
				<?php
				if ( $nh_editorial_has_toc ) {
					get_template_part( 'template-parts/components/toc', null, array( 'items' => $nh_editorial_article['toc'] ) );
				}
				?>
				<div class="article-main">
					<div class="entry-content prose">
						<?php echo $nh_editorial_article['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output of the_content filters, identical to the_content(). ?>
					</div>
					<?php
					get_template_part( 'template-parts/components/faq', null, array( 'faq' => $nh_editorial_extras['faq'] ) );
					get_template_part( 'template-parts/components/sources', null, array( 'sources' => $nh_editorial_extras['sources'] ) );
					?>
				</div>
			</div>
		</article>

		<?php if ( ! empty( $nh_editorial_kids ) ) : ?>
			<section class="section" aria-labelledby="pillar-subtopics">
				<?php
				get_template_part(
					'template-parts/components/section-head',
					null,
					array(
						'title' => __( 'Explore the topic', 'nguyen-ha-editorial' ),
						'id'    => 'pillar-subtopics',
					)
				);
				get_template_part( 'template-parts/components/hubs-grid', null, array( 'hubs' => $nh_editorial_kids ) );
				?>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $nh_editorial_ids ) ) : ?>
			<?php $nh_editorial_support = nh_editorial_posts_by_ids( $nh_editorial_ids ); ?>
			<section class="section" aria-labelledby="pillar-articles">
				<?php
				$nh_editorial_all = $nh_editorial_term ? get_term_link( $nh_editorial_term ) : '';
				get_template_part(
					'template-parts/components/section-head',
					null,
					array(
						'title'      => __( 'Latest articles in this topic', 'nguyen-ha-editorial' ),
						'id'         => 'pillar-articles',
						'link_url'   => ( $nh_editorial_all && ! is_wp_error( $nh_editorial_all ) && 'category' === $nh_editorial_term->taxonomy ) ? $nh_editorial_all : '',
						'link_label' => __( 'View all articles', 'nguyen-ha-editorial' ),
					)
				);
				?>
				<div class="grid grid--cards">
					<?php
					while ( $nh_editorial_support->have_posts() ) {
						$nh_editorial_support->the_post();
						get_template_part( 'template-parts/cards/post-card', null, array( 'heading' => 'h3' ) );
					}
					wp_reset_postdata();
					?>
				</div>
			</section>
		<?php endif; ?>

		<?php get_template_part( 'template-parts/components/cta', null, array( 'class' => 'cta--article' ) ); ?>
	</div>
	<?php
}

get_footer();
