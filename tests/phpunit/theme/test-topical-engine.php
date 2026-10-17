<?php
/**
 * Topical authority engine: cache, silo/pillar/hub, previous/next, related posts, breadcrumbs, menus.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

/**
 * Versioned fragment cache.
 */
class NH_Test_Cache extends NH_Unit_Test_Case {

	public function set_up() {
		parent::set_up();
		remove_action( 'shutdown', 'nh_editorial_cache_bump_now', 5 );
	}

	public function tear_down() {
		remove_action( 'shutdown', 'nh_editorial_cache_bump_now', 5 );
		parent::tear_down();
	}

	private function bump_scheduled() {
		return false !== has_action( 'shutdown', 'nh_editorial_cache_bump_now' );
	}

	public function test_fragments_are_computed_once_until_the_version_changes() {
		$calls = 0;
		$cb    = static function () use ( &$calls ) {
			++$calls;
			return array( 'n' => $calls );
		};
		$this->assertSame( array( 'n' => 1 ), nh_editorial_cache_remember( 'unit-test', $cb ) );
		$this->assertSame( array( 'n' => 1 ), nh_editorial_cache_remember( 'unit-test', $cb ) );
		$this->assertSame( 1, $calls );

		nh_editorial_cache_bump_now();
		$this->assertSame( array( 'n' => 2 ), nh_editorial_cache_remember( 'unit-test', $cb ) );
	}

	public function test_false_and_empty_results_are_cached_too() {
		$calls = 0;
		$cb    = static function () use ( &$calls ) {
			++$calls;
			return array();
		};
		nh_editorial_cache_remember( 'empty', $cb );
		nh_editorial_cache_remember( 'empty', $cb );
		$this->assertSame( 1, $calls );
	}

	public function test_the_cache_can_be_switched_off() {
		add_filter( 'nh_editorial_cache_enabled', '__return_false' );
		$calls = 0;
		$cb    = static function () use ( &$calls ) {
			return ++$calls;
		};
		nh_editorial_cache_remember( 'off', $cb );
		nh_editorial_cache_remember( 'off', $cb );
		$this->assertSame( 2, $calls );
		remove_filter( 'nh_editorial_cache_enabled', '__return_false' );
	}

	public function test_new_version_each_bump() {
		$before = nh_editorial_cache_version();
		nh_editorial_cache_bump_now();
		$this->assertNotSame( $before, nh_editorial_cache_version() );
	}

	public function test_publishing_and_unpublishing_request_an_invalidation() {
		$id = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		remove_action( 'shutdown', 'nh_editorial_cache_bump_now', 5 );

		wp_update_post(
			array(
				'ID'         => $id,
				'post_title' => 'Still a draft',
			)
		);
		$this->assertFalse( $this->bump_scheduled(), 'editing a draft changes nothing public' );

		wp_publish_post( $id );
		$this->assertTrue( $this->bump_scheduled() );

		remove_action( 'shutdown', 'nh_editorial_cache_bump_now', 5 );
		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'draft',
			)
		);
		$this->assertTrue( $this->bump_scheduled(), 'unpublishing changes lists' );
	}

	public function test_structural_changes_request_an_invalidation() {
		$cat = self::factory()->category->create();
		$id  = self::factory()->post->create();

		remove_action( 'shutdown', 'nh_editorial_cache_bump_now', 5 );
		wp_set_post_categories( $id, array( $cat ) );
		$this->assertTrue( $this->bump_scheduled(), 'term assignment' );

		remove_action( 'shutdown', 'nh_editorial_cache_bump_now', 5 );
		wp_update_term( $cat, 'category', array( 'name' => 'Renamed' ) );
		$this->assertTrue( $this->bump_scheduled(), 'term rename' );

		remove_action( 'shutdown', 'nh_editorial_cache_bump_now', 5 );
		update_post_meta( $id, 'nh_pillar_id', 5 );
		$this->assertTrue( $this->bump_scheduled(), 'pillar mapping' );

		remove_action( 'shutdown', 'nh_editorial_cache_bump_now', 5 );
		update_term_meta( $cat, 'nh_pillar_id', 5 );
		$this->assertTrue( $this->bump_scheduled(), 'term pillar mapping' );

		remove_action( 'shutdown', 'nh_editorial_cache_bump_now', 5 );
		update_option( 'sticky_posts', array( $id ) );
		$this->assertTrue( $this->bump_scheduled(), 'sticky posts' );

		remove_action( 'shutdown', 'nh_editorial_cache_bump_now', 5 );
		update_post_meta( $id, '_some_unrelated_meta', 'x' );
		$this->assertFalse( $this->bump_scheduled(), 'unrelated meta must not flush the cache' );
	}

	public function test_changing_the_publish_date_of_a_live_post_invalidates() {
		$id = self::factory()->post->create();
		remove_action( 'shutdown', 'nh_editorial_cache_bump_now', 5 );
		wp_update_post(
			array(
				'ID'            => $id,
				'post_date'     => gmdate( 'Y-m-d H:i:s', strtotime( '-3 days' ) ),
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', strtotime( '-3 days' ) ),
			)
		);
		$this->assertTrue( $this->bump_scheduled() );
	}

	public function test_many_triggers_cost_a_single_scheduled_write() {
		for ( $i = 0; $i < 5; $i++ ) {
			nh_editorial_cache_bump();
		}
		global $wp_filter;
		$registered = array_keys( $wp_filter['shutdown']->callbacks[5] );
		$matches    = array_filter(
			$registered,
			static function ( $key ) {
				return false !== strpos( $key, 'nh_editorial_cache_bump_now' );
			}
		);
		$this->assertCount( 1, $matches );
	}
}

/**
 * Silo, Pillar, Hub, previous/next and hub listings.
 */
class NH_Test_Topics extends NH_Unit_Test_Case {

	/** @var int */
	private $top;
	/** @var int */
	private $mid;
	/** @var int */
	private $leaf;

	public function set_up() {
		parent::set_up();
		$this->ensure_cluster_taxonomy();
		$this->use_permalinks();
		$this->top  = self::factory()->category->create( array( 'name' => 'Top' ) );
		$this->mid  = self::factory()->category->create(
			array(
				'name'   => 'Mid',
				'parent' => $this->top,
			)
		);
		$this->leaf = self::factory()->category->create(
			array(
				'name'   => 'Leaf',
				'parent' => $this->mid,
			)
		);
		nh_editorial_cache_bump_now();
	}

	public function test_primary_category_matches_the_plugin_algorithm_in_every_scenario() {
		if ( ! function_exists( 'nh_seo_get_primary_category' ) ) {
			$this->markTestSkipped( 'Companion plugin not loaded.' );
		}

		$default   = (int) get_option( 'default_category' );
		$scenarios = array(
			'deepest wins'          => array( array( $this->top, $this->leaf, $this->mid ), array() ),
			'uncategorized ignored' => array( array( $default, $this->top ), array() ),
			'uncategorized alone'   => array( array(), array() ),
			'yoast primary'         => array( array( $this->top, $this->leaf ), array( '_yoast_wpseo_primary_category' => $this->top ) ),
			'rank math primary'     => array( array( $this->top, $this->leaf ), array( 'rank_math_primary_category' => $this->mid ) ),
			'stale primary ignored' => array( array( $this->leaf ), array( '_yoast_wpseo_primary_category' => $this->top ) ),
		);
		foreach ( $scenarios as $label => $scenario ) {
			$post_id = self::factory()->post->create( array( 'post_category' => $scenario[0] ) );
			foreach ( $scenario[1] as $key => $value ) {
				update_post_meta( $post_id, $key, $value );
			}
			$theme  = nh_editorial_primary_category( $post_id );
			$plugin = nh_seo_get_primary_category( $post_id );
			$this->assertSame( $plugin ? $plugin->term_id : null, $theme ? $theme->term_id : null, $label );
		}
	}

	public function test_silo_prefers_cluster_then_alias_then_category() {
		// With the companion plugin active an alias is turned into a real term; this test covers the
		// theme's own alias fallback, which is what a site without the plugin relies on.
		add_filter( 'nh_seo_cluster_can_create_term', '__return_false' );

		$post_id = self::factory()->post->create( array( 'post_category' => array( $this->leaf ) ) );
		$silo    = nh_editorial_get_silo( $post_id );
		$this->assertSame( 'category:' . $this->leaf, $silo['id'] );
		$this->assertSame( 'Leaf', $silo['label'] );

		update_post_meta( $post_id, 'nh_topic_cluster', 'scooter-rental-basics' );
		$silo = nh_editorial_get_silo( $post_id );
		$this->assertSame( 'meta', $silo['type'] );
		$this->assertSame( 'Scooter rental basics', $silo['label'] );

		$cluster = self::factory()->term->create(
			array(
				'taxonomy' => 'topic_cluster',
				'name'     => 'Real cluster',
			)
		);
		wp_set_object_terms( $post_id, array( $cluster ), 'topic_cluster' );
		$silo = nh_editorial_get_silo( $post_id );
		$this->assertSame( 'topic_cluster:' . $cluster, $silo['id'] );
		$this->assertSame( 'Real cluster', $silo['label'] );

		$orphan = self::factory()->post->create();
		wp_delete_object_term_relationships( $orphan, 'category' );
		$this->assertNull( nh_editorial_get_silo( $orphan ) );
	}

	public function test_alias_label_humanizes_slugs_only() {
		$this->assertSame( 'Scooter basics', nh_editorial_alias_label( 'scooter-basics' ) );
		$this->assertSame( 'First time riders', nh_editorial_alias_label( 'first_time_riders' ) );
		$this->assertSame( 'Already Nice Name', nh_editorial_alias_label( 'Already Nice Name' ) );
	}

	public function test_primary_cluster_prefers_the_marked_term_and_falls_back_to_the_lowest_id() {
		$first  = self::factory()->term->create( array( 'taxonomy' => 'topic_cluster' ) );
		$second = self::factory()->term->create( array( 'taxonomy' => 'topic_cluster' ) );
		$post   = self::factory()->post->create();
		wp_set_object_terms( $post, array( $second, $first ), 'topic_cluster' );

		$this->assertSame( $first, nh_editorial_get_primary_cluster( $post )->term_id );
		update_post_meta( $post, 'nh_primary_cluster', $second );
		$this->assertSame( $second, nh_editorial_get_primary_cluster( $post )->term_id );
		update_post_meta( $post, 'nh_primary_cluster', 999999 );
		$this->assertSame( $first, nh_editorial_get_primary_cluster( $post )->term_id, 'a marker that is not assigned is ignored' );
	}

	public function test_pillar_resolution_order_post_then_cluster_then_category_with_ancestors() {
		$post    = self::factory()->post->create( array( 'post_category' => array( $this->leaf ) ) );
		$page_a  = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Pillar A',
			)
		);
		$page_b  = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Pillar B',
			)
		);
		$page_c  = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Pillar C',
			)
		);
		$cluster = self::factory()->term->create( array( 'taxonomy' => 'topic_cluster' ) );

		$this->assertNull( nh_editorial_get_pillar( $post ) );

		update_term_meta( $this->top, 'nh_pillar_id', $page_c );
		$found = nh_editorial_get_pillar( $post );
		$this->assertSame( $page_c, $found['id'] );
		$this->assertSame( 'category', $found['source'], 'an ancestor category may provide the pillar' );

		update_term_meta( $this->leaf, 'nh_pillar_id', $page_b );
		$this->assertSame( $page_b, nh_editorial_get_pillar( $post )['id'], 'the nearest category wins' );

		wp_set_object_terms( $post, array( $cluster ), 'topic_cluster' );
		update_term_meta( $cluster, 'nh_pillar_id', $page_a );
		$found = nh_editorial_get_pillar( $post );
		$this->assertSame( $page_a, $found['id'] );
		$this->assertSame( 'cluster', $found['source'] );

		$own = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Own pillar',
			)
		);
		update_post_meta( $post, 'nh_pillar_id', $own );
		$found = nh_editorial_get_pillar( $post );
		$this->assertSame( $own, $found['id'] );
		$this->assertSame( 'post', $found['source'] );
		$this->assertSame( 'Own pillar', $found['title'] );
		$this->assertSame( get_permalink( $own ), $found['url'] );
	}

	public function test_pillar_never_points_at_hidden_content_or_itself() {
		$post = self::factory()->post->create( array( 'post_category' => array( $this->leaf ) ) );
		foreach ( array(
			'draft'    => array( 'post_status' => 'draft' ),
			'private'  => array( 'post_status' => 'private' ),
			'password' => array( 'post_password' => 'secret' ),
			'future'   => array(
				'post_status' => 'future',
				'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '+5 days' ) ),
			),
		) as $label => $args ) {
			$hidden = self::factory()->post->create( array_merge( array( 'post_type' => 'page' ), $args ) );
			update_post_meta( $post, 'nh_pillar_id', $hidden );
			$this->assertNull( nh_editorial_get_pillar( $post ), $label );
		}

		update_post_meta( $post, 'nh_pillar_id', $post );
		$this->assertNull( nh_editorial_get_pillar( $post ), 'a post cannot be its own pillar' );
		update_post_meta( $post, 'nh_pillar_id', 999999 );
		$this->assertNull( nh_editorial_get_pillar( $post ), 'a missing page is ignored' );
	}

	public function test_hub_is_the_top_level_category_archive() {
		$post = self::factory()->post->create( array( 'post_category' => array( $this->leaf ) ) );
		$hub  = nh_editorial_get_hub( $post );
		$this->assertSame( 'Top', $hub['name'] );
		$this->assertSame( get_category_link( $this->top ), $hub['url'] );
		$this->assertSame( $this->top, nh_editorial_top_category( get_term( $this->leaf ) )->term_id );
		$this->assertSame( $this->top, nh_editorial_top_category( get_term( $this->top ) )->term_id );
	}

	public function test_previous_and_next_stay_inside_the_silo_and_follow_publish_order() {
		$other = self::factory()->category->create();
		$mine  = $this->make_posts( $this->leaf, 4, strtotime( '-20 days' ) );
		$this->make_posts( $other, 3, strtotime( '-19 days' ) ); // Interleaved posts of another silo.

		$adjacent = nh_editorial_get_adjacent_ids( $mine[1] );
		$this->assertSame( $mine[0], $adjacent['prev'] );
		$this->assertSame( $mine[2], $adjacent['next'] );
		$this->assertSame( 'Leaf', $adjacent['label'] );

		$first = nh_editorial_get_adjacent_ids( $mine[0] );
		$this->assertSame( 0, $first['prev'] );
		$this->assertSame( $mine[1], $first['next'] );

		$last = nh_editorial_get_adjacent_ids( $mine[3] );
		$this->assertSame( $mine[2], $last['prev'] );
		$this->assertSame( 0, $last['next'] );
	}

	public function test_identical_publish_dates_are_ordered_by_id() {
		$date = gmdate( 'Y-m-d H:i:s', strtotime( '-5 days' ) );
		$ids  = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$ids[] = self::factory()->post->create(
				array(
					'post_date'     => $date,
					'post_date_gmt' => $date,
					'post_category' => array( $this->leaf ),
				)
			);
		}
		sort( $ids );

		$this->assertSame( 0, nh_editorial_get_adjacent_ids( $ids[0] )['prev'] );
		$this->assertSame( $ids[1], nh_editorial_get_adjacent_ids( $ids[0] )['next'] );
		$this->assertSame( $ids[0], nh_editorial_get_adjacent_ids( $ids[1] )['prev'] );
		$this->assertSame( $ids[2], nh_editorial_get_adjacent_ids( $ids[1] )['next'] );
		$this->assertSame( $ids[1], nh_editorial_get_adjacent_ids( $ids[2] )['prev'] );
		$this->assertSame( 0, nh_editorial_get_adjacent_ids( $ids[2] )['next'] );
	}

	public function test_neighbours_never_include_hidden_posts() {
		$mine = $this->make_posts( $this->leaf, 3, strtotime( '-10 days' ) );
		$mid  = strtotime( '-9 days' );
		foreach ( array(
			array( 'post_status' => 'draft' ),
			array( 'post_status' => 'private' ),
			array( 'post_password' => 'secret' ),
		) as $args ) {
			self::factory()->post->create(
				array_merge(
					array(
						'post_date'     => gmdate( 'Y-m-d H:i:s', $mid ),
						'post_category' => array( $this->leaf ),
					),
					$args
				)
			);
		}
		$adjacent = nh_editorial_get_adjacent_ids( $mine[0] );
		$this->assertSame( $mine[1], $adjacent['next'] );
	}

	public function test_neighbours_follow_the_cluster_alias_without_the_taxonomy() {
		$a = self::factory()->post->create( array( 'post_date' => gmdate( 'Y-m-d H:i:s', strtotime( '-9 days' ) ) ) );
		$b = self::factory()->post->create( array( 'post_date' => gmdate( 'Y-m-d H:i:s', strtotime( '-8 days' ) ) ) );
		$c = self::factory()->post->create( array( 'post_date' => gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) ) ) );
		foreach ( array( $a, $b, $c ) as $id ) {
			update_post_meta( $id, 'nh_topic_cluster', 'alias-silo' );
		}
		$outsider = self::factory()->post->create( array( 'post_date' => gmdate( 'Y-m-d H:i:s', strtotime( '-8 days +1 hour' ) ) ) );

		$adjacent = nh_editorial_get_adjacent_ids( $b );
		$this->assertSame( $a, $adjacent['prev'] );
		$this->assertSame( $c, $adjacent['next'] );
		$this->assertNotSame( $outsider, $adjacent['next'] );
	}

	public function test_neighbours_refresh_after_a_new_publication() {
		$mine = $this->make_posts( $this->leaf, 2, strtotime( '-10 days' ) );
		$this->assertSame( 0, nh_editorial_get_adjacent_ids( $mine[1] )['next'] );

		$newer = self::factory()->post->create(
			array(
				'post_date'     => gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
				'post_category' => array( $this->leaf ),
			)
		);
		nh_editorial_cache_bump_now(); // What the shutdown hook does after publication.
		$this->assertSame( $newer, nh_editorial_get_adjacent_ids( $mine[1] )['next'] );
	}

	public function test_hubs_count_articles_in_children_and_skip_the_default_category() {
		$this->make_posts( $this->leaf, 3 );
		$this->make_posts( $this->mid, 2 );
		$other = self::factory()->category->create( array( 'name' => 'Small hub' ) );
		$this->make_posts( $other, 1 );
		self::factory()->post->create(); // Lands in the default (Uncategorized) category.
		nh_editorial_cache_bump_now();

		$hubs  = nh_editorial_get_hubs( 10 );
		$names = wp_list_pluck( $hubs, 'name' );
		$this->assertSame( array( 'Top', 'Small hub' ), $names, 'largest first; default category hidden when others exist' );
		$this->assertSame( 5, $hubs[0]['count'], 'posts in descendants count toward the top-level hub' );
		$this->assertSame( 1, $hubs[1]['count'] );
		$this->assertSame( array( 'Mid' ), wp_list_pluck( $hubs[0]['children'], 'name' ) );
		$this->assertSame( get_category_link( $this->top ), $hubs[0]['url'] );
	}

	public function test_hubs_fall_back_to_the_default_category_when_it_is_the_only_one() {
		foreach ( array( $this->top, $this->mid, $this->leaf ) as $term_id ) {
			wp_delete_term( $term_id, 'category' );
		}
		self::factory()->post->create();
		nh_editorial_cache_bump_now();
		$this->assertCount( 1, nh_editorial_get_hubs( 5 ) );
	}

	public function test_hubs_expose_a_valid_pillar_only() {
		$this->make_posts( $this->leaf, 2 );
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_term_meta( $this->top, 'nh_pillar_id', $page );
		nh_editorial_cache_bump_now();
		$this->assertSame( $page, nh_editorial_get_hubs( 5 )[0]['pillar']['id'] );

		wp_update_post(
			array(
				'ID'          => $page,
				'post_status' => 'draft',
			)
		);
		nh_editorial_cache_bump_now();
		$this->assertNull( nh_editorial_get_hubs( 5 )[0]['pillar'] );
	}

	public function test_child_hubs_list_direct_children_with_exact_counts() {
		$this->make_posts( $this->leaf, 3 );
		$this->make_posts( $this->mid, 1 );
		nh_editorial_cache_bump_now();
		$children = nh_editorial_get_child_hubs( $this->top );
		$this->assertSame( array( 'Mid' ), wp_list_pluck( $children, 'name' ) );
		$this->assertSame( 4, $children[0]['count'] );
		$this->assertSame( array( 'Leaf' ), wp_list_pluck( nh_editorial_get_child_hubs( $this->mid ), 'name' ) );
		$this->assertSame( array(), nh_editorial_get_child_hubs( $this->leaf ) );
	}

	public function test_supporting_articles_are_public_newest_first_and_include_descendants() {
		$in_leaf = $this->make_posts( $this->leaf, 3, strtotime( '-10 days' ) );
		$in_mid  = $this->make_posts( $this->mid, 2, strtotime( '-5 days' ) );
		self::factory()->post->create(
			array(
				'post_status'   => 'draft',
				'post_category' => array( $this->leaf ),
			)
		);
		self::factory()->post->create(
			array(
				'post_password' => 'x',
				'post_category' => array( $this->leaf ),
			)
		);
		nh_editorial_cache_bump_now();

		$ids = nh_editorial_get_supporting_ids( get_term( $this->top ), 10 );
		$this->assertSame( array_reverse( array_merge( $in_leaf, $in_mid ) ), $ids );
		$this->assertCount( 2, nh_editorial_get_supporting_ids( get_term( $this->top ), 2 ) );
		$this->assertNotContains( $in_mid[1], nh_editorial_get_supporting_ids( get_term( $this->top ), 10, $in_mid[1] ) );
	}

	public function test_hub_term_resolution_from_page_setting_and_from_term_mapping() {
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->assertNull( nh_editorial_get_hub_term( $page ) );

		update_post_meta( $page, 'nh_hub_term', 'category:' . $this->mid );
		$this->assertSame( $this->mid, nh_editorial_get_hub_term( $page )->term_id );

		update_post_meta( $page, 'nh_hub_term', 'nonsense' );
		$this->assertNull( nh_editorial_get_hub_term( $page ) );

		update_term_meta( $this->top, 'nh_pillar_id', $page );
		$this->assertSame( $this->top, nh_editorial_get_hub_term( $page )->term_id, 'reverse lookup through the pillar mapping' );

		$cluster = self::factory()->term->create( array( 'taxonomy' => 'topic_cluster' ) );
		update_post_meta( $page, 'nh_hub_term', 'topic_cluster:' . $cluster );
		$this->assertSame( $cluster, nh_editorial_get_hub_term( $page )->term_id );
	}
}

/**
 * Related articles.
 */
class NH_Test_Related extends NH_Unit_Test_Case {

	/** @var int */
	private $top;
	/** @var int */
	private $mid;
	/** @var int */
	private $leaf;
	/** @var int */
	private $other;

	public function set_up() {
		parent::set_up();
		$this->ensure_cluster_taxonomy();
		$this->top   = self::factory()->category->create( array( 'name' => 'Top' ) );
		$this->mid   = self::factory()->category->create(
			array(
				'name'   => 'Mid',
				'parent' => $this->top,
			)
		);
		$this->leaf  = self::factory()->category->create(
			array(
				'name'   => 'Leaf',
				'parent' => $this->mid,
			)
		);
		$this->other = self::factory()->category->create( array( 'name' => 'Other' ) );
		nh_editorial_cache_bump_now();
	}

	public function test_stages_fill_in_order_cluster_category_parent_top() {
		$subject = self::factory()->post->create(
			array(
				'post_title'    => 'Subject',
				'post_category' => array( $this->leaf ),
				'post_date'     => gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) ),
			)
		);
		$cluster = self::factory()->term->create( array( 'taxonomy' => 'topic_cluster' ) );
		wp_set_object_terms( $subject, array( $cluster ), 'topic_cluster' );

		$in_cluster = $this->make_posts( $this->other, 1, strtotime( '-25 days' ) )[0];
		wp_set_object_terms( $in_cluster, array( $cluster ), 'topic_cluster' );
		$same_leaf = $this->make_posts( $this->leaf, 2, strtotime( '-20 days' ) );
		$in_mid    = $this->make_posts( $this->mid, 1, strtotime( '-15 days' ) )[0];
		$in_top    = $this->make_posts( $this->top, 1, strtotime( '-10 days' ) )[0];
		$elsewhere = $this->make_posts( $this->other, 2, strtotime( '-5 days' ) );
		nh_editorial_cache_bump_now();

		$this->assertSame( array( $in_cluster, $same_leaf[1], $same_leaf[0] ), nh_editorial_get_related_ids( $subject, 3 ), 'cluster first, then same category newest first' );
		$this->assertSame(
			array( $in_cluster, $same_leaf[1], $same_leaf[0], $in_mid, $in_top ),
			nh_editorial_get_related_ids( $subject, 5 ),
			'parent then top-level only when more are needed'
		);
		foreach ( $elsewhere as $id ) {
			$this->assertNotContains( $id, nh_editorial_get_related_ids( $subject, 12 ), 'unrelated categories are never used' );
		}
	}

	public function test_without_a_cluster_the_category_chain_is_used() {
		$subject   = self::factory()->post->create( array( 'post_category' => array( $this->leaf ) ) );
		$same_leaf = $this->make_posts( $this->leaf, 1, strtotime( '-20 days' ) )[0];
		$in_mid    = $this->make_posts( $this->mid, 2, strtotime( '-10 days' ) );
		nh_editorial_cache_bump_now();

		$this->assertSame( array( $same_leaf, $in_mid[1], $in_mid[0] ), nh_editorial_get_related_ids( $subject, 3 ) );
	}

	public function test_alias_cluster_posts_come_first_without_the_taxonomy() {
		$subject = self::factory()->post->create( array( 'post_category' => array( $this->leaf ) ) );
		update_post_meta( $subject, 'nh_topic_cluster', 'alias-group' );
		$friend = self::factory()->post->create( array( 'post_category' => array( $this->other ) ) );
		update_post_meta( $friend, 'nh_topic_cluster', 'alias-group' );
		$this->make_posts( $this->leaf, 2 );
		nh_editorial_cache_bump_now();

		$this->assertSame( $friend, nh_editorial_get_related_ids( $subject, 3 )[0] );
	}

	public function test_hidden_posts_current_post_and_duplicates_are_never_returned() {
		$subject = self::factory()->post->create( array( 'post_category' => array( $this->leaf ) ) );
		$public  = $this->make_posts( $this->leaf, 2 );
		$hidden  = array();
		foreach ( array(
			array( 'post_status' => 'draft' ),
			array( 'post_status' => 'private' ),
			array( 'post_password' => 'x' ),
			array(
				'post_status' => 'future',
				'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '+9 days' ) ),
			),
		) as $args ) {
			$hidden[] = self::factory()->post->create( array_merge( array( 'post_category' => array( $this->leaf ) ), $args ) );
		}
		nh_editorial_cache_bump_now();

		$ids = nh_editorial_get_related_ids( $subject, 10 );
		$this->assertEqualsCanonicalizing( $public, $ids );
		$this->assertNotContains( $subject, $ids );
		foreach ( $hidden as $id ) {
			$this->assertNotContains( $id, $ids );
		}
		$this->assertSame( array_unique( $ids ), $ids );
	}

	public function test_a_new_publication_refreshes_the_result_after_the_invalidation() {
		$subject = self::factory()->post->create( array( 'post_category' => array( $this->leaf ) ) );
		$old     = $this->make_posts( $this->leaf, 2, strtotime( '-10 days' ) );
		nh_editorial_cache_bump_now();
		$this->assertSame( array( $old[1], $old[0] ), nh_editorial_get_related_ids( $subject, 3 ) );

		$fresh = self::factory()->post->create(
			array(
				'post_category' => array( $this->leaf ),
				'post_date'     => gmdate( 'Y-m-d H:i:s', strtotime( '-1 hour' ) ),
			)
		);
		nh_editorial_cache_bump_now();
		$this->assertSame( array( $fresh, $old[1], $old[0] ), nh_editorial_get_related_ids( $subject, 3 ) );
	}

	public function test_count_follows_the_setting_and_the_filter() {
		$subject = self::factory()->post->create( array( 'post_category' => array( $this->leaf ) ) );
		$this->make_posts( $this->leaf, 8 );
		nh_editorial_cache_bump_now();

		$this->assertCount( 3, nh_editorial_get_related_ids( $subject ) );
		set_theme_mod( 'nh_related_count', '6' );
		$this->assertCount( 6, nh_editorial_get_related_ids( $subject ) );
		add_filter(
			'nh_editorial_related_count',
			static function () {
				return 2;
			}
		);
		$this->assertCount( 2, nh_editorial_get_related_ids( $subject ) );
		remove_theme_mods();
	}

	public function test_a_post_without_categories_has_no_related_articles() {
		$subject = self::factory()->post->create();
		wp_delete_object_term_relationships( $subject, 'category' );
		$this->assertSame( array(), nh_editorial_get_related_ids( $subject, 3 ) );
	}
}

/**
 * The visible breadcrumb must equal the trail the companion plugin describes to search engines.
 */
class NH_Test_Breadcrumb_Parity extends NH_Unit_Test_Case {

	public function set_up() {
		parent::set_up();
		$this->use_permalinks();
	}

	/**
	 * Compare both implementations on the current query.
	 *
	 * @param string $label Failure label.
	 * @return array Theme items.
	 */
	private function assert_parity( $label ) {
		$this->assertTrue( function_exists( 'nh_seo_breadcrumb_trail' ), 'The companion plugin must be loaded for the parity check.' );
		$theme = nh_editorial_breadcrumb_items();
		$this->assertSame( nh_seo_breadcrumb_trail(), $theme, $label );
		return $theme;
	}

	public function test_posts_pages_and_archives_produce_the_same_trail() {
		// WordPress stores term names as plain text with encoded entities ("&amp;").
		$top = self::factory()->category->create( array( 'name' => 'Top <One>' ) );
		$mid = self::factory()->category->create(
			array(
				'name'   => 'Mid & Two',
				'parent' => $top,
			)
		);
		$this->assertSame( 'Top', get_term( $top )->name );
		$this->assertSame( 'Mid &amp; Two', get_term( $mid )->name );
		$post  = self::factory()->post->create(
			array(
				'post_title'    => 'Leaf “post”',
				'post_category' => array( $mid ),
			)
		);
		$root  = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Guides',
			)
		);
		$child = self::factory()->post->create(
			array(
				'post_type'     => 'page',
				'post_title'    => 'Visas',
				'post_parent'   => $root,
				'post_password' => 'secret',
			)
		);
		$tag   = self::factory()->term->create(
			array(
				'taxonomy' => 'post_tag',
				'name'     => 'Handy',
			)
		);
		$user  = self::factory()->user->create( array( 'display_name' => 'Jane Doe' ) );
		wp_update_post(
			array(
				'ID'          => $post,
				'post_author' => $user,
			)
		);

		$this->go_to( get_permalink( $post ) );
		$trail = $this->assert_parity( 'single post' );
		$this->assertSame( array( 'Home', 'Top', 'Mid &amp; Two', 'Leaf “post”' ), wp_list_pluck( $trail, 'name' ) );

		ob_start();
		nh_editorial_breadcrumbs();
		$html = ob_get_clean();
		$this->assertStringContainsString( 'Mid &amp; Two', $html );
		$this->assertStringNotContainsString( '&amp;amp;', $html, 'stored entities are not escaped twice' );

		$this->go_to( get_permalink( $child ) );
		$this->assertSame( array( 'Home', 'Guides', 'Visas' ), wp_list_pluck( $this->assert_parity( 'child page' ), 'name' ) );

		$this->go_to( get_category_link( $mid ) );
		$this->assertSame( array( 'Home', 'Top', 'Mid &amp; Two' ), wp_list_pluck( $this->assert_parity( 'category' ), 'name' ) );

		$this->go_to( get_tag_link( $tag ) );
		$this->assertSame( array( 'Home', 'Handy' ), wp_list_pluck( $this->assert_parity( 'tag' ), 'name' ) );

		$this->go_to( get_author_posts_url( $user ) );
		$this->assertSame( array( 'Home', 'Jane Doe' ), wp_list_pluck( $this->assert_parity( 'author' ), 'name' ) );

		$this->go_to( home_url( '/?s=handy' ) );
		$names = wp_list_pluck( $this->assert_parity( 'search' ), 'name' );
		$this->assertSame( 'Search results for: handy', end( $names ) );

		$this->go_to( get_year_link( (int) gmdate( 'Y' ) ) );
		$this->assertCount( 2, $this->assert_parity( 'date archive' ) );
	}

	public function test_archive_crumbs_ignore_the_visitors_query_string_in_both_trails() {
		$cat  = self::factory()->category->create( array( 'name' => 'Guides' ) );
		$post = self::factory()->post->create(
			array(
				'post_date'     => '2024-03-09 10:00:00',
				'post_category' => array( $cat ),
			)
		);
		$tag  = self::factory()->term->create(
			array(
				'taxonomy' => 'post_tag',
				'name'     => 'Handy',
			)
		);
		wp_set_post_terms( $post, array( $tag ), 'post_tag' );

		$archives = array(
			'year'     => get_year_link( 2024 ),
			'month'    => get_month_link( 2024, 3 ),
			'day'      => get_day_link( 2024, 3, 9 ),
			'category' => get_category_link( $cat ),
			'tag'      => get_tag_link( $tag ),
		);
		foreach ( $archives as $label => $url ) {
			$this->go_to(
				add_query_arg(
					array(
						'utm_source' => 'newsletter',
						'ref'        => 'abc',
					),
					$url
				)
			);
			$items = $this->assert_parity( $label . ' with tracking arguments' );
			$last  = end( $items );
			$this->assertSame( $url, $last['url'], $label . ': the crumb is the archive itself' );
			$this->assertStringNotContainsString( 'utm_source', $last['url'], $label );
		}
	}

	public function test_front_page_and_404_have_no_trail() {
		$this->go_to( home_url( '/' ) );
		$this->assertSame( array(), $this->assert_parity( 'front page' ) );
		$this->go_to( home_url( '/definitely-missing/' ) );
		$this->assertSame( array(), $this->assert_parity( '404' ) );
	}

	public function test_paged_archives_do_not_add_a_page_crumb() {
		$cat = self::factory()->category->create( array( 'name' => 'Many' ) );
		$this->make_posts( $cat, 15 );
		$this->go_to( get_category_link( $cat ) . 'page/2/' );
		$this->assertSame( array( 'Home', 'Many' ), wp_list_pluck( $this->assert_parity( 'paged category' ), 'name' ) );
	}

	public function test_a_primary_category_from_an_seo_plugin_changes_both_trails_equally() {
		$a    = self::factory()->category->create( array( 'name' => 'Alpha' ) );
		$b    = self::factory()->category->create(
			array(
				'name'   => 'Beta',
				'parent' => $a,
			)
		);
		$post = self::factory()->post->create( array( 'post_category' => array( $a, $b ) ) );
		update_post_meta( $post, 'rank_math_primary_category', $a );
		$this->go_to( get_permalink( $post ) );
		$this->assertSame( 'Alpha', wp_list_pluck( $this->assert_parity( 'primary a' ), 'name' )[1] );
		update_post_meta( $post, 'rank_math_primary_category', $b );
		$this->assertSame( array( 'Home', 'Alpha', 'Beta' ), array_slice( wp_list_pluck( $this->assert_parity( 'primary b' ), 'name' ), 0, 3 ) );
	}
}

/**
 * Primary navigation: walker markup and the category-based fallback.
 */
class NH_Test_Navigation extends NH_Unit_Test_Case {

	/**
	 * Build and assign a three-level menu.
	 *
	 * @return array<string, int> Item IDs.
	 */
	private function build_menu() {
		$menu   = wp_create_nav_menu( 'Test primary' );
		$parent = wp_update_nav_menu_item(
			$menu,
			0,
			array(
				'menu-item-title'  => 'Rental',
				'menu-item-url'    => 'https://example.org/rental/',
				'menu-item-type'   => 'custom',
				'menu-item-status' => 'publish',
			)
		);
		$child  = wp_update_nav_menu_item(
			$menu,
			0,
			array(
				'menu-item-title'     => 'Scooters',
				'menu-item-url'       => 'https://example.org/rental/scooters/',
				'menu-item-type'      => 'custom',
				'menu-item-parent-id' => $parent,
				'menu-item-status'    => 'publish',
			)
		);
		$grand  = wp_update_nav_menu_item(
			$menu,
			0,
			array(
				'menu-item-title'     => 'Automatic',
				'menu-item-url'       => 'https://example.org/rental/scooters/automatic/',
				'menu-item-type'      => 'custom',
				'menu-item-parent-id' => $child,
				'menu-item-status'    => 'publish',
			)
		);
		$single = wp_update_nav_menu_item(
			$menu,
			0,
			array(
				'menu-item-title'  => 'Contact',
				'menu-item-url'    => 'https://example.org/contact/',
				'menu-item-type'   => 'custom',
				'menu-item-status' => 'publish',
			)
		);
		set_theme_mod( 'nav_menu_locations', array( 'primary' => $menu ) );
		return compact( 'parent', 'child', 'grand', 'single' );
	}

	public function test_walker_renders_accessible_disclosure_panels() {
		$ids = $this->build_menu();
		ob_start();
		nh_editorial_primary_menu();
		$html = ob_get_clean();

		$this->assertSame( 1, $this->count_nodes( $html, '//button[contains(@class,"submenu-toggle")]' ), 'only the top-level item with children gets a toggle' );
		$this->assertSame( 1, $this->count_nodes( $html, '//ul[contains(@class,"mega-panel")][@hidden]' ) );
		$this->assertSame( 1, $this->count_nodes( $html, '//ul[@id="submenu-' . $ids['parent'] . '"]' ) );
		$this->assertSame( 1, $this->count_nodes( $html, '//button[@aria-controls="submenu-' . $ids['parent'] . '"][@aria-expanded="false"][@type="button"]' ) );
		$this->assertSame( 1, $this->count_nodes( $html, '//ul[contains(@class,"sub-menu--level-2")]' ) );
		$this->assertSame( 0, $this->count_nodes( $html, '//ul[contains(@class,"sub-menu--level-2")][@hidden]' ), 'grandchildren sit inside the panel' );
		$this->assertSame( 0, $this->count_nodes( $html, '//li[contains(@class,"menu-item-has-children")][a[contains(.,"Contact")]]' ) );

		// The parent stays a real link, so the hub is reachable without JavaScript.
		$this->assertSame( 1, $this->count_nodes( $html, '//li[contains(@class,"menu-item-has-children")]/a[@href="https://example.org/rental/"]' ) );
		$this->assertStringContainsString( 'Toggle submenu of Rental', $html );
	}

	public function test_panel_ids_are_unique() {
		$this->build_menu();
		ob_start();
		nh_editorial_primary_menu();
		$html = ob_get_clean();
		preg_match_all( '/\sid="([^"]+)"/', $html, $matches );
		$this->assertSame( array_unique( $matches[1] ), $matches[1] );
	}

	public function test_fallback_menu_is_built_from_category_hubs() {
		remove_theme_mod( 'nav_menu_locations' );
		$top   = self::factory()->category->create( array( 'name' => 'Rental hub' ) );
		$child = self::factory()->category->create(
			array(
				'name'   => 'Scooter child',
				'parent' => $top,
			)
		);
		$this->make_posts( $child, 2 );
		nh_editorial_cache_bump_now();

		ob_start();
		nh_editorial_primary_menu();
		$html = ob_get_clean();

		$this->assertSame( 1, $this->count_nodes( $html, '//ul[@id="primary-menu"]' ) );
		$this->assertSame( 1, $this->count_nodes( $html, '//li[contains(@class,"menu-item-has-children")]/a[.="Rental hub"]' ) );
		$this->assertSame( 1, $this->count_nodes( $html, '//ul[contains(@class,"mega-panel")][@hidden]//a[.="Scooter child"]' ) );
		$this->assertSame( 1, $this->count_nodes( $html, '//button[@aria-controls="submenu-cat-' . $top . '"]' ) );
		$this->assertSame( 1, $this->count_nodes( $html, '//ul[@id="submenu-cat-' . $top . '"]' ) );
	}

	public function test_fallback_marks_the_current_hub() {
		remove_theme_mod( 'nav_menu_locations' );
		$this->use_permalinks();
		$top = self::factory()->category->create( array( 'name' => 'Current hub' ) );
		$this->make_posts( $top, 2 );
		nh_editorial_cache_bump_now();
		$this->go_to( get_category_link( $top ) );

		ob_start();
		nh_editorial_primary_menu();
		$html = ob_get_clean();
		$this->assertSame( 1, $this->count_nodes( $html, '//li[contains(@class,"current-menu-item")]/a[@aria-current="page"]' ) );
	}

	public function test_fallback_lists_pages_when_there_are_no_categories_yet() {
		remove_theme_mod( 'nav_menu_locations' );
		foreach ( get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
				'exclude'    => array( (int) get_option( 'default_category' ) ),
			)
		) as $term ) {
			wp_delete_term( $term->term_id, 'category' );
		}
		wp_delete_post( 1, true );
		self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Only page',
			)
		);
		nh_editorial_cache_bump_now();

		ob_start();
		nh_editorial_primary_menu();
		$html = ob_get_clean();
		$this->assertStringContainsString( 'Only page', $html );
	}

	public function test_submenu_toggle_text_is_escaped() {
		$html = nh_editorial_submenu_toggle( 'x', 'Rides <b>& more</b>' );
		$this->assertStringNotContainsString( '<b>', $html );
		$this->assertStringContainsString( 'Rides &lt;b&gt;&amp; more&lt;/b&gt;', $html );
	}
}
