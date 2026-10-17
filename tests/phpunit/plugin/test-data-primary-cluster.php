<?php
/**
 * Tests for the primary Topic Cluster rule (docs/DATA-CONTRACT.md section 2).
 *
 * @package nguyen-ha-seo-companion
 */

/**
 * Primary cluster resolution: nh_seo_get_primary_cluster().
 */
class NH_Test_Data_Primary_Cluster extends NH_Unit_Test_Case {

	/**
	 * Create a cluster term.
	 *
	 * @param string $name Term name.
	 * @return int Term ID.
	 */
	private function cluster( $name ) {
		return self::factory()->term->create(
			array(
				'taxonomy' => 'topic_cluster',
				'name'     => $name,
			)
		);
	}

	public function test_post_without_clusters_has_no_primary_cluster() {
		$post_id = self::factory()->post->create();
		$this->assertNull( nh_seo_get_primary_cluster( $post_id ) );

		// A stale choice does not create a primary cluster out of nothing.
		update_post_meta( $post_id, 'nh_primary_cluster', $this->cluster( 'Orphan' ) );
		$this->assertNull( nh_seo_get_primary_cluster( $post_id ) );
	}

	public function test_missing_post_has_no_primary_cluster() {
		$this->assertNull( nh_seo_get_primary_cluster( 999999 ) );
		$this->assertNull( nh_seo_get_primary_cluster( 0 ) );
	}

	public function test_single_cluster_is_primary() {
		$post_id = self::factory()->post->create();
		$only    = $this->cluster( 'Only' );
		wp_set_object_terms( $post_id, array( $only ), 'topic_cluster' );

		$primary = nh_seo_get_primary_cluster( $post_id );
		$this->assertInstanceOf( WP_Term::class, $primary );
		$this->assertSame( $only, $primary->term_id );
		$this->assertSame( 'topic_cluster', $primary->taxonomy );
	}

	public function test_without_a_choice_the_lowest_term_id_wins() {
		$post_id = self::factory()->post->create();
		$first   = $this->cluster( 'Zebra' );
		$second  = $this->cluster( 'Apple' );
		$third   = $this->cluster( 'Middle' );
		wp_set_object_terms( $post_id, array( $third, $second, $first ), 'topic_cluster' );

		$this->assertLessThan( $second, $first );
		$this->assertSame( $first, nh_seo_get_primary_cluster( $post_id )->term_id, 'the lowest ID wins, not the alphabetical order' );
	}

	public function test_chosen_primary_wins_when_assigned() {
		$post_id = self::factory()->post->create();
		$first   = $this->cluster( 'One' );
		$second  = $this->cluster( 'Two' );
		wp_set_object_terms( $post_id, array( $first, $second ), 'topic_cluster' );
		update_post_meta( $post_id, 'nh_primary_cluster', $second );

		$this->assertSame( $second, nh_seo_get_primary_cluster( $post_id )->term_id );
	}

	public function test_choice_that_is_not_assigned_is_ignored() {
		$post_id    = self::factory()->post->create();
		$first      = $this->cluster( 'One' );
		$second     = $this->cluster( 'Two' );
		$unassigned = $this->cluster( 'Three' );
		wp_set_object_terms( $post_id, array( $second, $first ), 'topic_cluster' );
		update_post_meta( $post_id, 'nh_primary_cluster', $unassigned );

		$this->assertSame( $first, nh_seo_get_primary_cluster( $post_id )->term_id );
	}

	public function test_choice_that_was_removed_from_the_post_is_ignored() {
		$post_id = self::factory()->post->create();
		$first   = $this->cluster( 'One' );
		$second  = $this->cluster( 'Two' );
		wp_set_object_terms( $post_id, array( $first, $second ), 'topic_cluster' );
		update_post_meta( $post_id, 'nh_primary_cluster', $second );
		$this->assertSame( $second, nh_seo_get_primary_cluster( $post_id )->term_id );

		wp_remove_object_terms( $post_id, $second, 'topic_cluster' );
		$this->assertSame( $first, nh_seo_get_primary_cluster( $post_id )->term_id );
	}

	public function test_choice_that_was_deleted_is_ignored() {
		$post_id = self::factory()->post->create();
		$first   = $this->cluster( 'One' );
		$second  = $this->cluster( 'Two' );
		wp_set_object_terms( $post_id, array( $first, $second ), 'topic_cluster' );
		update_post_meta( $post_id, 'nh_primary_cluster', $second );

		wp_delete_term( $second, 'topic_cluster' );
		$this->assertSame( $first, nh_seo_get_primary_cluster( $post_id )->term_id );
	}

	public function test_accepts_post_object_and_current_post() {
		$post_id = self::factory()->post->create();
		$only    = $this->cluster( 'Only' );
		wp_set_object_terms( $post_id, array( $only ), 'topic_cluster' );

		$this->assertSame( $only, nh_seo_get_primary_cluster( get_post( $post_id ) )->term_id );

		$GLOBALS['post'] = get_post( $post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test fixture for the "current post" default.
		$this->assertSame( $only, nh_seo_get_primary_cluster()->term_id );
	}

	public function test_categories_and_tags_never_count_as_clusters() {
		$post_id = self::factory()->post->create( array( 'post_category' => array( self::factory()->category->create() ) ) );
		wp_set_post_tags( $post_id, array( 'a-tag' ) );
		$this->assertNull( nh_seo_get_primary_cluster( $post_id ) );
	}

	public function test_pages_have_no_primary_cluster() {
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->assertNull( nh_seo_get_primary_cluster( $page_id ) );
	}
}
