<?php
/**
 * Tests for the data model: taxonomy and post/term/user meta registration.
 *
 * @package nguyen-ha-seo-companion
 */

/**
 * Registration of the Topic Cluster taxonomy and of every meta key of the data contract.
 */
class NH_Test_Data_Model extends NH_Unit_Test_Case {

	public function set_up() {
		parent::set_up();
		remove_theme_support( 'nguyen-ha-companion' );
	}

	public function test_taxonomy_is_flat_non_public_and_exposed_to_rest() {
		$taxonomy = get_taxonomy( 'topic_cluster' );
		$this->assertInstanceOf( WP_Taxonomy::class, $taxonomy );
		$this->assertFalse( $taxonomy->hierarchical );
		$this->assertFalse( $taxonomy->public );
		$this->assertFalse( $taxonomy->publicly_queryable );
		$this->assertTrue( $taxonomy->show_ui );
		$this->assertTrue( $taxonomy->show_admin_column );
		$this->assertFalse( $taxonomy->show_in_nav_menus );
		$this->assertFalse( $taxonomy->show_tagcloud );
		$this->assertFalse( $taxonomy->rewrite );
		$this->assertFalse( $taxonomy->query_var );
		$this->assertTrue( $taxonomy->show_in_rest );
		$this->assertSame( 'topic_clusters', $taxonomy->rest_base );
		$this->assertSame( array( 'post' ), $taxonomy->object_type );
		$this->assertTrue( is_object_in_taxonomy( 'post', 'topic_cluster' ) );
		$this->assertFalse( is_object_in_taxonomy( 'page', 'topic_cluster' ) );
	}

	public function test_taxonomy_is_registered_on_init_before_default_priority() {
		$this->assertSame( 5, has_action( 'init', array( 'NH_SEO_Data_Model', 'register_taxonomy' ) ) );
		$this->assertIsInt( has_action( 'init', array( 'NH_SEO_Data_Model', 'register_meta' ) ) );
	}

	public function test_cluster_terms_cannot_be_queried_as_a_front_end_archive() {
		$term_id = self::factory()->term->create(
			array(
				'taxonomy' => 'topic_cluster',
				'name'     => 'Scooter basics',
			)
		);
		$inside  = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$outside = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		wp_set_object_terms( $inside, array( $term_id ), 'topic_cluster' );

		$this->use_permalinks();
		foreach ( array( '/?taxonomy=topic_cluster&term=scooter-basics', '/?topic_cluster=scooter-basics', '/topic_cluster/scooter-basics/' ) as $path ) {
			$this->go_to( home_url( $path ) );
			$this->assertFalse( is_tax(), $path );
			$this->assertFalse( is_archive(), $path );
			if ( is_home() ) {
				// The query var is ignored: the request is a plain home query, not filtered by the cluster.
				$this->assertContains( $outside, wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ), $path );
			}
		}
	}

	public function test_cluster_taxonomy_is_not_listed_in_core_sitemaps() {
		$provider = wp_sitemaps_get_server()->registry->get_provider( 'taxonomies' );
		$this->assertNotNull( $provider );
		$this->assertArrayNotHasKey( 'topic_cluster', $provider->get_object_subtypes() );
	}

	public function test_post_meta_is_registered_for_posts_and_pages() {
		$shared = array(
			'nh_summary',
			'nh_takeaways',
			'nh_faq',
			'nh_sources',
			'nh_content_updated',
			'nh_topic_cluster',
			'nh_primary_cluster',
			'nh_pillar_id',
			'nh_local_business',
			'nh_keywords',
		);
		foreach ( $shared as $key ) {
			$this->assertTrue( registered_meta_key_exists( 'post', $key, 'post' ), "$key on posts" );
			$this->assertTrue( registered_meta_key_exists( 'post', $key, 'page' ), "$key on pages" );
		}
	}

	public function test_hub_term_is_registered_for_pages_only() {
		$this->assertTrue( registered_meta_key_exists( 'post', 'nh_hub_term', 'page' ) );
		$this->assertFalse( registered_meta_key_exists( 'post', 'nh_hub_term', 'post' ) );
	}

	public function test_post_meta_is_single_and_visible_in_rest_with_item_schemas() {
		$keys = get_registered_meta_keys( 'post', 'post' );
		foreach ( NH_SEO_Data_Model::post_meta_definitions() as $key => $definition ) {
			if ( ! in_array( 'post', $definition['post_types'], true ) ) {
				continue;
			}
			$this->assertArrayHasKey( $key, $keys );
			$this->assertTrue( $keys[ $key ]['single'], "$key is single" );
			$this->assertNotEmpty( $keys[ $key ]['show_in_rest'], "$key is in REST" );
		}
		$this->assertSame( 'string', $keys['nh_takeaways']['show_in_rest']['schema']['items']['type'] );
		$this->assertSame( 'object', $keys['nh_faq']['show_in_rest']['schema']['items']['type'] );
		$this->assertArrayHasKey( 'question', $keys['nh_faq']['show_in_rest']['schema']['items']['properties'] );
		$this->assertArrayHasKey( 'answer', $keys['nh_faq']['show_in_rest']['schema']['items']['properties'] );
		$this->assertSame( 'object', $keys['nh_sources']['show_in_rest']['schema']['items']['type'] );
		$this->assertSame( 'boolean', $keys['nh_local_business']['type'] );
		$this->assertSame( 'integer', $keys['nh_pillar_id']['type'] );
		$this->assertSame( 'integer', $keys['nh_primary_cluster']['type'] );
	}

	public function test_term_meta_is_registered_for_categories_and_clusters() {
		foreach ( array( 'category', 'topic_cluster' ) as $taxonomy ) {
			$this->assertTrue( registered_meta_key_exists( 'term', 'nh_pillar_id', $taxonomy ), $taxonomy );
			$args = get_registered_meta_keys( 'term', $taxonomy );
			$this->assertSame( 'integer', $args['nh_pillar_id']['type'] );
			$this->assertTrue( $args['nh_pillar_id']['single'] );
			$this->assertTrue( $args['nh_pillar_id']['show_in_rest'] );
		}
		$this->assertFalse( registered_meta_key_exists( 'term', 'nh_pillar_id', 'post_tag' ) );
	}

	public function test_user_meta_is_registered_but_not_exposed_to_rest() {
		foreach ( array( 'nh_job_title', 'nh_sameas' ) as $key ) {
			$this->assertTrue( registered_meta_key_exists( 'user', $key ), $key );
			$args = get_registered_meta_keys( 'user' );
			$this->assertFalse( (bool) $args[ $key ]['show_in_rest'], "$key must not be in REST" );
		}
	}

	public function test_post_meta_sanitizers_are_wired_to_the_shared_functions() {
		$post_id = self::factory()->post->create();

		update_post_meta( $post_id, 'nh_summary', "  <b>Short</b>\n answer " );
		$this->assertSame( 'Short answer', get_post_meta( $post_id, 'nh_summary', true ) );

		update_post_meta( $post_id, 'nh_takeaways', array( 'One', '', '<i>Two</i>' ) );
		$this->assertSame( array( 'One', 'Two' ), get_post_meta( $post_id, 'nh_takeaways', true ) );

		update_post_meta(
			$post_id,
			'nh_faq',
			array(
				array(
					'question' => 'Q?',
					'answer'   => '<p onclick="x()">A</p><script>alert(1)</script>',
				),
			)
		);
		$faq = get_post_meta( $post_id, 'nh_faq', true );
		$this->assertStringStartsWith( '<p>A</p>', $faq[0]['answer'] );
		$this->assertStringNotContainsString( '<script', $faq[0]['answer'] );
		$this->assertStringNotContainsString( 'onclick', $faq[0]['answer'] );

		update_post_meta(
			$post_id,
			'nh_sources',
			array(
				array( 'url' => 'javascript:alert(1)' ),
				array( 'url' => 'https://example.org/page' ),
			)
		);
		$this->assertCount( 1, get_post_meta( $post_id, 'nh_sources', true ) );

		update_post_meta( $post_id, 'nh_content_updated', '2999-01-01' );
		$this->assertSame( '', get_post_meta( $post_id, 'nh_content_updated', true ) );
		update_post_meta( $post_id, 'nh_content_updated', '2024-05-06' );
		$this->assertSame( '2024-05-06', get_post_meta( $post_id, 'nh_content_updated', true ) );

		update_post_meta( $post_id, 'nh_local_business', 'on' );
		$this->assertSame( '1', get_post_meta( $post_id, 'nh_local_business', true ) );
		update_post_meta( $post_id, 'nh_local_business', 'anything else' );
		$this->assertSame( '0', get_post_meta( $post_id, 'nh_local_business', true ) );

		update_post_meta( $post_id, 'nh_keywords', '<b>one</b>, two, one' );
		$this->assertSame( 'one, two', get_post_meta( $post_id, 'nh_keywords', true ) );

		update_post_meta( $post_id, 'nh_topic_cluster', "  <i>Scooter</i>   basics\n" );
		$this->assertSame( 'Scooter basics', get_post_meta( $post_id, 'nh_topic_cluster', true ) );
		update_post_meta( $post_id, 'nh_topic_cluster', str_repeat( 'x', 500 ) );
		$this->assertSame( NH_SEO_Keys::LIMIT_CLUSTER_ALIAS, strlen( get_post_meta( $post_id, 'nh_topic_cluster', true ) ) );
	}

	public function test_pillar_id_sanitizer_rejects_unpublished_targets() {
		$post_id   = self::factory()->post->create();
		$published = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$draft     = self::factory()->post->create( array( 'post_status' => 'draft' ) );

		update_post_meta( $post_id, 'nh_pillar_id', $published );
		$this->assertSame( (string) $published, get_post_meta( $post_id, 'nh_pillar_id', true ) );
		update_post_meta( $post_id, 'nh_pillar_id', $draft );
		$this->assertSame( '0', get_post_meta( $post_id, 'nh_pillar_id', true ) );
		update_post_meta( $post_id, 'nh_pillar_id', '12abc' );
		$this->assertSame( '0', get_post_meta( $post_id, 'nh_pillar_id', true ) );
	}

	public function test_primary_cluster_must_be_an_existing_cluster_term() {
		$post_id = self::factory()->post->create();
		$cluster = self::factory()->term->create( array( 'taxonomy' => 'topic_cluster' ) );
		$tag     = self::factory()->term->create( array( 'taxonomy' => 'post_tag' ) );

		update_post_meta( $post_id, 'nh_primary_cluster', $cluster );
		$this->assertSame( (string) $cluster, get_post_meta( $post_id, 'nh_primary_cluster', true ) );
		update_post_meta( $post_id, 'nh_primary_cluster', $tag );
		$this->assertSame( '0', get_post_meta( $post_id, 'nh_primary_cluster', true ) );
		update_post_meta( $post_id, 'nh_primary_cluster', 987654 );
		$this->assertSame( '0', get_post_meta( $post_id, 'nh_primary_cluster', true ) );
		update_post_meta( $post_id, 'nh_primary_cluster', array( $cluster ) );
		$this->assertSame( '0', get_post_meta( $post_id, 'nh_primary_cluster', true ) );
	}

	public function test_hub_term_reference_is_validated_and_normalized() {
		$category = self::factory()->category->create();
		$cluster  = self::factory()->term->create( array( 'taxonomy' => 'topic_cluster' ) );
		$tag      = self::factory()->term->create( array( 'taxonomy' => 'post_tag' ) );

		$this->assertSame( "category:$category", NH_SEO_Data_Model::sanitize_hub_term( " category:$category " ) );
		$this->assertSame( "topic_cluster:$cluster", NH_SEO_Data_Model::sanitize_hub_term( "topic_cluster:$cluster" ) );
		$this->assertSame( '', NH_SEO_Data_Model::sanitize_hub_term( "post_tag:$tag" ), 'only categories and clusters' );
		$this->assertSame( '', NH_SEO_Data_Model::sanitize_hub_term( "category:$cluster" ), 'a cluster ID is not a category' );
		$this->assertSame( '', NH_SEO_Data_Model::sanitize_hub_term( 'category:999999' ) );
		$this->assertSame( '', NH_SEO_Data_Model::sanitize_hub_term( "category:$category; DROP TABLE x" ) );
		$this->assertSame( '', NH_SEO_Data_Model::sanitize_hub_term( "category:-$category" ) );
		$this->assertSame( '', NH_SEO_Data_Model::sanitize_hub_term( array( 'category' ) ) );

		$this->assertSame(
			array(
				'taxonomy' => 'category',
				'term_id'  => 12,
			),
			NH_SEO_Data_Model::parse_hub_term( 'category:12' )
		);
		$this->assertNull( NH_SEO_Data_Model::parse_hub_term( 'nope' ) );

		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_post_meta( $page, 'nh_hub_term', "category:$category" );
		$this->assertSame( "category:$category", get_post_meta( $page, 'nh_hub_term', true ) );
		update_post_meta( $page, 'nh_hub_term', 'category:999999' );
		$this->assertSame( '', get_post_meta( $page, 'nh_hub_term', true ) );
	}

	public function test_term_pillar_is_sanitized() {
		$published = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$draft     = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		$category  = self::factory()->category->create();

		update_term_meta( $category, 'nh_pillar_id', $published );
		$this->assertSame( (string) $published, get_term_meta( $category, 'nh_pillar_id', true ) );
		update_term_meta( $category, 'nh_pillar_id', $draft );
		$this->assertSame( '0', get_term_meta( $category, 'nh_pillar_id', true ) );
	}

	public function test_user_meta_is_sanitized() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );

		update_user_meta( $user_id, 'nh_job_title', '  <b>Senior</b> guide ' . str_repeat( 'x', 300 ) );
		$title = get_user_meta( $user_id, 'nh_job_title', true );
		$this->assertStringStartsWith( 'Senior guide', $title );
		$this->assertLessThanOrEqual( NH_SEO_Data_Model::LIMIT_JOB_TITLE, mb_strlen( $title ) );

		update_user_meta( $user_id, 'nh_sameas', "https://example.org/a\njavascript:alert(1)\nhttps://example.org/a\nhttps://example.org/b" );
		$this->assertSame( "https://example.org/a\nhttps://example.org/b", get_user_meta( $user_id, 'nh_sameas', true ) );
	}

	public function test_business_profile_filter_is_supplied_by_the_plugin() {
		delete_option( NH_SEO_Keys::OPTION_BUSINESS );
		$profile = apply_filters( 'nguyen_ha_business_profile', array() );
		$this->assertSame( '', $profile['name'] );
		$this->assertSame( '', $profile['phone'] );
		$this->assertSame( array(), $profile['hours'] );

		update_option(
			NH_SEO_Keys::OPTION_BUSINESS,
			array(
				'name'  => 'Example Co',
				'phone' => '+00 000 000',
			)
		);
		$profile = apply_filters( 'nguyen_ha_business_profile', array() );
		$this->assertSame( 'Example Co', $profile['name'] );
		$this->assertSame( '+00 000 000', $profile['phone'] );
	}

	public function test_meta_authorization_follows_post_capabilities() {
		$author    = self::factory()->user->create( array( 'role' => 'author' ) );
		$other     = self::factory()->user->create( array( 'role' => 'author' ) );
		$editor    = self::factory()->user->create( array( 'role' => 'editor' ) );
		$subscribe = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$post_id   = self::factory()->post->create( array( 'post_author' => $author ) );

		$this->assertTrue( user_can( $author, 'edit_post_meta', $post_id, 'nh_summary' ) );
		$this->assertTrue( user_can( $editor, 'edit_post_meta', $post_id, 'nh_summary' ) );
		$this->assertFalse( user_can( $other, 'edit_post_meta', $post_id, 'nh_summary' ) );
		$this->assertFalse( user_can( $subscribe, 'edit_post_meta', $post_id, 'nh_summary' ) );
		$this->assertFalse( user_can( 0, 'edit_post_meta', $post_id, 'nh_summary' ) );
		$this->assertFalse( user_can( $other, 'delete_post_meta', $post_id, 'nh_faq' ) );
	}

	public function test_term_and_user_meta_authorization() {
		$editor    = self::factory()->user->create( array( 'role' => 'editor' ) );
		$author    = self::factory()->user->create( array( 'role' => 'author' ) );
		$subscribe = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$category  = self::factory()->category->create();

		$this->assertTrue( user_can( $editor, 'edit_term_meta', $category, 'nh_pillar_id' ) );
		$this->assertFalse( user_can( $author, 'edit_term_meta', $category, 'nh_pillar_id' ) );
		$this->assertFalse( user_can( $subscribe, 'edit_term_meta', $category, 'nh_pillar_id' ) );

		$this->assertTrue( user_can( $author, 'edit_user_meta', $author, 'nh_job_title' ) );
		$this->assertFalse( user_can( $author, 'edit_user_meta', $editor, 'nh_job_title' ) );
		$this->assertFalse( user_can( 0, 'edit_user_meta', $author, 'nh_job_title' ) );
	}

	public function test_registering_twice_is_harmless() {
		NH_SEO_Data_Model::register();
		NH_SEO_Data_Model::register();
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'nh_summary', '<b>x</b>' );
		$this->assertSame( 'x', get_post_meta( $post_id, 'nh_summary', true ) );
		$this->assertTrue( taxonomy_exists( 'topic_cluster' ) );
	}

	public function test_missing_post_meta_returns_registered_defaults() {
		$post_id = self::factory()->post->create();
		$this->assertSame( array(), get_post_meta( $post_id, 'nh_faq', true ) );
		$this->assertSame( array(), get_post_meta( $post_id, 'nh_takeaways', true ) );
		$this->assertSame( '', get_post_meta( $post_id, 'nh_summary', true ) );
		$this->assertSame( 0, get_post_meta( $post_id, 'nh_pillar_id', true ) );
	}
}
