<?php
/**
 * Cache module: purging happens once at shutdown, respects the setting, and reaches every cache integration.
 *
 * SIMULATIONS: WP Rocket, W3 Total Cache and WP Super Cache are simulated by stand-in functions of the same
 * names (tests/phpunit/plugin/stubs/third-party-cache-functions.php), LiteSpeed Cache by an action listener.
 * Nothing here proves that a real cache plugin purges its files; it proves what is handed over and when.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

// Shared fixtures.
require_once __DIR__ . '/class-nh-cache-test-case.php';

/**
 * Dispatch tests.
 */
class NH_Test_Cache_Dispatch extends NH_Cache_Test_Case {

	/**
	 * Make the simulated cache plugin functions exist from here on.
	 *
	 * @return void
	 */
	private function install_simulated_cache_plugins() {
		require_once __DIR__ . '/stubs/third-party-cache-functions.php';
	}

	/**
	 * Record LiteSpeed's purge action.
	 *
	 * @return ArrayObject
	 */
	private function spy_on_litespeed() {
		$calls = new ArrayObject();
		add_action(
			'litespeed_purge_url',
			static function ( $url ) use ( $calls ) {
				$calls[] = $url;
			}
		);
		return $calls;
	}

	/**
	 * Must run before the simulated plugin functions are defined (they stay defined for the rest of the process):
	 * proves that nothing breaks when no cache plugin exists. It skips itself when they are already loaded.
	 *
	 * @return void
	 */
	public function test_with_no_cache_plugin_installed_only_the_generic_action_fires() {
		if ( function_exists( 'rocket_clean_files' ) || function_exists( 'w3tc_flush_url' ) || function_exists( 'wpsc_delete_url_cache' ) ) {
			$this->markTestSkipped( 'The simulated cache plugin functions are already loaded in this PHP process (they cannot be unloaded).' );
		}
		$this->assertFalse( has_action( 'litespeed_purge_url' ) );

		$spy     = $this->spy_on_purge_action();
		$post_id = $this->make_post();
		NH_SEO_Cache::flush();

		$this->assertCount( 1, $spy );
		$this->assertContains( get_permalink( $post_id ), $spy[0] );
		$this->assertSame( array(), $this->cache_calls() );
	}

	public function test_dispatch_does_not_fail_without_any_integration() {
		$spy = $this->spy_on_purge_action();
		NH_SEO_Cache::dispatch( array( home_url( '/' ), home_url( '/' ), 'javascript:alert(1)', '/relative/' ) );
		$this->assertCount( 1, $spy );
		$this->assertSame( array( home_url( '/' ) ), $spy[0], 'The generic action gets a clean, de-duplicated list.' );
	}

	public function test_many_changes_in_one_request_purge_once_at_shutdown() {
		$spy = $this->spy_on_purge_action();

		$first  = $this->make_post();
		$second = $this->make_post();
		wp_update_post(
			array(
				'ID'         => $first,
				'post_title' => 'Changed',
			)
		);
		wp_set_post_tags( $first, array( 'one', 'two' ) );
		wp_set_post_tags( $second, array( 'two', 'three' ) );
		wp_trash_post( $second );
		wp_insert_term( 'A new topic', 'category' );

		$this->assertSame( 20, has_action( 'shutdown', array( 'NH_SEO_Cache', 'flush' ) ), 'The purge is registered for shutdown, not run inline.' );
		$this->assertCount( 0, $spy, 'Nothing has been purged during the request.' );

		NH_SEO_Cache::flush();

		$this->assertCount( 1, $spy, 'One purge for the whole request.' );
		$urls = $spy[0];
		$this->assertSame( $urls, array_values( array_unique( $urls ) ) );
		$this->assertContains( get_permalink( $first ), $urls );
		$this->assertContains( home_url( '/' ), $urls );

		NH_SEO_Cache::flush();
		$this->assertCount( 1, $spy, 'A second flush finds nothing left to do.' );
		$this->assertSame(
			array(
				'posts' => array(),
				'urls'  => array(),
			),
			NH_SEO_Cache::pending()
		);
	}

	public function test_the_shutdown_hook_is_registered_only_when_there_is_something_to_purge() {
		remove_all_actions( 'shutdown', 20 );
		$this->assertFalse( has_action( 'shutdown', array( 'NH_SEO_Cache', 'flush' ) ) );

		$this->make_post( array( 'post_status' => 'draft' ) );
		$this->assertFalse( has_action( 'shutdown', array( 'NH_SEO_Cache', 'flush' ) ), 'A draft changes no public page.' );

		$this->make_post();
		$this->assertSame( 20, has_action( 'shutdown', array( 'NH_SEO_Cache', 'flush' ) ) );
	}

	public function test_nothing_is_collected_or_purged_when_the_setting_is_off() {
		update_option( NH_SEO_Keys::OPTION_SETTINGS, array( 'purge_caches' => 0 ) );
		$this->assertFalse( NH_SEO_Cache::enabled() );
		$spy = $this->spy_on_purge_action();
		remove_all_actions( 'shutdown', 20 );

		$post_id = $this->make_post();
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Changed while purging is off',
			)
		);
		wp_trash_post( $post_id );
		wp_insert_term( 'Another topic', 'category' );
		NH_SEO_Cache::queue_post( $post_id );
		NH_SEO_Cache::queue_url( home_url( '/' ) );

		$this->assertSame(
			array(
				'posts' => array(),
				'urls'  => array(),
			),
			NH_SEO_Cache::pending()
		);
		$this->assertFalse( has_action( 'shutdown', array( 'NH_SEO_Cache', 'flush' ) ) );
		NH_SEO_Cache::flush();
		$this->assertCount( 0, $spy );
	}

	public function test_switching_the_setting_off_before_shutdown_still_prevents_the_purge() {
		$spy = $this->spy_on_purge_action();
		$this->make_post();
		$this->assertNotEmpty( NH_SEO_Cache::pending()['posts'] );

		update_option( NH_SEO_Keys::OPTION_SETTINGS, array( 'purge_caches' => 0 ) );
		NH_SEO_Cache::flush();

		$this->assertCount( 0, $spy );
		$this->assertSame(
			array(
				'posts' => array(),
				'urls'  => array(),
			),
			NH_SEO_Cache::pending(),
			'The queue is emptied either way.'
		);
	}

	public function test_every_integration_that_exists_receives_the_urls() {
		$this->install_simulated_cache_plugins();
		$litespeed = $this->spy_on_litespeed();
		$generic   = $this->spy_on_purge_action();

		$post_id = $this->make_post();
		NH_SEO_Cache::flush();

		$urls = $generic[0];
		$this->assertContains( get_permalink( $post_id ), $urls );
		$this->assertContains( home_url( '/' ), $urls );

		$calls = $this->cache_calls();
		$by    = array(
			'wp-rocket'      => array(),
			'w3-total-cache' => array(),
			'wp-super-cache' => array(),
		);
		foreach ( $calls as $call ) {
			$by[ $call[0] ][] = $call[1];
		}

		$this->assertCount( 1, $by['wp-rocket'], 'WP Rocket takes the whole list in one call.' );
		$this->assertSame( $urls, $by['wp-rocket'][0] );
		$this->assertSame( $urls, $by['w3-total-cache'], 'W3 Total Cache is called once per URL.' );
		$this->assertSame( $urls, $by['wp-super-cache'], 'WP Super Cache is called once per URL.' );
		$this->assertSame( $urls, $litespeed->getArrayCopy(), 'LiteSpeed Cache gets one action per URL.' );
	}

	public function test_litespeed_is_only_called_when_something_listens() {
		$this->install_simulated_cache_plugins();
		$this->assertFalse( has_action( 'litespeed_purge_url' ) );

		$counter = 0;
		add_filter(
			'all',
			static function ( $hook ) use ( &$counter ) {
				if ( 'litespeed_purge_url' === $hook ) {
					++$counter;
				}
			}
		);
		$this->make_post();
		NH_SEO_Cache::flush();
		$this->assertSame( 0, $counter, 'No listener: the action is not even fired.' );
	}

	public function test_a_failing_cache_plugin_does_not_stop_the_others() {
		$this->install_simulated_cache_plugins();
		$litespeed = $this->spy_on_litespeed();
		$failures  = array();
		add_action(
			'nh_seo_purge_failed',
			static function ( $name, $error ) use ( &$failures ) {
				$failures[ $name ] = $error->getMessage();
			},
			10,
			2
		);

		foreach ( array( 'wp-rocket', 'w3-total-cache', 'wp-super-cache' ) as $broken ) {
			$GLOBALS['nh_test_cache_calls'] = array();
			$GLOBALS['nh_test_cache_throw'] = $broken;
			$failures                       = array();
			$litespeed->exchangeArray( array() );

			$this->make_post();
			NH_SEO_Cache::flush();

			$called = array_unique( wp_list_pluck( $this->cache_calls(), 0 ) );
			sort( $called );
			$this->assertSame( array( $broken ), array_keys( $failures ), $broken . ' failed and was reported.' );
			$this->assertNotEmpty( $litespeed->getArrayCopy(), 'LiteSpeed is still purged when ' . $broken . ' fails.' );
			$expected = array( 'w3-total-cache', 'wp-rocket', 'wp-super-cache' );
			foreach ( $expected as $name ) {
				if ( $name !== $broken ) {
					$this->assertContains( $name, $called, $name . ' is still purged when ' . $broken . ' fails.' );
				}
			}
		}
		unset( $GLOBALS['nh_test_cache_throw'] );
	}

	public function test_an_empty_or_junk_url_list_dispatches_nothing() {
		$this->install_simulated_cache_plugins();
		$spy = $this->spy_on_purge_action();

		NH_SEO_Cache::dispatch( array() );
		NH_SEO_Cache::dispatch( array( '', 'javascript:alert(1)', '/relative/', 'ftp://example.org/file', 42 ) );

		$this->assertCount( 0, $spy );
		$this->assertSame( array(), $this->cache_calls() );
	}

	public function test_purging_never_makes_a_remote_request() {
		$this->install_simulated_cache_plugins();
		$this->spy_on_litespeed();

		$post_id = $this->make_post();
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Remote free',
			)
		);
		wp_trash_post( $post_id );
		NH_SEO_Cache::flush();

		$this->assertNotEmpty( $this->cache_calls() );
		$this->assertSame( 0, $this->http_attempts );
	}

	public function test_urls_of_a_post_for_tools_match_what_a_change_would_purge() {
		$post_id = $this->make_post();
		$this->assertSameUrls( NH_SEO_Cache_Urls::for_post( $post_id ), NH_SEO_Cache::urls_for_post( $post_id ) );
		$this->assertSame( array(), NH_SEO_Cache::urls_for_post( 999999 ) );
	}
}
