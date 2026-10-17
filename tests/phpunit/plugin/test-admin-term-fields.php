<?php
/**
 * Tests for the Pillar field on category and Topic Cluster screens.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * NH_SEO_Term_Fields.
 */
class NH_Test_Admin_Term_Fields extends NH_Unit_Test_Case {

	/**
	 * User IDs by role.
	 *
	 * @var int[]
	 */
	private $users = array();

	public function set_up() {
		parent::set_up();
		foreach ( array( 'administrator', 'editor', 'author', 'subscriber' ) as $role ) {
			$this->users[ $role ] = self::factory()->user->create( array( 'role' => $role ) );
		}
		wp_set_current_user( $this->users['editor'] );
	}

	/**
	 * Create a term.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return int
	 */
	private function term( $taxonomy = 'category' ) {
		return self::factory()->term->create( array( 'taxonomy' => $taxonomy ) );
	}

	/**
	 * Simulate a term form submission and run the save handler.
	 *
	 * @param int         $term_id  Term ID.
	 * @param array       $fields   Extra $_POST fields (the Pillar field is usually among them).
	 * @param string|bool $nonce    Nonce value, null for a valid one, false to omit.
	 * @param string|null $taxonomy Taxonomy the nonce is created for (defaults to the term's).
	 * @return void
	 */
	private function submit( $term_id, array $fields, $nonce = null, $taxonomy = null ) {
		$term     = get_term( $term_id );
		$taxonomy = $taxonomy ? $taxonomy : $term->taxonomy;
		$post     = $fields;
		if ( null === $nonce ) {
			$post['nh_seo_term_nonce'] = wp_create_nonce( NH_SEO_Term_Fields::nonce_action( $taxonomy ) );
		} elseif ( false !== $nonce ) {
			$post['nh_seo_term_nonce'] = $nonce;
		}
		$_POST = wp_slash( $post );
		NH_SEO_Term_Fields::save( $term_id );
	}

	/**
	 * Queued notices of the current user.
	 *
	 * @return array
	 */
	private function notices() {
		$queue = get_transient( NH_SEO_Admin_Notices::TRANSIENT_PREFIX . get_current_user_id() );
		return is_array( $queue ) ? $queue : array();
	}

	public function test_the_hooks_cover_categories_and_clusters_only() {
		foreach ( array( 'category', 'topic_cluster' ) as $taxonomy ) {
			$this->assertIsInt( has_action( $taxonomy . '_add_form_fields', array( 'NH_SEO_Term_Fields', 'render_add_field' ) ), $taxonomy );
			$this->assertIsInt( has_action( $taxonomy . '_edit_form_fields', array( 'NH_SEO_Term_Fields', 'render_edit_field' ) ), $taxonomy );
			$this->assertIsInt( has_action( 'created_' . $taxonomy, array( 'NH_SEO_Term_Fields', 'save' ) ), $taxonomy );
			$this->assertIsInt( has_action( 'edited_' . $taxonomy, array( 'NH_SEO_Term_Fields', 'save' ) ), $taxonomy );
		}
		$this->assertFalse( has_action( 'post_tag_add_form_fields', array( 'NH_SEO_Term_Fields', 'render_add_field' ) ) );
		$this->assertFalse( has_action( 'edited_post_tag', array( 'NH_SEO_Term_Fields', 'save' ) ) );
	}

	public function test_the_add_form_has_a_nonce_and_an_empty_field() {
		ob_start();
		NH_SEO_Term_Fields::render_add_field( 'category' );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'name="nh_seo_term_nonce"', $html );
		$this->assertStringContainsString( 'name="nh_seo_pillar_id"', $html );
		$this->assertStringContainsString( 'value=""', $html );
		$this->assertStringContainsString( 'class="form-field', $html );
		$this->assertStringNotContainsString( '_wp_http_referer', $html, 'the core form already carries a referer field' );
		$this->assertSame( 1, preg_match( '/name="nh_seo_term_nonce" value="([^"]+)"/', $html, $match ) );
		$this->assertNotFalse( wp_verify_nonce( $match[1], NH_SEO_Term_Fields::nonce_action( 'category' ) ) );
		$this->assertFalse( wp_verify_nonce( $match[1], NH_SEO_Term_Fields::nonce_action( 'topic_cluster' ) ), 'the nonce is bound to the taxonomy' );
	}

	public function test_unsupported_taxonomies_render_nothing() {
		ob_start();
		NH_SEO_Term_Fields::render_add_field( 'post_tag' );
		NH_SEO_Term_Fields::render_edit_field( get_term( $this->term( 'post_tag' ) ), 'post_tag' );
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_the_edit_form_shows_the_current_pillar_with_its_title() {
		$pillar = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Scooter <i>guide</i> "2026"',
			)
		);
		$term   = $this->term( 'topic_cluster' );
		update_term_meta( $term, 'nh_pillar_id', $pillar );

		ob_start();
		NH_SEO_Term_Fields::render_edit_field( get_term( $term ), 'topic_cluster' );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'value="' . $pillar . '"', $html );
		$this->assertStringContainsString( 'Scooter guide', $html );
		$this->assertStringNotContainsString( '<i>guide</i>', $html );
		$this->assertStringContainsString( '<tr class="form-field', $html );
		$this->assertStringContainsString( 'scope="row"', $html );
	}

	public function test_a_valid_pillar_is_saved_for_categories_and_clusters() {
		$pillar = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		foreach ( array( 'category', 'topic_cluster' ) as $taxonomy ) {
			$term = $this->term( $taxonomy );
			$this->submit( $term, array( 'nh_seo_pillar_id' => " $pillar " ) );
			$this->assertSame( (string) $pillar, get_term_meta( $term, 'nh_pillar_id', true ), $taxonomy );
		}
		$this->assertSame( array(), $this->notices() );
	}

	public function test_pages_can_be_pillars_too() {
		$page = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
		$term = $this->term();

		$this->submit( $term, array( 'nh_seo_pillar_id' => (string) $page ) );

		$this->assertSame( (string) $page, get_term_meta( $term, 'nh_pillar_id', true ) );
	}

	public function test_an_invalid_pillar_is_dropped_and_the_editor_is_told() {
		$draft   = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		$private = self::factory()->post->create( array( 'post_status' => 'private' ) );
		$term    = $this->term();

		foreach ( array( (string) $draft, (string) $private, '999999', 'abc', '12abc', '-5' ) as $bad ) {
			$good = self::factory()->post->create( array( 'post_status' => 'publish' ) );
			update_term_meta( $term, 'nh_pillar_id', $good );
			delete_transient( NH_SEO_Admin_Notices::TRANSIENT_PREFIX . get_current_user_id() );

			$this->submit( $term, array( 'nh_seo_pillar_id' => $bad ) );

			$this->assertFalse( metadata_exists( 'term', $term, 'nh_pillar_id' ), 'value: ' . $bad );
			$notices = $this->notices();
			$this->assertCount( 1, $notices, 'value: ' . $bad );
			$this->assertStringContainsString( 'was ignored', $notices[0]['message'] );
		}
	}

	public function test_an_empty_pillar_removes_it_without_a_notice() {
		$pillar = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$term   = $this->term();
		update_term_meta( $term, 'nh_pillar_id', $pillar );

		$this->submit( $term, array( 'nh_seo_pillar_id' => '  ' ) );

		$this->assertFalse( metadata_exists( 'term', $term, 'nh_pillar_id' ) );
		$this->assertSame( array(), $this->notices() );
	}

	public function test_a_missing_or_invalid_nonce_saves_nothing() {
		$pillar = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$other  = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$term   = $this->term();
		update_term_meta( $term, 'nh_pillar_id', $other );

		$this->submit( $term, array( 'nh_seo_pillar_id' => (string) $pillar ), false );
		$this->assertSame( (string) $other, get_term_meta( $term, 'nh_pillar_id', true ), 'no nonce' );

		foreach ( array( '', 'nonsense', wp_create_nonce( 'another_action' ) ) as $bad ) {
			$this->submit( $term, array( 'nh_seo_pillar_id' => (string) $pillar ), $bad );
			$this->assertSame( (string) $other, get_term_meta( $term, 'nh_pillar_id', true ), 'nonce: ' . $bad );
		}

		$this->submit( $term, array( 'nh_seo_pillar_id' => (string) $pillar ), null, 'topic_cluster' );
		$this->assertSame( (string) $other, get_term_meta( $term, 'nh_pillar_id', true ), 'a nonce made for another taxonomy' );
	}

	public function test_users_who_cannot_edit_the_term_save_nothing() {
		$pillar = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$other  = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$term   = $this->term();
		update_term_meta( $term, 'nh_pillar_id', $other );

		foreach ( array( 'author', 'subscriber' ) as $role ) {
			wp_set_current_user( $this->users[ $role ] );
			$this->assertFalse( current_user_can( 'edit_term', $term ), $role );
			$this->submit( $term, array( 'nh_seo_pillar_id' => (string) $pillar ) ); // The nonce is valid for this user.
			$this->assertSame( (string) $other, get_term_meta( $term, 'nh_pillar_id', true ), $role );
		}

		wp_set_current_user( 0 );
		$this->submit( $term, array( 'nh_seo_pillar_id' => (string) $pillar ) );
		$this->assertSame( (string) $other, get_term_meta( $term, 'nh_pillar_id', true ), 'logged out' );
	}

	public function test_a_form_without_the_field_keeps_the_stored_pillar() {
		$pillar = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$term   = $this->term();
		update_term_meta( $term, 'nh_pillar_id', $pillar );

		// Quick edit and other inline forms post a valid term nonce at best, never our field.
		$this->submit( $term, array( 'name' => 'Renamed' ) );

		$this->assertSame( (string) $pillar, get_term_meta( $term, 'nh_pillar_id', true ) );
	}

	public function test_other_taxonomies_are_ignored() {
		$pillar = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$tag    = $this->term( 'post_tag' );
		$_POST  = wp_slash(
			array(
				'nh_seo_term_nonce' => wp_create_nonce( NH_SEO_Term_Fields::nonce_action( 'post_tag' ) ),
				'nh_seo_pillar_id'  => (string) $pillar,
			)
		);

		NH_SEO_Term_Fields::save( $tag );

		$this->assertFalse( metadata_exists( 'term', $tag, 'nh_pillar_id' ) );
		NH_SEO_Term_Fields::save( 999999 );
		NH_SEO_Term_Fields::save( 0 );
	}

	public function test_the_edit_and_create_hooks_run_the_handler() {
		$pillar = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$term   = $this->term();

		$_POST = wp_slash(
			array(
				'nh_seo_term_nonce' => wp_create_nonce( NH_SEO_Term_Fields::nonce_action( 'category' ) ),
				'nh_seo_pillar_id'  => (string) $pillar,
			)
		);
		wp_update_term( $term, 'category', array( 'description' => 'Edited' ) );
		$this->assertSame( (string) $pillar, get_term_meta( $term, 'nh_pillar_id', true ), 'edited_category' );

		$_POST   = wp_slash(
			array(
				'nh_seo_term_nonce' => wp_create_nonce( NH_SEO_Term_Fields::nonce_action( 'topic_cluster' ) ),
				'nh_seo_pillar_id'  => (string) $pillar,
			)
		);
		$created = wp_insert_term( 'A new cluster', 'topic_cluster' );
		$this->assertSame( (string) $pillar, get_term_meta( $created['term_id'], 'nh_pillar_id', true ), 'created_topic_cluster' );
	}

	public function test_terms_created_without_the_form_get_no_pillar() {
		$_POST   = array();
		$created = wp_insert_term( 'Created by a workflow', 'topic_cluster' );

		$this->assertFalse( metadata_exists( 'term', $created['term_id'], 'nh_pillar_id' ) );
	}
}
