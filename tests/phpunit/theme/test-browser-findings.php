<?php
/**
 * Regression tests, on the server side, for defects the browser suite (tests/e2e) found: an empty search, the
 * accessible name of the brand link and of the search forms, a comments region that names a missing heading, the
 * singular suggestion label and the no-JavaScript style. What only a browser can judge (contrast, layout, print) is
 * covered by tests/e2e, which runs in CI.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

/**
 * Findings of the browser suite.
 */
class NH_Test_Browser_Findings extends NH_Render_Test_Case {

	public function set_up() {
		parent::set_up();
		$this->use_permalinks( '/%postname%/' );
		$this->build_site( 14 );
	}

	/**
	 * Texts of the headings of a page, by level.
	 *
	 * @param string $html Page HTML.
	 * @param string $tag  h1, h2...
	 * @return string[]
	 */
	private function heading_texts( $html, $tag ) {
		$texts = array();
		foreach ( $this->xpath( $html )->query( '//' . $tag ) as $node ) {
			$texts[] = trim( preg_replace( '/\s+/', ' ', $node->textContent ) );
		}
		return $texts;
	}

	public function test_a_search_without_a_word_asks_for_one_instead_of_listing_every_article() {
		foreach ( array( '/?s=', '/?s=%20%20', '/?s=+' ) as $path ) {
			$html = $this->render_url( home_url( $path ) );

			$this->assertSame( array( 'What are you looking for?' ), $this->heading_texts( $html, 'h1' ), $path );
			$this->assertSame( 0, $this->count_nodes( $html, '//article' ), $path . ': no article is listed' );
			$this->assertSame( 0, $this->count_nodes( $html, $this->by_class( 'pagination' ) ), $path );
			$this->assertGreaterThan( 0, $this->count_nodes( $html, '//main//form[@role="search"]' ), $path . ': the form to type in is there' );
			$this->assertGreaterThan( 0, $this->count_nodes( $html, '//main//ul[contains(@class,"chips")]//a' ), $path . ': the topics are offered' );
			$this->assertSame( array(), $this->heading_skips( $html ), $path );
			// The document title belongs to WordPress (or the SEO plugin); the page itself says nothing about results.
			$this->assertStringNotContainsString( 'Results for', (string) $this->xpath( $html )->evaluate( 'string(//main)' ), $path );

			$this->assertSame( 0, (int) $GLOBALS['wp_query']->found_posts, $path . ': nothing was counted' );
			$this->assertStringContainsString( 'IN (0)', $GLOBALS['wp_query']->request, $path . ': the query is the trivial one' );
		}
	}

	public function test_a_search_with_a_word_is_unchanged() {
		$html = $this->render_url( home_url( '/?s=guide' ) );

		$this->assertSame( array( 'Results for “guide”' ), $this->heading_texts( $html, 'h1' ) );
		$this->assertGreaterThan( 0, $this->count_nodes( $html, '//article' ) );
		$this->assertStringNotContainsString( 'IN (0)', $GLOBALS['wp_query']->request );
	}

	public function test_the_brand_link_takes_its_name_from_its_visible_text() {
		$html = $this->render_url( home_url( '/' ) );
		foreach ( $this->xpath( $html )->query( '//a[contains(concat(" ", normalize-space(@class), " "), " brand ")]' ) as $link ) {
			$this->assertFalse( $link->hasAttribute( 'aria-label' ), 'an aria-label that differs from the visible text breaks WCAG 2.5.3 (label in name)' );
			$this->assertStringContainsString( get_bloginfo( 'name' ), $link->textContent );
		}

		// With a logo the image carries the name.
		$attachment = $this->attach_featured_image( array() );
		set_theme_mod( 'custom_logo', $attachment );
		$html = $this->render_url( home_url( '/' ) );
		$this->assertGreaterThan( 0, $this->count_nodes( $html, '//a[contains(@class,"brand")]//img[@alt="' . esc_attr( get_bloginfo( 'name' ) ) . '"]' ) );
		$this->assertSame( 0, $this->count_nodes( $html, '//a[contains(@class,"brand")][@aria-label]' ) );
	}

	public function test_the_search_forms_of_one_page_have_different_names() {
		$pages = array(
			'home'         => home_url( '/' ),
			'a 404'        => home_url( '/no-such-page/' ),
			'a category'   => get_category_link( $this->site['child'] ),
			'search'       => home_url( '/?s=guide' ),
			'empty search' => home_url( '/?s=' ),
			'no result'    => home_url( '/?s=zzzzqqqq' ),
			'an article'   => get_permalink( $this->site['rich'] ),
		);
		foreach ( $pages as $label => $url ) {
			$html  = $this->render_url( $url );
			$names = array();
			foreach ( $this->xpath( $html )->query( '//form[@role="search"]/@aria-label' ) as $attribute ) {
				$names[] = $attribute->value;
			}
			$this->assertNotEmpty( $names, $label );
			$this->assertSame( $names, array_values( array_unique( $names ) ), $label . ': two landmarks with the same name cannot be told apart' );
		}
	}

	public function test_the_comments_region_names_a_heading_only_when_it_has_one() {
		$with = $this->render_url( get_permalink( $this->site['rich'] ) );
		$this->assertSame( 1, $this->count_nodes( $with, '//section[@id="comments"][@aria-labelledby="comments-title"]' ) );
		$this->assertSame( 1, $this->count_nodes( $with, '//*[@id="comments-title"]' ) );

		$without = $this->render_url( get_permalink( $this->site['plain'] ) );
		$this->assertTrue( comments_open( $this->site['plain'] ) );
		$this->assertSame( 1, $this->count_nodes( $without, '//section[@id="comments"]' ), 'the comment form is still offered' );
		$this->assertSame( 0, $this->count_nodes( $without, '//section[@id="comments"][@aria-labelledby]' ), 'a reference to a heading that is not there is an error' );
		$this->assertSame( 0, $this->count_nodes( $without, '//*[@id="comments-title"]' ) );
	}

	public function test_the_suggestions_announce_one_result_in_the_singular() {
		$attributes = nh_editorial_search_suggest_attributes();
		$this->assertStringContainsString( 'data-label-one="1 suggestion available"', $attributes );
		$this->assertStringContainsString( 'data-label-count="%d suggestions available"', $attributes );
		$this->assertStringContainsString( 'data-label-none="No suggestions"', $attributes );

		$script = (string) file_get_contents( get_theme_file_path( 'assets/js/theme.min.js' ) );
		$this->assertStringContainsString( 'data-label-one', $script, 'the built script reads the singular label' );
	}

	public function test_without_javascript_the_collapsed_menus_and_the_search_panel_stay_reachable() {
		$html = $this->render_url( home_url( '/' ) );
		preg_match( '#<noscript><style>(.*?)</style></noscript>#s', $html, $match );
		$this->assertNotEmpty( $match, 'the no-JavaScript style is printed in the head' );
		$css = $match[1];

		// The critical CSS says [hidden]{display:none!important}: the rules that undo it must be as strong.
		$this->assertStringContainsString( '.sub-menu[hidden],.header-search[hidden]{display:block!important}', $css );
		$this->assertStringContainsString( '.search-toggle,.submenu-toggle,.nav-toggle,.mode-toggle{display:none!important}', $css );
		$this->assertStringContainsString( '.has-sticky-header .site-header{position:static!important}', $css, 'the whole menu is in the header, which must not stay glued to the screen' );
		$this->assertStringContainsString( '.mega-panel{position:static!important;display:block!important', $css );
	}

	public function test_a_password_form_has_the_theme_field_style_and_footer_widgets_a_gap() {
		$css = (string) file_get_contents( get_theme_file_path( 'assets/css/main.min.css' ) ) . (string) file_get_contents( get_theme_file_path( 'assets/css/critical.min.css' ) );
		$this->assertStringContainsString( 'input[type=password]', str_replace( '"', '', $css ), 'the password field is styled like the other fields' );
		$this->assertStringContainsString( '.footer-widget+.footer-widget', $css );
		$this->assertStringNotContainsString( '.prose h2,.prose h3,.prose h4{scroll-margin-top', str_replace( "\n", '', $css ), 'the html element already reserves the header: a second margin on the headings doubles the gap' );
	}
}
