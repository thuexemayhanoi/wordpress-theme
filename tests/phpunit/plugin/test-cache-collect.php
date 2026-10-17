<?php
/**
 * Cache module: which URLs a content change makes stale, collected completely and without duplicates.
 *
 * SIMULATIONS: no real cache plugin is involved here (see NH_Cache_Test_Case). These tests inspect the list
 * of URLs the module would hand to the caches.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

// Shared fixtures.
require_once __DIR__ . '/class-nh-cache-test-case.php';

/**
 * URL collection tests.
 */
class NH_Test_Cache_Collect extends NH_Cache_Test_Case {

	/**
	 * Build the site used by the publish scenario and return what the expected URL set is made of.
	 *
	 * @return array<string, mixed>
	 */
	private function scenario() {
		list( $root, $mid, $leaf ) = $this->cats;
		$other                     = self::factory()->category->create( array( 'name' => 'Other' ) );

		$older = $this->make_post(
			array(
				'post_title' => 'Older neighbour',
				'post_date'  => '2026-01-01 10:00:00',
			)
		);
		$newer = $this->make_post(
			array(
				'post_title' => 'Newer neighbour',
				'post_date'  => '2026-03-01 10:00:00',
			)
		);
		$this->make_post(
			array(
				'post_title'    => 'Other silo',
				'post_category' => array( $other ),
				'post_date'     => '2026-02-01 10:00:00',
			)
		);
		$this->make_post(
			array(
				'post_title'  => 'Draft in the silo',
				'post_status' => 'draft',
				'post_date'   => '2026-01-31 10:00:00',
			)
		);
		$this->make_post(
			array(
				'post_title'    => 'Protected in the silo',
				'post_password' => 'secret',
				'post_date'     => '2026-02-02 10:00:00',
			)
		);

		$pillar = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'The pillar',
				'post_excerpt' => '',
			)
		);
		$blog   = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'The blog',
				'post_excerpt' => '',
			)
		);
		update_option( 'show_on_front', 'page' );
		update_option( 'page_for_posts', $blog );

		$post_id = $this->make_post(
			array(
				'post_title'  => 'The subject',
				'post_status' => 'draft',
				'post_date'   => '2026-02-15 10:00:00',
				'tags_input'  => array( 'Hanoi', 'Scooter' ),
			)
		);
		update_post_meta( $post_id, NH_SEO_Keys::META_PILLAR, $pillar );

		return array(
			'post'     => $post_id,
			'older'    => $older,
			'newer'    => $newer,
			'pillar'   => $pillar,
			'blog'     => $blog,
			'chain'    => array( $root, $mid, $leaf ),
			'tags'     => array( 'hanoi', 'scooter' ),
			'expected' => null,
		);
	}

	/**
	 * Every URL a published subject post must purge.
	 *
	 * @param array $scenario Output of scenario().
	 * @return string[]
	 */
	private function expected_urls( array $scenario ) {
		$urls = array(
			get_permalink( $scenario['post'] ),
			home_url( '/' ),
			get_permalink( $scenario['blog'] ),
			get_author_posts_url( $this->author ),
			get_permalink( $scenario['pillar'] ),
			get_permalink( $scenario['older'] ),
			get_permalink( $scenario['newer'] ),
		);
		foreach ( $scenario['chain'] as $term_id ) {
			$urls[] = get_category_link( $term_id );
		}
		foreach ( $scenario['tags'] as $slug ) {
			$urls[] = get_term_link( $slug, 'post_tag' );
		}
		return $urls;
	}

	public function test_publishing_collects_the_complete_set_of_affected_urls() {
		$scenario = $this->scenario();
		NH_SEO_Cache::reset();

		$this->publish( $scenario['post'] );

		$this->assertSameUrls( $this->expected_urls( $scenario ), NH_SEO_Cache::collect() );
		$this->assertSame( array( $scenario['post'] ), NH_SEO_Cache::pending()['posts'] );
	}

	public function test_the_set_covers_post_home_posts_page_categories_with_ancestors_tags_author_pillar_and_neighbours() {
		$scenario = $this->scenario();
		$this->publish( $scenario['post'] );
		NH_SEO_Cache::reset();

		$urls = NH_SEO_Cache_Urls::for_post( $scenario['post'] );

		$this->assertContains( get_permalink( $scenario['post'] ), $urls, 'the post' );
		$this->assertContains( home_url( '/' ), $urls, 'home' );
		$this->assertContains( get_permalink( $scenario['blog'] ), $urls, 'posts page' );
		foreach ( $this->cats as $category_id ) {
			$this->assertContains( get_category_link( $category_id ), $urls, 'category archive or ancestor ' . $category_id );
		}
		$this->assertContains( get_term_link( 'hanoi', 'post_tag' ), $urls, 'tag archive' );
		$this->assertContains( get_term_link( 'scooter', 'post_tag' ), $urls, 'tag archive' );
		$this->assertContains( get_author_posts_url( $this->author ), $urls, 'author archive' );
		$this->assertContains( get_permalink( $scenario['pillar'] ), $urls, 'pillar page' );
		$this->assertContains( get_permalink( $scenario['older'] ), $urls, 'previous neighbour' );
		$this->assertContains( get_permalink( $scenario['newer'] ), $urls, 'next neighbour' );
		$this->assertCount( 12, $urls, 'Nothing else: no drafts, no protected posts, no other silo.' );
		$this->assertSame( $urls, array_values( array_unique( $urls ) ) );
	}

	public function test_updating_a_published_post_collects_the_same_set() {
		$scenario = $this->scenario();
		$this->publish( $scenario['post'] );
		NH_SEO_Cache::reset();

		wp_update_post(
			array(
				'ID'           => $scenario['post'],
				'post_content' => 'Edited content',
			)
		);

		$this->assertSameUrls( $this->expected_urls( $scenario ), NH_SEO_Cache::collect() );
	}

	public function test_a_new_slug_purges_the_old_address_too() {
		$scenario = $this->scenario();
		$this->publish( $scenario['post'] );
		$old = get_permalink( $scenario['post'] );
		NH_SEO_Cache::reset();

		wp_update_post(
			array(
				'ID'        => $scenario['post'],
				'post_name' => 'a-brand-new-slug',
			)
		);

		$urls = NH_SEO_Cache::collect();
		$this->assertContains( $old, $urls, 'The old address is stale and cannot be recomputed later.' );
		$this->assertContains( get_permalink( $scenario['post'] ), $urls );
		$this->assertStringContainsString( 'a-brand-new-slug', get_permalink( $scenario['post'] ) );
		$this->assertNotSame( $old, get_permalink( $scenario['post'] ) );
	}

	public function test_a_changed_category_purges_the_old_and_the_new_archives() {
		$scenario = $this->scenario();
		$this->publish( $scenario['post'] );
		$sibling = self::factory()->category->create(
			array(
				'name'   => 'Manual',
				'slug'   => 'manual',
				'parent' => $this->cats[0],
			)
		);
		NH_SEO_Cache::reset();

		wp_set_post_categories( $scenario['post'], array( $sibling ) );

		$urls = NH_SEO_Cache::collect();
		foreach ( array( $this->cats[2], $this->cats[1], $this->cats[0], $sibling ) as $category_id ) {
			$this->assertContains( get_category_link( $category_id ), $urls, 'archive ' . $category_id );
		}
		$this->assertContains( get_permalink( $scenario['post'] ), $urls );
	}

	public function test_a_changed_tag_purges_the_old_and_the_new_archives() {
		$scenario = $this->scenario();
		$this->publish( $scenario['post'] );
		NH_SEO_Cache::reset();

		wp_set_post_tags( $scenario['post'], array( 'Scooter', 'Brand new tag' ) );

		$urls = NH_SEO_Cache::collect();
		$this->assertContains( get_term_link( 'hanoi', 'post_tag' ), $urls, 'The removed tag archive no longer lists the post.' );
		$this->assertContains( get_term_link( 'scooter', 'post_tag' ), $urls );
		$this->assertContains( get_term_link( 'brand-new-tag', 'post_tag' ), $urls );
	}

	public function test_unpublishing_purges_the_old_address_the_archives_and_the_neighbours() {
		$scenario = $this->scenario();
		$this->publish( $scenario['post'] );
		$old      = get_permalink( $scenario['post'] );
		$expected = $this->expected_urls( $scenario );
		NH_SEO_Cache::reset();

		wp_update_post(
			array(
				'ID'          => $scenario['post'],
				'post_status' => 'draft',
			)
		);

		$urls = NH_SEO_Cache::collect();
		$this->assertContains( $old, $urls, 'The address the post had while it was public.' );
		$this->assertSameUrls( $expected, $urls );
		foreach ( $urls as $url ) {
			$this->assertStringNotContainsString( 'p=', $url, 'No ?p=ID address of a draft.' );
		}
	}

	public function test_trashing_purges_the_address_it_had_before_the_trashed_suffix() {
		$scenario = $this->scenario();
		$this->publish( $scenario['post'] );
		$old      = get_permalink( $scenario['post'] );
		$expected = $this->expected_urls( $scenario );
		NH_SEO_Cache::reset();

		wp_trash_post( $scenario['post'] );

		$urls = NH_SEO_Cache::collect();
		$this->assertContains( $old, $urls );
		$this->assertSameUrls( $expected, $urls );
		foreach ( $urls as $url ) {
			$this->assertStringNotContainsString( '__trashed', $url );
		}
	}

	public function test_deleting_for_good_captures_everything_before_the_post_is_gone() {
		$scenario = $this->scenario();
		$this->publish( $scenario['post'] );
		$expected = $this->expected_urls( $scenario );
		NH_SEO_Cache::reset();

		wp_delete_post( $scenario['post'], true );

		$this->assertNull( get_post( $scenario['post'] ) );
		$this->assertSame( array(), NH_SEO_Cache::pending()['posts'], 'A deleted post is not left in the queue.' );
		$this->assertSameUrls( $expected, NH_SEO_Cache::collect() );
	}

	public function test_deleting_a_post_that_was_already_in_the_trash_purges_nothing_more() {
		$scenario = $this->scenario();
		$this->publish( $scenario['post'] );
		wp_trash_post( $scenario['post'] );
		NH_SEO_Cache::reset();

		wp_delete_post( $scenario['post'], true );

		$this->assertSame( array(), NH_SEO_Cache::collect() );
	}

	public function test_drafts_pending_and_private_posts_collect_nothing() {
		$post_id = $this->make_post( array( 'post_status' => 'draft' ) );
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Still a draft',
			)
		);
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'pending',
			)
		);
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'private',
			)
		);
		$this->make_post( array( 'post_status' => 'private' ) );
		$this->make_post(
			array(
				'post_status' => 'future',
				'post_date'   => gmdate( 'Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS ),
			)
		);

		$this->assertSame( array(), NH_SEO_Cache::pending()['posts'] );
		$this->assertSame( array(), NH_SEO_Cache::collect() );
	}

	public function test_going_from_private_to_published_collects() {
		$post_id = $this->make_post( array( 'post_status' => 'private' ) );
		NH_SEO_Cache::reset();
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);
		$this->assertContains( get_permalink( $post_id ), NH_SEO_Cache::collect() );
	}

	public function test_revisions_autosaves_attachments_and_menu_items_collect_nothing() {
		$post_id = $this->make_post();
		NH_SEO_Cache::reset();

		wp_save_post_revision( $post_id );
		self::factory()->attachment->create_object( 'cache-test.jpg', $post_id, array( 'post_mime_type' => 'image/jpeg' ) );
		wp_update_nav_menu_item(
			wp_create_nav_menu( 'Cache menu' ),
			0,
			array(
				'menu-item-title'  => 'Home',
				'menu-item-url'    => home_url( '/' ),
				'menu-item-status' => 'publish',
			)
		);

		$this->assertSame( array(), NH_SEO_Cache::pending()['posts'] );
		$this->assertSame( array(), NH_SEO_Cache::collect() );
	}

	public function test_a_page_collects_its_address_home_and_the_posts_page_only() {
		$scenario = $this->scenario();
		$page     = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'Rental terms',
				'post_excerpt' => '',
				'post_author'  => $this->author,
				'post_status'  => 'draft',
			)
		);
		NH_SEO_Cache::reset();

		$this->publish( $page );

		$this->assertSameUrls(
			array( get_permalink( $page ), home_url( '/' ), get_permalink( $scenario['blog'] ) ),
			NH_SEO_Cache::collect()
		);
		$this->assertNotContains( get_author_posts_url( $this->author ), NH_SEO_Cache::collect(), 'Author archives list posts, not pages.' );
	}

	public function test_a_post_without_tags_still_collects_what_it_can() {
		$post_id = $this->make_post( array( 'post_status' => 'draft' ) );
		NH_SEO_Cache::reset();

		$this->publish( $post_id );

		$urls = NH_SEO_Cache::collect();
		$this->assertContains( get_permalink( $post_id ), $urls );
		$this->assertContains( home_url( '/' ), $urls );
		$this->assertContains( get_author_posts_url( $this->author ), $urls );
		$this->assertContains( get_category_link( $this->cats[2] ), $urls );
		$this->assertCount( 6, $urls, 'Post, home, author and the category chain; no tags, no pillar, no neighbours.' );
	}

	public function test_a_post_alone_in_its_silo_has_no_neighbours_and_the_first_has_only_a_next() {
		$first  = $this->make_post( array( 'post_date' => '2026-01-01 10:00:00' ) );
		$second = $this->make_post( array( 'post_date' => '2026-01-02 10:00:00' ) );

		$this->assertSame( array( $second ), NH_SEO_Cache_Urls::neighbour_ids( get_post( $first ) ) );
		$this->assertSame( array( $first ), NH_SEO_Cache_Urls::neighbour_ids( get_post( $second ) ) );

		wp_delete_post( $second, true );
		$this->assertSame( array(), NH_SEO_Cache_Urls::neighbour_ids( get_post( $first ) ) );
	}

	public function test_neighbours_follow_publish_date_with_ties_broken_by_id() {
		$a = $this->make_post( array( 'post_date' => '2026-02-01 10:00:00' ) );
		$b = $this->make_post( array( 'post_date' => '2026-02-01 10:00:00' ) );
		$c = $this->make_post( array( 'post_date' => '2026-02-01 10:00:00' ) );
		$d = $this->make_post( array( 'post_date' => '2026-01-01 10:00:00' ) );
		$e = $this->make_post( array( 'post_date' => '2026-03-01 10:00:00' ) );

		$this->assertSame( array( $d, $b ), NH_SEO_Cache_Urls::neighbour_ids( get_post( $a ) ), 'Oldest of the tie: previous is the older date, next is the next ID.' );
		$this->assertSame( array( $a, $c ), NH_SEO_Cache_Urls::neighbour_ids( get_post( $b ) ) );
		$this->assertSame( array( $b, $e ), NH_SEO_Cache_Urls::neighbour_ids( get_post( $c ) ) );
	}

	public function test_neighbours_ignore_other_types_unpublished_and_protected_posts_and_other_silos() {
		$subject = $this->make_post( array( 'post_date' => '2026-02-15 10:00:00' ) );
		$this->make_post(
			array(
				'post_status' => 'draft',
				'post_date'   => '2026-02-14 10:00:00',
			)
		);
		$this->make_post(
			array(
				'post_status'   => 'publish',
				'post_password' => 'x',
				'post_date'     => '2026-02-14 11:00:00',
			)
		);
		$this->make_post(
			array(
				'post_category' => array( $this->cats[1] ),
				'post_date'     => '2026-02-14 12:00:00',
			)
		);
		$this->make_post(
			array(
				'post_status' => 'private',
				'post_date'   => '2026-02-16 10:00:00',
			)
		);
		$this->assertSame( array(), NH_SEO_Cache_Urls::neighbour_ids( get_post( $subject ) ) );

		$real = $this->make_post( array( 'post_date' => '2026-02-14 13:00:00' ) );
		$this->assertSame( array( $real ), NH_SEO_Cache_Urls::neighbour_ids( get_post( $subject ) ) );
	}

	public function test_the_silo_is_the_primary_cluster_when_there_is_one() {
		register_taxonomy( NH_SEO_Keys::TAXONOMY_CLUSTER, 'post', array( 'public' => false ) );
		try {
			$cluster = wp_insert_term( 'Basics', NH_SEO_Keys::TAXONOMY_CLUSTER );
			$cluster = (int) $cluster['term_id'];

			$in_cluster_old = $this->make_post(
				array(
					'post_date'     => '2026-01-01 10:00:00',
					'post_category' => array( $this->cats[0] ),
				)
			);
			$in_cluster_new = $this->make_post(
				array(
					'post_date'     => '2026-03-01 10:00:00',
					'post_category' => array( $this->cats[0] ),
				)
			);
			$in_category    = $this->make_post( array( 'post_date' => '2026-02-01 10:00:00' ) );
			$subject        = $this->make_post( array( 'post_date' => '2026-02-15 10:00:00' ) );
			wp_set_object_terms( $in_cluster_old, array( $cluster ), NH_SEO_Keys::TAXONOMY_CLUSTER );
			wp_set_object_terms( $in_cluster_new, array( $cluster ), NH_SEO_Keys::TAXONOMY_CLUSTER );
			wp_set_object_terms( $subject, array( $cluster ), NH_SEO_Keys::TAXONOMY_CLUSTER );

			$this->assertSame( $cluster, NH_SEO_Cache_Urls::silo_term( get_post( $subject ) )->term_id );
			$this->assertSame( array( $in_cluster_old, $in_cluster_new ), NH_SEO_Cache_Urls::neighbour_ids( get_post( $subject ) ), 'Neighbours come from the cluster, not from the category.' );

			// Without a cluster the primary category is the silo.
			wp_set_object_terms( $subject, array(), NH_SEO_Keys::TAXONOMY_CLUSTER );
			$this->assertSame( $this->cats[2], NH_SEO_Cache_Urls::silo_term( get_post( $subject ) )->term_id );
			$this->assertSame( array( $in_category ), NH_SEO_Cache_Urls::neighbour_ids( get_post( $subject ) ), 'Back to the category silo: only the post of the same category is a neighbour.' );
		} finally {
			unregister_taxonomy( NH_SEO_Keys::TAXONOMY_CLUSTER );
		}
	}

	public function test_the_primary_cluster_meta_decides_between_several_clusters() {
		register_taxonomy( NH_SEO_Keys::TAXONOMY_CLUSTER, 'post', array( 'public' => false ) );
		try {
			$low  = (int) wp_insert_term( 'Low id', NH_SEO_Keys::TAXONOMY_CLUSTER )['term_id'];
			$high = (int) wp_insert_term( 'High id', NH_SEO_Keys::TAXONOMY_CLUSTER )['term_id'];
			$post = get_post( $this->make_post() );
			wp_set_object_terms( $post->ID, array( $high, $low ), NH_SEO_Keys::TAXONOMY_CLUSTER );

			$this->assertSame( $low, NH_SEO_Cache_Urls::primary_cluster( $post )->term_id, 'Lowest term ID by default.' );

			update_post_meta( $post->ID, NH_SEO_Keys::META_PRIMARY_CLUSTER, $high );
			$this->assertSame( $high, NH_SEO_Cache_Urls::primary_cluster( $post )->term_id, 'nh_primary_cluster when it is assigned.' );

			update_post_meta( $post->ID, NH_SEO_Keys::META_PRIMARY_CLUSTER, 999999 );
			$this->assertSame( $low, NH_SEO_Cache_Urls::primary_cluster( $post )->term_id, 'A stale primary cluster is ignored.' );
		} finally {
			unregister_taxonomy( NH_SEO_Keys::TAXONOMY_CLUSTER );
		}
	}

	public function test_pillar_resolution_follows_the_data_contract() {
		list( $root, $mid, $leaf ) = $this->cats;
		$post                      = get_post( $this->make_post() );
		$page                      = static function () {
			return self::factory()->post->create(
				array(
					'post_type'    => 'page',
					'post_excerpt' => '',
				)
			);
		};
		$pillar_post               = $page();
		$pillar_cluster            = $page();
		$pillar_leaf               = $page();
		$pillar_mid                = $page();
		$pillar_root               = $page();

		$this->assertNull( NH_SEO_Cache_Urls::pillar( $post ), 'No pillar mapped anywhere.' );

		update_term_meta( $root, NH_SEO_Keys::TERM_PILLAR, $pillar_root );
		$this->assertSame( $pillar_root, NH_SEO_Cache_Urls::pillar( $post )->ID, 'The top-level category (the root of the ancestors).' );

		update_term_meta( $mid, NH_SEO_Keys::TERM_PILLAR, $pillar_mid );
		$this->assertSame( $pillar_mid, NH_SEO_Cache_Urls::pillar( $post )->ID, 'Ancestors nearest first.' );

		update_term_meta( $leaf, NH_SEO_Keys::TERM_PILLAR, $pillar_leaf );
		$this->assertSame( $pillar_leaf, NH_SEO_Cache_Urls::pillar( $post )->ID, 'The primary category before its ancestors.' );

		register_taxonomy( NH_SEO_Keys::TAXONOMY_CLUSTER, 'post', array( 'public' => false ) );
		try {
			$cluster = (int) wp_insert_term( 'Pillar cluster', NH_SEO_Keys::TAXONOMY_CLUSTER )['term_id'];
			wp_set_object_terms( $post->ID, array( $cluster ), NH_SEO_Keys::TAXONOMY_CLUSTER );
			update_term_meta( $cluster, NH_SEO_Keys::TERM_PILLAR, $pillar_cluster );
			$this->assertSame( $pillar_cluster, NH_SEO_Cache_Urls::pillar( $post )->ID, 'The primary cluster before the categories.' );

			update_post_meta( $post->ID, NH_SEO_Keys::META_PILLAR, $pillar_post );
			$this->assertSame( $pillar_post, NH_SEO_Cache_Urls::pillar( $post )->ID, 'The post itself before everything.' );

			// A target that is not published, not public or the post itself is skipped.
			wp_update_post(
				array(
					'ID'          => $pillar_post,
					'post_status' => 'draft',
				)
			);
			$this->assertSame( $pillar_cluster, NH_SEO_Cache_Urls::pillar( $post )->ID, 'A draft pillar is skipped.' );

			wp_update_post(
				array(
					'ID'            => $pillar_cluster,
					'post_password' => 'secret',
				)
			);
			$this->assertSame( $pillar_leaf, NH_SEO_Cache_Urls::pillar( $post )->ID, 'A protected pillar is skipped.' );

			update_post_meta( $post->ID, NH_SEO_Keys::META_PILLAR, $post->ID );
			update_term_meta( $leaf, NH_SEO_Keys::TERM_PILLAR, $post->ID );
			$this->assertSame( $pillar_mid, NH_SEO_Cache_Urls::pillar( $post )->ID, 'A post is never its own pillar.' );
		} finally {
			unregister_taxonomy( NH_SEO_Keys::TAXONOMY_CLUSTER );
		}
	}

	public function test_term_archives_are_collected_when_terms_are_created_renamed_and_deleted() {
		$term = wp_insert_term( 'Fresh topic', 'category' );
		$id   = (int) $term['term_id'];
		$new  = get_category_link( $id );
		$this->assertContains( $new, NH_SEO_Cache::pending()['urls'], 'A new category has an archive that did not exist.' );

		NH_SEO_Cache::reset();
		wp_update_term(
			$id,
			'category',
			array(
				'name' => 'Renamed topic',
				'slug' => 'renamed-topic',
			)
		);
		$urls = NH_SEO_Cache::pending()['urls'];
		$this->assertContains( $new, $urls, 'The old address.' );
		$this->assertContains( get_category_link( $id ), $urls, 'The new address.' );
		$this->assertNotSame( $new, get_category_link( $id ) );

		NH_SEO_Cache::reset();
		$final = get_category_link( $id );
		wp_delete_term( $id, 'category' );
		$urls = NH_SEO_Cache::pending()['urls'];
		$this->assertContains( $final, $urls, 'The archive of a deleted term.' );
		$this->assertContains( home_url( '/' ), $urls, 'Menus and widgets list categories.' );
	}

	public function test_a_nested_category_purges_its_ancestors_when_edited() {
		NH_SEO_Cache::reset();
		wp_update_term( $this->cats[2], 'category', array( 'name' => 'Automatic scooters' ) );
		$urls = NH_SEO_Cache::pending()['urls'];
		foreach ( $this->cats as $category_id ) {
			$this->assertContains( get_category_link( $category_id ), $urls );
		}
	}

	public function test_terms_of_taxonomies_without_public_pages_are_ignored() {
		register_taxonomy( NH_SEO_Keys::TAXONOMY_CLUSTER, 'post', array( 'public' => false ) );
		try {
			NH_SEO_Cache::reset();
			$term = wp_insert_term( 'Private cluster', NH_SEO_Keys::TAXONOMY_CLUSTER );
			wp_update_term( (int) $term['term_id'], NH_SEO_Keys::TAXONOMY_CLUSTER, array( 'name' => 'Renamed private cluster' ) );

			$post_id = $this->make_post();
			NH_SEO_Cache::reset();
			wp_set_object_terms( $post_id, array( (int) $term['term_id'] ), NH_SEO_Keys::TAXONOMY_CLUSTER );
			wp_delete_term( (int) $term['term_id'], NH_SEO_Keys::TAXONOMY_CLUSTER );

			$this->assertSame( array(), NH_SEO_Cache::pending()['urls'] );
			$this->assertSame( array(), NH_SEO_Cache::pending()['posts'], 'Cluster changes alone do not purge: clusters have no public pages.' );
		} finally {
			unregister_taxonomy( NH_SEO_Keys::TAXONOMY_CLUSTER );
		}
	}

	public function test_urls_are_unique_absolute_http_urls_and_the_list_is_capped_and_filterable() {
		$scenario = $this->scenario();
		$this->publish( $scenario['post'] );
		NH_SEO_Cache::reset();
		for ( $i = 0; $i < 5; $i++ ) {
			wp_update_post(
				array(
					'ID'           => $scenario['post'],
					'post_content' => 'Edit ' . $i,
				)
			);
		}
		wp_set_post_tags( $scenario['post'], array( 'Hanoi' ) );
		wp_set_post_tags( $scenario['post'], array( 'Scooter' ) );

		$urls = NH_SEO_Cache::collect();
		$this->assertSame( $urls, array_values( array_unique( $urls ) ), 'Many events, every URL once.' );
		foreach ( $urls as $url ) {
			$this->assertMatchesRegularExpression( '#^https?://#', $url );
		}

		add_filter(
			'nh_seo_purge_post_urls',
			static function ( $urls, $post ) {
				$urls[] = 'javascript:alert(1)';
				$urls[] = '/relative/path/';
				$urls[] = '';
				$urls[] = array( 'not', 'a', 'url' );
				$urls[] = get_year_link( (int) gmdate( 'Y', strtotime( $post->post_date ) ) );
				return $urls;
			},
			10,
			2
		);
		$urls = NH_SEO_Cache::collect();
		$this->assertContains( get_year_link( 2026 ), $urls, 'A site can add URLs.' );
		foreach ( $urls as $url ) {
			$this->assertMatchesRegularExpression( '#^https?://#', $url, 'Junk added by a filter never reaches a cache.' );
		}

		add_filter(
			'nh_seo_purge_max_urls',
			static function () {
				return 3;
			}
		);
		$this->assertCount( 3, NH_SEO_Cache::collect() );
	}

	public function test_a_rest_request_from_the_ai_writer_collects_the_final_state() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$tag = (int) wp_insert_term( 'Rest tag', 'post_tag' )['term_id'];
		NH_SEO_Cache::reset();

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$request->set_body_params(
			array(
				'title'      => 'Published through REST',
				'content'    => 'Body',
				'status'     => 'publish',
				'categories' => array( $this->cats[2] ),
				'tags'       => array( $tag ),
			)
		);
		$response = rest_do_request( $request );
		$this->assertSame( 201, $response->get_status() );
		$post_id = (int) $response->get_data()['id'];

		// Terms are assigned after the post row is written: the URLs must come from the final state.
		$urls = NH_SEO_Cache::collect();
		$this->assertContains( get_permalink( $post_id ), $urls );
		foreach ( $this->cats as $category_id ) {
			$this->assertContains( get_category_link( $category_id ), $urls );
		}
		$this->assertContains( get_tag_link( $tag ), $urls );
		$this->assertContains( home_url( '/' ), $urls );
		$this->assertSame( array( $post_id ), NH_SEO_Cache::pending()['posts'] );
	}

	public function test_the_post_list_follows_the_change_not_the_request() {
		$first  = $this->make_post();
		$second = $this->make_post();
		NH_SEO_Cache::reset();

		wp_update_post(
			array(
				'ID'         => $first,
				'post_title' => 'Changed one',
			)
		);
		wp_update_post(
			array(
				'ID'         => $second,
				'post_title' => 'Changed two',
			)
		);
		wp_update_post(
			array(
				'ID'         => $first,
				'post_title' => 'Changed one again',
			)
		);

		$this->assertSame( array( $first, $second ), NH_SEO_Cache::pending()['posts'] );
	}
}
