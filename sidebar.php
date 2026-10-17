<?php
/**
 * Widget area printed below an article (only when it has widgets). It sits after the content in the
 * document so the article text comes first for readers, search engines and assistive technology.
 *
 * @package nguyen-ha-editorial
 */

if ( ! is_active_sidebar( 'article-sidebar' ) ) {
	return;
}
?>
<aside class="widget-area" aria-label="<?php esc_attr_e( 'Sidebar', 'nguyen-ha-editorial' ); ?>">
	<?php dynamic_sidebar( 'article-sidebar' ); ?>
</aside>
