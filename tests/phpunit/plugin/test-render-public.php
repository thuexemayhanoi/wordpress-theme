<?php
/**
 * Tests for the public renderers and the HTML builders behind them.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * The public renderers (summary, takeaways, FAQ, sources, updated) and the HTML builders behind them.
 */
class NH_Test_Render_Public extends NH_Unit_Test_Case {

	public function set_up() {
		parent::set_up();
		remove_theme_support( 'nguyen-ha-companion' );
	}

	/**
	 * Assert that every class attribute in some HTML only holds plugin-prefixed classes.
	 *
	 * @param string $html HTML.
	 * @return void
	 */
	private function assert_classes_are_prefixed( $html ) {
		$this->assertGreaterThan( 0, preg_match_all( '/class="([^"]*)"/', $html, $matches ) );
		foreach ( $matches[1] as $value ) {
			foreach ( preg_split( '/\s+/', trim( $value ) ) as $class ) {
				$this->assertStringStartsWith( 'nh-seo-', $class );
			}
		}
	}

	public function test_summary_is_semantic_escaped_and_prefixed() {
		$html = NH_SEO_Renderer::summary( 'Pick an automatic scooter & ride "slowly" <today>.' );

		$this->assertStringStartsWith( '<section class="nh-seo-block nh-seo-summary">', $html );
		$this->assertStringContainsString( '<h2 class="nh-seo-block__title">Quick answer</h2>', $html );
		$this->assertStringContainsString( '<p class="nh-seo-summary__text">Pick an automatic scooter &amp; ride &quot;slowly&quot; &lt;today&gt;.</p>', $html );
		$this->assertStringEndsWith( '</section>', $html );
		$this->assert_classes_are_prefixed( $html );
	}

	public function test_builders_return_an_empty_string_when_there_is_nothing_to_show() {
		$this->assertSame( '', NH_SEO_Renderer::summary( '' ) );
		$this->assertSame( '', NH_SEO_Renderer::summary( '   ' ) );
		$this->assertSame( '', NH_SEO_Renderer::summary( array( 'x' ) ) );
		$this->assertSame( '', NH_SEO_Renderer::takeaways( array() ) );
		$this->assertSame( '', NH_SEO_Renderer::takeaways( array( '', '  ' ) ) );
		$this->assertSame( '', NH_SEO_Renderer::takeaways( 'not a list' ) );
		$this->assertSame( '', NH_SEO_Renderer::faq( array() ) );
		$this->assertSame( '', NH_SEO_Renderer::faq( array( array( 'question' => 'Only a question' ) ) ) );
		$this->assertSame( '', NH_SEO_Renderer::faq( array( 'garbage', 42, null ) ) );
		$this->assertSame(
			'',
			NH_SEO_Renderer::faq(
				array(
					array(
						'question' => 'Q',
						'answer'   => '<img src="x"><p> </p><iframe src="//evil.example"></iframe>',
					),
				)
			),
			'an answer without any text is not shown'
		);
		$this->assertSame( '', NH_SEO_Renderer::sources( array() ) );
		$this->assertSame( '', NH_SEO_Renderer::sources( array( array( 'title' => 'No URL' ) ) ) );
		$this->assertSame( '', NH_SEO_Renderer::updated( '' ) );
		$this->assertSame( '', NH_SEO_Renderer::updated( 'yesterday' ) );
		$this->assertSame( '', NH_SEO_Renderer::updated( '2999-01-01' ) );
	}

	public function test_takeaways_are_a_list_of_escaped_items() {
		$html = NH_SEO_Renderer::takeaways( array( 'One', '<b>Two</b>', 'Tom & "Jerry"', '' ) );

		$this->assertStringContainsString( '<ul class="nh-seo-takeaways__list">', $html );
		$this->assertStringContainsString( '<li>One</li>', $html );
		$this->assertStringContainsString( '<li>&lt;b&gt;Two&lt;/b&gt;</li>', $html );
		$this->assertStringContainsString( '<li>Tom &amp; &quot;Jerry&quot;</li>', $html );
		$this->assertSame( 3, substr_count( $html, '<li>' ) );
		$this->assertStringContainsString( 'Key takeaways', $html );
		$this->assert_classes_are_prefixed( $html );
	}

	public function test_faq_escapes_questions_and_filters_answers() {
		$html = NH_SEO_Renderer::faq(
			array(
				array(
					'question' => '<img src=x onerror=alert(1)>Is it safe?',
					'answer'   => '<p onclick="evil()">Yes.</p><script>alert(2)</script><a href="javascript:alert(3)" onmouseover="x()">bad</a><a href="https://example.org/guide" rel="noopener">good</a><iframe src="//evil.example"></iframe><img src=x onerror=alert(4)><style>body{display:none}</style>',
				),
			)
		);

		$this->assertStringContainsString( '<h3 class="nh-seo-faq__question">&lt;img src=x onerror=alert(1)&gt;Is it safe?</h3>', $html );
		$this->assertStringContainsString( '<p>Yes.</p>', $html );
		$this->assertStringContainsString( '<a href="https://example.org/guide" rel="noopener">good</a>', $html );
		foreach ( array( '<script', '<iframe', '<img', '<style', 'javascript:', 'onclick', 'onmouseover', 'onerror=alert(4)' ) as $needle ) {
			$this->assertStringNotContainsStringIgnoringCase( $needle, str_replace( '&lt;img src=x onerror=alert(1)&gt;', '', $html ), $needle );
		}
		$this->assert_classes_are_prefixed( $html );
	}

	public function test_faq_answers_without_markup_become_paragraphs() {
		$html = NH_SEO_Renderer::faq(
			array(
				array(
					'question' => 'Q?',
					'answer'   => "First paragraph.\n\nSecond paragraph.",
				),
			)
		);
		$this->assertStringContainsString( '<p>First paragraph.</p>', $html );
		$this->assertStringContainsString( '<p>Second paragraph.</p>', $html );
	}

	public function test_faq_keeps_every_item_visible_without_scripts() {
		$html = NH_SEO_Renderer::faq(
			array(
				array(
					'question' => 'One?',
					'answer'   => 'First',
				),
				array(
					'question' => 'Two?',
					'answer'   => 'Second',
				),
			)
		);
		$this->assertSame( 2, substr_count( $html, 'nh-seo-faq__item' ) );
		$this->assertStringNotContainsString( '<details', $html );
		$this->assertStringNotContainsString( 'hidden', $html );
		$this->assertStringNotContainsString( 'display:none', $html );
	}

	public function test_sources_are_http_links_with_escaped_titles() {
		$html = NH_SEO_Renderer::sources(
			array(
				array(
					'title'     => '<b>Official</b> guide',
					'url'       => 'https://example.org/path?a=1&b=2',
					'publisher' => 'Example & Sons',
				),
				array( 'url' => 'javascript:alert(1)' ),
				array( 'url' => 'data:text/html;base64,AAAA' ),
				array( 'url' => 'https://www.example.net/page' ),
				'garbage',
			)
		);

		$this->assertStringContainsString( '<ol class="nh-seo-sources__list">', $html );
		$this->assertSame( 2, substr_count( $html, '<li class="nh-seo-sources__item">' ) );
		$this->assertStringContainsString( '<a href="https://example.org/path?a=1&#038;b=2">&lt;b&gt;Official&lt;/b&gt; guide</a>', $html );
		$this->assertStringContainsString( '<span class="nh-seo-sources__publisher">(Example &amp; Sons)</span>', $html );
		$this->assertStringContainsString( '>www.example.net</a>', $html, 'a missing title falls back to the host' );
		$this->assertStringNotContainsString( 'javascript:', $html );
		$this->assertStringNotContainsString( 'data:text', $html );
		$this->assert_classes_are_prefixed( $html );
	}

	public function test_updated_line_uses_a_machine_readable_time_element() {
		update_option( 'date_format', 'j F Y' );
		$html = NH_SEO_Renderer::updated( '2024-01-15' );

		$this->assertStringContainsString( '<time datetime="2024-01-15">15 January 2024</time>', $html );
		$this->assertStringContainsString( 'Content updated:', $html );
		$this->assert_classes_are_prefixed( $html );
	}

	public function test_updated_date_respects_the_site_timezone_without_shifting_the_day() {
		update_option( 'date_format', 'Y-m-d' );
		foreach ( array( 'Asia/Ho_Chi_Minh', 'America/Los_Angeles', 'UTC', 'Pacific/Kiritimati' ) as $timezone ) {
			update_option( 'timezone_string', $timezone );
			$this->assertStringContainsString( '>2024-03-01</time>', NH_SEO_Renderer::updated( '2024-03-01' ), $timezone );
		}
	}

	public function test_vietnamese_text_is_preserved() {
		$html = NH_SEO_Renderer::faq(
			array(
				array(
					'question' => 'Thuê xe máy ở Hà Nội cần giấy tờ gì?',
					'answer'   => '<p>Hộ chiếu và bằng lái xe hợp lệ.</p>',
				),
			)
		);
		$this->assertStringContainsString( 'Thuê xe máy ở Hà Nội cần giấy tờ gì?', $html );
		$this->assertStringContainsString( '<p>Hộ chiếu và bằng lái xe hợp lệ.</p>', $html );
		$this->assertStringContainsString( 'đ', NH_SEO_Renderer::summary( 'Đi đường đông đúc.' ) );
	}

	public function test_public_functions_read_the_post_meta() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		update_post_meta( $post_id, 'nh_summary', 'The short answer.' );
		update_post_meta( $post_id, 'nh_takeaways', array( 'First', 'Second' ) );
		update_post_meta(
			$post_id,
			'nh_faq',
			array(
				array(
					'question' => 'Why?',
					'answer'   => '<p>Because.</p>',
				),
			)
		);
		update_post_meta( $post_id, 'nh_sources', array( array( 'url' => 'https://example.org/ref' ) ) );
		update_post_meta( $post_id, 'nh_content_updated', '2024-02-03' );

		$this->assertStringContainsString( 'The short answer.', nh_seo_render_summary( $post_id ) );
		$this->assertStringContainsString( '<li>Second</li>', nh_seo_render_takeaways( $post_id ) );
		$this->assertStringContainsString( 'Because.', nh_seo_render_faq( $post_id ) );
		$this->assertStringContainsString( 'https://example.org/ref', nh_seo_render_sources( $post_id ) );
		$this->assertStringContainsString( '2024-02-03', nh_seo_render_updated( $post_id ) );

		// Post object and "current post" forms.
		$this->assertSame( nh_seo_render_summary( $post_id ), nh_seo_render_summary( get_post( $post_id ) ) );
		$GLOBALS['post'] = get_post( $post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test fixture for the "current post" default.
		$this->assertSame( nh_seo_render_summary( $post_id ), nh_seo_render_summary() );
	}

	public function test_public_functions_return_an_empty_string_without_data_or_post() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		foreach ( array( 'summary', 'takeaways', 'faq', 'sources', 'updated' ) as $name ) {
			$function = 'nh_seo_render_' . $name;
			$this->assertSame( '', $function( $post_id ), $name . ' without data' );
			$this->assertSame( '', $function( 999999 ), $name . ' for a missing post' );
		}
	}

	public function test_public_functions_sanitize_what_is_stored_before_rendering() {
		global $wpdb;
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		// Hostile values written behind the sanitizers' back, as a compromised database or an old import could hold.
		foreach ( array(
			'nh_summary' => '<script>alert(1)</script>Summary <img src=x onerror=alert(2)>',
			'nh_faq'     => maybe_serialize(
				array(
					array(
						'question' => '<b>Q</b>',
						'answer'   => '<p onclick="evil()">A</p><script>alert(3)</script>',
					),
				)
			),
			'nh_sources' => maybe_serialize( array( array( 'url' => 'javascript:alert(4)' ), array( 'url' => 'https://example.org/ok' ) ) ),
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

		$all = nh_seo_render_summary( $post_id ) . nh_seo_render_faq( $post_id ) . nh_seo_render_sources( $post_id );

		foreach ( array( '<script', '<img', '<b>', 'onclick', 'onerror', 'javascript:' ) as $needle ) {
			$this->assertStringNotContainsStringIgnoringCase( $needle, $all, $needle );
		}
		$this->assertStringContainsString( 'https://example.org/ok', $all );
		$this->assertStringContainsString( '<h3 class="nh-seo-faq__question">Q</h3>', $all, 'tags are stripped from questions on read' );
		$this->assertStringContainsString( '<p>A</p>', $all );
		$this->assertStringContainsString( 'Summary', $all );
		$this->assertStringNotContainsString( 'alert(1)', nh_seo_render_summary( $post_id ), 'script content never reaches the summary' );
	}

	public function test_password_protected_posts_render_nothing_until_unlocked() {
		$post_id = self::factory()->post->create(
			array(
				'post_status'   => 'publish',
				'post_password' => 'secret',
			)
		);
		update_post_meta( $post_id, 'nh_summary', 'Protected summary' );
		update_post_meta( $post_id, 'nh_takeaways', array( 'Protected point' ) );

		$this->assertSame( '', nh_seo_render_summary( $post_id ) );
		$this->assertSame( '', nh_seo_render_takeaways( $post_id ) );
		$this->assertSame( '', nh_seo_render_faq( $post_id ) );
		$this->assertSame( '', nh_seo_render_sources( $post_id ) );
		$this->assertSame( '', nh_seo_render_updated( $post_id ) );
	}

	public function test_every_public_renderer_uses_only_prefixed_classes() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		update_post_meta( $post_id, 'nh_summary', 'S' );
		update_post_meta( $post_id, 'nh_takeaways', array( 'T' ) );
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
		update_post_meta( $post_id, 'nh_sources', array( array( 'url' => 'https://example.org' ) ) );
		update_post_meta( $post_id, 'nh_content_updated', '2024-01-01' );

		foreach ( array( 'summary', 'takeaways', 'faq', 'sources', 'updated' ) as $name ) {
			$function = 'nh_seo_render_' . $name;
			$this->assert_classes_are_prefixed( $function( $post_id ) );
		}
	}
}
