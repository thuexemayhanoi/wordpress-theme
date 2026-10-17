<?php
/**
 * Comments. Printed only when comments are open or already exist.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

if ( post_password_required() ) {
	return;
}
?>
<section id="comments" class="comments-area" aria-labelledby="comments-title">
	<?php if ( have_comments() ) : ?>
		<h2 id="comments-title" class="comments-title">
			<?php
			$nh_editorial_count = (int) get_comments_number();
			echo esc_html(
				sprintf(
					/* translators: %s: number of comments. */
					_n( '%s comment', '%s comments', $nh_editorial_count, 'nguyen-ha-editorial' ),
					number_format_i18n( $nh_editorial_count )
				)
			);
			?>
		</h2>

		<ol class="comment-list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 48,
				)
			);
			?>
		</ol>

		<?php
		the_comments_pagination(
			array(
				'prev_text' => esc_html__( 'Older comments', 'nguyen-ha-editorial' ),
				'next_text' => esc_html__( 'Newer comments', 'nguyen-ha-editorial' ),
			)
		);
		?>
	<?php endif; ?>

	<?php if ( ! comments_open() && get_comments_number() && post_type_supports( get_post_type(), 'comments' ) ) : ?>
		<p class="no-comments"><?php esc_html_e( 'Comments are closed.', 'nguyen-ha-editorial' ); ?></p>
	<?php endif; ?>

	<?php comment_form(); ?>
</section>
