<?php
/**
 * Single article (and any other single post type without its own template).
 *
 * Order: breadcrumb, H1 and meta, featured image, answer-first summary and key takeaways, table of
 * contents, body, FAQ, sources, tags, call to action, topic/pillar box, author box, previous/next in
 * the same silo, related articles, widgets, comments.
 *
 * SEO title, meta description, canonical, Open Graph and JSON-LD are the SEO plugin's job; this
 * template only presents the content and never prints them.
 *
 * @package nguyen-ha-editorial
 */

get_header();

while ( have_posts() ) {
	the_post();

	$nh_editorial_post_id  = get_the_ID();
	$nh_editorial_is_post  = 'post' === get_post_type();
	$nh_editorial_article  = nh_editorial_prepare_article();
	$nh_editorial_extras   = nh_editorial_get_extras( $nh_editorial_post_id );
	$nh_editorial_silo     = $nh_editorial_is_post ? nh_editorial_get_silo( $nh_editorial_post_id ) : null;
	$nh_editorial_category = $nh_editorial_is_post ? nh_editorial_primary_category( $nh_editorial_post_id ) : null;
	$nh_editorial_profile  = nh_editorial_get_author_profile( (int) get_the_author_meta( 'ID' ) );
	$nh_editorial_has_toc  = ! empty( $nh_editorial_article['toc'] );
	$nh_editorial_tags     = get_the_tags();
	?>
	<div class="wrap article-wrap">
		<article <?php post_class( 'article' ); ?> id="post-<?php the_ID(); ?>">

			<header class="article-header">
				<?php nh_editorial_breadcrumbs(); ?>

				<?php if ( $nh_editorial_category || ( $nh_editorial_silo && 'meta' === $nh_editorial_silo['type'] ) || ( $nh_editorial_silo && $nh_editorial_silo['term'] && 'category' !== $nh_editorial_silo['term']->taxonomy ) ) : ?>
					<ul class="chips chips--topics">
						<?php if ( $nh_editorial_category && ! is_wp_error( get_term_link( $nh_editorial_category ) ) ) : ?>
							<li><a class="chip chip--accent" href="<?php echo esc_url( get_term_link( $nh_editorial_category ) ); ?>"><?php echo esc_html( $nh_editorial_category->name ); ?></a></li>
						<?php endif; ?>
						<?php if ( $nh_editorial_silo && ( 'meta' === $nh_editorial_silo['type'] || 'category' !== $nh_editorial_silo['term']->taxonomy ) ) : ?>
							<li><span class="chip"><?php echo esc_html( $nh_editorial_silo['label'] ); ?></span></li>
						<?php endif; ?>
					</ul>
				<?php endif; ?>

				<h1 class="article-title"><?php the_title(); ?></h1>

				<?php
				nh_editorial_post_meta(
					array(
						'author'  => true,
						'date'    => true,
						'updated' => true,
						'reading' => true,
					)
				);
				?>
			</header>

			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="article-media">
					<?php echo nh_editorial_thumbnail( $nh_editorial_post_id, 'featured' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by core (get_the_post_thumbnail). ?>
					<?php if ( '' !== get_the_post_thumbnail_caption() ) : ?>
						<figcaption><?php echo wp_kses_post( get_the_post_thumbnail_caption() ); ?></figcaption>
					<?php endif; ?>
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
					wp_link_pages(
						array(
							'before' => '<nav class="page-links" aria-label="' . esc_attr__( 'Article pages', 'nguyen-ha-editorial' ) . '">',
							'after'  => '</nav>',
						)
					);

					get_template_part( 'template-parts/components/faq', null, array( 'faq' => $nh_editorial_extras['faq'] ) );
					get_template_part( 'template-parts/components/sources', null, array( 'sources' => $nh_editorial_extras['sources'] ) );
					?>

					<?php if ( ! empty( $nh_editorial_tags ) ) : ?>
						<ul class="chips chips--tags" aria-label="<?php esc_attr_e( 'Tags', 'nguyen-ha-editorial' ); ?>">
							<?php foreach ( array_slice( $nh_editorial_tags, 0, 8 ) as $nh_editorial_tag ) : ?>
								<li><a class="chip" href="<?php echo esc_url( get_tag_link( $nh_editorial_tag ) ); ?>" rel="tag"><?php echo esc_html( $nh_editorial_tag->name ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>

					<?php
					if ( nh_editorial_option( 'nh_cta_after_post' ) ) {
						get_template_part( 'template-parts/components/cta', null, array( 'class' => 'cta--article' ) );
					}

					if ( $nh_editorial_is_post ) {
						get_template_part(
							'template-parts/components/pillar-box',
							null,
							array(
								'silo'   => $nh_editorial_silo,
								'pillar' => nh_editorial_get_pillar( $nh_editorial_post_id ),
								'hub'    => nh_editorial_get_hub( $nh_editorial_post_id ),
							)
						);
					}

					if ( nh_editorial_author_box_visible( $nh_editorial_profile ) ) {
						get_template_part( 'template-parts/components/author-box', null, array( 'profile' => $nh_editorial_profile ) );
					}
					?>
				</div>
			</div>
		</article>

		<?php
		if ( $nh_editorial_is_post ) {
			get_template_part( 'template-parts/components/post-nav', null, array( 'adjacent' => nh_editorial_get_adjacent_ids( $nh_editorial_post_id ) ) );
			get_template_part( 'template-parts/components/related', null, array( 'post_id' => $nh_editorial_post_id ) );
		}

		get_sidebar();

		if ( comments_open() || get_comments_number() ) {
			comments_template();
		}
		?>
	</div>
	<?php
}

get_footer();
