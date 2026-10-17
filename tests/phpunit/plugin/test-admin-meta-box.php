<?php
/**
 * Tests for the "Answer, FAQ and sources" meta box: rendering and the save handler.
 *
 * @package nguyen-ha-seo-companion
 */

/**
 * NH_SEO_Meta_Box.
 */
class NH_Test_Admin_Meta_Box extends NH_Unit_Test_Case {

	/**
	 * User IDs by role.
	 *
	 * @var int[]
	 */
	private $users = array();

	public function set_up() {
		parent::set_up();
		remove_theme_support( 'nguyen-ha-companion' );
		update_option( NH_SEO_Keys::OPTION_SETTINGS, array( 'auto_cluster_terms' => 1 ) );
		delete_option( NH_SEO_Keys::OPTION_BUSINESS );
		foreach ( array( 'administrator', 'editor', 'author', 'subscriber' ) as $role ) {
			$this->users[ $role ] = self::factory()->user->create( array( 'role' => $role ) );
		}
		wp_set_current_user( $this->users['editor'] );
	}

	/**
	 * Create a published post or page.
	 *
	 * @param array $args Overrides.
	 * @return int
	 */
	private function post( array $args = array() ) {
		return self::factory()->post->create( array_merge( array( 'post_status' => 'publish' ), $args ) );
	}

	/**
	 * Simulate the submission of the meta box: nonce plus fields in $_POST (slashed like WordPress does),
	 * then run the save handler.
	 *
	 * @param int        $post_id Post ID.
	 * @param array      $fields  Submitted nh_seo fields.
	 * @param string|int $nonce   Nonce value, or null to create a valid one, or false to omit it.
	 * @return void
	 */
	private function submit( $post_id, array $fields, $nonce = null ) {
		$post = array( 'nh_seo' => $fields );
		if ( null === $nonce ) {
			$post['nh_seo_box_nonce'] = wp_create_nonce( NH_SEO_Meta_Box::nonce_action( $post_id ) );
		} elseif ( false !== $nonce ) {
			$post['nh_seo_box_nonce'] = $nonce;
		}
		$_POST = wp_slash( $post );
		NH_SEO_Meta_Box::save( $post_id, get_post( $post_id ) );
	}

	/**
	 * Render the box for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function render( $post_id ) {
		ob_start();
		NH_SEO_Meta_Box::render( get_post( $post_id ) );
		return ob_get_clean();
	}

	/**
	 * A set of fields exercising every kind of input.
	 *
	 * @param array $overrides Fields to replace.
	 * @return array
	 */
	private function full_fields( array $overrides = array() ) {
		return array_merge(
			array(
				'summary'        => "  <b>Pick</b> an automatic scooter.\n",
				'takeaways'      => "One\n\n<i>Two</i>\n",
				'faq'            => array(
					array(
						'question' => 'Q1?',
						'answer'   => '<p onclick="x()">A1 <a href="https://example.org">link</a></p>',
					),
					array(
						'question' => '',
						'answer'   => '',
					),
					array(
						'question' => 'Q2?',
						'answer'   => 'Plain answer',
					),
				),
				'sources'        => array(
					array(
						'title'     => 'Guide',
						'url'       => 'https://example.org/guide',
						'publisher' => 'Example',
					),
					array(
						'title'     => 'Bad',
						'url'       => 'javascript:alert(1)',
						'publisher' => '',
					),
				),
				'updated'        => '2024-01-15',
				'keywords'       => 'a, b, a',
				'local_business' => '1',
			),
			$overrides
		);
	}

	public function test_the_meta_box_is_registered_for_posts_and_pages_only() {
		$GLOBALS['wp_meta_boxes'] = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test isolation.
		register_post_type( 'nh_test_book', array( 'public' => true ) );
		try {
			foreach ( array( 'post', 'page', 'nh_test_book', 'attachment' ) as $type ) {
				do_action( 'add_meta_boxes', $type, get_post( $this->post() ) );
			}
		} finally {
			unregister_post_type( 'nh_test_book' );
		}

		$boxes = $GLOBALS['wp_meta_boxes'];
		$this->assertArrayHasKey( 'nh-seo-box', $boxes['post']['normal']['default'] );
		$this->assertArrayHasKey( 'nh-seo-box', $boxes['page']['normal']['default'] );
		$this->assertArrayNotHasKey( 'nh_test_book', $boxes );
		$this->assertArrayNotHasKey( 'attachment', $boxes );
		$this->assertSame( 'Answer, FAQ and sources', $boxes['post']['normal']['default']['nh-seo-box']['title'] );
	}

	public function test_render_prints_a_valid_nonce_and_every_field() {
		$post_id = $this->post();
		$html    = $this->render( $post_id );

		$this->assertSame( 1, preg_match( '/name="nh_seo_box_nonce" value="([^"]+)"/', $html, $match ) );
		$this->assertNotFalse( wp_verify_nonce( $match[1], NH_SEO_Meta_Box::nonce_action( $post_id ) ) );
		$this->assertFalse( wp_verify_nonce( $match[1], NH_SEO_Meta_Box::nonce_action( $post_id + 1 ) ), 'the nonce is bound to the post' );

		foreach ( array( 'summary', 'takeaways', 'updated', 'keywords', 'pillar', 'local_business' ) as $field ) {
			$this->assertStringContainsString( 'name="nh_seo[' . $field . ']"', $html, $field );
		}
		$this->assertStringContainsString( 'name="nh_seo[faq][0][question]"', $html );
		$this->assertStringContainsString( 'name="nh_seo[faq][0][answer]"', $html );
		$this->assertStringContainsString( 'name="nh_seo[sources][0][title]"', $html );
		$this->assertStringContainsString( 'name="nh_seo[sources][0][url]"', $html );
		$this->assertStringContainsString( 'name="nh_seo[sources][0][publisher]"', $html );
	}

	public function test_posts_and_pages_show_different_linking_fields() {
		$post_id = $this->post();
		$page_id = $this->post( array( 'post_type' => 'page' ) );
		$cluster = self::factory()->term->create( array( 'taxonomy' => 'topic_cluster' ) );
		wp_set_object_terms( $post_id, array( $cluster ), 'topic_cluster' );

		$post_html = $this->render( $post_id );
		$page_html = $this->render( $page_id );

		$this->assertStringContainsString( 'name="nh_seo[primary_cluster]"', $post_html );
		$this->assertStringNotContainsString( 'name="nh_seo[hub_term]"', $post_html );
		$this->assertStringNotContainsString( 'name="nh_seo[primary_cluster]"', $page_html );
		$this->assertStringContainsString( 'name="nh_seo[hub_term]"', $page_html );
	}

	public function test_stored_values_are_escaped_in_the_form() {
		$post_id = $this->post();
		update_post_meta( $post_id, 'nh_summary', 'A "quoted" summary & more' );
		update_post_meta( $post_id, 'nh_takeaways', array( 'Tom & "Jerry"' ) );
		update_post_meta(
			$post_id,
			'nh_faq',
			array(
				array(
					'question' => 'Is "this" <safe>?',
					'answer'   => '<p>Yes &amp; <a href="https://example.org">link</a></p>',
				),
			)
		);
		update_post_meta(
			$post_id,
			'nh_sources',
			array(
				array(
					'title'     => 'Title "one"',
					'url'       => 'https://example.org/a?b=1&c=2',
					'publisher' => 'Pub & Co',
				),
			)
		);
		update_post_meta( $post_id, 'nh_keywords', 'one "two"' );

		$html = $this->render( $post_id );

		$this->assertStringContainsString( '>A &quot;quoted&quot; summary &amp; more</textarea>', $html );
		$this->assertStringContainsString( 'Tom &amp; &quot;Jerry&quot;', $html );
		$this->assertStringContainsString( 'value="Is &quot;this&quot; ?"', $html, 'tags are stripped on read and quotes escaped' );
		$this->assertStringContainsString( '&lt;p&gt;Yes &amp;amp; &lt;a href=&quot;https://example.org&quot;&gt;link&lt;/a&gt;&lt;/p&gt;', $html );
		$this->assertStringContainsString( 'value="Title &quot;one&quot;"', $html );
		$this->assertStringContainsString( 'value="https://example.org/a?b=1&amp;c=2"', $html );
		$this->assertStringContainsString( 'value="Pub &amp; Co"', $html );
		$this->assertStringContainsString( 'value="one &quot;two&quot;"', $html );
		$this->assertStringNotContainsString( '"this" <safe>', $html );
	}

	public function test_hostile_stored_values_cannot_break_out_of_the_form() {
		global $wpdb;
		$post_id = $this->post();
		foreach ( array(
			'nh_summary'  => '</textarea><script>alert(1)</script>',
			'nh_faq'      => maybe_serialize(
				array(
					array(
						'question' => '"><script>alert(2)</script>',
						'answer'   => '</textarea><img src=x onerror=alert(3)>',
					),
				)
			),
			'nh_keywords' => '"><script>alert(4)</script>',
		) as $key => $value ) {
			$wpdb->insert(
				$wpdb->postmeta,
				array(
					'post_id'    => $post_id,
					'meta_key'   => $key,
					'meta_value' => $value,
				)
			);
		}
		wp_cache_delete( $post_id, 'post_meta' );

		$html = $this->render( $post_id );

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( '<img', $html );
		$this->assertSame( 1, substr_count( $html, 'id="nh-seo-summary"' ) );
	}

	public function test_every_repeater_gets_a_blank_row_and_a_template() {
		$post_id = $this->post();
		update_post_meta(
			$post_id,
			'nh_faq',
			array(
				array(
					'question' => 'Q',
					'answer'   => 'A',
				),
			)
		);

		$html = $this->render( $post_id );

		// One stored FAQ row, one blank row and one template row; the sources have a blank row and a template.
		$this->assertSame( 1, substr_count( $html, 'name="nh_seo[faq][0][question]"' ) );
		$this->assertSame( 1, substr_count( $html, 'name="nh_seo[faq][1][question]"' ) );
		$this->assertSame( 1, substr_count( $html, 'name="nh_seo[faq][__INDEX__][question]"' ) );
		$this->assertSame( 1, substr_count( $html, 'name="nh_seo[sources][0][url]"' ) );
		$this->assertSame( 1, substr_count( $html, 'name="nh_seo[sources][__INDEX__][url]"' ) );
		$this->assertSame( 2, substr_count( $html, '<template data-nh-template>' ) );
		$this->assertSame( 2, substr_count( $html, '<fieldset class="nh-seo-section nh-seo-repeater" data-nh-repeater' ) );

		// Without JavaScript the action buttons stay hidden: they could not work.
		$this->assertStringContainsString( 'data-nh-actions hidden', $html );
		$this->assertStringContainsString( 'data-nh-add-wrap hidden', $html );
	}

	public function test_a_full_repeater_gets_no_blank_row() {
		$post_id = $this->post();
		$items   = array();
		for ( $i = 0; $i < NH_SEO_Keys::LIMIT_FAQ_ITEMS; $i++ ) {
			$items[] = array(
				'question' => "Question $i?",
				'answer'   => "Answer $i",
			);
		}
		update_post_meta( $post_id, 'nh_faq', $items );

		$html = $this->render( $post_id );

		$last = NH_SEO_Keys::LIMIT_FAQ_ITEMS - 1;
		$this->assertStringContainsString( 'name="nh_seo[faq][' . $last . '][question]"', $html );
		$this->assertStringNotContainsString( 'name="nh_seo[faq][' . NH_SEO_Keys::LIMIT_FAQ_ITEMS . '][question]"', $html );
		$this->assertStringContainsString( 'data-nh-max="' . NH_SEO_Keys::LIMIT_FAQ_ITEMS . '"', $html );
	}

	public function test_inputs_carry_the_contract_limits() {
		$html = $this->render( $this->post() );

		$this->assertStringContainsString( 'name="nh_seo[summary]" maxlength="' . NH_SEO_Keys::LIMIT_SUMMARY . '"', $html );
		$this->assertStringContainsString( 'name="nh_seo[faq][0][question]" value="" maxlength="' . NH_SEO_Keys::LIMIT_FAQ_QUESTION . '"', $html );
		$this->assertStringContainsString( 'name="nh_seo[faq][0][answer]" maxlength="' . NH_SEO_Keys::LIMIT_FAQ_ANSWER . '"', $html );
		$this->assertStringContainsString( 'name="nh_seo[keywords]" value="" maxlength="' . NH_SEO_Keys::LIMIT_KEYWORDS . '"', $html );
		$this->assertStringContainsString( 'type="date"', $html );
		$this->assertStringContainsString( 'max="' . wp_date( 'Y-m-d' ) . '"', $html );
	}

	public function test_the_primary_cluster_select_lists_only_assigned_clusters() {
		$post_id   = $this->post();
		$first     = self::factory()->term->create(
			array(
				'taxonomy' => 'topic_cluster',
				'name'     => 'Alpha',
			)
		);
		$second    = self::factory()->term->create(
			array(
				'taxonomy' => 'topic_cluster',
				'name'     => 'Beta',
			)
		);
		$elsewhere = self::factory()->term->create(
			array(
				'taxonomy' => 'topic_cluster',
				'name'     => 'Gamma',
			)
		);
		wp_set_object_terms( $post_id, array( $first, $second ), 'topic_cluster' );
		update_post_meta( $post_id, 'nh_primary_cluster', $second );

		$html = $this->render( $post_id );

		$this->assertStringContainsString( '<option value="0">Automatic', $html );
		$this->assertStringContainsString( '<option value="' . $first . '">Alpha</option>', $html );
		$this->assertStringContainsString( '<option value="' . $second . '" selected=\'selected\'>Beta</option>', $html );
		$this->assertStringNotContainsString( 'Gamma', $html );
		$this->assertStringNotContainsString( 'value="' . $elsewhere . '"', $html );
	}

	public function test_a_stale_primary_cluster_choice_is_shown_as_automatic() {
		$post_id    = $this->post();
		$assigned   = self::factory()->term->create( array( 'taxonomy' => 'topic_cluster' ) );
		$unassigned = self::factory()->term->create( array( 'taxonomy' => 'topic_cluster' ) );
		wp_set_object_terms( $post_id, array( $assigned ), 'topic_cluster' );
		update_post_meta( $post_id, 'nh_primary_cluster', $unassigned );

		$html = $this->render( $post_id );

		$this->assertStringNotContainsString( "selected='selected'", $html );
	}

	public function test_posts_without_clusters_get_a_hint_instead_of_a_select() {
		$html = $this->render( $this->post() );

		$this->assertStringNotContainsString( 'name="nh_seo[primary_cluster]"', $html );
		$this->assertStringContainsString( 'Assign Topic Clusters to this post and save it', $html );
	}

	public function test_a_pending_cluster_alias_is_explained() {
		update_option( NH_SEO_Keys::OPTION_SETTINGS, array( 'auto_cluster_terms' => 0 ) );
		$post_id = $this->post();
		update_post_meta( $post_id, 'nh_topic_cluster', 'ghost-cluster' );

		$this->assertStringContainsString( 'The cluster alias &quot;ghost-cluster&quot; sent by a workflow does not match', $this->render( $post_id ) );

		self::factory()->term->create(
			array(
				'taxonomy' => 'topic_cluster',
				'name'     => 'Ghost cluster',
				'slug'     => 'ghost-cluster',
			)
		);
		$this->assertStringNotContainsString( 'does not match any Topic Cluster', $this->render( $post_id ) );
	}

	public function test_the_hub_select_lists_categories_and_clusters_and_selects_the_stored_one() {
		$category = self::factory()->category->create( array( 'name' => 'Rentals' ) );
		$cluster  = self::factory()->term->create(
			array(
				'taxonomy' => 'topic_cluster',
				'name'     => 'Basics',
			)
		);
		$page_id  = $this->post( array( 'post_type' => 'page' ) );
		update_post_meta( $page_id, 'nh_hub_term', 'topic_cluster:' . $cluster );

		$html = $this->render( $page_id );

		$this->assertStringContainsString( '<optgroup label="Categories">', $html );
		$this->assertStringContainsString( '<optgroup label="Topic Clusters">', $html );
		$this->assertStringContainsString( '<option value="category:' . $category . '">Rentals</option>', $html );
		$this->assertStringContainsString( '<option value="topic_cluster:' . $cluster . '" selected=\'selected\'>Basics</option>', $html );
		$this->assertStringContainsString( '<option value="">None</option>', $html );
	}

	public function test_the_hub_select_is_limited_but_never_drops_the_stored_topic() {
		$limit = NH_SEO_Meta_Box::MAX_TERMS;
		for ( $i = 0; $i < $limit + 2; $i++ ) {
			self::factory()->category->create( array( 'name' => sprintf( 'A category %04d', $i ) ) );
		}
		$last = self::factory()->category->create( array( 'name' => 'Zzz sorted last' ) );
		$page = $this->post( array( 'post_type' => 'page' ) );
		update_post_meta( $page, 'nh_hub_term', 'category:' . $last );

		$html = $this->render( $page );

		// The default category is also listed, so allow it on top of the limit; the stored topic is added once.
		$this->assertLessThanOrEqual( $limit + 1, substr_count( $html, '<option value="category:' ) );
		$this->assertSame( 1, substr_count( $html, 'value="category:' . $last . '"' ) );
		$this->assertStringContainsString( '<option value="category:' . $last . '" selected="selected">Zzz sorted last</option>', $html );
	}

	public function test_the_pillar_field_shows_the_resolved_title() {
		$pillar  = $this->post( array( 'post_title' => 'Complete guide <b>to</b> scooters' ) );
		$post_id = $this->post();
		update_post_meta( $post_id, 'nh_pillar_id', $pillar );

		$html = $this->render( $post_id );

		$this->assertStringContainsString( 'name="nh_seo[pillar]" value="' . $pillar . '"', $html );
		$this->assertStringContainsString( 'Complete guide to scooters', $html );
		$this->assertStringNotContainsString( '<b>to</b>', $html );
	}

	public function test_the_local_business_hint_follows_the_profile_and_the_capability() {
		$post_id = $this->post();

		$incomplete_editor = $this->render( $post_id );
		$this->assertStringContainsString( 'The business profile is not complete yet', $incomplete_editor );
		$this->assertStringNotContainsString( 'Complete the business profile', $incomplete_editor, 'editors cannot open the settings' );

		wp_set_current_user( $this->users['administrator'] );
		$incomplete_admin = $this->render( $post_id );
		$this->assertStringContainsString( 'Complete the business profile', $incomplete_admin );
		$this->assertStringContainsString( 'tab=business', $incomplete_admin );

		update_option(
			NH_SEO_Keys::OPTION_BUSINESS,
			array(
				'name'     => 'Example Co',
				'phone'    => '+00 000 000',
				'street'   => '1 Example Street',
				'locality' => 'Example City',
			)
		);
		$this->assertStringNotContainsString( 'The business profile is not complete yet', $this->render( $post_id ) );
	}

	public function test_the_checkbox_reflects_the_stored_flag() {
		$post_id = $this->post();
		$this->assertStringNotContainsString( 'checked=', $this->render( $post_id ) );

		update_post_meta( $post_id, 'nh_local_business', 1 );
		$this->assertMatchesRegularExpression( '/id="nh-seo-local-business"[^>]*checked=/', $this->render( $post_id ) );
	}

	public function test_a_valid_submission_saves_every_field_sanitized() {
		$pillar  = $this->post();
		$cluster = self::factory()->term->create( array( 'taxonomy' => 'topic_cluster' ) );
		$post_id = $this->post();
		wp_set_object_terms( $post_id, array( $cluster ), 'topic_cluster' );

		$this->submit(
			$post_id,
			$this->full_fields(
				array(
					'pillar'          => (string) $pillar,
					'primary_cluster' => (string) $cluster,
				)
			)
		);

		$this->assertSame( 'Pick an automatic scooter.', get_post_meta( $post_id, 'nh_summary', true ) );
		$this->assertSame( array( 'One', 'Two' ), get_post_meta( $post_id, 'nh_takeaways', true ) );

		$faq = get_post_meta( $post_id, 'nh_faq', true );
		$this->assertCount( 2, $faq, 'the blank row is dropped' );
		$this->assertSame( 'Q1?', $faq[0]['question'] );
		$this->assertStringNotContainsString( 'onclick', $faq[0]['answer'] );
		$this->assertStringContainsString( '<a href="https://example.org">link</a>', $faq[0]['answer'] );
		$this->assertSame( 'Q2?', $faq[1]['question'] );
		$this->assertSame( 'Plain answer', $faq[1]['answer'] );

		$this->assertSame(
			array(
				array(
					'title'     => 'Guide',
					'url'       => 'https://example.org/guide',
					'publisher' => 'Example',
				),
			),
			get_post_meta( $post_id, 'nh_sources', true )
		);
		$this->assertSame( '2024-01-15', get_post_meta( $post_id, 'nh_content_updated', true ) );
		$this->assertSame( 'a, b', get_post_meta( $post_id, 'nh_keywords', true ) );
		$this->assertSame( '1', get_post_meta( $post_id, 'nh_local_business', true ) );
		$this->assertSame( (string) $pillar, get_post_meta( $post_id, 'nh_pillar_id', true ) );
		$this->assertSame( (string) $cluster, get_post_meta( $post_id, 'nh_primary_cluster', true ) );
	}

	public function test_what_is_saved_is_what_the_form_shows_again() {
		$post_id = $this->post();
		$this->submit( $post_id, $this->full_fields() );

		$html = $this->render( $post_id );

		$this->assertStringContainsString( '>Pick an automatic scooter.</textarea>', $html );
		$this->assertStringContainsString( "One\nTwo</textarea>", $html );
		$this->assertStringContainsString( 'value="Q2?"', $html );
		$this->assertStringContainsString( 'value="2024-01-15"', $html );
		$this->assertStringContainsString( 'value="a, b"', $html );
	}

	public function test_empty_values_delete_the_meta() {
		$post_id = $this->post();
		$this->submit( $post_id, $this->full_fields() );
		foreach ( array( 'nh_summary', 'nh_takeaways', 'nh_faq', 'nh_sources', 'nh_content_updated', 'nh_keywords', 'nh_local_business' ) as $key ) {
			$this->assertTrue( metadata_exists( 'post', $post_id, $key ), "$key was saved" );
		}

		$this->submit(
			$post_id,
			array(
				'summary'        => '',
				'takeaways'      => " \n ",
				'faq'            => array(
					array(
						'question' => '',
						'answer'   => '',
					),
				),
				'sources'        => array(
					array(
						'title' => 'No url',
						'url'   => '',
					),
				),
				'updated'        => '',
				'keywords'       => '',
				'local_business' => '0',
				'pillar'         => '',
			)
		);

		foreach ( array( 'nh_summary', 'nh_takeaways', 'nh_faq', 'nh_sources', 'nh_content_updated', 'nh_keywords', 'nh_local_business', 'nh_pillar_id' ) as $key ) {
			$this->assertFalse( metadata_exists( 'post', $post_id, $key ), "$key is deleted, not blanked" );
		}
	}

	public function test_a_missing_nonce_saves_nothing() {
		$post_id = $this->post();
		update_post_meta( $post_id, 'nh_summary', 'Original' );

		$this->submit( $post_id, $this->full_fields(), false );

		$this->assertSame( 'Original', get_post_meta( $post_id, 'nh_summary', true ) );
		$this->assertFalse( metadata_exists( 'post', $post_id, 'nh_faq' ) );
		$this->assertFalse( metadata_exists( 'post', $post_id, 'nh_local_business' ) );
	}

	public function test_invalid_nonces_save_nothing() {
		$post_id = $this->post();
		update_post_meta( $post_id, 'nh_summary', 'Original' );

		foreach ( array( '', 'not-a-nonce', '0', wp_create_nonce( 'some_other_action' ), wp_create_nonce( NH_SEO_Meta_Box::nonce_action( $post_id + 100 ) ) ) as $bad ) {
			$this->submit( $post_id, $this->full_fields(), $bad );
			$this->assertSame( 'Original', get_post_meta( $post_id, 'nh_summary', true ), 'nonce: ' . $bad );
		}
		$this->assertFalse( metadata_exists( 'post', $post_id, 'nh_faq' ) );
	}

	public function test_a_nonce_made_for_another_post_is_rejected() {
		$mine  = $this->post();
		$other = $this->post();
		$nonce = wp_create_nonce( NH_SEO_Meta_Box::nonce_action( $mine ) );

		$this->submit( $other, $this->full_fields(), $nonce );

		$this->assertFalse( metadata_exists( 'post', $other, 'nh_summary' ) );
	}

	public function test_a_nonce_of_another_user_is_rejected() {
		$post_id = $this->post();
		wp_set_current_user( $this->users['administrator'] );
		$admin_nonce = wp_create_nonce( NH_SEO_Meta_Box::nonce_action( $post_id ) );
		wp_set_current_user( $this->users['editor'] );

		$this->submit( $post_id, $this->full_fields(), $admin_nonce );

		$this->assertFalse( metadata_exists( 'post', $post_id, 'nh_summary' ), 'nonces are bound to the user' );
	}

	public function test_users_who_cannot_edit_the_post_save_nothing() {
		$post_id = $this->post( array( 'post_author' => $this->users['editor'] ) );
		update_post_meta( $post_id, 'nh_summary', 'Original' );

		foreach ( array( 'subscriber', 'author' ) as $role ) {
			wp_set_current_user( $this->users[ $role ] );
			$this->assertFalse( current_user_can( 'edit_post', $post_id ), $role );
			$this->submit( $post_id, $this->full_fields() ); // The nonce itself is valid for this user.
			$this->assertSame( 'Original', get_post_meta( $post_id, 'nh_summary', true ), $role );
			$this->assertFalse( metadata_exists( 'post', $post_id, 'nh_faq' ), $role );
		}

		wp_set_current_user( 0 );
		$this->submit( $post_id, $this->full_fields(), false );
		$this->assertSame( 'Original', get_post_meta( $post_id, 'nh_summary', true ) );
	}

	public function test_authors_can_save_their_own_posts() {
		$post_id = $this->post( array( 'post_author' => $this->users['author'] ) );
		wp_set_current_user( $this->users['author'] );

		$this->submit( $post_id, $this->full_fields() );

		$this->assertSame( 'Pick an automatic scooter.', get_post_meta( $post_id, 'nh_summary', true ) );
	}

	public function test_autosaves_and_revisions_save_nothing() {
		$post_id  = $this->post();
		$revision = wp_insert_post(
			array(
				'post_type'   => 'revision',
				'post_status' => 'inherit',
				'post_parent' => $post_id,
				'post_name'   => $post_id . '-revision-v1',
			)
		);
		$autosave = wp_insert_post(
			array(
				'post_type'   => 'revision',
				'post_status' => 'inherit',
				'post_parent' => $post_id,
				'post_name'   => $post_id . '-autosave-v1',
			)
		);

		foreach ( array( $revision, $autosave ) as $id ) {
			// A perfectly valid nonce for that very ID, so only the revision and autosave rules can stop the save.
			$this->submit( $id, $this->full_fields() );
			$this->assertFalse( metadata_exists( 'post', $id, 'nh_summary' ) );
			$this->assertFalse( metadata_exists( 'post', $post_id, 'nh_summary' ), 'nothing leaks to the parent post' );
		}
	}

	public function test_other_post_types_are_ignored() {
		register_post_type( 'nh_test_book', array( 'public' => true ) );
		try {
			$book = $this->post( array( 'post_type' => 'nh_test_book' ) );
			$this->submit( $book, $this->full_fields() );
			$saved = metadata_exists( 'post', $book, 'nh_summary' );
		} finally {
			unregister_post_type( 'nh_test_book' );
		}
		$this->assertFalse( $saved );
	}

	public function test_fields_that_are_not_part_of_the_form_are_left_untouched() {
		$post_id = $this->post();
		$this->submit( $post_id, $this->full_fields() );

		$this->submit( $post_id, array( 'summary' => 'Only the summary was submitted' ) );

		$this->assertSame( 'Only the summary was submitted', get_post_meta( $post_id, 'nh_summary', true ) );
		$this->assertSame( array( 'One', 'Two' ), get_post_meta( $post_id, 'nh_takeaways', true ) );
		$this->assertCount( 2, get_post_meta( $post_id, 'nh_faq', true ) );
		$this->assertCount( 1, get_post_meta( $post_id, 'nh_sources', true ) );
		$this->assertSame( '2024-01-15', get_post_meta( $post_id, 'nh_content_updated', true ) );
		$this->assertSame( '1', get_post_meta( $post_id, 'nh_local_business', true ) );
	}

	public function test_a_request_without_the_field_group_changes_nothing() {
		$post_id = $this->post();
		$this->submit( $post_id, $this->full_fields() );

		$_POST = wp_slash( array( 'nh_seo_box_nonce' => wp_create_nonce( NH_SEO_Meta_Box::nonce_action( $post_id ) ) ) );
		NH_SEO_Meta_Box::save( $post_id, get_post( $post_id ) );
		$this->assertSame( 'Pick an automatic scooter.', get_post_meta( $post_id, 'nh_summary', true ) );

		$_POST = wp_slash(
			array(
				'nh_seo_box_nonce' => wp_create_nonce( NH_SEO_Meta_Box::nonce_action( $post_id ) ),
				'nh_seo'           => 'not an array',
			)
		);
		NH_SEO_Meta_Box::save( $post_id, get_post( $post_id ) );
		$this->assertSame( 'Pick an automatic scooter.', get_post_meta( $post_id, 'nh_summary', true ) );
	}

	public function test_backslashes_survive_a_round_trip() {
		$post_id = $this->post();
		$this->submit(
			$post_id,
			array(
				'summary'   => 'Path C:\\temp\\new and a \\ slash',
				'takeaways' => "Use C:\\temp\\n\nUse \\d+",
				'faq'       => array(
					array(
						'question' => 'Where is C:\\Users\\Guest?',
						'answer'   => '<p>Open C:\\Users\\Guest\\Documents</p>',
					),
				),
			)
		);

		$this->assertSame( 'Path C:\\temp\\new and a \\ slash', get_post_meta( $post_id, 'nh_summary', true ) );
		$this->assertSame( array( 'Use C:\\temp\\n', 'Use \\d+' ), get_post_meta( $post_id, 'nh_takeaways', true ) );
		$faq = get_post_meta( $post_id, 'nh_faq', true );
		$this->assertSame( 'Where is C:\\Users\\Guest?', $faq[0]['question'] );
		$this->assertSame( '<p>Open C:\\Users\\Guest\\Documents</p>', $faq[0]['answer'] );

		// Saving what the form shows again must not eat more backslashes.
		$this->submit(
			$post_id,
			array(
				'summary' => get_post_meta( $post_id, 'nh_summary', true ),
				'faq'     => $faq,
			)
		);
		$this->assertSame( 'Path C:\\temp\\new and a \\ slash', get_post_meta( $post_id, 'nh_summary', true ) );
		$this->assertSame( $faq, get_post_meta( $post_id, 'nh_faq', true ) );
	}

	public function test_the_primary_cluster_must_be_assigned_to_the_post() {
		$post_id    = $this->post();
		$assigned   = self::factory()->term->create( array( 'taxonomy' => 'topic_cluster' ) );
		$unassigned = self::factory()->term->create( array( 'taxonomy' => 'topic_cluster' ) );
		$tag        = self::factory()->term->create( array( 'taxonomy' => 'post_tag' ) );
		wp_set_object_terms( $post_id, array( $assigned ), 'topic_cluster' );

		$this->submit( $post_id, array( 'primary_cluster' => (string) $assigned ) );
		$this->assertSame( (string) $assigned, get_post_meta( $post_id, 'nh_primary_cluster', true ) );

		foreach ( array( (string) $unassigned, (string) $tag, '999999', 'abc', '0', '' ) as $bad ) {
			update_post_meta( $post_id, 'nh_primary_cluster', $assigned );
			$this->submit( $post_id, array( 'primary_cluster' => $bad ) );
			$this->assertFalse( metadata_exists( 'post', $post_id, 'nh_primary_cluster' ), 'value: ' . $bad );
		}

		// Not part of the form at all (no cluster was assigned when the page loaded): untouched.
		update_post_meta( $post_id, 'nh_primary_cluster', $assigned );
		$this->submit( $post_id, array( 'summary' => 'x' ) );
		$this->assertSame( (string) $assigned, get_post_meta( $post_id, 'nh_primary_cluster', true ) );
	}

	public function test_pages_have_no_primary_cluster() {
		$page    = $this->post( array( 'post_type' => 'page' ) );
		$cluster = self::factory()->term->create( array( 'taxonomy' => 'topic_cluster' ) );

		$this->submit( $page, array( 'primary_cluster' => (string) $cluster ) );

		$this->assertFalse( metadata_exists( 'post', $page, 'nh_primary_cluster' ) );
	}

	public function test_the_hub_topic_is_saved_for_pages_only_and_validated() {
		$category = self::factory()->category->create();
		$cluster  = self::factory()->term->create( array( 'taxonomy' => 'topic_cluster' ) );
		$page     = $this->post( array( 'post_type' => 'page' ) );
		$post_id  = $this->post();

		$this->submit( $page, array( 'hub_term' => "category:$category" ) );
		$this->assertSame( "category:$category", get_post_meta( $page, 'nh_hub_term', true ) );

		$this->submit( $page, array( 'hub_term' => "topic_cluster:$cluster" ) );
		$this->assertSame( "topic_cluster:$cluster", get_post_meta( $page, 'nh_hub_term', true ) );

		foreach ( array( 'category:999999', 'post_tag:1', 'category:1; DROP TABLE x', 'junk' ) as $bad ) {
			$this->submit( $page, array( 'hub_term' => "category:$category" ) );
			$this->submit( $page, array( 'hub_term' => $bad ) );
			$this->assertFalse( metadata_exists( 'post', $page, 'nh_hub_term' ), $bad );
		}

		$this->submit( $page, array( 'hub_term' => "category:$category" ) );
		$this->submit( $page, array( 'hub_term' => '' ) );
		$this->assertFalse( metadata_exists( 'post', $page, 'nh_hub_term' ), 'None deletes it' );

		$this->submit( $post_id, array( 'hub_term' => "category:$category" ) );
		$this->assertFalse( metadata_exists( 'post', $post_id, 'nh_hub_term' ), 'posts have no hub topic' );
	}

	public function test_the_pillar_must_be_another_published_page_and_the_editor_is_told() {
		$pillar  = $this->post();
		$draft   = $this->post( array( 'post_status' => 'draft' ) );
		$post_id = $this->post();

		$this->submit( $post_id, array( 'pillar' => " $pillar " ) );
		$this->assertSame( (string) $pillar, get_post_meta( $post_id, 'nh_pillar_id', true ) );
		$this->assertFalse( get_transient( NH_SEO_Admin_Notices::TRANSIENT_PREFIX . $this->users['editor'] ), 'a valid Pillar raises no notice' );

		$this->submit( $post_id, array( 'pillar' => (string) $post_id ) );
		$this->assertFalse( metadata_exists( 'post', $post_id, 'nh_pillar_id' ), 'a post cannot be its own Pillar' );
		$notices = get_transient( NH_SEO_Admin_Notices::TRANSIENT_PREFIX . $this->users['editor'] );
		$this->assertNotEmpty( $notices );
		$this->assertStringContainsString( 'was ignored', $notices[0]['message'] );

		delete_transient( NH_SEO_Admin_Notices::TRANSIENT_PREFIX . $this->users['editor'] );
		foreach ( array( (string) $draft, '999999', 'abc' ) as $bad ) {
			$this->submit( $post_id, array( 'pillar' => (string) $pillar ) );
			$this->submit( $post_id, array( 'pillar' => $bad ) );
			$this->assertFalse( metadata_exists( 'post', $post_id, 'nh_pillar_id' ), 'value: ' . $bad );
		}
		$this->assertCount( 3, get_transient( NH_SEO_Admin_Notices::TRANSIENT_PREFIX . $this->users['editor'] ) );

		delete_transient( NH_SEO_Admin_Notices::TRANSIENT_PREFIX . $this->users['editor'] );
		$this->submit( $post_id, array( 'pillar' => (string) $pillar ) );
		$this->submit( $post_id, array( 'pillar' => '' ) );
		$this->assertFalse( metadata_exists( 'post', $post_id, 'nh_pillar_id' ), 'empty removes the Pillar' );
		$this->assertFalse( get_transient( NH_SEO_Admin_Notices::TRANSIENT_PREFIX . $this->users['editor'] ), 'removing a Pillar raises no notice' );
	}

	public function test_list_limits_are_enforced_on_save() {
		$post_id = $this->post();
		$faq     = array();
		$sources = array();
		for ( $i = 0; $i < 40; $i++ ) {
			$faq[]     = array(
				'question' => "Question $i?",
				'answer'   => "Answer $i",
			);
			$sources[] = array( 'url' => "https://example.org/$i" );
		}

		$this->submit(
			$post_id,
			array(
				'summary'   => str_repeat( 'x', 5000 ),
				'takeaways' => implode( "\n", array_fill( 0, 30, 'Point' ) ),
				'faq'       => $faq,
				'sources'   => $sources,
				'keywords'  => str_repeat( 'word, ', 300 ),
			)
		);

		$this->assertSame( NH_SEO_Keys::LIMIT_SUMMARY, mb_strlen( get_post_meta( $post_id, 'nh_summary', true ) ) );
		$this->assertCount( NH_SEO_Keys::LIMIT_TAKEAWAYS, get_post_meta( $post_id, 'nh_takeaways', true ) );
		$this->assertCount( NH_SEO_Keys::LIMIT_FAQ_ITEMS, get_post_meta( $post_id, 'nh_faq', true ) );
		$this->assertCount( NH_SEO_Keys::LIMIT_SOURCES, get_post_meta( $post_id, 'nh_sources', true ) );
		$this->assertLessThanOrEqual( NH_SEO_Keys::LIMIT_KEYWORDS, mb_strlen( get_post_meta( $post_id, 'nh_keywords', true ) ) );
	}

	public function test_row_order_is_kept_and_blank_rows_are_dropped_whatever_the_indexes() {
		$post_id = $this->post();

		$this->submit(
			$post_id,
			array(
				'faq' => array(
					7 => array(
						'question' => 'Third?',
						'answer'   => 'c',
					),
					0 => array(
						'question' => '',
						'answer'   => '',
					),
					3 => array(
						'question' => 'First?',
						'answer'   => 'a',
					),
					5 => array(
						'question' => 'Question without answer',
						'answer'   => '',
					),
					9 => array(
						'question' => 'Second?',
						'answer'   => 'b',
					),
				),
			)
		);

		$this->assertSame( array( 'Third?', 'First?', 'Second?' ), wp_list_pluck( get_post_meta( $post_id, 'nh_faq', true ), 'question' ), 'the order of the submitted rows is the saved order' );
	}

	public function test_malformed_submissions_are_harmless() {
		$post_id = $this->post();
		$this->submit(
			$post_id,
			array(
				'summary'        => array( 'not', 'a', 'string' ),
				'takeaways'      => array( 'one', array( 'nested' ) ),
				'faq'            => array(
					'string row',
					array(
						'question' => array( 'x' ),
						'answer'   => 'a',
					),
					array(
						'question' => 'Q',
						'answer'   => array( 'x' ),
					),
					null,
				),
				'sources'        => array( 'row', array( 'url' => array( 'x' ) ), array( 'url' => 'https://example.org' ) ),
				'updated'        => array( '2024-01-01' ),
				'keywords'       => array( 'a', array( 'b' ) ),
				'local_business' => array( '1' ),
				'pillar'         => array( '1' ),
			)
		);

		$this->assertFalse( metadata_exists( 'post', $post_id, 'nh_summary' ) );
		$this->assertSame( array( 'one' ), get_post_meta( $post_id, 'nh_takeaways', true ) );
		$this->assertFalse( metadata_exists( 'post', $post_id, 'nh_faq' ) );
		$this->assertCount( 1, get_post_meta( $post_id, 'nh_sources', true ) );
		$this->assertFalse( metadata_exists( 'post', $post_id, 'nh_content_updated' ) );
		$this->assertFalse( metadata_exists( 'post', $post_id, 'nh_local_business' ) );
		$this->assertFalse( metadata_exists( 'post', $post_id, 'nh_pillar_id' ) );
	}

	public function test_hostile_input_is_neutralized_on_save() {
		$post_id = $this->post();

		$this->submit(
			$post_id,
			array(
				'summary'   => '<script>alert(1)</script>Summary <img src=x onerror=alert(2)>',
				'takeaways' => "<script>alert(3)</script>Point\n<b onmouseover=alert(4)>bold</b>",
				'faq'       => array(
					array(
						'question' => '<img src=x onerror=alert(5)>Question',
						'answer'   => '<p onclick="evil()">Answer</p><iframe src="javascript:alert(6)"></iframe><a href="javascript:alert(7)">x</a>',
					),
				),
				'sources'   => array(
					array(
						'title' => '<svg onload=alert(8)>',
						'url'   => 'javascript:alert(9)',
					),
					array(
						'title' => 'OK',
						'url'   => 'https://example.org/x"onmouseover="alert(10)',
					),
				),
				'keywords'  => '<script>alert(11)</script>kw',
			)
		);

		$stored = wp_json_encode(
			array(
				get_post_meta( $post_id, 'nh_summary', true ),
				get_post_meta( $post_id, 'nh_takeaways', true ),
				get_post_meta( $post_id, 'nh_faq', true ),
				get_post_meta( $post_id, 'nh_sources', true ),
				get_post_meta( $post_id, 'nh_keywords', true ),
			)
		);
		foreach ( array( '<script', '<img', '<iframe', '<svg', 'javascript:' ) as $needle ) {
			$this->assertStringNotContainsStringIgnoringCase( $needle, $stored, $needle );
		}
		$this->assertDoesNotMatchRegularExpression( '/<[^>]*\bon[a-z]+\s*=/i', $stored );
		$this->assertCount( 1, get_post_meta( $post_id, 'nh_sources', true ) );
	}

	public function test_the_save_post_hook_runs_the_handler_and_unrelated_saves_change_nothing() {
		$post_id = $this->post();
		$this->assertIsInt( has_action( 'save_post', array( 'NH_SEO_Meta_Box', 'save' ) ) );

		$_POST = wp_slash(
			array(
				'nh_seo_box_nonce' => wp_create_nonce( NH_SEO_Meta_Box::nonce_action( $post_id ) ),
				'nh_seo'           => array( 'summary' => 'Saved through the hook' ),
			)
		);
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Edited',
			)
		);
		$this->assertSame( 'Saved through the hook', get_post_meta( $post_id, 'nh_summary', true ) );

		// A save without the form (REST, cron, quick edit, bulk edit) must leave everything as it is.
		$_POST = array();
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Edited by something else',
			)
		);
		$this->assertSame( 'Saved through the hook', get_post_meta( $post_id, 'nh_summary', true ) );

		// Fields without a nonce (an inline edit that happens to carry the same names) are ignored.
		$_POST = wp_slash( array( 'nh_seo' => array( 'summary' => 'No nonce' ) ) );
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Edited again',
			)
		);
		$this->assertSame( 'Saved through the hook', get_post_meta( $post_id, 'nh_summary', true ) );
	}

	public function test_saving_twice_is_idempotent() {
		$post_id = $this->post();
		$this->submit( $post_id, $this->full_fields() );
		$first = array(
			get_post_meta( $post_id, 'nh_faq', true ),
			get_post_meta( $post_id, 'nh_sources', true ),
			get_post_meta( $post_id, 'nh_takeaways', true ),
		);

		$this->submit( $post_id, $this->full_fields() );

		$this->assertSame(
			$first,
			array(
				get_post_meta( $post_id, 'nh_faq', true ),
				get_post_meta( $post_id, 'nh_sources', true ),
				get_post_meta( $post_id, 'nh_takeaways', true ),
			)
		);
	}
}
