<?php
/**
 * Search form (used in the header, search page, 404 and empty states).
 *
 * Arguments from get_search_form(): aria_label, suggest (adds the suggestions container the script
 * fills from the core REST search route).
 *
 * @package nguyen-ha-editorial
 */

$nh_editorial_form    = isset( $args ) && is_array( $args ) ? $args : array();
$nh_editorial_id      = wp_unique_id( 'search-field-' );
$nh_editorial_label   = ! empty( $nh_editorial_form['aria_label'] ) ? $nh_editorial_form['aria_label'] : __( 'Search articles', 'nguyen-ha-editorial' );
$nh_editorial_suggest = ! empty( $nh_editorial_form['suggest'] ) && '' !== nh_editorial_search_suggest_attributes();
?>
<form role="search" aria-label="<?php echo esc_attr( $nh_editorial_label ); ?>" method="get" class="search-form<?php echo $nh_editorial_suggest ? ' has-suggest' : ''; ?>" action="<?php echo esc_url( home_url( '/' ) ); ?>"<?php echo $nh_editorial_suggest ? nh_editorial_search_suggest_attributes() : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside the helper. ?>>
	<label class="screen-reader-text" for="<?php echo esc_attr( $nh_editorial_id ); ?>"><?php esc_html_e( 'Search for:', 'nguyen-ha-editorial' ); ?></label>
	<input type="search" id="<?php echo esc_attr( $nh_editorial_id ); ?>" class="search-field" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php esc_attr_e( 'Search guides and answers…', 'nguyen-ha-editorial' ); ?>" enterkeyhint="search"<?php echo $nh_editorial_suggest ? ' autocomplete="off"' : ''; ?>>
	<button type="submit" class="search-submit">
		<?php echo nh_editorial_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup. ?>
		<span class="screen-reader-text"><?php esc_html_e( 'Search', 'nguyen-ha-editorial' ); ?></span>
	</button>
	<?php if ( $nh_editorial_suggest ) : ?>
	<p class="screen-reader-text search-suggest__status" role="status" aria-live="polite"></p>
	<div class="search-suggest" hidden>
		<ul class="search-suggest__list"></ul>
	</div>
	<?php endif; ?>
</form>
