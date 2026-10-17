<?php
/**
 * Search index Tools panel: markup, escaping, assets, AJAX endpoints (capability, nonce, method) and the script.
 *
 * Runs in autocommit mode against InnoDB FULLTEXT (see NH_Search_Test_Case for why).
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

// Autocommit base class.
require_once __DIR__ . '/class-nh-search-test-case.php';

/**
 * Admin panel and AJAX tests.
 */
class NH_Test_Search_Admin extends NH_Search_Test_Case {

	/**
	 * Request method before the test.
	 *
	 * @var string|null
	 */
	private $old_method;

	public function set_up() {
		parent::set_up();
		$this->old_method = isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : null;
		wp_deregister_script( NH_SEO_Search_Admin::HANDLE );
		wp_dequeue_script( NH_SEO_Search_Admin::HANDLE );
	}

	public function tear_down() {
		if ( null === $this->old_method ) {
			unset( $_SERVER['REQUEST_METHOD'] );
		} else {
			$_SERVER['REQUEST_METHOD'] = $this->old_method;
		}
		wp_dequeue_script( NH_SEO_Search_Admin::HANDLE );
		wp_deregister_script( NH_SEO_Search_Admin::HANDLE );
		parent::tear_down();
	}

	/**
	 * Run an AJAX action the way admin-ajax.php does and return the decoded JSON answer.
	 *
	 * @param string      $action Action without the wp_ajax_ prefix.
	 * @param array       $post   POST fields.
	 * @param string|null $role   Role of the current user, or null for a visitor.
	 * @param bool|string $nonce  true for a valid nonce, false for none, a string for that exact value.
	 * @param string      $method Request method.
	 * @return array|null
	 */
	private function ajax( $action, array $post = array(), $role = 'administrator', $nonce = true, $method = 'POST' ) {
		wp_set_current_user( $role ? self::factory()->user->create( array( 'role' => $role ) ) : 0 );

		if ( true === $nonce ) {
			$post['nonce'] = wp_create_nonce( NH_SEO_Search_Admin::NONCE_ACTION );
		} elseif ( is_string( $nonce ) ) {
			$post['nonce'] = $nonce;
		}
		$_POST                     = $post;
		$_REQUEST                  = $post;
		$_SERVER['REQUEST_METHOD'] = $method;

		// AJAX requests end through the "ajax" die handler, which the suite's handler replaces by an exception.
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', array( $this, 'get_wp_die_handler' ) );
		ob_start();
		try {
			do_action( 'wp_ajax_' . $action );
		} catch ( WPDieException $e ) {
			unset( $e ); // wp_send_json_* ends the request with wp_die(); the answer is in the buffer.
		}
		$output = ob_get_clean();
		remove_filter( 'wp_die_ajax_handler', array( $this, 'get_wp_die_handler' ) );
		remove_filter( 'wp_doing_ajax', '__return_true' );

		return json_decode( $output, true );
	}

	/**
	 * Create published posts.
	 *
	 * @param int $count Count.
	 * @return int[]
	 */
	private function posts( $count ) {
		$ids = array();
		for ( $i = 1; $i <= $count; $i++ ) {
			$ids[] = $this->make_post( array( 'post_title' => 'Ajax post ' . $i ) );
		}
		NH_SEO_Search_Indexer::reset();
		return $ids;
	}

	public function test_the_panel_hooks_are_registered() {
		$this->assertNotFalse( has_action( 'nh_seo_render_tools_panel', array( 'NH_SEO_Search_Admin', 'render_panel' ) ) );
		$this->assertNotFalse( has_filter( 'nh_seo_search_status', array( 'NH_SEO_Search_Admin', 'filter_status' ) ) );
		$this->assertNotFalse( has_action( 'wp_ajax_nh_seo_search_build' ) );
		$this->assertNotFalse( has_action( 'wp_ajax_nh_seo_search_clear' ) );
		$this->assertFalse( has_action( 'wp_ajax_nopriv_nh_seo_search_build' ), 'Never available to visitors.' );
		$this->assertFalse( has_action( 'wp_ajax_nopriv_nh_seo_search_clear' ), 'Never available to visitors.' );
	}

	public function test_the_panel_shows_the_status_progress_and_both_buttons_to_administrators() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		ob_start();
		do_action( 'nh_seo_render_tools_panel' );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'id="nh-seo-search-panel"', $html );
		$this->assertStringContainsString( 'id="nh-seo-search-build"', $html );
		$this->assertStringContainsString( 'id="nh-seo-search-clear"', $html );
		$this->assertStringContainsString( 'Build / rebuild index', $html );
		$this->assertStringContainsString( 'Clear index', $html );
		$this->assertStringContainsString( '<progress id="nh-seo-search-progress"', $html );
		$this->assertStringContainsString( 'role="status" aria-live="polite"', $html, 'Progress is announced to screen readers.' );
		$this->assertStringContainsString( esc_html( NH_SEO_Search_State::status_line() ), $html );
		$this->assertStringContainsString( 'Not built yet', $html );
		$this->assertSame( 2, substr_count( $html, 'type="button"' ), 'Buttons never submit the settings form.' );
	}

	/**
	 * Render a tab of the plugin's settings page (the admin module's own entry points).
	 *
	 * @param string $tab Tab slug.
	 * @return string
	 */
	private function settings_tab( $tab ) {
		$_GET['tab'] = $tab;
		NH_SEO_Settings_Page::register_settings();
		ob_start();
		NH_SEO_Settings_Page::render_page();
		$html = ob_get_clean();
		unset( $_GET['tab'] );
		return $html;
	}

	public function test_the_settings_page_shows_the_panel_in_its_tools_tab_and_the_status_line_in_its_status_box() {
		if ( ! class_exists( 'NH_SEO_Settings_Page' ) ) {
			$this->markTestSkipped( 'The admin module is not loaded.' );
		}
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$tools = $this->settings_tab( 'tools' );
		$this->assertStringContainsString( 'id="nh-seo-search-panel"', $tools );
		$this->assertStringContainsString( 'id="nh-seo-search-build"', $tools );
		$this->assertStringNotContainsString( 'There are no tools available.', $tools );

		$rows = NH_SEO_Settings_Page::status_rows();
		$this->assertSame( NH_SEO_Search_State::status_line(), $rows['Internal search index'] );
		$this->assertStringContainsString( 'Not built yet', $rows['Internal search index'] );

		$this->posts( 3 );
		$this->index_all();
		$rows = NH_SEO_Settings_Page::status_rows();
		$this->assertStringContainsString( 'Ready: 3 posts indexed', $rows['Internal search index'] );
		$this->assertStringContainsString( esc_html( $rows['Internal search index'] ), $this->settings_tab( 'status' ) );
	}

	public function test_the_panel_prints_nothing_without_manage_options() {
		foreach ( array( 'editor', 'author', 'subscriber', null ) as $role ) {
			wp_set_current_user( $role ? self::factory()->user->create( array( 'role' => $role ) ) : 0 );
			ob_start();
			do_action( 'nh_seo_render_tools_panel' );
			$this->assertSame( '', ob_get_clean(), (string) $role );
		}
		$this->assertFalse( wp_script_is( NH_SEO_Search_Admin::HANDLE, 'enqueued' ), 'No script for users who cannot use the panel.' );
	}

	public function test_the_panel_enqueues_its_script_with_a_nonce_and_texts() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		ob_start();
		do_action( 'nh_seo_render_tools_panel' );
		ob_end_clean();

		$this->assertTrue( wp_script_is( NH_SEO_Search_Admin::HANDLE, 'enqueued' ) );
		$script = wp_scripts()->registered[ NH_SEO_Search_Admin::HANDLE ];
		$this->assertSame( NH_SEO_URL . 'assets/admin-search-index.js', $script->src );
		$this->assertSame( NH_SEO_VERSION, $script->ver );
		$this->assertSame( 1, $script->extra['group'], 'Loaded in the footer.' );

		$inline = wp_scripts()->get_data( NH_SEO_Search_Admin::HANDLE, 'before' );
		$inline = is_array( $inline ) ? implode( "\n", $inline ) : (string) $inline;
		$this->assertStringContainsString( 'window.nhSeoSearchIndex = ', $inline );
		$this->assertSame( 1, preg_match( '/window\.nhSeoSearchIndex = (\{.*\});/s', $inline, $match ) );
		$config = json_decode( $match[1], true, 512, JSON_THROW_ON_ERROR );
		$this->assertSame( admin_url( 'admin-ajax.php' ), $config['ajaxUrl'] );
		$this->assertSame( 1, wp_verify_nonce( $config['nonce'], NH_SEO_Search_Admin::NONCE_ACTION ) );
		$this->assertSame( 'nh_seo_search_build', $config['buildAction'] );
		$this->assertSame( 'nh_seo_search_clear', $config['clearAction'] );
		foreach ( array( 'starting', 'progress', 'done', 'clearing', 'cleared', 'confirmClear', 'retrying', 'failed', 'leaveWarning' ) as $key ) {
			$this->assertNotEmpty( $config['i18n'][ $key ], $key );
		}
		$this->assertStringNotContainsString( '</script', $inline );
		$this->assertFileExists( NH_SEO_DIR . 'assets/admin-search-index.js' );
	}

	public function test_status_text_in_the_panel_is_escaped() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		add_filter(
			'nh_seo_search_status',
			static function () {
				return '<img src=x onerror=alert(1)> & "quotes"';
			},
			99
		);
		NH_SEO_Search_State::trip( '<img src=x onerror=alert(1)>' );

		ob_start();
		do_action( 'nh_seo_render_tools_panel' );
		$html = ob_get_clean();

		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringContainsString( 'paused', $html );
	}

	public function test_a_full_build_over_ajax_batch_by_batch() {
		$ids = $this->posts( 25 );
		add_filter(
			'nh_seo_search_batch_size',
			static function () {
				return 10;
			}
		);

		$start = $this->ajax( 'nh_seo_search_build', array( 'step' => 'start' ) );
		$this->assertTrue( $start['success'] );
		$this->assertSame( 25, $start['data']['total'] );
		$this->assertSame( 0, $start['data']['cursor'] );
		$this->assertFalse( $start['data']['done'] );
		$build = $start['data']['build'];
		$this->assertSame( 12, strlen( $build ) );

		$cursor  = 0;
		$batches = 0;
		do {
			$batch = $this->ajax(
				'nh_seo_search_build',
				array(
					'step'   => 'batch',
					'cursor' => $cursor,
					'build'  => $build,
				)
			);
			$this->assertTrue( $batch['success'], wp_json_encode( $batch ) );
			$this->assertSame( $build, $batch['data']['build'] );
			$cursor = $batch['data']['cursor'];
			++$batches;
		} while ( ! $batch['data']['done'] && $batches < 10 );

		$this->assertSame( 3, $batches );
		$this->assertSame( 100, $batch['data']['percent'] );
		$this->assertSame( 25, $batch['data']['indexed'] );
		$this->assertTrue( $batch['data']['ready'] );
		$this->assertSame( $ids, NH_SEO_Search_Index::ids() );
		$this->assertTrue( NH_SEO_Search_State::get()['ready'] );
	}

	public function test_a_repeated_batch_request_changes_nothing() {
		$this->posts( 15 );
		add_filter(
			'nh_seo_search_batch_size',
			static function () {
				return 10;
			}
		);
		$start = $this->ajax( 'nh_seo_search_build', array( 'step' => 'start' ) );
		$args  = array(
			'step'   => 'batch',
			'cursor' => 0,
			'build'  => $start['data']['build'],
		);

		$first  = $this->ajax( 'nh_seo_search_build', $args );
		$second = $this->ajax( 'nh_seo_search_build', $args );
		$this->assertSame( $first, $second );
		$this->assertCount( 10, NH_SEO_Search_Index::ids() );
	}

	public function test_a_superseded_build_is_refused() {
		$this->posts( 15 );
		$first  = $this->ajax( 'nh_seo_search_build', array( 'step' => 'start' ) );
		$second = $this->ajax( 'nh_seo_search_build', array( 'step' => 'start' ) );

		$stale = $this->ajax(
			'nh_seo_search_build',
			array(
				'step'   => 'batch',
				'cursor' => 0,
				'build'  => $first['data']['build'],
			)
		);
		$this->assertFalse( $stale['success'] );
		$this->assertStringContainsString( 'Another index build has started', $stale['data']['message'] );

		$missing = $this->ajax(
			'nh_seo_search_build',
			array(
				'step'   => 'batch',
				'cursor' => 0,
			)
		);
		$this->assertFalse( $missing['success'], 'No build token while a build runs.' );

		$current = $this->ajax(
			'nh_seo_search_build',
			array(
				'step'   => 'batch',
				'cursor' => 0,
				'build'  => $second['data']['build'],
			)
		);
		$this->assertTrue( $current['success'] );
	}

	public function test_unknown_steps_and_garbage_are_refused() {
		$this->assertFalse( $this->ajax( 'nh_seo_search_build', array( 'step' => 'explode' ) )['success'] );
		$this->assertFalse( $this->ajax( 'nh_seo_search_build', array() )['success'] );
		$this->assertFalse( $this->ajax( 'nh_seo_search_build', array( 'step' => array( 'start' ) ) )['success'] );

		$this->posts( 3 );
		$start = $this->ajax( 'nh_seo_search_build', array( 'step' => 'start' ) );
		$batch = $this->ajax(
			'nh_seo_search_build',
			array(
				'step'   => 'batch',
				'cursor' => '-5; DROP TABLE x',
				'build'  => $start['data']['build'] . "'",
			)
		);
		$this->assertFalse( $batch['success'], 'A tampered build token is refused.' );

		$batch = $this->ajax(
			'nh_seo_search_build',
			array(
				'step'   => 'batch',
				'cursor' => '-5; DROP TABLE x',
				'build'  => $start['data']['build'],
			)
		);
		$this->assertTrue( $batch['success'], 'A garbage cursor is read as an integer.' );
		$this->assertTrue( NH_SEO_Search_Index::table_exists() );
	}

	public function test_every_action_needs_post_manage_options_and_a_valid_nonce() {
		$this->posts( 3 );
		$valid_fields = array( 'step' => 'start' );

		foreach ( array( 'nh_seo_search_build', 'nh_seo_search_clear' ) as $action ) {
			$fields = 'nh_seo_search_build' === $action ? $valid_fields : array();

			$answer = $this->ajax( $action, $fields, 'administrator', false );
			$this->assertFalse( $answer['success'], $action . ': no nonce.' );

			$answer = $this->ajax( $action, $fields, 'administrator', 'bogus' );
			$this->assertFalse( $answer['success'], $action . ': wrong nonce.' );

			$other  = wp_create_nonce( 'some_other_action' );
			$answer = $this->ajax( $action, $fields, 'administrator', $other );
			$this->assertFalse( $answer['success'], $action . ': a nonce for another action.' );

			foreach ( array( 'editor', 'author', 'subscriber', null ) as $role ) {
				$answer = $this->ajax( $action, $fields, $role, true );
				$this->assertFalse( $answer['success'], $action . ': role ' . (string) $role );
			}

			$answer = $this->ajax( $action, $fields, 'administrator', true, 'GET' );
			$this->assertFalse( $answer['success'], $action . ': GET is refused.' );
		}

		$this->assertSame( 0, NH_SEO_Search_Index::count(), 'Nothing was written by any refused request.' );
		$this->assertFalse( NH_SEO_Search_State::get()['building'] );
	}

	public function test_a_nonce_from_one_user_does_not_work_for_another() {
		$first = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $first );
		$nonce = wp_create_nonce( NH_SEO_Search_Admin::NONCE_ACTION );

		$answer = $this->ajax( 'nh_seo_search_build', array( 'step' => 'start' ), 'administrator', $nonce );
		$this->assertFalse( $answer['success'], 'Another administrator, same nonce string.' );
	}

	public function test_clearing_over_ajax_empties_the_index_and_resets_the_state() {
		$this->posts( 5 );
		$this->index_all();
		$this->assertSame( 5, NH_SEO_Search_Index::count() );

		$answer = $this->ajax( 'nh_seo_search_clear' );

		$this->assertTrue( $answer['success'] );
		$this->assertSame( 0, NH_SEO_Search_Index::count() );
		$this->assertSame( NH_SEO_Search_State::defaults(), NH_SEO_Search_State::get() );
		$this->assertSame( NH_SEO_Search_State::status_line(), $answer['data']['status'] );

		global $wpdb;
		$this->assertSame( 5, (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish'" ), 'Posts themselves are untouched.' );
	}

	public function test_the_script_runs_correctly_in_a_fake_browser() {
		$node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec
		if ( '' === $node ) {
			$this->markTestSkipped( 'node is not available to run the script harness.' );
		}

		$harness = __DIR__ . '/js/search-index-panel.harness.js';
		$script  = NH_SEO_DIR . 'assets/admin-search-index.js';
		$output  = array();
		$code    = 0;
		exec( escapeshellarg( $node ) . ' ' . escapeshellarg( $harness ) . ' ' . escapeshellarg( $script ) . ' 2>&1', $output, $code ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec

		$this->assertSame( 0, $code, implode( "\n", $output ) );
		$this->assertContains( 'ALL PASSED', $output );
		$this->assertNotContains( 'not ok', array_map( 'trim', $output ) );
	}
}
