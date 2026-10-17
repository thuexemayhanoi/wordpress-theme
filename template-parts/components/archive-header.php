<?php
/**
 * Header of an archive-like page: breadcrumb, eyebrow, the template's single H1, description.
 *
 * Arguments: title (required, plain text), eyebrow, description (HTML already sanitized by the
 * caller), meta (plain text such as the article count).
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

$nh_editorial_header = wp_parse_args(
	isset( $args ) && is_array( $args ) ? $args : array(),
	array(
		'title'       => '',
		'eyebrow'     => '',
		'description' => '',
		'meta'        => '',
	)
);
?>
<header class="archive-header">
	<?php nh_editorial_breadcrumbs(); ?>
	<?php if ( '' !== $nh_editorial_header['eyebrow'] ) : ?>
		<p class="eyebrow"><?php echo esc_html( $nh_editorial_header['eyebrow'] ); ?></p>
	<?php endif; ?>
	<h1 class="archive-title"><?php echo esc_html( $nh_editorial_header['title'] ); ?></h1>
	<?php if ( '' !== $nh_editorial_header['meta'] ) : ?>
		<p class="archive-meta"><?php echo esc_html( $nh_editorial_header['meta'] ); ?></p>
	<?php endif; ?>
	<?php if ( '' !== $nh_editorial_header['description'] ) : ?>
		<div class="archive-description prose"><?php echo wp_kses_post( $nh_editorial_header['description'] ); ?></div>
	<?php endif; ?>
</header>
