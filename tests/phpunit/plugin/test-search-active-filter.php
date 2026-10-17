<?php
/**
 * The filter nh_editorial_search_index_active: the Nguyen Ha Editorial theme asks it whether a fast search
 * index is serving the search, and keeps its search-as-you-type suggestions off on very large sites otherwise.
 *
 * The theme is NOT loaded in the plugin suite. These tests call the filter exactly like the theme does,
 * apply_filters( 'nh_editorial_search_index_active', false ), and check the answer against what a real search
 * does in every index state (a search that is answered by the index runs a MATCH ... AGAINST query).
 *
 * Runs in autocommit mode (see NH_Search_Test_Case) because those searches use the InnoDB FULLTEXT index.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

// Autocommit base class.
require_once __DIR__ . '/class-nh-search-test-case.php';

/**
 * Tests of the theme contract.
 */
class NH_Test_Search_Active_Filter extends NH_Search_Test_Case {

	/**
	 * The question exactly as the theme asks it.
	 *
	 * @return mixed
	 */
	private function theme_asks() {
		return apply_filters( 'nh_editorial_search_index_active', false );
	}

	/**
	 * Whether a plain search is really answered by the index right now.
	 *
	 * @return bool
	 */
	private function search_is_answered_by_the_index() {
		$this->record_sql();
		$this->search_ids( 'zebra', array( 'cache_results' => false ) );
		$this->stop_sql_log();
		return array() !== $this->sql_containing( 'MATCH(' );
	}

	/**
	 * One searchable post and a complete build.
	 *
	 * @return int Post ID.
	 */
	private function build() {
		$post_id = $this->make_post( array( 'post_title' => 'Zebra crossing rules' ) );
		$this->make_post( array( 'post_title' => 'Something else entirely' ) );
		$this->index_all();
		return $post_id;
	}

	public function test_the_search_bootstrap_registers_the_filter() {
		$this->assertNotFalse( has_filter( 'nh_editorial_search_index_active', array( 'NH_SEO_Search_Query', 'filter_index_active' ) ) );
	}

	public function test_an_index_that_was_never_built_is_not_active() {
		$this->make_post( array( 'post_title' => 'Zebra crossing rules' ) );

		$this->assertFalse( $this->theme_asks() );
		$this->assertFalse( NH_SEO_Search_Query::is_serving() );
		$this->assertFalse( $this->search_is_answered_by_the_index() );
	}

	public function test_a_complete_build_makes_it_active_and_searches_really_use_the_index() {
		$post_id = $this->build();

		$this->assertTrue( $this->theme_asks() );
		$this->assertTrue( NH_SEO_Search_Query::is_serving() );
		$this->assertTrue( $this->search_is_answered_by_the_index() );
		$this->assertSame( array( $post_id ), $this->search_ids( 'zebra' ) );
	}

	public function test_the_answer_is_strictly_boolean() {
		$this->assertSame( false, $this->theme_asks() );
		$this->build();
		$this->assertSame( true, $this->theme_asks() );
	}

	public function test_the_answer_matches_what_a_real_search_does_in_every_state() {
		$gate_off = '__return_false';

		$states = array(
			'complete build in auto mode'                  => array(
				static function () {},
				true,
			),
			'native mode with a complete build'            => array(
				function () {
					$this->set_search_mode( 'native' );
				},
				false,
			),
			'a build that has not finished (auto mode)'    => array(
				static function () {
					NH_SEO_Search_State::save(
						array(
							'ready'    => false,
							'building' => true,
						)
					);
				},
				false,
			),
			'index mode, build not finished, table exists' => array(
				function () {
					NH_SEO_Search_State::save(
						array(
							'ready'    => false,
							'building' => true,
						)
					);
					$this->set_search_mode( 'index' );
				},
				true,
			),
			'index mode without the table'                 => array(
				function () {
					NH_SEO_Search_Index::drop_table();
					$this->set_search_mode( 'index' );
				},
				false,
			),
			'paused after a database error'                => array(
				static function () {
					NH_SEO_Search_State::trip( 'Simulated error' );
				},
				false,
			),
			'index cleared from the Tools panel'           => array(
				static function () {
					NH_SEO_Search_Builder::clear();
				},
				false,
			),
			'switched off with nh_seo_search_enabled'      => array(
				static function () use ( $gate_off ) {
					add_filter( 'nh_seo_search_enabled', $gate_off );
				},
				false,
			),
		);

		foreach ( $states as $label => $state ) {
			list( $apply, $expected ) = $state;

			// Back to a complete, healthy build before each state.
			NH_SEO_Search_State::clear_breaker();
			remove_filter( 'nh_seo_search_enabled', $gate_off );
			delete_option( NH_SEO_Keys::OPTION_SETTINGS );
			NH_SEO_Search_Index::forget_table_state();
			NH_SEO_Search_Index::ensure_table();
			$this->build();

			$apply();

			$this->assertSame( $expected, $this->theme_asks(), $label . ': what the theme is told' );
			$this->assertSame( $expected, $this->search_is_answered_by_the_index(), $label . ': what a search does' );
		}
		remove_filter( 'nh_seo_search_enabled', $gate_off );
	}

	public function test_it_never_turns_an_earlier_true_into_false() {
		$this->make_post( array( 'post_title' => 'Zebra crossing rules' ) );
		$this->assertFalse( $this->theme_asks(), 'The index is not built.' );

		$this->assertTrue( apply_filters( 'nh_editorial_search_index_active', true ), 'Passed in as true, still true.' );

		add_filter( 'nh_editorial_search_index_active', '__return_true', 5 );
		$this->assertTrue( $this->theme_asks(), 'Another search plugin reported its own index first.' );
		remove_filter( 'nh_editorial_search_index_active', '__return_true', 5 );

		$this->assertFalse( $this->theme_asks() );
	}

	public function test_the_site_wide_question_asks_the_enabled_gate_with_an_empty_query() {
		$this->build();
		$seen = array();
		add_filter(
			'nh_seo_search_enabled',
			static function ( $enabled, $query ) use ( &$seen ) {
				$seen[] = $query;
				return $enabled;
			},
			10,
			2
		);

		$this->assertTrue( $this->theme_asks() );

		$this->assertCount( 1, $seen );
		$this->assertInstanceOf( 'WP_Query', $seen[0] );
		$this->assertSame( '', (string) $seen[0]->get( 's' ), 'There is no particular search to judge.' );
		$this->assertEmpty( $seen[0]->posts, 'The empty query was never run.' );
	}

	public function test_the_gate_is_not_asked_while_the_index_is_off_anyway() {
		$asked = 0;
		add_filter(
			'nh_seo_search_enabled',
			static function ( $enabled ) use ( &$asked ) {
				++$asked;
				return $enabled;
			}
		);

		$this->assertFalse( $this->theme_asks() );
		$this->assertSame( 0, $asked );
	}

	public function test_the_pause_ends_and_the_answer_follows() {
		$this->build();
		$this->assertTrue( $this->theme_asks() );

		NH_SEO_Search_State::trip( 'Simulated error' );
		$this->assertFalse( $this->theme_asks() );

		NH_SEO_Search_State::clear_breaker();
		$this->assertTrue( $this->theme_asks() );
	}

	public function test_a_missing_table_behind_a_ready_state_is_noticed_by_the_first_failing_search() {
		global $wpdb;
		$post_id = $this->build();
		NH_SEO_Search_Index::drop_table();

		// Auto mode trusts the state and does not look for the table on page views (that would cost a query each).
		$this->assertTrue( $this->theme_asks(), 'The state still says ready.' );

		$suppress = $wpdb->suppress_errors( true );
		$ids      = $this->search_ids( 'zebra', array( 'cache_results' => false ) );
		$wpdb->suppress_errors( $suppress );

		$this->assertSame( array( $post_id ), $ids, 'Native search answered the very request whose index query failed.' );
		$this->assertFalse( $this->theme_asks(), 'The failed search paused the index, so the theme stops relying on it.' );
	}

	public function test_an_unbuilt_or_native_index_costs_no_pause_lookup_and_native_mode_costs_no_query() {
		$this->make_post( array( 'post_title' => 'Zebra crossing rules' ) );

		$this->record_sql();
		$this->assertFalse( $this->theme_asks() );
		$this->stop_sql_log();
		$this->assertSame( array(), $this->sql_containing( 'nh_seo_search_off' ), 'An index that is not ready is not asked about the pause.' );

		$this->set_search_mode( 'native' );
		$this->record_sql();
		$this->assertFalse( $this->theme_asks() );
		$this->assertFalse( $this->theme_asks() );
		$this->assertSame( array(), $this->stop_sql_log(), 'Native mode is answered from the loaded settings, with no database query.' );
	}

	public function test_asking_changes_nothing_and_makes_no_remote_request() {
		$this->build();
		$before = array(
			NH_SEO_Search_State::get(),
			NH_SEO_Search_Index::count(),
			get_option( NH_SEO_Keys::OPTION_SETTINGS ),
		);

		$http = 0;
		add_filter(
			'pre_http_request',
			static function ( $preempt ) use ( &$http ) {
				++$http;
				return $preempt;
			}
		);

		$this->assertTrue( $this->theme_asks() );
		$this->assertTrue( $this->theme_asks() );

		$this->assertSame( 0, $http );
		$this->assertSame(
			$before,
			array(
				NH_SEO_Search_State::get(),
				NH_SEO_Search_Index::count(),
				get_option( NH_SEO_Keys::OPTION_SETTINGS ),
			)
		);
	}
}
