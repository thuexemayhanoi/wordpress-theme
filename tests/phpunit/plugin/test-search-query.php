<?php
/**
 * Search through the index: relevance, fallbacks, modes, errors, REST and hostile input.
 *
 * Runs in autocommit mode against InnoDB FULLTEXT (see NH_Search_Test_Case for why). The SQL is plain MySQL
 * 5.6+ syntax, but these tests ran on MariaDB only.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

// Autocommit base class.
require_once __DIR__ . '/class-nh-search-test-case.php';

/**
 * Query integration tests.
 */
class NH_Test_Search_Query extends NH_Search_Test_Case {

	/**
	 * Filler posts so that word statistics (idf) are realistic, then a complete index build.
	 *
	 * @param int $fillers Number of filler posts.
	 * @return void
	 */
	private function build_index_with_fillers( $fillers = 15 ) {
		$this->bulk_posts(
			$fillers,
			static function ( $i ) {
				return array(
					'title'   => 'Filler headline ' . $i,
					'content' => 'Lorem ipsum dolor sit amet consectetur ' . $i,
				);
			}
		);
		$this->index_all();
	}

	/**
	 * Main-query search as a visitor sees it. Returns the IDs in result order.
	 *
	 * @param string $search Search string.
	 * @return int[]
	 */
	private function main_search( $search ) {
		$this->go_to( home_url( '/?s=' . rawurlencode( $search ) ) );
		return array_map( 'intval', wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );
	}

	public function test_vietnamese_search_ignores_accents_and_case() {
		$post_id = $this->make_post(
			array(
				'post_title'   => 'Thuê xe máy Hà Nội',
				'post_content' => 'Giá thuê xe máy tự động ở Hà Nội cho khách du lịch.',
			)
		);
		$this->make_post( array( 'post_title' => 'Một bài khác hoàn toàn' ) );
		$this->build_index_with_fillers();

		foreach ( array( 'Thuê xe máy', 'thue xe may', 'THUE XE MAY', 'thuê  xe   máy hà nội', 'hà nội', 'NOI', 'tu dong', 'khách du lịch', 'thue' ) as $search ) {
			$this->assertSame( array( $post_id ), $this->search_ids( $search ), $search );
		}
	}

	public function test_prefix_matching_finds_partial_words() {
		$post_id = $this->make_post( array( 'post_title' => 'Motorbike rentals in Hanoi' ) );
		$this->build_index_with_fillers();

		$this->assertSame( array( $post_id ), $this->search_ids( 'motor' ) );
		$this->assertSame( array( $post_id ), $this->search_ids( 'rent' ) );
		$this->assertSame( array( $post_id ), $this->search_ids( 'hano' ) );
	}

	public function test_the_main_search_query_is_one_match_query_ordered_by_relevance() {
		$post_id = $this->make_post( array( 'post_title' => 'Zebra crossing rules' ) );
		$this->build_index_with_fillers();

		$this->record_sql();
		$ids = $this->main_search( 'zebra' );
		$this->stop_sql_log();

		$this->assertSame( array( $post_id ), $ids );
		$match_queries = $this->sql_containing( 'MATCH(' );
		$this->assertCount( 1, $match_queries, 'The results come from exactly one query that uses the index.' );

		$sql = $match_queries[0];
		$this->assertStringContainsString( 'SQL_CALC_FOUND_ROWS', $sql );
		$this->assertStringContainsString( 'INNER JOIN ' . NH_SEO_Search_Index::table() . ' AS nh_si ON ( nh_si.post_id = wptests_posts.ID )', $sql );
		$this->assertStringContainsString( "MATCH(nh_si.title_fold, nh_si.tax_fold, nh_si.body_fold) AGAINST ('+zebra*' IN BOOLEAN MODE)", $sql );
		$this->assertStringContainsString( "ORDER BY ( MATCH(nh_si.title_fold) AGAINST ('zebra*' IN BOOLEAN MODE) * 3 + MATCH(nh_si.tax_fold) AGAINST ('zebra*' IN BOOLEAN MODE) * 2 + MATCH(nh_si.title_fold, nh_si.tax_fold, nh_si.body_fold) AGAINST ('+zebra*' IN BOOLEAN MODE) ) DESC, wptests_posts.post_date DESC, wptests_posts.ID DESC", $sql );
		$this->assertStringNotContainsString( 'LIKE', $sql, 'The LIKE clauses are replaced.' );
		$this->assertStringContainsString( "post_status = 'publish'", $sql );
		$this->assertCount( 1, $this->sql_containing( 'SELECT FOUND_ROWS()' ) );
	}

	public function test_the_index_query_is_answered_by_the_fulltext_index() {
		global $wpdb;
		$this->make_post( array( 'post_title' => 'Zebra crossing rules' ) );
		$this->build_index_with_fillers();

		$query = new WP_Query( array( 's' => 'zebra' ) );
		$plan  = $wpdb->get_results( 'EXPLAIN ' . $query->request, ARRAY_A );

		$index_row = null;
		foreach ( $plan as $row ) {
			if ( 'nh_si' === $row['table'] ) {
				$index_row = $row;
			}
		}
		$this->assertNotNull( $index_row, 'The index table is part of the plan.' );
		$this->assertSame( 'fulltext', $index_row['type'] );
		$this->assertSame( 'ft_all', $index_row['key'] );
	}

	public function test_title_matches_beat_taxonomy_matches_beat_body_matches_whatever_the_date() {
		$this->bulk_posts(
			15,
			static function ( $i ) {
				return array(
					'title'   => 'Filler headline ' . $i,
					'content' => 'Lorem ipsum dolor sit amet ' . $i,
				);
			}
		);
		$body  = $this->make_post(
			array(
				'post_title'   => 'Unrelated headline',
				'post_content' => 'This article talks about zebra crossing safety.',
				'post_date'    => '2026-03-01 10:00:00', // Newest.
			)
		);
		$tax   = $this->make_post(
			array(
				'post_title'   => 'Another headline',
				'post_content' => 'Nothing special here.',
				'tags_input'   => array( 'zebra' ),
				'post_date'    => '2026-02-01 10:00:00',
			)
		);
		$title = $this->make_post(
			array(
				'post_title'   => 'Zebra on the road',
				'post_content' => 'Short.',
				'post_date'    => '2026-01-01 10:00:00', // Oldest.
			)
		);
		$this->index_all();

		$this->assertSame( array( $title, $tax, $body ), $this->search_ids( 'zebra' ) );
		$this->assertSame( array( $title, $tax, $body ), $this->main_search( 'zebra' ) );
	}

	public function test_focus_keywords_and_extra_keywords_count_as_taxonomy_matches() {
		$this->bulk_posts(
			15,
			static function ( $i ) {
				return array(
					'title'   => 'Filler headline ' . $i,
					'content' => 'Lorem ipsum ' . $i,
				);
			}
		);
		$body = $this->make_post(
			array(
				'post_title'   => 'Plain headline',
				'post_content' => 'Mentions quokka in passing.',
				'post_date'    => '2026-03-01 10:00:00',
			)
		);
		$kw   = $this->make_post(
			array(
				'post_title'   => 'Keyword headline',
				'post_content' => 'Nothing.',
				'post_date'    => '2026-01-01 10:00:00',
			)
		);
		update_post_meta( $kw, NH_SEO_Keys::META_KEYWORDS, 'quokka, marsupial' );
		$this->index_all();

		$this->assertSame( array( $kw, $body ), $this->search_ids( 'quokka' ) );
	}

	public function test_equally_relevant_results_come_newest_first() {
		$old = $this->make_post(
			array(
				'post_title' => 'Giraffe guide',
				'post_date'  => '2026-01-01 10:00:00',
			)
		);
		$new = $this->make_post(
			array(
				'post_title' => 'Giraffe guide',
				'post_date'  => '2026-02-01 10:00:00',
			)
		);
		$mid = $this->make_post(
			array(
				'post_title' => 'Giraffe guide',
				'post_date'  => '2026-01-15 10:00:00',
			)
		);
		$this->build_index_with_fillers();

		$this->assertSame( array( $new, $mid, $old ), $this->search_ids( 'giraffe' ) );
	}

	public function test_every_word_is_required_before_any_word_is_enough() {
		$both = $this->make_post(
			array(
				'post_title'   => 'Alpaca and llama care',
				'post_content' => 'Both animals.',
			)
		);
		$this->make_post( array( 'post_title' => 'Alpaca only' ) );
		$this->make_post( array( 'post_title' => 'Llama only' ) );
		$this->build_index_with_fillers();

		$this->go_to( home_url( '/?s=alpaca+llama' ) );
		$this->assertSame( array( $both ), array_map( 'intval', wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) ) );
		$this->assertFalse( nh_seo_search_was_relaxed(), 'All words matched, nothing was relaxed.' );
	}

	public function test_when_no_post_has_every_word_the_search_is_relaxed_once_and_flagged() {
		$alpaca = $this->make_post(
			array(
				'post_title' => 'Alpaca farm visit',
				'post_date'  => '2026-02-01 10:00:00',
			)
		);
		$llama  = $this->make_post(
			array(
				'post_title' => 'Llama trekking',
				'post_date'  => '2026-01-01 10:00:00',
			)
		);
		$this->build_index_with_fillers();

		$this->record_sql();
		$this->go_to( home_url( '/?s=alpaca+llama' ) );
		$this->stop_sql_log();

		$ids = array_map( 'intval', wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );
		$this->assertSame( array( $alpaca, $llama ), $ids );
		$this->assertTrue( nh_seo_search_was_relaxed(), 'The displayed results come from the relaxed query.' );
		$this->assertTrue( nh_seo_search_was_relaxed( $GLOBALS['wp_query'] ) );

		$matches = $this->sql_containing( 'MATCH(' );
		$this->assertCount( 2, $matches, 'One strict query, then exactly one relaxed retry.' );
		$this->assertStringContainsString( "AGAINST ('+alpaca* +llama*' IN BOOLEAN MODE) )", $matches[0] );
		$this->assertStringContainsString( "WHERE 1=1  AND ( MATCH(nh_si.title_fold, nh_si.tax_fold, nh_si.body_fold) AGAINST ('alpaca* llama*' IN BOOLEAN MODE) )", $matches[1] );

		$this->assertEquals( 2, $GLOBALS['wp_query']->found_posts );
		$this->assertSame( array( 'alpaca', 'llama' ), $GLOBALS['wp_query']->get( NH_SEO_Search_Query::VAR_PLAN )['tokens'] );
	}

	public function test_relaxed_results_still_rank_the_post_with_more_matching_words_first() {
		$one = $this->make_post(
			array(
				'post_title' => 'Alpaca farm',
				'post_date'  => '2026-03-01 10:00:00',
			)
		);
		$two = $this->make_post(
			array(
				'post_title'   => 'Alpaca llama farm',
				'post_content' => 'Quokka wombat.',
				'post_date'    => '2026-01-01 10:00:00',
			)
		);
		$this->build_index_with_fillers();

		// "alpaca llama quokka koala": no post has all four, one has three of them, one has one.
		$this->assertSame( array( $two, $one ), $this->search_ids( 'alpaca llama quokka koala' ) );
	}

	public function test_relaxed_pagination_stays_consistent_from_page_to_page() {
		$posts = array();
		foreach ( array( 'Alpaca one', 'Llama two', 'Alpaca three', 'Llama four', 'Alpaca five' ) as $i => $title ) {
			$posts[] = $this->make_post(
				array(
					'post_title' => $title,
					'post_date'  => '2026-02-0' . ( $i + 1 ) . ' 10:00:00',
				)
			);
		}
		$this->build_index_with_fillers();

		$query = function ( $page ) {
			return new WP_Query(
				array(
					's'              => 'alpaca llama',
					'posts_per_page' => 2,
					'paged'          => $page,
				)
			);
		};

		$page1 = $query( 1 );
		$this->assertTrue( nh_seo_search_was_relaxed( $page1 ) );
		$this->assertSame( 5, $page1->found_posts );
		$this->assertSame( 3, $page1->max_num_pages );

		$this->record_sql();
		$page2 = $query( 2 );
		$page3 = $query( 3 );
		$page4 = $query( 4 );
		$this->stop_sql_log();

		$all = array_merge( wp_list_pluck( $page1->posts, 'ID' ), wp_list_pluck( $page2->posts, 'ID' ), wp_list_pluck( $page3->posts, 'ID' ) );
		$this->assertCount( 5, $all );
		$this->assertSame( array_values( array_unique( $all ) ), $all, 'No post repeats across pages.' );
		$this->assertEqualsCanonicalizing( $posts, $all, 'Page 2 and 3 are relaxed too, although their strict query is empty.' );
		$this->assertTrue( nh_seo_search_was_relaxed( $page2 ) );
		$this->assertTrue( nh_seo_search_was_relaxed( $page3 ) );
		$this->assertSame( 5, $page2->found_posts );
		$this->assertCount( 1, $page3->posts );
		$this->assertSame( array(), $page4->posts, 'Past the end stays empty.' );
		$this->assertNotEmpty( $this->sql_containing( 'SELECT 1 FROM' ), 'Later empty pages ask the index which stage applies.' );
	}

	public function test_a_later_page_past_the_end_of_strict_results_stays_empty_and_strict() {
		$this->make_post( array( 'post_title' => 'Alpaca llama one' ) );
		$this->make_post( array( 'post_title' => 'Alpaca llama two' ) );
		$this->build_index_with_fillers();

		$page2 = new WP_Query(
			array(
				's'              => 'alpaca llama',
				'posts_per_page' => 2,
				'paged'          => 2,
			)
		);
		$this->assertSame( array(), $page2->posts );
		$this->assertFalse( nh_seo_search_was_relaxed( $page2 ), 'Past the end of strict results: no relaxation, no native fallback.' );
		$this->assertSame( 'strict', $page2->get( NH_SEO_Search_Query::VAR_PLAN )['stage'] );
	}

	public function test_native_search_answers_when_the_index_finds_nothing_at_all() {
		$post_id = $this->make_post( array( 'post_content' => 'Everything about motorbikes.' ) );
		$this->build_index_with_fillers();

		// A prefix search cannot find the middle of a word; WordPress's own LIKE search can.
		$this->record_sql();
		$ids = $this->search_ids( 'orbike' );
		$this->stop_sql_log();

		$this->assertSame( array( $post_id ), $ids );
		$this->assertCount( 1, $this->sql_containing( 'MATCH(' ), 'A single word has no relaxed form: the index is asked once.' );
		$this->assertNotEmpty( $this->sql_containing( "LIKE '%orbike%'" ), 'Then the native query runs.' );
	}

	/**
	 * Set the size above which an empty index answer is final (the default is 15,000 posts).
	 *
	 * @param int $limit Limit for the filter nh_seo_search_native_fallback_limit.
	 * @return void
	 */
	private function set_fallback_limit( $limit ) {
		add_filter(
			'nh_seo_search_native_fallback_limit',
			static function () use ( $limit ) {
				return $limit;
			}
		);
	}

	public function test_a_large_index_keeps_its_empty_answer_instead_of_scanning_every_post_natively() {
		$post_id = $this->make_post( array( 'post_content' => 'Everything about motorbikes.' ) );
		$this->make_post( array( 'post_title' => 'Alpaca llama one' ) );
		$this->build_index_with_fillers();
		$this->assertGreaterThan( 5, NH_SEO_Search_Index::count() );
		$this->set_fallback_limit( 5 ); // The index now counts as "large".

		// Text inside a word is only found by native search: on a large site the answer is empty.
		$this->record_sql();
		$this->assertSame( array(), $this->search_ids( 'orbike' ), 'Mid-word text is outside what the index finds.' );
		$this->assertSame( array(), $this->search_ids( 'qwertyuiop' ), 'A nonsense word.' );
		$this->assertSame( array(), $this->search_ids( 'qwertyuiop asdfghjkl' ), 'Nonsense words.' );
		$this->stop_sql_log();

		$this->assertSame( array(), $this->sql_containing( "LIKE '%" ), 'No query reads every post.' );
		$this->assertCount( 4, $this->sql_containing( 'MATCH(' ), 'One index query per single word, a strict and a relaxed one for the two words.' );

		// The same search under the limit still ends in native search, which finds the word.
		remove_all_filters( 'nh_seo_search_native_fallback_limit' );
		$this->assertSame( array( $post_id ), $this->search_ids( 'orbike' ) );
	}

	public function test_a_large_index_still_relaxes_and_answers_what_it_can() {
		$this->make_post( array( 'post_title' => 'Alpaca llama one' ) );
		$this->build_index_with_fillers();
		$this->set_fallback_limit( 5 );

		$query = new WP_Query( array( 's' => 'alpaca qwertyuiop' ) );
		$this->assertCount( 1, $query->posts, 'Relaxed results are not affected by the limit.' );
		$this->assertTrue( nh_seo_search_was_relaxed( $query ) );
	}

	public function test_a_large_index_keeps_later_empty_pages_empty_without_a_native_scan() {
		$this->make_post( array( 'post_title' => 'Alpaca llama one' ) );
		$this->build_index_with_fillers();
		$this->set_fallback_limit( 5 );

		$this->record_sql();
		$page2 = new WP_Query(
			array(
				's'              => 'qwertyuiop',
				'posts_per_page' => 2,
				'paged'          => 2,
			)
		);
		$this->stop_sql_log();

		$this->assertSame( array(), $page2->posts );
		$this->assertSame( array(), $this->sql_containing( "LIKE '%" ), 'The probe found nothing and native search does not run.' );
		$this->assertSame( 'strict', $page2->get( NH_SEO_Search_Query::VAR_PLAN )['stage'] );
	}

	public function test_a_database_error_hands_the_request_to_native_search_even_on_a_large_index() {
		global $wpdb;
		$post_id = $this->make_post( array( 'post_title' => 'Resilient zebra' ) );
		$this->build_index_with_fillers();
		$this->set_fallback_limit( 5 );
		$table = NH_SEO_Search_Index::table();

		$wpdb->query( "ALTER TABLE {$table} DROP INDEX ft_title" );
		$suppress = $wpdb->suppress_errors( true );
		try {
			$this->assertSame( array( $post_id ), $this->search_ids( 'zebra' ), 'An error is not an empty answer: native search answers.' );
			$this->assertNotNull( NH_SEO_Search_State::breaker() );
		} finally {
			$wpdb->suppress_errors( $suppress );
			NH_SEO_Search_Index::drop_table();
			NH_SEO_Search_Index::create_table();
			NH_SEO_Search_State::clear_breaker();
		}
	}

	public function test_the_limit_can_be_switched_off_either_way_and_the_size_comes_from_the_last_build_or_the_rows() {
		$post_id = $this->make_post( array( 'post_content' => 'Everything about motorbikes.' ) );
		$this->build_index_with_fillers();

		$this->set_fallback_limit( 0 );
		$this->assertSame( array(), $this->search_ids( 'orbike' ), 'Zero: never repeated natively, whatever the size.' );
		remove_all_filters( 'nh_seo_search_native_fallback_limit' );

		$this->set_fallback_limit( -1 );
		$this->assertSame( array( $post_id ), $this->search_ids( 'orbike' ), 'A negative limit: always repeated natively.' );
		remove_all_filters( 'nh_seo_search_native_fallback_limit' );

		// Size: the posts of the last complete build, or the rows when no build has recorded a number.
		$this->set_fallback_limit( 5 );
		NH_SEO_Search_State::save( array( 'total' => 0 ) );
		$this->assertSame( array(), $this->search_ids( 'orbike' ), 'No recorded build: the rows are counted.' );
		NH_SEO_Search_State::save( array( 'total' => 3 ) );
		$this->assertSame( array( $post_id ), $this->search_ids( 'orbike' ), 'The recorded size of the last build is used.' );
	}

	public function test_a_search_nobody_can_answer_returns_nothing_without_errors() {
		global $wpdb;
		$this->make_post( array( 'post_title' => 'Something else' ) );
		$this->build_index_with_fillers();

		$this->assertSame( array(), $this->search_ids( 'qwertyuiop asdfghjkl' ) );
		$this->assertSame( '', $wpdb->last_error );
		$this->assertNull( NH_SEO_Search_State::breaker(), 'No result is not an error.' );
	}

	public function test_short_words_and_stop_words_use_native_search() {
		$post_id = $this->make_post( array( 'post_title' => 'Xe số tự động' ) );
		$this->build_index_with_fillers();

		foreach ( array( 'xe so', 'xe', 'tu do' ) as $search ) {
			$this->record_sql();
			$ids = $this->search_ids( $search );
			$this->stop_sql_log();
			$this->assertContains( $post_id, $ids, $search . ': native search (substring matching) still finds it.' );
			$this->assertSame( array(), $this->sql_containing( 'MATCH(' ), $search . ': no usable word, so the index is not used.' );
		}

		$this->make_post( array( 'post_title' => 'The Who' ) );
		$this->record_sql();
		$this->search_ids( 'the' );
		$this->stop_sql_log();
		$this->assertSame( array(), $this->sql_containing( 'MATCH(' ), 'A stop-word alone is never sent to the index.' );
	}

	public function test_native_search_is_used_until_the_index_is_complete() {
		$this->make_post( array( 'post_title' => 'Halfway zebra one' ) );
		$this->make_post( array( 'post_title' => 'Halfway zebra two' ) );
		$this->bulk_posts(
			30,
			static function ( $i ) {
				return array(
					'title'   => 'Filler ' . $i,
					'content' => 'Lorem ' . $i,
				);
			}
		);
		NH_SEO_Search_Indexer::reset();

		$state = NH_SEO_Search_Builder::start();
		NH_SEO_Search_Builder::run_batch( 0, 10, $state['build'] );
		$this->assertFalse( NH_SEO_Search_State::get()['ready'] );
		$this->assertGreaterThan( 0, NH_SEO_Search_Index::count() );

		$this->record_sql();
		$ids = $this->search_ids( 'zebra' );
		$this->stop_sql_log();
		$this->assertCount( 2, $ids, 'Native search still finds everything while the index is partial.' );
		$this->assertSame( array(), $this->sql_containing( 'MATCH(' ), 'A partial index is never used in auto mode.' );
		$this->assertNotEmpty( $this->sql_containing( 'LIKE' ) );

		// Finish the build: the very next search uses the index.
		NH_SEO_Search_Builder::rebuild();
		$this->record_sql();
		$this->search_ids( 'zebra' );
		$this->stop_sql_log();
		$this->assertNotEmpty( $this->sql_containing( 'MATCH(' ) );
	}

	public function test_index_mode_uses_the_index_even_when_the_build_is_not_complete() {
		$this->make_post( array( 'post_title' => 'Partial zebra' ) );
		$this->build_index_with_fillers( 30 );
		NH_SEO_Search_State::save(
			array(
				'ready'    => false,
				'building' => true,
			)
		);

		$this->record_sql();
		$this->search_ids( 'zebra' );
		$this->stop_sql_log();
		$this->assertSame( array(), $this->sql_containing( 'MATCH(' ), 'auto mode: not ready, native.' );

		$this->set_search_mode( 'index' );
		$this->record_sql();
		$ids = $this->search_ids( 'zebra' );
		$this->stop_sql_log();
		$this->assertCount( 1, $ids );
		$this->assertNotEmpty( $this->sql_containing( 'MATCH(' ), 'index mode: use it when it exists.' );
	}

	public function test_native_mode_never_uses_the_index() {
		$this->make_post( array( 'post_title' => 'Native zebra' ) );
		$this->build_index_with_fillers();
		$this->set_search_mode( 'native' );

		$this->record_sql();
		$ids = $this->search_ids( 'zebra' );
		$this->stop_sql_log();

		$this->assertCount( 1, $ids );
		$this->assertSame( array(), $this->sql_containing( 'MATCH(' ) );
		$this->assertSame( array(), $this->sql_containing( 'nh_si' ) );
		$this->assertFalse( NH_SEO_Search_State::is_active() );
	}

	public function test_index_mode_without_a_table_falls_back_quietly() {
		global $wpdb;
		$this->make_post( array( 'post_title' => 'Tableless zebra' ) );
		NH_SEO_Search_Index::drop_table();
		$this->set_search_mode( 'index' );

		$ids = $this->search_ids( 'zebra' );

		$this->assertCount( 1, $ids );
		$this->assertSame( '', $wpdb->last_error );
		$this->assertNull( NH_SEO_Search_State::breaker(), 'A missing table is not a database error.' );
	}

	public function test_a_database_error_pauses_the_index_and_native_search_answers_the_same_request() {
		global $wpdb;
		$post_id = $this->make_post( array( 'post_title' => 'Resilient zebra' ) );
		$this->build_index_with_fillers();
		$table = NH_SEO_Search_Index::table();

		// Break the title index: the relevance expression can no longer be evaluated.
		$wpdb->query( "ALTER TABLE {$table} DROP INDEX ft_title" );
		$suppress = $wpdb->suppress_errors( true );
		try {
			$this->record_sql();
			$ids = $this->search_ids( 'zebra' );
			$this->stop_sql_log();

			$this->assertSame( array( $post_id ), $ids, 'The visitor still gets the answer, from native search.' );
			$breaker = NH_SEO_Search_State::breaker();
			$this->assertNotNull( $breaker, 'The index is paused.' );
			$this->assertEqualsWithDelta( time() + 600, $breaker['until'], 5, 'For ten minutes.' );
			$this->assertStringContainsString( 'FULLTEXT', NH_SEO_Search_State::get()['last_error'] );
			$this->assertStringContainsString( 'paused', NH_SEO_Search_State::status_line() );
			$this->assertCount( 1, $this->sql_containing( 'MATCH(' ), 'The failing query ran once; the retry is native.' );
			$this->assertNotEmpty( $this->sql_containing( "LIKE '%zebra%'" ) );

			// While paused nothing even tries the index.
			$this->record_sql();
			$again = $this->search_ids( 'zebra' );
			$this->stop_sql_log();
			$this->assertSame( array( $post_id ), $again );
			$this->assertSame( array(), $this->sql_containing( 'MATCH(' ) );
			$this->assertFalse( NH_SEO_Search_State::is_active() );
		} finally {
			$wpdb->suppress_errors( $suppress );
			NH_SEO_Search_Index::drop_table();
			NH_SEO_Search_Index::create_table();
		}

		// Repaired and un-paused: the index is used again after a rebuild.
		NH_SEO_Search_State::clear_breaker();
		$this->index_all();
		$this->record_sql();
		$this->assertSame( array( $post_id ), $this->search_ids( 'zebra' ) );
		$this->stop_sql_log();
		$this->assertNotEmpty( $this->sql_containing( 'MATCH(' ) );
	}

	public function test_the_pause_expires_by_itself() {
		$this->make_post( array( 'post_title' => 'Expiring zebra' ) );
		$this->build_index_with_fillers();

		NH_SEO_Search_State::trip( 'simulated failure' );
		$this->assertFalse( NH_SEO_Search_State::is_active() );

		delete_transient( NH_SEO_Search_State::BREAKER ); // What expiry does.
		$this->assertTrue( NH_SEO_Search_State::is_active() );
	}

	public function test_exclusions_phrases_exact_matches_and_custom_columns_use_native_search() {
		$wanted = $this->make_post(
			array(
				'post_title'   => 'Alpaca farm',
				'post_content' => 'Alpaca farm visit.',
			)
		);
		$other  = $this->make_post(
			array(
				'post_title'   => 'Alpaca llama farm',
				'post_content' => 'Alpaca and llama together.',
			)
		);
		$this->build_index_with_fillers();

		$this->record_sql();
		$this->assertSame( array( $wanted ), $this->search_ids( 'alpaca -llama' ), 'Native search excludes posts with "llama".' );
		$this->assertSame( array(), $this->sql_containing( 'MATCH(' ), 'An exclusion is never turned into an inclusion by the index.' );
		$this->stop_sql_log();

		foreach (
			array(
				'sentence'       => array(
					's'        => 'alpaca farm',
					'sentence' => true,
				),
				'exact'          => array(
					's'     => 'alpaca farm',
					'exact' => true,
				),
				'search columns' => array(
					's'              => 'alpaca',
					'search_columns' => array( 'post_title' ),
				),
			) as $label => $args
		) {
			$this->record_sql();
			new WP_Query( $args );
			$this->stop_sql_log();
			$this->assertSame( array(), $this->sql_containing( 'MATCH(' ), $label );
		}

		// A hyphen inside a word is not an exclusion.
		$this->record_sql();
		$this->search_ids( 'alpaca-farm' );
		$this->stop_sql_log();
		$this->assertNotEmpty( $this->sql_containing( 'MATCH(' ) );
		$this->assertNotSame( $other, $wanted );
	}

	public function test_queries_that_may_return_anything_but_public_indexed_posts_use_native_search() {
		register_post_type(
			'nh_guide',
			array(
				'public'       => true,
				'show_in_rest' => true,
			)
		);
		try {
			$this->make_post( array( 'post_title' => 'Zebra post' ) );
			$this->make_post(
				array(
					'post_title' => 'Zebra page',
					'post_type'  => 'page',
				)
			);
			$this->make_post(
				array(
					'post_title' => 'Zebra guide',
					'post_type'  => 'nh_guide',
				)
			);
			$this->make_post(
				array(
					'post_title'  => 'Zebra draft',
					'post_status' => 'draft',
				)
			);
			$this->build_index_with_fillers();

			$uses_index = function ( array $args ) {
				$this->record_sql();
				new WP_Query(
					array_merge(
						array(
							's'             => 'zebra',
							'cache_results' => false,
						),
						$args
					)
				);
				$sql = $this->stop_sql_log();
				return ! empty(
					array_filter(
						$sql,
						static function ( $q ) {
							return false !== strpos( $q, 'MATCH(' );
						}
					)
				);
			};

			// The default search covers every searchable type, including the one that is not indexed.
			$this->assertFalse( $uses_index( array() ), 'A searchable but unindexed post type means native search.' );
			$this->assertFalse( $uses_index( array( 'post_type' => 'any' ) ) );
			$this->assertFalse( $uses_index( array( 'post_type' => array( 'post', 'nh_guide' ) ) ) );
			$this->assertFalse( $uses_index( array( 'post_type' => 'nh_guide' ) ) );

			$this->assertTrue( $uses_index( array( 'post_type' => 'post' ) ) );
			$this->assertTrue( $uses_index( array( 'post_type' => array( 'post', 'page' ) ) ) );
			$this->assertTrue( $uses_index( array( 'post_type' => array( 'post', 'attachment' ) ) ), 'Attachments are never "publish".' );

			$this->assertFalse(
				$uses_index(
					array(
						'post_type'   => 'post',
						'post_status' => 'any',
					)
				)
			);
			$this->assertFalse(
				$uses_index(
					array(
						'post_type'   => 'post',
						'post_status' => 'draft',
					)
				)
			);
			$this->assertFalse(
				$uses_index(
					array(
						'post_type'   => 'post',
						'post_status' => array( 'publish', 'draft' ),
					)
				)
			);
			$this->assertFalse(
				$uses_index(
					array(
						'post_type'   => 'post',
						'post_status' => 'publish,private',
					)
				)
			);
			$this->assertTrue(
				$uses_index(
					array(
						'post_type'   => 'post',
						'post_status' => 'publish',
					)
				)
			);
			$this->assertTrue(
				$uses_index(
					array(
						'post_type'   => 'post',
						'post_status' => array( 'publish' ),
					)
				)
			);

			$this->assertFalse(
				$uses_index(
					array(
						'post_type'    => 'post',
						'has_password' => true,
					)
				)
			);
			$this->assertFalse(
				$uses_index(
					array(
						'post_type'     => 'post',
						'post_password' => 'x',
					)
				)
			);

			// The post types that are indexed can be extended.
			add_filter(
				'nh_seo_search_post_types',
				static function ( $types ) {
					$types[] = 'nh_guide';
					return $types;
				}
			);
			$this->assertTrue( $uses_index( array() ), 'With the guide type indexed too, the default search uses the index.' );
		} finally {
			unregister_post_type( 'nh_guide' );
		}
	}

	public function test_the_admin_uses_native_search_unless_published_posts_are_asked_for() {
		$this->make_post( array( 'post_title' => 'Admin zebra' ) );
		$draft = $this->make_post(
			array(
				'post_title'  => 'Admin zebra draft',
				'post_status' => 'draft',
			)
		);
		$this->build_index_with_fillers();

		set_current_screen( 'edit-post' );
		$this->assertTrue( is_admin() );

		$this->record_sql();
		$ids = $this->search_ids( 'zebra', array( 'post_type' => 'post' ) );
		$this->stop_sql_log();
		$this->assertContains( $draft, $ids, 'The admin list finds drafts: native search.' );
		$this->assertSame( array(), $this->sql_containing( 'MATCH(' ) );

		$this->record_sql();
		$this->search_ids(
			'zebra',
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
			)
		);
		$this->stop_sql_log();
		$this->assertNotEmpty( $this->sql_containing( 'MATCH(' ), 'Asking for published posts explicitly is safe.' );
	}

	public function test_an_explicit_order_wins_but_the_index_still_filters() {
		$a = $this->make_post(
			array(
				'post_title' => 'Aardvark zebra',
				'post_date'  => '2026-01-01 10:00:00',
			)
		);
		$b = $this->make_post(
			array(
				'post_title'   => 'Mongoose',
				'post_content' => 'Zebra in the body.',
				'post_date'    => '2026-02-01 10:00:00',
			)
		);
		$c = $this->make_post(
			array(
				'post_title' => 'Zebra zebra zulu',
				'post_date'  => '2026-03-01 10:00:00',
			)
		);
		$this->build_index_with_fillers();

		$this->assertSame( array( $c, $a, $b ), $this->search_ids( 'zebra' ), 'Relevance first (title matches), then newest.' );
		$this->assertSame( array( $c, $b, $a ), $this->search_ids( 'zebra', array( 'orderby' => 'date' ) ) );
		$this->assertSame(
			array( $a, $b, $c ),
			$this->search_ids(
				'zebra',
				array(
					'orderby' => 'date',
					'order'   => 'ASC',
				)
			)
		);
		$this->assertSame(
			array( $a, $b, $c ),
			$this->search_ids(
				'zebra',
				array(
					'orderby' => 'title',
					'order'   => 'ASC',
				)
			)
		);
		$this->assertSame( array( $c, $a, $b ), $this->search_ids( 'zebra', array( 'orderby' => 'relevance' ) ) );

		$this->record_sql();
		$this->search_ids(
			'zebra',
			array(
				'orderby'       => 'date',
				'cache_results' => false,
			)
		);
		$sql  = $this->stop_sql_log();
		$main = array_values(
			array_filter(
				$sql,
				static function ( $q ) {
					return false !== strpos( $q, 'SQL_CALC_FOUND_ROWS' );
				}
			)
		);
		$this->assertStringContainsString( 'AGAINST (', $main[0] );
		$this->assertStringContainsString( 'ORDER BY wptests_posts.post_date DESC', $main[0], 'WordPress keeps the order the caller asked for.' );
		$this->assertStringNotContainsString( 'nh_si.title_fold) AGAINST', $main[0] );
	}

	public function test_other_query_arguments_combine_with_the_search() {
		$cat    = self::factory()->category->create( array( 'name' => 'Safari' ) );
		$inside = $this->make_post(
			array(
				'post_title'    => 'Zebra in safari',
				'post_category' => array( $cat ),
			)
		);
		$this->make_post( array( 'post_title' => 'Zebra outside' ) );
		$this->build_index_with_fillers();

		$this->assertSame( array( $inside ), $this->search_ids( 'zebra', array( 'cat' => $cat ) ) );
		$this->assertSame( array( $inside ), $this->search_ids( 'zebra', array( 'post__in' => array( $inside ) ) ) );
		$this->assertSame( array( $inside ), $this->search_ids( 'zebra', array( 'category_name' => 'safari' ) ) );
		$this->assertCount( 1, $this->search_ids( 'zebra', array( 'post__not_in' => array( $inside ) ) ) );
		$this->assertSame( array(), $this->search_ids( 'zebra', array( 'post_type' => 'page' ) ) );

		update_post_meta( $inside, 'nh_test_flag', 'yes' );
		$this->assertSame(
			array( $inside ),
			$this->search_ids(
				'zebra',
				array(
					'meta_key'   => 'nh_test_flag',
					'meta_value' => 'yes',
				)
			)
		);
	}

	public function test_unpublished_and_protected_posts_are_never_returned_even_from_stale_rows() {
		global $wpdb;
		$public  = $this->make_post( array( 'post_title' => 'Public zebra' ) );
		$draft   = $this->make_post( array( 'post_title' => 'Draft zebra' ) );
		$trashed = $this->make_post( array( 'post_title' => 'Trashed zebra' ) );
		$secret  = $this->make_post( array( 'post_title' => 'Secret zebra' ) );
		$this->build_index_with_fillers();

		// The posts change behind the plugin's back, so their index rows are stale.
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'draft' ), array( 'ID' => $draft ) );
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'trash' ), array( 'ID' => $trashed ) );
		$wpdb->update( $wpdb->posts, array( 'post_password' => 'pw' ), array( 'ID' => $secret ) );
		wp_cache_flush();
		$this->assertCount( 19, NH_SEO_Search_Index::ids(), 'Sanity: the stale rows are still in the index.' );

		$this->assertSame( array( $public ), $this->search_ids( 'zebra' ) );
		$this->assertSame( array( $public ), $this->main_search( 'zebra' ) );
	}

	public function test_known_limitation_private_posts_are_not_indexed_so_index_results_leave_them_out() {
		$admin   = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$public  = $this->make_post( array( 'post_title' => 'Public zebra' ) );
		$private = $this->make_post(
			array(
				'post_title'  => 'Private zebra',
				'post_status' => 'private',
				'post_author' => $admin,
			)
		);
		$this->build_index_with_fillers();
		wp_set_current_user( $admin );

		$this->assertSame( array( $public ), $this->search_ids( 'zebra' ), 'With the index, private posts do not appear (documented limitation).' );

		$this->set_search_mode( 'native' );
		$this->assertEqualsCanonicalizing( array( $public, $private ), $this->search_ids( 'zebra' ), 'Native search shows them to users who may read them.' );
	}

	public function test_a_filter_can_keep_a_query_on_native_search() {
		$this->make_post( array( 'post_title' => 'Filtered zebra' ) );
		$this->build_index_with_fillers();

		add_filter(
			'nh_seo_search_enabled',
			static function ( $enabled, $query ) {
				return 'skipme' === $query->get( 'nh_test_tag' ) ? false : $enabled;
			},
			10,
			2
		);

		$this->record_sql();
		$this->search_ids( 'zebra', array( 'nh_test_tag' => 'skipme' ) );
		$this->assertSame( array(), $this->sql_containing( 'MATCH(' ) );
		$this->search_ids( 'zebra' );
		$this->stop_sql_log();
		$this->assertNotEmpty( $this->sql_containing( 'MATCH(' ) );
	}

	public function test_queries_that_return_only_ids_use_the_index_too() {
		$post_id = $this->make_post( array( 'post_title' => 'Identified zebra' ) );
		$this->build_index_with_fillers();

		$this->record_sql();
		$query = new WP_Query(
			array(
				's'      => 'zebra',
				'fields' => 'ids',
			)
		);
		$this->stop_sql_log();
		$this->assertSame( array( $post_id ), array_map( 'intval', $query->posts ) );
		$this->assertNotEmpty( $this->sql_containing( 'MATCH(' ) );
	}

	public function test_get_posts_without_filters_keeps_native_search() {
		$post_id = $this->make_post( array( 'post_title' => 'Plain get_posts zebra' ) );
		$this->build_index_with_fillers();

		$this->record_sql();
		$found = get_posts( array( 's' => 'zebra' ) ); // suppress_filters is true by default.
		$this->stop_sql_log();

		$this->assertSame( array( $post_id ), wp_list_pluck( $found, 'ID' ) );
		$this->assertSame( array(), $this->sql_containing( 'MATCH(' ) );
	}

	public function test_rest_search_endpoint_benefits_from_the_index() {
		$title = $this->make_post(
			array(
				'post_title' => 'Zebra on the road',
				'post_date'  => '2026-01-01 10:00:00',
			)
		);
		$body  = $this->make_post(
			array(
				'post_title'   => 'Unrelated headline',
				'post_content' => 'Zebra in the body.',
				'post_date'    => '2026-03-01 10:00:00',
			)
		);
		$this->build_index_with_fillers();

		$request = new WP_REST_Request( 'GET', '/wp/v2/search' );
		$request->set_param( 'search', 'zebra' );
		$request->set_param( 'subtype', 'any' );

		$this->record_sql();
		$response = rest_do_request( $request );
		$this->stop_sql_log();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( $title, $body ), wp_list_pluck( $response->get_data(), 'id' ), 'Title match first, newer body match second.' );
		$this->assertNotEmpty( $this->sql_containing( 'MATCH(' ), 'REST search goes through the index.' );
		$this->assertSame( array(), $this->sql_containing( "LIKE '%zebra%'" ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'search', 'zebra' );
		$this->record_sql();
		$response = rest_do_request( $request );
		$this->stop_sql_log();
		$this->assertSame( 200, $response->get_status() );
		$this->assertEqualsCanonicalizing( array( $title, $body ), wp_list_pluck( $response->get_data(), 'id' ) );
		$this->assertNotEmpty( $this->sql_containing( 'MATCH(' ), 'The posts endpoint searches through the index too.' );

		// A request that lists drafts (editors) keeps native search.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'search', 'zebra' );
		$request->set_param( 'status', 'any' );
		$this->record_sql();
		rest_do_request( $request );
		$this->stop_sql_log();
		$this->assertSame( array(), $this->sql_containing( 'MATCH(' ) );
	}

	public function test_rest_search_relaxes_and_falls_back_like_any_other_query() {
		$alpaca = $this->make_post( array( 'post_title' => 'Alpaca farm' ) );
		$llama  = $this->make_post( array( 'post_title' => 'Llama trekking' ) );
		$this->build_index_with_fillers();

		$request = new WP_REST_Request( 'GET', '/wp/v2/search' );
		$request->set_param( 'search', 'alpaca llama' );
		$response = rest_do_request( $request );
		$this->assertEqualsCanonicalizing( array( $alpaca, $llama ), wp_list_pluck( $response->get_data(), 'id' ) );
	}

	public function test_hostile_search_strings_never_reach_the_sql_and_never_break_the_search() {
		global $wpdb;
		$public = $this->make_post( array( 'post_title' => 'Canary zebra' ) );
		$this->make_post( array( 'post_title' => 'Second canary' ) );
		$this->build_index_with_fillers();

		$hostile = array(
			'"',
			"'",
			'\\',
			"zebra' OR 1=1 -- ",
			'zebra") OR 1=1 -- ',
			"zebra'; DROP TABLE {$wpdb->posts}; --",
			'+-~*<>()"@',
			'zebra* -canary +second ~x >y <z (a b) "zebra canary" @3',
			'%',
			'%s %d %%',
			"zebra\0canary",
			'0x27 UNION SELECT user_pass FROM wp_users',
			'zebra /* comment */ canary',
			'canary${jndi:ldap://x}',
			'ｚｅｂｒａ',
			"\u{202E}zebra",
			'摩托车租赁',
			'zebra ' . str_repeat( 'a', 5000 ),
			str_repeat( 'zebra canary ', 20000 ),
		);

		foreach ( $hostile as $index => $search ) {
			$this->record_sql();
			$suppress = $wpdb->suppress_errors( true );
			$query    = new WP_Query( array( 's' => $search ) );
			$error    = $wpdb->last_error;
			$wpdb->suppress_errors( $suppress );
			$sql = $this->stop_sql_log();

			$this->assertSame( '', $error, 'No database error for case ' . $index );
			$this->assertNull( NH_SEO_Search_State::breaker(), 'The index was not paused by case ' . $index );

			foreach ( $sql as $statement ) {
				if ( false === strpos( $statement, 'MATCH(' ) ) {
					continue;
				}
				// Every full-text expression holds folded tokens only.
				preg_match_all( "/AGAINST \\('([^']*)' IN BOOLEAN MODE\\)/", $statement, $matches );
				$this->assertNotEmpty( $matches[1], 'Case ' . $index );
				foreach ( $matches[1] as $expression ) {
					$this->assertMatchesRegularExpression( '/^(?:\+?[a-z0-9]{3,80}\*)(?: \+?[a-z0-9]{3,80}\*)*$/', $expression, 'Case ' . $index . ': ' . $expression );
				}
				$without_expressions = preg_replace( "/AGAINST \\('[^']*' IN BOOLEAN MODE\\)/", 'AGAINST (?)', $statement );
				$this->assertStringNotContainsString( 'OR 1=1', $without_expressions );
				$this->assertStringNotContainsString( 'DROP TABLE', $without_expressions );
				$this->assertStringNotContainsString( 'UNION', $without_expressions );
			}
			$used_index = ! empty(
				array_filter(
					$sql,
					static function ( $statement ) {
						return false !== strpos( $statement, 'MATCH(' );
					}
				)
			);
			if ( $used_index ) {
				$this->assertLessThanOrEqual( 2, count( $query->posts ), 'Case ' . $index . ' cannot return more than the two public posts.' );
			}
		}

		$this->assertSame( array( $public ), $this->search_ids( 'zebra' ), 'The canary data is intact and still searchable.' );
		$this->assertTrue( NH_SEO_Search_Index::table_exists() );
		$this->assertGreaterThan( 0, (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts}" ) );
	}

	public function test_a_very_long_search_is_fast_and_bounded() {
		$this->make_post( array( 'post_title' => 'Canary zebra' ) );
		$this->build_index_with_fillers();

		$this->record_sql();
		$start = microtime( true );
		$this->search_ids( str_repeat( 'zebra canary lorem ipsum dolor ', 100000 ) ); // 3,100,000 characters.
		$elapsed = microtime( true ) - $start;
		$sql     = $this->stop_sql_log();

		$this->assertLessThan( 2, $elapsed );
		foreach ( $sql as $statement ) {
			$this->assertLessThan( 5000, strlen( $statement ), 'The SQL stays small whatever the input size.' );
		}
	}

	public function test_hostile_input_in_the_main_query_and_the_search_form_is_safe() {
		global $wpdb;
		$this->make_post( array( 'post_title' => 'Canary zebra' ) );
		$this->build_index_with_fillers();

		foreach ( array( "zebra') OR 1=1 --", '+-~*<>()"@', '"zebra canary"', 'zebra -canary' ) as $search ) {
			$suppress = $wpdb->suppress_errors( true );
			$this->main_search( $search );
			$error = $wpdb->last_error;
			$wpdb->suppress_errors( $suppress );
			$this->assertSame( '', $error, $search );
			$this->assertTrue( is_search(), $search );
		}
	}

	public function test_results_are_never_cached_in_a_way_that_survives_an_index_change() {
		$post_id = $this->make_post( array( 'post_title' => 'Cached zebra' ) );
		$this->build_index_with_fillers();

		$this->assertSame( array( $post_id ), $this->search_ids( 'zebra' ) );

		// A term rename changes what the index finds without saving a post: the cron batch bumps the query caches.
		$term = wp_insert_term( 'Wombat', 'category' );
		wp_set_object_terms( $post_id, array( (int) $term['term_id'] ), 'category' );
		$this->flush_index();
		$this->assertSame( array( $post_id ), $this->search_ids( 'wombat' ) );

		wp_update_term( (int) $term['term_id'], 'category', array( 'name' => 'Numbat' ) );
		NH_SEO_Search_Indexer::cron_reindex_term( 'category', (int) $term['term_id'], 0 );
		$this->assertSame( array( $post_id ), $this->search_ids( 'numbat' ) );
		$this->assertSame( array(), $this->search_ids( 'wombat' ) );
	}
}
