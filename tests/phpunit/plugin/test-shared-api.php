<?php
/**
 * Contract tests for the companion plugin shared API (includes/functions.php).
 *
 * @package nguyen-ha-seo-companion
 */

/**
 * Shared API contract tests.
 */
class NH_Test_Shared_Api extends NH_Unit_Test_Case {

	public function set_up() {
		parent::set_up();
		remove_theme_support( 'nguyen-ha-companion' );
	}

	public function test_summary_is_plain_text_and_limited() {
		$this->assertSame( 'Hello world', nh_seo_sanitize_summary( "  <b>Hello</b>\n\n  world " ) );
		$this->assertSame( '', nh_seo_sanitize_summary( array( 'x' ) ) );
		$this->assertLessThanOrEqual( NH_SEO_Keys::LIMIT_SUMMARY, mb_strlen( nh_seo_sanitize_summary( str_repeat( 'đ', 2000 ) ) ) );
	}

	public function test_takeaways_accept_array_and_textarea() {
		$this->assertSame( array( 'One', 'Two' ), nh_seo_sanitize_takeaways( "One\n\n<i>Two</i>\n" ) );
		$many = array_fill( 0, 30, 'Item' );
		$this->assertCount( NH_SEO_Keys::LIMIT_TAKEAWAYS, nh_seo_sanitize_takeaways( $many ) );
		$this->assertSame( array(), nh_seo_sanitize_takeaways( 42 ) );
	}

	public function test_faq_accepts_aliases_json_and_strips_unsafe_html() {
		$faq = nh_seo_sanitize_faq(
			array(
				array(
					'q' => 'Cần giấy tờ gì?',
					'a' => '<p>Xem <a href="https://example.org/guide" onclick="x()">hướng dẫn</a><script>alert(1)</script></p>',
				),
				array(
					'question' => '',
					'answer'   => 'no question',
				),
				'not-an-array',
			)
		);
		$this->assertCount( 1, $faq );
		$this->assertSame( 'Cần giấy tờ gì?', $faq[0]['question'] );
		$this->assertStringContainsString( '<a href="https://example.org/guide">hướng dẫn</a>', $faq[0]['answer'] );
		$this->assertStringNotContainsString( 'onclick', $faq[0]['answer'] );
		$this->assertStringNotContainsString( '<script', $faq[0]['answer'] );

		$from_json = nh_seo_sanitize_faq( '[{"question":"Q?","answer":"A."}]' );
		$this->assertSame( 'Q?', $from_json[0]['question'] );
		$this->assertSame( array(), nh_seo_sanitize_faq( 'not json' ) );
	}

	public function test_faq_blocks_javascript_links_and_caps_items() {
		$faq = nh_seo_sanitize_faq(
			array(
				array(
					'question' => 'Q',
					'answer'   => '<a href="javascript:alert(1)">x</a> text',
				),
			)
		);
		$this->assertStringNotContainsString( 'javascript:', $faq[0]['answer'] );

		$items = array_fill(
			0,
			40,
			array(
				'question' => 'Q',
				'answer'   => 'A',
			)
		);
		$this->assertCount( NH_SEO_Keys::LIMIT_FAQ_ITEMS, nh_seo_sanitize_faq( $items ) );
	}

	public function test_sources_require_http_urls_and_fall_back_to_host_title() {
		$sources = nh_seo_sanitize_sources(
			array(
				array(
					'title'     => 'Gov',
					'url'       => 'https://example.gov/x',
					'publisher' => 'Ministry',
				),
				array( 'url' => 'javascript:alert(1)' ),
				array( 'url' => 'ftp://example.org/file' ),
				array( 'url' => 'https://example.org/page' ),
				array( 'title' => 'No url' ),
			)
		);
		$this->assertCount( 2, $sources );
		$this->assertSame( 'Ministry', $sources[0]['publisher'] );
		$this->assertSame( 'example.org', $sources[1]['title'] );
	}

	public function test_updated_date_must_be_valid_and_not_in_the_future() {
		$this->assertSame( '2025-03-09', nh_seo_sanitize_date( '2025-03-09' ) );
		$this->assertSame( '', nh_seo_sanitize_date( '2025-02-30' ) );
		$this->assertSame( '', nh_seo_sanitize_date( '09/03/2025' ) );
		$this->assertSame( '', nh_seo_sanitize_date( gmdate( 'Y-m-d', time() + 10 * DAY_IN_SECONDS ) ) );
	}

	public function test_pillar_id_must_be_public_post() {
		$published = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$draft     = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		$private   = self::factory()->post->create( array( 'post_status' => 'private' ) );
		$password  = self::factory()->post->create(
			array(
				'post_status'   => 'publish',
				'post_password' => 'secret',
			)
		);
		$this->assertSame( $published, nh_seo_sanitize_pillar_id( $published ) );
		$this->assertSame( 0, nh_seo_sanitize_pillar_id( $draft ) );
		$this->assertSame( 0, nh_seo_sanitize_pillar_id( $private ) );
		$this->assertSame( 0, nh_seo_sanitize_pillar_id( $password ) );
		$this->assertSame( 0, nh_seo_sanitize_pillar_id( 999999 ) );
	}

	public function test_hours_use_schema_org_text_format() {
		$this->assertSame(
			array( 'Mo-Su 09:00-21:00', 'Sa,Su 10:00-12:30' ),
			nh_seo_sanitize_hours( "Mo-Su 09:00-21:00\nevery day\nSa,Su 10:00-12:30\nMo-Su 25:00-26:00" )
		);
	}

	public function test_business_profile_is_empty_by_default_and_sanitized() {
		delete_option( NH_SEO_Keys::OPTION_BUSINESS );
		$profile = nh_seo_get_business_profile();
		$this->assertSame( '', $profile['name'] );
		$this->assertSame( '', $profile['phone'] );
		$this->assertFalse( nh_seo_business_is_complete( $profile ) );

		$clean = nh_seo_sanitize_business(
			array(
				'name'    => '<b>Shop</b>',
				'type'    => 'NotAType',
				'phone'   => '+84 000 000 000 <script>',
				'country' => 'vn1',
				'lat'     => '999',
				'lng'     => '105.85',
				'social'  => "https://example.org/a\njavascript:alert(1)\nhttps://example.org/a",
				'email'   => 'bad',
			)
		);
		$this->assertSame( 'Shop', $clean['name'] );
		$this->assertSame( 'LocalBusiness', $clean['type'] );
		$this->assertSame( '+84 000 000 000', $clean['phone'] );
		$this->assertSame( 'VN', $clean['country'] );
		$this->assertSame( '', $clean['lat'] );
		$this->assertSame( '105.85', $clean['lng'] );
		$this->assertSame( array( 'https://example.org/a' ), $clean['social'] );
		$this->assertSame( '', $clean['email'] );
	}

	public function test_business_completeness_requires_name_phone_and_address() {
		$profile = array_merge(
			nh_seo_business_defaults(),
			array(
				'name'     => 'Example Co',
				'phone'    => '+00 000',
				'street'   => '1 Example Street',
				'locality' => 'Example City',
			)
		);
		$this->assertTrue( nh_seo_business_is_complete( $profile ) );
		$profile['phone'] = '';
		$this->assertFalse( nh_seo_business_is_complete( $profile ) );
	}

	public function test_theme_support_shapes() {
		$this->assertFalse( nh_seo_theme_supports( 'faq' ) );

		add_theme_support(
			'nguyen-ha-companion',
			array(
				'faq'     => true,
				'summary' => false,
			)
		);
		$this->assertTrue( nh_seo_theme_supports( 'faq' ) );
		$this->assertFalse( nh_seo_theme_supports( 'summary' ) );
		$this->assertFalse( nh_seo_theme_supports( 'sources' ) );

		remove_theme_support( 'nguyen-ha-companion' );
		add_theme_support( 'nguyen-ha-companion' );
		$this->assertTrue( nh_seo_theme_supports( 'sources' ) );
	}

	public function test_schema_mode_resolution() {
		delete_option( NH_SEO_Keys::OPTION_SETTINGS );
		$this->assertSame( 'full', nh_seo_schema_mode() );

		add_filter(
			'nh_seo_detected_seo_plugin',
			static function () {
				return 'yoast';
			}
		);
		$this->assertSame( 'extras', nh_seo_schema_mode() );

		update_option( NH_SEO_Keys::OPTION_SETTINGS, array( 'schema_mode' => 'off' ) );
		$this->assertSame( 'off', nh_seo_schema_mode() );

		update_option( NH_SEO_Keys::OPTION_SETTINGS, array( 'schema_mode' => 'bogus' ) );
		$this->assertSame( 'extras', nh_seo_schema_mode() );
	}

	public function test_post_extras_are_normalized_on_read() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, NH_SEO_Keys::META_SUMMARY, '<i>Short answer.</i>' );
		update_post_meta( $post_id, NH_SEO_Keys::META_TAKEAWAYS, array( 'A', '', 'B' ) );
		update_post_meta(
			$post_id,
			NH_SEO_Keys::META_FAQ,
			array(
				array(
					'question' => 'Q?',
					'answer'   => 'A.',
				),
			)
		);
		update_post_meta( $post_id, NH_SEO_Keys::META_SOURCES, array( array( 'url' => 'https://example.org' ) ) );
		update_post_meta( $post_id, NH_SEO_Keys::META_UPDATED, '2024-01-15' );

		$extras = nh_seo_get_post_extras( $post_id );
		$this->assertSame( 'Short answer.', $extras['summary'] );
		$this->assertSame( array( 'A', 'B' ), $extras['takeaways'] );
		$this->assertCount( 1, $extras['faq'] );
		$this->assertCount( 1, $extras['sources'] );
		$this->assertSame( '2024-01-15', $extras['updated'] );
	}
}
