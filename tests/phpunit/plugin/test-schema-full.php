<?php
/**
 * Schema module, "full" mode: one @graph per page, built from real stored data only.
 *
 * No SEO plugin is involved here: with none detected the module owns the whole graph.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

// Shared fixtures and helpers.
require_once __DIR__ . '/class-nh-schema-test-case.php';

/**
 * Full graph tests.
 */
class NH_Test_Schema_Full extends NH_Schema_Test_Case {

	/** @var int */
	private $author;
	/** @var int */
	private $parent_cat;
	/** @var int */
	private $child_cat;
	/** @var int */
	private $post_id;

	public function set_up() {
		parent::set_up();

		$this->author = self::factory()->user->create(
			array(
				'role'         => 'author',
				'user_login'   => 'linh',
				'display_name' => 'Linh Tran',
			)
		);
		update_user_meta( $this->author, NH_SEO_Keys::USER_JOB_TITLE, 'Travel editor' );
		update_user_meta( $this->author, NH_SEO_Keys::USER_SAMEAS, "https://example.org/linh\nhttps://example.org/linh-2\njavascript:alert(1)" );

		$this->parent_cat = self::factory()->category->create(
			array(
				'name' => 'Rental',
				'slug' => 'rental',
			)
		);
		$this->child_cat  = self::factory()->category->create(
			array(
				'name'   => 'Scooters',
				'slug'   => 'scooters',
				'parent' => $this->parent_cat,
			)
		);

		$this->post_id = self::factory()->post->create(
			array(
				'post_title'    => 'Which scooter should a first-time visitor rent?',
				'post_content'  => str_repeat( 'word ', 120 ),
				'post_excerpt'  => '',
				'post_author'   => $this->author,
				'post_category' => array( $this->child_cat ),
				'tags_input'    => array( 'Scooter', 'Hanoi' ),
				'post_date'     => '2026-03-01 09:30:00',
			)
		);
	}

	public function test_a_post_prints_one_graph_with_the_expected_nodes() {
		$blocks = $this->blocks_at( get_permalink( $this->post_id ) );
		$this->assertCount( 1, $blocks, 'Full mode prints exactly one script element.' );

		$nodes = $this->assert_well_formed( $blocks );
		$this->assertSame(
			array( 'Organization', 'WebSite', 'WebPage', 'BreadcrumbList', 'Article', 'Person' ),
			$this->types_of( $nodes )
		);

		$data = $this->decode( $blocks[0] );
		$this->assertSame( 'https://schema.org', $data['@context'] );
		$this->assertArrayHasKey( '@graph', $data );
	}

	public function test_article_carries_only_real_fields() {
		$url   = get_permalink( $this->post_id );
		$nodes = $this->nodes_of( $this->blocks_at( $url ) );

		$article = $this->node( $nodes, 'Article' );
		$webpage = $this->node( $nodes, 'WebPage' );
		$person  = $this->node( $nodes, 'Person' );
		$org     = $this->node( $nodes, 'Organization' );

		$this->assertSame( 'Which scooter should a first-time visitor rent?', $article['headline'] );
		$this->assertSame( '2026-03-01T09:30:00+07:00', $article['datePublished'] );
		$this->assertGreaterThanOrEqual( strtotime( $article['datePublished'] ), strtotime( $article['dateModified'] ) );
		$this->assertSame( 'Scooters', $article['articleSection'] );
		$this->assertSame( 'Hanoi, Scooter', $article['keywords'] );
		$this->assertSame( 120, $article['wordCount'] );
		$this->assertSame( get_bloginfo( 'language' ), $article['inLanguage'] );
		$this->assertSame( array( '@id' => $person['@id'] ), $article['author'] );
		$this->assertSame( array( '@id' => $org['@id'] ), $article['publisher'] );
		$this->assertSame( array( '@id' => $webpage['@id'] ), $article['mainEntityOfPage'] );
		$this->assertSame( array( '@id' => $webpage['@id'] ), $article['isPartOf'] );

		$this->assertArrayNotHasKey( 'image', $article, 'No featured image, so no image is invented.' );
		$this->assertArrayNotHasKey( 'description', $article, 'No summary and no manual excerpt, so no description is generated.' );

		$this->assertSame( $url, $webpage['url'] );
		$this->assertSame( $article['headline'], $webpage['name'] );
		$this->assertSame( $article['datePublished'], $webpage['datePublished'] );
	}

	public function test_person_holds_only_what_the_author_stored() {
		$nodes  = $this->nodes_of( $this->blocks_at( get_permalink( $this->post_id ) ) );
		$person = $this->node( $nodes, 'Person' );

		$this->assertSame( 'Linh Tran', $person['name'] );
		$this->assertSame( 'Travel editor', $person['jobTitle'] );
		$this->assertSame( array( 'https://example.org/linh', 'https://example.org/linh-2' ), $person['sameAs'] );
		$this->assertSame( get_author_posts_url( $this->author ), $person['url'] );
		$this->assertMatchesRegularExpression( '#/\#/schema/person/[0-9a-f]{20}$#', $person['@id'], 'The person id is a salted hash, never the numeric user ID.' );
		$this->assertNotSame( home_url( '/' ) . '#/schema/person/' . $this->author, $person['@id'] );

		$this->assertEqualsCanonicalizing(
			array( '@type', '@id', 'name', 'url', 'jobTitle', 'sameAs' ),
			array_keys( $person ),
			'Nothing else (credentials, awards, education...) is ever invented.'
		);

		// An author without a stored job title or links gets neither.
		$other = self::factory()->user->create( array( 'display_name' => 'Bare Author' ) );
		$post  = self::factory()->post->create(
			array(
				'post_author'  => $other,
				'post_excerpt' => '',
			)
		);
		$bare  = $this->node( $this->nodes_of( $this->blocks_at( get_permalink( $post ) ) ), 'Person' );
		$this->assertArrayNotHasKey( 'jobTitle', $bare );
		$this->assertArrayNotHasKey( 'sameAs', $bare );
	}

	public function test_breadcrumb_list_serialises_the_canonical_trail() {
		$url   = get_permalink( $this->post_id );
		$nodes = $this->nodes_of( $this->blocks_at( $url ) );
		$trail = nh_seo_breadcrumb_trail();
		$list  = $this->node( $nodes, 'BreadcrumbList' );

		$this->assertSame( array( 'Home', 'Rental', 'Scooters', 'Which scooter should a first-time visitor rent?' ), wp_list_pluck( $list['itemListElement'], 'name' ) );
		$this->assertSame( wp_list_pluck( $trail, 'url' ), wp_list_pluck( $list['itemListElement'], 'item' ) );
		$this->assertSame( array( 1, 2, 3, 4 ), wp_list_pluck( $list['itemListElement'], 'position' ) );
		$this->assertSame( 'ListItem', $list['itemListElement'][0]['@type'] );

		$webpage = $this->node( $nodes, 'WebPage' );
		$this->assertSame( array( '@id' => $list['@id'] ), $webpage['breadcrumb'] );
	}

	public function test_headline_is_limited_to_110_characters() {
		$title = str_repeat( 'Đường đi Hà Nội ', 20 );
		$post  = self::factory()->post->create(
			array(
				'post_title'   => $title,
				'post_excerpt' => '',
			)
		);
		$nodes = $this->nodes_of( $this->blocks_at( get_permalink( $post ) ) );

		$article = $this->node( $nodes, 'Article' );
		$this->assertLessThanOrEqual( 110, mb_strlen( $article['headline'] ) );
		$this->assertGreaterThan( 100, mb_strlen( $article['headline'] ) );
		$this->assertSame( trim( $title ), $this->node( $nodes, 'WebPage' )['name'], 'Only the headline is shortened.' );
	}

	public function test_description_is_the_summary_then_a_manual_excerpt_and_never_generated() {
		$url = get_permalink( $this->post_id );

		wp_update_post(
			array(
				'ID'           => $this->post_id,
				'post_excerpt' => '<b>Hand written</b> excerpt.',
			)
		);
		$nodes = $this->nodes_of( $this->blocks_at( $url ) );
		$this->assertSame( 'Hand written excerpt.', $this->node( $nodes, 'Article' )['description'] );
		$this->assertSame( 'Hand written excerpt.', $this->node( $nodes, 'WebPage' )['description'] );

		update_post_meta( $this->post_id, NH_SEO_Keys::META_SUMMARY, 'The summary wins.' );
		$nodes = $this->nodes_of( $this->blocks_at( $url ) );
		$this->assertSame( 'The summary wins.', $this->node( $nodes, 'Article' )['description'] );
	}

	public function test_date_modified_uses_the_real_update_date_only_when_valid() {
		$url = get_permalink( $this->post_id );

		update_post_meta( $this->post_id, NH_SEO_Keys::META_UPDATED, '2026-04-10' );
		$article = $this->node( $this->nodes_of( $this->blocks_at( $url ) ), 'Article' );
		$this->assertSame( '2026-04-10', $article['dateModified'] );

		// Earlier than publication: ignored, and the modified date is never before the publication date.
		update_post_meta( $this->post_id, NH_SEO_Keys::META_UPDATED, '2026-02-01' );
		$article = $this->node( $this->nodes_of( $this->blocks_at( $url ) ), 'Article' );
		$this->assertNotSame( '2026-02-01', $article['dateModified'] );
		$this->assertGreaterThanOrEqual( strtotime( $article['datePublished'] ), strtotime( $article['dateModified'] ) );

		// A future date is dropped by the shared sanitizer.
		update_post_meta( $this->post_id, NH_SEO_Keys::META_UPDATED, gmdate( 'Y-m-d', time() + 30 * DAY_IN_SECONDS ) );
		$article = $this->node( $this->nodes_of( $this->blocks_at( $url ) ), 'Article' );
		$this->assertLessThan( time() + DAY_IN_SECONDS, strtotime( $article['dateModified'] ) );
	}

	public function test_featured_image_becomes_a_nested_image_object() {
		set_post_thumbnail( $this->post_id, $this->create_image( 1200, 630 ) );
		$article = $this->node( $this->nodes_of( $this->blocks_at( get_permalink( $this->post_id ) ) ), 'Article' );

		$this->assertSame( 'ImageObject', $article['image']['@type'] );
		$this->assertSame( 1200, $article['image']['width'] );
		$this->assertSame( 630, $article['image']['height'] );
		$this->assertStringStartsWith( 'http', $article['image']['url'] );
	}

	public function test_organization_falls_back_to_the_site_name_and_invents_nothing() {
		$nodes = $this->assert_well_formed( $this->blocks_at( get_permalink( $this->post_id ) ) );
		$org   = $this->node( $nodes, 'Organization' );

		$this->assertSame( get_bloginfo( 'name' ), $org['name'] );
		$this->assertSame( home_url( '/' ), $org['url'] );
		foreach ( array( 'logo', 'sameAs', 'contactPoint', 'legalName', 'address', 'telephone', 'email', 'location', 'aggregateRating', 'review', 'offers' ) as $key ) {
			$this->assertArrayNotHasKey( $key, $org, $key . ' must not exist without verified profile data.' );
		}
	}

	public function test_organization_uses_the_configured_profile() {
		$this->set_profile(
			$this->complete_profile(
				array(
					'legal_name' => 'Example Scooter Hire Co.',
					'logo_id'    => $this->create_image( 512, 512 ),
				)
			)
		);
		$org = $this->node( $this->nodes_of( $this->blocks_at( get_permalink( $this->post_id ) ) ), 'Organization' );

		$this->assertSame( 'Example Scooter Hire', $org['name'] );
		$this->assertSame( 'Example Scooter Hire Co.', $org['legalName'] );
		$this->assertSame( array( 'https://example.org/social' ), $org['sameAs'] );
		$this->assertSame( 'ImageObject', $org['logo']['@type'] );
		$this->assertSame( 'ContactPoint', $org['contactPoint']['@type'] );
		$this->assertSame( '+00 000 000 000', $org['contactPoint']['telephone'] );
		$this->assertArrayNotHasKey( 'location', $org, 'No LocalBusiness on this page, so no link to one.' );
	}

	public function test_contact_point_needs_a_phone_number() {
		$this->set_profile(
			array(
				'name'  => 'No Phone Shop',
				'email' => 'hello@example.org',
			)
		);
		$org = $this->node( $this->nodes_of( $this->blocks_at( get_permalink( $this->post_id ) ) ), 'Organization' );
		$this->assertArrayNotHasKey( 'contactPoint', $org );
	}

	public function test_website_node_links_the_publisher_and_the_site_search() {
		$nodes   = $this->nodes_of( $this->blocks_at( get_permalink( $this->post_id ) ) );
		$website = $this->node( $nodes, 'WebSite' );
		$org     = $this->node( $nodes, 'Organization' );

		$this->assertSame( home_url( '/' ), $website['url'] );
		$this->assertSame( array( '@id' => $org['@id'] ), $website['publisher'] );
		$this->assertSame( 'SearchAction', $website['potentialAction']['@type'] );
		$this->assertSame( home_url( '/' ) . '?s={search_term_string}', $website['potentialAction']['target']['urlTemplate'] );
		$this->assertSame( 'required name=search_term_string', $website['potentialAction']['query-input'] );
	}

	public function test_each_kind_of_page_gets_the_right_page_type() {
		$page = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'About us',
				'post_excerpt' => '',
			)
		);
		self::factory()->post->create(
			array(
				'post_author'   => $this->author,
				'post_category' => array( $this->child_cat ),
				'post_excerpt'  => '',
			)
		);

		$cases = array(
			'latest posts front page' => array( home_url( '/' ), 'WebPage', array( 'Organization', 'WebSite', 'WebPage' ) ),
			'static page'             => array( get_permalink( $page ), 'WebPage', array( 'Organization', 'WebSite', 'WebPage', 'BreadcrumbList' ) ),
			'category archive'        => array( get_category_link( $this->child_cat ), 'CollectionPage', array( 'Organization', 'WebSite', 'CollectionPage', 'BreadcrumbList' ) ),
			'tag archive'             => array( get_tag_link( get_term_by( 'name', 'Scooter', 'post_tag' )->term_id ), 'CollectionPage', array( 'Organization', 'WebSite', 'CollectionPage', 'BreadcrumbList' ) ),
			'author archive'          => array( get_author_posts_url( $this->author ), 'ProfilePage', array( 'Organization', 'WebSite', 'ProfilePage', 'BreadcrumbList', 'Person' ) ),
			'search results'          => array( home_url( '/?s=scooter' ), 'SearchResultsPage', array( 'Organization', 'WebSite', 'SearchResultsPage', 'BreadcrumbList' ) ),
			'year archive'            => array( get_year_link( 2026 ), 'CollectionPage', array( 'Organization', 'WebSite', 'CollectionPage', 'BreadcrumbList' ) ),
		);

		foreach ( $cases as $label => $case ) {
			$blocks = $this->blocks_at( $case[0] );
			$this->assertCount( 1, $blocks, $label );
			$nodes = $this->assert_well_formed( $blocks );
			$this->assertSame( $case[2], $this->types_of( $nodes ), $label );

			$webpage = $this->node( $nodes, $case[1] );
			$this->assertStringStartsWith( home_url( '/' ), $webpage['url'], $label );
			$this->assertNotSame( '', $webpage['name'], $label );
			$this->assertArrayNotHasKey( 'description', $webpage, $label . ': archives get no invented description.' );
		}
	}

	public function test_page_names_follow_the_canonical_trail() {
		$nodes = $this->nodes_of( $this->blocks_at( get_category_link( $this->child_cat ) ) );
		$this->assertSame( 'Scooters', $this->node( $nodes, 'CollectionPage' )['name'] );
		$this->assertSame( get_category_link( $this->child_cat ), $this->node( $nodes, 'CollectionPage' )['url'] );

		$nodes = $this->nodes_of( $this->blocks_at( home_url( '/?s=scooter' ) ) );
		$this->assertSame( 'Search results for: scooter', $this->node( $nodes, 'SearchResultsPage' )['name'] );

		$nodes = $this->nodes_of( $this->blocks_at( home_url( '/' ) ) );
		$this->assertSame( get_bloginfo( 'name' ), $this->node( $nodes, 'WebPage' )['name'] );
	}

	public function test_author_archive_is_a_profile_page_about_the_person() {
		$nodes   = $this->nodes_of( $this->blocks_at( get_author_posts_url( $this->author ) ) );
		$profile = $this->node( $nodes, 'ProfilePage' );
		$person  = $this->node( $nodes, 'Person' );

		$this->assertSame( array( '@id' => $person['@id'] ), $profile['mainEntity'] );
		$this->assertSame( 'Linh Tran', $person['name'] );
		$this->assertNotContains( 'Article', $this->types_of( $nodes ) );
	}

	public function test_static_front_page_and_posts_page() {
		$front = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'Welcome',
				'post_excerpt' => '',
			)
		);
		$blog  = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'The blog',
				'post_excerpt' => '',
			)
		);
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front );
		update_option( 'page_for_posts', $blog );

		$nodes = $this->assert_well_formed( $this->blocks_at( home_url( '/' ) ) );
		$this->assertSame( array( 'Organization', 'WebSite', 'WebPage' ), $this->types_of( $nodes ) );
		$this->assertSame( 'Welcome', $this->node( $nodes, 'WebPage' )['name'] );

		$nodes = $this->assert_well_formed( $this->blocks_at( get_permalink( $blog ) ) );
		$this->assertSame( array( 'Organization', 'WebSite', 'CollectionPage' ), $this->types_of( $nodes ) );
		$this->assertSame( 'The blog', $this->node( $nodes, 'CollectionPage' )['name'] );
		$this->assertSame( get_permalink( $blog ), $this->node( $nodes, 'CollectionPage' )['url'] );
	}

	public function test_pages_do_not_get_an_article() {
		$page  = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'Rental terms',
				'post_excerpt' => '',
			)
		);
		$nodes = $this->assert_well_formed( $this->blocks_at( get_permalink( $page ) ) );
		$this->assertNotContains( 'Article', $this->types_of( $nodes ) );
		$this->assertNotContains( 'Person', $this->types_of( $nodes ) );
	}

	public function test_nothing_is_printed_for_404_previews_and_non_public_content() {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$draft    = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		$private  = self::factory()->post->create( array( 'post_status' => 'private' ) );
		$password = self::factory()->post->create( array( 'post_password' => 'secret' ) );

		$this->assertSame( array(), $this->blocks_at( home_url( '/definitely-missing/' ) ), '404' );
		$this->assertSame( array(), $this->blocks_at( home_url( '/?p=' . $draft . '&preview=true' ) ), 'draft preview' );
		$this->assertSame( array(), $this->blocks_at( get_permalink( $private ) ), 'private post' );
		$this->assertSame( array(), $this->blocks_at( get_permalink( $password ) ), 'password protected post' );
	}

	public function test_faq_node_joins_the_graph_when_visible() {
		$this->set_faq( $this->post_id, 2 );
		$blocks = $this->blocks_at( get_permalink( $this->post_id ) );
		$nodes  = $this->assert_well_formed( $blocks );

		$this->assertSame( array( 'Organization', 'WebSite', 'WebPage', 'BreadcrumbList', 'Article', 'Person', 'FAQPage' ), $this->types_of( $nodes ) );
		$faq = $this->node( $nodes, 'FAQPage' );
		$this->assertCount( 2, $faq['mainEntity'] );
		$this->assertSame( 'Question number 1?', $faq['mainEntity'][0]['name'] );
		$this->assertSame( 'Answer', $faq['mainEntity'][0]['acceptedAnswer']['@type'] );
	}

	public function test_local_business_joins_the_graph_and_is_linked_from_the_organization() {
		$this->set_profile( $this->complete_profile() );
		update_post_meta( $this->post_id, NH_SEO_Keys::META_LOCAL_BUSINESS, 1 );

		$nodes = $this->assert_well_formed( $this->blocks_at( get_permalink( $this->post_id ) ) );
		$this->assertContains( 'AutoRental', $this->types_of( $nodes ) );

		$business = $this->node( $nodes, 'AutoRental' );
		$org      = $this->node( $nodes, 'Organization' );
		$this->assertSame( array( '@id' => $business['@id'] ), $org['location'] );
		$this->assertSame( 'PostalAddress', $business['address']['@type'] );
		$this->assertSame( '1 Example Street', $business['address']['streetAddress'] );
	}

	public function test_off_mode_prints_nothing() {
		$this->set_settings( array( 'schema_mode' => 'off' ) );
		$this->assertSame( array(), $this->blocks_at( get_permalink( $this->post_id ) ) );
	}

	public function test_filter_edits_the_graph_and_repeated_ids_are_dropped() {
		$seen = array();
		add_filter(
			'nh_seo_schema_graph',
			function ( $nodes, $mode, $context ) use ( &$seen ) {
				$seen = array( $mode, $context['kind'], $context['url'] );
				$org  = $this->node( $nodes, 'Organization' );

				$nodes[] = array(
					'@type' => 'Thing',
					'@id'   => $org['@id'],
					'name'  => 'Duplicate id must be dropped',
				);
				$nodes[] = array(
					'@type' => 'Brand',
					'@id'   => home_url( '/#brand' ),
					'name'  => 'Added by a site owner',
				);
				$nodes[] = 'not a node';
				$nodes[] = array( 'name' => 'no type' );
				return $nodes;
			},
			10,
			3
		);

		$url   = get_permalink( $this->post_id );
		$nodes = $this->assert_well_formed( $this->blocks_at( $url ) );

		$this->assertSame( array( 'full', 'singular', $url ), $seen );
		$this->assertContains( 'Brand', $this->types_of( $nodes ) );
		$this->assertNotContains( 'Thing', $this->types_of( $nodes ) );
		$this->assertNotContains( 'not a node', $nodes );
	}

	public function test_hook_is_registered_on_wp_head_with_priority_20() {
		$this->assertSame( 20, has_action( 'wp_head', array( 'NH_SEO_Schema', 'print_head' ) ) );
	}

	public function test_print_head_wraps_every_block_in_a_json_ld_script_element() {
		$this->go_to( get_permalink( $this->post_id ) );
		ob_start();
		NH_SEO_Schema::print_head();
		$html = ob_get_clean();

		$this->assertSame( 1, substr_count( $html, '<script type="application/ld+json">' ) );
		$this->assertSame( 1, substr_count( $html, '</script>' ) );
		$this->assertSame( 1, preg_match( '#<script type="application/ld\+json">(.+)</script>#s', $html, $match ) );
		$this->assertIsArray( json_decode( $match[1], true, 512, JSON_THROW_ON_ERROR ) );
	}

	public function test_no_remote_request_is_ever_made() {
		$this->set_profile( $this->complete_profile() );
		update_post_meta( $this->post_id, NH_SEO_Keys::META_LOCAL_BUSINESS, 1 );
		$this->set_faq( $this->post_id, 3 );

		$this->blocks_at( get_permalink( $this->post_id ) );
		$this->blocks_at( home_url( '/' ) );
		$this->blocks_at( get_category_link( $this->child_cat ) );
		$this->assertSame( 0, $this->http_attempts );
	}
}
