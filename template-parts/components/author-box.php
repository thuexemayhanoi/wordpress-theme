<?php
/**
 * Author box. Shows only what the author entered (name, bio, job title, links).
 *
 * Arguments: profile (from nh_editorial_get_author_profile()), tag (heading tag for the name).
 *
 * @package nguyen-ha-editorial
 */

$nh_editorial_profile = isset( $args['profile'] ) && is_array( $args['profile'] ) ? $args['profile'] : array();
if ( empty( $nh_editorial_profile ) ) {
	return;
}
?>
<section class="author-box" aria-labelledby="author-box-title">
	<h2 id="author-box-title" class="screen-reader-text"><?php esc_html_e( 'About the author', 'nguyen-ha-editorial' ); ?></h2>
	<?php if ( '' !== $nh_editorial_profile['avatar'] ) : ?>
		<div class="author-box__avatar"><?php echo wp_kses_post( $nh_editorial_profile['avatar'] ); ?></div>
	<?php endif; ?>
	<div class="author-box__body">
		<p class="author-box__name">
			<a href="<?php echo esc_url( $nh_editorial_profile['url'] ); ?>" rel="author"><?php echo esc_html( $nh_editorial_profile['name'] ); ?></a>
		</p>
		<?php if ( '' !== $nh_editorial_profile['job'] ) : ?>
			<p class="author-box__job"><?php echo esc_html( $nh_editorial_profile['job'] ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $nh_editorial_profile['bio'] ) : ?>
			<p class="author-box__bio"><?php echo esc_html( $nh_editorial_profile['bio'] ); ?></p>
		<?php endif; ?>
		<?php get_template_part( 'template-parts/components/social-links', null, array( 'links' => $nh_editorial_profile['links'] ) ); ?>
		<a class="author-box__more" href="<?php echo esc_url( $nh_editorial_profile['url'] ); ?>">
			<?php
			/* translators: %s: author name. */
			echo esc_html( sprintf( __( 'All articles by %s', 'nguyen-ha-editorial' ), $nh_editorial_profile['name'] ) );
			?>
		</a>
	</div>
</section>
