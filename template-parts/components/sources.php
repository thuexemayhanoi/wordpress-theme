<?php
/**
 * Reference list ("Sources"). Only references supplied with the article are shown.
 *
 * Arguments: sources (items from nh_editorial_get_extras()).
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

$nh_editorial_sources = isset( $args['sources'] ) && is_array( $args['sources'] ) ? $args['sources'] : array();
if ( empty( $nh_editorial_sources ) ) {
	return;
}
?>
<section class="sources" id="sources" aria-labelledby="sources-title">
	<h2 id="sources-title" class="sources__title"><?php esc_html_e( 'Sources and references', 'nguyen-ha-editorial' ); ?></h2>
	<ol class="sources__list">
		<?php foreach ( $nh_editorial_sources as $nh_editorial_source ) : ?>
			<li>
				<a href="<?php echo esc_url( $nh_editorial_source['url'], array( 'http', 'https' ) ); ?>" rel="noopener external"><?php echo esc_html( $nh_editorial_source['title'] ); ?></a>
				<?php if ( '' !== $nh_editorial_source['publisher'] ) : ?>
					<span class="sources__publisher"><?php echo esc_html( $nh_editorial_source['publisher'] ); ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ol>
</section>
