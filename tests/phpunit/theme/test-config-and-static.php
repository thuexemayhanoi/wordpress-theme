<?php
/**
 * Configuration schema, accent color accessibility and static quality gates for the theme source.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings schema, sanitizers, business profile access and social links.
 */
class NH_Test_Config extends NH_Unit_Test_Case {

	public function set_up() {
		parent::set_up();
		remove_theme_mods();
		remove_all_filters( 'nguyen_ha_business_profile' );
	}

	public function test_every_schema_key_has_a_working_default() {
		foreach ( nh_editorial_settings_schema() as $key => $def ) {
			$this->assertArrayHasKey( 'default', $def, $key );
			$this->assertArrayHasKey( 'label', $def, $key );
			$this->assertSame(
				nh_editorial_sanitize_setting( $key, $def['default'] ),
				nh_editorial_option( $key ),
				"Default of {$key} must survive its own sanitizer."
			);
		}
	}

	public function test_sanitizers_reject_bad_input() {
		$this->assertSame( '#0b57d0', nh_editorial_sanitize_setting( 'nh_accent', 'not-a-color' ) );
		$this->assertSame( '#112233', nh_editorial_sanitize_setting( 'nh_accent', '#112233' ) );
		$this->assertSame( 10, nh_editorial_sanitize_setting( 'nh_toc_min', 999 ) );
		$this->assertSame( 2, nh_editorial_sanitize_setting( 'nh_toc_min', -4 ) );
		$this->assertSame( 3, nh_editorial_sanitize_setting( 'nh_toc_min', 'abc' ) );
		$this->assertSame( 'with_bio', nh_editorial_sanitize_setting( 'nh_author_box', 'evil' ) );
		$this->assertSame( 'never', nh_editorial_sanitize_setting( 'nh_author_box', 'never' ) );
		$this->assertSame( '3', nh_editorial_sanitize_setting( 'nh_related_count', '99' ) );
		$this->assertTrue( nh_editorial_sanitize_setting( 'nh_dark_mode', '1' ) );
		$this->assertFalse( nh_editorial_sanitize_setting( 'nh_dark_mode', 'maybe' ) );
		$this->assertSame( '', nh_editorial_sanitize_setting( 'nh_cta_url', 'javascript:alert(1)' ) );
		$this->assertSame( 'tel:+000', nh_editorial_sanitize_setting( 'nh_cta_url', 'tel:+000' ) );
		$this->assertSame( 'https://example.org/a', nh_editorial_sanitize_setting( 'nh_cta_url', ' https://example.org/a ' ) );
		$this->assertNull( nh_editorial_sanitize_setting( 'nh_unknown', 'x' ) );
	}

	public function test_footer_note_is_kses_filtered() {
		$clean = nh_editorial_sanitize_setting( 'nh_footer_text', '<a href="https://example.org" onclick="x()">ok</a><script>alert(1)</script><iframe src="x"></iframe>' );
		$this->assertStringContainsString( '<a href="https://example.org">ok</a>', $clean );
		$this->assertStringNotContainsString( 'script', $clean );
		$this->assertStringNotContainsString( 'iframe', $clean );
		$this->assertStringNotContainsString( 'onclick', $clean );
	}

	public function test_urls_setting_keeps_valid_unique_http_urls_only() {
		$value = nh_editorial_sanitize_urls( "https://a.example/x\njavascript:alert(1)\nhttps://a.example/x\nftp://b.example\n  http://c.example/y  " );
		$this->assertSame( "https://a.example/x\nhttp://c.example/y", $value );
	}

	public function test_option_is_sanitized_when_read() {
		set_theme_mod( 'nh_toc_min', 999 );
		set_theme_mod( 'nh_accent', 'red' );
		$this->assertSame( 10, nh_editorial_option( 'nh_toc_min' ) );
		$this->assertSame( '#0b57d0', nh_editorial_option( 'nh_accent' ) );
	}

	public function test_cta_needs_label_and_link() {
		$this->assertSame( array(), nh_editorial_cta() );
		set_theme_mod( 'nh_cta_label', 'Go' );
		$this->assertSame( array(), nh_editorial_cta() );
		set_theme_mod( 'nh_cta_url', 'https://example.org/go' );
		$cta = nh_editorial_cta();
		$this->assertSame( 'Go', $cta['label'] );
		$this->assertSame( 'https://example.org/go', $cta['url'] );
	}

	public function test_business_profile_is_empty_without_the_plugin_filter() {
		$business = nh_editorial_business();
		$this->assertSame( '', $business['phone'] );
		$this->assertSame( array(), $business['hours'] );
		$this->assertFalse( nh_editorial_has_business_contact() );
	}

	public function test_business_profile_shape_is_normalized() {
		add_filter(
			'nguyen_ha_business_profile',
			static function () {
				return array(
					'name'   => 'Example Co',
					'phone'  => array( 'not', 'scalar' ),
					'email'  => 'hello@example.org',
					'hours'  => 'not-an-array',
					'social' => array( 'https://example.org/profile', 123 ),
					'evil'   => '<script>',
				);
			}
		);
		$business = nh_editorial_business();
		$this->assertSame( 'Example Co', $business['name'] );
		$this->assertSame( '', $business['phone'] );
		$this->assertSame( array(), $business['hours'] );
		$this->assertArrayNotHasKey( 'evil', $business );
		$this->assertTrue( nh_editorial_has_business_contact() );

		add_filter(
			'nguyen_ha_business_profile',
			static function () {
				return 'garbage';
			},
			20
		);
		$this->assertSame( '', nh_editorial_business()['name'] );
	}

	public function test_social_links_describe_networks_and_prefer_the_theme_setting() {
		add_filter(
			'nguyen_ha_business_profile',
			static function () {
				return array( 'social' => array( 'https://www.tiktok.com/@example' ) );
			}
		);
		$from_business = nh_editorial_social_links();
		$this->assertSame( 'TikTok', $from_business[0]['label'] );

		set_theme_mod( 'nh_social_urls', "https://www.facebook.com/example\nhttps://youtu.be/abc\nhttps://unknown.example/me\njavascript:alert(1)" );
		$links = nh_editorial_social_links();
		$this->assertSame( array( 'Facebook', 'YouTube', 'unknown.example' ), wp_list_pluck( $links, 'label' ) );
		$this->assertSame( array( 'facebook', 'youtube', 'link' ), wp_list_pluck( $links, 'icon' ) );
	}

	public function test_social_icons_exist_for_every_known_network() {
		$icons = nh_editorial_icon_paths();
		foreach ( nh_editorial_social_networks() as $domain => $network ) {
			$this->assertArrayHasKey( $network['icon'], $icons, $domain );
		}
		$this->assertArrayHasKey( 'link', $icons );
	}
}

/**
 * Accessible accent derivation.
 */
class NH_Test_Accent_Color extends NH_Unit_Test_Case {

	/**
	 * Accent colors an owner might plausibly pick, including hard cases.
	 *
	 * @return array<int, array<int, string>>
	 */
	public function accent_provider() {
		return array(
			'default blue' => array( '#0b57d0' ),
			'pale yellow'  => array( '#ffe066' ),
			'light green'  => array( '#90ee90' ),
			'white'        => array( '#ffffff' ),
			'black'        => array( '#000000' ),
			'mid orange'   => array( '#ff8800' ),
			'deep purple'  => array( '#2a0a5e' ),
			'short hex'    => array( '#0af' ),
			'bright red'   => array( '#ff0000' ),
			'light cyan'   => array( '#7df9ff' ),
		);
	}

	/**
	 * @dataProvider accent_provider
	 *
	 * @param string $accent Accent color.
	 */
	public function test_derived_tokens_meet_wcag_aa( $accent ) {
		$tokens = nh_editorial_accent_tokens( $accent );

		$this->assertGreaterThanOrEqual( 4.5, nh_editorial_contrast_ratio( $tokens['light']['--accent'], '#ffffff' ), 'accent text on white' );
		$this->assertGreaterThanOrEqual( 4.5, nh_editorial_contrast_ratio( $tokens['light']['--on-accent'], $tokens['light']['--accent'] ), 'button text on accent (light)' );
		$this->assertGreaterThanOrEqual( 4.5, nh_editorial_contrast_ratio( $tokens['dark']['--accent'], '#121826' ), 'accent text on dark paper' );
		$this->assertGreaterThanOrEqual( 4.5, nh_editorial_contrast_ratio( $tokens['dark']['--on-accent'], $tokens['dark']['--accent'] ), 'button text on accent (dark)' );
		$this->assertGreaterThanOrEqual( 4.5, nh_editorial_contrast_ratio( $tokens['light']['--accent'], $tokens['light']['--accent-soft'] ), 'accent text on its soft tint (light)' );
		$this->assertGreaterThanOrEqual( 4.5, nh_editorial_contrast_ratio( $tokens['dark']['--accent'], $tokens['dark']['--accent-soft'] ), 'accent text on its soft tint (dark)' );
	}

	public function test_default_accent_emits_no_css_and_custom_accent_emits_both_modes() {
		remove_theme_mods();
		$this->assertSame( '', nh_editorial_accent_css() );

		set_theme_mod( 'nh_accent', '#aa2200' );
		$css = nh_editorial_accent_css();
		$this->assertStringContainsString( ':root{--accent:', $css );
		$this->assertStringContainsString( ':root[data-theme="dark"]{', $css );
		$this->assertStringContainsString( ':root:not([data-theme])', $css );
		$this->assertDoesNotMatchRegularExpression( '/[<>]/', $css );
	}

	public function test_contrast_helpers() {
		$this->assertEqualsWithDelta( 21.0, nh_editorial_contrast_ratio( '#000000', '#ffffff' ), 0.01 );
		$this->assertEqualsWithDelta( 1.0, nh_editorial_contrast_ratio( '#777777', '#777777' ), 0.001 );
		$this->assertSame( '#808080', nh_editorial_mix( '#000000', '#ffffff', 0.5 ) );
		$this->assertNull( nh_editorial_hex_to_rgb( 'zzz' ) );
		$this->assertSame( array( 0, 170, 255 ), nh_editorial_hex_to_rgb( '#0af' ) );
	}
}

/**
 * Static checks over the theme source: metadata, security hygiene and "no invented business data".
 */
class NH_Test_Theme_Source extends NH_Unit_Test_Case {

	/**
	 * Theme PHP files (excludes tooling, tests, the companion plugin and dependencies).
	 *
	 * @return string[] Absolute paths.
	 */
	private function theme_php_files() {
		$root  = dirname( __DIR__, 3 );
		$files = array();
		$it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $file ) {
			$path = $file->getPathname();
			if ( 'php' !== $file->getExtension() ) {
				continue;
			}
			if ( preg_match( '#/(vendor|node_modules|tests|plugins|\.git|\.claude|tools)/#', $path ) ) {
				continue;
			}
			$files[] = $path;
		}
		sort( $files );
		return $files;
	}

	/**
	 * Every PHP file of the repository: theme, companion plugin, tests and tooling (not dependencies).
	 *
	 * @return string[] Absolute paths.
	 */
	private function repository_php_files() {
		$root  = dirname( __DIR__, 3 );
		$files = array();
		$it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $file ) {
			$path = $file->getPathname();
			if ( 'php' !== $file->getExtension() || preg_match( '#/(vendor|node_modules|\.git|\.claude|dist)/#', $path ) ) {
				continue;
			}
			$files[] = $path;
		}
		sort( $files );
		return $files;
	}

	public function test_version_constant_matches_style_header() {
		$theme = wp_get_theme( 'nguyen-ha-editorial' );
		$this->assertSame( NH_EDITORIAL_VERSION, $theme->get( 'Version' ) );
		$this->assertSame( 'nguyen-ha-editorial', $theme->get( 'TextDomain' ) );
		$this->assertSame( '/languages', $theme->get( 'DomainPath' ) );
		$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', NH_EDITORIAL_VERSION );
	}

	public function test_minimum_versions_match_the_lint_configuration() {
		$theme = wp_get_theme( 'nguyen-ha-editorial' );
		$phpcs = (string) file_get_contents( dirname( __DIR__, 3 ) . '/phpcs.xml.dist' );
		preg_match( '/minimum_supported_wp_version" value="([\d.]+)"/', $phpcs, $wp );
		preg_match( '/testVersion" value="([\d.]+)-"/', $phpcs, $php );
		$this->assertSame( $wp[1], $theme->get( 'RequiresWP' ) );
		$this->assertSame( $php[1], $theme->get( 'RequiresPHP' ) );
	}

	public function test_theme_json_is_valid() {
		$data = json_decode( (string) file_get_contents( dirname( __DIR__, 3 ) . '/theme.json' ), true );
		$this->assertIsArray( $data );
		$this->assertSame( 2, $data['version'] );
		$this->assertFalse( $data['settings']['color']['defaultPalette'] );
		$this->assertSame( '760px', $data['settings']['layout']['contentSize'] );
	}

	public function test_every_php_file_has_an_abspath_guard() {
		foreach ( $this->theme_php_files() as $file ) {
			$source = (string) file_get_contents( $file );
			$this->assertTrue(
				false !== strpos( $source, "defined( 'ABSPATH' )" ) || false !== strpos( $source, 'Silence is golden' ),
				basename( $file ) . ' must stop when loaded outside WordPress.'
			);
		}
	}

	/**
	 * A deployment may copy the whole repository into the theme folder (docs/DEPLOYMENT.md), so even the tests,
	 * the tooling and the companion plugin must stop when requested directly over the web.
	 */
	public function test_no_php_file_of_the_repository_runs_when_requested_directly() {
		$files = $this->repository_php_files();
		$this->assertGreaterThan( 100, count( $files ), 'the whole repository is scanned' );

		$unguarded = array();
		foreach ( $files as $file ) {
			if ( ! preg_match( "/defined\( 'ABSPATH' \)|Silence is golden|PHP_SAPI/", (string) file_get_contents( $file ) ) ) {
				$unguarded[] = substr( $file, strlen( dirname( __DIR__, 3 ) ) + 1 );
			}
		}
		$this->assertSame( array(), $unguarded, 'these files would run when requested directly over the web' );
	}

	public function test_development_folders_are_closed_to_the_web_and_left_out_of_the_zip() {
		$root       = dirname( __DIR__, 3 );
		$attributes = (string) file_get_contents( $root . '/.gitattributes' );
		foreach ( array( 'tests', 'tools', 'docs', 'plugins' ) as $folder ) {
			$htaccess = (string) file_get_contents( "{$root}/{$folder}/.htaccess" );
			$this->assertStringContainsString( 'Require all denied', $htaccess, "{$folder}/.htaccess (Apache 2.4, LiteSpeed)" );
			$this->assertStringContainsString( 'Deny from all', $htaccess, "{$folder}/.htaccess (Apache 2.2)" );
			$this->assertMatchesRegularExpression( '#^/' . $folder . '(/|\s)#m', $attributes, "{$folder} is left out of the installable zip" );
		}
		$this->assertMatchesRegularExpression( '#^/\.htaccess\s+export-ignore#m', $attributes, 'the root .htaccess guards the repository copy only' );
		$this->assertFileExists( $root . '/.htaccess' );
	}

	/**
	 * A file in the installable zip that nothing loads is dead weight and, worse, a stale copy that someone may
	 * edit by mistake (version 1 left such a script behind).
	 */
	public function test_every_shipped_asset_is_loaded_by_the_theme() {
		$root   = dirname( __DIR__, 3 );
		$source = '';
		foreach ( $this->theme_php_files() as $file ) {
			$source .= (string) file_get_contents( $file );
		}
		$assets = array_merge( (array) glob( $root . '/assets/css/*.css' ), (array) glob( $root . '/assets/js/*.js' ) );
		$this->assertNotEmpty( $assets );

		$unused = array();
		foreach ( $assets as $asset ) {
			$relative = 'assets/' . basename( dirname( $asset ) ) . '/' . basename( $asset );
			if ( false === strpos( $source, $relative ) ) {
				$unused[] = $relative;
			}
		}
		$this->assertSame( array(), $unused, 'these shipped assets are not loaded by any theme file' );
	}

	public function test_no_dangerous_functions_in_theme_source() {
		$forbidden = '/\b(eval|base64_decode|shell_exec|passthru|proc_open|popen|create_function|unserialize|extract|assert)\s*\(|\bexec\s*\(|\bsystem\s*\(/';
		foreach ( $this->theme_php_files() as $file ) {
			$source = (string) preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', (string) file_get_contents( $file ) );
			$this->assertDoesNotMatchRegularExpression( $forbidden, $source, basename( $file ) );
		}
	}

	public function test_no_request_data_is_used_without_a_sanitizer() {
		foreach ( $this->theme_php_files() as $file ) {
			$source = (string) file_get_contents( $file );
			if ( ! preg_match_all( '/[^;{}]*\$_(GET|POST|REQUEST|COOKIE|SERVER)\b[^;{}]*[;{]/', $source, $matches ) ) {
				continue;
			}
			foreach ( $matches[0] as $statement ) {
				$this->assertMatchesRegularExpression(
					'/wp_unslash|absint|sanitize_|esc_|isset\(|check_admin_referer|wp_verify_nonce/',
					$statement,
					basename( $file ) . ': ' . $statement
				);
			}
		}
	}

	public function test_no_business_data_is_hard_coded() {
		$root    = dirname( __DIR__, 3 );
		$sources = array();
		foreach ( $this->theme_php_files() as $file ) {
			$sources[ $file ] = (string) file_get_contents( $file );
		}
		foreach ( glob( $root . '/assets/src/{css,js}/*', GLOB_BRACE ) as $file ) {
			$sources[ $file ] = (string) file_get_contents( $file );
		}

		// Owner reference data and anything that looks like a hard-coded phone link or price.
		$patterns = array(
			'/0942\s?467\s?674|\+?84\s?942/',
			'/thuexemaynguyentu|rentbikehanoi\.com/i',
			'/112 Nguy[eễ]n V[aă]n C[uừ]/iu',
			'/href=["\']tel:\+?\d/',
			'/\b\d{2,3}[.,]\d{3}\s?(?:đ|VND|vnd|₫)/u',
			'/(?:aggregateRating|ratingValue|reviewCount)/',
		);
		foreach ( $sources as $file => $source ) {
			foreach ( $patterns as $pattern ) {
				$this->assertDoesNotMatchRegularExpression( $pattern, $source, basename( $file ) . ' contains hard-coded business data.' );
			}
		}
	}

	public function test_the_theme_never_prints_seo_owned_markup() {
		$forbidden = '/application\/ld\+json|rel=["\']canonical|name=["\']description|property=["\']og:|name=["\']twitter:|name=["\']robots|hreflang/i';
		foreach ( $this->theme_php_files() as $file ) {
			$source = (string) preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', (string) file_get_contents( $file ) );
			$this->assertDoesNotMatchRegularExpression( $forbidden, $source, basename( $file ) . ' must leave SEO output to the SEO plugin.' );
		}
	}

	public function test_text_domain_is_consistent() {
		foreach ( $this->theme_php_files() as $file ) {
			$source = (string) file_get_contents( $file );
			preg_match_all( "/\b(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e|_x|_n)\([^;]*?,\s*'([a-z0-9-]+)'\s*\)/", $source, $matches );
			foreach ( $matches[1] as $domain ) {
				$this->assertSame( 'nguyen-ha-editorial', $domain, basename( $file ) );
			}
		}
	}

	public function test_no_custom_image_sizes_are_registered() {
		foreach ( get_intermediate_image_sizes() as $size ) {
			$this->assertStringStartsNotWith( 'nh', $size );
		}
	}
}
