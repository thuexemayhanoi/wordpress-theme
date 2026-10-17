<?php
/**
 * Tests for the canonical primary-category and breadcrumb-trail helpers.
 *
 * @package nguyen-ha-seo-companion
 */

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
}
