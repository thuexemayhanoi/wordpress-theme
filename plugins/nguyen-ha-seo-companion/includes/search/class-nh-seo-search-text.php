<?php
/**
 * Text folding and tokenization for the internal search index.
 *
 * Everything that reaches the database (indexed text and user queries alike) is first folded to plain ASCII
 * tokens made of [a-z0-9]: markup and shortcodes removed, entities decoded, diacritics removed (Vietnamese
 * letters and "đ" included), lower case. A folded string therefore can never carry SQL or full-text operators.
 *
 * InnoDB full-text constraints that cannot be changed on shared hosting are handled here, in code:
 *  - tokens shorter than 3 characters are never indexed or searched (innodb_ft_min_token_size defaults to 3);
 *  - tokens longer than 80 characters are dropped (innodb_ft_max_token_size is 84);
 *  - InnoDB's built-in stop-words are never put into a query (a required stop-word matches nothing).
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * Folding helpers. Stateless.
 */
final class NH_SEO_Search_Text {

	/**
	 * Shortest token InnoDB indexes by default.
	 */
	const MIN_TOKEN = 3;

	/**
	 * Longest token kept (InnoDB ignores tokens above 84 characters).
	 */
	const MAX_TOKEN = 80;

	/**
	 * Default cap of unique body tokens per post.
	 */
	const DEFAULT_BODY_CAP = 4000;

	/**
	 * Characters of a search string that are considered.
	 */
	const MAX_QUERY_CHARS = 200;

	/**
	 * Tokens of a search string that are used.
	 */
	const MAX_QUERY_TOKENS = 10;

	/**
	 * Raw markup larger than this is cut before it is folded (one megabyte).
	 */
	const MAX_RAW_BYTES = 1048576;

	/**
	 * InnoDB default stop-word list (INFORMATION_SCHEMA.INNODB_FT_DEFAULT_STOPWORD), identical on MySQL and MariaDB.
	 *
	 * @return string[]
	 */
	public static function default_stopwords() {
		return array(
			'a',
			'about',
			'an',
			'are',
			'as',
			'at',
			'be',
			'by',
			'com',
			'de',
			'en',
			'for',
			'from',
			'how',
			'i',
			'in',
			'is',
			'it',
			'la',
			'of',
			'on',
			'or',
			'that',
			'the',
			'this',
			'to',
			'und',
			'was',
			'what',
			'when',
			'where',
			'who',
			'will',
			'with',
			'www',
		);
	}

	/**
	 * Stop-words that must never be sent to a boolean query.
	 *
	 * @return array<string, true> Set keyed by word.
	 */
	public static function stopwords() {
		$words = self::default_stopwords();

		/**
		 * Filters the stop-words dropped from search queries. Add words when the database uses a custom
		 * InnoDB stop-word table.
		 *
		 * @param string[] $words Lower case ASCII words.
		 */
		$words = (array) apply_filters( 'nh_seo_search_stopwords', $words );

		$set = array();
		foreach ( $words as $word ) {
			if ( is_string( $word ) && '' !== $word ) {
				$set[ strtolower( $word ) ] = true;
			}
		}
		return $set;
	}

	/**
	 * Fold plain text: accents removed, lower case, anything but [a-z0-9] becomes one space.
	 *
	 * No markup handling happens here; use fold_html() for content.
	 *
	 * @param mixed $text Text.
	 * @return string Single-space separated tokens.
	 */
	public static function fold_text( $text ) {
		if ( ! is_scalar( $text ) ) {
			return '';
		}
		$text = (string) $text;
		if ( '' === $text ) {
			return '';
		}

		$text = wp_check_invalid_utf8( $text, true );
		$text = remove_accents( $text );

		// Combining marks that survive (decomposed input) must not split a word.
		$stripped = preg_replace( '/[\x{0300}-\x{036F}]+/u', '', $text );
		if ( is_string( $stripped ) ) {
			$text = $stripped;
		}

		// Locale independent lower casing of ASCII letters.
		$text = strtr( $text, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz' );
		$text = preg_replace( '/[^a-z0-9]+/', ' ', $text );
		return trim( (string) $text );
	}

	/**
	 * Fold content that may hold markup, comments, scripts, shortcodes and entities.
	 *
	 * @param mixed $html HTML or text.
	 * @return string Single-space separated tokens.
	 */
	public static function fold_html( $html ) {
		if ( ! is_scalar( $html ) ) {
			return '';
		}
		$html = (string) $html;
		if ( '' === $html ) {
			return '';
		}
		if ( strlen( $html ) > self::MAX_RAW_BYTES ) {
			$html = substr( $html, 0, self::MAX_RAW_BYTES );
		}

		if ( false !== strpos( $html, '[' ) ) {
			$html = strip_shortcodes( $html );
		}
		// Removes tags, comments (block delimiters) and the content of script and style elements.
		$html = wp_strip_all_tags( $html );
		$html = html_entity_decode( $html, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return self::fold_text( $html );
	}

	/**
	 * Split folded text into tokens.
	 *
	 * @param string $folded Output of fold_text() or fold_html().
	 * @return string[]
	 */
	public static function tokens( $folded ) {
		$folded = (string) $folded;
		return '' === $folded ? array() : explode( ' ', $folded );
	}

	/**
	 * Keep tokens whose length is inside the indexable range.
	 *
	 * @param string[] $tokens Tokens.
	 * @param int      $min    Minimum length.
	 * @return string[]
	 */
	public static function usable_tokens( array $tokens, $min = self::MIN_TOKEN ) {
		$out = array();
		foreach ( $tokens as $token ) {
			$length = strlen( $token );
			if ( $length >= $min && $length <= self::MAX_TOKEN ) {
				$out[] = $token;
			}
		}
		return $out;
	}

	/**
	 * Folded title: every token, because a title is short and InnoDB ignores what it does not index.
	 *
	 * @param mixed $title Post title.
	 * @return string
	 */
	public static function fold_title( $title ) {
		$tokens = array_slice( self::usable_tokens( self::tokens( self::fold_html( $title ) ), 1 ), 0, 80 );
		return implode( ' ', $tokens );
	}

	/**
	 * Folded taxonomy words: names, keywords and focus keywords (all tokens, capped).
	 *
	 * @param array<int, mixed> $parts Strings.
	 * @return string
	 */
	public static function fold_taxonomy( array $parts ) {
		$strings = array();
		foreach ( $parts as $part ) {
			if ( is_scalar( $part ) && '' !== trim( (string) $part ) ) {
				$strings[] = (string) $part;
			}
		}
		$tokens = array_slice( self::usable_tokens( self::tokens( self::fold_html( implode( ' ', $strings ) ) ), 1 ), 0, 300 );
		return implode( ' ', $tokens );
	}

	/**
	 * Unique body tokens of at least three characters from already folded parts, in priority order, capped.
	 *
	 * Earlier parts win when the cap is reached, so put the most important text first.
	 *
	 * @param string[] $folded_parts Folded strings, most important first.
	 * @param int      $cap          Maximum number of tokens.
	 * @return string
	 */
	public static function body_tokens( array $folded_parts, $cap = self::DEFAULT_BODY_CAP ) {
		$cap  = max( 1, (int) $cap );
		$seen = array();
		$out  = array();

		foreach ( $folded_parts as $part ) {
			foreach ( self::tokens( $part ) as $token ) {
				$length = strlen( $token );
				if ( $length < self::MIN_TOKEN || $length > self::MAX_TOKEN || isset( $seen[ $token ] ) ) {
					continue;
				}
				$seen[ $token ] = true;
				$out[]          = $token;
				if ( count( $out ) >= $cap ) {
					break 2;
				}
			}
		}
		return implode( ' ', $out );
	}

	/**
	 * Tokens of a user search string that may be sent to a boolean full-text query.
	 *
	 * Operators, quotes and punctuation disappear while folding, so the result contains [a-z0-9] only. Short
	 * tokens and stop-words are dropped. An empty result means "do not use the index".
	 *
	 * @param mixed $search Raw search string.
	 * @return string[] Unique tokens in order of appearance.
	 */
	public static function query_tokens( $search ) {
		if ( ! is_scalar( $search ) ) {
			return array();
		}
		$search = mb_substr( (string) $search, 0, self::MAX_QUERY_CHARS );

		/**
		 * Filters how many distinct tokens of a search string are used.
		 *
		 * @param int $max Maximum number of tokens. Default 10.
		 */
		$max       = max( 1, (int) apply_filters( 'nh_seo_search_max_tokens', self::MAX_QUERY_TOKENS ) );
		$stopwords = self::stopwords();
		$out       = array();

		foreach ( self::usable_tokens( self::tokens( self::fold_text( $search ) ) ) as $token ) {
			if ( isset( $stopwords[ $token ] ) || in_array( $token, $out, true ) ) {
				continue;
			}
			$out[] = $token;
			if ( count( $out ) >= $max ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Boolean-mode expression for tokens: prefix matching, required (AND) or optional (OR).
	 *
	 * @param string[] $tokens   Tokens from query_tokens().
	 * @param bool     $required True for "+token*" (all must match), false for "token*" (any may match).
	 * @return string
	 */
	public static function boolean_expression( array $tokens, $required ) {
		$parts = array();
		foreach ( $tokens as $token ) {
			// Defence in depth: only folded tokens may ever enter an expression.
			if ( 1 === preg_match( '/^[a-z0-9]{1,80}$/', $token ) ) {
				$parts[] = ( $required ? '+' : '' ) . $token . '*';
			}
		}
		return implode( ' ', $parts );
	}
}
