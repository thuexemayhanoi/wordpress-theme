<?php
/**
 * Color helpers: derive accessible accent tokens from the brand color chosen in the Customizer.
 *
 * Contrast is computed with the WCAG 2.x relative-luminance formula so that link and button colors
 * always reach AA (4.5:1) on the backgrounds they are drawn on, whatever accent the owner picks.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

/**
 * Convert #rgb / #rrggbb to an array of 0-255 integers.
 *
 * @param string $hex Hex color.
 * @return int[]|null array( r, g, b ) or null when invalid.
 */
function nh_editorial_hex_to_rgb( $hex ) {
	$hex = ltrim( (string) $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	if ( ! preg_match( '/^[0-9a-fA-F]{6}$/', $hex ) ) {
		return null;
	}
	return array(
		(int) hexdec( substr( $hex, 0, 2 ) ),
		(int) hexdec( substr( $hex, 2, 2 ) ),
		(int) hexdec( substr( $hex, 4, 2 ) ),
	);
}

/**
 * Convert 0-255 integers to #rrggbb.
 *
 * @param int[] $rgb Channels.
 * @return string
 */
function nh_editorial_rgb_to_hex( array $rgb ) {
	return sprintf(
		'#%02x%02x%02x',
		max( 0, min( 255, (int) round( $rgb[0] ) ) ),
		max( 0, min( 255, (int) round( $rgb[1] ) ) ),
		max( 0, min( 255, (int) round( $rgb[2] ) ) )
	);
}

/**
 * WCAG relative luminance of a hex color.
 *
 * @param string $hex Hex color.
 * @return float 0 (black) to 1 (white).
 */
function nh_editorial_luminance( $hex ) {
	$rgb = nh_editorial_hex_to_rgb( $hex );
	if ( null === $rgb ) {
		return 0.0;
	}
	$channels = array();
	foreach ( $rgb as $value ) {
		$value      = $value / 255;
		$channels[] = $value <= 0.03928 ? $value / 12.92 : pow( ( $value + 0.055 ) / 1.055, 2.4 );
	}
	return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

/**
 * WCAG contrast ratio between two hex colors.
 *
 * @param string $a First color.
 * @param string $b Second color.
 * @return float 1 to 21.
 */
function nh_editorial_contrast_ratio( $a, $b ) {
	$l1 = nh_editorial_luminance( $a );
	$l2 = nh_editorial_luminance( $b );
	return ( max( $l1, $l2 ) + 0.05 ) / ( min( $l1, $l2 ) + 0.05 );
}

/**
 * Mix two hex colors.
 *
 * @param string $from   Base color.
 * @param string $to     Color to mix in.
 * @param float  $amount 0 keeps $from, 1 gives $to.
 * @return string #rrggbb
 */
function nh_editorial_mix( $from, $to, $amount ) {
	$a      = nh_editorial_hex_to_rgb( $from );
	$b      = nh_editorial_hex_to_rgb( $to );
	$amount = max( 0.0, min( 1.0, (float) $amount ) );
	if ( null === $a || null === $b ) {
		return (string) $from;
	}
	return nh_editorial_rgb_to_hex(
		array(
			$a[0] + ( $b[0] - $a[0] ) * $amount,
			$a[1] + ( $b[1] - $a[1] ) * $amount,
			$a[2] + ( $b[2] - $a[2] ) * $amount,
		)
	);
}

/**
 * Move a color toward black or white until it reaches a contrast ratio against a background.
 *
 * @param string $color      Starting color.
 * @param string $background Background color.
 * @param float  $minimum    Required ratio (4.5 for body text).
 * @param string $toward     Color to mix toward (#000000 or #ffffff).
 * @return string
 */
function nh_editorial_ensure_contrast( $color, $background, $minimum, $toward ) {
	for ( $step = 0; $step <= 20; $step++ ) {
		$candidate = nh_editorial_mix( $color, $toward, $step / 20 );
		if ( nh_editorial_contrast_ratio( $candidate, $background ) >= $minimum ) {
			return $candidate;
		}
	}
	return $toward;
}

/**
 * Accent tokens for both color modes.
 *
 * @param string $accent Brand color chosen by the owner.
 * @return array{light: array<string, string>, dark: array<string, string>}
 */
function nh_editorial_accent_tokens( $accent ) {
	$paper_light = '#ffffff';
	$paper_dark  = '#121826';
	$ink         = '#0b1220';

	$light_accent = nh_editorial_ensure_contrast( $accent, $paper_light, 4.5, '#000000' );
	$dark_accent  = nh_editorial_ensure_contrast( $accent, $paper_dark, 4.5, '#ffffff' );

	$on_light = nh_editorial_contrast_ratio( '#ffffff', $light_accent ) >= nh_editorial_contrast_ratio( $ink, $light_accent ) ? '#ffffff' : $ink;
	$on_dark  = nh_editorial_contrast_ratio( '#ffffff', $dark_accent ) >= nh_editorial_contrast_ratio( $ink, $dark_accent ) ? '#ffffff' : $ink;

	return array(
		'light' => array(
			'--accent'        => $light_accent,
			'--accent-strong' => nh_editorial_mix( $light_accent, '#000000', 0.22 ),
			'--accent-soft'   => nh_editorial_mix( $light_accent, '#ffffff', 0.92 ),
			'--on-accent'     => $on_light,
		),
		'dark'  => array(
			'--accent'        => $dark_accent,
			'--accent-strong' => nh_editorial_mix( $dark_accent, '#ffffff', 0.2 ),
			'--accent-soft'   => nh_editorial_mix( $dark_accent, $paper_dark, 0.82 ),
			'--on-accent'     => $on_dark,
		),
	);
}

/**
 * CSS custom properties for the chosen accent, or '' when the default accent is in use.
 *
 * @return string
 */
function nh_editorial_accent_css() {
	$schema  = nh_editorial_settings_schema();
	$accent  = (string) nh_editorial_option( 'nh_accent' );
	$default = (string) $schema['nh_accent']['default'];
	if ( strtolower( $accent ) === strtolower( $default ) ) {
		return '';
	}

	$tokens = nh_editorial_accent_tokens( $accent );
	$render = static function ( array $set ) {
		$css = '';
		foreach ( $set as $name => $value ) {
			$css .= $name . ':' . $value . ';';
		}
		return $css;
	};

	return ':root{' . $render( $tokens['light'] ) . '}'
		. ':root[data-theme="dark"]{' . $render( $tokens['dark'] ) . '}'
		. '@media(prefers-color-scheme:dark){:root:not([data-theme]){' . $render( $tokens['dark'] ) . '}}';
}
