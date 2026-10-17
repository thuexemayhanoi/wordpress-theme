<?php
/**
 * Whose trail the visible breadcrumb shows: the one the SEO plugin prints as BreadcrumbList (Yoast SEO, read through
 * its surface) or the one the theme builds. Found by the browser suite against the real Yoast SEO in CI: a fresh Yoast
 * lists no category for a post and says "You searched for", while the theme showed Home > category > post and "Search
 * results for", so the page and the structured data described two different trails.
 *
 * SIMULATION: Yoast SEO is not installed here and was never executed by these tests. They use what the theme documents
 * for it: the shape of YoastSEO()->meta->for_current_page()->breadcrumbs (a fake object with that surface) and the
 * filter nh_editorial_seo_plugin_crumbs. The real plugin is exercised by the "seo-plugins" job of the CI workflow.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

/**
 * Source of the visible breadcrumb.
 */
class NH_Test_Breadcrumb_Source extends NH_Unit_Test_Case {

	/** @var int */
	private $category;
	/** @var int */
	private $post;

	public function set_up() {
		parent::set_up();
		$this->use_permalinks();
		$this->category = self::factory()->category->create( array( 'name' => 'Scooters' ) );
		$this->post     = self::factory()->post->create(
			array(
				'post_title'    => 'Choosing a scooter',
				'post_category' => array( $this->category ),
			)
		);
	}

	public function tear_down() {
		remove_all_filters( 'nh_seo_detected_seo_plugin' );
		remove_all_filters( 'nh_seo_schema_mode' );
		remove_all_filters( 'nh_editorial_seo_plugin_crumbs' );
		remove_all_filters( 'nh_editorial_breadcrumbs_follow_seo_plugin' );
		parent::tear_down();
	}

	/**
	 * What Yoast SEO 28 lists for a post when no taxonomy is chosen for breadcrumbs (the default).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function yoast_crumbs() {
		return array(
			array(
				'url'  => home_url( '/' ),
				'text' => 'Home',
				'id'   => 0,
			),
			array(
				'url'  => get_permalink( $this->post ),
				'text' => 'Choosing a scooter',
				'id'   => $this->post,
			),
		);
	}

	/**
	 * Make the SEO plugin the owner of the BreadcrumbList and hand over its crumbs.
	 *
	 * @param array|null $crumbs Crumbs the surface returns.
	 * @return void
	 */
	private function yoast_prints_the_list( $crumbs ) {
		add_filter(
			'nh_seo_detected_seo_plugin',
			static function () {
				return 'yoast';
			}
		);
		add_filter(
			'nh_editorial_seo_plugin_crumbs',
			static function () use ( $crumbs ) {
				return $crumbs;
			}
		);
	}

	/**
	 * A stand-in for the object YoastSEO() returns.
	 *
	 * @param callable $for_current_page Behaviour of ->meta->for_current_page().
	 * @return object
	 */
	private function fake_yoast( $for_current_page ) {
		$meta = new class( $for_current_page ) {
			/** @var callable */
			private $callback;

			public function __construct( $callback ) {
				$this->callback = $callback;
			}

			public function for_current_page() {
				return call_user_func( $this->callback );
			}
		};
		return (object) array( 'meta' => $meta );
	}

	/**
	 * An object that serves its values only through __get(), without __isset(): how Yoast SEO serves its surfaces
	 * and the values of a page. (A real property would be found by property_exists(), which is not the case here.)
	 *
	 * @param array $values Name => value.
	 * @return object
	 */
	private function magic_object( array $values ) {
		return new class( $values ) {
			/** @var array */
			private $values;

			public function __construct( array $values ) {
				$this->values = $values;
			}

			public function __get( $name ) {
				return isset( $this->values[ $name ] ) ? $this->values[ $name ] : null;
			}
		};
	}

	public function test_the_page_shows_the_trail_that_yoast_prints_as_breadcrumblist() {
		$this->go_to( get_permalink( $this->post ) );
		$own = nh_editorial_breadcrumb_items();
		$this->assertSame( array( 'Home', 'Scooters', 'Choosing a scooter' ), wp_list_pluck( $own, 'name' ), 'Without Yoast the theme builds Home > category > post.' );

		$this->yoast_prints_the_list( $this->yoast_crumbs() );
		$items = nh_editorial_breadcrumb_items();
		$this->assertSame(
			array(
				array(
					'name' => 'Home',
					'url'  => home_url( '/' ),
				),
				array(
					'name' => 'Choosing a scooter',
					'url'  => get_permalink( $this->post ),
				),
			),
			$items
		);

		ob_start();
		nh_editorial_breadcrumbs();
		$html = ob_get_clean();
		$this->assertSame( 2, substr_count( $html, '<li ' ), 'Home and the current item.' );
		$this->assertStringNotContainsString( 'Scooters', $html, 'The category Yoast does not list is not shown.' );
		$this->assertStringContainsString( 'aria-current="page"', $html );
	}

	public function test_crumbs_a_plugin_hides_from_its_structured_data_are_hidden_here_too() {
		$crumbs = $this->yoast_crumbs();
		array_splice(
			$crumbs,
			1,
			0,
			array(
				array(
					'url'            => home_url( '/blog/' ),
					'text'           => 'Blog',
					'hide_in_schema' => true,
				),
			)
		);
		$this->yoast_prints_the_list( $crumbs );
		$this->go_to( get_permalink( $this->post ) );
		$this->assertSame( array( 'Home', 'Choosing a scooter' ), wp_list_pluck( nh_editorial_breadcrumb_items(), 'name' ) );
	}

	public function test_names_are_plain_text_and_unusable_crumbs_are_skipped() {
		$this->yoast_prints_the_list(
			array(
				array(
					'url'  => home_url( '/' ),
					'text' => 'Home',
				),
				'not a crumb',
				array( 'url' => home_url( '/no-text/' ) ),
				array(
					'url'  => home_url( '/array/' ),
					'text' => array( 'x' ),
				),
				array(
					'url'  => home_url( '/blank/' ),
					'text' => '   ',
				),
				array(
					'url'  => get_permalink( $this->post ),
					'text' => 'Fast &amp; <b>fun</b> &#039;rides&#039;',
				),
			)
		);
		$this->go_to( get_permalink( $this->post ) );
		$items = nh_editorial_breadcrumb_items();
		$this->assertSame( array( 'Home', "Fast & fun 'rides'" ), wp_list_pluck( $items, 'name' ) );

		ob_start();
		nh_editorial_breadcrumbs();
		$html = ob_get_clean();
		$this->assertStringContainsString( 'Fast &amp; fun &#039;rides&#039;', $html, 'Escaped once, on output.' );
		$this->assertStringNotContainsString( '<b>', $html );
	}

	public function test_a_crumb_without_an_address_is_text_not_a_link_to_nowhere() {
		$this->yoast_prints_the_list(
			array(
				array(
					'url'  => home_url( '/' ),
					'text' => 'Home',
				),
				array(
					'url'  => '',
					'text' => 'Section without a page',
				),
				array(
					'url'  => get_permalink( $this->post ),
					'text' => 'Choosing a scooter',
				),
			)
		);
		$this->go_to( get_permalink( $this->post ) );

		ob_start();
		nh_editorial_breadcrumbs();
		$html = ob_get_clean();
		$this->assertSame( 3, substr_count( $html, '<li ' ) );
		$this->assertSame( 1, substr_count( $html, '<a ' ), 'Only Home is a link.' );
		$this->assertStringNotContainsString( 'href=""', $html );
		$this->assertStringContainsString( '<span>Section without a page</span>', $html );
	}

	public function test_a_search_page_shows_the_wording_of_the_plugin() {
		$crumbs = array(
			array(
				'url'  => home_url( '/' ),
				'text' => 'Home',
			),
			array(
				'url'  => home_url( '/?s=scooter' ),
				'text' => 'You searched for scooter',
			),
		);
		$this->yoast_prints_the_list( $crumbs );
		$this->go_to( home_url( '/?s=scooter' ) );
		$this->assertSame( array( 'Home', 'You searched for scooter' ), wp_list_pluck( nh_editorial_breadcrumb_items(), 'name' ) );
	}

	public function test_without_a_usable_answer_the_theme_builds_the_trail() {
		$this->go_to( get_permalink( $this->post ) );
		$own = nh_editorial_breadcrumb_items();

		foreach ( array( null, false, 'text', array(), array( 'not a crumb' ), array( array( 'text' => '' ) ) ) as $answer ) {
			remove_all_filters( 'nh_editorial_seo_plugin_crumbs' );
			$this->yoast_prints_the_list( $answer );
			$this->assertSame( $own, nh_editorial_breadcrumb_items(), 'Unusable crumbs: ' . wp_json_encode( $answer ) );
		}
	}

	public function test_the_companion_plugin_in_full_mode_keeps_the_trail_it_serialises() {
		// An SEO plugin is detected, but the owner chose "full": the companion plugin prints BreadcrumbList itself.
		$this->yoast_prints_the_list( $this->yoast_crumbs() );
		add_filter(
			'nh_seo_schema_mode',
			static function () {
				return 'full';
			}
		);
		$this->go_to( get_permalink( $this->post ) );
		$this->assertSame( nh_seo_breadcrumb_trail(), nh_editorial_breadcrumb_items() );
		$this->assertSame( array( 'Home', 'Scooters', 'Choosing a scooter' ), wp_list_pluck( nh_editorial_breadcrumb_items(), 'name' ) );
	}

	public function test_the_theme_can_be_told_to_keep_its_own_trail() {
		$this->yoast_prints_the_list( $this->yoast_crumbs() );
		add_filter( 'nh_editorial_breadcrumbs_follow_seo_plugin', '__return_false' );
		$this->go_to( get_permalink( $this->post ) );
		$this->assertSame( array( 'Home', 'Scooters', 'Choosing a scooter' ), wp_list_pluck( nh_editorial_breadcrumb_items(), 'name' ) );
	}

	public function test_the_front_page_and_a_missing_page_still_show_no_breadcrumb() {
		$this->yoast_prints_the_list( $this->yoast_crumbs() );
		$this->go_to( home_url( '/' ) );
		$this->assertSame( array(), nh_editorial_breadcrumb_items() );
		$this->go_to( home_url( '/definitely-missing/' ) );
		$this->assertSame( array(), nh_editorial_breadcrumb_items() );
	}

	public function test_the_item_filter_still_has_the_last_word() {
		$this->yoast_prints_the_list( $this->yoast_crumbs() );
		add_filter(
			'nh_editorial_breadcrumb_items',
			static function ( $items ) {
				$items[0]['name'] = 'Start';
				return $items;
			}
		);
		$this->go_to( get_permalink( $this->post ) );
		$this->assertSame( 'Start', nh_editorial_breadcrumb_items()[0]['name'] );
	}

	public function test_the_reader_of_the_yoast_surface_copes_with_what_it_may_meet() {
		$this->assertFalse( function_exists( 'YoastSEO' ), 'These tests run without Yoast SEO.' );
		$this->assertNull( nh_editorial_yoast_breadcrumbs(), 'No Yoast, no crumbs.' );

		$crumbs = $this->yoast_crumbs();
		$page   = (object) array( 'breadcrumbs' => $crumbs );
		$this->assertSame(
			$crumbs,
			nh_editorial_yoast_breadcrumbs(
				$this->fake_yoast(
					static function () use ( $page ) {
						return $page;
					}
				)
			)
		);

		$answers = array(
			'no page'              => static function () {
				return false;
			},
			'page without crumbs'  => static function () {
				return (object) array();
			},
			'crumbs that are text' => static function () {
				return (object) array( 'breadcrumbs' => 'Home' );
			},
			'a surface that fails' => static function () {
				throw new RuntimeException( 'Yoast could not describe this page.' );
			},
		);
		foreach ( $answers as $label => $answer ) {
			$this->assertNull( nh_editorial_yoast_breadcrumbs( $this->fake_yoast( $answer ) ), $label );
		}

		// The surface is a class with for_current_page(); the page a Meta object that gives "breadcrumbs" through __get().
		$surface = new class( $this->magic_object( array( 'breadcrumbs' => $crumbs ) ) ) {
			/** @var object */
			private $page;

			public function __construct( $page ) {
				$this->page = $page;
			}

			public function for_current_page() {
				return $this->page;
			}
		};
		$this->assertSame( $crumbs, nh_editorial_yoast_breadcrumbs( $this->magic_object( array( 'meta' => $surface ) ) ), 'Magic getters without __isset().' );

		$this->assertNull( nh_editorial_yoast_breadcrumbs( (object) array() ), 'An older Yoast without the meta surface.' );
		$this->assertNull( nh_editorial_yoast_breadcrumbs( (object) array( 'meta' => new stdClass() ) ), 'A meta surface without for_current_page().' );
		$this->assertNull( nh_editorial_yoast_breadcrumbs( 'not an object' ) );
	}
}
