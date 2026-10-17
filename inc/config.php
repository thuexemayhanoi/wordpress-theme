<?php
/**
 * Central theme configuration.
 *
 * One schema describes every presentation setting: its default, sanitizer and Customizer control.
 * The Customizer (inc/customizer.php), the getters below and the tests all read this schema, so a
 * change here propagates everywhere. Business and SEO data are NOT stored here: they live in the
 * companion plugin and are read through the `nguyen_ha_business_profile` filter.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

/**
 * Schema of all presentation settings, keyed by theme_mod name.
 *
 * Each entry: section, type (color|image|checkbox|number|select|text|url|urls|kses), default, label,
 * optional description, min/max (number), choices (select).
 *
 * @return array<string, array<string, mixed>>
 */
function nh_editorial_settings_schema() {
	static $schema = null;

	if ( null !== $schema ) {
		return $schema;
	}

	$schema = array(
		'nh_accent'         => array(
			'section'     => 'brand',
			'type'        => 'color',
			'default'     => '#0b57d0',
			'label'       => __( 'Accent color', 'nguyen-ha-editorial' ),
			'description' => __( 'Used for links, buttons and highlights. Colors that are too light for readable text are darkened automatically (WCAG AA).', 'nguyen-ha-editorial' ),
		),
		'nh_logo_dark'      => array(
			'section'     => 'brand',
			'type'        => 'image',
			'default'     => 0,
			'label'       => __( 'Logo for dark mode (optional)', 'nguyen-ha-editorial' ),
			'description' => __( 'Shown instead of the main logo when dark mode is active. The main logo is set under Site Identity.', 'nguyen-ha-editorial' ),
		),
		'nh_dark_mode'      => array(
			'section'     => 'layout',
			'type'        => 'checkbox',
			'default'     => true,
			'label'       => __( 'Enable dark mode', 'nguyen-ha-editorial' ),
			'description' => __( 'Follows the visitor preference and shows a toggle in the header.', 'nguyen-ha-editorial' ),
		),
		'nh_sticky_header'  => array(
			'section' => 'layout',
			'type'    => 'checkbox',
			'default' => true,
			'label'   => __( 'Sticky header', 'nguyen-ha-editorial' ),
		),
		'nh_search_suggest' => array(
			'section'     => 'layout',
			'type'        => 'checkbox',
			'default'     => true,
			'label'       => __( 'Search suggestions', 'nguyen-ha-editorial' ),
			'description' => __( 'Shows up to five matching articles while typing, using the built-in WordPress REST search. Turn off if REST is blocked.', 'nguyen-ha-editorial' ),
		),
		'nh_toc'            => array(
			'section' => 'article',
			'type'    => 'checkbox',
			'default' => true,
			'label'   => __( 'Table of contents on articles', 'nguyen-ha-editorial' ),
		),
		'nh_toc_min'        => array(
			'section'     => 'article',
			'type'        => 'number',
			'default'     => 3,
			'min'         => 2,
			'max'         => 10,
			'label'       => __( 'Minimum headings for a table of contents', 'nguyen-ha-editorial' ),
			'description' => __( 'The table of contents is built from H2 and H3 headings and hidden below this number.', 'nguyen-ha-editorial' ),
		),
		'nh_reading_time'   => array(
			'section' => 'article',
			'type'    => 'checkbox',
			'default' => true,
			'label'   => __( 'Show reading time', 'nguyen-ha-editorial' ),
		),
		'nh_author_box'     => array(
			'section' => 'article',
			'type'    => 'select',
			'default' => 'with_bio',
			'choices' => array(
				'with_bio' => __( 'Only when the author has a biography', 'nguyen-ha-editorial' ),
				'always'   => __( 'Always', 'nguyen-ha-editorial' ),
				'never'    => __( 'Never', 'nguyen-ha-editorial' ),
			),
			'label'   => __( 'Author box', 'nguyen-ha-editorial' ),
		),
		'nh_related_count'  => array(
			'section' => 'article',
			'type'    => 'select',
			'default' => '3',
			'choices' => array(
				'3' => '3',
				'4' => '4',
				'6' => '6',
			),
			'label'   => __( 'Related articles', 'nguyen-ha-editorial' ),
		),
		'nh_home_headline'  => array(
			'section'     => 'home',
			'type'        => 'text',
			'default'     => '',
			'label'       => __( 'Homepage headline (H1)', 'nguyen-ha-editorial' ),
			'description' => __( 'Leave empty to use the site title.', 'nguyen-ha-editorial' ),
		),
		'nh_home_subline'   => array(
			'section'     => 'home',
			'type'        => 'text',
			'default'     => '',
			'label'       => __( 'Homepage introduction', 'nguyen-ha-editorial' ),
			'description' => __( 'Leave empty to use the tagline.', 'nguyen-ha-editorial' ),
		),
		'nh_home_pillars'   => array(
			'section'     => 'home',
			'type'        => 'number',
			'default'     => 3,
			'min'         => 0,
			'max'         => 6,
			'label'       => __( 'Topic sections on the homepage', 'nguyen-ha-editorial' ),
			'description' => __( 'Latest articles from the largest top-level categories. 0 hides them.', 'nguyen-ha-editorial' ),
		),
		'nh_home_latest'    => array(
			'section'     => 'home',
			'type'        => 'number',
			'default'     => 9,
			'min'         => 6,
			'max'         => 24,
			'label'       => __( 'Latest articles on a static front page', 'nguyen-ha-editorial' ),
			'description' => __( 'Only used when the front page is a static page. With "Your latest posts" the Reading setting applies and pages are paginated.', 'nguyen-ha-editorial' ),
		),
		'nh_cta_label'      => array(
			'section' => 'cta',
			'type'    => 'text',
			'default' => '',
			'label'   => __( 'Button label', 'nguyen-ha-editorial' ),
		),
		'nh_cta_url'        => array(
			'section'     => 'cta',
			'type'        => 'url',
			'default'     => '',
			'label'       => __( 'Button link', 'nguyen-ha-editorial' ),
			'description' => __( 'A page, a tel: or mailto: link, or a messaging link. The call-to-action band stays hidden until label and link are set.', 'nguyen-ha-editorial' ),
		),
		'nh_cta_text'       => array(
			'section' => 'cta',
			'type'    => 'text',
			'default' => '',
			'label'   => __( 'Short message', 'nguyen-ha-editorial' ),
		),
		'nh_cta_after_post' => array(
			'section' => 'cta',
			'type'    => 'checkbox',
			'default' => true,
			'label'   => __( 'Show the call-to-action after articles', 'nguyen-ha-editorial' ),
		),
		'nh_social_urls'    => array(
			'section'     => 'footer',
			'type'        => 'urls',
			'default'     => '',
			'label'       => __( 'Social profile links', 'nguyen-ha-editorial' ),
			'description' => __( 'One URL per line. Used for the footer icons. Leave empty to use the profiles of the companion plugin.', 'nguyen-ha-editorial' ),
		),
		'nh_footer_text'    => array(
			'section'     => 'footer',
			'type'        => 'kses',
			'default'     => '',
			'label'       => __( 'Footer note', 'nguyen-ha-editorial' ),
			'description' => __( 'Basic formatting and links are allowed.', 'nguyen-ha-editorial' ),
		),
	);

	return $schema;
}

/**
 * Allowed HTML for the footer note.
 *
 * @return array<string, array<string, bool>>
 */
function nh_editorial_footer_kses() {
	return array(
		'a'      => array(
			'href'   => true,
			'rel'    => true,
			'target' => true,
		),
		'br'     => array(),
		'strong' => array(),
		'em'     => array(),
	);
}

/**
 * Protocols accepted for configurable links (CTA, social, footer note).
 *
 * @return string[]
 */
function nh_editorial_link_protocols() {
	return array( 'http', 'https', 'tel', 'mailto' );
}

/**
 * Normalize a textarea of URLs: one valid http(s) URL per line, unique, at most 12.
 *
 * @param mixed $value Raw value (string or array).
 * @return string Newline separated URLs.
 */
function nh_editorial_sanitize_urls( $value ) {
	$lines = is_array( $value ) ? $value : preg_split( '/\R/u', (string) $value );
	$clean = array();
	foreach ( (array) $lines as $line ) {
		if ( ! is_scalar( $line ) ) {
			continue;
		}
		$url = esc_url_raw( trim( (string) $line ), array( 'http', 'https' ) );
		if ( '' !== $url ) {
			$clean[] = $url;
		}
	}
	return implode( "\n", array_slice( array_values( array_unique( $clean ) ), 0, 12 ) );
}

/**
 * Sanitize one setting according to the schema. Unknown keys return null.
 *
 * @param string $key   Setting key.
 * @param mixed  $value Raw value.
 * @return mixed
 */
function nh_editorial_sanitize_setting( $key, $value ) {
	$schema = nh_editorial_settings_schema();
	if ( ! isset( $schema[ $key ] ) ) {
		return null;
	}
	$def = $schema[ $key ];

	switch ( $def['type'] ) {
		case 'checkbox':
			return true === $value || 1 === $value || '1' === $value || 'on' === $value || 'true' === $value;

		case 'color':
			$color = is_scalar( $value ) ? sanitize_hex_color( (string) $value ) : '';
			return $color ? strtolower( $color ) : $def['default'];

		case 'image':
			return absint( $value );

		case 'number':
			$number = is_numeric( $value ) ? (int) $value : (int) $def['default'];
			return max( (int) $def['min'], min( (int) $def['max'], $number ) );

		case 'select':
			$choice = is_scalar( $value ) ? (string) $value : '';
			return array_key_exists( $choice, $def['choices'] ) ? $choice : (string) $def['default'];

		case 'url':
			return is_scalar( $value ) ? esc_url_raw( trim( (string) $value ), nh_editorial_link_protocols() ) : '';

		case 'urls':
			return nh_editorial_sanitize_urls( $value );

		case 'kses':
			return is_scalar( $value ) ? wp_kses( (string) $value, nh_editorial_footer_kses(), nh_editorial_link_protocols() ) : '';

		default:
			return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	}
}

/**
 * Read one presentation setting (sanitized on read, filterable).
 *
 * @param string $key Setting key from nh_editorial_settings_schema().
 * @return mixed
 */
function nh_editorial_option( $key ) {
	$schema = nh_editorial_settings_schema();
	if ( ! isset( $schema[ $key ] ) ) {
		return null;
	}

	$value = nh_editorial_sanitize_setting( $key, get_theme_mod( $key, $schema[ $key ]['default'] ) );

	/**
	 * Filters a presentation setting.
	 *
	 * @param mixed  $value Sanitized value.
	 * @param string $key   Setting key.
	 */
	return apply_filters( 'nh_editorial_option', $value, $key );
}

/**
 * Verified business profile supplied by the companion plugin (empty array without it).
 *
 * Only known keys are returned, as plain strings/arrays; callers must escape on output.
 *
 * @return array<string, mixed>
 */
function nh_editorial_business() {
	/**
	 * Filters the verified business profile. The companion plugin supplies it.
	 *
	 * @param array $profile Empty array by default: the theme never invents business data.
	 */
	$profile = apply_filters( 'nguyen_ha_business_profile', array() );
	if ( ! is_array( $profile ) ) {
		return array();
	}

	$scalar = array( 'name', 'legal_name', 'phone', 'email', 'street', 'locality', 'region', 'postal', 'country', 'has_map', 'url', 'price_range' );
	$clean  = array();
	foreach ( $scalar as $key ) {
		$clean[ $key ] = isset( $profile[ $key ] ) && is_scalar( $profile[ $key ] ) ? trim( (string) $profile[ $key ] ) : '';
	}
	$clean['hours']  = isset( $profile['hours'] ) && is_array( $profile['hours'] ) ? array_values( array_filter( array_map( 'strval', $profile['hours'] ) ) ) : array();
	$clean['social'] = isset( $profile['social'] ) && is_array( $profile['social'] ) ? array_values( array_filter( array_map( 'strval', $profile['social'] ) ) ) : array();

	return $clean;
}

/**
 * Whether the business profile has anything worth showing in the footer.
 *
 * @return bool
 */
function nh_editorial_has_business_contact() {
	$business = nh_editorial_business();
	return '' !== $business['phone'] || '' !== $business['email'] || '' !== $business['street'] || '' !== $business['locality'];
}

/**
 * Call-to-action configuration, or an empty array when it is not fully configured.
 *
 * @return array{label: string, url: string, text: string}|array{}
 */
function nh_editorial_cta() {
	$label = (string) nh_editorial_option( 'nh_cta_label' );
	$url   = (string) nh_editorial_option( 'nh_cta_url' );
	if ( '' === $label || '' === $url ) {
		return array();
	}
	return array(
		'label' => $label,
		'url'   => $url,
		'text'  => (string) nh_editorial_option( 'nh_cta_text' ),
	);
}

/**
 * Known social networks by host, with a human label and the icon used.
 *
 * @return array<string, array{label: string, icon: string}>
 */
function nh_editorial_social_networks() {
	return array(
		'facebook.com'  => array(
			'label' => 'Facebook',
			'icon'  => 'facebook',
		),
		'fb.com'        => array(
			'label' => 'Facebook',
			'icon'  => 'facebook',
		),
		'x.com'         => array(
			'label' => 'X',
			'icon'  => 'x',
		),
		'twitter.com'   => array(
			'label' => 'X',
			'icon'  => 'x',
		),
		'instagram.com' => array(
			'label' => 'Instagram',
			'icon'  => 'instagram',
		),
		'youtube.com'   => array(
			'label' => 'YouTube',
			'icon'  => 'youtube',
		),
		'youtu.be'      => array(
			'label' => 'YouTube',
			'icon'  => 'youtube',
		),
		'linkedin.com'  => array(
			'label' => 'LinkedIn',
			'icon'  => 'linkedin',
		),
		'tiktok.com'    => array(
			'label' => 'TikTok',
			'icon'  => 'tiktok',
		),
		'pinterest.com' => array(
			'label' => 'Pinterest',
			'icon'  => 'pinterest',
		),
		'zalo.me'       => array(
			'label' => 'Zalo',
			'icon'  => 'zalo',
		),
		't.me'          => array(
			'label' => 'Telegram',
			'icon'  => 'telegram',
		),
		'wa.me'         => array(
			'label' => 'WhatsApp',
			'icon'  => 'whatsapp',
		),
		'whatsapp.com'  => array(
			'label' => 'WhatsApp',
			'icon'  => 'whatsapp',
		),
	);
}

/**
 * Describe a profile URL: label and icon from its host.
 *
 * @param string $url Absolute http(s) URL.
 * @return array{url: string, label: string, icon: string}|null Null for invalid URLs.
 */
function nh_editorial_describe_social_url( $url ) {
	$url = esc_url_raw( (string) $url, array( 'http', 'https' ) );
	if ( '' === $url ) {
		return null;
	}
	$host = wp_parse_url( $url, PHP_URL_HOST );
	if ( ! $host ) {
		return null;
	}
	$host     = strtolower( preg_replace( '/^www\./', '', $host ) );
	$networks = nh_editorial_social_networks();

	foreach ( $networks as $domain => $network ) {
		if ( $host === $domain || str_ends_with( $host, '.' . $domain ) ) {
			return array(
				'url'   => $url,
				'label' => $network['label'],
				'icon'  => $network['icon'],
			);
		}
	}
	return array(
		'url'   => $url,
		'label' => $host,
		'icon'  => 'link',
	);
}

/**
 * Social profile links: theme setting first, then the business profile.
 *
 * @return array<int, array{url: string, label: string, icon: string}>
 */
function nh_editorial_social_links() {
	$lines = (string) nh_editorial_option( 'nh_social_urls' );
	$urls  = '' !== $lines ? preg_split( '/\R/u', $lines ) : array();
	if ( empty( $urls ) ) {
		$business = nh_editorial_business();
		$urls     = $business['social'];
	}

	$links = array();
	foreach ( $urls as $url ) {
		$item = nh_editorial_describe_social_url( $url );
		if ( $item ) {
			$links[] = $item;
		}
	}
	return $links;
}
