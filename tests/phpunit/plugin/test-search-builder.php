<?php
/**
 * Search index builds: batches, readiness, idempotency, superseding, partial passes, state and status line.
 *
 * Runs in autocommit mode against InnoDB FULLTEXT (see NH_Search_Test_Case for why).
 *
 * @package nguyen-ha-seo-companion
 */

// Autocommit base class.
require_once __DIR__ . '/class-nh-search-test-case.php';

/**
 * Builder tests.
 */
class NH_Test_Search_Builder extends NH_Search_Test_Case {

	/**
	 * Create published posts and return their IDs (ascending).
	 *
	 * @param int $count Number of posts.
	 * @return int[]
	 */
	private function posts( $count ) {
		$ids = array();
		for ( $i = 1; $i <= $count; $i++ ) {
			$ids[] = $this->make_post( array( 'post_title' => 'Builder post ' . $i ) );
		}
		NH_SEO_Search_Indexer::reset(); // The build, not the hooks, must index them.
		return $ids;
	}

	public function test_batch_size_is_clamped_and_filterable() {
		$this->assertSame( 200, NH_SEO_Search_Builder::batch_size() );
		$this->assertSame( 200, NH_SEO_Search_Builder::batch_size( 0 ) );
		$this->assertSame( 10, NH_SEO_Search_Builder::batch_size( 1 ) );
		$this->assertSame( 500, NH_SEO_Search_Builder::batch_size( 99999 ) );
		$this->assertSame( 50, NH_SEO_Search_Builder::batch_size( 50 ) );

		add_filter(
			'nh_seo_search_batch_size',
			static function () {
				return 77;
			}
		);
		$this->assertSame( 77, NH_SEO_Search_Builder::batch_size() );
	}

	public function test_start_empties_the_table_counts_the_posts_and_issues_a_build_token() {
		$ids = $this->posts( 3 );
		$this->make_post( array( 'post_status' => 'draft' ) );
		NH_SEO_Search_Indexer::reset();
		$this->flush_index();
		$this->assertSame( 0, NH_SEO_Search_Index::count() );
		NH_SEO_Search_Index::upsert(
			99999,
			array(
				'post_type'  => 'post',
				'title_fold' => 'stale row',
				'tax_fold'   => '',
				'body_fold'  => '',
			)
		);

		$state = NH_SEO_Search_Builder::start();

		$this->assertNotWPError( $state );
		$this->assertSame( 0, NH_SEO_Search_Index::count(), 'A rebuild starts from an empty table.' );
		$this->assertSame( count( $ids ), $state['total'] );
		$this->assertTrue( $state['building'] );
		$this->assertFalse( $state['ready'] );
		$this->assertSame( 12, strlen( $state['build'] ) );
		$this->assertGreaterThan( 0, $state['last_run'] );
	}

	public function test_ready_is_set_only_after_a_complete_build() {
		$ids   = $this->posts( 25 );
		$state = NH_SEO_Search_Builder::start();

		$first = NH_SEO_Search_Builder::run_batch( 0, 10, $state['build'] );
		$this->assertSame( 10, $first['processed'] );
		$this->assertFalse( $first['done'] );
		$this->assertFalse( $first['ready'] );
		$this->assertSame( $ids[9], $first['cursor'] );
		$this->assertSame( 10, $first['indexed'] );
		$this->assertSame( 25, $first['total'] );
		$this->assertSame( 40, $first['percent'] );
		$this->assertFalse( NH_SEO_Search_State::get()['ready'], 'A partial index is never "ready".' );
		$this->assertTrue( NH_SEO_Search_State::get()['building'] );

		$second = NH_SEO_Search_Builder::run_batch( $first['cursor'], 10, $state['build'] );
		$this->assertFalse( $second['done'] );
		$this->assertFalse( NH_SEO_Search_State::get()['ready'] );
		$this->assertSame( 20, $second['indexed'] );

		$third = NH_SEO_Search_Builder::run_batch( $second['cursor'], 10, $state['build'] );
		$this->assertSame( 5, $third['processed'] );
		$this->assertTrue( $third['done'] );
		$this->assertTrue( $third['ready'] );
		$this->assertSame( 100, $third['percent'] );
		$this->assertSame( $ids, NH_SEO_Search_Index::ids() );

		$final = NH_SEO_Search_State::get();
		$this->assertTrue( $final['ready'] );
		$this->assertFalse( $final['building'] );
		$this->assertSame( '', $final['build'] );
		$this->assertSame( 25, $final['indexed'] );
		$this->assertSame( '', $final['last_error'] );
	}

	public function test_batches_are_idempotent_so_a_retried_request_changes_nothing() {
		$ids   = $this->posts( 25 );
		$state = NH_SEO_Search_Builder::start();

		$a = NH_SEO_Search_Builder::run_batch( 0, 10, $state['build'] );
		$b = NH_SEO_Search_Builder::run_batch( 0, 10, $state['build'] );
		$this->assertSame( $a, $b, 'The same request twice gives the same answer.' );
		$this->assertSame( array_slice( $ids, 0, 10 ), NH_SEO_Search_Index::ids() );

		$before = NH_SEO_Search_Index::get( $ids[3] );
		NH_SEO_Search_Builder::run_batch( 0, 10, $state['build'] );
		$after = NH_SEO_Search_Index::get( $ids[3] );
		unset( $before['updated'], $after['updated'] );
		$this->assertSame( $before, $after );

		// Walk the rest, repeating every batch.
		$cursor = $a['cursor'];
		do {
			$one    = NH_SEO_Search_Builder::run_batch( $cursor, 10, $state['build'] );
			$repeat = NH_SEO_Search_Builder::run_batch( $cursor, 10, $state['build'] );
			$this->assertSame( $one, $repeat );
			$cursor = $one['cursor'];
		} while ( ! $one['done'] );

		$this->assertSame( $ids, NH_SEO_Search_Index::ids(), 'Exactly one row per post, no duplicates.' );
		$this->assertTrue( NH_SEO_Search_State::get()['ready'] );
	}

	public function test_a_batch_can_resume_from_any_cursor() {
		$ids   = $this->posts( 25 );
		$state = NH_SEO_Search_Builder::start();

		$late = NH_SEO_Search_Builder::run_batch( $ids[14], 10, $state['build'] );
		$this->assertSame( array_slice( $ids, 15, 10 ), NH_SEO_Search_Index::ids() );
		$this->assertSame( $ids[24], $late['cursor'] );
		$this->assertTrue( $late['done'], 'It walked to the end, so the build is complete.' );
	}

	public function test_a_second_build_supersedes_the_first() {
		$this->posts( 15 );
		$first = NH_SEO_Search_Builder::start();
		NH_SEO_Search_Builder::run_batch( 0, 10, $first['build'] );

		$second = NH_SEO_Search_Builder::start();
		$this->assertNotSame( $first['build'], $second['build'] );
		$this->assertSame( 0, NH_SEO_Search_Index::count() );

		$stale = NH_SEO_Search_Builder::run_batch( 0, 10, $first['build'] );
		$this->assertWPError( $stale );
		$this->assertSame( 'nh_seo_search_superseded', $stale->get_error_code() );
		$this->assertWPError( NH_SEO_Search_Builder::run_batch( 0, 10, '' ), 'No token while a build is running: refused.' );
		$this->assertSame( 0, NH_SEO_Search_Index::count(), 'The superseded build wrote nothing.' );

		$this->assertNotWPError( NH_SEO_Search_Builder::run_batch( 0, 10, $second['build'] ) );
		$this->assertSame( 10, NH_SEO_Search_Index::count() );
	}

	public function test_rebuild_walks_every_post_reports_progress_and_sets_ready() {
		$ids      = $this->posts( 25 );
		$progress = array();
		$result   = NH_SEO_Search_Builder::rebuild(
			10,
			static function ( $batch ) use ( &$progress ) {
				$progress[] = $batch['indexed'];
			}
		);

		$this->assertNotWPError( $result );
		$this->assertTrue( $result['done'] );
		$this->assertSame( array( 10, 20, 25 ), $progress );
		$this->assertSame( $ids, NH_SEO_Search_Index::ids() );
		$this->assertTrue( NH_SEO_Search_State::get()['ready'] );
	}

	public function test_rebuild_of_an_empty_site_is_ready_with_an_empty_index() {
		$result = NH_SEO_Search_Builder::rebuild();
		$this->assertNotWPError( $result );
		$this->assertTrue( $result['done'] );
		$this->assertSame( 0, NH_SEO_Search_Index::count() );
		$this->assertTrue( NH_SEO_Search_State::get()['ready'] );
	}

	public function test_posts_changed_during_a_build_are_handled_by_the_hooks() {
		$ids   = $this->posts( 25 );
		$state = NH_SEO_Search_Builder::start();
		NH_SEO_Search_Builder::run_batch( 0, 10, $state['build'] );

		// An already indexed post is trashed, another is edited, a new one appears while the build runs.
		wp_trash_post( $ids[2] );
		wp_update_post(
			array(
				'ID'         => $ids[4],
				'post_title' => 'Edited during the build',
			)
		);
		$new = $this->make_post( array( 'post_title' => 'Brand new during the build' ) );
		$this->flush_index();

		$cursor = $ids[9];
		do {
			$batch  = NH_SEO_Search_Builder::run_batch( $cursor, 10, $state['build'] );
			$cursor = $batch['cursor'];
		} while ( ! $batch['done'] );

		$expected = array_values( array_diff( array_merge( $ids, array( $new ) ), array( $ids[2] ) ) );
		$this->assertSame( $expected, NH_SEO_Search_Index::ids() );
		$this->assertSame( 'edited during the build', NH_SEO_Search_Index::get( $ids[4] )['title_fold'] );
	}

	public function test_a_batch_that_runs_out_of_time_hands_control_back_without_losing_progress() {
		$ids   = $this->posts( 25 );
		$state = NH_SEO_Search_Builder::start();
		add_filter(
			'nh_seo_search_time_budget',
			static function () {
				return -1;
			}
		);

		$batch = NH_SEO_Search_Builder::run_batch( 0, 10, $state['build'] );
		$this->assertSame( 1, $batch['processed'], 'Out of budget: one post, so that every batch makes progress.' );
		$this->assertFalse( $batch['done'] );
		$this->assertSame( $ids[0], $batch['cursor'] );

		// The caller simply keeps going from the cursor.
		$guard = 0;
		while ( ! $batch['done'] && ++$guard < 100 ) {
			$batch = NH_SEO_Search_Builder::run_batch( $batch['cursor'], 10, $state['build'] );
		}
		$this->assertTrue( $batch['done'] );
		$this->assertSame( $ids, NH_SEO_Search_Index::ids() );
	}

	public function test_a_partial_pass_by_post_type_never_changes_ready_and_prunes_leftovers() {
		global $wpdb;
		$posts = $this->posts( 4 );
		$page  = $this->make_post(
			array(
				'post_type'  => 'page',
				'post_title' => 'Only page',
			)
		);
		NH_SEO_Search_Indexer::reset();
		$this->index_all();
		$this->assertTrue( NH_SEO_Search_State::get()['ready'] );
		$this->assertCount( 5, NH_SEO_Search_Index::ids() );

		// A post disappears behind the plugin's back.
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'draft' ), array( 'ID' => $posts[1] ) );
		clean_post_cache( $posts[1] );
		NH_SEO_Search_Index::upsert(
			$page,
			array(
				'post_type'  => 'page',
				'title_fold' => 'tampered',
				'tax_fold'   => '',
				'body_fold'  => '',
			)
		);

		$result = NH_SEO_Search_Builder::reindex_types( array( 'page' ), 10 );

		$this->assertNotWPError( $result );
		$this->assertSame( 'only page', NH_SEO_Search_Index::get( $page )['title_fold'], 'The pages were re-indexed.' );
		$this->assertNull( NH_SEO_Search_Index::get( $posts[1] ), 'Leftovers are pruned at the end.' );
		$this->assertSame( array( $posts[0], $posts[2], $posts[3], $page ), NH_SEO_Search_Index::ids() );
		$this->assertTrue( NH_SEO_Search_State::get()['ready'], 'A partial pass does not change readiness.' );
	}

	public function test_a_partial_pass_cannot_make_an_unbuilt_index_ready() {
		$this->posts( 3 );
		$result = NH_SEO_Search_Builder::reindex_types( array( 'post' ), 10 );
		$this->assertNotWPError( $result );
		$this->assertTrue( $result['done'] );
		$this->assertFalse( NH_SEO_Search_State::get()['ready'] );
		$this->assertSame( 3, NH_SEO_Search_Index::count() );
	}

	public function test_start_lifts_a_pause_and_clear_resets_everything() {
		$this->posts( 3 );
		NH_SEO_Search_State::trip( 'boom' );
		$this->assertNotNull( NH_SEO_Search_State::breaker() );

		NH_SEO_Search_Builder::rebuild();
		$this->assertNull( NH_SEO_Search_State::breaker(), 'A rebuild lifts the pause.' );
		$this->assertSame( 3, NH_SEO_Search_Index::count() );

		NH_SEO_Search_Builder::clear();
		$this->assertSame( 0, NH_SEO_Search_Index::count() );
		$this->assertSame( NH_SEO_Search_State::defaults(), NH_SEO_Search_State::get() );
	}

	public function test_start_reports_an_error_when_the_table_cannot_be_created() {
		global $wpdb;
		NH_SEO_Search_Index::drop_table();
		$break = static function ( $query ) {
			return 0 === strpos( ltrim( $query ), 'CREATE TABLE' ) ? 'CREATE TABLE this is not valid sql' : $query;
		};
		add_filter( 'query', $break );
		$suppress = $wpdb->suppress_errors( true );

		$result = NH_SEO_Search_Builder::start();
		$batch  = NH_SEO_Search_Builder::run_batch( 0, 10, '' );

		$wpdb->suppress_errors( $suppress );
		remove_filter( 'query', $break );
		$this->assertWPError( $result );
		$this->assertSame( 'nh_seo_search_table', $result->get_error_code() );
		$this->assertStringContainsString( 'MySQL 5.6', NH_SEO_Search_State::get()['last_error'] );
		$this->assertWPError( $batch, 'A batch refuses to run without a table.' );
		$this->assertFalse( NH_SEO_Search_State::get()['ready'] );
	}

	public function test_state_is_typed_complete_and_survives_garbage() {
		update_option( NH_SEO_Keys::OPTION_SEARCH_STATE, 'not an array' );
		$this->assertSame( NH_SEO_Search_State::defaults(), NH_SEO_Search_State::get() );

		update_option(
			NH_SEO_Keys::OPTION_SEARCH_STATE,
			array(
				'ready'      => '1',
				'total'      => '12',
				'indexed'    => -5,
				'last_run'   => 'x',
				'last_error' => array( 'x' ),
				'unknown'    => 'dropped',
			)
		);
		$state = NH_SEO_Search_State::get();
		$this->assertTrue( $state['ready'] );
		$this->assertSame( 12, $state['total'] );
		$this->assertSame( 5, $state['indexed'] );
		$this->assertSame( 0, $state['last_run'] );
		$this->assertSame( '', $state['last_error'] );
		$this->assertArrayNotHasKey( 'unknown', $state );
		$this->assertSame( array_keys( NH_SEO_Search_State::defaults() ), array_keys( $state ) );

		NH_SEO_Search_State::save( array( 'total' => 3 ) );
		$this->assertArrayNotHasKey( NH_SEO_Keys::OPTION_SEARCH_STATE, wp_load_alloptions( true ), 'The state is not autoloaded on every page view.' );
	}

	public function test_status_line_describes_every_state_in_plain_text() {
		$line = NH_SEO_Search_State::status_line();
		$this->assertStringContainsString( 'Not built yet', $line );

		$this->posts( 25 );
		$state = NH_SEO_Search_Builder::start();
		NH_SEO_Search_Builder::run_batch( 0, 10, $state['build'] );
		$this->assertStringContainsString( 'Building: 10 of 25 posts indexed (40%)', NH_SEO_Search_State::status_line() );

		NH_SEO_Search_Builder::rebuild();
		$line = NH_SEO_Search_State::status_line();
		$this->assertStringContainsString( 'Ready: 25 posts indexed', $line );
		$this->assertSame( $line, apply_filters( 'nh_seo_search_status', '' ), 'The Status box filter returns the same line.' );

		NH_SEO_Search_State::trip( 'Boom <b>bold</b>' );
		$this->assertStringContainsString( 'paused', NH_SEO_Search_State::status_line() );
		$this->assertStringContainsString( 'Boom bold', NH_SEO_Search_State::status_line() );
		NH_SEO_Search_State::clear_breaker();

		$this->set_search_mode( 'native' );
		$this->assertStringContainsString( 'Native WordPress search', NH_SEO_Search_State::status_line() );

		$this->set_search_mode( 'index' );
		NH_SEO_Search_State::save( array( 'ready' => false ) );
		$this->assertStringContainsString( 'Index mode', NH_SEO_Search_State::status_line() );

		NH_SEO_Search_Index::drop_table();
		$this->assertStringContainsString( 'does not exist', NH_SEO_Search_State::status_line() );

		$this->assertSame( wp_strip_all_tags( NH_SEO_Search_State::status_line() ), NH_SEO_Search_State::status_line(), 'Plain text, no markup.' );
	}

	public function test_the_pause_is_remembered_for_ten_minutes_and_the_reason_is_stored() {
		NH_SEO_Search_State::trip( str_repeat( 'x', 1000 ) );
		$breaker = NH_SEO_Search_State::breaker();
		$this->assertNotNull( $breaker );
		$this->assertEqualsWithDelta( time() + 600, $breaker['until'], 5 );
		$this->assertLessThanOrEqual( 300, strlen( $breaker['error'] ) );
		$this->assertSame( $breaker['error'], NH_SEO_Search_State::get()['last_error'] );

		NH_SEO_Search_State::clear_breaker();
		$this->assertNull( NH_SEO_Search_State::breaker() );
	}
}
