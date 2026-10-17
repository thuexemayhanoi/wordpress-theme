<?php
/**
 * Inline SVG icon sprite and helper.
 *
 * One hidden sprite is printed after <body> opens; icons reference it with <use>. Interface icons are
 * simple 24px stroke shapes; social icons are deliberately simplified glyphs (not brand logos) so the
 * theme ships no trademarked artwork. Every icon is decorative: the control that holds it carries the
 * accessible name.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

/**
 * SVG inner markup for each icon. Stroke icons inherit currentColor.
 *
 * @return array<string, string>
 */
function nh_editorial_icon_paths() {
	return array(
		'search'        => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/>',
		'menu'          => '<path d="M4 7h16M4 12h16M4 17h16"/>',
		'close'         => '<path d="M6 6l12 12M18 6 6 18"/>',
		'sun'           => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
		'moon'          => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
		'chevron-down'  => '<path d="m6 9 6 6 6-6"/>',
		'chevron-right' => '<path d="m9 6 6 6-6 6"/>',
		'arrow-right'   => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'arrow-left'    => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
		'clock'         => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'calendar'      => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
		'user'          => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
		'link'          => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
		'phone'         => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
		'mail'          => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
		'map-pin'       => '<path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>',
		'book'          => '<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2V5zM4 19a2 2 0 0 1 2-2h13"/>',
		'layers'        => '<path d="m12 3 9 5-9 5-9-5 9-5zM3 13l9 5 9-5"/>',
		'list'          => '<path d="M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01"/>',
		'external'      => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
		'check'         => '<path d="m5 12 5 5 9-10"/>',
		'help'          => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.7.4-1 .9-1 1.7M12 17h.01"/>',
		'facebook'      => '<path d="M14 21v-8h2.7l.5-3.2H14V7.9c0-.9.4-1.6 1.7-1.6h1.6V3.5c-.3 0-1.3-.2-2.5-.2-2.5 0-4.1 1.5-4.1 4.2v2.3H8v3.2h2.7v8h3.3z"/>',
		'x'             => '<path d="M5 4l14 16M19 4 5 20"/>',
		'instagram'     => '<rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17.3 6.7h.01"/>',
		'youtube'       => '<rect x="2.5" y="5.5" width="19" height="13" rx="4"/><path d="m10.2 9.2 4.6 2.8-4.6 2.8z"/>',
		'linkedin'      => '<path d="M5 9.5V19M5 5.5v.01M10 19v-9.5M10 13.2a3.2 3.2 0 0 1 6.4 0V19"/>',
		'tiktok'        => '<path d="M14 4v10.5a3.5 3.5 0 1 1-3.5-3.5M14 4c.5 2.5 2 4 5 4"/>',
		'pinterest'     => '<path d="M9 21l2-9M8 12.5a4 4 0 1 1 6.5 3"/>',
		'zalo'          => '<path d="M6 7h12L6 17h12"/>',
		'telegram'      => '<path d="M21 3 3 10.5l6 2.5 2.5 6L21 3zM9 13l5-4"/>',
		'whatsapp'      => '<path d="M3 21l1.6-4.6A8.5 8.5 0 1 1 8 19.6L3 21z"/>',
	);
}

/**
 * Names of icons that are drawn filled instead of stroked.
 *
 * @return string[]
 */
function nh_editorial_filled_icons() {
	return array( 'facebook' );
}

/**
 * Markup for one decorative icon that references the sprite.
 *
 * @param string $name    Icon name.
 * @param string $classes Extra CSS classes.
 * @return string Safe HTML.
 */
function nh_editorial_icon( $name, $classes = '' ) {
	$paths = nh_editorial_icon_paths();
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	$classes = trim( 'icon icon--' . sanitize_html_class( $name ) . ' ' . $classes );
	return '<svg class="' . esc_attr( $classes ) . '" width="20" height="20" aria-hidden="true" focusable="false"><use href="#nh-i-' . esc_attr( $name ) . '"></use></svg>';
}

/**
 * Print the hidden sprite once, right after <body> opens.
 *
 * @return void
 */
function nh_editorial_print_sprite() {
	$filled = nh_editorial_filled_icons();
	$out    = '<svg xmlns="http://www.w3.org/2000/svg" class="icon-sprite" width="0" height="0" aria-hidden="true" focusable="false" style="position:absolute;width:0;height:0;overflow:hidden">';
	foreach ( nh_editorial_icon_paths() as $name => $markup ) {
		$paint = in_array( $name, $filled, true )
			? 'fill="currentColor" stroke="none"'
			: 'fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"';
		$out  .= '<symbol id="nh-i-' . esc_attr( $name ) . '" viewBox="0 0 24 24" ' . $paint . '>' . $markup . '</symbol>';
	}
	$out .= '</svg>';

	// Static markup assembled from the constants above; nothing user-controlled is included.
	echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action( 'wp_body_open', 'nh_editorial_print_sprite', 1 );
