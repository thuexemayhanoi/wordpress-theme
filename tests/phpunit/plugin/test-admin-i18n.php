<?php
/**
 * Tests for the translation files: the POT, the Vietnamese catalog and how translations reach the output.
 *
 * @package nguyen-ha-seo-companion
 */

/**
 * Translation catalogs of the companion plugin.
 *
 * The extraction is scoped to the files of the admin, data and render modules (plus the shared strings that
 * are checked by name), so strings that other modules add later do not break these tests: they only have to
 * be added to the POT when the catalogs are regenerated.
 */
class NH_Test_Admin_I18n extends NH_Unit_Test_Case {

	const DOMAIN = 'nguyen-ha-seo-companion';

	/**
	 * Parsed catalogs, shared by all tests.
	 *
	 * @var array
	 */
	private static $catalogs = array();

	/**
	 * WordPress's own PO and MO readers.
	 *
	 * @return void
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();
		require_once ABSPATH . WPINC . '/pomo/po.php';
		require_once ABSPATH . WPINC . '/pomo/mo.php';
	}

	public function tear_down() {
		unload_textdomain( self::DOMAIN );
		parent::tear_down();
	}

	/**
	 * Path of a file in the plugin's languages folder.
	 *
	 * @param string $name File name.
	 * @return string
	 */
	private function language_file( $name ) {
		return NH_SEO_DIR . 'languages/' . $name;
	}

	/**
	 * A parsed PO or POT file.
	 *
	 * @param string $name File name.
	 * @return PO
	 */
	private function po( $name ) {
		if ( ! isset( self::$catalogs[ $name ] ) ) {
			$po = new PO();
			$this->assertTrue( $po->import_from_file( $this->language_file( $name ) ), "$name can be parsed" );
			self::$catalogs[ $name ] = $po;
		}
		return self::$catalogs[ $name ];
	}

	/**
	 * Every translatable string the files of the admin, data and render modules use, keyed like a catalog entry.
	 *
	 * @return array<string, array{text: string, context: string|null, plural: string|null}>
	 */
	private function module_strings() {
		$strings = array();
		$files   = array_merge(
			glob( NH_SEO_DIR . 'includes/admin/*.php' ),
			glob( NH_SEO_DIR . 'includes/data/*.php' ),
			glob( NH_SEO_DIR . 'includes/render/*.php' )
		);
		foreach ( $files as $file ) {
			foreach ( $this->extract_strings( $file ) as $key => $string ) {
				$strings[ $key ] = $string;
			}
		}
		return $strings;
	}

	/**
	 * Decode a PHP string literal token.
	 *
	 * @param string $literal Source text of the literal, quotes included.
	 * @return string
	 */
	private function literal( $literal ) {
		$body = substr( $literal, 1, -1 );
		if ( "'" === $literal[0] ) {
			return str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $body );
		}
		return stripcslashes( $body );
	}

	/**
	 * Translatable strings used in a PHP file, found with the tokenizer like the real extractors do.
	 *
	 * @param string $file File path.
	 * @return array<string, array{text: string, context: string|null, plural: string|null}>
	 */
	private function extract_strings( $file ) {
		$functions = array(
			'__'         => array(
				'text'   => 0,
				'domain' => 1,
			),
			'_e'         => array(
				'text'   => 0,
				'domain' => 1,
			),
			'esc_html__' => array(
				'text'   => 0,
				'domain' => 1,
			),
			'esc_html_e' => array(
				'text'   => 0,
				'domain' => 1,
			),
			'esc_attr__' => array(
				'text'   => 0,
				'domain' => 1,
			),
			'esc_attr_e' => array(
				'text'   => 0,
				'domain' => 1,
			),
			'_x'         => array(
				'text'    => 0,
				'context' => 1,
				'domain'  => 2,
			),
			'_ex'        => array(
				'text'    => 0,
				'context' => 1,
				'domain'  => 2,
			),
			'esc_html_x' => array(
				'text'    => 0,
				'context' => 1,
				'domain'  => 2,
			),
			'esc_attr_x' => array(
				'text'    => 0,
				'context' => 1,
				'domain'  => 2,
			),
			'_n'         => array(
				'text'   => 0,
				'plural' => 1,
				'domain' => 3,
			),
		);

		$tokens = array_values(
			array_filter(
				token_get_all( file_get_contents( $file ) ),
				static function ( $token ) {
					return ! is_array( $token ) || ! in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true );
				}
			)
		);

		$found = array();
		$count = count( $tokens );
		for ( $i = 0; $i < $count - 1; $i++ ) {
			if ( ! is_array( $tokens[ $i ] ) || T_STRING !== $tokens[ $i ][0] || ! isset( $functions[ $tokens[ $i ][1] ] ) || '(' !== $tokens[ $i + 1 ] ) {
				continue;
			}

			// Split the arguments of this call at depth zero.
			$args    = array( array() );
			$depth   = 0;
			$current = 0;
			for ( $j = $i + 2; $j < $count; $j++ ) {
				$token = $tokens[ $j ];
				if ( '(' === $token || '[' === $token ) {
					++$depth;
				} elseif ( ')' === $token || ']' === $token ) {
					if ( 0 === $depth ) {
						break;
					}
					--$depth;
				} elseif ( ',' === $token && 0 === $depth ) {
					++$current;
					$args[ $current ] = array();
					continue;
				}
				$args[ $current ][] = $token;
			}

			$map    = $functions[ $tokens[ $i ][1] ];
			$values = array();
			foreach ( $map as $name => $position ) {
				$tokens_of_arg   = isset( $args[ $position ] ) ? $args[ $position ] : array();
				$values[ $name ] = ( 1 === count( $tokens_of_arg ) && is_array( $tokens_of_arg[0] ) && T_CONSTANT_ENCAPSED_STRING === $tokens_of_arg[0][0] ) ? $this->literal( $tokens_of_arg[0][1] ) : null;
			}
			if ( self::DOMAIN !== $values['domain'] ) {
				continue;
			}
			$this->assertNotNull( $values['text'], basename( $file ) . ': the text of ' . $tokens[ $i ][1] . '() must be a plain string literal so that it can be extracted' );

			$context       = isset( $values['context'] ) ? $values['context'] : null;
			$key           = null === $context ? $values['text'] : $context . chr( 4 ) . $values['text'];
			$found[ $key ] = array(
				'text'    => $values['text'],
				'context' => $context,
				'plural'  => isset( $values['plural'] ) ? $values['plural'] : null,
			);
		}
		return $found;
	}

	public function test_the_catalogs_exist_with_the_names_wordpress_loads() {
		foreach ( array( 'nguyen-ha-seo-companion.pot', 'nguyen-ha-seo-companion-vi.po', 'nguyen-ha-seo-companion-vi.mo' ) as $file ) {
			$this->assertFileExists( $this->language_file( $file ) );
			$this->assertGreaterThan( 1000, filesize( $this->language_file( $file ) ), $file );
		}
		// load_plugin_textdomain() looks for {domain}-{locale}.mo in the folder named by the plugin class.
		$this->assertStringContainsString( "'/languages'", file_get_contents( NH_SEO_DIR . 'includes/class-nh-seo-plugin.php' ) );
		$this->assertStringContainsString( 'Text Domain:       nguyen-ha-seo-companion', file_get_contents( NH_SEO_FILE ) );
		$this->assertStringContainsString( 'Domain Path:       /languages', file_get_contents( NH_SEO_FILE ) );
	}

	public function test_the_pot_has_the_right_headers_and_no_translations() {
		$pot = $this->po( 'nguyen-ha-seo-companion.pot' );

		$this->assertSame( 'nguyen-ha-seo-companion', $pot->headers['X-Domain'] );
		$this->assertStringStartsWith( 'Nguyen Ha SEO Companion', $pot->headers['Project-Id-Version'] );
		$this->assertStringStartsWith( 'https://github.com/', $pot->headers['Report-Msgid-Bugs-To'] );
		foreach ( $pot->entries as $entry ) {
			$this->assertSame( array(), array_filter( $entry->translations, 'strlen' ), 'a template holds no translations: ' . $entry->singular );
		}
	}

	public function test_every_string_of_the_plugin_modules_is_in_the_pot() {
		$pot     = $this->po( 'nguyen-ha-seo-companion.pot' );
		$missing = array();
		foreach ( $this->module_strings() as $key => $string ) {
			if ( ! isset( $pot->entries[ $key ] ) ) {
				$missing[] = $string['text'];
			} elseif ( null !== $string['plural'] ) {
				$this->assertSame( $string['plural'], $pot->entries[ $key ]->plural, 'plural form of: ' . $string['text'] );
			}
		}
		$this->assertSame( array(), $missing, 'regenerate the POT: wp i18n make-pot plugins/nguyen-ha-seo-companion plugins/nguyen-ha-seo-companion/languages/nguyen-ha-seo-companion.pot' );
		$this->assertGreaterThan( 100, count( $this->module_strings() ), 'the extractor found the strings' );
	}

	public function test_every_string_of_the_plugin_modules_is_translated_into_vietnamese() {
		$po           = $this->po( 'nguyen-ha-seo-companion-vi.po' );
		$untranslated = array();
		foreach ( $this->module_strings() as $key => $string ) {
			if ( empty( $po->entries[ $key ] ) ) {
				$untranslated[] = $string['text'];
				continue;
			}
			$entry = $po->entries[ $key ];
			foreach ( $entry->translations as $translation ) {
				if ( '' === trim( $translation ) ) {
					$untranslated[] = $string['text'];
				}
			}
			$this->assertNotContains( 'fuzzy', $entry->flags, 'not fuzzy: ' . $string['text'] );
		}
		$this->assertSame( array(), $untranslated, 'update the Vietnamese catalog: wp i18n update-po' );
	}

	public function test_shared_strings_are_translated_too() {
		$po = $this->po( 'nguyen-ha-seo-companion-vi.po' );

		foreach ( array(
			'Home'                   => 'Trang chủ',
			'(no title)'             => '(không có tiêu đề)',
			'Search results for: %s' => 'Kết quả tìm kiếm cho: %s',
		) as $text => $expected ) {
			$this->assertSame( array( $expected ), $po->entries[ $text ]->translations, $text );
		}
	}

	public function test_placeholders_and_markup_are_identical_in_every_translation() {
		$po = $this->po( 'nguyen-ha-seo-companion-vi.po' );
		$this->assertGreaterThan( 150, count( $po->entries ) );

		foreach ( $po->entries as $entry ) {
			$sources = array( $entry->singular, $entry->plural );
			$tokens  = static function ( $text ) {
				preg_match_all( '/%(?:\d+\$)?[sd]|&\w+;|<[^>]+>/', (string) $text, $matches );
				sort( $matches[0] );
				return $matches[0];
			};
			foreach ( $entry->translations as $index => $translation ) {
				$source = 0 === $index || null === $entry->plural ? $entry->singular : $entry->plural;
				$this->assertSame( $tokens( $source ), $tokens( $translation ), 'placeholders of: ' . $entry->singular );
				$this->assertNotSame( '', trim( $translation ), 'translated: ' . $entry->singular );
			}
			unset( $sources );
		}
	}

	public function test_the_vietnamese_catalog_declares_vietnamese_and_its_plural_rule() {
		$po = $this->po( 'nguyen-ha-seo-companion-vi.po' );

		$this->assertSame( 'vi', $po->headers['Language'] );
		$this->assertSame( 'nplurals=1; plural=0;', $po->headers['Plural-Forms'] );
		$this->assertStringContainsString( 'charset=UTF-8', $po->headers['Content-Type'] );
		$this->assertSame( 'nguyen-ha-seo-companion', $po->headers['X-Domain'] );
	}

	public function test_the_compiled_catalog_matches_the_po_file() {
		$po = $this->po( 'nguyen-ha-seo-companion-vi.po' );
		$mo = new MO();
		$this->assertTrue( $mo->import_from_file( $this->language_file( 'nguyen-ha-seo-companion-vi.mo' ) ) );

		$this->assertSame( 'vi', $mo->headers['Language'] );
		$this->assertCount( count( $po->entries ), $mo->entries, 'the .mo was compiled from the current .po: run wp i18n make-mo' );
		foreach ( $po->entries as $key => $entry ) {
			$this->assertArrayHasKey( $key, $mo->entries, $entry->singular );
			$this->assertSame( $entry->translations, $mo->entries[ $key ]->translations, $entry->singular );
		}
	}

	public function test_translations_reach_the_output_once_the_catalog_is_loaded() {
		$this->assertSame( 'Quick answer', __( 'Quick answer', 'nguyen-ha-seo-companion' ), 'English without a catalog' );

		$this->assertTrue( load_textdomain( self::DOMAIN, $this->language_file( 'nguyen-ha-seo-companion-vi.mo' ) ) );

		// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment -- These calls only exercise the compiled catalog.
		$this->assertSame( 'Trả lời nhanh', __( 'Quick answer', 'nguyen-ha-seo-companion' ) );
		$this->assertSame( 'Cụm chủ đề', _x( 'Topic Cluster', 'taxonomy singular name', 'nguyen-ha-seo-companion' ) );
		$this->assertSame( 'Cụm chủ đề', _x( 'Topic Clusters', 'taxonomy general name', 'nguyen-ha-seo-companion' ) );
		$this->assertSame( 'Đã bỏ qua 1 liên kết hồ sơ vì không phải địa chỉ http hoặc https hợp lệ.', sprintf( _n( '%d profile link was ignored because it is not a valid http or https address.', '%d profile links were ignored because they are not valid http or https addresses.', 1, 'nguyen-ha-seo-companion' ), 1 ) );
		$this->assertSame( 'Đã bỏ qua 5 liên kết hồ sơ vì không phải địa chỉ http hoặc https hợp lệ.', sprintf( _n( '%d profile link was ignored because it is not a valid http or https address.', '%d profile links were ignored because they are not valid http or https addresses.', 5, 'nguyen-ha-seo-companion' ), 5 ) );
		$this->assertSame( '12/800 ký tự', sprintf( __( '%1$s of %2$s characters', 'nguyen-ha-seo-companion' ), 12, 800 ) );
		// phpcs:enable WordPress.WP.I18n.MissingTranslatorsComment
	}

	public function test_the_front_end_blocks_speak_vietnamese() {
		load_textdomain( self::DOMAIN, $this->language_file( 'nguyen-ha-seo-companion-vi.mo' ) );

		$this->assertStringContainsString( '<h2 class="nh-seo-block__title">Trả lời nhanh</h2>', NH_SEO_Renderer::summary( 'Một câu trả lời.' ) );
		$this->assertStringContainsString( '<h2 class="nh-seo-block__title">Điểm chính cần nhớ</h2>', NH_SEO_Renderer::takeaways( array( 'Ý một' ) ) );
		$this->assertStringContainsString( '<h2 class="nh-seo-block__title">Nguồn tham khảo</h2>', NH_SEO_Renderer::sources( array( array( 'url' => 'https://example.org' ) ) ) );
		$this->assertStringContainsString(
			'<h2 class="nh-seo-block__title">Câu hỏi thường gặp</h2>',
			NH_SEO_Renderer::faq(
				array(
					array(
						'question' => 'Hỏi?',
						'answer'   => 'Đáp.',
					),
				)
			)
		);
		$this->assertStringContainsString( 'Nội dung được cập nhật:', NH_SEO_Renderer::updated( '2024-01-15' ) );
	}

	public function test_the_admin_screens_speak_vietnamese() {
		load_textdomain( self::DOMAIN, $this->language_file( 'nguyen-ha-seo-companion-vi.mo' ) );

		$rows = NH_SEO_Settings_Page::status_rows();
		$this->assertArrayHasKey( 'Phiên bản plugin', $rows );
		$this->assertArrayHasKey( 'Chỉ mục tìm kiếm nội bộ', $rows );

		$tabs = array();
		foreach ( NH_SEO_Settings_Page::business_sections() as $section ) {
			$tabs[] = $section['title'];
		}
		$this->assertSame( array( 'Thông tin nhận diện', 'Liên hệ', 'Địa chỉ', 'Giờ mở cửa và liên kết' ), $tabs );

		$post_id = self::factory()->post->create();
		ob_start();
		NH_SEO_Meta_Box::render( get_post( $post_id ) );
		$html = ob_get_clean();
		$this->assertStringContainsString( 'Trả lời nhanh', $html );
		$this->assertStringContainsString( 'Thêm câu hỏi', $html );
		$this->assertStringContainsString( 'data-nh-label-up="Chuyển câu hỏi %d lên"', $html );
		$this->assertStringContainsString( 'data-nh-counter-text="%1$s/%2$s ký tự"', $html );

		NH_SEO_Data_Model::register_taxonomy();
		$labels = get_taxonomy( 'topic_cluster' )->labels;
		$this->assertSame( 'Cụm chủ đề', $labels->name );
		$this->assertSame( 'Thêm cụm chủ đề mới', $labels->add_new_item );
	}

	public function test_translation_files_hold_no_business_data() {
		foreach ( array( 'nguyen-ha-seo-companion.pot', 'nguyen-ha-seo-companion-vi.po' ) as $file ) {
			$text = file_get_contents( $this->language_file( $file ) );
			$this->assertDoesNotMatchRegularExpression( '/\+?\d[\d ().-]{8,}\d/', preg_replace( '/^#.*$|^"[A-Za-z-]+: .*$/m', '', $text ), "$file must not hold phone numbers" );
			$this->assertDoesNotMatchRegularExpression( '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', preg_replace( '/^"(?:Last-Translator|Language-Team|Report-Msgid-Bugs-To): .*$/m', '', $text ), "$file must not hold email addresses" );
		}
	}
}
