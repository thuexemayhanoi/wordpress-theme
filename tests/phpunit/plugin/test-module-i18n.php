<?php
/**
 * Translations of the schema, search, cache and CLI modules.
 *
 * The admin, data and render modules have their own checks (test-admin-i18n.php). Here: every translatable
 * string of the four modules of this part of the plugin is in the template, has a complete Vietnamese
 * translation, and the translations really reach the Tools panel, its script texts, the status line and the
 * WP-CLI messages. Strings are found with the PHP tokenizer, like the real extractors do.
 *
 * @package nguyen-ha-seo-companion
 */

// The output double of the WP-CLI command tests.
require_once __DIR__ . '/class-nh-cli-test-output.php';

/**
 * Catalog checks for the schema, search, cache and CLI modules.
 */
class NH_Test_Module_I18n extends NH_Unit_Test_Case {

	const DOMAIN = 'nguyen-ha-seo-companion';

	/**
	 * Parsed catalogs, shared by the tests.
	 *
	 * @var array
	 */
	private static $catalogs = array();

	public static function set_up_before_class() {
		parent::set_up_before_class();
		require_once ABSPATH . WPINC . '/pomo/po.php';
		require_once ABSPATH . WPINC . '/pomo/mo.php';
	}

	public function tear_down() {
		unload_textdomain( self::DOMAIN );
		wp_dequeue_script( NH_SEO_Search_Admin::HANDLE );
		wp_deregister_script( NH_SEO_Search_Admin::HANDLE );
		parent::tear_down();
	}

	/**
	 * Path of a file in the languages folder.
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
	 * Load the compiled Vietnamese catalog.
	 *
	 * @return void
	 */
	private function speak_vietnamese() {
		$this->assertTrue( load_textdomain( self::DOMAIN, $this->language_file( 'nguyen-ha-seo-companion-vi.mo' ) ) );
	}

	/**
	 * Every translatable string used in the files of the four modules, keyed like a catalog entry.
	 *
	 * @return array<string, array{text: string, file: string}>
	 */
	private function module_strings() {
		$files = array_merge(
			glob( NH_SEO_DIR . 'includes/schema/*.php' ),
			glob( NH_SEO_DIR . 'includes/search/*.php' ),
			glob( NH_SEO_DIR . 'includes/cache/*.php' ),
			glob( NH_SEO_DIR . 'includes/cli/*.php' )
		);

		$strings = array();
		foreach ( $files as $file ) {
			foreach ( $this->extract( $file ) as $text ) {
				$strings[ $text ] = array(
					'text' => $text,
					'file' => basename( $file ),
				);
			}
		}
		return $strings;
	}

	/**
	 * Texts of the translation calls of one file. Only the functions these modules use are understood; a call of
	 * any other translation function fails the test, so that no string can slip past the catalog check.
	 *
	 * @param string $file File path.
	 * @return string[]
	 */
	private function extract( $file ) {
		$simple = array( '__', '_e', 'esc_html__', 'esc_html_e', 'esc_attr__', 'esc_attr_e' );
		$other  = array( '_x', '_ex', 'esc_html_x', 'esc_attr_x', '_n', '_nx', '_n_noop', '_nx_noop' );

		$tokens = array_values(
			array_filter(
				token_get_all( (string) file_get_contents( $file ) ),
				static function ( $token ) {
					return ! is_array( $token ) || ! in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true );
				}
			)
		);

		$texts = array();
		$count = count( $tokens );
		for ( $i = 0; $i < $count - 1; $i++ ) {
			if ( ! is_array( $tokens[ $i ] ) || T_STRING !== $tokens[ $i ][0] || '(' !== $tokens[ $i + 1 ] ) {
				continue;
			}
			// A call, not a method or a declaration.
			if ( $i > 0 && is_array( $tokens[ $i - 1 ] ) && in_array( $tokens[ $i - 1 ][0], array( T_FUNCTION, T_OBJECT_OPERATOR, T_DOUBLE_COLON ), true ) ) {
				continue;
			}

			$name = $tokens[ $i ][1];
			$this->assertNotContains( $name, $other, basename( $file ) . ': ' . $name . '() is not handled by this check; extend it.' );
			if ( ! in_array( $name, $simple, true ) ) {
				continue;
			}

			$text   = $tokens[ $i + 2 ];
			$comma  = $tokens[ $i + 3 ];
			$domain = isset( $tokens[ $i + 4 ] ) ? $tokens[ $i + 4 ] : null;
			$this->assertTrue( is_array( $text ) && T_CONSTANT_ENCAPSED_STRING === $text[0], basename( $file ) . ': the text of ' . $name . '() must be a plain string literal so that it can be extracted.' );
			$this->assertSame( ',', $comma, basename( $file ) . ': ' . $name . '() needs the text domain.' );
			$this->assertTrue( is_array( $domain ) && "'" . self::DOMAIN . "'" === $domain[1], basename( $file ) . ': ' . $name . '() must use the text domain ' . self::DOMAIN . '.' );

			$body    = substr( $text[1], 1, -1 );
			$texts[] = "'" === $text[1][0] ? str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $body ) : stripcslashes( $body );
		}
		return array_values( array_unique( $texts ) );
	}

	public function test_the_extractor_finds_the_strings_of_all_four_modules() {
		$strings = $this->module_strings();
		$this->assertGreaterThan( 40, count( $strings ), 'The search, CLI and status strings were found.' );
		$files = array_unique( wp_list_pluck( $strings, 'file' ) );
		foreach ( array( 'class-nh-seo-cli-command.php', 'class-nh-seo-search-admin.php', 'class-nh-seo-search-state.php' ) as $expected ) {
			$this->assertContains( $expected, $files );
		}
	}

	public function test_every_string_of_the_four_modules_is_in_the_pot() {
		$pot     = $this->po( 'nguyen-ha-seo-companion.pot' );
		$missing = array();
		foreach ( $this->module_strings() as $key => $string ) {
			if ( ! isset( $pot->entries[ $key ] ) ) {
				$missing[] = $string['file'] . ': ' . $string['text'];
			}
		}
		$this->assertSame( array(), $missing, 'regenerate the POT: wp i18n make-pot plugins/nguyen-ha-seo-companion plugins/nguyen-ha-seo-companion/languages/nguyen-ha-seo-companion.pot' );
	}

	public function test_every_string_of_the_four_modules_has_a_complete_vietnamese_translation() {
		$po      = $this->po( 'nguyen-ha-seo-companion-vi.po' );
		$problem = array();
		foreach ( $this->module_strings() as $key => $string ) {
			if ( empty( $po->entries[ $key ] ) ) {
				$problem[] = 'not in the catalog: ' . $string['text'];
				continue;
			}
			$entry = $po->entries[ $key ];
			if ( array() === array_filter( $entry->translations, 'strlen' ) ) {
				$problem[] = 'untranslated: ' . $string['text'];
			}
			if ( in_array( 'fuzzy', $entry->flags, true ) ) {
				$problem[] = 'fuzzy: ' . $string['text'];
			}

			// Same placeholders (%s, %1$d, %%) and the same quotation marks around names and commands.
			$tokens = static function ( $text ) {
				preg_match_all( '/%(?:\d+\$)?[sd]|%%|--[a-z-]+|"[^"]*"/u', (string) $text, $matches );
				$found = array_map(
					static function ( $item ) {
						return '"' === $item[0] ? '"' : $item;
					},
					$matches[0]
				);
				sort( $found );
				return $found;
			};
			foreach ( $entry->translations as $translation ) {
				if ( $tokens( $string['text'] ) !== $tokens( $translation ) ) {
					$problem[] = 'placeholders or command names differ: ' . $string['text'];
				}
			}
		}
		$this->assertSame( array(), $problem, 'update the Vietnamese catalog: wp i18n update-po' );
	}

	public function test_the_compiled_catalog_holds_every_string_of_the_four_modules() {
		$mo = new MO();
		$this->assertTrue( $mo->import_from_file( $this->language_file( 'nguyen-ha-seo-companion-vi.mo' ) ) );
		$po = $this->po( 'nguyen-ha-seo-companion-vi.po' );
		foreach ( $this->module_strings() as $key => $string ) {
			$this->assertArrayHasKey( $key, $mo->entries, $string['text'] );
			$this->assertSame( $po->entries[ $key ]->translations, $mo->entries[ $key ]->translations, $string['text'] );
		}
	}

	public function test_the_status_line_speaks_vietnamese() {
		$this->speak_vietnamese();

		$this->assertStringContainsString( 'Chưa được tạo', NH_SEO_Search_State::status_line() );

		NH_SEO_Search_State::save(
			array(
				'ready'    => true,
				'total'    => 3000,
				'indexed'  => 3000,
				'last_run' => time(),
			)
		);
		$this->assertMatchesRegularExpression( '/^Sẵn sàng: đã lập chỉ mục [\d,]+ bài viết \(lần tạo gần nhất .+\)\. Các lượt tìm kiếm dùng chỉ mục\.$/u', NH_SEO_Search_State::status_line() );

		NH_SEO_Search_State::trip( 'Simulated error' );
		$this->assertStringContainsString( 'Chỉ mục tạm dừng đến', NH_SEO_Search_State::status_line() );
		NH_SEO_Search_State::clear_breaker();

		update_option( NH_SEO_Keys::OPTION_SETTINGS, array( 'search_mode' => 'native' ) );
		$this->assertStringContainsString( 'Tìm kiếm mặc định của WordPress', NH_SEO_Search_State::status_line() );
	}

	public function test_the_tools_panel_and_its_script_texts_speak_vietnamese() {
		$this->speak_vietnamese();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		ob_start();
		NH_SEO_Search_Admin::render_panel();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Trạng thái:', $html );
		$this->assertStringContainsString( 'Tạo / tạo lại chỉ mục', $html );
		$this->assertStringContainsString( 'Xóa chỉ mục', $html );
		$this->assertStringContainsString( 'Cần bật JavaScript', $html );
		$this->assertStringContainsString( 'Chỉ mục tìm kiếm nội bộ', $html );

		$inline = implode( "\n", (array) wp_scripts()->get_data( NH_SEO_Search_Admin::HANDLE, 'before' ) );
		foreach ( array( 'Đang bắt đầu…', 'Đã lập chỉ mục %1$s trên %2$s bài viết (%3$s%%).', 'Chỉ mục vẫn đang được tạo. Rời khỏi trang này?' ) as $text ) {
			// The panel encodes its configuration with JSON_HEX_TAG | JSON_HEX_AMP: letters come out as \uXXXX escapes.
			$this->assertStringContainsString( wp_json_encode( $text, JSON_HEX_TAG | JSON_HEX_AMP ), $inline, $text );
		}
	}

	public function test_the_wp_cli_messages_speak_vietnamese_but_the_machine_readable_keys_do_not() {
		$this->speak_vietnamese();
		$out     = new NH_CLI_Test_Output();
		$command = new NH_SEO_CLI_Command( $out );

		$run = static function ( $callback ) {
			try {
				$callback();
			} catch ( RuntimeException $e ) {
				return $e->getMessage();
			}
			return '';
		};

		$this->assertSame( 'Bài viết 99999999 không tồn tại.', $run( static function () use ( $command ) {
			$command->purge( array(), array( 'post' => '99999999' ) );
		} ) );
		$this->assertSame( '--batch phải là một số nguyên dương (từ 10 đến 500).', $run( static function () use ( $command ) {
			$command->reindex( array(), array( 'batch' => 'abc' ) );
		} ) );
		$this->assertSame( 'Hãy chỉ định bài viết bằng --post=<id>.', $run( static function () use ( $command ) {
			$command->purge( array(), array() );
		} ) );

		// The keys of index-status are an interface for scripts and stay in English.
		$command->index_status( array(), array( 'format' => 'json' ) );
		$rows = wp_list_pluck( $out->tables[0][1], 'value', 'key' );
		$this->assertSame( array( 'status', 'mode', 'used_for_searches', 'ready', 'building', 'table', 'posts_in_index', 'posts_at_last_build', 'last_run', 'paused_until', 'last_error', 'rebuild_recommended' ), array_keys( $rows ) );
		$this->assertContains( $rows['used_for_searches'], array( 'yes', 'no' ) );
	}
}
