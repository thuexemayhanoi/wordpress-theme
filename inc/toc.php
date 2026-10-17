<?php
/**
 * Table of contents built from the H2/H3 headings of the article body.
 *
 * Headings receive stable ids (existing ids are kept) and the list is rendered on the server, so it
 * works without JavaScript and does not alter the text of the article. Heading text, order and levels
 * are untouched; only an id attribute is added where one is missing.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

/**
 * Collected headings of the article being prepared (reset for each article).
 *
 * @param array|null $items Pass an array to store, null to read.
 * @return array<int, array{id: string, text: string, level: int}>
 */
function nh_editorial_toc_store( $items = null ) {
	static $store = array();
	if ( null !== $items ) {
		$store = $items;
	}
	return $store;
}

/**
 * Ids that must never be generated because the theme or core already uses them.
 *
 * @return string[]
 */
function nh_editorial_reserved_ids() {
	return array( 'primary', 'content', 'comments', 'respond', 'main', 'site-navigation', 'primary-navigation', 'header-search', 'footer', 'toc', 'faq', 'sources' );
}

/**
 * Add ids to H2/H3 headings and remember them for the table of contents.
 *
 * Runs on the_content only while nh_editorial_prepare_article() is active.
 *
 * @param string $content Rendered content.
 * @return string
 */
function nh_editorial_toc_filter( $content ) {
	$used = array_flip( nh_editorial_reserved_ids() );
	if ( preg_match_all( '/\sid=(["\'])([^"\']+)\1/i', $content, $existing ) ) {
		foreach ( $existing[2] as $id ) {
			$used[ $id ] = true;
		}
	}

	$items   = array();
	$counter = 0;

	$content = preg_replace_callback(
		'#<h([23])((?:\s[^>]*)?)>(.*?)</h\1>#is',
		static function ( $match ) use ( &$used, &$items, &$counter ) {
			$level = (int) $match[1];
			$attrs = $match[2];
			$text  = trim( html_entity_decode( wp_strip_all_tags( $match[3] ), ENT_QUOTES, 'UTF-8' ) );
			if ( '' === $text ) {
				return $match[0];
			}
			++$counter;

			if ( preg_match( '/\sid=(["\'])([^"\']+)\1/i', $attrs, $id_match ) ) {
				$id = $id_match[2];
			} else {
				$base = sanitize_title( mb_substr( $text, 0, 60 ) );
				$base = '' !== $base ? $base : 'section-' . $counter;
				$id   = $base;
				$n    = 2;
				while ( isset( $used[ $id ] ) ) {
					$id = $base . '-' . $n;
					++$n;
				}
				$used[ $id ] = true;
				$attrs      .= ' id="' . esc_attr( $id ) . '"';
			}

			$items[] = array(
				'id'    => $id,
				'text'  => $text,
				'level' => $level,
			);

			return '<h' . $level . $attrs . '>' . $match[3] . '</h' . $level . '>';
		},
		$content
	);

	nh_editorial_toc_store( $items );
	return (string) $content;
}

/**
 * Render the post content once and collect its table of contents.
 *
 * Equivalent to the_content() but returns the HTML so the template can print the table of contents
 * before it. The standard the_content filters (blocks, shortcodes, lazy-loading, embeds) all run.
 *
 * @return array{content: string, toc: array<int, array{id: string, text: string, level: int}>}
 */
function nh_editorial_prepare_article() {
	nh_editorial_toc_store( array() );

	add_filter( 'the_content', 'nh_editorial_toc_filter', 20 );
	$content = apply_filters( 'the_content', get_the_content( null, false ) );
	remove_filter( 'the_content', 'nh_editorial_toc_filter', 20 );

	$content = str_replace( ']]>', ']]&gt;', $content );
	$toc     = nh_editorial_toc_store();

	/**
	 * Filters the minimum number of headings before a table of contents is shown.
	 *
	 * @param int $minimum Minimum heading count.
	 */
	$minimum = (int) apply_filters( 'nh_editorial_toc_min', (int) nh_editorial_option( 'nh_toc_min' ) );

	if ( ! nh_editorial_option( 'nh_toc' ) || count( $toc ) < $minimum ) {
		$toc = array();
	}

	return array(
		'content' => $content,
		'toc'     => $toc,
	);
}
