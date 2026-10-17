<?php
/**
 * Table of contents: collapsible, server-rendered, nested by H2/H3.
 *
 * Arguments: items (from nh_editorial_prepare_article()).
 *
 * @package nguyen-ha-editorial
 */

$nh_editorial_items = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
if ( empty( $nh_editorial_items ) ) {
	return;
}

// H3 entries nest under the preceding H2; an H3 with no H2 before it becomes a top-level entry.
$nh_editorial_tree = array();
foreach ( $nh_editorial_items as $nh_editorial_item ) {
	if ( 3 === (int) $nh_editorial_item['level'] && ! empty( $nh_editorial_tree ) ) {
		$nh_editorial_tree[ count( $nh_editorial_tree ) - 1 ]['children'][] = $nh_editorial_item;
	} else {
		$nh_editorial_tree[] = array(
			'item'     => $nh_editorial_item,
			'children' => array(),
		);
	}
}
?>
<nav class="toc" aria-label="<?php esc_attr_e( 'Table of contents', 'nguyen-ha-editorial' ); ?>">
	<details class="toc__details">
		<summary class="toc__summary">
			<?php echo nh_editorial_icon( 'list' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup. ?>
			<span><?php esc_html_e( 'Contents', 'nguyen-ha-editorial' ); ?></span>
			<?php echo nh_editorial_icon( 'chevron-down', 'toc__chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup. ?>
		</summary>
		<ol class="toc__list">
			<?php foreach ( $nh_editorial_tree as $nh_editorial_node ) : ?>
				<li class="toc__item">
					<a href="#<?php echo esc_attr( $nh_editorial_node['item']['id'] ); ?>"><?php echo esc_html( $nh_editorial_node['item']['text'] ); ?></a>
					<?php if ( ! empty( $nh_editorial_node['children'] ) ) : ?>
						<ol class="toc__sub">
							<?php foreach ( $nh_editorial_node['children'] as $nh_editorial_child ) : ?>
								<li class="toc__item toc__item--sub"><a href="#<?php echo esc_attr( $nh_editorial_child['id'] ); ?>"><?php echo esc_html( $nh_editorial_child['text'] ); ?></a></li>
							<?php endforeach; ?>
						</ol>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ol>
	</details>
</nav>
