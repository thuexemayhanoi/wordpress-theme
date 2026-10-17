<?php
/**
 * Whole-page tests for images, the asset pipeline and the performance budget.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

/**
 * Images: one LCP candidate per page, everything else lazy, space reserved, responsive.
 */
class NH_Test_Page_Images extends NH_Render_Test_Case {

	/**
	 * Build the site with a featured image on the first posts.
	 *
	 * @return array<string, mixed>
	 */
	private function site_with_images() {
		$site = $this->build_site();
		$this->attach_featured_image( array_merge( array( $site['rich'], $site['plain'] ), $site['posts'] ) );
		wp_set_current_user( 0 );
		return $site;
	}

	/**
	 * Post thumbnails of a rendered page (images inside article cards and the article figure).
	 *
	 * @param string $html HTML.
	 * @return DOMNodeList
	 */
	private function thumbnails( $html ) {
		return $this->xpath( $html )->query( '//main//article[contains(@class,"card")]//img|//main//figure[contains(@class,"article-media")]//img' );
	}

	/**
	 * Assert that exactly one image is eager and high priority and the rest are lazy.
	 *
	 * @param string $html  HTML.
	 * @param string $label Failure label.
	 * @return void
	 */
	private function assert_single_lcp_candidate( $html, $label ) {
		$images = $this->thumbnails( $html );
		$this->assertGreaterThan( 1, $images->length, $label . ': the fixture should show several images' );

		$eager = 0;
		$high  = 0;
		foreach ( $images as $position => $img ) {
			$loading  = $img->getAttribute( 'loading' );
			$priority = $img->getAttribute( 'fetchpriority' );
			if ( 'high' === $priority ) {
				++$high;
			}
			if ( 'eager' === $loading ) {
				++$eager;
				$this->assertSame( 0, $position, $label . ': the eager image must be the first one on the page' );
			} else {
				$this->assertSame( 'lazy', $loading, $label . ': image ' . $position . ' must be lazy' );
				$this->assertSame( '', $priority, $label . ': a lazy image never has a fetch priority' );
			}
		}
		$this->assertSame( 1, $eager, $label . ': exactly one eager image' );
		$this->assertSame( 1, $high, $label . ': exactly one high-priority image' );
	}

	public function test_a_list_page_prioritises_only_its_first_image() {
		$site = $this->site_with_images();

		$this->assert_single_lcp_candidate( $this->render_url( home_url( '/' ) ), 'front page' );
		$this->assert_single_lcp_candidate( $this->render_url( get_category_link( $site['child'] ) ), 'category' );
		$this->assert_single_lcp_candidate( $this->render_url( home_url( '/?s=guide' ) ), 'search' );
		$this->assert_single_lcp_candidate( $this->render_url( get_author_posts_url( $site['author'] ) ), 'author' );
	}

	public function test_an_article_prioritises_its_featured_image_and_lazy_loads_everything_else() {
		$site = $this->site_with_images();
		$html = $this->render_url( get_permalink( $site['rich'] ) );
		$xp   = $this->xpath( $html );

		$featured = $xp->query( '//figure[contains(@class,"article-media")]//img' );
		$this->assertSame( 1, $featured->length );
		$this->assertSame( 'eager', $featured->item( 0 )->getAttribute( 'loading' ) );
		$this->assertSame( 'high', $featured->item( 0 )->getAttribute( 'fetchpriority' ) );
		$this->assertSame( 1, $xp->query( '//img[@fetchpriority="high"]' )->length, 'nothing else competes for the LCP slot' );

		foreach ( $xp->query( '//img[not(ancestor::figure[contains(@class,"article-media")])]' ) as $img ) {
			$this->assertSame( 'lazy', $img->getAttribute( 'loading' ), 'every other image is lazy: ' . $img->getAttribute( 'src' ) );
		}
		$this->assertGreaterThan( 0, $xp->query( '//section[contains(@class,"related")]//img' )->length, 'related cards show their images' );
	}

	public function test_images_reserve_space_and_offer_responsive_sources() {
		$site = $this->site_with_images();

		foreach ( array(
			'front page' => home_url( '/' ),
			'category'   => get_category_link( $site['child'] ),
			'article'    => get_permalink( $site['rich'] ),
		) as $label => $url ) {
			foreach ( $this->thumbnails( $this->render_url( $url ) ) as $img ) {
				$source = $img->getAttribute( 'src' );
				$this->assertGreaterThan( 0, (int) $img->getAttribute( 'width' ), $label . ': width on ' . $source );
				$this->assertGreaterThan( 0, (int) $img->getAttribute( 'height' ), $label . ': height on ' . $source );
				$this->assertNotSame( '', $img->getAttribute( 'sizes' ), $label . ': sizes on ' . $source );
				$this->assertSame( 'async', $img->getAttribute( 'decoding' ), $label . ': async decoding' );

				// Fixed 112px thumbnails are cropped squares: there is no second source to offer.
				if ( false === strpos( $img->getAttribute( 'class' ), 'size-thumbnail' ) ) {
					$this->assertNotSame( '', $img->getAttribute( 'srcset' ), $label . ': srcset on ' . $source );
					$this->assertSame( 1, preg_match( '/\(min-width: \d+px\)/', $img->getAttribute( 'sizes' ) ), $label . ': sizes follows the CSS layout: ' . $img->getAttribute( 'sizes' ) );
				}
				$this->assertSame( 'A test photograph', $img->getAttribute( 'alt' ), $label . ': the media library alt text is used' );
			}
		}
	}

	public function test_card_images_are_decorative_links_that_do_not_double_the_tab_stops() {
		$site = $this->site_with_images();
		$xp   = $this->xpath( $this->render_url( get_category_link( $site['child'] ) ) );

		foreach ( $xp->query( '//article[contains(@class,"card--post")]/a[contains(@class,"card__media")]' ) as $link ) {
			$this->assertSame( 'true', $link->getAttribute( 'aria-hidden' ) );
			$this->assertSame( '-1', $link->getAttribute( 'tabindex' ) );
		}
		foreach ( $xp->query( '//article[contains(@class,"card--post")]' ) as $card ) {
			$this->assertSame( 1, $xp->query( './/a[not(@aria-hidden) and not(contains(@class,"meta__term"))]', $card )->length, 'one reachable title link per card' );
		}
	}

	public function test_posts_without_an_image_get_a_placeholder_and_no_broken_image() {
		$site = $this->build_site();
		$html = $this->render_url( get_category_link( $site['child'] ) );
		$xp   = $this->xpath( $html );
		$this->assertGreaterThan( 0, $xp->query( '//span[contains(@class,"card__placeholder")]' )->length );
		foreach ( $xp->query( '//img' ) as $img ) {
			$this->assertNotSame( '', trim( $img->getAttribute( 'src' ) ), 'no img without a source' );
		}
		$this->assertSame( 0, $xp->query( '//*[@fetchpriority]' )->length, 'no priority hint without a real image' );
	}
}

/**
 * The asset pipeline of every page: inline critical CSS, non-blocking main CSS, one deferred script,
 * no third-party requests, and budgets for the built files.
 */
class NH_Test_Page_Assets extends NH_Render_Test_Case {

	public function test_critical_css_is_inline_and_the_main_stylesheet_does_not_block_rendering() {
		$site = $this->build_site();

		foreach ( array(
			'front page' => home_url( '/' ),
			'article'    => get_permalink( $site['rich'] ),
			'category'   => get_category_link( $site['child'] ),
			'404'        => home_url( '/missing/' ),
		) as $label => $url ) {
			$html = $this->render_url( $url );
			$xp   = $this->xpath( $html );

			$inline = $xp->query( '//head/style[@id="nh-editorial-critical-inline-css"]' );
			$this->assertSame( 1, $inline->length, $label . ': critical CSS is inlined once' );
			$this->assertStringContainsString( '--accent', $inline->item( 0 )->textContent, $label . ': it carries the design tokens' );

			$deferred = $xp->query( '//head/link[@id="nh-editorial-main-css"][@rel="stylesheet"][@media="print"][@onload]' );
			$this->assertSame( 1, $deferred->length, $label . ': the main stylesheet swaps in without blocking' );
			$this->assertSame( 1, $xp->query( '//head/noscript/link[@rel="stylesheet"]' )->length, $label . ': and has a no-JavaScript fallback' );

			foreach ( $xp->query( '//head/link[@rel="stylesheet"][not(@media="print")]' ) as $blocking ) {
				$this->fail( $label . ': render-blocking stylesheet ' . $blocking->getAttribute( 'href' ) );
			}
		}
	}

	public function test_one_deferred_theme_script_and_nothing_heavy() {
		$site = $this->build_site();

		foreach ( array(
			'front page' => home_url( '/' ),
			'article'    => get_permalink( $site['rich'] ),
			'search'     => home_url( '/?s=guide' ),
		) as $label => $url ) {
			$xp      = $this->xpath( $this->render_url( $url ) );
			$scripts = array();
			foreach ( $xp->query( '//script[@src]' ) as $script ) {
				$scripts[] = $script->getAttribute( 'src' );
			}

			$theme_scripts = array_values(
				array_filter(
					$scripts,
					static function ( $src ) {
						return false !== strpos( $src, 'theme.min.js' );
					}
				)
			);
			$this->assertCount( 1, $theme_scripts, $label . ': the theme loads exactly one script' );
			$this->assertSame( 1, $xp->query( '//script[contains(@src,"theme.min.js")][@defer]' )->length, $label . ': and it is deferred' );

			foreach ( $scripts as $src ) {
				$this->assertStringNotContainsString( 'jquery', $src, $label . ': no jQuery on the front end' );
			}
			$this->assertFalse( wp_script_is( 'jquery', 'enqueued' ), $label . ': jQuery is not enqueued' );
		}
	}

	public function test_no_third_party_requests_except_avatars() {
		$site = $this->build_site();
		wp_set_current_user( 0 );
		$host = wp_parse_url( home_url(), PHP_URL_HOST );

		foreach ( array(
			'front page' => home_url( '/' ),
			'article'    => get_permalink( $site['rich'] ),
			'category'   => get_category_link( $site['child'] ),
			'author'     => get_author_posts_url( $site['author'] ),
		) as $label => $url ) {
			$xp      = $this->xpath( $this->render_url( $url ) );
			$queries = array(
				'//script[@src]/@src',
				'//link[@rel="stylesheet" or @rel="preload" or @rel="preconnect" or @rel="dns-prefetch" or @rel="modulepreload"]/@href',
				'//iframe/@src',
				'//source/@src|//source/@srcset|//video/@src|//audio/@src',
			);
			foreach ( $queries as $query ) {
				foreach ( $xp->query( $query ) as $attribute ) {
					$request_host = wp_parse_url( trim( strtok( $attribute->value, ' ' ) ), PHP_URL_HOST );
					$this->assertTrue( null === $request_host || $host === $request_host, $label . ': third-party request to ' . $attribute->value );
				}
			}
			foreach ( $xp->query( '//img/@src' ) as $attribute ) {
				$request_host = wp_parse_url( $attribute->value, PHP_URL_HOST );
				$this->assertTrue(
					null === $request_host || $host === $request_host || 'gravatar.com' === substr( (string) $request_host, -12 ),
					$label . ': image from an unexpected host ' . $attribute->value
				);
			}
		}
	}

	public function test_built_files_contain_no_remote_urls_fonts_or_imports() {
		$css = glob( NH_EDITORIAL_DIR . 'assets/css/*.css' );
		$js  = glob( NH_EDITORIAL_DIR . 'assets/js/*.js' );
		$this->assertNotEmpty( $css );
		$this->assertNotEmpty( $js );

		foreach ( array_merge( $css, $js ) as $file ) {
			$code = (string) file_get_contents( $file );
			$code = str_replace( array( 'http://www.w3.org/2000/svg', 'http://www.w3.org/1999/xlink', 'http://www.w3.org/1998/Math/MathML' ), '', $code );
			$this->assertSame( 0, preg_match( '#https?://#i', $code ), basename( $file ) . ' makes no absolute URL request' );
		}
		foreach ( $css as $file ) {
			$code = (string) file_get_contents( $file );
			$this->assertStringNotContainsString( '@import', $code, basename( $file ) . ' has no @import chain' );
			$this->assertStringNotContainsString( '@font-face', $code, basename( $file ) . ' uses the system font stack' );
		}
	}

	/**
	 * Budgets for the built files, in bytes: raw and gzip.
	 *
	 * @return array<string, array{raw: int, gzip: int}>
	 */
	private function budgets() {
		return array(
			'assets/css/critical.min.css' => array(
				'raw'  => 30 * 1024,
				'gzip' => 7 * 1024,
			),
			'assets/css/main.min.css'     => array(
				'raw'  => 70 * 1024,
				'gzip' => 14 * 1024,
			),
			'assets/js/theme.min.js'      => array(
				'raw'  => 12 * 1024,
				'gzip' => 4 * 1024,
			),
		);
	}

	public function test_the_built_files_stay_within_the_performance_budget() {
		foreach ( $this->budgets() as $relative => $limits ) {
			$path = NH_EDITORIAL_DIR . $relative;
			$this->assertFileExists( $path );
			$code = (string) file_get_contents( $path );
			$this->assertLessThanOrEqual( $limits['raw'], strlen( $code ), $relative . ' raw size budget' );
			$this->assertLessThanOrEqual( $limits['gzip'], strlen( (string) gzencode( $code, 9 ) ), $relative . ' gzip size budget' );
		}
	}

	public function test_dark_mode_script_runs_before_paint_and_can_be_switched_off() {
		$this->build_site();

		$xp = $this->xpath( $this->render_url( home_url( '/' ) ) );
		$this->assertSame( 1, $xp->query( '//head/script[@id="nh-editorial-mode"]' )->length );
		$this->assertStringContainsString( 'prefers-color-scheme', $xp->query( '//head/script[@id="nh-editorial-mode"]' )->item( 0 )->textContent );
		$this->assertSame( 1, $xp->query( '//button[contains(@class,"mode-toggle")][@aria-pressed]' )->length );
		$this->assertSame( 0, $xp->query( '//html[@data-theme]' )->length, 'the saved or preferred mode is applied by the script, not by the server' );

		set_theme_mod( 'nh_dark_mode', false );
		$html = $this->render_url( home_url( '/' ) );
		$xp   = $this->xpath( $html );
		$this->assertSame( 0, $xp->query( '//script[@id="nh-editorial-mode"]' )->length );
		$this->assertSame( 0, $xp->query( '//button[contains(@class,"mode-toggle")]' )->length );
		$this->assertSame( 1, $xp->query( '//html[@data-theme="light"]' )->length, 'light only when dark mode is disabled' );
	}

	public function test_a_custom_accent_reaches_the_inline_css_in_both_color_modes() {
		$this->build_site();
		set_theme_mod( 'nh_accent', '#aa3300' );
		$xp  = $this->xpath( $this->render_url( home_url( '/' ) ) );
		$css = $xp->query( '//head/style[@id="nh-editorial-critical-inline-css"]' )->item( 0 )->textContent;
		$this->assertStringContainsString( ':root[data-theme="dark"]{--accent:', $css );
		$this->assertStringContainsString( '@media(prefers-color-scheme:dark){:root:not([data-theme]){--accent:', $css );
	}

	public function test_browser_ui_colors_and_viewport_are_declared() {
		$this->build_site();
		$xp = $this->xpath( $this->render_url( home_url( '/' ) ) );
		$this->assertSame( 2, $xp->query( '//head/meta[@name="theme-color"]' )->length );
		$this->assertSame( 'width=device-width, initial-scale=1', $xp->query( '//head/meta[@name="viewport"]/@content' )->item( 0 )->value, 'zooming is never disabled' );
	}
}
