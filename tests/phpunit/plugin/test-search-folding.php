<?php
/**
 * Search index folding and tokenization: Vietnamese text, markup, limits and hostile input.
 *
 * These tests do not touch the database.
 *
 * @package nguyen-ha-seo-companion
 */

/**
 * Folding tests.
 */
class NH_Test_Search_Folding extends NH_Unit_Test_Case {

	public function test_vietnamese_text_folds_to_ascii_in_both_directions() {
		$this->assertSame( 'thue xe may ha noi', NH_SEO_Search_Text::fold_text( 'Thuê xe máy Hà Nội' ) );
		$this->assertSame( 'thue xe may ha noi', NH_SEO_Search_Text::fold_text( 'THUÊ XE MÁY HÀ NỘI' ) );
		$this->assertSame( 'thue xe may ha noi', NH_SEO_Search_Text::fold_text( 'thue xe may ha noi' ) );
		$this->assertSame( 'da nang duong de la thanh', NH_SEO_Search_Text::fold_text( 'Đà Nẵng, Đường Đê La Thành' ) );
		$this->assertSame( 'uong uong uu dai on', NH_SEO_Search_Text::fold_text( 'ƯƠNG ƯỚNG Ưu đãi ƠN' ) );
		$this->assertSame( 'pho bo com tam bun cha', NH_SEO_Search_Text::fold_text( 'Phở bò – cơm tấm & bún chả' ) );

		// Accented and plain spellings meet in the middle.
		$this->assertSame(
			NH_SEO_Search_Text::fold_text( 'Kinh nghiệm thuê xe máy' ),
			NH_SEO_Search_Text::fold_text( 'kinh nghiem thue xe may' )
		);
	}

	public function test_decomposed_unicode_folds_like_composed_unicode() {
		// "Việt Nam" with combining marks instead of precomposed letters (what some keyboards and files produce).
		$decomposed = "Vie\u{0323}\u{0302}t Nam, tha\u{0306}m, o\u{031B}n, \u{01B0}\u{0301}ng, nga\u{0300}y";
		$this->assertSame( 'viet nam tham on ung ngay', NH_SEO_Search_Text::fold_text( $decomposed ) );
		$this->assertSame( 'a e o', NH_SEO_Search_Text::fold_text( "a\u{0301} e\u{0302} o\u{0309}" ) );
	}

	public function test_only_ascii_letters_and_digits_survive() {
		$this->assertSame( 'foo bar baz 125cc', NH_SEO_Search_Text::fold_text( "foo_bar\tbaz-125cc!" ) );
		$this->assertSame( 'scooter', NH_SEO_Search_Text::fold_text( 'scooter 🛵 摩托车 мотороллер' ) );
		$this->assertSame( '', NH_SEO_Search_Text::fold_text( '摩托车 мотороллер ¿?!' ) );
		$this->assertSame( '', NH_SEO_Search_Text::fold_text( '' ) );
		$this->assertSame( '', NH_SEO_Search_Text::fold_text( array( 'x' ) ) );
		$this->assertSame( '', NH_SEO_Search_Text::fold_text( null ) );
	}

	public function test_invalid_utf8_is_dropped_instead_of_breaking_the_fold() {
		$this->assertSame( 'caf bar', NH_SEO_Search_Text::fold_text( "caf\xE9 \xFF bar" ) );
		$folded = NH_SEO_Search_Text::fold_text( "valid \xC3\x28 text \xF0\x28\x8C\xBC end" );
		$this->assertMatchesRegularExpression( '/^[a-z0-9 ]*$/', $folded );
		$this->assertStringContainsString( 'valid', $folded );
		$this->assertStringContainsString( 'end', $folded );
	}

	public function test_html_comments_scripts_styles_shortcodes_and_entities_are_removed() {
		$html = "<!-- wp:paragraph {\"className\":\"hidden-comment\"} -->\n<p>Thu&ecirc; xe <strong>m&aacute;y</strong> &amp; gi&aacute; r&#7867;&nbsp;t&#7889;t</p>\n<!-- /wp:paragraph -->"
			. '<script>var secret = "scriptword";</script><style>.stylerule { color: red }</style>'
			. '[gallery ids="1,2,3"] <a href="https://example.org/linkwordonly">visible anchor</a>';

		$folded = NH_SEO_Search_Text::fold_html( $html );
		$this->assertSame( 'thue xe may gia re tot visible anchor', $folded );
		foreach ( array( 'hidden', 'comment', 'scriptword', 'secret', 'stylerule', 'gallery', 'linkwordonly', 'example', 'https' ) as $leaked ) {
			$this->assertStringNotContainsString( $leaked, $folded );
		}
	}

	public function test_raw_markup_over_a_megabyte_is_cut_before_folding() {
		$html   = str_repeat( 'word ', 400000 ) . 'tailmarker';
		$folded = NH_SEO_Search_Text::fold_html( $html );
		$this->assertStringNotContainsString( 'tailmarker', $folded );
		$this->assertStringStartsWith( 'word word', $folded );
	}

	public function test_title_folding_keeps_short_words_and_limits_the_token_count() {
		$this->assertSame( 'thue xe may ha noi', NH_SEO_Search_Text::fold_title( 'Thuê xe máy Hà Nội' ) );
		$this->assertSame( 'a b c', NH_SEO_Search_Text::fold_title( 'A, B & C' ) );
		$this->assertCount( 80, explode( ' ', NH_SEO_Search_Text::fold_title( implode( ' ', array_fill( 0, 200, 'word' ) ) ) ) );
		$this->assertSame( 'tom jerry', NH_SEO_Search_Text::fold_title( '<b>Tom</b> &amp; Jerry' ) );
	}

	public function test_taxonomy_words_include_names_and_keywords() {
		$this->assertSame(
			'thue xe may xe so tu dong scooter rental',
			NH_SEO_Search_Text::fold_taxonomy( array( 'Thuê xe máy', 'Xe số tự động', '', null, array( 'x' ), 'Scooter, Rental' ) )
		);
	}

	public function test_body_tokens_are_unique_at_least_three_characters_and_capped() {
		$folded = array( 'the cat sat on the mat and the cat ran', 'xe may xe so noi thue noi' );
		$this->assertSame( 'the cat sat mat and ran may noi thue', NH_SEO_Search_Text::body_tokens( $folded ) );

		$tokens = explode( ' ', NH_SEO_Search_Text::body_tokens( array( implode( ' ', array_map( 'strval', range( 100, 9999 ) ) ) ), 4000 ) );
		$this->assertCount( 4000, $tokens );
		$this->assertSame( '100', $tokens[0], 'The cap keeps the first tokens.' );
		$this->assertSame( array_unique( $tokens ), $tokens );
	}

	public function test_earlier_parts_win_when_the_cap_is_reached() {
		$this->assertSame(
			'summary heading',
			NH_SEO_Search_Text::body_tokens( array( 'summary heading', 'content words later' ), 2 )
		);
	}

	public function test_tokens_longer_than_the_full_text_limit_are_dropped() {
		$long = str_repeat( 'a', 81 );
		$ok   = str_repeat( 'b', 80 );
		$this->assertSame( $ok, NH_SEO_Search_Text::body_tokens( array( $long . ' ' . $ok ) ) );
		$this->assertSame( array(), NH_SEO_Search_Text::query_tokens( $long ) );
		$this->assertSame( array( $ok ), NH_SEO_Search_Text::query_tokens( $ok ) );
	}

	public function test_query_tokens_drop_short_words_stop_words_and_duplicates() {
		$this->assertSame( array( 'thue', 'may', 'noi' ), NH_SEO_Search_Text::query_tokens( 'Thuê xe máy hà nội' ) );
		$this->assertSame( array( 'thue', 'may' ), NH_SEO_Search_Text::query_tokens( 'thuê máy THUÊ may' ) );
		$this->assertSame( array(), NH_SEO_Search_Text::query_tokens( 'xe so di' ), 'Everything shorter than three characters is dropped.' );
		$this->assertSame( array(), NH_SEO_Search_Text::query_tokens( 'the THE The' ), 'InnoDB stop-words never enter a query.' );
		$this->assertSame( array( 'scooter', 'rental' ), NH_SEO_Search_Text::query_tokens( 'the scooter for that rental with' ) );
		$this->assertSame( array(), NH_SEO_Search_Text::query_tokens( '' ) );
		$this->assertSame( array(), NH_SEO_Search_Text::query_tokens( array( 'x' ) ) );
	}

	public function test_vietnamese_words_that_fold_to_stop_words_are_not_sent_to_the_index() {
		// "thẻ" (card) and "thế" fold to "the"; "cơm" (rice) folds to "com": all InnoDB stop-words.
		$this->assertSame( array( 'sim' ), NH_SEO_Search_Text::query_tokens( 'thẻ sim' ) );
		$this->assertSame( array( 'tam' ), NH_SEO_Search_Text::query_tokens( 'cơm tấm' ) );
	}

	public function test_stop_words_are_extensible_for_custom_innodb_stopword_tables() {
		add_filter(
			'nh_seo_search_stopwords',
			static function ( $words ) {
				$words[] = 'Scooter';
				return $words;
			}
		);
		$this->assertSame( array( 'rental' ), NH_SEO_Search_Text::query_tokens( 'scooter rental' ) );
	}

	public function test_operators_quotes_and_sql_never_survive_in_query_tokens() {
		$hostile = array(
			'+-~*<>()"@',
			"' OR 1=1 --",
			'thue") OR 1=1 -- ',
			"thue'; DROP TABLE wp_posts; --",
			'thue* -may +noi ~xxx >yyy <zzz (aaa bbb) "phrase here" @4',
			'\\ \\\' %s %d %% 0x27 /* comment */ UNION SELECT user_pass FROM wp_users',
			"thue\0may",
			"\u{FF0B}\u{FF0D}thue", // Full-width plus and minus.
		);
		foreach ( $hostile as $input ) {
			foreach ( NH_SEO_Search_Text::query_tokens( $input ) as $token ) {
				$this->assertMatchesRegularExpression( '/^[a-z0-9]{3,80}$/', $token, 'Token from: ' . $input );
			}
		}
		$this->assertSame( array(), NH_SEO_Search_Text::query_tokens( '+-~*<>()"@' ) );
		$this->assertSame( array( 'thue' ), NH_SEO_Search_Text::query_tokens( "thue') OR 1=1 -- " ) );
		$this->assertSame( array( 'thue', 'may', 'noi', 'xxx', 'yyy', 'zzz', 'aaa', 'bbb', 'phrase', 'here' ), NH_SEO_Search_Text::query_tokens( $hostile[4] ) );
	}

	public function test_query_tokens_are_bounded_for_very_long_input() {
		$input  = str_repeat( 'abc def ghi ', 20000 ); // 240,000 characters.
		$start  = microtime( true );
		$tokens = NH_SEO_Search_Text::query_tokens( $input );
		$this->assertLessThan( 0.5, microtime( true ) - $start );
		$this->assertSame( array( 'abc', 'def', 'ghi' ), $tokens );

		$many = implode(
			' ',
			array_map(
				static function ( $i ) {
					return 'word' . $i;
				},
				range( 1, 500 )
			)
		);
		$this->assertLessThanOrEqual( NH_SEO_Search_Text::MAX_QUERY_TOKENS, count( NH_SEO_Search_Text::query_tokens( $many ) ) );

		add_filter(
			'nh_seo_search_max_tokens',
			static function () {
				return 3;
			}
		);
		$this->assertSame( array( 'word1', 'word2', 'word3' ), NH_SEO_Search_Text::query_tokens( $many ) );
	}

	public function test_only_the_first_characters_of_a_search_string_count() {
		$input = str_repeat( 'x', NH_SEO_Search_Text::MAX_QUERY_CHARS - 2 ) . ' abc tail';
		$this->assertSame( array(), NH_SEO_Search_Text::query_tokens( $input ), 'The 198-character token is too long, and "abc" is cut after its first letter.' );

		$input = 'abc ' . str_repeat( 'y ', 500 ) . 'late';
		$this->assertSame( array( 'abc' ), NH_SEO_Search_Text::query_tokens( $input ) );
	}

	public function test_search_in_other_scripts_has_no_usable_token() {
		$this->assertSame( array(), NH_SEO_Search_Text::query_tokens( '摩托车租赁' ) );
		$this->assertSame( array(), NH_SEO_Search_Text::query_tokens( 'аренда мотороллера' ) );
	}

	public function test_boolean_expressions_are_prefix_matches_required_or_optional() {
		$this->assertSame( '+thue* +may* +noi*', NH_SEO_Search_Text::boolean_expression( array( 'thue', 'may', 'noi' ), true ) );
		$this->assertSame( 'thue* may* noi*', NH_SEO_Search_Text::boolean_expression( array( 'thue', 'may', 'noi' ), false ) );
		$this->assertSame( '', NH_SEO_Search_Text::boolean_expression( array(), true ) );
	}

	public function test_boolean_expressions_refuse_anything_but_folded_tokens() {
		$this->assertSame(
			'+good*',
			NH_SEO_Search_Text::boolean_expression( array( "x') OR 1=1 --", 'a b', '+a', 'good', '', 'UPPER', str_repeat( 'z', 81 ) ), true )
		);
	}

	public function test_default_stop_words_are_the_documented_innodb_list() {
		$words = NH_SEO_Search_Text::default_stopwords();
		$this->assertCount( 35, $words );
		foreach ( array( 'the', 'com', 'www', 'und', 'about', 'where' ) as $word ) {
			$this->assertContains( $word, $words );
		}
		$this->assertSame( $words, array_unique( $words ) );
	}
}
