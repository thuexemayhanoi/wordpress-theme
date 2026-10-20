<?php
/**
 * Regressions for authenticated once-per-release LiteSpeed cache invalidation.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

/**
 * A Git theme deploy must clear stale EN hero HTML without exposing a public
 * purge endpoint or continuously flushing the cache on anonymous page views.
 */
class NH_Test_Release_Litespeed_Cache extends NH_Unit_Test_Case {

	/** A trusted administrator purges once per theme version, never on each request. */
	public function test_admin_purges_once_when_litespeed_is_active() {
		$original_user = get_current_user_id();
		$admin         = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$calls         = 0;
		$reason        = '';
		$callback      = static function ( $message ) use ( &$calls, &$reason ) {
			++$calls;
			$reason = (string) $message;
		};

		add_action( 'litespeed_purge_all', $callback, 10, 1 );
		delete_option( 'nh_editorial_litespeed_purged_version' );
		wp_set_current_user( $admin );

		nh_editorial_purge_litespeed_after_release();
		$this->assertSame( 1, $calls );
		$this->assertSame( NH_EDITORIAL_VERSION, get_option( 'nh_editorial_litespeed_purged_version' ) );
		$this->assertStringContainsString( NH_EDITORIAL_VERSION, $reason );

		nh_editorial_purge_litespeed_after_release();
		$this->assertSame( 1, $calls, 'The same release must never purge twice.' );

		remove_action( 'litespeed_purge_all', $callback, 10 );
		delete_option( 'nh_editorial_litespeed_purged_version' );
		wp_set_current_user( $original_user );
	}

	/** Anonymous and unprivileged requests cannot trigger full cache purges. */
	public function test_anonymous_and_subscriber_cannot_purge() {
		$original_user = get_current_user_id();
		$subscriber    = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$calls         = 0;
		$callback      = static function () use ( &$calls ) {
			++$calls;
		};

		add_action( 'litespeed_purge_all', $callback );
		delete_option( 'nh_editorial_litespeed_purged_version' );

		foreach ( array( 0, $subscriber ) as $user_id ) {
			wp_set_current_user( $user_id );
			nh_editorial_purge_litespeed_after_release();
		}

		$this->assertSame( 0, $calls );
		$this->assertFalse( get_option( 'nh_editorial_litespeed_purged_version' ) );

		remove_action( 'litespeed_purge_all', $callback );
		wp_set_current_user( $original_user );
	}

	/** A site without LiteSpeed plugin is unaffected. */
	public function test_absent_cache_plugin_never_writes_release_marker() {
		$original_user = get_current_user_id();
		$admin         = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		delete_option( 'nh_editorial_litespeed_purged_version' );

		if ( ! has_action( 'litespeed_purge_all' ) ) {
			nh_editorial_purge_litespeed_after_release();
			$this->assertFalse( get_option( 'nh_editorial_litespeed_purged_version' ) );
		}

		wp_set_current_user( $original_user );
	}
}
