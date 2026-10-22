<?php
/**
 * English editorial homepage, using the same visual components as the Vietnamese homepage.
 *
 * This template is selected only by Nguyen Ha VI-EN Core for a published /en/ page.
 * The page's own title, excerpt and body provide independent English editorial copy,
 * while category and post sections query only published English posts.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( ! have_posts() ) {
	get_footer();
	return;
}

the_post();
$nh_editorial_en_home_id = get_the_ID();
$nh_editorial_en_title   = get_the_title( $nh_editorial_en_home_id );
$nh_editorial_en_intro   = (string) get_post_field( 'post_excerpt', $nh_editorial_en_home_id );
$nh_editorial_en_body    = (string) get_post_field( 'post_content', $nh_editorial_en_home_id );
$nh_editorial_en_topics  = nh_editorial_search_topics();

// Do not retrieve any articles unless the VI/EN plugin explicitly identifies them as English.
$nh_editorial_en_post_ids = class_exists( 'NH_VIEN_Links' ) ? NH_VIEN_Links::public_en_ids() : array();
$nh_editorial_en_posts    = array();
if ( $nh_editorial_en_post_ids ) {
	$nh_editorial_en_posts = get_posts(
		array(
			'post_type'              => 'post',
			'post_status'            => 'publish',
			'post__in'               => $nh_editorial_en_post_ids,
			'posts_per_page'         => 16,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => true,
			'suppress_filters'       => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);
}
$nh_editorial_en_sorted   = array_map( 'intval', wp_list_pluck( $nh_editorial_en_posts, 'ID' ) );
$nh_editorial_en_featured = array_slice( $nh_editorial_en_sorted, 0, 4 );
$nh_editorial_en_latest   = array_slice( $nh_editorial_en_sorted, 4, 12 );
?>
<section class="home-intro">
	<div class="wrap home-intro__grid">
		<div class="home-intro__text">
			<h1 class="home-intro__title"><?php echo esc_html( $nh_editorial_en_title ); ?></h1>
			<?php if ( '' !== trim( $nh_editorial_en_intro ) ) : ?>
				<p class="home-intro__lead"><?php echo esc_html( $nh_editorial_en_intro ); ?></p>
			<?php endif; ?>
		</div>
		<div class="home-intro__search">
			<p class="home-intro__label" id="home-search-label"><?php esc_html_e( 'What would you like to read?', 'nguyen-ha-editorial' ); ?></p>
			<?php get_search_form( array( 'aria_label' => __( 'Search articles', 'nguyen-ha-editorial' ) ) ); ?>
			<?php if ( ! empty( $nh_editorial_en_topics ) ) : ?>
				<ul class="chips" aria-label="<?php esc_attr_e( 'Popular topics', 'nguyen-ha-editorial' ); ?>">
					<?php foreach ( array_slice( $nh_editorial_en_topics, 0, 5 ) as $nh_editorial_en_topic ) : ?>
						<li><a class="chip" href="<?php echo esc_url( $nh_editorial_en_topic['url'] ); ?>"><?php echo esc_html( $nh_editorial_en_topic['name'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>
</section>

<?php
// A fresh /en/ home created by Core contains a short placeholder shortcode. Replace that
// placeholder with the editorial post grid below, not a duplicated unstyled list.
if ( '' !== trim( $nh_editorial_en_body ) && '[nh_vien_posts]' !== trim( $nh_editorial_en_body ) ) :
	?>
	<section class="section section--page-content">
		<div class="wrap">
			<div class="page-content prose"><?php the_content(); ?></div>
		</div>
	</section>
	<?php
endif;

get_template_part( 'template-parts/home/featured', null, array( 'ids' => $nh_editorial_en_featured ) );
// The VI/EN plugin deliberately uses this separate template, not front-page.php.
// Reuse the exact same verified Pillar/Hub/Silo rendering and language guards.
if ( function_exists( 'nh_editorial_seo_home_matrix' ) ) {
	nh_editorial_seo_home_matrix();
}
get_template_part( 'template-parts/home/hubs' );

// Topic sections reuse the Nguyen Ha card grids, while the VI/EN query context scopes
// their underlying article queries and category archives to English.
get_template_part(
	'template-parts/home/topic-sections',
	null,
	array( 'skip' => array_merge( $nh_editorial_en_featured, $nh_editorial_en_latest ) )
);
?>

<?php if ( $nh_editorial_en_latest ) : ?>
<section class="section section--latest" id="latest" aria-labelledby="latest-title">
	<div class="wrap">
		<?php
		get_template_part(
			'template-parts/components/section-head',
			null,
			array(
				'title' => __( 'Latest articles', 'nguyen-ha-editorial' ),
				'id'    => 'latest-title',
			)
		);
		$nh_editorial_en_query = nh_editorial_posts_by_ids( $nh_editorial_en_latest );
		if ( $nh_editorial_en_query->have_posts() ) :
			?>
			<div class="grid grid--cards">
				<?php
				while ( $nh_editorial_en_query->have_posts() ) {
					$nh_editorial_en_query->the_post();
					get_template_part( 'template-parts/cards/post-card', null, array( 'heading' => 'h3' ) );
				}
				wp_reset_postdata();
				?>
			</div>
			<?php
		endif;
		?>
	</div>
</section>
<?php endif; ?>

<?php
// English call-to-action points only to a real published English contact page.
$nh_editorial_en_contact = get_page_by_path( 'en/contact', OBJECT, 'page' );
if ( $nh_editorial_en_contact instanceof WP_Post && 'publish' === $nh_editorial_en_contact->post_status ) :
	?>
	<div class="wrap section section--cta">
		<aside class="cta cta--home" aria-label="<?php esc_attr_e( 'Get in touch', 'nguyen-ha-editorial' ); ?>">
			<?php $nh_editorial_en_cta = trim( (string) get_post_field( 'post_excerpt', $nh_editorial_en_contact->ID ) ); ?>
			<?php if ( '' !== $nh_editorial_en_cta ) : ?>
				<p class="cta__text"><?php echo esc_html( $nh_editorial_en_cta ); ?></p>
			<?php endif; ?>
			<a class="button cta__button" href="<?php echo esc_url( get_permalink( $nh_editorial_en_contact ) ); ?>"><?php esc_html_e( 'Get in touch', 'nguyen-ha-editorial' ); ?></a>
		</aside>
	</div>
	<?php
endif;
get_footer();
