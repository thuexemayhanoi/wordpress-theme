<?php
/**
 * Shared API: keys, sanitizers, getters and detection helpers.
 *
 * This file is the contract between the companion plugin, the Nguyen Ha Editorial theme and the
 * AI Writer workflow. Behaviour is documented in docs/DATA-CONTRACT.md. Sanitizers expect
 * UNSLASHED input (REST payloads already are; admin form handlers must call wp_unslash() first).
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * HTML allowed inside FAQ answers (enforced on save and again on output).
 *
 * @return array<string, array<string, bool>> wp_kses allowed-tags array.
 */
function nh_seo_allowed_html() {
	$allowed = array(
		'a'      => array(
			'href'  => true,
			'title' => true,
			'rel'   => true,
		),
		'p'      => array(),
		'br'     => array(),
		'strong' => array(),
		'b'      => array(),
		'em'     => array(),
		'i'      => array(),
		'ul'     => array(),
		'ol'     => array(),
		'li'     => array(),
		'code'   => array(),
	);

	/**
	 * Filters the HTML allowed inside FAQ answers.
	 *
	 * @param array $allowed wp_kses allowed-tags array.
	 */
	return apply_filters( 'nh_seo_allowed_html', $allowed );
}

/**
 * Protocols allowed for links inside FAQ answers and for source URLs.
 *
 * @return string[]
 */
function nh_seo_allowed_protocols() {
	return array( 'http', 'https' );
}

/**
 * Truncate a string to a number of characters (multibyte safe).
 *
 * @param string $text  Text.
 * @param int    $limit Maximum characters.
 * @return string
 */
function nh_seo_truncate( $text, $limit ) {
	$text  = (string) $text;
	$limit = max( 0, (int) $limit );
	if ( mb_strlen( $text ) <= $limit ) {
		return $text;
	}
	return rtrim( mb_substr( $text, 0, $limit ) );
}

/**
 * Sanitize a single line of plain text with a length limit.
 *
 * @param mixed $value Raw value.
 * @param int   $limit Maximum characters.
 * @return string
 */
function nh_seo_sanitize_line( $value, $limit ) {
	if ( ! is_scalar( $value ) ) {
		return '';
	}
	return nh_seo_truncate( sanitize_text_field( (string) $value ), $limit );
}

/**
 * Sanitize the answer-first summary: plain text, whitespace collapsed.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function nh_seo_sanitize_summary( $value ) {
	if ( ! is_scalar( $value ) ) {
		return '';
	}
	$text = wp_strip_all_tags( (string) $value );
	$text = trim( preg_replace( '/\s+/u', ' ', $text ) );
	return nh_seo_truncate( $text, NH_SEO_Keys::LIMIT_SUMMARY );
}

/**
 * Turn a textarea string (one item per line) or an array into a clean list of lines.
 *
 * @param mixed $value Raw value.
 * @return array<int, mixed>
 */
function nh_seo_to_list( $value ) {
	if ( is_string( $value ) ) {
		$value = preg_split( '/\R/u', $value );
	}
	return is_array( $value ) ? array_values( $value ) : array();
}

/**
 * Sanitize key takeaways.
 *
 * @param mixed $value Array of strings or newline separated string.
 * @return string[]
 */
function nh_seo_sanitize_takeaways( $value ) {
	$clean = array();
	foreach ( nh_seo_to_list( $value ) as $item ) {
		$line = nh_seo_sanitize_line( $item, NH_SEO_Keys::LIMIT_TAKEAWAY_CHARS );
		if ( '' === $line ) {
			continue;
		}
		$clean[] = $line;
		if ( count( $clean ) >= NH_SEO_Keys::LIMIT_TAKEAWAYS ) {
			break;
		}
	}
	return $clean;
}

/**
 * Sanitize the HTML of one FAQ answer.
 *
 * @param mixed $value Raw HTML.
 * @return string
 */
function nh_seo_sanitize_answer( $value ) {
	if ( ! is_scalar( $value ) ) {
		return '';
	}
	$html = nh_seo_truncate( trim( (string) $value ), NH_SEO_Keys::LIMIT_FAQ_ANSWER );
	$html = wp_kses( $html, nh_seo_allowed_html(), nh_seo_allowed_protocols() );
	return trim( force_balance_tags( $html ) );
}

/**
 * Sanitize FAQ items. Accepts question/answer keys or the short q/a aliases, or a JSON string.
 *
 * @param mixed $value Array of items or JSON string.
 * @return array<int, array{question: string, answer: string}>
 */
function nh_seo_sanitize_faq( $value ) {
	if ( is_string( $value ) ) {
		$decoded = json_decode( $value, true );
		$value   = is_array( $decoded ) ? $decoded : array();
	}
	if ( ! is_array( $value ) ) {
		return array();
	}

	$clean = array();
	foreach ( $value as $item ) {
		$item = is_object( $item ) ? get_object_vars( $item ) : $item;
		if ( ! is_array( $item ) ) {
			continue;
		}
		$question = isset( $item['question'] ) ? $item['question'] : ( isset( $item['q'] ) ? $item['q'] : '' );
		$answer   = isset( $item['answer'] ) ? $item['answer'] : ( isset( $item['a'] ) ? $item['a'] : '' );

		$question = nh_seo_sanitize_line( $question, NH_SEO_Keys::LIMIT_FAQ_QUESTION );
		$answer   = nh_seo_sanitize_answer( $answer );
		if ( '' === $question || '' === wp_strip_all_tags( $answer ) ) {
			continue;
		}

		$clean[] = array(
			'question' => $question,
			'answer'   => $answer,
		);
		if ( count( $clean ) >= NH_SEO_Keys::LIMIT_FAQ_ITEMS ) {
			break;
		}
	}
	return $clean;
}

/**
 * Sanitize reference sources. A source needs a valid http(s) URL.
 *
 * @param mixed $value Array of items or JSON string.
 * @return array<int, array{title: string, url: string, publisher: string}>
 */
function nh_seo_sanitize_sources( $value ) {
	if ( is_string( $value ) ) {
		$decoded = json_decode( $value, true );
		$value   = is_array( $decoded ) ? $decoded : array();
	}
	if ( ! is_array( $value ) ) {
		return array();
	}

	$clean = array();
	foreach ( $value as $item ) {
		$item = is_object( $item ) ? get_object_vars( $item ) : $item;
		if ( ! is_array( $item ) || empty( $item['url'] ) || ! is_scalar( $item['url'] ) ) {
			continue;
		}
		$url = esc_url_raw( trim( (string) $item['url'] ), nh_seo_allowed_protocols() );
		if ( '' === $url ) {
			continue;
		}

		$title = isset( $item['title'] ) ? nh_seo_sanitize_line( $item['title'], NH_SEO_Keys::LIMIT_SOURCE_TITLE ) : '';
		if ( '' === $title ) {
			$host  = wp_parse_url( $url, PHP_URL_HOST );
			$title = $host ? (string) $host : $url;
		}
		$publisher = isset( $item['publisher'] ) ? nh_seo_sanitize_line( $item['publisher'], NH_SEO_Keys::LIMIT_SOURCE_PUBLISHER ) : '';

		$clean[] = array(
			'title'     => $title,
			'url'       => $url,
			'publisher' => $publisher,
		);
		if ( count( $clean ) >= NH_SEO_Keys::LIMIT_SOURCES ) {
			break;
		}
	}
	return $clean;
}

/**
 * Sanitize a real content-update date. Returns Y-m-d, or '' when invalid or in the future.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function nh_seo_sanitize_date( $value ) {
	if ( ! is_scalar( $value ) ) {
		return '';
	}
	$value = trim( (string) $value );
	$date  = DateTime::createFromFormat( '!Y-m-d', $value, wp_timezone() );
	if ( ! $date || $date->format( 'Y-m-d' ) !== $value ) {
		return '';
	}
	// Today (in the site's timezone) is the latest acceptable day: a content update cannot be dated ahead.
	if ( $date > new DateTime( 'today', wp_timezone() ) ) {
		return '';
	}
	return $value;
}

/**
 * Sanitize a boolean-ish flag to 1 or 0.
 *
 * @param mixed $value Raw value.
 * @return int
 */
function nh_seo_sanitize_flag( $value ) {
	return ( true === $value || 1 === $value || '1' === $value || 'true' === $value || 'on' === $value ) ? 1 : 0;
}

/**
 * Sanitize a comma separated keyword string used by the internal search index.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function nh_seo_sanitize_keywords( $value ) {
	if ( is_array( $value ) ) {
		$value = implode( ', ', array_map( 'strval', array_filter( $value, 'is_scalar' ) ) );
	}
	if ( ! is_scalar( $value ) ) {
		return '';
	}
	$parts = array_filter( array_map( 'trim', explode( ',', sanitize_text_field( (string) $value ) ) ) );
	return nh_seo_truncate( implode( ', ', array_unique( $parts ) ), NH_SEO_Keys::LIMIT_KEYWORDS );
}

/**
 * Sanitize a Pillar post ID: must be a published, public post or page. 0 when invalid.
 *
 * @param mixed $value Raw value.
 * @return int
 */
function nh_seo_sanitize_pillar_id( $value ) {
	// Only a positive integer or a string of digits is an ID: absint() would turn "12abc" into 12, "1e3" into 1000 and -5 into 5.
	if ( is_string( $value ) ) {
		$id = ctype_digit( trim( $value ) ) ? (int) trim( $value ) : 0;
	} else {
		$id = is_int( $value ) ? max( 0, $value ) : 0;
	}
	if ( ! $id ) {
		return 0;
	}
	$post = get_post( $id );
	if ( ! $post || ! nh_seo_is_public_post( $post ) ) {
		return 0;
	}
	return $id;
}

/**
 * Plain-text title of a post without "Protected:" / "Private:" prefixes or markup, for breadcrumbs and schema.
 *
 * @param WP_Post|int $post Post or ID.
 * @return string
 */
function nh_seo_plain_title( $post ) {
	$post = get_post( $post );
	if ( ! $post instanceof WP_Post ) {
		return '';
	}
	$title = trim( wp_specialchars_decode( wp_strip_all_tags( get_post_field( 'post_title', $post, 'raw' ) ), ENT_QUOTES ) );
	return '' !== $title ? $title : __( '(no title)', 'nguyen-ha-seo-companion' );
}

/**
 * Whether a post is something we may link to, index or describe publicly.
 *
 * Published, not password protected, viewable post type. Never drafts, private posts or trash.
 *
 * @param WP_Post|int|null $post Post or ID.
 * @return bool
 */
function nh_seo_is_public_post( $post ) {
	$post = get_post( $post );
	if ( ! $post instanceof WP_Post ) {
		return false;
	}
	return 'publish' === $post->post_status
		&& '' === (string) $post->post_password
		&& is_post_type_viewable( $post->post_type );
}

/**
 * Schema.org types the business profile may use.
 *
 * @return string[]
 */
function nh_seo_business_types() {
	$types = array(
		'LocalBusiness',
		'AutoRental',
		'AutomotiveBusiness',
		'MotorcycleDealer',
		'MotorcycleRepair',
		'TravelAgency',
		'TouristInformationCenter',
		'Store',
		'ProfessionalService',
		'LodgingBusiness',
		'FoodEstablishment',
	);

	/**
	 * Filters the schema.org types selectable for the business profile.
	 *
	 * @param string[] $types Type names.
	 */
	return apply_filters( 'nh_seo_business_types', $types );
}

/**
 * Default (empty) business profile. Nothing is pre-filled on purpose: business data must come from the owner.
 *
 * @return array<string, mixed>
 */
function nh_seo_business_defaults() {
	return array(
		'name'        => '',
		'legal_name'  => '',
		'type'        => 'LocalBusiness',
		'url'         => '',
		'phone'       => '',
		'email'       => '',
		'street'      => '',
		'locality'    => '',
		'region'      => '',
		'postal'      => '',
		'country'     => '',
		'lat'         => '',
		'lng'         => '',
		'hours'       => array(),
		'price_range' => '',
		'has_map'     => '',
		'social'      => array(),
		'logo_id'     => 0,
	);
}

/**
 * Sanitize a list of opening-hours lines in schema.org text form, e.g. "Mo-Su 09:00-21:00".
 *
 * @param mixed $value Newline separated string or array.
 * @return string[]
 */
function nh_seo_sanitize_hours( $value ) {
	$day     = '(?:Mo|Tu|We|Th|Fr|Sa|Su)';
	$days    = $day . '(?:-' . $day . ')?(?:,' . $day . '(?:-' . $day . ')?)*';
	$pattern = '/^' . $days . ' (?:[01]\d|2[0-3]):[0-5]\d-(?:[01]\d|2[0-3]):[0-5]\d$/';

	$clean = array();
	foreach ( nh_seo_to_list( $value ) as $line ) {
		if ( ! is_scalar( $line ) ) {
			continue;
		}
		$line = trim( sanitize_text_field( (string) $line ) );
		if ( '' !== $line && preg_match( $pattern, $line ) ) {
			$clean[] = $line;
		}
	}
	return array_values( array_unique( $clean ) );
}

/**
 * Sanitize a list of http(s) profile URLs.
 *
 * @param mixed $value Newline separated string or array.
 * @return string[]
 */
function nh_seo_sanitize_url_list( $value ) {
	$clean = array();
	foreach ( nh_seo_to_list( $value ) as $line ) {
		if ( ! is_scalar( $line ) ) {
			continue;
		}
		$url = esc_url_raw( trim( (string) $line ), nh_seo_allowed_protocols() );
		if ( '' !== $url ) {
			$clean[] = $url;
		}
	}
	return array_slice( array_values( array_unique( $clean ) ), 0, 12 );
}

/**
 * Sanitize a latitude or longitude. '' when empty or out of range.
 *
 * @param mixed $value Raw value.
 * @param float $limit Absolute limit (90 or 180).
 * @return string
 */
function nh_seo_sanitize_coordinate( $value, $limit ) {
	if ( ! is_scalar( $value ) || '' === trim( (string) $value ) || ! is_numeric( $value ) ) {
		return '';
	}
	$number = (float) $value;
	if ( abs( $number ) > $limit ) {
		return '';
	}
	return rtrim( rtrim( number_format( $number, 6, '.', '' ), '0' ), '.' );
}

/**
 * Sanitize the whole business profile.
 *
 * @param mixed $input Raw profile array (unslashed).
 * @return array<string, mixed>
 */
function nh_seo_sanitize_business( $input ) {
	$defaults = nh_seo_business_defaults();
	$input    = is_array( $input ) ? $input : array();
	$input    = array_merge( $defaults, $input );

	$type = is_scalar( $input['type'] ) ? (string) $input['type'] : '';
	if ( ! in_array( $type, nh_seo_business_types(), true ) ) {
		$type = $defaults['type'];
	}

	$phone = is_scalar( $input['phone'] ) ? preg_replace( '/[^0-9+()\-.\s]/', '', (string) $input['phone'] ) : '';
	$phone = nh_seo_truncate( trim( preg_replace( '/\s+/', ' ', $phone ) ), 30 );

	$country = is_scalar( $input['country'] ) ? strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $input['country'] ) ) : '';
	if ( 2 !== strlen( $country ) ) {
		$country = '';
	}

	return array(
		'name'        => nh_seo_sanitize_line( $input['name'], 120 ),
		'legal_name'  => nh_seo_sanitize_line( $input['legal_name'], 160 ),
		'type'        => $type,
		'url'         => is_scalar( $input['url'] ) ? esc_url_raw( (string) $input['url'], nh_seo_allowed_protocols() ) : '',
		'phone'       => $phone,
		'email'       => is_scalar( $input['email'] ) ? sanitize_email( (string) $input['email'] ) : '',
		'street'      => nh_seo_sanitize_line( $input['street'], 200 ),
		'locality'    => nh_seo_sanitize_line( $input['locality'], 120 ),
		'region'      => nh_seo_sanitize_line( $input['region'], 120 ),
		'postal'      => nh_seo_sanitize_line( $input['postal'], 20 ),
		'country'     => $country,
		'lat'         => nh_seo_sanitize_coordinate( $input['lat'], 90 ),
		'lng'         => nh_seo_sanitize_coordinate( $input['lng'], 180 ),
		'hours'       => nh_seo_sanitize_hours( $input['hours'] ),
		'price_range' => nh_seo_sanitize_line( $input['price_range'], 40 ),
		'has_map'     => is_scalar( $input['has_map'] ) ? esc_url_raw( (string) $input['has_map'], nh_seo_allowed_protocols() ) : '',
		'social'      => nh_seo_sanitize_url_list( $input['social'] ),
		'logo_id'     => absint( $input['logo_id'] ),
	);
}

/**
 * The verified business profile as configured by the owner. Empty fields stay empty.
 *
 * @return array<string, mixed>
 */
function nh_seo_get_business_profile() {
	$stored  = get_option( NH_SEO_Keys::OPTION_BUSINESS, array() );
	$profile = nh_seo_sanitize_business( is_array( $stored ) ? $stored : array() );

	/**
	 * Filters the business profile.
	 *
	 * @param array $profile Sanitized profile.
	 */
	return apply_filters( 'nh_seo_business_profile', $profile );
}

/**
 * Whether the profile has the minimum data needed to describe a LocalBusiness truthfully:
 * a name, a phone number and a street plus locality.
 *
 * @param array<string, mixed>|null $profile Profile, or null for the stored one.
 * @return bool
 */
function nh_seo_business_is_complete( $profile = null ) {
	$profile = is_array( $profile ) ? $profile : nh_seo_get_business_profile();
	return '' !== $profile['name'] && '' !== $profile['phone'] && '' !== $profile['street'] && '' !== $profile['locality'];
}

/**
 * Default plugin settings.
 *
 * @return array<string, mixed>
 */
function nh_seo_settings_defaults() {
	return array(
		'schema_mode'           => 'auto',  // auto | extras | full | off.
		'search_mode'           => 'auto',  // auto | native | index.
		'auto_cluster_terms'    => 1,       // Create topic_cluster terms from the nh_topic_cluster alias.
		'fallback_render'       => 1,       // Render summary/takeaways/FAQ/sources when the theme does not.
		'purge_caches'          => 1,       // Purge page caches when content changes.
		'localbusiness_on_home' => 0,       // Emit LocalBusiness on the front page.
	);
}

/**
 * Sanitize plugin settings.
 *
 * @param mixed $input Raw settings (unslashed).
 * @return array<string, mixed>
 */
function nh_seo_sanitize_settings( $input ) {
	$defaults = nh_seo_settings_defaults();
	$input    = is_array( $input ) ? array_merge( $defaults, $input ) : $defaults;

	$schema_mode = is_scalar( $input['schema_mode'] ) ? (string) $input['schema_mode'] : '';
	$search_mode = is_scalar( $input['search_mode'] ) ? (string) $input['search_mode'] : '';

	return array(
		'schema_mode'           => in_array( $schema_mode, array( 'auto', 'extras', 'full', 'off' ), true ) ? $schema_mode : $defaults['schema_mode'],
		'search_mode'           => in_array( $search_mode, array( 'auto', 'native', 'index' ), true ) ? $search_mode : $defaults['search_mode'],
		'auto_cluster_terms'    => nh_seo_sanitize_flag( $input['auto_cluster_terms'] ),
		'fallback_render'       => nh_seo_sanitize_flag( $input['fallback_render'] ),
		'purge_caches'          => nh_seo_sanitize_flag( $input['purge_caches'] ),
		'localbusiness_on_home' => nh_seo_sanitize_flag( $input['localbusiness_on_home'] ),
	);
}

/**
 * Current plugin settings.
 *
 * @return array<string, mixed>
 */
function nh_seo_get_settings() {
	$stored = get_option( NH_SEO_Keys::OPTION_SETTINGS, array() );
	return nh_seo_sanitize_settings( is_array( $stored ) ? $stored : array() );
}

/**
 * Editorial extras of a post, normalized and sanitized on read as a second line of defence.
 *
 * @param int $post_id Post ID.
 * @return array{summary: string, takeaways: string[], faq: array, sources: array, updated: string}
 */
function nh_seo_get_post_extras( $post_id ) {
	$post_id = absint( $post_id );
	return array(
		'summary'   => nh_seo_sanitize_summary( get_post_meta( $post_id, NH_SEO_Keys::META_SUMMARY, true ) ),
		'takeaways' => nh_seo_sanitize_takeaways( get_post_meta( $post_id, NH_SEO_Keys::META_TAKEAWAYS, true ) ),
		'faq'       => nh_seo_sanitize_faq( get_post_meta( $post_id, NH_SEO_Keys::META_FAQ, true ) ),
		'sources'   => nh_seo_sanitize_sources( get_post_meta( $post_id, NH_SEO_Keys::META_SOURCES, true ) ),
		'updated'   => nh_seo_sanitize_date( get_post_meta( $post_id, NH_SEO_Keys::META_UPDATED, true ) ),
	);
}

/**
 * Whether the active theme renders a given extra itself.
 *
 * Themes declare this with add_theme_support( 'nguyen-ha-companion', array( 'faq' => true, ... ) ).
 * Supported features: summary, takeaways, faq, sources, updated.
 *
 * @param string $feature Feature key.
 * @return bool
 */
function nh_seo_theme_supports( $feature ) {
	$support = get_theme_support( 'nguyen-ha-companion' );
	if ( empty( $support ) ) {
		return false;
	}
	if ( true === $support ) {
		return true;
	}

	// A feature added with one array argument is stored wrapped in another array.
	$args = $support;
	if ( is_array( $support ) && isset( $support[0] ) && is_array( $support[0] ) ) {
		$args = $support[0];
	}
	return is_array( $args ) && ! empty( $args[ $feature ] );
}

/**
 * Slug of the active SEO plugin that owns titles, canonicals, robots and the JSON-LD graph, or ''.
 *
 * @return string yoast | rank-math | aioseo | seopress | seo-framework | smartcrawl | slim-seo | squirrly | custom slug | ''.
 */
function nh_seo_detect_seo_plugin() {
	$detected = array(
		'yoast'         => defined( 'WPSEO_VERSION' ),
		'rank-math'     => defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath', false ),
		'aioseo'        => defined( 'AIOSEO_VERSION' ),
		'seopress'      => defined( 'SEOPRESS_VERSION' ),
		'seo-framework' => defined( 'THE_SEO_FRAMEWORK_VERSION' ),
		'smartcrawl'    => defined( 'SMARTCRAWL_VERSION' ),
		'slim-seo'      => defined( 'SLIM_SEO_VER' ),
		'squirrly'      => defined( 'SQ_VERSION' ),
	);

	$slug = '';
	foreach ( $detected as $name => $active ) {
		if ( $active ) {
			$slug = $name;
			break;
		}
	}

	/**
	 * Filters the detected SEO plugin slug. Return a non-empty slug for plugins this code does not know.
	 *
	 * @param string $slug Detected slug or ''.
	 */
	return (string) apply_filters( 'nh_seo_detected_seo_plugin', $slug );
}

/**
 * Effective schema mode.
 *
 * Modes: "full" outputs the whole minimal graph (no SEO plugin owns it); "extras" outputs only FAQPage
 * and LocalBusiness as separate JSON-LD blocks (an SEO plugin owns the graph); "off" outputs nothing.
 *
 * @return string full | extras | off
 */
function nh_seo_schema_mode() {
	$settings = nh_seo_get_settings();
	$mode     = $settings['schema_mode'];
	if ( 'auto' === $mode ) {
		$mode = '' !== nh_seo_detect_seo_plugin() ? 'extras' : 'full';
	}

	/**
	 * Filters the effective schema mode. Return "off" to print no structured data at all (the theme's tests do
	 * that to look at the theme's own output). Anything but full, extras or off is ignored.
	 *
	 * @param string $mode full | extras | off, from the setting and the detected SEO plugin.
	 */
	$filtered = apply_filters( 'nh_seo_schema_mode', $mode );
	return ( is_string( $filtered ) && in_array( $filtered, array( 'full', 'extras', 'off' ), true ) ) ? $filtered : $mode;
}

/**
 * Whether a post's FAQ is (or will be) visible on the page. Structured data must never describe invisible content.
 *
 * True when the post has FAQ items AND either the theme renders them or the fallback renderer is enabled.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function nh_seo_faq_is_visible( $post_id ) {
	$post_id = absint( $post_id );
	$faq     = nh_seo_sanitize_faq( get_post_meta( $post_id, NH_SEO_Keys::META_FAQ, true ) );
	if ( empty( $faq ) ) {
		return false;
	}
	$settings = nh_seo_get_settings();
	$visible  = nh_seo_theme_supports( 'faq' ) || ! empty( $settings['fallback_render'] );

	/**
	 * Filters whether the FAQ of a post is visible on its page.
	 *
	 * The theme declares support for the FAQ block, and the plugin takes that as a promise that every template shows
	 * it. A child theme or a page builder that renders a template without the FAQ returns false for the posts it
	 * serves, so that no FAQPage structured data describes questions a reader cannot see. A post without FAQ items
	 * never reaches this filter.
	 *
	 * @param bool $visible Whether the FAQ is shown.
	 * @param int  $post_id Post ID.
	 */
	return (bool) apply_filters( 'nh_seo_faq_is_visible', $visible, $post_id );
}

/**
 * Primary category of a post. Canonical algorithm shared with the theme (see docs/DATA-CONTRACT.md):
 *
 *  1. Yoast / Rank Math primary category, when it is assigned to the post;
 *  2. otherwise the deepest assigned category (ties: lowest term ID), ignoring the default
 *     "Uncategorized" category unless it is the only one.
 *
 * @param WP_Post|int|null $post Post or ID. Defaults to the current post.
 * @return WP_Term|null
 */
function nh_seo_get_primary_category( $post = null ) {
	$post = get_post( $post );
	if ( ! $post instanceof WP_Post ) {
		return null;
	}

	$terms = get_the_terms( $post, 'category' );
	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return null;
	}

	$by_id = array();
	foreach ( $terms as $term ) {
		$by_id[ (int) $term->term_id ] = $term;
	}

	$candidates = array(
		(int) get_post_meta( $post->ID, '_yoast_wpseo_primary_category', true ),
		(int) get_post_meta( $post->ID, 'rank_math_primary_category', true ),
	);
	if ( function_exists( 'yoast_get_primary_term_id' ) ) {
		array_unshift( $candidates, (int) yoast_get_primary_term_id( 'category', $post ) );
	}
	foreach ( $candidates as $candidate ) {
		if ( $candidate && isset( $by_id[ $candidate ] ) ) {
			return $by_id[ $candidate ];
		}
	}

	$pool    = $by_id;
	$default = (int) get_option( 'default_category' );
	if ( count( $pool ) > 1 ) {
		unset( $pool[ $default ] );
	}

	$best       = null;
	$best_depth = -1;
	foreach ( $pool as $term ) {
		$depth = count( get_ancestors( $term->term_id, 'category', 'taxonomy' ) );
		if ( $depth > $best_depth || ( $depth === $best_depth && null !== $best && $term->term_id < $best->term_id ) ) {
			$best       = $term;
			$best_depth = $depth;
		}
	}
	return $best;
}

/**
 * Canonical URL of the current archive (first page, no tracking or other foreign query arguments).
 *
 * The core function get_pagenum_link() keeps whatever query string the visitor sent (utm_source, ref...) and HTML-escapes it, so every
 * campaign link would become its own entity in structured data. Date and post type archives are built from their
 * query instead; anything else falls back to the paged link without arguments that are not WordPress query variables.
 * Same algorithm as nh_editorial_archive_url() in the theme (docs/DATA-CONTRACT.md section 4).
 *
 * @return string
 */
function nh_seo_archive_url() {
	global $wp;

	if ( is_day() ) {
		return (string) get_day_link( (int) get_query_var( 'year' ), (int) get_query_var( 'monthnum' ), (int) get_query_var( 'day' ) );
	}
	if ( is_month() ) {
		return (string) get_month_link( (int) get_query_var( 'year' ), (int) get_query_var( 'monthnum' ) );
	}
	if ( is_year() ) {
		return (string) get_year_link( (int) get_query_var( 'year' ) );
	}
	if ( is_post_type_archive() ) {
		$object = get_queried_object();
		$link   = $object instanceof WP_Post_Type ? get_post_type_archive_link( $object->name ) : false;
		if ( $link ) {
			return (string) $link;
		}
	}

	$url     = (string) get_pagenum_link( 1, false );
	$foreign = array();
	foreach ( array_keys( wp_parse_args( (string) wp_parse_url( $url, PHP_URL_QUERY ) ) ) as $name ) {
		if ( ! in_array( $name, (array) $wp->public_query_vars, true ) ) {
			$foreign[] = $name;
		}
	}
	return $foreign ? remove_query_arg( $foreign, $url ) : $url;
}

/**
 * Canonical breadcrumb trail for the current main query. The theme renders the visible breadcrumb
 * from the same rules; the schema module serialises this one when it owns BreadcrumbList.
 *
 * Each item is array( 'name' => string (raw, unescaped), 'url' => string ). The last item is the
 * current page. Empty array on the front page and on 404.
 *
 * @return array<int, array{name: string, url: string}>
 */
function nh_seo_breadcrumb_trail() {
	if ( is_front_page() || is_404() ) {
		return array();
	}

	$trail = array(
		array(
			'name' => __( 'Home', 'nguyen-ha-seo-companion' ),
			'url'  => home_url( '/' ),
		),
	);

	if ( is_singular() ) {
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return $trail;
		}

		if ( 'post' === $post->post_type ) {
			$primary = nh_seo_get_primary_category( $post );
			if ( $primary ) {
				$chain   = array_reverse( get_ancestors( $primary->term_id, 'category', 'taxonomy' ) );
				$chain[] = $primary->term_id;
				foreach ( $chain as $term_id ) {
					$term = get_term( $term_id, 'category' );
					$link = $term instanceof WP_Term ? get_term_link( $term ) : '';
					if ( $term instanceof WP_Term && ! is_wp_error( $link ) ) {
						$trail[] = array(
							'name' => $term->name,
							'url'  => $link,
						);
					}
				}
			}
		} elseif ( is_post_type_hierarchical( $post->post_type ) ) {
			foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor_id ) {
				if ( 'publish' === get_post_status( $ancestor_id ) ) {
					$trail[] = array(
						'name' => nh_seo_plain_title( $ancestor_id ),
						'url'  => get_permalink( $ancestor_id ),
					);
				}
			}
		} else {
			$archive = get_post_type_archive_link( $post->post_type );
			$object  = get_post_type_object( $post->post_type );
			if ( $archive && $object ) {
				$trail[] = array(
					'name' => $object->labels->name,
					'url'  => $archive,
				);
			}
		}

		$trail[] = array(
			'name' => nh_seo_plain_title( $post ),
			'url'  => get_permalink( $post ),
		);
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term ) {
			if ( is_taxonomy_hierarchical( $term->taxonomy ) ) {
				foreach ( array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) ) as $ancestor_id ) {
					$ancestor = get_term( $ancestor_id, $term->taxonomy );
					$link     = $ancestor instanceof WP_Term ? get_term_link( $ancestor ) : '';
					if ( $ancestor instanceof WP_Term && ! is_wp_error( $link ) ) {
						$trail[] = array(
							'name' => $ancestor->name,
							'url'  => $link,
						);
					}
				}
			}
			$link = get_term_link( $term );
			if ( ! is_wp_error( $link ) ) {
				$trail[] = array(
					'name' => $term->name,
					'url'  => $link,
				);
			}
		}
	} elseif ( is_author() ) {
		$author = get_queried_object();
		if ( $author instanceof WP_User ) {
			$trail[] = array(
				'name' => $author->display_name,
				'url'  => get_author_posts_url( $author->ID ),
			);
		}
	} elseif ( is_search() ) {
		$trail[] = array(
			/* translators: %s: search query. */
			'name' => sprintf( __( 'Search results for: %s', 'nguyen-ha-seo-companion' ), get_search_query( false ) ),
			'url'  => get_search_link( get_search_query( false ) ),
		);
	} elseif ( is_archive() ) {
		$trail[] = array(
			'name' => wp_strip_all_tags( get_the_archive_title() ),
			'url'  => nh_seo_archive_url(),
		);
	}

	/**
	 * Filters the canonical breadcrumb trail.
	 *
	 * @param array $trail Items of name and url.
	 */
	return apply_filters( 'nh_seo_breadcrumb_trail', $trail );
}
