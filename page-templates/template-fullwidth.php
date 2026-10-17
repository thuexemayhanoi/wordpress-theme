<?php
/**
 * Template Name: Full width page
 * Template Post Type: page
 *
 * Edge-to-edge content for landing pages built with wide and full-width blocks. Keeps the header,
 * breadcrumb-free layout and footer; the page title is still the single H1.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) {
	the_post();
	?>
	<article <?php post_class( 'article article--full' ); ?> id="post-<?php the_ID(); ?>">
		<header class="wrap article-header article-header--full">
			<h1 class="article-title"><?php the_title(); ?></h1>
		</header>
		<div class="entry-content prose prose--full">
			<?php the_content(); ?>
		</div>
	</article>
	<?php
}

get_footer();
