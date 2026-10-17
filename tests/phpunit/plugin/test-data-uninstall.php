<?php
/**
 * Tests for uninstall: the plugin's own storage goes, editorial data stays.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * NH_SEO_Uninstall and uninstall.php.
 */
class NH_Test_Data_Uninstall extends NH_Unit_Test_Case {

	/**
	 * Throw-away site prefix used for the search table, so a real index table is never involved.
	 *
	 * @var string
	 */
	const TEST_PREFIX = 'wptests_nhun_';

	/**
	 * The uninstall class is only loaded by uninstall.php in production, never on normal requests.
	 *
	 * @return void
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();
		require_once NH_SEO_DIR . 'includes/class-nh-seo-uninstall.php';
	}

	/**
	 * Whether a table can be read.
	 *
	 * @param string $table Table name.
	 * @return bool
	 */
	private function table_exists( $table ) {
		global $wpdb;
		$wpdb->suppress_errors( true );
		$count = $wpdb->get_var( 'SELECT COUNT(*) FROM `' . esc_sql( $table ) . '`' );
		$wpdb->suppress_errors( false );
		return null !== $count;
	}

	/**
	 * Number of option rows whose name contains the plugin prefix (options, transients and timeouts).
	 *
	 * @return int
	 */
	private function plugin_option_rows() {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", '%' . $wpdb->esc_like( 'nh_seo_' ) . '%' ) );
	}

	/**
	 * Create editorial data of every kind, as a site that used the plugin would have.
	 *
	 * @return array IDs of what was created.
	 */
	private function editorial_data() {
		$user    = self::factory()->user->create( array( 'role' => 'author' ) );
		$pillar  = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$cluster = self::factory()->term->create(
			array(
				'taxonomy' => 'topic_cluster',
				'name'     => 'Scooter basics',
			)
		);
		$post    = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_author' => $user,
			)
		);
		wp_set_object_terms( $post, array( $cluster ), 'topic_cluster' );
		update_post_meta( $post, 'nh_summary', 'A summary' );
		update_post_meta( $post, 'nh_takeaways', array( 'One', 'Two' ) );
		update_post_meta(
			$post,
			'nh_faq',
			array(
				array(
					'question' => 'Q?',
					'answer'   => 'A.',
				),
			)
		);
		update_post_meta( $post, 'nh_sources', array( array( 'url' => 'https://example.org' ) ) );
		update_post_meta( $post, 'nh_content_updated', '2024-01-15' );
		update_post_meta( $post, 'nh_topic_cluster', 'Scooter basics' );
		update_post_meta( $post, 'nh_primary_cluster', $cluster );
		update_post_meta( $post, 'nh_pillar_id', $pillar );
		update_post_meta( $post, 'nh_local_business', 1 );
		update_post_meta( $post, 'nh_keywords', 'one, two' );
		update_post_meta( $post, 'nh_seo_looks_like_an_option_key', 'but it is post meta' );
		update_term_meta( $cluster, 'nh_pillar_id', $pillar );
		update_user_meta( $user, 'nh_job_title', 'Guide' );
		update_user_meta( $user, 'nh_sameas', 'https://example.org/me' );

		return array(
			'user'    => $user,
			'pillar'  => $pillar,
			'cluster' => $cluster,
			'post'    => $post,
		);
	}

	/**
	 * A fingerprint of all editorial data, to compare before and after.
	 *
	 * @param array $ids IDs from editorial_data().
	 * @return array
	 */
	private function fingerprint( array $ids ) {
		global $wpdb;
		return array(
			'post_meta'     => $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d ORDER BY meta_key", $ids['post'] ), ARRAY_A ),
			'term_meta'     => $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->termmeta} WHERE term_id = %d ORDER BY meta_key", $ids['cluster'] ), ARRAY_A ),
			'user_meta'     => $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE %s ORDER BY meta_key", $ids['user'], 'nh\\_%' ), ARRAY_A ),
			'posts'         => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts}" ),
			'terms'         => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->terms}" ),
			'taxonomy_rows' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'topic_cluster'" ),
			'relationships' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->term_relationships}" ),
			'users'         => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" ),
		);
	}

	public function test_the_plugin_options_are_removed_and_other_options_are_kept() {
		foreach ( array( 'nh_seo_business', 'nh_seo_settings', 'nh_seo_search_state', 'nh_seo_db_version', 'nh_seo_something_new' ) as $name ) {
			update_option( $name, array( 'x' => 1 ) );
		}
		foreach ( array( 'nh_editorial_cache_version', 'nh_seo', 'nhseo_other', 'my_nh_seo_option', 'blogdescription_nh_seo_' ) as $name ) {
			update_option( $name, 'keep' );
		}
		$description = get_option( 'blogdescription' );

		NH_SEO_Uninstall::remove_site_data();

		foreach ( array( 'nh_seo_business', 'nh_seo_settings', 'nh_seo_search_state', 'nh_seo_db_version', 'nh_seo_something_new' ) as $name ) {
			$this->assertFalse( get_option( $name ), $name . ' is removed' );
			$this->assertFalse( wp_cache_get( $name, 'options' ), $name . ' is gone from the cache too' );
		}
		foreach ( array( 'nh_editorial_cache_version', 'nh_seo', 'nhseo_other', 'my_nh_seo_option', 'blogdescription_nh_seo_' ) as $name ) {
			$this->assertSame( 'keep', get_option( $name ), $name . ' belongs to someone else' );
		}
		$this->assertSame( $description, get_option( 'blogdescription' ) );
	}

	public function test_a_site_that_never_configured_the_plugin_is_fine() {
		delete_option( 'nh_seo_business' );
		delete_option( 'nh_seo_settings' );

		NH_SEO_Uninstall::remove_site_data();

		$this->assertSame( 0, $this->plugin_option_rows() );
	}

	public function test_transients_and_their_timeouts_are_removed_but_not_other_transients() {
		set_transient( 'nh_seo_search_disabled', 1, 600 );
		set_transient( 'nh_seo_notices_7', array( 'x' ), 300 );
		set_site_transient( 'nh_seo_site_flag', 1, 600 );
		set_site_transient( 'other_plugin_site_cache', 'keep', 600 );
		set_transient( 'other_plugin_cache', 'keep', 600 );
		$this->assertGreaterThan( 0, $this->plugin_option_rows() );

		NH_SEO_Uninstall::remove_everywhere(); // Also clears the network table on multisite, where site transients live.

		$this->assertFalse( get_transient( 'nh_seo_search_disabled' ) );
		$this->assertFalse( get_transient( 'nh_seo_notices_7' ) );
		$this->assertFalse( get_site_transient( 'nh_seo_site_flag' ) );
		$this->assertSame( 0, $this->plugin_option_rows(), 'no value row and no timeout row is left behind' );
		$this->assertSame( 'keep', get_transient( 'other_plugin_cache' ) );
		$this->assertSame( 'keep', get_site_transient( 'other_plugin_site_cache' ) );
	}

	public function test_network_options_with_the_plugin_prefix_are_removed_on_a_network() {
		$this->skipWithoutMultisite();
		update_site_option( 'nh_seo_network_flag', 1 );
		update_site_option( 'other_network_option', 'keep' );

		NH_SEO_Uninstall::remove_everywhere();

		$this->assertFalse( get_site_option( 'nh_seo_network_flag' ) );
		$this->assertSame( 'keep', get_site_option( 'other_network_option' ) );
	}

	public function test_orphan_transient_timeouts_are_removed_too() {
		update_option( '_transient_timeout_nh_seo_orphan', time() + 600, false );

		NH_SEO_Uninstall::remove_site_data();

		$this->assertSame( 0, $this->plugin_option_rows() );
	}

	public function test_scheduled_events_of_the_plugin_are_removed_whatever_their_arguments() {
		wp_schedule_event( time() + 3600, 'hourly', 'nh_seo_reindex_tick' );
		wp_schedule_single_event( time() + 60, 'nh_seo_reindex_batch', array( 1, 200 ) );
		wp_schedule_single_event( time() + 120, 'nh_seo_reindex_batch', array( 201, 400 ) );
		wp_schedule_single_event( time() + 90, 'other_plugin_event' );
		wp_schedule_single_event( time() + 95, 'my_nh_seo_not_ours' );

		NH_SEO_Uninstall::remove_site_data();

		$this->assertFalse( wp_next_scheduled( 'nh_seo_reindex_tick' ) );
		$this->assertFalse( wp_next_scheduled( 'nh_seo_reindex_batch', array( 1, 200 ) ) );
		$this->assertFalse( wp_next_scheduled( 'nh_seo_reindex_batch', array( 201, 400 ) ) );
		$this->assertNotFalse( wp_next_scheduled( 'other_plugin_event' ) );
		$this->assertNotFalse( wp_next_scheduled( 'my_nh_seo_not_ours' ) );
	}

	public function test_the_search_index_table_is_dropped() {
		global $wpdb;
		$original = $wpdb->prefix;
		$table    = self::TEST_PREFIX . 'nh_search_index';
		// The test case turns CREATE TABLE into a temporary table that vanishes with the connection.
		$wpdb->query( "CREATE TABLE {$table} ( post_id BIGINT UNSIGNED NOT NULL PRIMARY KEY )" );
		$this->assertTrue( $this->table_exists( $table ) );

		$wpdb->prefix = self::TEST_PREFIX;
		try {
			NH_SEO_Uninstall::remove_site_data();
		} finally {
			$wpdb->prefix = $original;
		}

		$this->assertFalse( $this->table_exists( $table ) );
		$this->assertTrue( $this->table_exists( $wpdb->posts ), 'only the search index table is dropped' );
	}

	public function test_a_missing_search_table_is_not_an_error() {
		global $wpdb;
		$original     = $wpdb->prefix;
		$wpdb->prefix = self::TEST_PREFIX;
		try {
			NH_SEO_Uninstall::remove_site_data();
			NH_SEO_Uninstall::remove_site_data();
		} finally {
			$wpdb->prefix = $original;
		}

		$this->assertSame( '', $wpdb->last_error );
	}

	public function test_post_term_and_user_meta_terms_and_content_survive() {
		$ids = $this->editorial_data();
		update_option( 'nh_seo_business', array( 'name' => 'Example Co' ) );
		update_option( 'nh_seo_settings', array( 'schema_mode' => 'full' ) );
		$before = $this->fingerprint( $ids );
		$this->assertNotEmpty( $before['post_meta'] );
		$this->assertNotEmpty( $before['term_meta'] );
		$this->assertCount( 2, $before['user_meta'] );

		NH_SEO_Uninstall::remove_site_data();

		$this->assertSame( $before, $this->fingerprint( $ids ), 'not a single row of editorial data may change' );
		$this->assertFalse( get_option( 'nh_seo_business' ) );
		$this->assertSame( 'A summary', get_post_meta( $ids['post'], 'nh_summary', true ) );
		$this->assertSame( 'but it is post meta', get_post_meta( $ids['post'], 'nh_seo_looks_like_an_option_key', true ), 'only options are matched by prefix' );
		$this->assertSame( (string) $ids['pillar'], get_term_meta( $ids['cluster'], 'nh_pillar_id', true ) );
		$this->assertSame( 'Guide', get_user_meta( $ids['user'], 'nh_job_title', true ) );
		$this->assertSame( 'publish', get_post_status( $ids['post'] ) );
		$this->assertSame( array( $ids['cluster'] ), wp_get_object_terms( $ids['post'], 'topic_cluster', array( 'fields' => 'ids' ) ) );
	}

	public function test_running_it_twice_changes_nothing_more() {
		$ids = $this->editorial_data();
		update_option( 'nh_seo_settings', array( 'schema_mode' => 'full' ) );
		NH_SEO_Uninstall::remove_site_data();
		$after_first = $this->fingerprint( $ids );

		NH_SEO_Uninstall::remove_site_data();

		$this->assertSame( $after_first, $this->fingerprint( $ids ) );
		$this->assertSame( 0, $this->plugin_option_rows() );
	}

	public function test_run_refuses_to_do_anything_outside_a_wordpress_uninstall() {
		$this->assertFalse( defined( 'WP_UNINSTALL_PLUGIN' ), 'the test process is not uninstalling anything' );
		update_option( 'nh_seo_business', array( 'name' => 'Example Co' ) );
		set_transient( 'nh_seo_probe', 1, 600 );
		wp_schedule_single_event( time() + 60, 'nh_seo_probe_event' );

		$this->assertFalse( NH_SEO_Uninstall::run() );

		$this->assertSame( array( 'name' => 'Example Co' ), get_option( 'nh_seo_business' ) );
		$this->assertSame( 1, get_transient( 'nh_seo_probe' ) );
		$this->assertNotFalse( wp_next_scheduled( 'nh_seo_probe_event' ) );
	}

	public function test_every_site_of_a_network_is_cleaned() {
		$this->skipWithoutMultisite();

		$second = self::factory()->blog->create();
		update_option( 'nh_seo_settings', array( 'schema_mode' => 'off' ) );
		switch_to_blog( $second );
		update_option( 'nh_seo_settings', array( 'schema_mode' => 'full' ) );
		update_option( 'nh_editorial_cache_version', 'keep' );
		restore_current_blog();

		NH_SEO_Uninstall::remove_everywhere();

		$this->assertFalse( get_option( 'nh_seo_settings' ) );
		switch_to_blog( $second );
		$this->assertFalse( get_option( 'nh_seo_settings' ) );
		$this->assertSame( 'keep', get_option( 'nh_editorial_cache_version' ) );
		restore_current_blog();
	}

	public function test_a_single_site_install_is_cleaned_by_remove_everywhere() {
		$this->skipWithMultisite();
		update_option( 'nh_seo_settings', array( 'schema_mode' => 'off' ) );

		NH_SEO_Uninstall::remove_everywhere();

		$this->assertFalse( get_option( 'nh_seo_settings' ) );
	}

	public function test_uninstall_php_stops_on_a_direct_request() {
		if ( ! function_exists( 'shell_exec' ) ) {
			$this->markTestSkipped( 'shell_exec() is not available.' );
		}
		$file = NH_SEO_DIR . 'uninstall.php';

		// Requested directly, as a visitor could: neither ABSPATH nor WP_UNINSTALL_PLUGIN exist.
		$bare = shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $file ) . ' 2>&1' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec -- Runs the file the way a direct request would.
		$this->assertSame( '', trim( (string) $bare ) );

		// Loaded by something that defines ABSPATH but is not an uninstall: it must still stop before touching anything.
		$code   = "define( 'ABSPATH', '/nowhere/' ); require " . var_export( $file, true ) . "; echo 'REACHED';";
		$fenced = shell_exec( escapeshellarg( PHP_BINARY ) . ' -r ' . escapeshellarg( $code ) . ' 2>&1' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec -- Simulates a stray include.
		$this->assertSame( '', trim( (string) $fenced ), 'the file exits before anything else runs' );
	}

	public function test_uninstall_php_hands_over_to_the_guarded_routine() {
		$source = file_get_contents( NH_SEO_DIR . 'uninstall.php' );

		$this->assertStringContainsString( "defined( 'WP_UNINSTALL_PLUGIN' )", $source );
		$this->assertStringContainsString( 'NH_SEO_Uninstall::run();', $source );
		$this->assertMatchesRegularExpression( '/exit;/', $source );
		$this->assertLessThan( strpos( $source, 'require_once' ), strpos( $source, 'WP_UNINSTALL_PLUGIN' ), 'the guard comes before anything is loaded' );
	}

	public function test_the_uninstall_code_cannot_touch_editorial_data() {
		$source = file_get_contents( NH_SEO_DIR . 'includes/class-nh-seo-uninstall.php' ) . file_get_contents( NH_SEO_DIR . 'uninstall.php' );
		// Strip comments so that the explanation of what is NOT done does not trip the check.
		$source = preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', $source );

		foreach ( array(
			'delete_post_meta',
			'delete_term_meta',
			'delete_user_meta',
			'delete_metadata',
			'wp_delete_post',
			'wp_delete_term',
			'wp_delete_user',
			'wp_delete_object_term_relationships',
			'wp_remove_object_terms',
			'wp_update_post',
			'wp_insert_post',
			'wp_trash_post',
			'->postmeta',
			'->termmeta',
			'->usermeta',
			'->posts',
			'->terms',
			'->term_taxonomy',
			'->term_relationships',
			'->users',
			'TRUNCATE',
			'unregister_taxonomy',
		) as $needle ) {
			$this->assertStringNotContainsString( $needle, $source, "uninstall must not use $needle" );
		}
		$this->assertSame( 1, preg_match_all( '/DROP TABLE/', $source ), 'exactly one table is dropped' );
		$this->assertStringContainsString( 'SEARCH_TABLE', $source );
	}
}
