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
 * A stored value as a string. Scalars are cast; arrays and objects (a plugin, an import or a script can store
 * them in any meta key) become '' instead of the text "Array" and a PHP warning.
 *
 * @param mixed $value Raw meta value.
 * @return string
 */
function nh_editorial_meta_string( $value ) {
	return is_scalar( $value ) ? (string) $value : '';
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
 * Sanitize one FAQ answer: allowed tags only, http(s) links only, no dangling href.
 *
 * The kses filter removes a disallowed protocol but keeps the rest of the URL ("javascript:alert(1)"
 * becomes the relative link "alert(1)"), so a link is kept only when it is absolute http(s),
 * root-relative or an in-page anchor; any other href is dropped and the link text stays.
 *
 * @param mixed $answer Raw answer.
 * @return string Safe HTML ('' when nothing readable is left).
 */
function nh_editorial_clean_faq_answer( $answer ) {
	if ( ! is_scalar( $answer ) ) {
		return '';
	}

	$answer = wp_kses( mb_substr( trim( (string) $answer ), 0, 3000 ), nh_editorial_faq_allowed_html(), array( 'http', 'https' ) );
	$answer = trim( force_balance_tags( $answer ) );

	if ( false !== stripos( $answer, '<a' ) ) {
		$tags = new WP_HTML_Tag_Processor( $answer );
		while ( $tags->next_tag( 'a' ) ) {
			$href = $tags->get_attribute( 'href' );
			if ( is_string( $href ) && ! preg_match( '#^(https?://|/|\#)#i', $href ) ) {
				$tags->remove_attribute( 'href' );
			}
		}
		$answer = $tags->get_updated_html();
	}

	return $answer;
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
		$answer   = nh_editorial_clean_faq_answer( isset( $item['answer'] ) ? $item['answer'] : ( isset( $item['a'] ) ? $item['a'] : '' ) );
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
	// Today (in the site's timezone) is the latest acceptable day: a content update cannot be dated ahead.
	return $date > new DateTime( 'today', wp_timezone() ) ? '' : $value;
}

wp_cache_add_non_persistent_groups( array( 'nh_editorial_extras' ) );

/**
 * Editorial extras of a post, validated. Cached for the request and dropped whenever its meta changes.
 *
 * @param int $post_id Post ID (defaults to the current post).
 * @return array{summary: string, takeaways: string[], faq: array, sources: array, updated: string}
 */
function nh_editorial_get_extras( $post_id = 0 ) {
	$post_id = $post_id ? absint( $post_id ) : (int) get_the_ID();

	// The title of a protected post is public, nothing else about it is: no summary, takeaways, FAQ, sources or
	// update date next to the password form. post_password_required() knows whether this visitor gave the password.
	if ( $post_id && post_password_required( $post_id ) ) {
		return array(
			'summary'   => '',
			'takeaways' => array(),
			'faq'       => array(),
			'sources'   => array(),
			'updated'   => '',
		);
	}

	$cached = wp_cache_get( $post_id, 'nh_editorial_extras' );
	if ( is_array( $cached ) ) {
		return $cached;
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

	$extras = array(
		'summary'   => nh_editorial_clean_text( get_post_meta( $post_id, 'nh_summary', true ), 800 ),
		'takeaways' => $clean_takeaways,
		'faq'       => nh_editorial_clean_faq( get_post_meta( $post_id, 'nh_faq', true ) ),
		'sources'   => nh_editorial_clean_sources( get_post_meta( $post_id, 'nh_sources', true ) ),
		'updated'   => nh_editorial_clean_date( get_post_meta( $post_id, 'nh_content_updated', true ) ),
	);

	wp_cache_set( $post_id, $extras, 'nh_editorial_extras' );
	return $extras;
}

/**
 * Drop the cached extras of a post when one of the nh_* meta keys changes.
 *
 * @param int|int[] $meta_id   Meta ID(s).
 * @param int       $object_id Post ID.
 * @param string    $meta_key  Meta key.
 * @return void
 */
function nh_editorial_flush_extras_on_meta( $meta_id, $object_id, $meta_key ) {
	if ( 0 === strpos( (string) $meta_key, 'nh_' ) ) {
		wp_cache_delete( absint( $object_id ), 'nh_editorial_extras' );
	}
}
add_action( 'added_post_meta', 'nh_editorial_flush_extras_on_meta', 10, 3 );
add_action( 'updated_post_meta', 'nh_editorial_flush_extras_on_meta', 10, 3 );
add_action( 'deleted_post_meta', 'nh_editorial_flush_extras_on_meta', 10, 3 );

/**
 * Drop the cached extras when the post cache is cleared.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function nh_editorial_flush_extras_on_clean( $post_id ) {
	wp_cache_delete( absint( $post_id ), 'nh_editorial_extras' );
}
add_action( 'clean_post_cache', 'nh_editorial_flush_extras_on_clean' );

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
