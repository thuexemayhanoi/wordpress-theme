<?php
/**
 * Template Name: Full width page
 * Template Post Type: page
 *
 * Edge-to-edge content for landing pages built with wide and full-width blocks. Keeps the header,
 * breadcrumb-free layout and footer; the page title is still the single H1. An FAQ entered for the page
 * is shown under the content, because the FAQPage structured data may only describe questions a reader sees.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) {
	the_post();
	$nh_editorial_extras = nh_editorial_get_extras( get_the_ID() );
	?>
	<article <?php post_class( 'article article--full' ); ?> id="post-<?php the_ID(); ?>">
		<header class="wrap article-header article-header--full">
			<h1 class="article-title"><?php the_title(); ?></h1>
		</header>
		<div class="entry-content prose prose--full">
			<?php the_content(); ?>
		</div>
		<?php if ( ! empty( $nh_editorial_extras['faq'] ) ) : ?>
			<div class="wrap">
				<?php get_template_part( 'template-parts/components/faq', null, array( 'faq' => $nh_editorial_extras['faq'] ) ); ?>
			</div>
		<?php endif; ?>
	</article>
	<?php
}

get_footer();
