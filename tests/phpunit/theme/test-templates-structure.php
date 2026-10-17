<?php
/**
 * Whole-page rendering tests: template hierarchy, one H1, heading levels, landmarks, SEO ownership,
 * hidden content, pagination and the article layout.
 *
 * @package nguyen-ha-editorial
 */

/**
 * Every public context renders a valid, accessible document skeleton.
 */
class NH_Test_Template_Structure extends NH_Render_Test_Case {

	/**
	 * URL of every public context of the fixture site, keyed by a label used in failure messages.
	 *
	 * @return array<string, string>
	 */
	private function contexts() {
		$s = $this->build_site();
		$this->attach_featured_image( array_merge( array( $s['rich'], $s['plain'] ), array_slice( $s['posts'], 0, 6 ) ) );
		wp_set_current_user( 0 );

		return array(
			'front page'       => home_url( '/' ),
			'front page 2'     => home_url( '/page/2/' ),
			'article'          => get_permalink( $s['rich'] ),
			'plain article'    => get_permalink( $s['plain'] ),
			'page'             => get_permalink( $s['page'] ),
			'pillar page'      => get_permalink( $s['pillar'] ),
			'full width page'  => get_permalink( $s['wide'] ),
			'hub category'     => get_category_link( $s['top'] ),
			'child category'   => get_category_link( $s['child'] ),
			'child category 2' => trailingslashit( get_category_link( $s['child'] ) ) . 'page/2/',
			'empty category'   => get_category_link( $s['empty'] ),
			'tag'              => get_tag_link( $s['tag'] ),
			'author'           => get_author_posts_url( $s['author'] ),
			'search'           => home_url( '/?s=guide' ),
			'search no result' => home_url( '/?s=zzzzqqqq' ),
			'date archive'     => get_year_link( (int) gmdate( 'Y' ) ),
			'404'              => home_url( '/no-such-page-here/' ),
		);
	}

	public function test_each_context_resolves_to_the_expected_template() {
		$expected = array(
			'front page'      => 'front-page.php',
			'article'         => 'single.php',
			'page'            => 'page.php',
			'pillar page'     => 'template-pillar.php',
			'full width page' => 'template-fullwidth.php',
			'hub category'    => 'category.php',
			'child category'  => 'category.php',
			'tag'             => 'archive.php',
			'author'          => 'author.php',
			'search'          => 'search.php',
			'date archive'    => 'archive.php',
			'404'             => '404.php',
		);

		foreach ( $this->contexts() as $label => $url ) {
			if ( ! isset( $expected[ $label ] ) ) {
				continue;
			}
			$this->go_to( $url );
			$this->assertSame( $expected[ $label ], basename( $this->current_template() ), $label );
		}
	}

	public function test_every_context_has_one_h1_and_a_valid_document_skeleton() {
		$problems = array();

		foreach ( $this->contexts() as $label => $url ) {
			$html  = $this->render_url( $url );
			$xp    = $this->xpath( $html );
			$check = static function ( $ok, $message ) use ( &$problems, $label ) {
				if ( ! $ok ) {
					$problems[] = $label . ': ' . $message;
				}
			};

			$h1s = $xp->query( '//h1' );
			$check( 1 === $h1s->length, 'expected exactly one h1, found ' . $h1s->length );
			$check( $h1s->length && '' !== trim( $h1s->item( 0 )->textContent ), 'the h1 has no text' );
			foreach ( $this->heading_skips( $html ) as $skip ) {
				$check( false, 'heading level skipped (' . $skip . ')' );
			}

			$check( 1 === $xp->query( '//main[@id="primary"]' )->length, 'expected one main landmark' );
			$check( 1 === $xp->query( '//header[@id="masthead"]' )->length, 'expected one site header' );
			$check( 1 === $xp->query( '//footer[@id="colophon"]' )->length, 'expected one site footer' );
			$check( 0 === $xp->query( '//main//header[@id="masthead"]|//main//footer[@id="colophon"]' )->length, 'site landmarks are nested in main' );

			$first_link = $xp->query( '(//a)[1]' )->item( 0 );
			$check( $first_link && '#primary' === $first_link->getAttribute( 'href' ), 'the skip link is not the first link' );

			$check( 1 === $xp->query( '//html[@lang]' )->length, 'missing lang attribute' );
			$check( 1 === $xp->query( '//head/meta[@name="viewport"]' )->length, 'missing viewport' );
			$check( 1 === $xp->query( '//head/title' )->length, 'expected one title element' );
			foreach ( $this->duplicate_ids( $html ) as $id ) {
				$check( false, 'duplicate id "' . $id . '"' );
			}

			foreach ( $xp->query( '//img' ) as $img ) {
				$check( $img->hasAttribute( 'alt' ), 'image without alt attribute: ' . $img->getAttribute( 'src' ) );
			}

			foreach ( $xp->query( '//button' ) as $button ) {
				$check( '' !== trim( $button->textContent . $button->getAttribute( 'aria-label' ) ), 'button without an accessible name' );
				$in_form = $xp->query( 'ancestor::form', $button )->length > 0;
				$check( 'button' === $button->getAttribute( 'type' ) || ( $in_form && 'submit' === $button->getAttribute( 'type' ) ), 'button without an explicit type' );
			}

			foreach ( $xp->query( '//a[@href][not(ancestor-or-self::*[@aria-hidden="true"])]' ) as $link ) {
				$has_name = '' !== trim( $link->textContent ) || '' !== $link->getAttribute( 'aria-label' ) || $xp->query( './/img[@alt!=""]', $link )->length > 0;
				$check( $has_name, 'link without an accessible name: ' . $link->getAttribute( 'href' ) );
				$check( '#' !== $link->getAttribute( 'href' ), 'placeholder link' );
			}

			foreach ( $xp->query( '//*[@aria-controls]' ) as $control ) {
				$check( 1 === $xp->query( '//*[@id="' . $control->getAttribute( 'aria-controls' ) . '"]' )->length, 'aria-controls points nowhere: ' . $control->getAttribute( 'aria-controls' ) );
			}

			$navs = array();
			foreach ( $xp->query( '//nav' ) as $nav ) {
				$check( '' !== $nav->getAttribute( 'aria-label' ), 'navigation landmark without a label' );
				$navs[] = $nav->getAttribute( 'aria-label' );
			}
			$check( array_values( array_unique( $navs ) ) === $navs, 'navigation labels are not unique: ' . implode( ' | ', $navs ) );

			// The only inline handler allowed is the non-blocking stylesheet swap.
			$check( array() === array_values( array_diff( $this->inline_handlers( $html ), array( 'link:onload' ) ) ), 'inline event handlers: ' . implode( ', ', $this->inline_handlers( $html ) ) );
		}

		$this->assertSame( array(), $problems );
	}

	public function test_the_theme_prints_no_seo_owned_markup_on_any_page() {
		foreach ( $this->contexts() as $label => $url ) {
			$html = $this->render_url( $url );
			$xp   = $this->xpath( $html );

			$this->assertSame( 0, $xp->query( '//script[@type="application/ld+json"]' )->length, $label . ': no JSON-LD' );
			$this->assertSame( 0, $xp->query( '//meta[@name="description"]' )->length, $label . ': no meta description' );
			$this->assertSame( 0, $xp->query( '//meta[starts-with(@property, "og:")]|//meta[starts-with(@name, "twitter:")]' )->length, $label . ': no Open Graph or Twitter cards' );
			$this->assertSame( 0, $xp->query( '//link[@hreflang]' )->length, $label . ': no hreflang' );
			$this->assertSame( 0, $xp->query( '//*[@itemscope or @itemprop or @typeof or @property]' )->length, $label . ': no microdata or RDFa that would duplicate the schema' );
			$this->assertLessThanOrEqual( 1, $xp->query( '//link[@rel="canonical"]' )->length, $label . ': at most the one canonical WordPress core prints' );
			$this->assertLessThanOrEqual( 1, $xp->query( '//meta[@name="robots"]' )->length, $label . ': at most one robots meta' );
		}
	}

	public function test_hidden_content_is_never_linked_or_named_on_any_page() {
		$contexts = $this->contexts();
		$hidden   = $this->site['hidden'];

		$urls   = array();
		$titles = array( 'Hidden draft guide', 'Hidden private guide', 'Hidden pending guide', 'Hidden future guide', 'Hidden trash guide', 'Hidden summary' );
		foreach ( $hidden as $post_id ) {
			$urls[] = get_permalink( $post_id );
			$urls[] = '?p=' . $post_id;
		}

		foreach ( $contexts as $label => $url ) {
			$html = $this->render_url( $url );
			foreach ( $titles as $title ) {
				$this->assertStringNotContainsString( $title, $html, $label . ': "' . $title . '" leaked' );
			}
			foreach ( $this->hrefs( $html ) as $href ) {
				foreach ( $urls as $hidden_url ) {
					$this->assertStringNotContainsString( $hidden_url, $href, $label . ': link to hidden content' );
				}
			}
		}
	}

	public function test_a_static_front_page_and_a_posts_page_both_work() {
		$this->build_site();
		$home = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'Welcome home',
				'post_content' => '<p>Static front page text.</p>',
			)
		);
		$blog = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'All articles',
			)
		);
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $home );
		update_option( 'page_for_posts', $blog );
		nh_editorial_cache_bump_now();

		$html = $this->render_url( home_url( '/' ) );
		$this->assertSame( 'front-page.php', basename( $this->current_template() ) );
		$this->assertSame( 1, $this->count_nodes( $html, '//h1' ) );
		$this->assertStringContainsString( 'Static front page text.', $html, 'the page content is shown' );
		$this->assertSame( array(), $this->heading_skips( $html ) );
		$this->assertSame( array(), $this->duplicate_ids( $html ) );
		$this->assertStringContainsString( get_permalink( $blog ), $html, 'the front page links to the posts page' );

		$html = $this->render_url( get_permalink( $blog ) );
		$this->assertSame( 'home.php', basename( $this->current_template() ) );
		$this->assertSame( 1, $this->count_nodes( $html, '//h1' ) );
		$this->assertSame( 'All articles', trim( $this->xpath( $html )->query( '//h1' )->item( 0 )->textContent ) );
		$this->assertSame( array(), $this->heading_skips( $html ) );
		$this->assertGreaterThan( 5, $this->count_nodes( $html, $this->by_class( 'card--post', 'article' ) ) );

		$html = $this->render_url( trailingslashit( get_permalink( $blog ) ) . 'page/2/' );
		$this->assertSame( 1, $this->count_nodes( $html, '//h1' ) );
	}

	public function test_the_article_has_every_element_in_the_documented_order() {
		$s    = $this->build_site();
		$html = $this->render_url( get_permalink( $s['rich'] ) );
		$xp   = $this->xpath( $html );

		$order = array(
			'breadcrumb'  => $this->by_class( 'breadcrumbs', 'nav' ),
			'h1'          => '//h1[contains(@class,"article-title")]',
			'summary'     => $this->by_class( 'summary', 'section' ),
			'takeaways'   => $this->by_class( 'takeaways', 'section' ),
			'toc'         => $this->by_class( 'toc', 'nav' ),
			'content'     => $this->by_class( 'entry-content', 'div' ),
			'faq'         => '//section[@id="faq"]',
			'sources'     => '//section[@id="sources"]',
			'tags'        => $this->by_class( 'chips--tags', 'ul' ),
			'topic box'   => $this->by_class( 'pillar-box', 'aside' ),
			'author box'  => $this->by_class( 'author-box', 'section' ),
			'prev / next' => $this->by_class( 'post-nav', 'nav' ),
			'related'     => $this->by_class( 'related', 'section' ),
			'comments'    => '//section[@id="comments"]',
		);

		$previous_label = '';
		$previous       = '';
		foreach ( $order as $label => $query ) {
			$this->assertSame( 1, $xp->query( $query )->length, $label . ' is present exactly once' );
			if ( '' !== $previous ) {
				$after = $xp->query( $previous . '/following::' . $this->axis_step( $query ) );
				$this->assertGreaterThan( 0, $after->length, $label . ' comes after ' . $previous_label );
			}
			$previous_label = $label;
			$previous       = $query;
		}

		$this->assertSame( 2, $xp->query( '//section[@id="faq"]//details' )->length );
		$this->assertTrue( $xp->query( '//section[@id="faq"]//details' )->item( 0 )->hasAttribute( 'open' ), 'the first answer is open' );
		$this->assertSame( 3, $xp->query( $this->by_class( 'related', 'section' ) . '//article' )->length, 'three related articles' );
		$this->assertSame( 1, $xp->query( '//form[@id="commentform"]' )->length );
		$this->assertSame( 2, $xp->query( '//ol[contains(@class,"comment-list")]/li' )->length );
		$this->assertStringContainsString( 'Updated', $this->text( $html, $this->by_class( 'meta', 'div' ) ) );
		$this->assertStringContainsString( 'The short answer in one sentence.', $html );
	}

	/**
	 * Convert "//tag[predicate]" into the "tag[predicate]" step used after an axis.
	 *
	 * @param string $query Absolute XPath of one element.
	 * @return string
	 */
	private function axis_step( $query ) {
		return preg_replace( '#^//#', '', $query );
	}

	/**
	 * Text content of the first node matching an XPath query.
	 *
	 * @param string $html  HTML.
	 * @param string $query XPath.
	 * @return string
	 */
	private function text( $html, $query ) {
		$node = $this->xpath( $html )->query( $query )->item( 0 );
		return $node ? trim( (string) preg_replace( '/\s+/u', ' ', $node->textContent ) ) : '';
	}

	public function test_an_article_without_extras_prints_no_empty_sections() {
		$s    = $this->build_site();
		$html = $this->render_url( get_permalink( $s['plain'] ) );

		foreach ( array( 'article-intro', 'toc', 'faq', 'sources', 'chips--tags', 'comment-list' ) as $class_name ) {
			$this->assertSame( 0, $this->count_nodes( $html, $this->by_class( $class_name ) ), $class_name . ' must not be printed without data' );
		}
		$this->assertSame( 1, $this->count_nodes( $html, '//h1' ) );
		$this->assertSame( 0, $this->count_nodes( $html, $this->by_class( 'meta__updated' ) ), 'no fake update date when nothing was updated' );
	}

	public function test_category_pagination_is_crawlable_and_later_pages_do_not_repeat_the_introduction() {
		$s     = $this->build_site();
		$base  = get_category_link( $s['child'] );
		$page1 = $this->render_url( $base );
		$page2 = $this->render_url( trailingslashit( $base ) . 'page/2/' );

		$nav = $this->xpath( $page1 )->query( '//nav[contains(@class,"pagination")]' );
		$this->assertSame( 1, $nav->length );
		$this->assertNotSame( '', $nav->item( 0 )->getAttribute( 'aria-label' ) );
		$this->assertContains( trailingslashit( $base ) . 'page/2/', $this->hrefs( $page1 ), 'page 1 links to page 2 with a real URL' );
		$this->assertNotContains( trailingslashit( $base ) . 'page/1/', $this->hrefs( $page2 ), 'page 2 links back to the clean category URL' );
		$this->assertContains( $base, $this->hrefs( $page2 ) );
		$this->assertSame( 1, $this->count_nodes( $page1, '//nav[contains(@class,"pagination")]//*[@aria-current="page"]' ) );
		$this->assertSame( 1, $this->count_nodes( $page2, '//nav[contains(@class,"pagination")]//*[@aria-current="page"]' ) );

		$cards1 = $this->card_links( $page1 );
		$cards2 = $this->card_links( $page2 );
		$this->assertNotEmpty( $cards1 );
		$this->assertNotEmpty( $cards2 );
		$this->assertSame( array(), array_values( array_intersect( $cards1, $cards2 ) ), 'no article appears on both pages' );

		$this->assertSame( 1, $this->count_nodes( $page1, '//h1' ) );
		$this->assertSame( 1, $this->count_nodes( $page2, '//h1' ) );
		$this->assertSame( 0, $this->count_nodes( $page2, $this->by_class( 'archive-description' ) ), 'no repeated description on page 2' );
		$this->assertSame( 0, $this->count_nodes( $page2, $this->by_class( 'section--subtopics' ) ), 'no repeated subtopics on page 2' );

		$hub1 = $this->render_url( get_category_link( $s['top'] ) );
		$this->assertSame( 1, $this->count_nodes( $hub1, $this->by_class( 'pillar-box' ) ), 'the hub links to its Pillar guide' );
		$this->assertContains( get_permalink( $s['pillar'] ), $this->hrefs( $hub1 ) );
		$this->assertSame( 1, $this->count_nodes( $hub1, $this->by_class( 'section--subtopics' ) ), 'the hub lists its child hubs' );
		$this->assertContains( $base, $this->hrefs( $hub1 ) );
	}

	/**
	 * Permalinks of the article cards of a page (titles links only).
	 *
	 * @param string $html HTML.
	 * @return string[]
	 */
	private function card_links( $html ) {
		$links = array();
		foreach ( $this->xpath( $html )->query( $this->by_class( 'card--post', 'article' ) . '//*[contains(@class,"card__title")]/a' ) as $link ) {
			$links[] = $link->getAttribute( 'href' );
		}
		return $links;
	}

	public function test_the_front_page_never_repeats_an_article_and_page_two_only_lists() {
		$this->build_site();
		$page1 = $this->render_url( home_url( '/' ) );
		$page2 = $this->render_url( home_url( '/page/2/' ) );

		$all = $this->card_links( $page1 );
		$this->assertSame( array_values( array_unique( $all ) ), $all, 'no article is listed twice on the front page' );
		$this->assertSame( array(), $this->duplicate_ids( $page1 ) );

		$this->assertSame( 1, $this->count_nodes( $page1, '//h1[contains(@class,"home-intro__title")]' ) );
		$this->assertSame( 1, $this->count_nodes( $page2, '//h1[contains(@class,"archive-title")]' ), 'page two has the list heading as its h1' );
		$this->assertStringContainsString( 'page 2', $this->text( $page2, '//h1' ) );
		$this->assertSame( 0, $this->count_nodes( $page2, $this->by_class( 'home-intro' ) ), 'the introduction is not repeated' );
		$this->assertSame( 0, $this->count_nodes( $page2, $this->by_class( 'card--hero' ) ), 'no hero on page 2' );
		$this->assertSame( 0, $this->count_nodes( $page2, $this->by_class( 'section--topic' ) ), 'no topic sections on page 2' );
		$this->assertContains( home_url( '/' ), $this->hrefs( $page2 ) );

		$latest1 = array();
		foreach ( $this->xpath( $page1 )->query( '//section[@id="latest"]//article//*[contains(@class,"card__title")]/a' ) as $link ) {
			$latest1[] = $link->getAttribute( 'href' );
		}
		$this->assertNotEmpty( $latest1 );
		$this->assertSame( array(), array_values( array_intersect( $latest1, $this->card_links( $page2 ) ) ), 'page 2 continues the list instead of repeating it' );
	}

	public function test_search_results_page_describes_the_query_and_escapes_it() {
		$this->build_site();
		$html = $this->render_url( home_url( '/?s=guide' ) );
		$xp   = $this->xpath( $html );

		$this->assertSame( 'Results for “guide”', $this->text( $html, '//h1' ) );
		$this->assertSame( 1, $xp->query( '//p[contains(@class,"archive-meta")][@role="status"]' )->length, 'the result count is announced' );
		$this->assertGreaterThan( 0, $xp->query( '//header//form[@role="search"]' )->length );
		$this->assertGreaterThan( 0, $xp->query( '//mark' )->length, 'matches are highlighted' );
		$this->assertSame( array(), $this->duplicate_ids( $html ) );

		$html = $this->render_url( home_url( '/?s=' . rawurlencode( '"><script>alert(1)</script>' ) ) );
		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
		$this->assertSame( 0, $this->count_nodes( $html, '//script[contains(.,"alert(1)")]' ) );
		$this->assertSame( 1, $this->count_nodes( $html, '//h1' ) );

		$html = $this->render_url( home_url( '/?s=zzzzqqqq' ) );
		$this->assertSame( 1, $this->count_nodes( $html, $this->by_class( 'empty-state' ) ), 'no results gives a helpful empty state' );
		$this->assertSame( 0, $this->count_nodes( $html, $this->by_class( 'card--post' ) ) );
	}

	public function test_empty_archives_show_an_empty_state_instead_of_a_blank_page() {
		$s    = $this->build_site();
		$html = $this->render_url( get_category_link( $s['empty'] ) );
		$this->assertSame( 1, $this->count_nodes( $html, '//h1' ) );
		$this->assertSame( 1, $this->count_nodes( $html, $this->by_class( 'empty-state' ) ) );
		$this->assertSame( 0, $this->count_nodes( $html, $this->by_class( 'pagination' ) ) );
	}

	public function test_a_protected_article_shows_the_password_form_and_no_content() {
		$s    = $this->build_site();
		$html = $this->render_url( get_permalink( $s['protected'] ) );
		$this->assertStringNotContainsString( 'Secret body text.', $html );
		$this->assertSame( 1, $this->count_nodes( $html, '//form[contains(@class,"post-password-form")]' ) );
		$this->assertSame( 0, $this->count_nodes( $html, '//section[@id="comments"]' ), 'comments stay hidden behind the password' );
		$this->assertSame( 1, $this->count_nodes( $html, '//h1' ) );

		$listing = $this->render_url( get_category_link( $s['child'] ) );
		$this->assertStringNotContainsString( 'Secret body text.', $listing );
	}

	public function test_the_primary_menu_works_without_javascript() {
		$s    = $this->build_site();
		$html = $this->render_url( home_url( '/' ) );
		$xp   = $this->xpath( $html );

		$parent = $xp->query( '//nav[@id="primary-navigation"]//li[contains(@class,"menu-item-has-children")]/a' );
		$this->assertSame( 1, $parent->length );
		$this->assertSame( get_category_link( $s['top'] ), $parent->item( 0 )->getAttribute( 'href' ), 'the hub is a real link' );
		$this->assertSame( 1, $xp->query( '//nav[@id="primary-navigation"]//ul[contains(@class,"mega-panel")]//a[@href="' . get_category_link( $s['child'] ) . '"]' )->length, 'the child hub is in the markup' );
		$this->assertSame( 1, $xp->query( '//noscript/style[contains(.,".sub-menu[hidden]")]' )->length, 'the no-JS fallback keeps the panels reachable' );

		$form = $xp->query( '//header//form[@role="search"]' );
		$this->assertSame( 1, $form->length );
		$this->assertSame( 'get', strtolower( $form->item( 0 )->getAttribute( 'method' ) ) );
		$this->assertSame( home_url( '/' ), $form->item( 0 )->getAttribute( 'action' ) );
		$this->assertSame( 1, $xp->query( '//header//form[@role="search"]//input[@name="s"]' )->length );
	}
}
