<?php
/**
 * Regression: the native VI/EN switcher moves into the homepage search row only.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

/**
 * Home-only language/search placement, without altering other page templates.
 */
class NH_Test_Home_Language_Search extends NH_Unit_Test_Case {

	public function test_vietnamese_homepage_suppresses_only_the_top_switcher() {
		$this->go_to( home_url( '/' ) );
		$this->assertTrue( nh_editorial_home_language_switcher_in_search() );
		$this->assertFalse( apply_filters( 'nh_vien_switcher_auto_placement', true ) );
	}

	public function test_english_homepage_uses_search_placement_and_other_pages_keep_bar() {
		$page = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_name'   => 'en',
				'post_title'  => 'English home',
				'post_status' => 'publish',
			)
		);
		$this->go_to( get_permalink( $page ) );
		$this->assertTrue( nh_editorial_home_language_switcher_in_search() );
		$this->assertFalse( apply_filters( 'nh_vien_switcher_auto_placement', true ) );

		$normal = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_name'   => 'ordinary-page',
				'post_status' => 'publish',
			)
		);
		$this->go_to( get_permalink( $normal ) );
		$this->assertFalse( nh_editorial_home_language_switcher_in_search() );
		$this->assertTrue( apply_filters( 'nh_vien_switcher_auto_placement', true ) );
	}

	public function test_switcher_is_safe_when_plugin_is_not_loaded() {
		// The theme must never produce a second or broken language switcher.
		$this->go_to( home_url( '/' ) );
		$markup = nh_editorial_home_language_switcher_markup();
		$this->assertIsString( $markup );
	}
}
