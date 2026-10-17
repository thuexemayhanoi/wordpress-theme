<?php
/**
 * Tests for the canonical primary-category and breadcrumb-trail helpers.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * Canonical trail and primary-category tests.
 */
class NH_Test_Shared_Trail extends NH_Unit_Test_Case {

	/** @var int */
	private $parent;
	/** @var int */
	private $child;
	/** @var int */
	private $grandchild;

	public function set_up() {
		parent::set_up();
		$this->use_permalinks( '/%category%/%postname%/' );
		$this->parent     = self::factory()->category->create(
			array(
				'name' => 'Rental',
				'slug' => 'rental',
			)
		);
		$this->child      = self::factory()->category->create(
			array(
				'name'   => 'Scooters',
				'slug'   => 'scooters',
				'parent' => $this->parent,
			)
		);
		$this->grandchild = self::factory()->category->create(
			array(
				'name'   => 'Automatic',
				'slug'   => 'automatic',
				'parent' => $this->child,
			)
		);
	}

	public function test_primary_category_prefers_deepest_and_ignores_uncategorized() {
		$post_id = self::factory()->post->create( array( 'post_category' => array( get_option( 'default_category' ), $this->parent, $this->grandchild ) ) );
		$primary = nh_seo_get_primary_category( $post_id );
		$this->assertSame( $this->grandchild, $primary->term_id );
	}

	public function test_primary_category_uses_seo_plugin_choice_when_assigned() {
		$post_id = self::factory()->post->create( array( 'post_category' => array( $this->parent, $this->grandchild ) ) );
		update_post_meta( $post_id, '_yoast_wpseo_primary_category', $this->parent );
		$this->assertSame( $this->parent, nh_seo_get_primary_category( $post_id )->term_id );

		// A stale primary that is no longer assigned to the post is ignored.
		update_post_meta( $post_id, '_yoast_wpseo_primary_category', $this->child );
		$this->assertSame( $this->grandchild, nh_seo_get_primary_category( $post_id )->term_id );

		delete_post_meta( $post_id, '_yoast_wpseo_primary_category' );
		update_post_meta( $post_id, 'rank_math_primary_category', $this->parent );
		$this->assertSame( $this->parent, nh_seo_get_primary_category( $post_id )->term_id );
	}

	public function test_primary_category_uses_only_uncategorized_when_nothing_else() {
		$post_id = self::factory()->post->create();
		$this->assertSame( (int) get_option( 'default_category' ), nh_seo_get_primary_category( $post_id )->term_id );
	}

	public function test_primary_category_is_null_for_post_without_categories() {
		$post_id = self::factory()->post->create();
		wp_set_object_terms( $post_id, array(), 'category' );
		wp_delete_object_term_relationships( $post_id, 'category' );
		$this->assertNull( nh_seo_get_primary_category( $post_id ) );
	}

	public function test_trail_for_post_goes_parent_child_grandchild_then_post() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'    => 'Which scooter?',
				'post_category' => array( $this->grandchild ),
			)
		);
		$this->go_to( get_permalink( $post_id ) );

		$trail = nh_seo_breadcrumb_trail();
		$this->assertSame( array( 'Home', 'Rental', 'Scooters', 'Automatic', 'Which scooter?' ), wp_list_pluck( $trail, 'name' ) );
		$this->assertSame( home_url( '/' ), $trail[0]['url'] );
		$this->assertSame( get_category_link( $this->child ), $trail[2]['url'] );
		$this->assertSame( get_permalink( $post_id ), end( $trail )['url'] );
	}

	public function test_trail_for_category_archive_includes_ancestors() {
		$this->go_to( get_category_link( $this->grandchild ) );
		$this->assertSame( array( 'Home', 'Rental', 'Scooters', 'Automatic' ), wp_list_pluck( nh_seo_breadcrumb_trail(), 'name' ) );
	}

	/**
	 * URL of the last crumb of the current page.
	 *
	 * @return string
	 */
	private function last_url() {
		$trail = nh_seo_breadcrumb_trail();
		$last  = end( $trail );
		return is_array( $last ) ? $last['url'] : '';
	}

	public function test_archive_crumbs_name_the_archive_and_never_the_visitors_query_string() {
		update_option( 'posts_per_page', 1 );
		self::factory()->post->create( array( 'post_date' => '2024-03-08 10:00:00' ) );
		$post_id = self::factory()->post->create( array( 'post_date' => '2024-03-09 10:00:00' ) );
		$tag     = self::factory()->term->create(
			array(
				'taxonomy' => 'post_tag',
				'name'     => 'Handy',
			)
		);
		wp_set_post_terms( $post_id, array( $tag ), 'post_tag' );

		$archives = array(
			'year'     => get_year_link( 2024 ),
			'month'    => get_month_link( 2024, 3 ),
			'day'      => get_day_link( 2024, 3, 9 ),
			'category' => get_category_link( $this->child ),
			'tag'      => get_tag_link( $tag ),
		);
		foreach ( $archives as $label => $url ) {
			$tracked = add_query_arg(
				array(
					'utm_source' => 'newsletter',
					'ref'        => 'abc',
				),
				$url
			);
			$this->go_to( $tracked );

			$last = $this->last_url();
			$this->assertSame( $url, $last, $label . ': a campaign link is the same page as the archive' );
			$this->assertStringNotContainsString( 'utm_source', $last, $label );
			$this->assertStringNotContainsString( '&', $last, $label . ': nothing HTML-escaped ends up in the data' );
		}

		$this->go_to( get_month_link( 2024, 3 ) . 'page/2/?utm_medium=mail' );
		$this->assertSame( get_month_link( 2024, 3 ), $this->last_url(), 'page 2 names the first page' );
	}

	public function test_the_search_crumb_is_the_address_the_visitor_reached() {
		update_option( 'posts_per_page', 1 );
		self::factory()->post->create( array( 'post_title' => 'Handy scooter one' ) );
		self::factory()->post->create( array( 'post_title' => 'Handy scooter two' ) );
		$plain  = home_url( '/?s=handy' );
		$pretty = get_search_link( 'handy' );
		$this->assertNotSame( $plain, $pretty, 'With pretty permalinks the two addresses of the same results differ.' );

		// Both addresses show the same results, and a BreadcrumbList names the one that was requested: a crawler that
		// reads /?s=handy must not be told that the page is /search/handy/.
		$cases = array(
			'query argument'           => array( $plain, $plain ),
			'pretty address'           => array( $pretty, $pretty ),
			'query argument, tracking' => array( $plain . '&utm_source=newsletter', $plain ),
			'pretty address, tracking' => array( $pretty . '?utm_source=newsletter', $pretty ),
			'query argument, page 2'   => array( $plain . '&paged=2', $plain ),
			'pretty address, page 2'   => array( $pretty . 'page/2/', $pretty ),
		);
		foreach ( $cases as $label => $case ) {
			$this->go_to( $case[0] );
			$this->assertTrue( is_search(), $label );
			$this->assertSame( $case[1], $this->last_url(), $label );
		}
	}

	public function test_trail_for_page_includes_published_ancestors() {
		$root = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Guides',
			)
		);
		$mid  = self::factory()->post->create(
			array(
				'post_type'     => 'page',
				'post_title'    => 'Visas',
				'post_parent'   => $root,
				'post_password' => 'secret',
			)
		);
		$leaf = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Tourist visa',
				'post_parent' => $mid,
			)
		);
		$this->go_to( get_permalink( $leaf ) );
		$this->assertSame( array( 'Home', 'Guides', 'Visas', 'Tourist visa' ), wp_list_pluck( nh_seo_breadcrumb_trail(), 'name' ) );
	}

	public function test_trail_is_empty_on_front_page_and_404() {
		$this->go_to( home_url( '/' ) );
		$this->assertSame( array(), nh_seo_breadcrumb_trail() );

		$this->go_to( home_url( '/definitely-missing/' ) );
		$this->assertSame( array(), nh_seo_breadcrumb_trail() );
	}

	public function test_faq_visibility_follows_theme_support_and_fallback_setting() {
		$post_id = self::factory()->post->create();
		update_post_meta(
			$post_id,
			NH_SEO_Keys::META_FAQ,
			array(
				array(
					'question' => 'Q?',
					'answer'   => 'A.',
				),
			)
		);

		remove_theme_support( 'nguyen-ha-companion' );
		update_option( NH_SEO_Keys::OPTION_SETTINGS, array( 'fallback_render' => 1 ) );
		$this->assertTrue( nh_seo_faq_is_visible( $post_id ) );

		update_option( NH_SEO_Keys::OPTION_SETTINGS, array( 'fallback_render' => 0 ) );
		$this->assertFalse( nh_seo_faq_is_visible( $post_id ) );

		add_theme_support( 'nguyen-ha-companion', array( 'faq' => true ) );
		$this->assertTrue( nh_seo_faq_is_visible( $post_id ) );
		remove_theme_support( 'nguyen-ha-companion' );

		delete_post_meta( $post_id, NH_SEO_Keys::META_FAQ );
		$this->assertFalse( nh_seo_faq_is_visible( $post_id ) );
	}

	public function test_a_template_that_hides_the_faq_can_say_so_through_a_filter() {
		$shown  = self::factory()->post->create();
		$hidden = self::factory()->post->create();
		$empty  = self::factory()->post->create();
		foreach ( array( $shown, $hidden ) as $post_id ) {
			update_post_meta(
				$post_id,
				NH_SEO_Keys::META_FAQ,
				array(
					array(
						'question' => 'Q?',
						'answer'   => 'A.',
					),
				)
			);
		}
		add_theme_support( 'nguyen-ha-companion', array( 'faq' => true ) );

		$received = array();
		$veto     = static function ( $visible, $post_id ) use ( $hidden, &$received ) {
			$received[] = array( $visible, $post_id );
			return $post_id === $hidden ? false : $visible;
		};
		add_filter( 'nh_seo_faq_is_visible', $veto, 10, 2 );

		$this->assertTrue( nh_seo_faq_is_visible( $shown ) );
		$this->assertFalse( nh_seo_faq_is_visible( $hidden ), 'the filter withdraws the claim' );
		$this->assertFalse( nh_seo_faq_is_visible( $empty ), 'a post without questions never reaches the filter' );
		$this->assertSame( array( array( true, $shown ), array( true, $hidden ) ), $received );

		// The filter cannot make questions appear that the post does not have.
		add_filter( 'nh_seo_faq_is_visible', '__return_true' );
		$this->assertFalse( nh_seo_faq_is_visible( $empty ) );
		remove_theme_support( 'nguyen-ha-companion' );
	}
}
