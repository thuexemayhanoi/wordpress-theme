<?php
/**
 * Schema module, "extras" mode: an SEO plugin owns the graph, so only separate FAQPage and LocalBusiness
 * blocks are printed, and only under strict conditions.
 *
 * SIMULATIONS: Yoast SEO and Rank Math are NOT installed. They are simulated through the documented
 * extension points (nh_seo_detected_seo_plugin, FAQ block markup in post content, the nh_seo_local_seo_probes
 * filter with made-up constant and class names, and the stored Rank Math module list). Behaviour against the
 * real plugins was not verified by these tests.
 *
 * @package nguyen-ha-seo-companion
 */

// Shared fixtures and helpers.
require_once __DIR__ . '/class-nh-schema-test-case.php';

/**
 * Extras mode tests.
 */
class NH_Test_Schema_Extras extends NH_Schema_Test_Case {

	/** @var int */
	private $post_id;
	/** @var int */
	private $author;

	public function set_up() {
		parent::set_up();
		$this->simulate_seo_plugin( 'yoast' );
		$this->author  = self::factory()->user->create( array( 'role' => 'author' ) );
		$this->post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Scooter rental basics',
				'post_excerpt' => '',
				'post_author'  => $this->author,
			)
		);
	}

	private function url() {
		return get_permalink( $this->post_id );
	}

	public function test_detected_seo_plugins_switch_the_module_to_extras() {
		$this->assertSame( 'extras', nh_seo_schema_mode() );

		remove_all_filters( 'nh_seo_detected_seo_plugin' );
		$this->simulate_seo_plugin( 'rank-math' );
		$this->assertSame( 'extras', nh_seo_schema_mode() );

		remove_all_filters( 'nh_seo_detected_seo_plugin' );
		$this->assertSame( 'full', nh_seo_schema_mode() );
	}

	public function test_nothing_is_printed_when_there_is_nothing_to_add() {
		$this->assertSame( array(), $this->blocks_at( $this->url() ) );
		$this->assertSame( array(), $this->blocks_at( home_url( '/' ) ) );
		$this->assertSame( array(), $this->blocks_at( get_category_link( get_option( 'default_category' ) ) ) );
	}

	public function test_extras_never_print_graph_nodes() {
		$this->set_faq( $this->post_id, 3 );
		$this->set_profile( $this->complete_profile() );
		update_post_meta( $this->post_id, NH_SEO_Keys::META_LOCAL_BUSINESS, 1 );

		$types = $this->types_of( $this->nodes_of( $this->blocks_at( $this->url() ) ) );
		foreach ( array( 'Organization', 'WebSite', 'WebPage', 'BreadcrumbList', 'Article', 'Person', 'CollectionPage' ) as $forbidden ) {
			$this->assertNotContains( $forbidden, $types, $forbidden . ' belongs to the SEO plugin graph.' );
		}
	}

	public function test_faq_page_is_a_separate_block() {
		$this->set_faq( $this->post_id, 2 );
		$blocks = $this->blocks_at( $this->url() );

		$this->assertCount( 1, $blocks );
		$data = $this->decode( $blocks[0] );
		$this->assertArrayNotHasKey( '@graph', $data, 'An extras block is a standalone document, never a graph.' );
		$this->assertSame( 'https://schema.org', $data['@context'] );
		$this->assertSame( 'FAQPage', $data['@type'] );
		$this->assertSame( $this->url() . '#faq', $data['@id'] );
		$this->assertSame( $this->url(), $data['url'] );
		$this->assertCount( 2, $data['mainEntity'] );

		$question = $data['mainEntity'][1];
		$this->assertSame( 'Question', $question['@type'] );
		$this->assertSame( 'Question number 2?', $question['name'] );
		$this->assertSame( 'Answer', $question['acceptedAnswer']['@type'] );
		$this->assertStringContainsString( '<a href="https://example.org/guide">guide</a>', $question['acceptedAnswer']['text'] );

		$this->assert_well_formed( $blocks );
	}

	public function test_faq_needs_at_least_two_items_and_the_minimum_is_filterable() {
		$this->set_faq( $this->post_id, 1 );
		$this->assertSame( array(), $this->blocks_at( $this->url() ) );

		add_filter(
			'nh_seo_faq_min_items',
			static function () {
				return 1;
			}
		);
		$this->assertCount( 1, $this->blocks_at( $this->url() ) );

		remove_all_filters( 'nh_seo_faq_min_items' );
		add_filter(
			'nh_seo_faq_min_items',
			static function () {
				return 4;
			}
		);
		$this->set_faq( $this->post_id, 3 );
		$this->assertSame( array(), $this->blocks_at( $this->url() ) );
	}

	public function test_faq_is_not_marked_up_when_readers_cannot_see_it() {
		$this->set_faq( $this->post_id, 2 );

		// Theme does not render it and the fallback renderer is off: the FAQ is invisible.
		$this->set_settings( array( 'fallback_render' => 0 ) );
		$this->assertSame( array(), $this->blocks_at( $this->url() ), 'Structured data must never describe invisible content.' );

		// The theme declares that it renders the FAQ itself.
		add_theme_support( 'nguyen-ha-companion', array( 'faq' => true ) );
		$this->assertCount( 1, $this->blocks_at( $this->url() ) );
		remove_theme_support( 'nguyen-ha-companion' );

		// The fallback renderer is on again.
		$this->set_settings( array( 'fallback_render' => 1 ) );
		$this->assertCount( 1, $this->blocks_at( $this->url() ) );
	}

	public function test_faq_is_skipped_when_the_content_already_holds_a_yoast_or_rank_math_faq_block() {
		$this->set_faq( $this->post_id, 2 );

		foreach ( array( 'yoast/faq-block', 'rank-math/faq-block' ) as $block ) {
			wp_update_post(
				array(
					'ID'           => $this->post_id,
					'post_content' => "<!-- wp:paragraph -->\n<p>Intro</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:{$block} {\"questions\":[]} -->\n<div class=\"wp-block\">FAQ</div>\n<!-- /wp:{$block} -->",
				)
			);
			$this->assertSame( array(), $this->blocks_at( $this->url() ), $block . ' prints its own FAQPage.' );
		}

		// An unrelated block is fine.
		wp_update_post(
			array(
				'ID'           => $this->post_id,
				'post_content' => "<!-- wp:paragraph -->\n<p>Intro</p>\n<!-- /wp:paragraph -->",
			)
		);
		$this->assertCount( 1, $this->blocks_at( $this->url() ) );
	}

	/**
	 * Create a reusable block (synced pattern) and return its ID.
	 *
	 * @param string $content Block markup.
	 * @param string $status  Post status.
	 * @return int
	 */
	private function reusable_block( $content, $status = 'publish' ) {
		return self::factory()->post->create(
			array(
				'post_type'    => 'wp_block',
				'post_status'  => $status,
				'post_title'   => 'Shared block',
				'post_content' => $content,
			)
		);
	}

	/**
	 * Markup that places a reusable block, optionally inside a group block.
	 *
	 * @param int  $ref   Reusable block ID.
	 * @param bool $group Whether to nest it in a group.
	 * @return string
	 */
	private function uses_reusable( $ref, $group = true ) {
		$block = '<!-- wp:block {"ref":' . $ref . '} /-->';
		return $group ? '<!-- wp:group --><div class="wp-block-group">' . $block . '</div><!-- /wp:group -->' : $block;
	}

	public function test_a_faq_block_inside_a_published_reusable_block_counts_as_an_external_faq() {
		$this->set_faq( $this->post_id, 2 );
		$faq_block = '<!-- wp:yoast/faq-block {"questions":[]} --><div>FAQ</div><!-- /wp:yoast/faq-block -->';
		$reusable  = $this->reusable_block( $faq_block );

		foreach ( array( true, false ) as $group ) {
			wp_update_post(
				array(
					'ID'           => $this->post_id,
					'post_content' => $this->uses_reusable( $reusable, $group ),
				)
			);
			$this->assertSame( array(), $this->blocks_at( $this->url() ), 'Visitors see the FAQ block of the reusable block, and its plugin prints the FAQPage.' );
		}

		// A reusable block that is not published is not shown to visitors.
		wp_update_post(
			array(
				'ID'          => $reusable,
				'post_status' => 'draft',
			)
		);
		$this->assertCount( 1, $this->blocks_at( $this->url() ) );

		// A reference to something that is not a reusable block, or to nothing, changes nothing.
		wp_update_post(
			array(
				'ID'           => $this->post_id,
				'post_content' => $this->uses_reusable( $this->post_id ) . $this->uses_reusable( 99999999 ) . '<!-- wp:block {"ref":"x"} /-->',
			)
		);
		$this->assertCount( 1, $this->blocks_at( $this->url() ) );
	}

	public function test_reusable_blocks_are_followed_a_few_levels_deep_and_loops_end() {
		$this->set_faq( $this->post_id, 2 );
		$faq_block = '<!-- wp:rank-math/faq-block {"questions":[]} --><div>FAQ</div><!-- /wp:rank-math/faq-block -->';

		// Chain: post -> level 1 -> level 2 -> level 3 (holds the FAQ block).
		$level3 = $this->reusable_block( $faq_block );
		$level2 = $this->reusable_block( $this->uses_reusable( $level3 ) );
		$level1 = $this->reusable_block( $this->uses_reusable( $level2 ) );
		wp_update_post(
			array(
				'ID'           => $this->post_id,
				'post_content' => $this->uses_reusable( $level1 ),
			)
		);
		$this->assertSame( array(), $this->blocks_at( $this->url() ), 'Three levels are followed.' );

		// One level more is beyond the bound: the search ends quietly instead of walking on.
		$level0 = $this->reusable_block( $this->uses_reusable( $level1 ) );
		wp_update_post(
			array(
				'ID'           => $this->post_id,
				'post_content' => $this->uses_reusable( $level0 ),
			)
		);
		$this->assertCount( 1, $this->blocks_at( $this->url() ), 'Four levels are not followed.' );

		// Two reusable blocks that contain each other never loop.
		$a = $this->reusable_block( 'placeholder' );
		$b = $this->reusable_block( $this->uses_reusable( $a ) );
		wp_update_post(
			array(
				'ID'           => $a,
				'post_content' => $this->uses_reusable( $b ),
			)
		);
		wp_update_post(
			array(
				'ID'           => $this->post_id,
				'post_content' => $this->uses_reusable( $a ),
			)
		);
		$this->assertCount( 1, $this->blocks_at( $this->url() ) );
	}

	public function test_only_a_bounded_number_of_reusable_blocks_is_opened_per_post() {
		$this->set_faq( $this->post_id, 2 );
		$content = '';
		for ( $i = 0; $i < NH_SEO_Schema_Detect::MAX_REUSABLE_BLOCKS; $i++ ) {
			$content .= $this->uses_reusable( $this->reusable_block( '<!-- wp:paragraph --><p>Nothing here</p><!-- /wp:paragraph -->' ), false );
		}
		$with_faq = $this->reusable_block( '<!-- wp:yoast/faq-block {"questions":[]} --><div>FAQ</div><!-- /wp:yoast/faq-block -->' );
		wp_update_post(
			array(
				'ID'           => $this->post_id,
				'post_content' => $content . $this->uses_reusable( $with_faq, false ),
			)
		);

		$this->assertFalse( NH_SEO_Schema_Detect::has_external_faq( $this->post_id ), 'The eleventh reusable block is past the bound.' );
		$this->assertCount( 1, $this->blocks_at( $this->url() ) );

		// The filter is the way out for such a page.
		add_filter( 'nh_seo_has_external_faq', '__return_true' );
		$this->assertSame( array(), $this->blocks_at( $this->url() ) );
		remove_filter( 'nh_seo_has_external_faq', '__return_true' );

		// Within the bound the same reusable block is found.
		wp_update_post(
			array(
				'ID'           => $this->post_id,
				'post_content' => $this->uses_reusable( $with_faq, false ),
			)
		);
		$this->assertTrue( NH_SEO_Schema_Detect::has_external_faq( $this->post_id ) );
	}

	public function test_external_faq_detection_is_extensible() {
		$this->set_faq( $this->post_id, 2 );
		wp_update_post(
			array(
				'ID'           => $this->post_id,
				'post_content' => '<!-- wp:acme/faq {"x":1} --><div>FAQ</div><!-- /wp:acme/faq -->',
			)
		);
		$this->assertCount( 1, $this->blocks_at( $this->url() ), 'Unknown block: not detected yet.' );

		add_filter(
			'nh_seo_external_faq_blocks',
			static function ( $names ) {
				$names[] = 'acme/faq';
				return $names;
			}
		);
		$this->assertSame( array(), $this->blocks_at( $this->url() ) );

		remove_all_filters( 'nh_seo_external_faq_blocks' );
		$this->assertCount( 1, $this->blocks_at( $this->url() ) );

		$seen = null;
		add_filter(
			'nh_seo_has_external_faq',
			static function ( $detected, $post ) use ( &$seen ) {
				$seen = $post;
				return true;
			},
			10,
			2
		);
		$this->assertSame( array(), $this->blocks_at( $this->url() ) );
		$this->assertInstanceOf( WP_Post::class, $seen );
		$this->assertSame( $this->post_id, $seen->ID );
	}

	public function test_faq_is_printed_for_posts_and_pages_only_never_for_archives() {
		$page = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_excerpt' => '',
			)
		);
		$this->set_faq( $page, 2 );
		$this->set_faq( $this->post_id, 2 );
		$this->assertCount( 1, $this->blocks_at( get_permalink( $page ) ) );
		$this->assertCount( 1, $this->blocks_at( $this->url() ) );

		$this->assertSame( array(), $this->blocks_at( home_url( '/' ) ) );
		$this->assertSame( array(), $this->blocks_at( get_category_link( get_option( 'default_category' ) ) ) );
		$this->assertSame( array(), $this->blocks_at( get_author_posts_url( $this->author ) ) );
	}

	public function test_nothing_is_printed_for_404_search_and_non_public_content() {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		$this->set_profile( $this->complete_profile() );
		$this->set_settings( array( 'localbusiness_on_home' => 1 ) );

		$ids = array(
			'draft'    => self::factory()->post->create( array( 'post_status' => 'draft' ) ),
			'private'  => self::factory()->post->create( array( 'post_status' => 'private' ) ),
			'password' => self::factory()->post->create( array( 'post_password' => 'secret' ) ),
		);
		foreach ( $ids as $id ) {
			$this->set_faq( $id, 3 );
			update_post_meta( $id, NH_SEO_Keys::META_LOCAL_BUSINESS, 1 );
		}

		$this->assertSame( array(), $this->blocks_at( home_url( '/?p=' . $ids['draft'] . '&preview=true' ) ), 'draft preview' );
		$this->assertSame( array(), $this->blocks_at( get_permalink( $ids['private'] ) ), 'private' );
		$this->assertSame( array(), $this->blocks_at( get_permalink( $ids['password'] ) ), 'password protected' );
		$this->assertSame( array(), $this->blocks_at( home_url( '/definitely-missing/' ) ), '404' );
		$this->assertSame( array(), $this->blocks_at( home_url( '/?s=scooter' ) ), 'search results' );
		$this->assertSame( array(), $this->blocks_at( home_url( '/?s=' ) ), 'empty search' );
	}

	public function test_local_business_needs_a_complete_profile_and_the_flag() {
		$flagged = $this->post_id;
		update_post_meta( $flagged, NH_SEO_Keys::META_LOCAL_BUSINESS, 1 );
		$plain = self::factory()->post->create( array( 'post_excerpt' => '' ) );

		// Flag but no profile.
		$this->assertSame( array(), $this->blocks_at( get_permalink( $flagged ) ) );

		// Incomplete profile (no phone) and the flag.
		$this->set_profile( $this->complete_profile( array( 'phone' => '' ) ) );
		$this->assertSame( array(), $this->blocks_at( get_permalink( $flagged ) ) );

		// Complete profile but no flag.
		$this->set_profile( $this->complete_profile() );
		$this->assertSame( array(), $this->blocks_at( get_permalink( $plain ) ) );

		// Both.
		$blocks = $this->blocks_at( get_permalink( $flagged ) );
		$this->assertCount( 1, $blocks );
		$this->assertSame( 'AutoRental', $this->decode( $blocks[0] )['@type'] );
	}

	public function test_local_business_uses_only_profile_data() {
		update_post_meta( $this->post_id, NH_SEO_Keys::META_LOCAL_BUSINESS, 1 );
		$this->set_profile( $this->complete_profile( array( 'logo_id' => $this->create_image( 400, 400 ) ) ) );

		$data = $this->decode( $this->blocks_at( $this->url() )[0] );

		$this->assertSame( 'https://schema.org', $data['@context'] );
		$this->assertSame( 'AutoRental', $data['@type'] );
		$this->assertSame( home_url( '/' ) . '#localbusiness', $data['@id'] );
		$this->assertSame( 'Example Scooter Hire', $data['name'] );
		$this->assertSame( 'https://example.org/', $data['url'] );
		$this->assertSame( '+00 000 000 000', $data['telephone'] );
		$this->assertSame( 'hello@example.org', $data['email'] );
		$this->assertSame(
			array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => '1 Example Street',
				'addressLocality' => 'Example City',
				'addressRegion'   => 'Example Region',
				'postalCode'      => '00000',
				'addressCountry'  => 'VN',
			),
			$data['address']
		);
		$this->assertSame( 'GeoCoordinates', $data['geo']['@type'] );
		$this->assertSame( 21.0285, $data['geo']['latitude'] );
		$this->assertSame( 105.8542, $data['geo']['longitude'] );
		$this->assertSame( array( 'Mo-Su 09:00-21:00' ), $data['openingHours'] );
		$this->assertSame( array( 'https://example.org/social' ), $data['sameAs'] );
		$this->assertSame( 'ImageObject', $data['image']['@type'] );

		$keys = $this->all_keys( $data );
		foreach ( array( 'priceRange', 'aggregateRating', 'review', 'reviews', 'offers', 'makesOffer', 'ratingValue', 'hasMap' ) as $forbidden ) {
			$this->assertNotContains( $forbidden, $keys, $forbidden . ' must not appear unless the owner provided it.' );
		}
	}

	public function test_price_range_and_map_appear_only_when_the_owner_typed_them() {
		update_post_meta( $this->post_id, NH_SEO_Keys::META_LOCAL_BUSINESS, 1 );
		$this->set_profile(
			$this->complete_profile(
				array(
					'price_range' => '$$',
					'has_map'     => 'https://maps.example.org/place/1',
				)
			)
		);
		$data = $this->decode( $this->blocks_at( $this->url() )[0] );
		$this->assertSame( '$$', $data['priceRange'] );
		$this->assertSame( 'https://maps.example.org/place/1', $data['hasMap'] );
		$this->assertNotContains( 'aggregateRating', $this->all_keys( $data ) );
	}

	public function test_optional_profile_fields_are_left_out_when_empty() {
		update_post_meta( $this->post_id, NH_SEO_Keys::META_LOCAL_BUSINESS, 1 );
		$this->set_profile(
			array(
				'name'     => 'Minimal Shop',
				'phone'    => '+00 111',
				'street'   => '2 Example Road',
				'locality' => 'Example Town',
			)
		);
		$data = $this->decode( $this->blocks_at( $this->url() )[0] );

		$this->assertSame( 'LocalBusiness', $data['@type'] );
		$this->assertSame( array( '@type', 'streetAddress', 'addressLocality' ), array_keys( $data['address'] ) );
		foreach ( array( 'geo', 'openingHours', 'email', 'sameAs', 'image', 'priceRange', 'hasMap' ) as $key ) {
			$this->assertArrayNotHasKey( $key, $data, $key );
		}
	}

	public function test_local_business_on_the_front_page_needs_the_setting() {
		$this->set_profile( $this->complete_profile() );

		$this->assertSame( array(), $this->blocks_at( home_url( '/' ) ) );

		$this->set_settings( array( 'localbusiness_on_home' => 1 ) );
		$blocks = $this->blocks_at( home_url( '/' ) );
		$this->assertCount( 1, $blocks );
		$this->assertSame( 'AutoRental', $this->decode( $blocks[0] )['@type'] );

		// Other pages stay silent: the setting is about the front page only.
		$this->assertSame( array(), $this->blocks_at( $this->url() ) );
		$this->assertSame( array(), $this->blocks_at( get_category_link( get_option( 'default_category' ) ) ) );

		// A static front page works the same way, and an incomplete profile still prints nothing.
		$front = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_excerpt' => '',
			)
		);
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front );
		$this->assertCount( 1, $this->blocks_at( home_url( '/' ) ) );

		$this->set_profile( $this->complete_profile( array( 'street' => '' ) ) );
		$this->assertSame( array(), $this->blocks_at( home_url( '/' ) ) );
	}

	public function test_front_page_prints_one_local_business_even_when_flagged_too() {
		$front = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_excerpt' => '',
			)
		);
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front );
		update_post_meta( $front, NH_SEO_Keys::META_LOCAL_BUSINESS, 1 );
		$this->set_profile( $this->complete_profile() );
		$this->set_settings( array( 'localbusiness_on_home' => 1 ) );
		$this->set_faq( $front, 2 );

		$nodes = $this->assert_well_formed( $this->blocks_at( home_url( '/' ) ) );
		$this->assertSame( array( 'FAQPage', 'AutoRental' ), $this->types_of( $nodes ) );
	}

	public function test_local_business_is_skipped_when_a_local_seo_addon_prints_it() {
		$this->set_profile( $this->complete_profile() );
		update_post_meta( $this->post_id, NH_SEO_Keys::META_LOCAL_BUSINESS, 1 );
		$this->assertCount( 1, $this->blocks_at( $this->url() ), 'Baseline: printed.' );

		// The shipped probes know the real constant, class and module names.
		$probes = NH_SEO_Schema_Detect::local_seo_probes();
		$this->assertContains( 'WPSEO_LOCAL_VERSION', $probes['yoast-local']['constants'] );
		$this->assertContains( 'WPSEO_Local_Core', $probes['yoast-local']['classes'] );
		$this->assertContains( 'RANK_MATH_VERSION', $probes['rank-math']['constants'] );
		$this->assertSame( 'local-seo', $probes['rank-math']['module']['slug'] );

		// Simulated Yoast Local SEO: the class probe is pointed at a class that always exists (stdClass).
		$add_class_probe = static function ( $probes ) {
			$probes['yoast-local']['classes'] = array( 'stdClass' );
			return $probes;
		};
		add_filter( 'nh_seo_local_seo_probes', $add_class_probe );
		$this->assertTrue( NH_SEO_Schema_Detect::has_external_localbusiness() );
		$this->assertSame( array(), $this->blocks_at( $this->url() ) );
		remove_filter( 'nh_seo_local_seo_probes', $add_class_probe );
		$this->assertFalse( NH_SEO_Schema_Detect::has_external_localbusiness() );

		// Simulated Yoast Local SEO: a constant.
		if ( ! defined( 'NH_TEST_FAKE_LOCAL_SEO_VERSION' ) ) {
			define( 'NH_TEST_FAKE_LOCAL_SEO_VERSION', '1.0' );
		}
		$add_constant_probe = static function ( $probes ) {
			$probes['yoast-local']['constants'] = array( 'NH_TEST_FAKE_LOCAL_SEO_VERSION' );
			return $probes;
		};
		add_filter( 'nh_seo_local_seo_probes', $add_constant_probe );
		$this->assertTrue( NH_SEO_Schema_Detect::has_external_localbusiness() );
		$this->assertSame( array(), $this->blocks_at( $this->url() ) );
		remove_filter( 'nh_seo_local_seo_probes', $add_constant_probe );
		$this->assertCount( 1, $this->blocks_at( $this->url() ) );
	}

	public function test_rank_math_local_module_is_detected_only_when_switched_on() {
		$this->set_profile( $this->complete_profile() );
		update_post_meta( $this->post_id, NH_SEO_Keys::META_LOCAL_BUSINESS, 1 );

		// Rank Math itself is simulated with a made-up constant; only its module list is real data.
		if ( ! defined( 'NH_TEST_FAKE_RANK_MATH_VERSION' ) ) {
			define( 'NH_TEST_FAKE_RANK_MATH_VERSION', '1.0' );
		}
		add_filter(
			'nh_seo_local_seo_probes',
			static function ( $probes ) {
				$probes['rank-math']['constants'] = array( 'NH_TEST_FAKE_RANK_MATH_VERSION' );
				return $probes;
			}
		);

		update_option( 'rank_math_modules', array( 'seo-analysis', 'sitemap' ) );
		$this->assertFalse( NH_SEO_Schema_Detect::has_external_localbusiness() );
		$this->assertCount( 1, $this->blocks_at( $this->url() ) );

		update_option( 'rank_math_modules', array( 'seo-analysis', 'local-seo' ) );
		$this->assertTrue( NH_SEO_Schema_Detect::has_external_localbusiness() );
		$this->assertSame( array(), $this->blocks_at( $this->url() ) );

		update_option( 'rank_math_modules', 'not-an-array' );
		$this->assertFalse( NH_SEO_Schema_Detect::has_external_localbusiness() );
	}

	public function test_local_business_detection_can_be_overridden_by_filter() {
		$this->set_profile( $this->complete_profile() );
		update_post_meta( $this->post_id, NH_SEO_Keys::META_LOCAL_BUSINESS, 1 );

		add_filter( 'nh_seo_has_external_localbusiness', '__return_true' );
		$this->assertSame( array(), $this->blocks_at( $this->url() ) );
		remove_filter( 'nh_seo_has_external_localbusiness', '__return_true' );
		$this->assertCount( 1, $this->blocks_at( $this->url() ) );
	}

	public function test_malformed_probe_data_never_breaks_detection() {
		add_filter(
			'nh_seo_local_seo_probes',
			static function () {
				return array(
					'broken-1' => 'not an array',
					'broken-2' => array( 'constants' => array( '', 42, null ) ),
					'broken-3' => array(
						'constants' => array( 'NH_TEST_NEVER_DEFINED_CONSTANT' ),
						'classes'   => array( 'NH_Test_Never_Defined_Class' ),
					),
				);
			}
		);
		$this->assertFalse( NH_SEO_Schema_Detect::has_external_localbusiness() );
	}

	public function test_forced_modes_override_detection() {
		$this->set_faq( $this->post_id, 2 );

		// An SEO plugin is detected, but the owner forces the full graph.
		$this->set_settings( array( 'schema_mode' => 'full' ) );
		$this->assertContains( 'Organization', $this->types_of( $this->nodes_of( $this->blocks_at( $this->url() ) ) ) );

		// No SEO plugin, but the owner forces extras only.
		remove_all_filters( 'nh_seo_detected_seo_plugin' );
		$this->set_settings( array( 'schema_mode' => 'extras' ) );
		$this->assertSame( array( 'FAQPage' ), $this->types_of( $this->nodes_of( $this->blocks_at( $this->url() ) ) ) );

		$this->set_settings( array( 'schema_mode' => 'off' ) );
		$this->assertSame( array(), $this->blocks_at( $this->url() ) );
	}

	public function test_the_graph_filter_also_runs_in_extras_mode() {
		$this->set_faq( $this->post_id, 2 );
		add_filter(
			'nh_seo_schema_graph',
			static function ( $nodes, $mode ) {
				return 'extras' === $mode ? array() : $nodes;
			},
			10,
			2
		);
		$this->assertSame( array(), $this->blocks_at( $this->url() ) );
	}
}
