<?php
/**
 * Base test case with helpers shared by theme and plugin integration tests.
 *
 * @package nguyen-ha-editorial
 */

/**
 * WP_UnitTestCase plus helpers for pretty permalinks and clean theme state.
 */
abstract class NH_Unit_Test_Case extends WP_UnitTestCase {

	/**
	 * Enable pretty permalinks properly: the test suite registers taxonomies before the permalink
	 * structure exists, so rewrite tags such as %category% must be registered again.
	 *
	 * @param string $structure Permalink structure.
	 * @return void
	 */
	protected function use_permalinks( $structure = '/%category%/%postname%/' ) {
		global $wp_rewrite;
		$this->set_permalink_structure( $structure );
		create_initial_taxonomies();
		$wp_rewrite->flush_rules();
	}
}
