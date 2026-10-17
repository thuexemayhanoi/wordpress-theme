<?php
/**
 * Editorial extras of an article: answer-first summary, key takeaways, FAQ, sources and the real
 * update date.
 *
 * The data is written by the AI Writer workflow or the companion plugin (docs/DATA-CONTRACT.md) as
 * plain post meta. The theme works without the plugin: it only reads meta, re-validates everything
 * and escapes on output. Nothing is generated or invented here.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

/**
 * HTML allowed inside FAQ answers. Mirrors the companion plugin's list.
 *
 * @return array<string, array<string, bool>>
 */
function nh_editorial_faq_allowed_html() {
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
	return apply_filters( 'nh_editorial_faq_allowed_html', $allowed );
}

/**
 * Plain, single-line text with a character limit.
 *
 * @param mixed $value Raw value.
 * @param int   $limit Maximum characters.
 * @return string
 */
function nh_editorial_clean_text( $value, $limit ) {
	if ( ! is_scalar( $value ) ) {
		return '';
	}
	$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $value ) ) );
	if ( mb_strlen( $text ) > $limit ) {
		$text = rtrim( mb_substr( $text, 0, $limit ) );
	}
	return $text;
}

/**
 * Validate FAQ items from meta (array of question/answer pairs).
 *
 * @param mixed $raw Meta value.
 * @return array<int, array{question: string, answer: string}>
 */
function nh_editorial_clean_faq( $raw ) {
	if ( is_string( $raw ) ) {
		$decoded = json_decode( $raw, true );
		$raw     = is_array( $decoded ) ? $decoded : array();
	}
	if ( ! is_array( $raw ) ) {
		return array();
	}

	$items = array();
	foreach ( $raw as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$question = nh_editorial_clean_text( isset( $item['question'] ) ? $item['question'] : ( isset( $item['q'] ) ? $item['q'] : '' ), 300 );
		$answer   = isset( $item['answer'] ) ? $item['answer'] : ( isset( $item['a'] ) ? $item['a'] : '' );
		$answer   = is_scalar( $answer ) ? wp_kses( mb_substr( trim( (string) $answer ), 0, 3000 ), nh_editorial_faq_allowed_html(), array( 'http', 'https' ) ) : '';
		$answer   = trim( force_balance_tags( $answer ) );
		if ( '' === $question || '' === trim( wp_strip_all_tags( $answer ) ) ) {
			continue;
		}
		$items[] = array(
			'question' => $question,
			'answer'   => $answer,
		);
		if ( count( $items ) >= 20 ) {
			break;
		}
	}
	return $items;
}

/**
 * Validate source references from meta.
 *
 * @param mixed $raw Meta value.
 * @return array<int, array{title: string, url: string, publisher: string}>
 */
function nh_editorial_clean_sources( $raw ) {
	if ( is_string( $raw ) ) {
		$decoded = json_decode( $raw, true );
		$raw     = is_array( $decoded ) ? $decoded : array();
	}
	if ( ! is_array( $raw ) ) {
		return array();
	}

	$items = array();
	foreach ( $raw as $item ) {
		if ( ! is_array( $item ) || empty( $item['url'] ) || ! is_scalar( $item['url'] ) ) {
			continue;
		}
		$url = esc_url_raw( trim( (string) $item['url'] ), array( 'http', 'https' ) );
		if ( '' === $url ) {
			continue;
		}
		$title = isset( $item['title'] ) ? nh_editorial_clean_text( $item['title'], 200 ) : '';
		if ( '' === $title ) {
			$host  = wp_parse_url( $url, PHP_URL_HOST );
			$title = $host ? (string) $host : $url;
		}
		$items[] = array(
			'title'     => $title,
			'url'       => $url,
			'publisher' => isset( $item['publisher'] ) ? nh_editorial_clean_text( $item['publisher'], 120 ) : '',
		);
		if ( count( $items ) >= 15 ) {
			break;
		}
	}
	return $items;
}

/**
 * Validate a Y-m-d date that is not in the future. '' when invalid.
 *
 * @param mixed $raw Meta value.
 * @return string
 */
function nh_editorial_clean_date( $raw ) {
	if ( ! is_scalar( $raw ) ) {
		return '';
	}
	$value = trim( (string) $raw );
	$date  = DateTime::createFromFormat( '!Y-m-d', $value, wp_timezone() );
	if ( ! $date || $date->format( 'Y-m-d' ) !== $value ) {
		return '';
	}
	return $date > new DateTime( 'tomorrow', wp_timezone() ) ? '' : $value;
}

/**
 * Editorial extras of a post, validated. Cached for the request.
 *
 * @param int $post_id Post ID (defaults to the current post).
 * @return array{summary: string, takeaways: string[], faq: array, sources: array, updated: string}
 */
function nh_editorial_get_extras( $post_id = 0 ) {
	static $cache = array();

	$post_id = $post_id ? absint( $post_id ) : (int) get_the_ID();
	if ( isset( $cache[ $post_id ] ) ) {
		return $cache[ $post_id ];
	}

	$takeaways = get_post_meta( $post_id, 'nh_takeaways', true );
	if ( is_string( $takeaways ) ) {
		$takeaways = preg_split( '/\R/u', $takeaways );
	}
	$clean_takeaways = array();
	foreach ( is_array( $takeaways ) ? $takeaways : array() as $line ) {
		$line = nh_editorial_clean_text( $line, 300 );
		if ( '' !== $line ) {
			$clean_takeaways[] = $line;
		}
		if ( count( $clean_takeaways ) >= 10 ) {
			break;
		}
	}

	$cache[ $post_id ] = array(
		'summary'   => nh_editorial_clean_text( get_post_meta( $post_id, 'nh_summary', true ), 800 ),
		'takeaways' => $clean_takeaways,
		'faq'       => nh_editorial_clean_faq( get_post_meta( $post_id, 'nh_faq', true ) ),
		'sources'   => nh_editorial_clean_sources( get_post_meta( $post_id, 'nh_sources', true ) ),
		'updated'   => nh_editorial_clean_date( get_post_meta( $post_id, 'nh_content_updated', true ) ),
	);

	return $cache[ $post_id ];
}

/**
 * The real update date of an article, or null when there is nothing meaningful to show.
 *
 * Priority: the content-updated date written by the workflow, then the modified date when it is at
 * least a day after publication. A modified date that equals the publish date is never shown, so the
 * theme cannot be used to fake freshness.
 *
 * @param WP_Post|int|null $post Post (defaults to the current post).
 * @return array{iso: string, label: string}|null
 */
function nh_editorial_updated_date( $post = null ) {
	$post = get_post( $post );
	if ( ! $post instanceof WP_Post ) {
		return null;
	}

	$published = (int) get_post_time( 'U', true, $post );
	$extras    = nh_editorial_get_extras( $post->ID );

	if ( '' !== $extras['updated'] ) {
		$date = DateTime::createFromFormat( '!Y-m-d', $extras['updated'], wp_timezone() );
		if ( $date && $date->getTimestamp() > $published ) {
			return array(
				'iso'   => $extras['updated'],
				'label' => wp_date( get_option( 'date_format' ), $date->getTimestamp() ),
			);
		}
	}

	$modified = (int) get_post_modified_time( 'U', true, $post );
	if ( $modified - $published >= DAY_IN_SECONDS ) {
		return array(
			'iso'   => get_post_modified_time( 'c', false, $post ),
			'label' => get_post_modified_time( get_option( 'date_format' ), false, $post, true ),
		);
	}

	return null;
}
