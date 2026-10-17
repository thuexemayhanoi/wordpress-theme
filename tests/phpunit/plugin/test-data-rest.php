<?php
/**
 * REST API tests: how the AI Writer workflow writes the data contract.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * Create and update posts, pages and terms through rest_do_request() with every meta key.
 */
class NH_Test_Data_Rest extends NH_Unit_Test_Case {

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

		// A fresh server builds fresh controllers that see the taxonomy and meta registered above.
		$GLOBALS['wp_rest_server'] = null;

		foreach ( array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' ) as $role ) {
			$this->users[ $role ] = self::factory()->user->create( array( 'role' => $role ) );
		}
		wp_set_current_user( $this->users['administrator'] );
	}

	public function tear_down() {
		$GLOBALS['wp_rest_server'] = null;
		parent::tear_down();
	}

	/**
	 * Dispatch a REST request.
	 *
	 * @param string $method HTTP method.
	 * @param string $route  Route.
	 * @param array  $body   JSON body.
	 * @return WP_REST_Response
	 */
	private function dispatch( $method, $route, array $body = array() ) {
		$request = new WP_REST_Request( $method, $route );
		if ( $body ) {
			$request->set_header( 'content-type', 'application/json' );
			$request->set_body( wp_json_encode( $body ) );
		}
		return rest_do_request( $request );
	}

	/**
	 * A complete, valid payload for posts.
	 *
	 * @param int $pillar Pillar post ID.
	 * @return array
	 */
	private function valid_payload( $pillar ) {
		return array(
			'title'  => 'Which scooter should a first-time visitor rent?',
			'status' => 'publish',
			'meta'   => array(
				'nh_summary'         => "  <b>Pick an automatic</b> scooter for city riding.\n",
				'nh_takeaways'       => array( 'Automatic scooters are easiest in traffic.', '', '<i>Check</i> the helmet.' ),
				'nh_faq'             => array(
					array(
						'question' => 'Can I rent without experience?',
						'answer'   => '<p>Confirm with the shop. <a href="https://example.org/guide" onclick="x()">Guide</a></p><script>alert(1)</script>',
					),
				),
				'nh_sources'         => array(
					array(
						'title'     => 'Official guide',
						'url'       => 'https://example.org/guide',
						'publisher' => 'Example',
					),
					array(
						'title' => 'Bad',
						'url'   => 'javascript:alert(1)',
					),
				),
				'nh_content_updated' => '2024-01-15',
				'nh_topic_cluster'   => 'scooter-rental-basics',
				'nh_pillar_id'       => $pillar,
				'nh_local_business'  => true,
				'nh_keywords'        => 'scooter, rental, scooter',
			),
		);
	}

	/**
	 * Create a published post and return its ID.
	 *
	 * @param array $args Overrides.
	 * @return int
	 */
	private function post( array $args = array() ) {
		return self::factory()->post->create( array_merge( array( 'post_status' => 'publish' ), $args ) );
	}

	public function test_admin_can_create_a_post_with_every_meta_key_and_it_is_sanitized() {
		$pillar   = $this->post();
		$response = $this->dispatch( 'POST', '/wp/v2/posts', $this->valid_payload( $pillar ) );

		$this->assertSame( 201, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$post_id = $response->get_data()['id'];

		$this->assertSame( 'Pick an automatic scooter for city riding.', get_post_meta( $post_id, 'nh_summary', true ) );
		$this->assertSame( array( 'Automatic scooters are easiest in traffic.', 'Check the helmet.' ), get_post_meta( $post_id, 'nh_takeaways', true ) );

		$faq = get_post_meta( $post_id, 'nh_faq', true );
		$this->assertCount( 1, $faq );
		$this->assertSame( 'Can I rent without experience?', $faq[0]['question'] );
		$this->assertStringContainsString( '<a href="https://example.org/guide">Guide</a>', $faq[0]['answer'] );
		$this->assertStringNotContainsString( 'onclick', $faq[0]['answer'] );
		$this->assertStringNotContainsString( '<script', $faq[0]['answer'] );

		$this->assertSame(
			array(
				array(
					'title'     => 'Official guide',
					'url'       => 'https://example.org/guide',
					'publisher' => 'Example',
				),
			),
			get_post_meta( $post_id, 'nh_sources', true )
		);
		$this->assertSame( '2024-01-15', get_post_meta( $post_id, 'nh_content_updated', true ) );
		$this->assertSame( (string) $pillar, get_post_meta( $post_id, 'nh_pillar_id', true ) );
		$this->assertSame( '1', get_post_meta( $post_id, 'nh_local_business', true ) );
		$this->assertSame( 'scooter, rental', get_post_meta( $post_id, 'nh_keywords', true ) );
		$this->assertSame( 'scooter-rental-basics', get_post_meta( $post_id, 'nh_topic_cluster', true ) );
	}

	public function test_the_write_response_and_a_later_get_return_the_sanitized_values() {
		$pillar   = $this->post();
		$response = $this->dispatch( 'POST', '/wp/v2/posts', $this->valid_payload( $pillar ) );
		$post_id  = $response->get_data()['id'];

		$cluster = get_term_by( 'slug', 'scooter-rental-basics', 'topic_cluster' );
		$this->assertInstanceOf( WP_Term::class, $cluster, 'the alias created the cluster' );

		foreach ( array( $response, $this->dispatch( 'GET', '/wp/v2/posts/' . $post_id ) ) as $result ) {
			$data = $result->get_data();
			$meta = $data['meta'];
			$this->assertSame( 'Pick an automatic scooter for city riding.', $meta['nh_summary'] );
			$this->assertSame( array( 'Automatic scooters are easiest in traffic.', 'Check the helmet.' ), $meta['nh_takeaways'] );
			$this->assertCount( 1, $meta['nh_faq'] );
			$this->assertCount( 1, $meta['nh_sources'] );
			$this->assertSame( '2024-01-15', $meta['nh_content_updated'] );
			$this->assertSame( $pillar, $meta['nh_pillar_id'] );
			$this->assertTrue( $meta['nh_local_business'] );
			$this->assertSame( 'scooter, rental', $meta['nh_keywords'] );
			$this->assertSame( $cluster->term_id, $meta['nh_primary_cluster'] );
			$this->assertSame( array( $cluster->term_id ), $data['topic_clusters'] );
		}
	}

	public function test_missing_meta_reads_as_documented_defaults() {
		$post_id = $this->post();
		$meta    = $this->dispatch( 'GET', '/wp/v2/posts/' . $post_id )->get_data()['meta'];

		$this->assertSame( '', $meta['nh_summary'] );
		$this->assertSame( array(), $meta['nh_takeaways'] );
		$this->assertSame( array(), $meta['nh_faq'] );
		$this->assertSame( array(), $meta['nh_sources'] );
		$this->assertSame( '', $meta['nh_content_updated'] );
		$this->assertSame( 0, $meta['nh_pillar_id'] );
		$this->assertSame( 0, $meta['nh_primary_cluster'] );
		$this->assertFalse( $meta['nh_local_business'] );
		$this->assertArrayNotHasKey( 'nh_hub_term', $meta, 'hub terms are for pages only' );
	}

	public function test_update_changes_only_the_keys_that_are_sent() {
		$post_id = $this->post();
		update_post_meta( $post_id, 'nh_summary', 'Original summary' );
		update_post_meta( $post_id, 'nh_keywords', 'keep, me' );

		$response = $this->dispatch( 'POST', '/wp/v2/posts/' . $post_id, array( 'meta' => array( 'nh_summary' => 'Updated summary' ) ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'Updated summary', get_post_meta( $post_id, 'nh_summary', true ) );
		$this->assertSame( 'keep, me', get_post_meta( $post_id, 'nh_keywords', true ) );
	}

	public function test_sending_the_same_payload_twice_succeeds_both_times() {
		$pillar  = $this->post();
		$payload = $this->valid_payload( $pillar );
		$first   = $this->dispatch( 'POST', '/wp/v2/posts', $payload );
		$post_id = $first->get_data()['id'];

		$update = array( 'meta' => $payload['meta'] );
		$second = $this->dispatch( 'POST', '/wp/v2/posts/' . $post_id, $update );
		$third  = $this->dispatch( 'POST', '/wp/v2/posts/' . $post_id, $update );

		$this->assertSame( 200, $second->get_status(), wp_json_encode( $second->get_data() ) );
		$this->assertSame( 200, $third->get_status(), wp_json_encode( $third->get_data() ) );
		$this->assertSame( $first->get_data()['meta'], $third->get_data()['meta'] );
	}

	public function test_null_deletes_a_meta_key() {
		$post_id = $this->post();
		update_post_meta( $post_id, 'nh_summary', 'To be removed' );

		$response = $this->dispatch( 'POST', '/wp/v2/posts/' . $post_id, array( 'meta' => array( 'nh_summary' => null ) ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( '', get_post_meta( $post_id, 'nh_summary', true ) );
		$this->assertFalse( metadata_exists( 'post', $post_id, 'nh_summary' ), 'the row is gone, not blanked' );
	}

	public function test_limits_truncate_instead_of_failing_the_publish_request() {
		$items = array();
		for ( $i = 0; $i < 25; $i++ ) {
			$items[] = array(
				'question' => 'Question ' . $i . '?',
				'answer'   => 'Answer ' . $i,
			);
		}
		$sources = array();
		for ( $i = 0; $i < 20; $i++ ) {
			$sources[] = array( 'url' => 'https://example.org/' . $i );
		}
		$response = $this->dispatch(
			'POST',
			'/wp/v2/posts',
			array(
				'title'  => 'Limits',
				'status' => 'publish',
				'meta'   => array(
					'nh_summary'   => str_repeat( 'đ', 2000 ),
					'nh_takeaways' => array_fill( 0, 15, 'Point' ),
					'nh_faq'       => $items,
					'nh_sources'   => $sources,
					'nh_keywords'  => str_repeat( 'word, ', 200 ),
				),
			)
		);

		$this->assertSame( 201, $response->get_status() );
		$post_id = $response->get_data()['id'];
		$this->assertSame( NH_SEO_Keys::LIMIT_SUMMARY, mb_strlen( get_post_meta( $post_id, 'nh_summary', true ) ) );
		$this->assertCount( NH_SEO_Keys::LIMIT_TAKEAWAYS, get_post_meta( $post_id, 'nh_takeaways', true ) );
		$this->assertCount( NH_SEO_Keys::LIMIT_FAQ_ITEMS, get_post_meta( $post_id, 'nh_faq', true ) );
		$this->assertCount( NH_SEO_Keys::LIMIT_SOURCES, get_post_meta( $post_id, 'nh_sources', true ) );
		$this->assertLessThanOrEqual( NH_SEO_Keys::LIMIT_KEYWORDS, mb_strlen( get_post_meta( $post_id, 'nh_keywords', true ) ) );
	}

	public function test_invalid_values_are_dropped_not_stored() {
		$draft    = $this->post( array( 'post_status' => 'draft' ) );
		$response = $this->dispatch(
			'POST',
			'/wp/v2/posts',
			array(
				'title'  => 'Invalid values',
				'status' => 'publish',
				'meta'   => array(
					'nh_content_updated' => '2999-12-31',
					'nh_pillar_id'       => $draft,
					'nh_primary_cluster' => 987654,
				),
			)
		);

		$this->assertSame( 201, $response->get_status() );
		$post_id = $response->get_data()['id'];
		$this->assertSame( '', get_post_meta( $post_id, 'nh_content_updated', true ) );
		$this->assertSame( 0, (int) get_post_meta( $post_id, 'nh_pillar_id', true ), 'an unpublished Pillar is dropped' );
		$this->assertSame( 0, (int) get_post_meta( $post_id, 'nh_primary_cluster', true ) );
	}

	public function test_a_malicious_payload_is_neutralized() {
		$response = $this->dispatch(
			'POST',
			'/wp/v2/posts',
			array(
				'title'  => 'Hostile',
				'status' => 'publish',
				'meta'   => array(
					'nh_summary'       => '<script>alert(1)</script>Safe <img src=x onerror=alert(2)>text',
					'nh_takeaways'     => array( '<script>alert(3)</script>ok', '<b onmouseover=alert(4)>bold</b>' ),
					'nh_faq'           => array(
						array(
							'question' => '<img src=x onerror=alert(5)>Question',
							'answer'   => '<iframe src="javascript:alert(6)"></iframe><a href="javascript:alert(7)" onclick="evil()" style="x">link</a><p style="color:red" onmouseover="y()">text</p><svg onload=alert(8)>',
						),
					),
					'nh_sources'       => array(
						array(
							'title'     => '<script>alert(9)</script>Title',
							'url'       => 'https://example.org/page"onmouseover="alert(10)',
							'publisher' => '<b>Pub</b>',
						),
						array(
							'title' => 'Data URL',
							'url'   => 'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
						),
						array(
							'title' => 'VBScript',
							'url'   => 'vbscript:msgbox(1)',
						),
					),
					'nh_keywords'      => '<script>alert(11)</script>keyword',
					'nh_topic_cluster' => '<script>alert(12)</script>Cluster',
				),
			)
		);

		$this->assertSame( 201, $response->get_status() );
		$post_id = $response->get_data()['id'];

		$summary   = get_post_meta( $post_id, 'nh_summary', true );
		$takeaways = get_post_meta( $post_id, 'nh_takeaways', true );
		$faq       = get_post_meta( $post_id, 'nh_faq', true );
		$sources   = get_post_meta( $post_id, 'nh_sources', true );
		$keywords  = get_post_meta( $post_id, 'nh_keywords', true );
		$alias     = get_post_meta( $post_id, 'nh_topic_cluster', true );
		$clusters  = wp_list_pluck( wp_get_object_terms( $post_id, 'topic_cluster' ), 'name' );

		$all = wp_json_encode( array( $summary, $takeaways, $faq, $sources, $keywords, $alias, $clusters ) );
		foreach ( array( '<script', '<img', '<iframe', '<svg', 'javascript:', 'vbscript:', 'data:text', 'style=' ) as $needle ) {
			$this->assertStringNotContainsStringIgnoringCase( $needle, $all, "stored data must not contain $needle" );
		}
		// No tag may carry an event handler attribute, in any stored HTML.
		$this->assertDoesNotMatchRegularExpression( '/<[^>]*\bon[a-z]+\s*=/i', wp_json_encode( array( $faq, $sources, $takeaways ) ) );

		$this->assertSame( 'Safe text', $summary );
		$this->assertSame( array( 'ok', 'bold' ), $takeaways );
		$this->assertSame( 'Question', $faq[0]['question'] );
		$this->assertStringNotContainsString( 'onclick', $faq[0]['answer'] );
		$this->assertCount( 1, $sources, 'only the http(s) source survives' );
		$this->assertSame( 'Title', $sources[0]['title'] );
		$this->assertSame( 'Pub', $sources[0]['publisher'] );
		$this->assertStringStartsWith( 'https://example.org/', $sources[0]['url'] );
		$this->assertDoesNotMatchRegularExpression( '/["\'\s<>]/', $sources[0]['url'], 'quotes cannot break out of an href attribute' );
		$this->assertSame( 'keyword', $keywords );
		$this->assertSame( array( 'Cluster' ), $clusters );
	}

	public function test_values_of_the_wrong_type_are_rejected_with_a_400() {
		$post_id  = $this->post();
		$payloads = array(
			'takeaways as objects' => array( 'nh_takeaways' => array( array( 'a' => 'b' ) ) ),
			'faq as string'        => array( 'nh_faq' => 'q and a' ),
			'faq items as strings' => array( 'nh_faq' => array( 'just a string' ) ),
			'faq as object'        => array(
				'nh_faq' => array(
					'question' => 'Q',
					'answer'   => 'A',
				),
			),
			'sources as string'    => array( 'nh_sources' => 'https://example.org' ),
			'sources as strings'   => array( 'nh_sources' => array( 'https://example.org' ) ),
			'pillar as text'       => array( 'nh_pillar_id' => '1 OR 1=1' ),
			'pillar as array'      => array( 'nh_pillar_id' => array( 1 ) ),
			'flag as array'        => array( 'nh_local_business' => array( 'yes' ) ),
			'summary as array'     => array( 'nh_summary' => array( 'x' ) ),
			'keywords as object'   => array( 'nh_keywords' => array( 'a' => 'b' ) ),
		);
		$accepted = array();
		foreach ( $payloads as $label => $meta ) {
			$response = $this->dispatch( 'POST', '/wp/v2/posts/' . $post_id, array( 'meta' => $meta ) );
			if ( 400 !== $response->get_status() ) {
				$accepted[] = $label . ' => ' . $response->get_status();
			}
		}
		$this->assertSame( array(), $accepted, 'every payload of the wrong type must be rejected with a 400' );
		$this->assertFalse( metadata_exists( 'post', $post_id, 'nh_summary' ) );
		$this->assertFalse( metadata_exists( 'post', $post_id, 'nh_pillar_id' ) );
	}

	public function test_core_splits_comma_separated_strings_for_list_fields() {
		// WordPress itself converts "a, b" to an array for array-typed fields before the sanitizers run.
		$post_id  = $this->post();
		$response = $this->dispatch( 'POST', '/wp/v2/posts/' . $post_id, array( 'meta' => array( 'nh_takeaways' => 'one, two' ) ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'one', 'two' ), get_post_meta( $post_id, 'nh_takeaways', true ) );
	}

	public function test_unregistered_meta_keys_are_ignored_and_never_stored() {
		$post_id  = $this->post();
		$response = $this->dispatch(
			'POST',
			'/wp/v2/posts/' . $post_id,
			array(
				'meta' => array(
					'nh_not_in_the_contract' => 'x',
					'_edit_lock'             => 'x',
				),
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( metadata_exists( 'post', $post_id, 'nh_not_in_the_contract' ) );
		$this->assertSame( '', get_post_meta( $post_id, '_edit_lock', true ) );
	}

	public function test_protected_plugin_meta_cannot_be_written() {
		$post_id  = $this->post();
		$response = $this->dispatch( 'POST', '/wp/v2/posts/' . $post_id, array( 'meta' => array( NH_SEO_Cluster_Sync::SYNCED_META => 'x' ) ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( metadata_exists( 'post', $post_id, NH_SEO_Cluster_Sync::SYNCED_META ) );
	}

	public function test_hub_term_is_for_pages_only() {
		$category = self::factory()->category->create();

		$post_response = $this->dispatch(
			'POST',
			'/wp/v2/posts',
			array(
				'title'  => 'A post',
				'status' => 'publish',
				'meta'   => array( 'nh_hub_term' => "category:$category" ),
			)
		);
		$this->assertSame( 201, $post_response->get_status() );
		$this->assertFalse( metadata_exists( 'post', $post_response->get_data()['id'], 'nh_hub_term' ), 'ignored on posts' );

		$page_response = $this->dispatch(
			'POST',
			'/wp/v2/pages',
			array(
				'title'  => 'A hub page',
				'status' => 'publish',
				'meta'   => array( 'nh_hub_term' => "category:$category" ),
			)
		);
		$this->assertSame( 201, $page_response->get_status(), wp_json_encode( $page_response->get_data() ) );
		$this->assertSame( "category:$category", get_post_meta( $page_response->get_data()['id'], 'nh_hub_term', true ) );

		$bad = $this->dispatch( 'POST', '/wp/v2/pages/' . $page_response->get_data()['id'], array( 'meta' => array( 'nh_hub_term' => 'category:999999' ) ) );
		$this->assertSame( 200, $bad->get_status() );
		$this->assertSame( '', get_post_meta( $page_response->get_data()['id'], 'nh_hub_term', true ), 'an unknown term is dropped' );
	}

	public function test_pages_accept_the_shared_meta_but_never_get_clusters() {
		$response = $this->dispatch(
			'POST',
			'/wp/v2/pages',
			array(
				'title'  => 'A page',
				'status' => 'publish',
				'meta'   => array(
					'nh_summary'        => '<b>Page</b> summary',
					'nh_local_business' => true,
					'nh_topic_cluster'  => 'Page cluster',
				),
			)
		);

		$this->assertSame( 201, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$page_id = $response->get_data()['id'];
		$this->assertSame( 'Page summary', get_post_meta( $page_id, 'nh_summary', true ) );
		$this->assertSame( array(), wp_get_object_terms( $page_id, 'topic_cluster' ) );
		$this->assertFalse( get_term_by( 'name', 'Page cluster', 'topic_cluster' ) );
		$this->assertArrayNotHasKey( 'topic_clusters', $response->get_data() );
	}

	public function test_unauthenticated_requests_cannot_write() {
		$post_id = $this->post();
		update_post_meta( $post_id, 'nh_summary', 'Untouched' );
		wp_set_current_user( 0 );

		$create = $this->dispatch( 'POST', '/wp/v2/posts', $this->valid_payload( $post_id ) );
		$update = $this->dispatch( 'POST', '/wp/v2/posts/' . $post_id, array( 'meta' => array( 'nh_summary' => 'Hacked' ) ) );
		$delete = $this->dispatch( 'POST', '/wp/v2/posts/' . $post_id, array( 'meta' => array( 'nh_summary' => null ) ) );

		$this->assertSame( 401, $create->get_status() );
		$this->assertSame( 401, $update->get_status() );
		$this->assertSame( 401, $delete->get_status() );
		$this->assertSame( 'Untouched', get_post_meta( $post_id, 'nh_summary', true ) );
		$this->assertFalse( get_term_by( 'slug', 'scooter-rental-basics', 'topic_cluster' ), 'no cluster is created by an anonymous request' );
	}

	public function test_subscribers_cannot_write() {
		$post_id = $this->post();
		update_post_meta( $post_id, 'nh_summary', 'Untouched' );
		wp_set_current_user( $this->users['subscriber'] );

		$create = $this->dispatch( 'POST', '/wp/v2/posts', $this->valid_payload( $post_id ) );
		$update = $this->dispatch( 'POST', '/wp/v2/posts/' . $post_id, array( 'meta' => array( 'nh_summary' => 'Hacked' ) ) );

		$this->assertSame( 403, $create->get_status() );
		$this->assertSame( 403, $update->get_status() );
		$this->assertSame( 'Untouched', get_post_meta( $post_id, 'nh_summary', true ) );
		$this->assertFalse( get_term_by( 'slug', 'scooter-rental-basics', 'topic_cluster' ) );
	}

	public function test_authors_can_write_their_own_posts_but_not_other_peoples() {
		$own   = $this->post( array( 'post_author' => $this->users['author'] ) );
		$other = $this->post( array( 'post_author' => $this->users['editor'] ) );
		update_post_meta( $other, 'nh_summary', 'Not yours' );
		wp_set_current_user( $this->users['author'] );

		$mine   = $this->dispatch( 'POST', '/wp/v2/posts/' . $own, array( 'meta' => array( 'nh_summary' => 'Mine' ) ) );
		$theirs = $this->dispatch( 'POST', '/wp/v2/posts/' . $other, array( 'meta' => array( 'nh_summary' => 'Hacked' ) ) );

		$this->assertSame( 200, $mine->get_status() );
		$this->assertSame( 'Mine', get_post_meta( $own, 'nh_summary', true ) );
		$this->assertSame( 403, $theirs->get_status() );
		$this->assertSame( 'Not yours', get_post_meta( $other, 'nh_summary', true ) );
	}

	public function test_contributors_can_write_meta_on_their_own_drafts_only() {
		wp_set_current_user( $this->users['contributor'] );

		$create = $this->dispatch(
			'POST',
			'/wp/v2/posts',
			array(
				'title'  => 'Draft',
				'status' => 'draft',
				'meta'   => array( 'nh_summary' => 'Draft summary' ),
			)
		);
		$this->assertSame( 201, $create->get_status(), wp_json_encode( $create->get_data() ) );
		$this->assertSame( 'Draft summary', get_post_meta( $create->get_data()['id'], 'nh_summary', true ) );

		$someone_elses = $this->post(
			array(
				'post_status' => 'draft',
				'post_author' => $this->users['editor'],
			)
		);
		$denied        = $this->dispatch( 'POST', '/wp/v2/posts/' . $someone_elses, array( 'meta' => array( 'nh_summary' => 'Nope' ) ) );
		$this->assertSame( 403, $denied->get_status() );
	}

	public function test_a_user_without_edit_rights_cannot_change_meta_through_the_meta_api_either() {
		$post_id = $this->post();
		wp_set_current_user( $this->users['subscriber'] );
		$this->assertFalse( current_user_can( 'edit_post_meta', $post_id, 'nh_summary' ) );
		$this->assertFalse( current_user_can( 'add_post_meta', $post_id, 'nh_faq' ) );
		$this->assertFalse( current_user_can( 'delete_post_meta', $post_id, 'nh_sources' ) );
	}

	public function test_published_meta_is_readable_without_authentication() {
		$post_id = $this->post();
		update_post_meta( $post_id, 'nh_summary', 'Public summary' );
		wp_set_current_user( 0 );

		$response = $this->dispatch( 'GET', '/wp/v2/posts/' . $post_id );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'Public summary', $response->get_data()['meta']['nh_summary'] );
	}

	public function test_draft_meta_is_not_readable_without_authentication() {
		$post_id = $this->post( array( 'post_status' => 'draft' ) );
		update_post_meta( $post_id, 'nh_summary', 'Secret draft' );
		wp_set_current_user( 0 );

		$response = $this->dispatch( 'GET', '/wp/v2/posts/' . $post_id );

		$this->assertGreaterThanOrEqual( 401, $response->get_status() );
		$this->assertStringNotContainsString( 'Secret draft', wp_json_encode( $response->get_data() ) );
	}

	public function test_alias_through_rest_creates_and_assigns_the_cluster_as_primary() {
		$existing = self::factory()->term->create(
			array(
				'taxonomy' => 'topic_cluster',
				'name'     => 'Existing',
			)
		);
		$response = $this->dispatch(
			'POST',
			'/wp/v2/posts',
			array(
				'title'          => 'With clusters',
				'status'         => 'publish',
				'topic_clusters' => array( $existing ),
				'meta'           => array( 'nh_topic_cluster' => 'Brand new cluster' ),
			)
		);

		$this->assertSame( 201, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$data = $response->get_data();
		$new  = get_term_by( 'name', 'Brand new cluster', 'topic_cluster' );
		$this->assertInstanceOf( WP_Term::class, $new );
		$this->assertEqualSets( array( $existing, $new->term_id ), $data['topic_clusters'], 'existing terms are kept' );
		$this->assertSame( $new->term_id, $data['meta']['nh_primary_cluster'] );
		$this->assertSame( $new->term_id, nh_seo_get_primary_cluster( $data['id'] )->term_id );
	}

	public function test_alias_through_rest_respects_the_auto_create_setting() {
		update_option( NH_SEO_Keys::OPTION_SETTINGS, array( 'auto_cluster_terms' => 0 ) );

		$response = $this->dispatch(
			'POST',
			'/wp/v2/posts',
			array(
				'title'  => 'No creation',
				'status' => 'publish',
				'meta'   => array( 'nh_topic_cluster' => 'Never created' ),
			)
		);

		$this->assertSame( 201, $response->get_status() );
		$this->assertFalse( get_term_by( 'name', 'Never created', 'topic_cluster' ) );
		$this->assertSame( array(), $response->get_data()['topic_clusters'] );
	}

	public function test_resending_an_unchanged_alias_through_rest_applies_it_again() {
		$post_id = $this->post();
		$this->dispatch( 'POST', '/wp/v2/posts/' . $post_id, array( 'meta' => array( 'nh_topic_cluster' => 'Sticky' ) ) );
		$term = get_term_by( 'name', 'Sticky', 'topic_cluster' );
		wp_remove_object_terms( $post_id, $term->term_id, 'topic_cluster' );
		$this->assertSame( array(), wp_get_object_terms( $post_id, 'topic_cluster' ) );

		// The alias value did not change, so no meta hook fires: the REST hook applies it on purpose.
		$response = $this->dispatch( 'POST', '/wp/v2/posts/' . $post_id, array( 'meta' => array( 'nh_topic_cluster' => 'Sticky' ) ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( $term->term_id ), $response->get_data()['topic_clusters'] );
	}

	public function test_saving_through_rest_without_the_alias_never_touches_clusters() {
		$post_id = $this->post();
		$this->dispatch( 'POST', '/wp/v2/posts/' . $post_id, array( 'meta' => array( 'nh_topic_cluster' => 'Stays' ) ) );
		$term = get_term_by( 'name', 'Stays', 'topic_cluster' );
		wp_remove_object_terms( $post_id, $term->term_id, 'topic_cluster' );

		$response = $this->dispatch( 'POST', '/wp/v2/posts/' . $post_id, array( 'title' => 'Renamed' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array(), $response->get_data()['topic_clusters'] );
	}

	public function test_changing_the_alias_through_rest_adds_a_cluster_and_keeps_the_old_one() {
		$post_id = $this->post();
		$this->dispatch( 'POST', '/wp/v2/posts/' . $post_id, array( 'meta' => array( 'nh_topic_cluster' => 'First' ) ) );
		$response = $this->dispatch( 'POST', '/wp/v2/posts/' . $post_id, array( 'meta' => array( 'nh_topic_cluster' => 'Second' ) ) );

		$first  = get_term_by( 'name', 'First', 'topic_cluster' );
		$second = get_term_by( 'name', 'Second', 'topic_cluster' );
		$this->assertEqualSets( array( $first->term_id, $second->term_id ), $response->get_data()['topic_clusters'] );
		$this->assertSame( $second->term_id, $response->get_data()['meta']['nh_primary_cluster'] );
	}

	public function test_cluster_terms_have_a_rest_endpoint_with_the_expected_permissions() {
		$editor_create = null;

		wp_set_current_user( $this->users['editor'] );
		$editor_create = $this->dispatch( 'POST', '/wp/v2/topic_clusters', array( 'name' => 'Editor cluster' ) );
		$this->assertSame( 201, $editor_create->get_status(), wp_json_encode( $editor_create->get_data() ) );
		$term_id = $editor_create->get_data()['id'];
		$this->assertSame( 'topic_cluster', $editor_create->get_data()['taxonomy'] );

		$list = $this->dispatch( 'GET', '/wp/v2/topic_clusters' );
		$this->assertSame( 200, $list->get_status() );
		$this->assertContains( $term_id, wp_list_pluck( $list->get_data(), 'id' ) );

		wp_set_current_user( $this->users['subscriber'] );
		$this->assertSame( 403, $this->dispatch( 'POST', '/wp/v2/topic_clusters', array( 'name' => 'Nope' ) )->get_status() );

		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->dispatch( 'POST', '/wp/v2/topic_clusters', array( 'name' => 'Nope' ) )->get_status() );
		$this->assertSame( 401, $this->dispatch( 'DELETE', '/wp/v2/topic_clusters/' . $term_id, array( 'force' => true ) )->get_status() );
		$this->assertInstanceOf( WP_Term::class, get_term( $term_id, 'topic_cluster' ) );

		wp_set_current_user( $this->users['author'] );
		$this->assertSame( 403, $this->dispatch( 'DELETE', '/wp/v2/topic_clusters/' . $term_id, array( 'force' => true ) )->get_status() );
	}

	public function test_posts_accept_cluster_ids_in_the_topic_clusters_field() {
		$cluster  = self::factory()->term->create( array( 'taxonomy' => 'topic_cluster' ) );
		$response = $this->dispatch(
			'POST',
			'/wp/v2/posts',
			array(
				'title'          => 'Assigned',
				'status'         => 'publish',
				'topic_clusters' => array( $cluster ),
			)
		);

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( array( $cluster ), $response->get_data()['topic_clusters'] );
		$this->assertSame( $cluster, nh_seo_get_primary_cluster( $response->get_data()['id'] )->term_id );
	}

	public function test_term_pillar_can_be_written_by_editors_only() {
		$pillar   = $this->post();
		$draft    = $this->post( array( 'post_status' => 'draft' ) );
		$category = self::factory()->category->create();
		$cluster  = self::factory()->term->create( array( 'taxonomy' => 'topic_cluster' ) );

		wp_set_current_user( $this->users['editor'] );
		foreach ( array(
			'/wp/v2/categories/' . $category    => $category,
			'/wp/v2/topic_clusters/' . $cluster => $cluster,
		) as $route => $term_id ) {
			$taxonomy = false !== strpos( $route, 'categories' ) ? 'category' : 'topic_cluster';

			$ok = $this->dispatch( 'POST', $route, array( 'meta' => array( 'nh_pillar_id' => $pillar ) ) );
			$this->assertSame( 200, $ok->get_status(), $route . ' ' . wp_json_encode( $ok->get_data() ) );
			$this->assertSame( (string) $pillar, get_term_meta( $term_id, 'nh_pillar_id', true ) );
			$this->assertSame( $pillar, $ok->get_data()['meta']['nh_pillar_id'] );

			$bad = $this->dispatch( 'POST', $route, array( 'meta' => array( 'nh_pillar_id' => $draft ) ) );
			$this->assertSame( 200, $bad->get_status() );
			$this->assertSame( '0', get_term_meta( $term_id, 'nh_pillar_id', true ), $taxonomy . ': an unpublished Pillar is dropped' );

			$text = $this->dispatch( 'POST', $route, array( 'meta' => array( 'nh_pillar_id' => 'abc' ) ) );
			$this->assertSame( 400, $text->get_status() );

			update_term_meta( $term_id, 'nh_pillar_id', $pillar );
			wp_set_current_user( $this->users['author'] );
			$denied = $this->dispatch( 'POST', $route, array( 'meta' => array( 'nh_pillar_id' => 0 ) ) );
			$this->assertSame( 403, $denied->get_status(), $route );
			$this->assertSame( (string) $pillar, get_term_meta( $term_id, 'nh_pillar_id', true ) );

			wp_set_current_user( 0 );
			$anon = $this->dispatch( 'POST', $route, array( 'meta' => array( 'nh_pillar_id' => 0 ) ) );
			$this->assertSame( 401, $anon->get_status(), $route );
			$this->assertSame( (string) $pillar, get_term_meta( $term_id, 'nh_pillar_id', true ) );

			wp_set_current_user( $this->users['editor'] );
		}
	}

	public function test_the_post_schema_documents_every_field() {
		$response = $this->dispatch( 'OPTIONS', '/wp/v2/posts' );
		$schema   = $response->get_data()['schema']['properties']['meta']['properties'];

		foreach ( array( 'nh_summary', 'nh_takeaways', 'nh_faq', 'nh_sources', 'nh_content_updated', 'nh_topic_cluster', 'nh_primary_cluster', 'nh_pillar_id', 'nh_local_business', 'nh_keywords' ) as $key ) {
			$this->assertArrayHasKey( $key, $schema, $key );
			$this->assertNotEmpty( $schema[ $key ]['description'], "$key has a description" );
		}
		$this->assertSame( 'array', $schema['nh_faq']['type'] );
		$this->assertSame( 'object', $schema['nh_faq']['items']['type'] );
		$this->assertSame( 'string', $schema['nh_faq']['items']['properties']['question']['type'] );
		$this->assertSame( 'integer', $schema['nh_pillar_id']['type'] );
		$this->assertSame( 'boolean', $schema['nh_local_business']['type'] );
		$this->assertArrayNotHasKey( 'nh_hub_term', $schema );

		$page_schema = $this->dispatch( 'OPTIONS', '/wp/v2/pages' )->get_data()['schema']['properties']['meta']['properties'];
		$this->assertArrayHasKey( 'nh_hub_term', $page_schema );
	}
}
