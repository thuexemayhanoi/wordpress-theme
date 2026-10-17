<?php
/**
 * Attachment page: minimal, with a link back to the parent article. WordPress 6.4+ disables attachment
 * pages by default; this only exists so a site that enables them never renders a broken article layout.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) {
	the_post();
	$nh_editorial_parent = get_post_parent();
	?>
	<div class="wrap page-wrap">
		<article <?php post_class( 'article article--attachment' ); ?> id="post-<?php the_ID(); ?>">
			<header class="article-header">
				<h1 class="article-title"><?php the_title(); ?></h1>
			</header>
			<div class="entry-content prose">
				<?php
				if ( wp_attachment_is_image() ) {
					echo wp_get_attachment_image( get_the_ID(), 'large' );
				} else {
					the_content();
				}
				?>
				<?php if ( $nh_editorial_parent ) : ?>
					<p>
						<a href="<?php echo esc_url( get_permalink( $nh_editorial_parent ) ); ?>">
							<?php
							/* translators: %s: article title. */
							echo esc_html( sprintf( __( 'Back to %s', 'nguyen-ha-editorial' ), nh_editorial_plain_title( $nh_editorial_parent ) ) );
							?>
						</a>
					</p>
				<?php endif; ?>
			</div>
		</article>
	</div>
	<?php
}

get_footer();
