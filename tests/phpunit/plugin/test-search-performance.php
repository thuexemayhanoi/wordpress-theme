<?php
/**
 * Search index at scale: a corpus of at least 2,000 posts is indexed in bounded batches and searched with a
 * single SQL query in bounded time.
 *
 * The corpus is deterministic (seeded random text, Vietnamese words with diacritics among them). Set the
 * environment variable NH_SEARCH_PERF_POSTS (for example 30000) to run the same checks on a bigger site;
 * the default is 2,400 posts. Time limits are deliberately loose so that slow CI machines do not fail them;
 * the measured numbers are printed to the console by the first test.
 *
 * Runs in autocommit mode against InnoDB FULLTEXT (see NH_Search_Test_Case for why), on MariaDB only.
 *
 * @package nguyen-ha-seo-companion
 */

// Autocommit base class.
require_once __DIR__ . '/class-nh-search-test-case.php';

/**
 * Large corpus tests.
 */
class NH_Test_Search_Performance extends NH_Search_Test_Case {

	/**
	 * Words the generated posts are made of: 300 filler words plus a few diacritic and stop-word spellings.
	 *
	 * @return string[]
	 */
	private function vocabulary() {
		$base = array( 'thuê', 'xe', 'máy', 'hà', 'nội', 'đà', 'nẵng', 'giá', 'rẻ', 'tự', 'động', 'số', 'khách', 'du', 'lịch', 'bằng', 'lái', 'hộ', 'chiếu', 'đặt', 'cọc', 'bảo', 'hiểm', 'mũ', 'bảo', 'hiểm', 'the', 'for', 'with', 'about', 'cơm', 'thẻ', 'sim' );
		for ( $i = 0; $i < 300; $i++ ) {
			$base[] = 'word' . dechex( $i * 7919 % 4096 ) . 'x';
		}
		return $base;
	}

	/**
	 * Create the corpus once per test class run and index it. Returns timing figures.
	 *
	 * @param int $posts Number of posts.
	 * @return array<string, mixed>
	 */
	private function build_corpus( $posts ) {
		global $wpdb;
		mt_srand( 20261017 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_seeding_mt_srand -- Deterministic corpus for a test.
		$vocabulary = $this->vocabulary();
		$size       = count( $vocabulary );

		$text = static function ( $words ) use ( $vocabulary, $size ) {
			$out = array();
			for ( $i = 0; $i < $words; $i++ ) {
				$out[] = $vocabulary[ mt_rand( 0, $size - 1 ) ]; // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand -- Deterministic corpus for a test.
			}
			return implode( ' ', $out );
		};

		$needle_in_title = array( 7, 1203, $posts - 1 );
		$needle_in_body  = array( 500, 1500 );

		$started = microtime( true );
		$ids     = $this->bulk_posts(
			$posts,
			static function ( $i ) use ( $text, $needle_in_title, $needle_in_body ) {
				$title   = ucfirst( $text( mt_rand( 4, 8 ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
				$content = '<h2>' . $text( 3 ) . '</h2><p>' . $text( mt_rand( 150, 250 ) ) . '</p>'; // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
				if ( in_array( $i, $needle_in_title, true ) ) {
					$title .= ' quokkaneedle';
				}
				if ( in_array( $i, $needle_in_body, true ) ) {
					$content .= '<p>The quokkaneedle appears only here.</p>';
				}
				return array(
					'title'   => $title,
					'content' => $content,
				);
			}
		);
		$created = microtime( true ) - $started;

		// Categories by direct SQL: one relationship per post, round robin.
		$terms = array();
		foreach ( array( 'Thuê xe máy', 'Du lịch', 'Bảo hiểm', 'Giấy tờ', 'Mẹo hay', 'Tin tức' ) as $name ) {
			$term    = wp_insert_term( $name, 'category' );
			$terms[] = (int) get_term( $term['term_id'] )->term_taxonomy_id;
		}
		foreach ( array_chunk( $ids, 500, true ) as $chunk ) {
			$rows = array();
			foreach ( $chunk as $position => $id ) {
				$rows[] = $wpdb->prepare( '(%d, %d)', $id, $terms[ $position % count( $terms ) ] );
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Values prepared above.
			$wpdb->query( "INSERT INTO {$wpdb->term_relationships} (object_id, term_taxonomy_id) VALUES " . implode( ', ', $rows ) );
		}
		// A tag only on one post, to find through the taxonomy column.
		wp_set_object_terms( $ids[ $posts - 2 ], array( 'quokkaneedle' ), 'post_tag' );
		NH_SEO_Search_Indexer::reset();

		$started = microtime( true );
		$batches = 0;
		$result  = NH_SEO_Search_Builder::rebuild(
			200,
			static function () use ( &$batches ) {
				++$batches;
			}
		);
		$this->assertNotWPError( $result );

		return array(
			'ids'     => $ids,
			'created' => $created,
			'build'   => microtime( true ) - $started,
			'batches' => $batches,
		);
	}

	/**
	 * Number of posts for this run.
	 *
	 * @return int
	 */
	private function corpus_size() {
		$env = getenv( 'NH_SEARCH_PERF_POSTS' );
		return $env && (int) $env >= 2000 ? (int) $env : 2400;
	}

	public function test_a_large_corpus_is_indexed_in_batches_and_searched_with_one_query() {
		global $wpdb;
		$posts = $this->corpus_size();
		$this->assertGreaterThanOrEqual( 2000, $posts );

		$corpus = $this->build_corpus( $posts );
		$ids    = $corpus['ids'];

		fwrite( STDERR, sprintf( "\n[search perf] %d posts: created in %.1fs, indexed in %.1fs (%d batches of 200, %.1f ms/post)\n", $posts, $corpus['created'], $corpus['build'], $corpus['batches'], 1000 * $corpus['build'] / $posts ) );

		// Every post is indexed by batches of at most 200, and the index is complete.
		$this->assertSame( (int) ceil( $posts / 200 ), $corpus['batches'] );
		$this->assertSame( $posts, NH_SEO_Search_Index::count() );
		$state = NH_SEO_Search_State::get();
		$this->assertTrue( $state['ready'] );
		$this->assertSame( $posts, $state['indexed'] );
		$this->assertSame( $posts, $state['total'] );
		$this->assertLessThan( 240, $corpus['build'], 'Indexing a few thousand posts stays far from any PHP time limit per request (200 posts per request).' );

		// Row sizes respect the caps.
		$longest = (int) $wpdb->get_var( 'SELECT MAX(CHAR_LENGTH(body_fold)) FROM ' . NH_SEO_Search_Index::table() );
		$this->assertLessThan( NH_SEO_Search_Text::DEFAULT_BODY_CAP * 81, $longest );
		$words = (int) $wpdb->get_var( 'SELECT MAX(LENGTH(body_fold) - LENGTH(REPLACE(body_fold, \' \', \'\')) + 1) FROM ' . NH_SEO_Search_Index::table() );
		$this->assertLessThanOrEqual( NH_SEO_Search_Text::DEFAULT_BODY_CAP, $words );

		// A rare word: title matches first, then the taxonomy match, then body-only matches; one results query.
		$this->record_sql();
		$start   = microtime( true );
		$query   = new WP_Query( array( 's' => 'quokkaneedle' ) );
		$elapsed = microtime( true ) - $start;
		$sql     = $this->stop_sql_log();

		$found = array_map( 'intval', wp_list_pluck( $query->posts, 'ID' ) );
		$this->assertCount( 6, $found );
		$this->assertEqualsCanonicalizing( array( $ids[7], $ids[1203], $ids[ $posts - 1 ] ), array_slice( $found, 0, 3 ), 'The three title matches come first.' );
		$this->assertSame( $ids[ $posts - 2 ], $found[3], 'Then the post with the tag.' );
		$this->assertEqualsCanonicalizing( array( $ids[500], $ids[1500] ), array_slice( $found, 4 ), 'Body-only matches last.' );
		$this->assertSame( 6, $query->found_posts );

		$match_queries = array_values(
			array_filter(
				$sql,
				static function ( $statement ) {
					return false !== strpos( $statement, 'MATCH(' );
				}
			)
		);
		$this->assertCount( 1, $match_queries, 'The results come from a single SQL query.' );
		$this->assertStringContainsString( 'INNER JOIN', $match_queries[0] );
		$this->assertLessThan( 10, count( $sql ), 'The whole search, including loading the posts, is a handful of statements.' );
		$this->assertLessThan( 1.0, $elapsed, 'One indexed search over ' . $posts . ' posts.' );
		fwrite( STDERR, sprintf( "[search perf] rare word: %.1f ms, %d SQL statements\n", 1000 * $elapsed, count( $sql ) ) );

		// Nothing runs per result: the index table is touched by exactly one statement, whatever the result count.
		foreach ( array( 'quokkaneedle', 'word1x' ) as $search ) {
			$this->record_sql();
			$query = new WP_Query(
				array(
					's'             => $search,
					'cache_results' => false,
				)
			);
			$log   = $this->stop_sql_log();
			$index = array_filter(
				$log,
				static function ( $statement ) {
					return false !== strpos( $statement, NH_SEO_Search_Index::table() );
				}
			);
			$this->assertCount( 1, $index, $search . ' (' . count( $query->posts ) . ' results on this page)' );
			$singles = array_filter(
				$log,
				static function ( $statement ) {
					return 1 === preg_match( '/WHERE\s+ID\s*=\s*\d+/i', $statement );
				}
			);
			$this->assertCount( 0, $singles, 'No single-post lookups.' );
		}
	}

	public function test_searches_on_a_large_corpus_stay_fast_and_correct() {
		$posts  = $this->corpus_size();
		$corpus = $this->build_corpus( $posts );
		$ids    = $corpus['ids'];

		$timings = array();
		$timed   = function ( $label, callable $callback ) use ( &$timings ) {
			$this->record_sql();
			$start             = microtime( true );
			$result            = $callback();
			$timings[ $label ] = microtime( true ) - $start;
			$sql               = $this->stop_sql_log();
			return array( $result, $sql );
		};

		// A very common word (in nearly every post), paged: each page is one query, and deep pages are fine.
		list( $first, $sql ) = $timed(
			'common word page 1',
			function () {
				return new WP_Query( array( 's' => 'word1x' ) );
			}
		);
		$this->assertGreaterThan( 0, $first->found_posts );
		$this->assertLessThanOrEqual( 10, count( $first->posts ) );

		list( $deep, $sql ) = $timed(
			'common word deep page',
			function () {
				return new WP_Query(
					array(
						's'     => 'thuê máy',
						'paged' => 20,
					)
				);
			}
		);
		$this->assertGreaterThan( 190, $deep->found_posts );
		$this->assertCount( 10, $deep->posts );
		$this->assertCount(
			1,
			array_filter(
				$sql,
				static function ( $statement ) {
					return false !== strpos( $statement, 'MATCH(' );
				}
			),
			'A deep page is still one query.'
		);

		// Accents do not matter, and the diacritic spelling finds the same posts.
		list( $accents ) = $timed(
			'with accents',
			function () {
				return new WP_Query( array( 's' => 'thuê xe máy hà nội' ) );
			}
		);
		list( $plain )   = $timed(
			'without accents',
			function () {
				return new WP_Query( array( 's' => 'thue xe may ha noi' ) );
			}
		);
		$this->assertSame( wp_list_pluck( $accents->posts, 'ID' ), wp_list_pluck( $plain->posts, 'ID' ) );
		$this->assertSame( $accents->found_posts, $plain->found_posts );

		// Relaxed: two words that never occur together in this corpus.
		list( $relaxed ) = $timed(
			'relaxed',
			function () {
				return new WP_Query( array( 's' => 'quokkaneedle bảo hiểm cọc xxxxzzzz' ) );
			}
		);
		$this->assertGreaterThan( 0, $relaxed->found_posts );

		// Native fallback (nothing usable for the index).
		list( $native ) = $timed(
			'native fallback',
			function () {
				return new WP_Query( array( 's' => 'xe so' ) );
			}
		);
		$this->assertGreaterThan( 0, $native->found_posts );

		// Nothing found anywhere.
		list( $none ) = $timed(
			'no result',
			function () {
				return new WP_Query( array( 's' => 'qwertyzxcvb' ) );
			}
		);
		$this->assertSame( 0, $none->found_posts );

		// A main query as a visitor sees it.
		$timed(
			'main query',
			function () {
				$this->go_to( home_url( '/?s=' . rawurlencode( 'bảo hiểm' ) ) );
			}
		);
		$this->assertGreaterThan( 0, $GLOBALS['wp_query']->found_posts );

		foreach ( $timings as $label => $seconds ) {
			$this->assertLessThan( 2.0, $seconds, $label . ' took ' . round( $seconds, 3 ) . 's over ' . $posts . ' posts.' );
		}
		fwrite(
			STDERR,
			"\n[search perf] " . implode(
				', ',
				array_map(
					static function ( $label, $seconds ) {
						return $label . ' ' . round( 1000 * $seconds ) . ' ms';
					},
					array_keys( $timings ),
					$timings
				)
			) . "\n"
		);

		// The index stays correct under change at this size.
		$changed = $ids[1000];
		wp_update_post(
			array(
				'ID'         => $changed,
				'post_title' => 'Brand new unique headline zorgblat',
			)
		);
		wp_trash_post( $ids[1001] );
		$this->flush_index();
		$this->assertSame( array( $changed ), array_map( 'intval', wp_list_pluck( ( new WP_Query( array( 's' => 'zorgblat' ) ) )->posts, 'ID' ) ) );
		$this->assertNull( NH_SEO_Search_Index::get( $ids[1001] ) );
		$this->assertSame( $posts - 1, NH_SEO_Search_Index::count() );
	}

	public function test_the_same_batch_walk_is_repeatable_on_a_large_corpus() {
		$posts  = $this->corpus_size();
		$corpus = $this->build_corpus( $posts );

		// Rebuilding again gives exactly the same rows.
		global $wpdb;
		$checksum = static function () use ( $wpdb ) {
			return $wpdb->get_var( 'SELECT BIT_XOR(CRC32(CONCAT(post_id, title_fold, tax_fold, body_fold))) FROM ' . NH_SEO_Search_Index::table() );
		};
		$before   = $checksum();
		$this->index_all( 500 );
		$this->assertSame( $before, $checksum(), 'A rebuild is idempotent, whatever the batch size.' );
		$this->assertSame( $posts, NH_SEO_Search_Index::count() );
		$this->assertNotEmpty( $corpus['ids'] );
	}
}
