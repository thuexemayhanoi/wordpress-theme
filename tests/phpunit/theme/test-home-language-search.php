<?php
/**
 * Regression: one native VI/EN switcher in the header on every frontend route.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

/**
 * Header language placement without changing VI/EN Core routing.
 */
class NH_Test_Home_Language_Search extends NH_Unit_Test_Case {

	/** Plugin availability, not the current template, controls placement. */
	public function test_home_placement_follows_plugin_availability() {
		$this->go_to( home_url( '/' ) );
		$markup = nh_editorial_header_language_switcher_markup();
		$this->assertIsString( $markup );
		$active = class_exists( 'NH_VIEN_Switcher' ) && NH_VIEN_Switcher::alternates();
		$this->assertSame( ! (bool) $active, apply_filters( 'nh_vien_switcher_auto_placement', true ) );
		if ( $active ) {
			$this->assertSame( 1, substr_count( $markup, 'class="nh-vien-switcher"' ) );
		}
	}

	/** English homepage and ordinary page use the same header placement. */
	public function test_english_and_ordinary_pages_do_not_restore_a_duplicate_top_bar() {
		$page  = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_name'   => 'en',
				'post_title'  => 'English home',
				'post_status' => 'publish',
			)
		);
		$other = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_name'   => 'ordinary-page',
				'post_status' => 'publish',
			)
		);
		foreach ( array( $page, $other ) as $id ) {
			$this->go_to( get_permalink( $id ) );
			$active = class_exists( 'NH_VIEN_Switcher' ) && NH_VIEN_Switcher::alternates();
			$this->assertSame( ! (bool) $active, apply_filters( 'nh_vien_switcher_auto_placement', true ) );
			$this->assertIsString( nh_editorial_header_language_switcher_markup() );
		}
	}

	/** A disabled or absent plugin must not cause a broken blank control. */
	public function test_header_markup_is_safe_without_the_plugin() {
		$this->go_to( home_url( '/' ) );
		$this->assertIsString( nh_editorial_header_language_switcher_markup() );
	}
}
