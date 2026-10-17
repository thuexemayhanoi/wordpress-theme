<?php
/**
 * Tests for the Topic Cluster alias sync (docs/DATA-CONTRACT.md section 2).
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * The `nh_topic_cluster` alias becomes a term, an assignment and a primary cluster.
 */
class NH_Test_Data_Cluster_Sync extends NH_Unit_Test_Case {

	public function set_up() {
		parent::set_up();
		$this->set_auto_create( 1 );
	}

	/**
	 * Switch the "Auto-create clusters" setting.
	 *
	 * @param int $on 1 or 0.
	 * @return void
	 */
	private function set_auto_create( $on ) {
		update_option( NH_SEO_Keys::OPTION_SETTINGS, array( 'auto_cluster_terms' => $on ) );
	}

	/**
	 * Create a cluster term.
	 *
	 * @param string $name Name.
	 * @param string $slug Optional slug.
	 * @return int Term ID.
	 */
	private function cluster( $name, $slug = '' ) {
		$args = array(
			'taxonomy' => 'topic_cluster',
			'name'     => $name,
		);
		if ( '' !== $slug ) {
			$args['slug'] = $slug;
		}
		return self::factory()->term->create( $args );
	}

	/**
	 * Term IDs assigned to a post, ascending.
	 *
	 * @param int $post_id Post ID.
	 * @return int[]
	 */
	private function assigned( $post_id ) {
		$ids = wp_get_object_terms( $post_id, 'topic_cluster', array( 'fields' => 'ids' ) );
		sort( $ids );
		return array_map( 'intval', $ids );
	}

	/**
	 * Number of cluster terms.
	 *
	 * @return int
	 */
	private function count_clusters() {
		return (int) wp_count_terms(
			array(
				'taxonomy'   => 'topic_cluster',
				'hide_empty' => false,
			)
		);
	}

	public function test_alias_matches_existing_term_by_slug() {
		$term    = $this->cluster( 'Scooter Rental', 'scooter-rental' );
		$post_id = self::factory()->post->create();

		update_post_meta( $post_id, 'nh_topic_cluster', 'scooter-rental' );

		$this->assertSame( array( $term ), $this->assigned( $post_id ) );
		$this->assertSame( (string) $term, get_post_meta( $post_id, 'nh_primary_cluster', true ) );
		$this->assertSame( 1, $this->count_clusters(), 'nothing is created when the term exists' );
	}

	public function test_alias_matches_existing_term_by_name_when_the_slug_differs() {
		$term    = $this->cluster( 'Scooter Rental', 'sr' );
		$post_id = self::factory()->post->create();

		update_post_meta( $post_id, 'nh_topic_cluster', 'Scooter Rental' );

		$this->assertSame( array( $term ), $this->assigned( $post_id ) );
		$this->assertSame( 1, $this->count_clusters() );
	}

	public function test_slug_match_wins_over_name_match() {
		$by_slug = $this->cluster( 'Beta', 'alpha' );
		$by_name = $this->cluster( 'alpha', 'alpha-2' );
		$post_id = self::factory()->post->create();

		update_post_meta( $post_id, 'nh_topic_cluster', 'alpha' );

		$this->assertSame( array( $by_slug ), $this->assigned( $post_id ) );
		$this->assertNotSame( $by_slug, $by_name );
	}

	public function test_alias_is_sanitized_before_the_lookup() {
		$term    = $this->cluster( 'Scooter Rental', 'scooter-rental' );
		$post_id = self::factory()->post->create();

		update_post_meta( $post_id, 'nh_topic_cluster', "  <b>SCOOTER</b>   rental \n" );

		$this->assertSame( array( $term ), $this->assigned( $post_id ) );
		$this->assertSame( 'SCOOTER rental', get_post_meta( $post_id, 'nh_topic_cluster', true ) );
	}

	public function test_vietnamese_alias_matches_accent_folded_slug() {
		$term = $this->cluster( 'Thuê xe máy Hà Nội' );
		$this->assertSame( 'thue-xe-may-ha-noi', get_term( $term )->slug );

		$by_name = self::factory()->post->create();
		update_post_meta( $by_name, 'nh_topic_cluster', 'Thuê xe máy Hà Nội' );
		$this->assertSame( array( $term ), $this->assigned( $by_name ) );

		$by_slug = self::factory()->post->create();
		update_post_meta( $by_slug, 'nh_topic_cluster', 'thue-xe-may-ha-noi' );
		$this->assertSame( array( $term ), $this->assigned( $by_slug ) );

		$unaccented = self::factory()->post->create();
		update_post_meta( $unaccented, 'nh_topic_cluster', 'Thue xe may Ha Noi' );
		$this->assertSame( array( $term ), $this->assigned( $unaccented ) );
		$this->assertSame( 1, $this->count_clusters() );
	}

	public function test_missing_term_is_created_when_auto_create_is_on() {
		$events = array();
		add_action(
			'nh_seo_cluster_synced',
			static function ( $post_id, $term_id, $created ) use ( &$events ) {
				$events[] = array( $post_id, $term_id, $created );
			},
			10,
			3
		);
		$post_id = self::factory()->post->create();

		update_post_meta( $post_id, 'nh_topic_cluster', 'Scooter Rental Basics' );

		$ids = $this->assigned( $post_id );
		$this->assertCount( 1, $ids );
		$term = get_term( $ids[0], 'topic_cluster' );
		$this->assertSame( 'Scooter Rental Basics', $term->name );
		$this->assertSame( 'scooter-rental-basics', $term->slug );
		$this->assertSame( (string) $ids[0], get_post_meta( $post_id, 'nh_primary_cluster', true ) );
		$this->assertSame( array( array( $post_id, $ids[0], true ) ), $events );
	}

	public function test_slug_like_alias_creates_a_term_with_that_slug() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'nh_topic_cluster', 'scooter-rental-basics' );

		$term = get_term( $this->assigned( $post_id )[0], 'topic_cluster' );
		$this->assertSame( 'scooter-rental-basics', $term->slug );
	}

	public function test_missing_term_is_not_created_when_auto_create_is_off() {
		$this->set_auto_create( 0 );
		$post_id = self::factory()->post->create();

		update_post_meta( $post_id, 'nh_topic_cluster', 'Does not exist' );

		$this->assertSame( array(), $this->assigned( $post_id ) );
		$this->assertSame( 0, $this->count_clusters() );
		$this->assertSame( 0, (int) get_post_meta( $post_id, 'nh_primary_cluster', true ) );
		$this->assertSame( 'Does not exist', get_post_meta( $post_id, 'nh_topic_cluster', true ), 'the alias itself is kept' );
	}

	public function test_existing_term_is_still_assigned_when_auto_create_is_off() {
		$this->set_auto_create( 0 );
		$term    = $this->cluster( 'Known', 'known' );
		$post_id = self::factory()->post->create();

		update_post_meta( $post_id, 'nh_topic_cluster', 'known' );

		$this->assertSame( array( $term ), $this->assigned( $post_id ) );
	}

	public function test_pending_alias_is_applied_on_the_next_save_once_the_term_exists() {
		$this->set_auto_create( 0 );
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'nh_topic_cluster', 'later' );
		$this->assertSame( array(), $this->assigned( $post_id ) );

		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Still nothing',
			)
		);
		$this->assertSame( array(), $this->assigned( $post_id ) );

		$term = $this->cluster( 'Later', 'later' );
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Now it applies',
			)
		);
		$this->assertSame( array( $term ), $this->assigned( $post_id ) );
		$this->assertSame( (string) $term, get_post_meta( $post_id, 'nh_primary_cluster', true ) );
	}

	public function test_existing_terms_are_never_removed() {
		$kept    = $this->cluster( 'Kept' );
		$post_id = self::factory()->post->create();
		wp_set_object_terms( $post_id, array( $kept ), 'topic_cluster' );

		update_post_meta( $post_id, 'nh_topic_cluster', 'Brand new' );

		$ids = $this->assigned( $post_id );
		$this->assertCount( 2, $ids );
		$this->assertContains( $kept, $ids );
		$new = array_values( array_diff( $ids, array( $kept ) ) )[0];
		$this->assertSame( (string) $new, get_post_meta( $post_id, 'nh_primary_cluster', true ), 'the alias cluster becomes primary' );
	}

	public function test_ordinary_terms_of_other_taxonomies_are_untouched() {
		$category = self::factory()->category->create();
		$post_id  = self::factory()->post->create( array( 'post_category' => array( $category ) ) );
		wp_set_post_tags( $post_id, array( 'keep-me' ) );

		update_post_meta( $post_id, 'nh_topic_cluster', 'Anything' );

		$this->assertContains( $category, wp_get_post_categories( $post_id ) );
		$this->assertSame( array( 'keep-me' ), wp_get_post_tags( $post_id, array( 'fields' => 'slugs' ) ) );
	}

	public function test_changing_the_alias_adds_the_new_cluster_and_keeps_the_old_one() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'nh_topic_cluster', 'First' );
		update_post_meta( $post_id, 'nh_topic_cluster', 'Second' );

		$ids = $this->assigned( $post_id );
		$this->assertCount( 2, $ids );
		$second = get_term_by( 'name', 'Second', 'topic_cluster' );
		$this->assertSame( (string) $second->term_id, get_post_meta( $post_id, 'nh_primary_cluster', true ) );
	}

	public function test_alias_given_at_insert_time_is_applied() {
		$post_id = wp_insert_post(
			array(
				'post_title'  => 'Imported',
				'post_status' => 'publish',
				'meta_input'  => array( 'nh_topic_cluster' => 'Imported cluster' ),
			)
		);

		$ids = $this->assigned( $post_id );
		$this->assertCount( 1, $ids );
		$this->assertSame( 'Imported cluster', get_term( $ids[0], 'topic_cluster' )->name );
	}

	public function test_save_post_applies_an_alias_stored_without_meta_hooks() {
		global $wpdb;
		$term    = $this->cluster( 'Imported', 'imported' );
		$post_id = self::factory()->post->create();
		$wpdb->insert(
			$wpdb->postmeta,
			array(
				'post_id'    => $post_id,
				'meta_key'   => 'nh_topic_cluster',
				'meta_value' => 'imported',
			)
		);
		wp_cache_delete( $post_id, 'post_meta' );
		$this->assertSame( array(), $this->assigned( $post_id ) );

		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Saved',
			)
		);

		$this->assertSame( array( $term ), $this->assigned( $post_id ) );
	}

	public function test_alias_is_applied_once_so_editor_choices_survive_later_saves() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'nh_topic_cluster', 'Workflow cluster' );
		$workflow = get_term_by( 'name', 'Workflow cluster', 'topic_cluster' )->term_id;

		// An editor adds a second cluster and makes it primary.
		$editor_cluster = $this->cluster( 'Editor choice' );
		wp_set_object_terms( $post_id, array( $editor_cluster ), 'topic_cluster', true );
		update_post_meta( $post_id, 'nh_primary_cluster', $editor_cluster );

		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Edited',
			)
		);
		$this->assertSame( (string) $editor_cluster, get_post_meta( $post_id, 'nh_primary_cluster', true ), 'saving does not reset the primary cluster' );

		// An editor removes the workflow cluster: saving must not bring it back.
		wp_remove_object_terms( $post_id, $workflow, 'topic_cluster' );
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Edited again',
			)
		);
		$this->assertSame( array( $editor_cluster ), $this->assigned( $post_id ) );
	}

	public function test_forced_sync_applies_the_alias_again_on_purpose() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'nh_topic_cluster', 'Workflow cluster' );
		$workflow = get_term_by( 'name', 'Workflow cluster', 'topic_cluster' )->term_id;
		wp_remove_object_terms( $post_id, $workflow, 'topic_cluster' );
		$this->assertSame( array(), $this->assigned( $post_id ) );

		$this->assertSame( $workflow, NH_SEO_Cluster_Sync::sync( $post_id, true ) );
		$this->assertSame( array( $workflow ), $this->assigned( $post_id ) );
	}

	public function test_repeating_a_sync_changes_nothing_and_fires_nothing() {
		$events = 0;
		add_action(
			'nh_seo_cluster_synced',
			static function () use ( &$events ) {
				++$events;
			}
		);
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'nh_topic_cluster', 'Stable' );
		$this->assertSame( 1, $events );

		$relationship_writes = 0;
		add_action(
			'set_object_terms',
			static function () use ( &$relationship_writes ) {
				++$relationship_writes;
			}
		);
		NH_SEO_Cluster_Sync::sync( $post_id, true );
		NH_SEO_Cluster_Sync::sync( $post_id, true );

		$this->assertSame( 1, $events );
		$this->assertSame( 0, $relationship_writes, 'an unchanged assignment is not rewritten' );
		$this->assertSame( 1, $this->count_clusters() );
	}

	public function test_deleting_the_alias_keeps_terms_but_forgets_what_was_applied() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'nh_topic_cluster', 'Gone soon' );
		$this->assertNotEmpty( get_post_meta( $post_id, NH_SEO_Cluster_Sync::SYNCED_META, true ) );

		delete_post_meta( $post_id, 'nh_topic_cluster' );

		$this->assertCount( 1, $this->assigned( $post_id ), 'terms are never removed' );
		$this->assertSame( '', get_post_meta( $post_id, NH_SEO_Cluster_Sync::SYNCED_META, true ) );
	}

	public function test_empty_alias_does_nothing() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'nh_topic_cluster', '   ' );
		$this->assertSame( array(), $this->assigned( $post_id ) );
		$this->assertSame( 0, $this->count_clusters() );
		$this->assertFalse( NH_SEO_Cluster_Sync::sync( $post_id, true ) );
	}

	public function test_sync_does_not_recurse() {
		$events = 0;
		add_action(
			'nh_seo_cluster_synced',
			static function () use ( &$events ) {
				++$events;
			}
		);
		$post_id = self::factory()->post->create();

		// A third-party callback saves the same post while the cluster is being attached.
		$nested = 0;
		add_action(
			'set_object_terms',
			static function ( $object_id, $terms, $tt_ids, $taxonomy ) use ( $post_id, &$nested ) {
				if ( $object_id === $post_id && 'topic_cluster' === $taxonomy && $nested < 5 ) {
					++$nested;
					wp_update_post(
						array(
							'ID'         => $post_id,
							'post_title' => 'Nested save ' . $nested,
						)
					);
				}
			},
			10,
			4
		);

		update_post_meta( $post_id, 'nh_topic_cluster', 'Recursion test' );

		$this->assertSame( 1, $events );
		$this->assertSame( 1, $nested, 'the nested save ran once and did not start another sync' );
		$this->assertCount( 1, $this->assigned( $post_id ) );
		$this->assertSame( 1, $this->count_clusters() );
	}

	public function test_revisions_and_autosaves_are_ignored() {
		$post_id  = self::factory()->post->create();
		$revision = wp_insert_post(
			array(
				'post_type'   => 'revision',
				'post_status' => 'inherit',
				'post_parent' => $post_id,
				'post_name'   => $post_id . '-revision-v1',
				'post_title'  => 'Revision',
			)
		);
		$autosave = wp_insert_post(
			array(
				'post_type'   => 'revision',
				'post_status' => 'inherit',
				'post_parent' => $post_id,
				'post_name'   => $post_id . '-autosave-v1',
				'post_title'  => 'Autosave',
			)
		);

		// update_post_meta() would redirect revision IDs to the parent post, so write to the revisions directly.
		update_metadata( 'post', $revision, 'nh_topic_cluster', 'From revision' );
		update_metadata( 'post', $autosave, 'nh_topic_cluster', 'From autosave' );
		do_action( 'save_post', $revision, get_post( $revision ), true );
		do_action( 'save_post', $autosave, get_post( $autosave ), true );

		$this->assertSame( 0, $this->count_clusters() );
		$this->assertFalse( NH_SEO_Cluster_Sync::sync( $revision, true ) );
		$this->assertFalse( NH_SEO_Cluster_Sync::sync( $autosave, true ) );
		$this->assertSame( array(), $this->assigned( $post_id ) );
	}

	public function test_pages_and_unknown_posts_are_ignored() {
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_post_meta( $page, 'nh_topic_cluster', 'Page cluster' );
		$this->assertSame( 0, $this->count_clusters() );
		$this->assertFalse( NH_SEO_Cluster_Sync::sync( $page, true ) );
		$this->assertFalse( NH_SEO_Cluster_Sync::sync( 999999, true ) );
		$this->assertFalse( NH_SEO_Cluster_Sync::sync( 0, true ) );
	}

	public function test_long_alias_is_truncated_to_the_contract_limit() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'nh_topic_cluster', str_repeat( 'a', 300 ) );

		$term = get_term( $this->assigned( $post_id )[0], 'topic_cluster' );
		$this->assertSame( NH_SEO_Keys::LIMIT_CLUSTER_ALIAS, strlen( $term->name ) );
	}

	public function test_markup_in_the_alias_never_reaches_the_term_name() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'nh_topic_cluster', '<script>alert(1)</script><b>Bold</b> topic' );

		$term = get_term( $this->assigned( $post_id )[0], 'topic_cluster' );
		$this->assertStringNotContainsString( '<', $term->name );
		$this->assertStringContainsString( 'Bold topic', $term->name );
	}

	public function test_a_term_created_by_a_concurrent_request_is_reused() {
		$racing = false;
		add_filter(
			'pre_insert_term',
			static function ( $term, $taxonomy ) use ( &$racing ) {
				if ( ! $racing && 'topic_cluster' === $taxonomy ) {
					$racing = true;
					wp_insert_term( $term, $taxonomy ); // The other request wins the race.
				}
				return $term;
			},
			10,
			2
		);
		$post_id = self::factory()->post->create();

		update_post_meta( $post_id, 'nh_topic_cluster', 'Raced' );

		$this->assertTrue( $racing );
		$this->assertSame( 1, $this->count_clusters() );
		$this->assertCount( 1, $this->assigned( $post_id ) );
	}

	public function test_filter_can_forbid_creating_clusters_but_not_assigning_existing_ones() {
		add_filter( 'nh_seo_cluster_can_create_term', '__return_false' );
		$existing = $this->cluster( 'Existing', 'existing' );

		$new_post = self::factory()->post->create();
		update_post_meta( $new_post, 'nh_topic_cluster', 'Unknown' );
		$this->assertSame( array(), $this->assigned( $new_post ) );
		$this->assertSame( 1, $this->count_clusters() );

		$known_post = self::factory()->post->create();
		update_post_meta( $known_post, 'nh_topic_cluster', 'existing' );
		$this->assertSame( array( $existing ), $this->assigned( $known_post ) );
	}

	public function test_filter_receives_the_alias_and_post() {
		$seen = array();
		add_filter(
			'nh_seo_cluster_can_create_term',
			static function ( $allowed, $alias, $post_id ) use ( &$seen ) {
				$seen = array( $allowed, $alias, $post_id );
				return $allowed;
			},
			10,
			3
		);
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'nh_topic_cluster', 'Probe' );
		$this->assertSame( array( true, 'Probe', $post_id ), $seen );
	}

	public function test_users_who_cannot_assign_terms_cannot_sync() {
		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$post_id    = self::factory()->post->create();
		update_post_meta( $post_id, 'nh_topic_cluster', 'Denied' );
		delete_post_meta( $post_id, NH_SEO_Cluster_Sync::SYNCED_META );
		wp_set_object_terms( $post_id, array(), 'topic_cluster' );
		$cluster_count = $this->count_clusters();

		wp_set_current_user( $subscriber );
		$this->assertFalse( NH_SEO_Cluster_Sync::sync( $post_id, true ) );
		$this->assertSame( $cluster_count, $this->count_clusters() );
		$this->assertSame( array(), $this->assigned( $post_id ) );
	}

	public function test_authors_may_create_clusters_like_they_may_create_tags() {
		$author  = self::factory()->user->create( array( 'role' => 'author' ) );
		$post_id = self::factory()->post->create( array( 'post_author' => $author ) );

		wp_set_current_user( $author );
		update_post_meta( $post_id, 'nh_topic_cluster', 'Author cluster' );

		$this->assertCount( 1, $this->assigned( $post_id ) );
	}

	public function test_contexts_without_a_user_are_trusted_for_cli_cron_and_imports() {
		wp_set_current_user( 0 );
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'nh_topic_cluster', 'Cron cluster' );
		$this->assertCount( 1, $this->assigned( $post_id ) );
	}

	public function test_hooks_are_registered_as_documented() {
		$this->assertIsInt( has_action( 'save_post', array( 'NH_SEO_Cluster_Sync', 'on_save_post' ) ) );
		$this->assertIsInt( has_action( 'rest_after_insert_post', array( 'NH_SEO_Cluster_Sync', 'on_rest_after_insert' ) ) );
		$this->assertIsInt( has_action( 'added_post_meta', array( 'NH_SEO_Cluster_Sync', 'on_meta_saved' ) ) );
		$this->assertIsInt( has_action( 'updated_post_meta', array( 'NH_SEO_Cluster_Sync', 'on_meta_saved' ) ) );
	}

	public function test_unrelated_meta_changes_do_not_trigger_the_sync() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'nh_topic_cluster', 'Probe' );
		delete_post_meta( $post_id, NH_SEO_Cluster_Sync::SYNCED_META );
		wp_set_object_terms( $post_id, array(), 'topic_cluster' );

		update_post_meta( $post_id, 'nh_summary', 'Something else' );
		update_post_meta( $post_id, '_edit_lock', '1:1' );

		$this->assertSame( array(), $this->assigned( $post_id ) );
	}
}
