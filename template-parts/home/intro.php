<?php
/**
 * Homepage introduction: the page's single H1, a short description and the search box.
 *
 * The headline and text come from the Customizer (default: site title and tagline); nothing about the
 * business is hard-coded here.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

$nh_editorial_headline = (string) nh_editorial_option( 'nh_home_headline' );
$nh_editorial_headline = '' !== $nh_editorial_headline ? $nh_editorial_headline : get_bloginfo( 'name' );
$nh_editorial_subline  = (string) nh_editorial_option( 'nh_home_subline' );
$nh_editorial_subline  = '' !== $nh_editorial_subline ? $nh_editorial_subline : get_bloginfo( 'description' );
$nh_editorial_topics   = nh_editorial_search_topics();
?>
<section class="home-intro">
	<div class="wrap home-intro__grid">
		<div class="home-intro__text">
			<h1 class="home-intro__title"><?php echo esc_html( $nh_editorial_headline ); ?></h1>
			<?php if ( '' !== $nh_editorial_subline ) : ?>
				<p class="home-intro__lead"><?php echo esc_html( $nh_editorial_subline ); ?></p>
			<?php endif; ?>
		</div>
		<div class="home-intro__search">
			<p class="home-intro__label" id="home-search-label"><?php esc_html_e( 'What would you like to read?', 'nguyen-ha-editorial' ); ?></p>
			<?php get_search_form( array( 'aria_label' => __( 'Search articles', 'nguyen-ha-editorial' ) ) ); ?>
			<?php if ( ! empty( $nh_editorial_topics ) ) : ?>
				<ul class="chips" aria-label="<?php esc_attr_e( 'Popular topics', 'nguyen-ha-editorial' ); ?>">
					<?php foreach ( array_slice( $nh_editorial_topics, 0, 5 ) as $nh_editorial_topic ) : ?>
						<li><a class="chip" href="<?php echo esc_url( $nh_editorial_topic['url'] ); ?>"><?php echo esc_html( $nh_editorial_topic['name'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>
</section>
