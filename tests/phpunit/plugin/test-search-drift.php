<?php
/**
 * An index that is "Ready" but no longer matches the content.
 *
 * The index is kept in step by hooks. While the plugin is inactive (or when an import bypasses WordPress, or
 * WP-Cron never runs) nothing does that, and the status used to go on saying "Ready". These tests describe what
 * the plugin does instead: it repairs a small gap at activation, sets the index aside when the gap is large,
 * and always says how far the index is from the content.
 *
 * Runs in autocommit mode against InnoDB FULLTEXT (see NH_Search_Test_Case for why).
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

// Autocommit base class.
require_once __DIR__ . '/class-nh-search-test-case.php';

/**
 * Gaps between the index and the content.
 */
class NH_Test_Search_Drift extends NH_Search_Test_Case {

	/**
	 * Five indexed posts and a complete, ready index.
	 *
	 * @return int[] Post IDs.
	 */
	private function ready_index() {
		$ids = array();
		for ( $i = 1; $i <= 5; $i++ ) {
			$ids[] = $this->make_post( array( 'post_title' => 'Known article ' . $i ) );
		}
		$this->index_all();
		$this->assertTrue( NH_SEO_Search_State::get()['ready'] );
		NH_SEO_Search_Indexer::reset();
		return $ids;
	}

	/**
	 * A published post without a row: what a post published while the plugin was inactive looks like.
	 *
	 * @param string $title Title.
	 * @return int
	 */
	private function post_without_row( $title ) {
		$post_id = $this->make_post( array( 'post_title' => $title ) );
		NH_SEO_Search_Indexer::reset(); // The shutdown flush of that save never happens.
		NH_SEO_Search_Index::delete_post( $post_id );
		return $post_id;
	}

	/**
	 * Edit a post behind the index's back: new title and modification time, while its row stays as it was an hour ago.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $title   New title.
	 * @return void
	 */
	private function edit_without_reindexing( $post_id, $title ) {
		global $wpdb;
		$wpdb->update(
			$wpdb->posts,
			array(
				'post_title'        => $title,
				'post_modified_gmt' => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'ID' => $post_id )
		);
		$wpdb->update(
			NH_SEO_Search_Index::table(),
			array( 'updated' => gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ) ),
			array( 'post_id' => $post_id )
		);
		clean_post_cache( $post_id );
	}

	/**
	 * The facts of `wp nh-seo index-status`.
	 *
	 * @return array<string, string>
	 */
	private function facts() {
		return wp_list_pluck( NH_SEO_CLI_Command::status_rows(), 'value', 'key' );
	}

	public function test_a_small_gap_is_repaired_when_the_plugin_is_activated() {
		$known  = $this->ready_index();
		$late   = $this->post_without_row( 'Written while the plugin was off' );
		$edited = $known[0];
		$this->edit_without_reindexing( $edited, 'Rewritten while the plugin was off' );
		// Unpublished while the plugin was off: its row is left behind.
		wp_update_post(
			array(
				'ID'          => $known[1],
				'post_status' => 'draft',
			)
		);
		NH_SEO_Search_Indexer::reset();
		NH_SEO_Search_Index::upsert(
			$known[1],
			array(
				'post_type'  => 'post',
				'title_fold' => 'leftover',
				'tax_fold'   => '',
				'body_fold'  => '',
			)
		);
		$this->assertNotNull( NH_SEO_Search_Index::get( $known[1] ) );

		do_action( 'nh_seo_activate' );

		$state = NH_SEO_Search_State::get();
		$this->assertTrue( $state['ready'] );
		$this->assertFalse( $state['gap'], 'nothing is left to rebuild' );
		$this->assertTrue( NH_SEO_Search_State::is_active() );
		$this->assertNotNull( NH_SEO_Search_Index::get( $late ), 'the post written during the gap has a row' );
		$this->assertNull( NH_SEO_Search_Index::get( $known[1] ), 'the row of the post that is no longer public is gone' );
		$this->assertSame( NH_SEO_Search_Index::count_indexable(), NH_SEO_Search_Index::count() );
		$this->assertSame( array( $late ), $this->search_ids( 'Written' ) );
		$this->assertSame( array( $edited ), $this->search_ids( 'Rewritten' ) );
		$this->assertStringNotContainsString( 'Out of step', NH_SEO_Search_State::status_line() );
	}

	public function test_a_gap_too_large_for_one_request_sets_the_index_aside_in_auto_mode() {
		$this->ready_index();
		add_filter(
			'nh_seo_search_activation_repair_limit',
			static function () {
				return 2;
			}
		);
		$late = array();
		foreach ( array( 'Late one', 'Late two', 'Late three' ) as $title ) {
			$late[] = $this->post_without_row( $title );
		}

		do_action( 'nh_seo_activate' );

		$state = NH_SEO_Search_State::get();
		$this->assertTrue( $state['gap'] );
		$this->assertFalse( NH_SEO_Search_State::is_active(), 'auto mode does not serve an index it knows to be incomplete' );
		$this->assertStringContainsString( 'native search is used until it is rebuilt', NH_SEO_Search_State::status_line() );
		$this->assertEqualSets( $late, $this->search_ids( 'Late' ), 'native search finds the posts the index lacks' );

		$facts = $this->facts();
		$this->assertSame( 'no', $facts['used_for_searches'] );
		$this->assertSame( 'yes', $facts['rebuild_recommended'] );

		// "Always use the index" is the administrator's explicit choice: it keeps serving, and says what is wrong.
		$this->set_search_mode( 'index' );
		$this->assertTrue( NH_SEO_Search_State::is_active() );
		$this->assertStringContainsString( 'no longer matches the content', NH_SEO_Search_State::status_line() );

		$this->set_search_mode( 'auto' );
		$this->index_all();
		$this->assertFalse( NH_SEO_Search_State::get()['gap'], 'a rebuild closes the gap' );
		$this->assertTrue( NH_SEO_Search_State::is_active() );
		$this->assertEqualSets( $late, $this->search_ids( 'Late' ) );
		$this->assertSame( 'no', $this->facts()['rebuild_recommended'] );
	}

	public function test_activation_leaves_an_index_that_is_being_built_or_was_never_built_alone() {
		$this->make_post( array( 'post_title' => 'Existing post' ) );
		do_action( 'nh_seo_activate' );
		$this->assertFalse( NH_SEO_Search_State::get()['gap'], 'never built: nothing to compare' );
		$this->assertSame( 0, NH_SEO_Search_Index::count() );

		NH_SEO_Search_Builder::start();
		add_filter(
			'nh_seo_search_activation_repair_limit',
			static function () {
				return 1;
			}
		);
		$this->make_post( array( 'post_title' => 'Second post' ) );
		$this->make_post( array( 'post_title' => 'Third post' ) );
		NH_SEO_Search_Indexer::reset();
		do_action( 'nh_seo_activate' );

		$state = NH_SEO_Search_State::get();
		$this->assertTrue( $state['building'], 'the running build is not disturbed' );
		$this->assertFalse( $state['gap'] );
		$this->assertSame( 0, NH_SEO_Search_Index::count() );
	}

	public function test_the_status_line_says_how_far_a_ready_index_is_from_the_content() {
		$known = $this->ready_index();
		$this->assertStringContainsString( 'Ready: 5 posts indexed', NH_SEO_Search_State::status_line() );
		$this->assertStringNotContainsString( 'Out of step', NH_SEO_Search_State::status_line() );
		$facts = $this->facts();
		$this->assertSame( '0', $facts['posts_missing'] );
		$this->assertSame( '0', $facts['posts_outdated'] );
		$this->assertSame( 'no', $facts['rebuild_recommended'] );

		$this->post_without_row( 'Missing one' );
		$this->post_without_row( 'Missing two' );
		$this->edit_without_reindexing( $known[2], 'Edited behind the index' );

		$this->assertStringContainsString( 'Out of step with the content. Posts missing from the index: 2. Posts edited after they were indexed: 1.', NH_SEO_Search_State::status_line() );
		$facts = $this->facts();
		$this->assertSame( '2', $facts['posts_missing'] );
		$this->assertSame( '1', $facts['posts_outdated'] );
		$this->assertSame( 'yes', $facts['rebuild_recommended'] );
		$this->assertTrue( NH_SEO_Search_State::is_active(), 'only the activation check, or the administrator, sets the index aside' );
	}

	public function test_the_status_line_reports_index_updates_that_wp_cron_has_not_run() {
		$this->ready_index();
		wp_schedule_single_event( time() - 2 * HOUR_IN_SECONDS, NH_SEO_Search_Indexer::CRON_IDS, array( array( 1 ) ) );
		wp_schedule_single_event( time() + 600, NH_SEO_Search_Indexer::CRON_TERM, array( 'category', 1, 0 ) );

		$this->assertStringContainsString( 'Index updates waiting in WP-Cron: 2 (overdue: 1).', NH_SEO_Search_State::status_line() );
		$facts = $this->facts();
		$this->assertSame( '2', $facts['wp_cron_updates_queued'] );
		$this->assertSame( '1', $facts['wp_cron_updates_overdue'] );
		$this->assertSame( 'yes', $facts['rebuild_recommended'], 'an update that is long overdue means WP-Cron is not running' );

		wp_unschedule_hook( NH_SEO_Search_Indexer::CRON_IDS );
		$this->assertStringContainsString( 'Index updates waiting in WP-Cron: 1 (overdue: 0).', NH_SEO_Search_State::status_line() );
		$this->assertSame( 'no', $this->facts()['rebuild_recommended'], 'updates that are merely queued are normal' );

		wp_unschedule_hook( NH_SEO_Search_Indexer::CRON_TERM );
		$this->assertStringNotContainsString( 'WP-Cron', NH_SEO_Search_State::status_line() );
	}

	public function test_a_term_rename_walk_that_never_ran_is_reported_not_hidden() {
		$this->ready_index();
		$category = self::factory()->category->create( array( 'name' => 'Old name' ) );
		wp_update_term( $category, 'category', array( 'name' => 'New name' ) );

		$pending = wp_next_scheduled( NH_SEO_Search_Indexer::CRON_TERM, array( 'category', $category, 0 ) );
		$this->assertNotFalse( $pending, 'the rename scheduled the walk over the posts of the term' );
		$this->assertStringContainsString( 'Index updates waiting in WP-Cron: 1 (overdue: 0).', NH_SEO_Search_State::status_line() );
	}
}
