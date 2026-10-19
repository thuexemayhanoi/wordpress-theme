<?php
/**
 * Test fixture: the one template of a theme that never calls wp_body_open().
 *
 * @package Nguyen_Ha_VI_EN
 */

defined( 'ABSPATH' ) || exit;

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<main>
<?php
while ( have_posts() ) :
	the_post();
	?>
<article>
	<h1><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h1>
	<?php the_content(); ?>
</article>
<?php endwhile; ?>
</main>
<?php wp_footer(); ?>
</body>
</html>
