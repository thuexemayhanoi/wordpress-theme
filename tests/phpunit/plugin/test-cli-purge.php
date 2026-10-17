<?php
/**
 * WP-CLI command `purge`, exercised as plain PHP without WP-CLI.
 *
 * WP-CLI is NOT loaded and no real cache plugin runs: the command talks to a recording output double and the
 * purge is observed through the generic nh_seo_purge_urls action.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

// Cache fixtures and the output double.
require_once __DIR__ . '/class-nh-cache-test-case.php';
require_once __DIR__ . '/class-nh-cli-test-output.php';

/**
 * Purge command tests.
 */
class NH_Test_CLI_Purge extends NH_Cache_Test_Case {

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
	 * Run the command and return the message it halted with, or '' when it finished.
	 *
	 * @param array $assoc_args Options.
	 * @return string
	 */
	private function purge_halting( array $assoc_args ) {
		try {
			$this->command->purge( array(), $assoc_args );
		} catch ( RuntimeException $e ) {
			return $e->getMessage();
		}
		return '';
	}

	public function test_a_post_id_is_required_and_must_be_valid() {
		$this->assertSame( 'Specify the post with --post=<id>.', $this->purge_halting( array() ) );
		$this->assertSame( 'Specify the post with --post=<id>.', $this->purge_halting( array( 'post' => '' ) ) );
		$this->assertSame( 'Specify the post with --post=<id>.', $this->purge_halting( array( 'post' => '  ' ) ) );
		$this->assertSame( 'Specify the post with --post=<id>.', $this->purge_halting( array( 'post' => array( '1' ) ) ) );
		$this->assertSame( 'Specify the post with --post=<id>.', $this->purge_halting( array( 'post' => true ) ), 'WP-CLI passes true for a flag without a value: that must not be read as post 1.' );

		foreach ( array( 'abc', '0', 0, '-1', '12abc', '1.5', "1'; DROP TABLE x" ) as $bad ) {
			$this->assertStringContainsString( '--post must be a post ID', $this->purge_halting( array( 'post' => $bad ) ), (string) $bad );
		}
		$this->assertSame( 'Post 99999999 does not exist.', $this->purge_halting( array( 'post' => '99999999' ) ) );
	}

	public function test_a_published_post_is_purged_through_every_cache_integration_once() {
		$spy     = $this->spy_on_purge_action();
		$post_id = $this->make_post( array( 'tags_input' => array( 'Hanoi' ) ) );
		NH_SEO_Cache::reset();
		$expected = NH_SEO_Cache::urls_for_post( $post_id );
		$this->assertContains( get_permalink( $post_id ), $expected );

		$this->command->purge( array(), array( 'post' => (string) $post_id ) );

		$this->assertCount( 1, $spy, 'One hand-over.' );
		$this->assertSameUrls( $expected, $spy[0] );
		$this->assertSame( $expected, array_map( 'trim', $this->out->messages( 'log' ) ), 'Every URL is listed.' );
		$this->assertSame( array( sprintf( 'Handed %d URLs to the page caches.', count( $expected ) ) ), $this->out->messages( 'success' ) );
		$this->assertSame( array(), $this->out->messages( 'warning' ) );
	}

	public function test_dry_run_lists_the_urls_and_purges_nothing() {
		$spy     = $this->spy_on_purge_action();
		$post_id = $this->make_post();

		$this->command->purge(
			array(),
			array(
				'post'    => (string) $post_id,
				'dry-run' => true,
			)
		);

		$this->assertCount( 0, $spy );
		$this->assertNotEmpty( $this->out->messages( 'log' ) );
		$this->assertStringContainsString( 'Dry run:', $this->out->messages( 'success' )[0] );
		$this->assertStringContainsString( 'would be purged', $this->out->messages( 'success' )[0] );
	}

	public function test_an_unpublished_post_warns_and_leaves_its_own_address_out() {
		$spy     = $this->spy_on_purge_action();
		$post_id = $this->make_post( array( 'post_status' => 'draft' ) );

		$this->command->purge( array(), array( 'post' => (string) $post_id ) );

		$this->assertStringContainsString( 'is "draft", not published', $this->out->messages( 'warning' )[0] );
		$this->assertCount( 1, $spy );
		$this->assertNotContains( get_permalink( $post_id ), $spy[0] );
		$this->assertContains( home_url( '/' ), $spy[0] );
	}

	public function test_an_explicit_purge_ignores_the_automatic_purge_setting_but_says_so() {
		update_option( NH_SEO_Keys::OPTION_SETTINGS, array( 'purge_caches' => 0 ) );
		$spy     = $this->spy_on_purge_action();
		$post_id = $this->make_post();

		$this->command->purge( array(), array( 'post' => (string) $post_id ) );

		$this->assertCount( 1, $spy, 'The administrator asked for it.' );
		$this->assertStringContainsString( 'Automatic purging is switched off', $this->out->messages( 'warning' )[0] );
	}

	public function test_a_post_with_nothing_to_purge_says_so() {
		$spy     = $this->spy_on_purge_action();
		$post_id = $this->make_post();
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => 'A changed body, so that WordPress keeps a revision.',
			)
		);
		$revisions = array_keys( wp_get_post_revisions( $post_id ) );
		$this->assertNotEmpty( $revisions, 'The fixture needs a revision.' );

		register_post_type( 'nh_cli_hidden', array( 'public' => false ) );
		$hidden = self::factory()->post->create( array( 'post_type' => 'nh_cli_hidden' ) );
		$draft  = self::factory()->post->create( array( 'post_status' => 'auto-draft' ) );

		foreach ( array( $revisions[0], $hidden, $draft ) as $id ) {
			$out = new NH_CLI_Test_Output();
			( new NH_SEO_CLI_Command( $out ) )->purge( array(), array( 'post' => (string) $id ) );
			$this->assertSame( array( 'Nothing to purge for this post.' ), $out->messages( 'warning' ), get_post_type( $id ) . ' / ' . get_post_status( $id ) );
			$this->assertSame( array(), $out->messages( 'success' ) );
			$this->assertSame( array(), $out->messages( 'log' ) );
		}
		$this->assertCount( 0, $spy );
	}

	public function test_the_command_makes_no_remote_request() {
		$this->spy_on_purge_action();
		$post_id = $this->make_post();
		$this->command->purge( array(), array( 'post' => (string) $post_id ) );
		$this->assertSame( 0, $this->http_attempts );
	}
}
