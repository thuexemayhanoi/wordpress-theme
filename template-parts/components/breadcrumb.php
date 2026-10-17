<?php
/**
 * Breadcrumb: Home › parent › child › current.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

$nh_editorial_crumbs = nh_editorial_breadcrumb_items();
if ( count( $nh_editorial_crumbs ) < 2 ) {
	return;
}
$nh_editorial_last = count( $nh_editorial_crumbs ) - 1;
?>
<nav class="breadcrumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'nguyen-ha-editorial' ); ?>">
	<ol class="breadcrumbs__list">
		<?php foreach ( $nh_editorial_crumbs as $nh_editorial_i => $nh_editorial_crumb ) : ?>
			<?php if ( $nh_editorial_i === $nh_editorial_last ) : ?>
				<li class="breadcrumbs__item breadcrumbs__item--current" aria-current="page"><span><?php echo esc_html( $nh_editorial_crumb['name'] ); ?></span></li>
			<?php elseif ( '' === $nh_editorial_crumb['url'] ) : ?>
				<li class="breadcrumbs__item"><span><?php echo esc_html( $nh_editorial_crumb['name'] ); ?></span></li>
			<?php else : ?>
				<li class="breadcrumbs__item"><a href="<?php echo esc_url( $nh_editorial_crumb['url'] ); ?>"><?php echo esc_html( $nh_editorial_crumb['name'] ); ?></a></li>
			<?php endif; ?>
		<?php endforeach; ?>
	</ol>
</nav>
