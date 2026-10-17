<?php
/**
 * Author archive. Shows only data the author entered; the page title and meta stay with the SEO plugin.
 *
 * @package nguyen-ha-editorial
 */

get_header();

global $wp_query;
$nh_editorial_author  = get_queried_object();
$nh_editorial_profile = $nh_editorial_author instanceof WP_User ? nh_editorial_get_author_profile( $nh_editorial_author->ID ) : null;
?>
<div class="wrap inner-page">
	<header class="archive-header archive-header--author">
		<?php nh_editorial_breadcrumbs(); ?>
		<div class="author-hero">
			<?php if ( $nh_editorial_profile && '' !== $nh_editorial_profile['avatar'] ) : ?>
				<div class="author-hero__avatar"><?php echo wp_kses_post( $nh_editorial_profile['avatar'] ); ?></div>
			<?php endif; ?>
			<div class="author-hero__body">
				<p class="eyebrow"><?php esc_html_e( 'Author', 'nguyen-ha-editorial' ); ?></p>
				<h1 class="archive-title"><?php echo esc_html( $nh_editorial_profile ? $nh_editorial_profile['name'] : wp_strip_all_tags( get_the_archive_title() ) ); ?></h1>
				<?php if ( $nh_editorial_profile && '' !== $nh_editorial_profile['job'] ) : ?>
					<p class="author-hero__job"><?php echo esc_html( $nh_editorial_profile['job'] ); ?></p>
				<?php endif; ?>
				<p class="archive-meta"><?php echo esc_html( nh_editorial_count_label( (int) $wp_query->found_posts ) ); ?></p>
				<?php if ( $nh_editorial_profile && ! is_paged() && '' !== $nh_editorial_profile['bio'] ) : ?>
					<p class="author-hero__bio"><?php echo esc_html( $nh_editorial_profile['bio'] ); ?></p>
				<?php endif; ?>
				<?php
				if ( $nh_editorial_profile ) {
					get_template_part( 'template-parts/components/social-links', null, array( 'links' => $nh_editorial_profile['links'] ) );
				}
				?>
			</div>
		</div>
	</header>

	<?php
	if ( have_posts() ) {
		get_template_part( 'template-parts/components/post-grid', null, array( 'heading' => 'h2' ) );
	} else {
		get_template_part( 'template-parts/components/empty-state', null, array( 'title' => __( 'No articles yet', 'nguyen-ha-editorial' ) ) );
	}
	?>
</div>
<?php
get_footer();
