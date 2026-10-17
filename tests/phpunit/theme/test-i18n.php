<?php
/**
 * Translation catalogs: the template is complete, the Vietnamese catalog covers it, and the compiled
 * file loads under the locale WordPress asks for.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

/**
 * Catalog consistency and runtime loading.
 */
class NH_Test_I18n extends NH_Render_Test_Case {

	/**
	 * Load a catalog with the PO parser of WordPress.
	 *
	 * @param string $file Path.
	 * @return PO
	 */
	private function catalog( $file ) {
		require_once ABSPATH . WPINC . '/pomo/po.php';
		$po = new PO();
		$this->assertTrue( $po->import_from_file( $file ), $file . ' parses' );
		return $po;
	}

	/**
	 * Placeholders such as %s and %1$d in a string, sorted.
	 *
	 * @param string $text String.
	 * @return string[]
	 */
	private function placeholders( $text ) {
		preg_match_all( '/%(?:\d+\$)?[sd]/', $text, $matches );
		sort( $matches[0] );
		return $matches[0];
	}

	public function test_the_vietnamese_catalog_translates_every_string_of_the_template_with_matching_placeholders() {
		$pot = $this->catalog( NH_EDITORIAL_DIR . 'languages/nguyen-ha-editorial.pot' );
		$po  = $this->catalog( NH_EDITORIAL_DIR . 'languages/vi.po' );

		$this->assertGreaterThan( 100, count( $pot->entries ), 'the template lists the strings of the theme' );

		$missing = array();
		$wrong   = array();
		foreach ( $pot->entries as $key => $entry ) {
			if ( '' === $entry->singular ) {
				continue;
			}
			if ( ! isset( $po->entries[ $key ] ) || '' === trim( implode( '', $po->entries[ $key ]->translations ) ) ) {
				$missing[] = $entry->singular;
				continue;
			}
			if ( $this->placeholders( $entry->singular ) !== $this->placeholders( $po->entries[ $key ]->translations[0] ) ) {
				$wrong[] = $entry->singular;
			}
		}
		$this->assertSame( array(), $missing, 'strings without a Vietnamese translation' );
		$this->assertSame( array(), $wrong, 'translations whose placeholders differ from the source' );

		$extra = array_diff( array_keys( $po->entries ), array_keys( $pot->entries ) );
		$this->assertSame( array(), array_values( $extra ), 'the catalog holds strings the template no longer has' );
	}

	public function test_the_catalog_headers_describe_vietnamese_without_plural_forms() {
		$po = $this->catalog( NH_EDITORIAL_DIR . 'languages/vi.po' );
		$this->assertSame( 'vi', $po->headers['Language'] );
		$this->assertSame( 'nplurals=1; plural=0;', $po->headers['Plural-Forms'] );
		$this->assertSame( 'text/plain; charset=UTF-8', $po->headers['Content-Type'] );
	}

	public function test_the_compiled_catalog_loads_for_the_vi_locale_and_translates_the_interface() {
		$this->assertFileExists( NH_EDITORIAL_DIR . 'languages/vi.mo' );

		add_filter(
			'locale',
			static function () {
				return 'vi';
			}
		);
		unload_textdomain( 'nguyen-ha-editorial' );
		$this->assertTrue( load_theme_textdomain( 'nguyen-ha-editorial', NH_EDITORIAL_DIR . 'languages' ), 'WordPress finds languages/vi.mo for the vi locale' );

		$this->assertSame( 'Chuyển đến nội dung', __( 'Skip to content', 'nguyen-ha-editorial' ) );
		$this->assertSame( 'Trang chủ', __( 'Home', 'nguyen-ha-editorial' ) );
		/* translators: %s: number of articles. */
		$this->assertSame( '5 bài viết', sprintf( _n( '%s article', '%s articles', 5, 'nguyen-ha-editorial' ), '5' ) );
		/* translators: %s: number of articles. */
		$this->assertSame( '1 bài viết', sprintf( _n( '%s article', '%s articles', 1, 'nguyen-ha-editorial' ), '1' ) );

		$site = $this->build_site();
		$html = $this->render_url( get_permalink( $site['rich'] ) );
		$this->assertStringContainsString( 'Chuyển đến nội dung', $html );
		$this->assertStringContainsString( 'Bài viết liên quan', $html );
		$this->assertStringContainsString( 'Câu hỏi thường gặp', $html );
		$this->assertStringContainsString( 'Mục lục', $html );
		$this->assertStringNotContainsString( 'Skip to content', $html );
		$this->assertStringNotContainsString( 'Related articles', $html );

		unload_textdomain( 'nguyen-ha-editorial' );
	}

	public function test_every_plural_function_uses_the_theme_text_domain() {
		$checked = 0;
		foreach ( glob( NH_EDITORIAL_DIR . '{*.php,inc/*.php,template-parts/*/*.php,page-templates/*.php}', GLOB_BRACE ) as $file ) {
			$code = (string) file_get_contents( $file );
			if ( preg_match_all( '/\b(?:_n|_nx|_x|__|_e|_ex|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\s*\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\)/s', $code, $calls ) ) {
				foreach ( $calls[0] as $call ) {
					++$checked;
					$this->assertStringContainsString( "'nguyen-ha-editorial'", $call, basename( $file ) . ': ' . substr( $call, 0, 80 ) );
				}
			}
		}
		$this->assertGreaterThan( 150, $checked );
	}
}
