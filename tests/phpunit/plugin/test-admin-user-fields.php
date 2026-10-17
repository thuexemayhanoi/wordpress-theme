<?php
/**
 * Tests for the author fields on the user profile screens.
 *
 * @package nguyen-ha-seo-companion
 */

/**
 * NH_SEO_User_Fields.
 */
class NH_Test_Admin_User_Fields extends NH_Unit_Test_Case {

	/**
	 * User IDs by role.
	 *
	 * @var int[]
	 */
	private $users = array();

	public function set_up() {
		parent::set_up();
		foreach ( array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' ) as $role ) {
			$this->users[ $role ] = self::factory()->user->create( array( 'role' => $role ) );
		}
		wp_set_current_user( $this->users['administrator'] );
	}

	/**
	 * Simulate a profile form submission and run the save handler.
	 *
	 * @param int         $user_id Edited user.
	 * @param array       $fields  Submitted fields.
	 * @param string|bool $nonce   Nonce value, null for a valid one, false to omit.
	 * @return void
	 */
	private function submit( $user_id, array $fields, $nonce = null ) {
		$post = $fields;
		if ( null === $nonce ) {
			$post['nh_seo_user_nonce'] = wp_create_nonce( NH_SEO_User_Fields::nonce_action( $user_id ) );
		} elseif ( false !== $nonce ) {
			$post['nh_seo_user_nonce'] = $nonce;
		}
		$_POST = wp_slash( $post );
		NH_SEO_User_Fields::save( $user_id );
	}

	/**
	 * Print the fields for a user.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private function render( $user_id ) {
		ob_start();
		NH_SEO_User_Fields::render( new WP_User( $user_id ) );
		return ob_get_clean();
	}

	public function test_the_fields_are_hooked_to_both_profile_screens_and_both_save_hooks() {
		$this->assertIsInt( has_action( 'show_user_profile', array( 'NH_SEO_User_Fields', 'render' ) ) );
		$this->assertIsInt( has_action( 'edit_user_profile', array( 'NH_SEO_User_Fields', 'render' ) ) );
		$this->assertIsInt( has_action( 'personal_options_update', array( 'NH_SEO_User_Fields', 'save' ) ) );
		$this->assertIsInt( has_action( 'edit_user_profile_update', array( 'NH_SEO_User_Fields', 'save' ) ) );
	}

	public function test_only_users_who_can_write_posts_get_the_fields() {
		foreach ( array( 'administrator', 'editor', 'author', 'contributor' ) as $role ) {
			$html = $this->render( $this->users[ $role ] );
			$this->assertStringContainsString( 'name="nh_seo_job_title"', $html, $role );
			$this->assertStringContainsString( 'name="nh_seo_sameas"', $html, $role );
			$this->assertStringContainsString( 'name="nh_seo_user_nonce"', $html, $role );
		}
		$this->assertSame( '', $this->render( $this->users['subscriber'] ) );

		ob_start();
		NH_SEO_User_Fields::render( null );
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_the_nonce_is_bound_to_the_edited_user() {
		$html = $this->render( $this->users['author'] );

		$this->assertSame( 1, preg_match( '/name="nh_seo_user_nonce" value="([^"]+)"/', $html, $match ) );
		$this->assertNotFalse( wp_verify_nonce( $match[1], NH_SEO_User_Fields::nonce_action( $this->users['author'] ) ) );
		$this->assertFalse( wp_verify_nonce( $match[1], NH_SEO_User_Fields::nonce_action( $this->users['editor'] ) ) );
		$this->assertStringNotContainsString( '_wp_http_referer', $html );
	}

	public function test_stored_values_are_shown_escaped() {
		$author = $this->users['author'];
		update_user_meta( $author, 'nh_job_title', 'Guide "senior" & trainer' );
		update_user_meta( $author, 'nh_sameas', "https://example.org/a?x=1&y=2\nhttps://example.org/b" );

		$html = $this->render( $author );

		$this->assertStringContainsString( 'value="Guide &quot;senior&quot; &amp; trainer"', $html );
		$this->assertStringContainsString( "https://example.org/a?x=1&amp;y=2\nhttps://example.org/b</textarea>", $html );
	}

	public function test_hostile_stored_values_cannot_break_out() {
		$author = $this->users['author'];
		// Written behind the sanitizers' back.
		global $wpdb;
		$wpdb->insert(
			$wpdb->usermeta,
			array(
				'user_id'    => $author,
				'meta_key'   => 'nh_job_title',
				'meta_value' => '"><script>alert(1)</script>',
			)
		);
		$wpdb->insert(
			$wpdb->usermeta,
			array(
				'user_id'    => $author,
				'meta_key'   => 'nh_sameas',
				'meta_value' => '</textarea><script>alert(2)</script>',
			)
		);
		wp_cache_delete( $author, 'user_meta' );

		$html = $this->render( $author );

		$this->assertStringNotContainsString( '<script', $html );
	}

	public function test_a_valid_submission_is_saved_by_an_administrator() {
		$author = $this->users['author'];

		$this->submit(
			$author,
			array(
				'nh_seo_job_title' => '  Senior <b>guide</b> ',
				'nh_seo_sameas'    => "https://example.org/profile\nhttps://example.net/me",
			)
		);

		$this->assertSame( 'Senior guide', get_user_meta( $author, 'nh_job_title', true ) );
		$this->assertSame( "https://example.org/profile\nhttps://example.net/me", get_user_meta( $author, 'nh_sameas', true ) );
	}

	public function test_authors_can_edit_their_own_details() {
		$author = $this->users['author'];
		wp_set_current_user( $author );

		$this->submit( $author, array( 'nh_seo_job_title' => 'Writer' ) );

		$this->assertSame( 'Writer', get_user_meta( $author, 'nh_job_title', true ) );
	}

	public function test_vietnamese_text_is_kept() {
		$author = $this->users['author'];
		$this->submit( $author, array( 'nh_seo_job_title' => 'Hướng dẫn viên du lịch' ) );

		$this->assertSame( 'Hướng dẫn viên du lịch', get_user_meta( $author, 'nh_job_title', true ) );
		$this->assertStringContainsString( 'value="Hướng dẫn viên du lịch"', $this->render( $author ) );
	}

	public function test_the_job_title_is_limited() {
		$author = $this->users['author'];
		$this->submit( $author, array( 'nh_seo_job_title' => str_repeat( 'đ', 500 ) ) );

		$this->assertSame( NH_SEO_Data_Model::LIMIT_JOB_TITLE, mb_strlen( get_user_meta( $author, 'nh_job_title', true ) ) );
	}

	public function test_invalid_profile_links_are_dropped_and_reported() {
		$author = $this->users['author'];

		$this->submit(
			$author,
			array(
				'nh_seo_sameas' => "https://example.org/ok\njavascript:alert(1)\nftp://example.org/file\nmailto:me@example.org\nhttps://example.org/ok",
			)
		);

		$this->assertSame( 'https://example.org/ok', get_user_meta( $author, 'nh_sameas', true ) );
		$notices = get_transient( NH_SEO_Admin_Notices::TRANSIENT_PREFIX . get_current_user_id() );
		$this->assertCount( 1, $notices );
		$this->assertStringContainsString( '3 profile links were ignored', $notices[0]['message'] );
	}

	public function test_empty_values_delete_the_meta() {
		$author = $this->users['author'];
		update_user_meta( $author, 'nh_job_title', 'Writer' );
		update_user_meta( $author, 'nh_sameas', 'https://example.org/a' );

		$this->submit(
			$author,
			array(
				'nh_seo_job_title' => '   ',
				'nh_seo_sameas'    => "\n\n",
			)
		);

		$this->assertFalse( metadata_exists( 'user', $author, 'nh_job_title' ) );
		$this->assertFalse( metadata_exists( 'user', $author, 'nh_sameas' ) );
		$this->assertFalse( get_transient( NH_SEO_Admin_Notices::TRANSIENT_PREFIX . get_current_user_id() ), 'clearing the field is not an error' );
	}

	public function test_a_missing_or_invalid_nonce_saves_nothing() {
		$author = $this->users['author'];
		update_user_meta( $author, 'nh_job_title', 'Original' );

		$this->submit( $author, array( 'nh_seo_job_title' => 'Hacked' ), false );
		$this->assertSame( 'Original', get_user_meta( $author, 'nh_job_title', true ) );

		foreach ( array( '', 'junk', wp_create_nonce( 'other_action' ), wp_create_nonce( NH_SEO_User_Fields::nonce_action( $this->users['editor'] ) ) ) as $bad ) {
			$this->submit( $author, array( 'nh_seo_job_title' => 'Hacked' ), $bad );
			$this->assertSame( 'Original', get_user_meta( $author, 'nh_job_title', true ), 'nonce: ' . $bad );
		}
	}

	public function test_users_who_cannot_edit_the_profile_save_nothing() {
		$author = $this->users['author'];
		update_user_meta( $author, 'nh_job_title', 'Original' );

		$actors = array(
			'another author' => self::factory()->user->create( array( 'role' => 'author' ) ),
			'an editor'      => $this->users['editor'], // Editors cannot edit other users either.
		);
		foreach ( $actors as $label => $actor ) {
			wp_set_current_user( $actor );
			$this->assertFalse( current_user_can( 'edit_user', $author ), $label );
			$this->submit( $author, array( 'nh_seo_job_title' => 'Hacked' ) ); // The nonce is valid for the acting user.
			$this->assertSame( 'Original', get_user_meta( $author, 'nh_job_title', true ), $label );
		}

		wp_set_current_user( 0 );
		$this->submit( $author, array( 'nh_seo_job_title' => 'Hacked' ) );
		$this->assertSame( 'Original', get_user_meta( $author, 'nh_job_title', true ), 'logged out' );
	}

	public function test_users_who_cannot_write_posts_are_never_given_these_fields() {
		$subscriber = $this->users['subscriber'];

		$this->submit( $subscriber, array( 'nh_seo_job_title' => 'Imposter' ) );

		$this->assertFalse( metadata_exists( 'user', $subscriber, 'nh_job_title' ) );
	}

	public function test_a_form_without_the_fields_leaves_the_stored_values() {
		$author = $this->users['author'];
		update_user_meta( $author, 'nh_job_title', 'Writer' );
		update_user_meta( $author, 'nh_sameas', 'https://example.org/a' );

		$this->submit( $author, array( 'first_name' => 'Someone' ) );

		$this->assertSame( 'Writer', get_user_meta( $author, 'nh_job_title', true ) );
		$this->assertSame( 'https://example.org/a', get_user_meta( $author, 'nh_sameas', true ) );
	}

	public function test_arrays_in_the_fields_are_harmless() {
		$author = $this->users['author'];

		$this->submit(
			$author,
			array(
				'nh_seo_job_title' => array( 'x' ),
				'nh_seo_sameas'    => array( 'https://example.org/a', array( 'nested' ) ),
			)
		);

		$this->assertFalse( metadata_exists( 'user', $author, 'nh_job_title' ) );
		$this->assertSame( 'https://example.org/a', get_user_meta( $author, 'nh_sameas', true ) );
	}

	public function test_both_profile_hooks_run_the_handler() {
		$author = $this->users['author'];
		$email  = get_userdata( $author )->user_email; // Core's own profile handlers read it from the form too.

		$_POST = wp_slash(
			array(
				'nh_seo_user_nonce' => wp_create_nonce( NH_SEO_User_Fields::nonce_action( $author ) ),
				'nh_seo_job_title'  => 'Via edit_user_profile_update',
				'email'             => $email,
			)
		);
		do_action( 'edit_user_profile_update', $author );
		$this->assertSame( 'Via edit_user_profile_update', get_user_meta( $author, 'nh_job_title', true ) );

		wp_set_current_user( $author );
		$_POST = wp_slash(
			array(
				'nh_seo_user_nonce' => wp_create_nonce( NH_SEO_User_Fields::nonce_action( $author ) ),
				'nh_seo_job_title'  => 'Via personal_options_update',
				'email'             => $email,
			)
		);
		do_action( 'personal_options_update', $author );
		$this->assertSame( 'Via personal_options_update', get_user_meta( $author, 'nh_job_title', true ) );
	}

	public function test_the_fields_are_not_exposed_through_the_rest_api() {
		$GLOBALS['wp_rest_server'] = null;
		update_user_meta( $this->users['author'], 'nh_job_title', 'Writer' );
		update_user_meta( $this->users['author'], 'nh_sameas', 'https://example.org/a' );

		$response = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/users/' . $this->users['author'] ) );

		$GLOBALS['wp_rest_server'] = null;
		$this->assertSame( 200, $response->get_status() );
		$this->assertStringNotContainsString( 'Writer', wp_json_encode( $response->get_data() ) );
		$this->assertStringNotContainsString( 'nh_sameas', wp_json_encode( $response->get_data() ) );
	}
}
