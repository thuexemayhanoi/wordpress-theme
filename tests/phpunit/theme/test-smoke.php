<?php
/**
 * Harness smoke test: WordPress boots with the theme active and no PHP errors.
 *
 * @package nguyen-ha-editorial
 */

/**
 * Smoke tests for the harness itself.
 */
class NH_Test_Smoke extends WP_UnitTestCase {

	public function test_theme_is_active() {
		$this->assertSame( 'nguyen-ha-editorial', get_stylesheet() );
		$this->assertSame( 'nguyen-ha-editorial', get_template() );
	}

	public function test_theme_header_is_readable() {
		$theme = wp_get_theme( 'nguyen-ha-editorial' );
		$this->assertTrue( $theme->exists() );
		$this->assertNotEmpty( $theme->get( 'Version' ) );
	}
}
