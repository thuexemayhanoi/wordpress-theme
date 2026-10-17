<?php
/**
 * Tests that readme.txt stays consistent with the plugin header.
 *
 * @package nguyen-ha-seo-companion
 */

/**
 * The WordPress-style readme.
 */
class NH_Test_Admin_Readme extends NH_Unit_Test_Case {

	/**
	 * Header fields of readme.txt.
	 *
	 * @return array<string, string>
	 */
	private function readme_headers() {
		$lines   = preg_split( '/\R/', file_get_contents( NH_SEO_DIR . 'readme.txt' ) );
		$headers = array();
		foreach ( $lines as $line ) {
			if ( preg_match( '/^([A-Za-z ]+): (.+)$/', $line, $match ) ) {
				$headers[ $match[1] ] = trim( $match[2] );
			} elseif ( '' !== trim( $line ) && 0 !== strpos( $line, '===' ) ) {
				break; // The short description starts after the headers.
			}
		}
		return $headers;
	}

	/**
	 * The text of readme.txt.
	 *
	 * @return string
	 */
	private function readme() {
		return file_get_contents( NH_SEO_DIR . 'readme.txt' );
	}

	public function test_the_readme_starts_with_the_plugin_name_and_has_the_required_headers() {
		$this->assertStringStartsWith( '=== Nguyen Ha SEO Companion ===', $this->readme() );

		$headers = $this->readme_headers();
		foreach ( array( 'Contributors', 'Tags', 'Requires at least', 'Tested up to', 'Requires PHP', 'Stable tag', 'License', 'License URI' ) as $field ) {
			$this->assertArrayHasKey( $field, $headers, $field );
			$this->assertNotSame( '', $headers[ $field ], $field );
		}
	}

	public function test_the_readme_agrees_with_the_plugin_header() {
		$plugin  = get_file_data(
			NH_SEO_FILE,
			array(
				'Version' => 'Version',
				'WP'      => 'Requires at least',
				'PHP'     => 'Requires PHP',
				'License' => 'License',
				'Name'    => 'Plugin Name',
			)
		);
		$headers = $this->readme_headers();

		$this->assertSame( NH_SEO_VERSION, $plugin['Version'] );
		$this->assertSame( $plugin['Version'], $headers['Stable tag'] );
		$this->assertSame( $plugin['WP'], $headers['Requires at least'] );
		$this->assertSame( $plugin['PHP'], $headers['Requires PHP'] );
		$this->assertStringContainsString( 'GPL', $headers['License'] );
		$this->assertStringContainsString( 'GPL', $plugin['License'] );
		$this->assertStringContainsString( '=== ' . $plugin['Name'] . ' ===', $this->readme() );
		$this->assertMatchesRegularExpression( '/^\d+\.\d+(\.\d+)?$/', $headers['Tested up to'] );
		$this->assertGreaterThanOrEqual( 0, version_compare( $headers['Tested up to'], $headers['Requires at least'] ) );
	}

	public function test_the_short_description_fits_the_directory_limit() {
		$lines = preg_split( '/\R/', $this->readme() );
		$short = '';
		$seen  = false;
		foreach ( $lines as $line ) {
			if ( 0 === strpos( $line, 'License URI:' ) ) {
				$seen = true;
				continue;
			}
			if ( $seen && '' !== trim( $line ) ) {
				$short = trim( $line );
				break;
			}
		}

		$this->assertNotSame( '', $short );
		$this->assertLessThanOrEqual( 150, mb_strlen( $short ), 'the short description may have 150 characters at most' );
		$this->assertStringNotContainsString( '==', $short );
	}

	public function test_the_readme_has_the_standard_sections() {
		$text = $this->readme();

		foreach ( array( '== Description ==', '== Installation ==', '== Frequently Asked Questions ==', '== Changelog ==', '== Upgrade Notice ==' ) as $section ) {
			$this->assertStringContainsString( $section, $text, $section );
		}
		$this->assertStringContainsString( '= ' . NH_SEO_VERSION . ' =', $text, 'the current version has a changelog entry' );
	}

	public function test_the_readme_documents_privacy_and_uninstall_behaviour() {
		$text = $this->readme();

		$this->assertStringContainsString( 'Does the plugin send any data anywhere?', $text );
		$this->assertStringContainsString( 'No. It makes no remote requests', $text );
		$this->assertStringContainsString( 'What happens when I delete the plugin?', $text );
		$this->assertStringContainsString( 'Your posts, pages, terms, users and every piece of editorial data', $text );
	}

	public function test_the_readme_documents_every_data_key_of_the_contract() {
		$text = $this->readme();

		foreach ( array( 'nh_summary', 'nh_takeaways', 'nh_faq', 'nh_sources', 'nh_content_updated', 'nh_topic_cluster', 'nh_primary_cluster', 'nh_pillar_id', 'nh_local_business', 'nh_keywords', 'nh_hub_term' ) as $key ) {
			$this->assertStringContainsString( '`' . $key . '`', $text, $key );
		}
	}

	public function test_the_readme_holds_no_business_data() {
		$text = $this->readme();

		$this->assertDoesNotMatchRegularExpression( '/\+?\d[\d ().-]{8,}\d/', preg_replace( '/\d{4}-\d{2}-\d{2}/', '', $text ), 'no phone numbers' );
		$this->assertDoesNotMatchRegularExpression( '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', $text, 'no email addresses' );
	}
}
