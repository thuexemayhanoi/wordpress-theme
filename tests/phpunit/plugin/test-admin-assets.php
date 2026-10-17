<?php
/**
 * Tests for the admin asset loader and the admin notice queue.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * NH_SEO_Admin_Assets and NH_SEO_Admin_Notices.
 */
class NH_Test_Admin_Assets extends NH_Unit_Test_Case {

	public function set_up() {
		parent::set_up();
		$this->reset_queues();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function tear_down() {
		$this->reset_queues();
		parent::tear_down();
	}

	/**
	 * Start from empty script and style queues.
	 *
	 * @return void
	 */
	private function reset_queues() {
		$GLOBALS['wp_scripts'] = new WP_Scripts(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test isolation.
		$GLOBALS['wp_styles']  = new WP_Styles(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test isolation.
	}

	/**
	 * A screen object of our own (the registry keeps the real ones, so never change those).
	 *
	 * @param string $base      Screen base.
	 * @param string $post_type Post type of the screen.
	 * @param string $taxonomy  Taxonomy of the screen.
	 * @return WP_Screen
	 */
	private function screen( $base, $post_type = '', $taxonomy = '' ) {
		$screen            = WP_Screen::get( 'nh-test-' . wp_generate_password( 10, false ) );
		$screen->base      = $base;
		$screen->post_type = $post_type;
		$screen->taxonomy  = $taxonomy;
		return $screen;
	}

	public function test_screens_with_something_to_show_are_recognized() {
		$cases = array(
			'post editor'              => array( $this->screen( 'post', 'post' ), '', 'post' ),
			'page editor'              => array( $this->screen( 'post', 'page' ), '', 'post' ),
			'category list'            => array( $this->screen( 'edit-tags', 'post', 'category' ), '', 'term' ),
			'category editor'          => array( $this->screen( 'term', 'post', 'category' ), '', 'term' ),
			'cluster list'             => array( $this->screen( 'edit-tags', 'post', 'topic_cluster' ), '', 'term' ),
			'cluster editor'           => array( $this->screen( 'term', 'post', 'topic_cluster' ), '', 'term' ),
			'own profile'              => array( $this->screen( 'profile' ), '', 'user' ),
			'other profile'            => array( $this->screen( 'user-edit' ), '', 'user' ),
			'settings page'            => array( $this->screen( 'settings_page_nguyen-ha-seo-companion' ), 'settings_page_nguyen-ha-seo-companion', 'settings' ),
			'settings page, no screen' => array( null, 'settings_page_nguyen-ha-seo-companion', 'settings' ),
		);
		foreach ( $cases as $label => $case ) {
			$this->assertSame( $case[2], NH_SEO_Admin_Assets::context( $case[0], $case[1] ), $label );
		}
	}

	public function test_all_other_screens_are_ignored() {
		$cases = array(
			'dashboard'       => $this->screen( 'dashboard' ),
			'post list'       => $this->screen( 'edit', 'post' ),
			'plugins'         => $this->screen( 'plugins' ),
			'media'           => $this->screen( 'upload' ),
			'tag list'        => $this->screen( 'edit-tags', 'post', 'post_tag' ),
			'tag editor'      => $this->screen( 'term', 'post', 'post_tag' ),
			'custom type'     => $this->screen( 'post', 'nh_test_book' ),
			'attachment'      => $this->screen( 'post', 'attachment' ),
			'menus'           => $this->screen( 'nav-menus' ),
			'general options' => $this->screen( 'options-general' ),
			'user list'       => $this->screen( 'users' ),
			'new user'        => $this->screen( 'user' ),
		);
		foreach ( $cases as $label => $screen ) {
			$this->assertSame( '', NH_SEO_Admin_Assets::context( $screen, 'x.php' ), $label );
		}
		$this->assertSame( '', NH_SEO_Admin_Assets::context( null, 'index.php' ) );
		$this->assertSame( '', NH_SEO_Admin_Assets::context( null, 'settings_page_another-plugin' ) );
		$this->assertSame( '', NH_SEO_Admin_Assets::context( 'post', '' ), 'a non-screen is not a screen' );
	}

	public function test_assets_are_enqueued_on_the_plugin_screens() {
		foreach ( array(
			array( $this->screen( 'post', 'post' ), 'post.php' ),
			array( $this->screen( 'edit-tags', 'post', 'category' ), 'edit-tags.php' ),
			array( $this->screen( 'profile' ), 'profile.php' ),
			array( $this->screen( 'settings_page_nguyen-ha-seo-companion' ), 'settings_page_nguyen-ha-seo-companion' ),
		) as $case ) {
			$this->reset_queues();
			set_current_screen( $case[0] );

			NH_SEO_Admin_Assets::enqueue( $case[1] );

			$this->assertTrue( wp_style_is( 'nh-seo-admin', 'enqueued' ), $case[1] );
			$this->assertTrue( wp_script_is( 'nh-seo-admin', 'enqueued' ), $case[1] );
		}
	}

	public function test_nothing_is_enqueued_elsewhere() {
		foreach ( array(
			array( $this->screen( 'dashboard' ), 'index.php' ),
			array( $this->screen( 'edit', 'post' ), 'edit.php' ),
			array( $this->screen( 'edit-tags', 'post', 'post_tag' ), 'edit-tags.php' ),
			array( $this->screen( 'post', 'nh_test_book' ), 'post.php' ),
			array( $this->screen( 'plugins' ), 'plugins.php' ),
		) as $case ) {
			$this->reset_queues();
			set_current_screen( $case[0] );

			NH_SEO_Admin_Assets::enqueue( $case[1] );

			$this->assertFalse( wp_style_is( 'nh-seo-admin', 'enqueued' ), $case[1] );
			$this->assertFalse( wp_script_is( 'nh-seo-admin', 'enqueued' ), $case[1] );
		}
	}

	public function test_the_script_has_no_dependencies_and_loads_in_the_footer() {
		set_current_screen( $this->screen( 'post', 'post' ) );
		NH_SEO_Admin_Assets::enqueue( 'post.php' );

		$script = wp_scripts()->registered['nh-seo-admin'];
		$this->assertSame( array(), $script->deps, 'the admin script must stay dependency-free' );
		$this->assertSame( 1, wp_scripts()->get_data( 'nh-seo-admin', 'group' ), 'loaded in the footer' );
		$this->assertStringEndsWith( 'assets/admin.js', $script->src );
		$this->assertSame( NH_SEO_VERSION, $script->ver );

		$style = wp_styles()->registered['nh-seo-admin'];
		$this->assertSame( array(), $style->deps );
		$this->assertStringEndsWith( 'assets/admin.css', $style->src );
	}

	public function test_the_script_receives_translated_strings_and_the_context() {
		set_current_screen( $this->screen( 'post', 'page' ) );
		NH_SEO_Admin_Assets::enqueue( 'post.php' );

		$data = wp_scripts()->get_data( 'nh-seo-admin', 'data' );
		$this->assertStringContainsString( 'var nhSeoAdmin = ', $data );
		$this->assertStringContainsString( '"context":"post"', $data );
		foreach ( array( 'selectLogo', 'useLogo', 'itemAdded', 'itemRemoved', 'itemMoved', 'limitReached' ) as $key ) {
			$this->assertStringContainsString( '"' . $key . '":"', $data, $key );
		}
	}

	public function test_every_string_handed_to_the_script_is_used_by_the_script() {
		set_current_screen( $this->screen( 'post', 'page' ) );
		NH_SEO_Admin_Assets::enqueue( 'post.php' );

		$data = wp_scripts()->get_data( 'nh-seo-admin', 'data' );
		$js   = file_get_contents( NH_SEO_DIR . 'assets/admin.js' );
		$this->assertNotFalse( $js );

		preg_match( '/"i18n":\{(.*?)\}/s', $data, $match );
		$this->assertNotEmpty( $match, 'the localized data has an i18n object' );
		preg_match_all( '/"([A-Za-z]+)":"/', $match[1], $keys );
		$this->assertNotEmpty( $keys[1] );
		foreach ( $keys[1] as $key ) {
			// A boolean keeps the failure message short instead of dumping the whole script.
			$used = 1 === preg_match( '/\bi18n\.' . preg_quote( $key, '/' ) . '\b/', $js );
			$this->assertTrue( $used, "admin.js never reads the string \"$key\"" );
		}
	}

	public function test_the_settings_hook_constant_matches_the_page_slug() {
		$this->assertSame( 'settings_page_' . NH_SEO_Settings_Page::SLUG, NH_SEO_Admin_Assets::SETTINGS_HOOK );
	}

	public function test_the_media_library_is_only_loaded_on_the_settings_page() {
		set_current_screen( $this->screen( 'post', 'post' ) );
		NH_SEO_Admin_Assets::enqueue( 'post.php' );
		$this->assertFalse( wp_style_is( 'media-views', 'enqueued' ), 'the editors load their own media scripts' );

		$this->reset_queues();
		set_current_screen( $this->screen( 'profile' ) );
		NH_SEO_Admin_Assets::enqueue( 'profile.php' );
		$this->assertFalse( wp_style_is( 'media-views', 'enqueued' ) );

		$this->reset_queues();
		set_current_screen( $this->screen( 'settings_page_nguyen-ha-seo-companion' ) );
		NH_SEO_Admin_Assets::enqueue( 'settings_page_nguyen-ha-seo-companion' );
		$this->assertTrue( wp_style_is( 'media-views', 'enqueued' ), 'the logo picker needs the media library' );
	}

	public function test_the_enqueue_hook_is_registered() {
		$this->assertIsInt( has_action( 'admin_enqueue_scripts', array( 'NH_SEO_Admin_Assets', 'enqueue' ) ) );
	}

	public function test_nothing_is_enqueued_when_there_is_no_screen() {
		$GLOBALS['current_screen'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test isolation.

		NH_SEO_Admin_Assets::enqueue( 'index.php' );

		$this->assertFalse( wp_script_is( 'nh-seo-admin', 'enqueued' ) );
	}

	/**
	 * The queued notices of a user.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	private function queue( $user_id ) {
		$queue = get_transient( NH_SEO_Admin_Notices::TRANSIENT_PREFIX . $user_id );
		return is_array( $queue ) ? $queue : array();
	}

	/**
	 * Print the notices on a screen.
	 *
	 * @param WP_Screen $screen Screen to show them on.
	 * @return string
	 */
	private function show_notices( $screen ) {
		set_current_screen( $screen );
		ob_start();
		NH_SEO_Admin_Notices::render();
		return ob_get_clean();
	}

	public function test_a_queued_notice_is_shown_once_on_a_plugin_screen() {
		NH_SEO_Admin_Notices::add( 'The Pillar page ID "x" was ignored.', 'warning' );

		$html = $this->show_notices( $this->screen( 'post', 'post' ) );

		$this->assertStringContainsString( '<div class="notice notice-warning is-dismissible"><p>The Pillar page ID &quot;x&quot; was ignored.</p></div>', $html );
		$this->assertSame( array(), $this->queue( get_current_user_id() ), 'the queue is cleared once shown' );
		$this->assertSame( '', $this->show_notices( $this->screen( 'post', 'post' ) ), 'and shown only once' );
	}

	public function test_notices_wait_for_a_plugin_screen() {
		NH_SEO_Admin_Notices::add( 'Wait for it', 'info' );

		$this->assertSame( '', $this->show_notices( $this->screen( 'dashboard' ) ) );
		$this->assertCount( 1, $this->queue( get_current_user_id() ), 'other screens leave the queue alone' );

		$this->assertStringContainsString( 'Wait for it', $this->show_notices( $this->screen( 'edit-tags', 'post', 'category' ) ) );
	}

	public function test_notice_text_is_plain_and_escaped() {
		NH_SEO_Admin_Notices::add( '<script>alert(1)</script>Value <b>bold</b> & "quotes"', 'error' );

		$html = $this->show_notices( $this->screen( 'profile' ) );

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( '<b>', $html );
		$this->assertStringContainsString( 'notice-error', $html );
		$this->assertStringContainsString( '&amp; &quot;quotes&quot;', $html );
	}

	public function test_unknown_types_become_warnings_and_empty_messages_are_ignored() {
		NH_SEO_Admin_Notices::add( 'Odd type', 'evil" onclick="x' );
		NH_SEO_Admin_Notices::add( '', 'info' );
		NH_SEO_Admin_Notices::add( '   ', 'info' );
		NH_SEO_Admin_Notices::add( '<b></b>', 'info' );

		$html = $this->show_notices( $this->screen( 'profile' ) );

		$this->assertSame( 1, substr_count( $html, 'class="notice' ) );
		$this->assertStringContainsString( 'notice-warning', $html );
		$this->assertStringNotContainsString( 'onclick', $html );
	}

	public function test_every_type_is_supported() {
		foreach ( array( 'error', 'warning', 'success', 'info' ) as $type ) {
			NH_SEO_Admin_Notices::add( "A $type", $type );
		}

		$html = $this->show_notices( $this->screen( 'profile' ) );

		foreach ( array( 'error', 'warning', 'success', 'info' ) as $type ) {
			$this->assertStringContainsString( "notice-$type", $html );
		}
	}

	public function test_the_queue_is_per_user_and_bounded() {
		$first = get_current_user_id();
		NH_SEO_Admin_Notices::add( 'For the first user' );
		$second = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $second );
		NH_SEO_Admin_Notices::add( 'For the second user' );

		$this->assertCount( 1, $this->queue( $first ) );
		$this->assertCount( 1, $this->queue( $second ) );
		$this->assertStringNotContainsString( 'first user', $this->show_notices( $this->screen( 'profile' ) ) );

		for ( $i = 0; $i < 25; $i++ ) {
			NH_SEO_Admin_Notices::add( "Notice $i" );
		}
		$queue = $this->queue( $second );
		$this->assertCount( 10, $queue );
		$this->assertSame( 'Notice 24', end( $queue )['message'], 'the newest notices are kept' );
	}

	public function test_visitors_have_no_queue() {
		wp_set_current_user( 0 );

		NH_SEO_Admin_Notices::add( 'Nobody to tell' );

		$this->assertFalse( get_transient( NH_SEO_Admin_Notices::TRANSIENT_PREFIX . '0' ) );
		$this->assertSame( '', $this->show_notices( $this->screen( 'profile' ) ) );
	}

	public function test_the_notice_hook_is_registered() {
		$this->assertIsInt( has_action( 'admin_notices', array( 'NH_SEO_Admin_Notices', 'render' ) ) );
	}
}
