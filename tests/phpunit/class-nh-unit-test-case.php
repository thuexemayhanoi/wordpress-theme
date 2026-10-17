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
	 * WP_UnitTestCase unregisters every registered meta key after each test, so the plugin's taxonomy and
	 * meta (normally registered once on `init`) would only exist for the first test of a run. Register them
	 * again before every test to keep tests independent of their execution order.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		if ( class_exists( 'NH_SEO_Data_Model' ) ) {
			NH_SEO_Data_Model::register();
		}
	}

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
