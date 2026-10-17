<?php
/**
 * Output escaping: hostile data in every field the theme prints must come out inert.
 *
 * Each test stores the payload the way a compromised workflow, plugin or database could (straight into
 * meta, options and theme mods, bypassing the sanitizers that run on save) and renders the real page.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hostile data never becomes markup.
 */
class NH_Test_Output_Escaping extends NH_Render_Test_Case {

	/**
	 * Assert that a page carries no script, handler or javascript: URL of its own.
	 *
	 * @param string $html  HTML.
	 * @param string $label Failure label.
	 * @return void
	 */
	private function assert_inert( $html, $label ) {
		$xp = $this->xpath( $html );

		$this->assertSame( array(), array_values( array_diff( $this->inline_handlers( $html ), array( 'link:onload' ) ) ), $label . ': inline event handlers' );
		$this->assertSame( 0, $xp->query( '//script[contains(., "alert(")]' )->length, $label . ': injected script' );
		$this->assertSame( 0, $xp->query( '//@*[starts-with(translate(normalize-space(.), "JAVASCRIPT", "javascript"), "javascript:")]' )->length, $label . ': javascript: URL' );
		$this->assertSame( 0, $xp->query( '//iframe|//object|//embed|//form[@action and not(@role="search") and not(@id="commentform") and not(contains(@class,"post-password-form"))]' )->length, $label . ': injected element' );
	}

	public function test_editorial_extras_are_escaped_or_filtered_on_articles() {
		$site = $this->build_site();
		update_post_meta( $site['rich'], 'nh_summary', '<img src=x onerror=alert(1)>Plain summary' );
		update_post_meta( $site['rich'], 'nh_takeaways', array( '<script>alert(1)</script>First point', '<b onmouseover=alert(1)>bold</b> second' ) );
		update_post_meta(
			$site['rich'],
			'nh_faq',
			array(
				array(
					'question' => '<script>alert(1)</script>Safe question?',
					'answer'   => '<p onclick="alert(1)">Answer <a href="javascript:alert(1)" onclick="alert(1)">link</a><script>alert(1)</script><img src=x onerror=alert(1)><iframe src="https://evil.example"></iframe></p>',
				),
			)
		);
		update_post_meta(
			$site['rich'],
			'nh_sources',
			array(
				array(
					'title' => '<i>Styled</i> title',
					'url'   => 'javascript:alert(1)',
				),
				array(
					'title'     => 'Honest source',
					'url'       => 'https://example.org/honest',
					'publisher' => '<script>alert(1)</script>Publisher',
				),
			)
		);

		$html = $this->render_url( get_permalink( $site['rich'] ) );
		$this->assert_inert( $html, 'article' );

		$xp = $this->xpath( $html );
		$this->assertStringContainsString( 'Plain summary', $this->node_text( $xp, '//p[contains(@class,"summary__text")]' ) );
		$this->assertStringContainsString( 'First point', $html );
		$this->assertStringContainsString( 'Safe question?', $html );
		$this->assertSame( 1, $xp->query( '//section[@id="sources"]//li' )->length, 'a source with a javascript: URL is dropped' );
		$this->assertSame( 1, $xp->query( '//section[@id="sources"]//a[@href="https://example.org/honest"][@rel="noopener external"]' )->length );
		$this->assertSame( 0, $xp->query( '//section[@id="faq"]//a[@href]' )->length, 'a javascript: link in an answer loses its href' );
		$this->assertStringContainsString( 'Answer', $this->node_text( $xp, '//section[@id="faq"]//div[contains(@class,"faq__answer")]' ), 'the harmless text of the answer survives' );
	}

	/**
	 * Text content of the first node matching a query.
	 *
	 * @param DOMXPath $xp    XPath object.
	 * @param string   $query Query.
	 * @return string
	 */
	private function node_text( DOMXPath $xp, $query ) {
		$node = $xp->query( $query )->item( 0 );
		return $node ? trim( $node->textContent ) : '';
	}

	public function test_author_and_term_data_cannot_inject_markup() {
		global $wpdb;
		$site = $this->build_site();

		update_user_meta( $site['author'], 'description', '<script>alert(1)</script>Bio <img src=x onerror=alert(1)>' );
		update_user_meta( $site['author'], 'nh_job_title', '<img src=x onerror=alert(1)>Editor' );
		update_user_meta( $site['author'], 'nh_sameas', "javascript:alert(1)\nhttps://www.linkedin.com/in/example" );
		update_user_meta( $site['author'], 'twitter', 'javascript:alert(1)' );
		$wpdb->update( $wpdb->users, array( 'display_name' => '<script>alert(1)</script>Linh' ), array( 'ID' => $site['author'] ) );
		clean_user_cache( $site['author'] );

		$wpdb->update(
			$wpdb->term_taxonomy,
			array( 'description' => '<script>alert(1)</script><p onclick="alert(1)">Topic text</p><iframe src="https://evil.example"></iframe>' ),
			array( 'term_id' => $site['top'] )
		);
		clean_term_cache( $site['top'], 'category' );

		foreach ( array(
			'article'  => get_permalink( $site['rich'] ),
			'author'   => get_author_posts_url( $site['author'] ),
			'category' => get_category_link( $site['top'] ),
			'home'     => home_url( '/' ),
		) as $label => $url ) {
			$html = $this->render_url( $url );
			$this->assert_inert( $html, $label );
		}

		$html = $this->render_url( get_author_posts_url( $site['author'] ) );
		$this->assertSame( 1, $this->count_nodes( $html, '//a[@href="https://www.linkedin.com/in/example"]' ), 'the real profile link is kept' );

		$html = $this->render_url( get_category_link( $site['top'] ) );
		$this->assertStringContainsString( 'Topic text', $html );
	}

	public function test_customizer_values_are_escaped_even_when_stored_raw() {
		$this->build_site();
		set_theme_mod( 'nh_home_headline', '<script>alert(1)</script>Headline' );
		set_theme_mod( 'nh_home_subline', '<img src=x onerror=alert(1)>Subline' );
		set_theme_mod( 'nh_cta_label', '<img src=x onerror=alert(1)>Call us' );
		set_theme_mod( 'nh_cta_url', 'javascript:alert(1)' );
		set_theme_mod( 'nh_cta_text', '<script>alert(1)</script>Message' );
		set_theme_mod( 'nh_footer_text', '<script>alert(1)</script><a href="javascript:alert(1)" onclick="alert(1)">Terms</a> and <strong>care</strong>' );
		set_theme_mod( 'nh_social_urls', "javascript:alert(1)\nhttps://www.facebook.com/example" );
		set_theme_mod( 'nh_accent', 'red;}</style><script>alert(1)</script>' );

		$html = $this->render_url( home_url( '/' ) );
		$this->assert_inert( $html, 'home' );

		$xp = $this->xpath( $html );
		$this->assertSame( 1, $xp->query( '//h1' )->length );
		$this->assertStringContainsString( 'Headline', $this->node_text( $xp, '//h1' ) );
		$this->assertSame( 0, $xp->query( $this->by_class( 'cta' ) )->length, 'a call to action with an unusable link is not printed' );
		$this->assertSame( 1, $xp->query( '//a[@href="https://www.facebook.com/example"]' )->length );
		$this->assertStringContainsString( 'care', $this->node_text( $xp, $this->by_class( 'footer-note', 'p' ) ) );
		$this->assertStringNotContainsString( '</style><script>', $html );
	}

	public function test_menu_items_and_the_search_phrase_cannot_break_out() {
		$this->build_site();
		$menu = wp_create_nav_menu( 'Hostile menu' );
		wp_update_nav_menu_item(
			$menu,
			0,
			array(
				'menu-item-title'  => '<script>alert(1)</script>Evil',
				'menu-item-url'    => 'javascript:alert(1)',
				'menu-item-type'   => 'custom',
				'menu-item-status' => 'publish',
			)
		);
		set_theme_mod( 'nav_menu_locations', array( 'primary' => $menu ) );

		$html = $this->render_url( home_url( '/' ) );
		$this->assert_inert( $html, 'menu' );

		$phrase = '" autofocus onfocus="alert(1)" x="';
		$html   = $this->render_url( home_url( '/?s=' . rawurlencode( $phrase ) . '&cat=<script>alert(1)</script>' ) );
		$this->assert_inert( $html, 'search' );
		$xp = $this->xpath( $html );
		foreach ( $xp->query( '//input[@name="s"]' ) as $input ) {
			$this->assertSame( $phrase, $input->getAttribute( 'value' ), 'the phrase stays inside the value attribute' );
			$this->assertFalse( $input->hasAttribute( 'onfocus' ) );
			$this->assertFalse( $input->hasAttribute( 'autofocus' ) );
		}
		$this->assertSame( 1, $xp->query( '//h1' )->length );
	}

	public function test_search_suggestions_are_filled_by_script_never_by_server_markup() {
		$site = $this->build_site();
		$html = $this->render_url( home_url( '/' ) );
		$xp   = $this->xpath( $html );

		$forms = $xp->query( '//form[@data-suggest-url]' );
		$this->assertGreaterThan( 0, $forms->length );
		foreach ( $forms as $form ) {
			$this->assertSame( rest_url( 'wp/v2/search' ), $form->getAttribute( 'data-suggest-url' ) );
			$this->assertSame( 0, $xp->query( './/div[contains(@class,"search-suggest")][@hidden]//li', $form )->length, 'no pre-rendered suggestions' );
			$this->assertSame( 1, $xp->query( './/p[@role="status"][@aria-live="polite"]', $form )->length, 'results are announced by a live region outside the hidden box' );
		}
		$this->assertStringNotContainsString( get_permalink( $site['hidden']['private'] ), $html );

		set_theme_mod( 'nh_search_suggest', false );
		$html = $this->render_url( home_url( '/' ) );
		$this->assertSame( 0, $this->count_nodes( $html, '//form[@data-suggest-url]' ), 'suggestions can be switched off' );
	}
}
