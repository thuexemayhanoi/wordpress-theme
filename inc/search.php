<?php
/**
 * Search: query tuning, result helpers and the suggestions endpoint wiring.
 *
 * Searching uses WordPress' own search (title, excerpt, content, plus taxonomy filtering through the
 * standard "cat" parameter). When the companion plugin's internal index is ready it takes over the SQL
 * transparently through core query filters, so nothing here changes. Suggestions use the core REST
 * route /wp/v2/search: no external service, nothing loaded into the browser except five results.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

/**
 * Restrict the main search query to articles and pages, in stable order.
 *
 * @param WP_Query $query Query.
 * @return void
 */
function nh_editorial_search_query( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
		return;
	}
	if ( '' === $query->get( 'post_type' ) ) {
		$query->set( 'post_type', array( 'post', 'page' ) );
	}
	$query->set( 'ignore_sticky_posts', true );
}
add_action( 'pre_get_posts', 'nh_editorial_search_query' );

/**
 * The visitor's search phrase, cleaned for display.
 *
 * @return string
 */
function nh_editorial_search_phrase() {
	return trim( get_search_query( false ) );
}

/**
 * Words of the search phrase worth highlighting (at least two characters, at most eight).
 *
 * @param string $phrase Search phrase.
 * @return string[]
 */
function nh_editorial_search_words( $phrase ) {
	$words = preg_split( '/[\s,.;:!?"\'()\[\]{}]+/u', trim( (string) $phrase ), -1, PREG_SPLIT_NO_EMPTY );
	$words = array_filter(
		(array) $words,
		static function ( $word ) {
			return mb_strlen( $word ) >= 2;
		}
	);
	return array_slice( array_values( array_unique( $words ) ), 0, 8 );
}

/**
 * Escape text and wrap the words of the search phrase in <mark>.
 *
 * The text is escaped first and entities are never split, so the result is safe to print.
 *
 * @param string $text   Plain text.
 * @param string $phrase Search phrase.
 * @return string Safe HTML.
 */
function nh_editorial_highlight( $text, $phrase ) {
	$escaped = esc_html( (string) $text );
	$words   = nh_editorial_search_words( $phrase );
	if ( empty( $words ) || '' === $escaped ) {
		return $escaped;
	}

	$patterns = array();
	foreach ( $words as $word ) {
		$patterns[] = preg_quote( esc_html( $word ), '/' );
	}
	$regex = '/(' . implode( '|', $patterns ) . ')/iu';

	// Split on character references so a match can never land inside "&amp;" and break it.
	$parts = preg_split( '/(&#?[a-z0-9]+;)/i', $escaped, -1, PREG_SPLIT_DELIM_CAPTURE );
	foreach ( $parts as $index => $part ) {
		if ( 0 === $index % 2 ) {
			$parts[ $index ] = preg_replace( $regex, '<mark>$1</mark>', $part );
		}
	}
	return implode( '', $parts );
}

/**
 * Data attributes for the header search form: the REST route and the strings used by the script.
 *
 * Empty when suggestions are disabled or the REST API is unavailable.
 *
 * @return string Safe attribute string (leading space) or ''.
 */
function nh_editorial_search_suggest_attributes() {
	if ( ! nh_editorial_option( 'nh_search_suggest' ) ) {
		return '';
	}

	$attributes = array(
		'data-suggest-url'   => rest_url( 'wp/v2/search' ),
		'data-label-none'    => __( 'No suggestions', 'nguyen-ha-editorial' ),
		/* translators: %d: number of suggestions. */
		'data-label-count'   => __( '%d suggestions available', 'nguyen-ha-editorial' ),
		'data-label-all'     => __( 'See all results', 'nguyen-ha-editorial' ),
		'data-label-heading' => __( 'Suggested articles', 'nguyen-ha-editorial' ),
	);

	$out = '';
	foreach ( $attributes as $name => $value ) {
		$out .= ' ' . $name . '="' . esc_attr( $value ) . '"';
	}
	return $out;
}

/**
 * Popular topics offered when a search has no result (top-level hubs).
 *
 * @return array<int, array{name: string, url: string}>
 */
function nh_editorial_search_topics() {
	$topics = array();
	foreach ( nh_editorial_get_hubs( 8 ) as $hub ) {
		$topics[] = array(
			'name' => $hub['name'],
			'url'  => $hub['url'],
		);
	}
	return $topics;
}
