<?php
/**
 * The theme and the companion plugin together: the support handshake, the data contract, the business
 * profile and the cluster alias, rendered through real pages.
 *
 * @package nguyen-ha-editorial
 */

/**
 * Both components active, as on a production site.
 */
class NH_Test_Plugin_Integration extends NH_Render_Test_Case {

	public function set_up() {
		parent::set_up();
		$this->assertTrue( function_exists( 'nh_seo_theme_supports' ), 'The companion plugin must be loaded for these tests.' );
	}

	public function test_the_theme_declares_every_editorial_block_it_renders_itself() {
		foreach ( array( 'summary', 'takeaways', 'faq', 'sources', 'updated' ) as $feature ) {
			$this->assertTrue( nh_seo_theme_supports( $feature ), $feature . ' is rendered by the theme, so the plugin must stay out of the way' );
		}
		$this->assertFalse( nh_seo_theme_supports( 'something-else' ) );
	}

	public function test_an_article_shows_each_editorial_block_once_and_carries_no_plugin_markup() {
		$site = $this->build_site();
		$html = $this->render_url( get_permalink( $site['rich'] ) );
		$xp   = $this->xpath( $html );

		$this->assertSame( 1, $xp->query( '//section[@id="faq"]' )->length );
		$this->assertSame( 1, $xp->query( '//section[@id="sources"]' )->length );
		$this->assertSame( 1, $xp->query( $this->by_class( 'summary', 'section' ) )->length );
		$this->assertSame( 1, $xp->query( $this->by_class( 'takeaways', 'section' ) )->length );
		$this->assertSame( 2, substr_count( $html, 'Is it simple?' ) + substr_count( $html, 'Is it cheap?' ), 'each question is printed once' );
		$this->assertStringNotContainsString( 'nh-seo-', $html, 'the plugin prints none of its own blocks on a theme that renders them' );
		$this->assertSame( 0, $xp->query( '//script[@type="application/ld+json"]' )->length, 'structured data is off in these tests' );
	}

	public function test_text_stored_through_the_plugin_sanitizers_is_clean_before_the_theme_sees_it() {
		$site = $this->build_site();
		update_post_meta(
			$site['plain'],
			'nh_faq',
			array(
				array(
					'question' => 'Safe?',
					'answer'   => '<p onclick="alert(1)">Yes <script>alert(1)</script><a href="javascript:alert(1)">x</a></p>',
				),
			)
		);

		$stored = wp_json_encode( get_post_meta( $site['plain'], 'nh_faq', true ) );
		$this->assertStringNotContainsString( 'onclick', $stored, 'the registered sanitize callback already cleaned the value' );
		$this->assertStringNotContainsString( 'javascript:', $stored );

		$html = $this->render_url( get_permalink( $site['plain'] ) );
		$this->assertSame( 1, $this->count_nodes( $html, '//section[@id="faq"]' ) );
		$this->assertSame( array(), array_values( array_diff( $this->inline_handlers( $html ), array( 'link:onload' ) ) ) );
	}

	public function test_contact_details_appear_only_when_the_owner_entered_them() {
		$this->build_site();
		$html = $this->render_url( home_url( '/' ) );
		$this->assertSame( 0, $this->count_nodes( $html, $this->by_class( 'contact-list' ) ), 'no business data, no contact block' );

		update_option(
			NH_SEO_Keys::OPTION_BUSINESS,
			array(
				'name'     => 'Example Rentals <b>Ltd</b>',
				'phone'    => '+00 111 222 333',
				'email'    => 'hello@example.org',
				'street'   => '1 Example Street',
				'locality' => 'Exampletown',
				'hours'    => "Mo-Su 09:00-21:00\nnot an hours line",
				'has_map'  => 'https://maps.example.org/place',
				'social'   => "https://www.facebook.com/example\njavascript:alert(1)",
			)
		);
		nh_editorial_cache_bump_now();

		$html = $this->render_url( home_url( '/' ) );
		$xp   = $this->xpath( $html );
		$this->assertSame( 1, $xp->query( $this->by_class( 'contact-list', 'ul' ) )->length );
		$this->assertSame( 1, $xp->query( '//ul[contains(@class,"contact-list")]//a[@href="tel:+00111222333"]' )->length, 'the phone is a tel: link' );
		$this->assertSame( 1, $xp->query( '//ul[contains(@class,"contact-list")]//a[@href="mailto:hello@example.org"]' )->length );
		$this->assertStringContainsString( '1 Example Street, Exampletown', $html );
		$this->assertSame( 1, $xp->query( '//a[contains(@class,"contact-list__map")][@href="https://maps.example.org/place"]' )->length );
		$this->assertSame( 1, $xp->query( '//span[contains(@class,"contact-list__hours")]' )->length, 'only the valid opening-hours line is kept' );
		$this->assertSame( 1, $xp->query( '//ul[contains(@class,"social-links")]//a[@href="https://www.facebook.com/example"]' )->length );
		$this->assertSame( 0, $xp->query( '//h2[contains(@class,"footer-title")][contains(.,"<b>")]' )->length, 'markup in the business name is not interpreted' );
		$this->assertSame( array(), array_values( array_diff( $this->inline_handlers( $html ), array( 'link:onload' ) ) ) );
		$this->assertSame( 0, $xp->query( '//@*[starts-with(translate(normalize-space(.), "JAVASCRIPT", "javascript"), "javascript:")]' )->length );

		delete_option( NH_SEO_Keys::OPTION_BUSINESS );
	}

	public function test_the_theme_setting_for_social_links_wins_over_the_business_profile() {
		$this->build_site();
		update_option( NH_SEO_Keys::OPTION_BUSINESS, array( 'social' => 'https://www.facebook.com/from-plugin' ) );
		set_theme_mod( 'nh_social_urls', 'https://www.instagram.com/from-theme' );

		$html = $this->render_url( home_url( '/' ) );
		$this->assertSame( 1, $this->count_nodes( $html, '//footer//a[@href="https://www.instagram.com/from-theme"]' ) );
		$this->assertSame( 0, $this->count_nodes( $html, '//footer//a[@href="https://www.facebook.com/from-plugin"]' ) );
	}

	public function test_the_cluster_alias_becomes_a_term_that_drives_the_silo_and_the_topic_box() {
		$site = $this->build_site();
		update_post_meta( $site['rich'], 'nh_topic_cluster', 'Scooter rental basics' );

		$terms = wp_get_object_terms( $site['rich'], 'topic_cluster' );
		$this->assertCount( 1, $terms, 'the plugin turned the alias into a Topic Cluster term' );
		$this->assertSame( 'Scooter rental basics', $terms[0]->name );

		nh_editorial_cache_bump_now();
		$silo = nh_editorial_get_silo( $site['rich'] );
		$this->assertSame( 'topic_cluster:' . $terms[0]->term_id, $silo['id'] );

		$html = $this->render_url( get_permalink( $site['rich'] ) );
		$this->assertStringContainsString( 'Part of the topic: Scooter rental basics', $html );

		$trail = wp_list_pluck( nh_seo_breadcrumb_trail(), 'name' );
		$this->assertSame( array( 'Home', 'Guides', 'Hanoi tips', 'The rich guide' ), $trail, 'clusters never change the breadcrumb' );
	}

	public function test_the_pillar_mapping_stored_by_the_plugin_reaches_the_category_page_and_the_article() {
		$site = $this->build_site();
		$html = $this->render_url( get_permalink( $site['rich'] ) );
		$this->assertContains( get_permalink( $site['pillar'] ), $this->hrefs( $html ), 'the topic box links to the Pillar page' );

		$other = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'A better pillar',
			)
		);
		update_post_meta( $site['rich'], 'nh_pillar_id', $other );
		nh_editorial_cache_bump_now();
		$html = $this->render_url( get_permalink( $site['rich'] ) );
		$this->assertContains( get_permalink( $other ), $this->hrefs( $html ), 'a post-level mapping beats the category-level one' );
		$this->assertNotContains( get_permalink( $site['pillar'] ), $this->hrefs( $html ) );
	}

	public function test_a_page_without_the_plugin_data_prints_none_of_it() {
		$site = $this->build_site();
		$html = $this->render_url( get_permalink( $site['plain'] ) );
		foreach ( array( 'article-intro', 'faq', 'sources' ) as $class_name ) {
			$this->assertSame( 0, $this->count_nodes( $html, $this->by_class( $class_name ) ), $class_name );
		}
	}
}
