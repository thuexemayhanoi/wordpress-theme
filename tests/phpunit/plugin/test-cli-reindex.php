<?php
/**
 * WP-CLI commands `reindex` and `index-status`, exercised as plain PHP without WP-CLI.
 *
 * WP-CLI is NOT loaded: the command class talks to a recording output double. The thin WP-CLI output adapter
 * and the command registration are not exercised here.
 *
 * Runs in autocommit mode (see NH_Search_Test_Case) because rebuilding empties the index table.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

// Autocommit base class and the output double.
require_once __DIR__ . '/class-nh-search-test-case.php';
require_once __DIR__ . '/class-nh-cli-test-output.php';

/**
 * Reindex and index-status command tests.
 */
class NH_Test_CLI_Reindex extends NH_Search_Test_Case {

	/**
	 * Output double.
	 *
	 * @var NH_CLI_Test_Output
	 */
	private $out;

	/**
	 * Command under test.
	 *
	 * @var NH_SEO_CLI_Command
	 */
	private $command;

	public function set_up() {
		parent::set_up();
		$this->out     = new NH_CLI_Test_Output();
		$this->command = new NH_SEO_CLI_Command( $this->out );
	}

	/**
	 * Create published posts and pages.
	 *
	 * @param int $posts Posts.
	 * @param int $pages Pages.
	 * @return array{posts: int[], pages: int[]}
	 */
	private function content( $posts, $pages = 0 ) {
		$made = array(
			'posts' => array(),
			'pages' => array(),
		);
		for ( $i = 1; $i <= $posts; $i++ ) {
			$made['posts'][] = $this->make_post( array( 'post_title' => 'Cli post ' . $i ) );
		}
		for ( $i = 1; $i <= $pages; $i++ ) {
			$made['pages'][] = $this->make_post(
				array(
					'post_title' => 'Cli page ' . $i,
					'post_type'  => 'page',
				)
			);
		}
		NH_SEO_Search_Indexer::reset();
		return $made;
	}

	/**
	 * Run a command and return the message of the error it halted with, or '' when it finished.
	 *
	 * @param callable $run Command call.
	 * @return string
	 */
	private function halts_with( callable $run ) {
		try {
			$run();
		} catch ( RuntimeException $e ) {
			return $e->getMessage();
		}
		return '';
	}

	public function test_the_commands_work_without_wp_cli_being_loaded() {
		$this->assertFalse( defined( 'WP_CLI' ), 'WP-CLI is not running.' );
		$this->assertFalse( class_exists( 'WP_CLI', false ) );
		$this->assertTrue( class_exists( 'NH_SEO_CLI_Command' ) );
		$this->assertTrue( interface_exists( 'NH_SEO_CLI_Output' ) );

		// The default output channel is created lazily and never touched here.
		$this->assertInstanceOf( 'NH_SEO_CLI_Command', new NH_SEO_CLI_Command() );
		$this->assertInstanceOf( 'NH_SEO_CLI_Output', new NH_SEO_CLI_Output_WPCLI() );

		foreach ( array( 'reindex', 'index_status', 'purge' ) as $method ) {
			$this->assertTrue( method_exists( 'NH_SEO_CLI_Command', $method ), $method );
		}
	}

	public function test_reindex_rebuilds_everything_with_progress_and_marks_the_index_ready() {
		$made = $this->content( 20, 5 );

		$this->command->reindex(
			array(),
			array( 'batch' => '10' )
		);

		$this->assertSame( array_merge( $made['posts'], $made['pages'] ), NH_SEO_Search_Index::ids() );
		$this->assertTrue( NH_SEO_Search_State::get()['ready'] );
		$this->assertSame( array( 'Indexed 25 posts. The index is ready.' ), $this->out->messages( 'success' ) );
		$this->assertSame( array( 'start', 25 ), $this->out->progress[0] );
		$this->assertSame( 25, $this->out->ticks(), 'The progress bar ends at the number of posts.' );
		$this->assertCount(
			3,
			array_filter(
				$this->out->progress,
				static function ( $event ) {
					return 'tick' === $event[0];
				}
			),
			'Three batches of at most ten posts.'
		);
		$this->assertSame( 'finish', end( $this->out->progress )[0] );
		$this->assertSame( array(), $this->out->messages( 'warning' ) );
	}

	public function test_the_default_batch_is_200_posts() {
		$this->content( 3 );
		$sizes = array();
		add_filter(
			'nh_seo_search_batch_size',
			static function ( $size ) use ( &$sizes ) {
				$sizes[] = $size;
				return $size;
			}
		);
		$this->command->reindex( array(), array() );
		$this->assertContains( 200, $sizes );
		$this->assertTrue( NH_SEO_Search_State::get()['ready'] );
	}

	public function test_a_rebuild_replaces_whatever_the_index_held_before() {
		$made = $this->content( 3 );
		NH_SEO_Search_Index::upsert(
			987654,
			array(
				'post_type'  => 'post',
				'title_fold' => 'leftover',
				'tax_fold'   => '',
				'body_fold'  => '',
			)
		);
		$this->command->reindex( array(), array() );
		$this->assertSame( $made['posts'], NH_SEO_Search_Index::ids() );
	}

	public function test_batch_must_be_a_positive_whole_number_and_is_clamped() {
		$this->content( 12 );

		foreach ( array( '0', '-5', 'abc', '', '1.5', '10; DROP TABLE x', ' ', array( '10' ), true ) as $bad ) {
			$out     = new NH_CLI_Test_Output();
			$command = new NH_SEO_CLI_Command( $out );
			$message = $this->halts_with(
				static function () use ( $command, $bad ) {
					$command->reindex( array(), array( 'batch' => $bad ) );
				}
			);
			$this->assertStringContainsString( '--batch must be a positive whole number', $message, wp_json_encode( $bad ) );
			$this->assertSame( array(), $out->messages( 'success' ) );
		}
		$this->assertSame( 0, NH_SEO_Search_Index::count(), 'A refused command changes nothing.' );

		$out = new NH_CLI_Test_Output();
		( new NH_SEO_CLI_Command( $out ) )->reindex( array(), array( 'batch' => '3' ) );
		$this->assertSame( array( 'Batch size adjusted to 10 (allowed: 10 to 500).' ), $out->messages( 'warning' ) );
		$this->assertSame( 12, NH_SEO_Search_Index::count() );

		$out = new NH_CLI_Test_Output();
		( new NH_SEO_CLI_Command( $out ) )->reindex( array(), array( 'batch' => '99999' ) );
		$this->assertSame( array( 'Batch size adjusted to 500 (allowed: 10 to 500).' ), $out->messages( 'warning' ) );

		$out = new NH_CLI_Test_Output();
		( new NH_SEO_CLI_Command( $out ) )->reindex( array(), array( 'batch' => '10' ) );
		$this->assertSame( array(), $out->messages( 'warning' ) );
	}

	public function test_post_type_limits_the_run_without_emptying_the_index_or_changing_readiness() {
		$made = $this->content( 4, 3 );
		// Start from an empty, never built index: only the pages are written, readiness stays false.
		$this->command->reindex(
			array(),
			array( 'post-type' => 'page' )
		);

		$this->assertSame( $made['pages'], NH_SEO_Search_Index::ids() );
		$this->assertFalse( NH_SEO_Search_State::get()['ready'], 'A partial run never makes the index ready.' );
		$this->assertSame( 3, $this->out->ticks() );
		$this->assertStringContainsString( 'Re-indexed the post types page', $this->out->messages( 'success' )[0] );
		$this->assertStringContainsString( 'whether it is ready is unchanged', $this->out->messages( 'success' )[0] );
	}

	public function test_naming_every_indexed_type_is_a_full_rebuild() {
		$made = $this->content( 3, 2 );
		$this->command->reindex(
			array(),
			array( 'post-type' => ' page , POST,post' )
		);
		$this->assertSame( array_merge( $made['posts'], $made['pages'] ), NH_SEO_Search_Index::ids() );
		$this->assertTrue( NH_SEO_Search_State::get()['ready'], 'post and page are all the indexed types, so this is the full rebuild.' );
		$this->assertStringContainsString( 'The index is ready', $this->out->messages( 'success' )[0] );
	}

	public function test_unknown_or_empty_post_types_are_refused() {
		$this->content( 2 );
		foreach ( array( 'attachment', 'nope', 'post,nope', ',,', '', true ) as $bad ) {
			$out     = new NH_CLI_Test_Output();
			$command = new NH_SEO_CLI_Command( $out );
			$message = $this->halts_with(
				static function () use ( $command, $bad ) {
					$command->reindex( array(), array( 'post-type' => $bad ) );
				}
			);
			$this->assertNotSame( '', $message, wp_json_encode( $bad ) );
			if ( is_string( $bad ) && '' !== trim( str_replace( ',', '', $bad ) ) ) {
				$this->assertStringContainsString( 'Indexed post types: post, page.', $message );
			} else {
				$this->assertStringContainsString( '--post-type needs at least one post type.', $message );
			}
		}
		$this->assertSame( 0, NH_SEO_Search_Index::count() );
	}

	public function test_native_search_mode_is_mentioned_but_the_index_is_still_built() {
		$this->content( 2 );
		$this->set_search_mode( 'native' );
		$this->command->reindex( array(), array() );
		$this->assertStringContainsString( 'The search mode is "native"', $this->out->messages( 'warning' )[0] );
		$this->assertSame( 2, NH_SEO_Search_Index::count() );
	}

	public function test_a_table_that_cannot_be_created_ends_the_command_with_an_error() {
		global $wpdb;
		NH_SEO_Search_Index::drop_table();
		$break = static function ( $query ) {
			return 0 === strpos( ltrim( $query ), 'CREATE TABLE' ) ? 'CREATE TABLE this is not valid sql' : $query;
		};
		add_filter( 'query', $break );
		$suppress = $wpdb->suppress_errors( true );
		$message  = $this->halts_with(
			function () {
				$this->command->reindex( array(), array() );
			}
		);
		$wpdb->suppress_errors( $suppress );
		remove_filter( 'query', $break );

		$this->assertStringContainsString( 'The index table could not be created', $message );
		$this->assertSame( array(), $this->out->messages( 'success' ) );
		$this->assertSame( 'finish', end( $this->out->progress )[0], 'The progress bar is closed even on failure.' );
	}

	public function test_index_status_prints_one_fact_per_row_in_the_requested_format() {
		$this->command->index_status( array(), array() );
		list( $format, $rows, $fields ) = $this->out->tables[0];

		$this->assertSame( 'table', $format );
		$this->assertSame( array( 'key', 'value' ), $fields );
		$facts = wp_list_pluck( $rows, 'value', 'key' );
		$this->assertSame(
			array( 'status', 'mode', 'used_for_searches', 'ready', 'building', 'table', 'posts_in_index', 'posts_at_last_build', 'last_run', 'paused_until', 'last_error', 'rebuild_recommended' ),
			array_keys( $facts )
		);
		$this->assertSame( 'auto', $facts['mode'] );
		$this->assertSame( 'no', $facts['used_for_searches'] );
		$this->assertSame( 'no', $facts['ready'] );
		$this->assertSame( '0', $facts['posts_in_index'] );
		$this->assertSame( '', $facts['last_run'] );
		$this->assertSame( NH_SEO_Search_Index::table(), $facts['table'] );
		$this->assertStringContainsString( 'Not built yet', $facts['status'] );

		foreach ( array( 'json', 'csv', 'yaml' ) as $wanted ) {
			$out = new NH_CLI_Test_Output();
			( new NH_SEO_CLI_Command( $out ) )->index_status( array(), array( 'format' => $wanted ) );
			$this->assertSame( $wanted, $out->tables[0][0] );
		}

		$out     = new NH_CLI_Test_Output();
		$command = new NH_SEO_CLI_Command( $out );
		$message = $this->halts_with(
			static function () use ( $command ) {
				$command->index_status( array(), array( 'format' => 'xml' ) );
			}
		);
		$this->assertStringContainsString( '--format must be table, json, csv or yaml', $message );
		$this->assertSame( array(), $out->tables );
	}

	public function test_index_status_after_a_build_a_pause_and_a_missing_table() {
		$this->content( 7 );
		$this->command->reindex( array(), array() );

		$facts = wp_list_pluck( NH_SEO_CLI_Command::status_rows(), 'value', 'key' );
		$this->assertSame( 'yes', $facts['used_for_searches'] );
		$this->assertSame( 'yes', $facts['ready'] );
		$this->assertSame( '7', $facts['posts_in_index'] );
		$this->assertSame( '7', $facts['posts_at_last_build'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d UTC$/', $facts['last_run'] );
		$this->assertStringContainsString( 'Ready: 7 posts indexed', $facts['status'] );

		NH_SEO_Search_State::trip( 'Simulated error' );
		$facts = wp_list_pluck( NH_SEO_CLI_Command::status_rows(), 'value', 'key' );
		$this->assertSame( 'no', $facts['used_for_searches'], 'Paused.' );
		$this->assertMatchesRegularExpression( '/UTC$/', $facts['paused_until'] );
		$this->assertSame( 'Simulated error', $facts['last_error'] );
		NH_SEO_Search_State::clear_breaker();

		NH_SEO_Search_Index::drop_table();
		$facts = wp_list_pluck( NH_SEO_CLI_Command::status_rows(), 'value', 'key' );
		$this->assertStringContainsString( '(missing)', $facts['table'] );
		$this->assertSame( '0', $facts['posts_in_index'] );

		NH_SEO_Search_State::save( array( 'stale' => true ) );
		$facts = wp_list_pluck( NH_SEO_CLI_Command::status_rows(), 'value', 'key' );
		$this->assertSame( 'yes', $facts['rebuild_recommended'] );
	}
}
