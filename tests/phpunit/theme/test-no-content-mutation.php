<?php
/**
 * Showing pages never changes the content of the site or the settings other tools protect.
 *
 * The live blog is protected: posts, pages, categories, users, permalinks and options such as the site title,
 * the front-page settings and the rewrite rules must come out of a page view exactly as they went in. The
 * theme and the companion plugin may only write their own cache data (transients and the cache version).
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

/**
 * Read-only page views.
 */
class NH_Test_No_Content_Mutation extends NH_Render_Test_Case {

	/**
	 * A complete fixture site, with the permalinks of the live site.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->use_permalinks( '/%postname%/' );
		update_option( 'blogname', 'Thuê xe máy Nguyễn Hà' );
		update_option( 'blogdescription', 'Thuê xe máy, xe 50cc và xe điện tại Hà Nội' );
		update_option( 'show_on_front', 'posts' );
		$this->build_site( 14 );
	}

	/**
	 * Everything a page view must not change, as comparable rows.
	 *
	 * Transients and the cache version of the theme (own cache data) and the cron schedule are the only rows left out.
	 *
	 * @return array<string, array>
	 */
	private function snapshot() {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$queries = array(
			'posts'              => "SELECT ID, post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt, post_status, comment_status, post_password, post_name, post_modified, post_parent, guid, menu_order, post_type, comment_count FROM {$wpdb->posts} ORDER BY ID",
			'postmeta'           => "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} ORDER BY meta_id",
			'terms'              => "SELECT term_id, name, slug, term_group FROM {$wpdb->terms} ORDER BY term_id",
			'term_taxonomy'      => "SELECT term_taxonomy_id, term_id, taxonomy, description, parent, count FROM {$wpdb->term_taxonomy} ORDER BY term_taxonomy_id",
			'term_relationships' => "SELECT object_id, term_taxonomy_id, term_order FROM {$wpdb->term_relationships} ORDER BY object_id, term_taxonomy_id",
			'termmeta'           => "SELECT term_id, meta_key, meta_value FROM {$wpdb->termmeta} ORDER BY meta_id",
			'comments'           => "SELECT comment_ID, comment_post_ID, comment_approved, comment_content FROM {$wpdb->comments} ORDER BY comment_ID",
			'users'              => "SELECT ID, user_login, user_nicename, user_email, display_name, user_status FROM {$wpdb->users} ORDER BY ID",
			'usermeta'           => "SELECT user_id, meta_key, meta_value FROM {$wpdb->usermeta} WHERE meta_key <> 'session_tokens' ORDER BY umeta_id",
			'options'            => "SELECT option_name, option_value, autoload FROM {$wpdb->options}"
				. " WHERE option_name NOT LIKE '\\_transient%' AND option_name NOT LIKE '\\_site\\_transient%'"
				. " AND option_name NOT LIKE 'nh\\_editorial\\_%' AND option_name <> 'cron' ORDER BY option_name",
		);

		$rows = array();
		foreach ( $queries as $name => $sql ) {
			$rows[ $name ] = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		// phpcs:enable

		$rows['options'] = $this->without_core_bookkeeping( $rows['options'] );
		return $rows;
	}

	/**
	 * Leave out what WordPress core itself records the first time a page is shown, whichever theme is active:
	 * the security salts it generates when wp-config.php defines no distinct ones (the test configuration uses one placeholder for all eight; a real site defines eight different values), and the
	 * marker that this theme has no Additional CSS post.
	 *
	 * @param array[] $options Rows of the options table.
	 * @return array[]
	 */
	private function without_core_bookkeeping( array $options ) {
		$kept = array();
		foreach ( $options as $row ) {
			if ( preg_match( '/^(auth|secure_auth|logged_in|nonce)_(key|salt)$/', $row['option_name'] ) ) {
				continue;
			}
			if ( 0 === strpos( $row['option_name'], 'theme_mods_' ) ) {
				$mods = maybe_unserialize( $row['option_value'] );
				if ( is_array( $mods ) ) {
					unset( $mods['custom_css_post_id'] );
				}
				if ( array() === $mods || false === $mods ) {
					continue;
				}
				$row['option_value'] = maybe_serialize( $mods );
			}
			$kept[] = $row;
		}
		return $kept;
	}

	/**
	 * Every kind of page of the theme, as a visitor requests them.
	 *
	 * @return string[] URLs.
	 */
	private function urls() {
		$site = $this->site;
		return array(
			home_url( '/' ),
			home_url( '/page/2/' ),
			get_permalink( $site['rich'] ),
			get_permalink( $site['plain'] ),
			get_permalink( $site['protected'] ),
			get_permalink( $site['pillar'] ),
			get_permalink( $site['wide'] ),
			get_permalink( $site['page'] ),
			get_category_link( $site['top'] ),
			get_category_link( $site['child'] ),
			get_category_link( $site['empty'] ),
			get_tag_link( $site['tag'] ),
			get_author_posts_url( $site['author'] ),
			home_url( '/?s=guide' ),
			home_url( '/?s=zzzzqqqq' ),
			home_url( '/no-such-page-xyz/' ),
		);
	}

	/**
	 * Visit every URL, then ask for search suggestions the way the page script does.
	 *
	 * @return void
	 */
	private function visit_everything() {
		foreach ( $this->urls() as $url ) {
			$html = $this->render_url( $url );
			$this->assertStringContainsString( '<h1', $html, 'a real page was rendered: ' . $url );
		}

		$request = new WP_REST_Request( 'GET', '/wp/v2/search' );
		$request->set_param( 'search', 'guide' );
		$request->set_param( 'per_page', 5 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
	}

	public function test_showing_pages_changes_no_content_term_user_or_protected_option() {
		$before = $this->snapshot();
		$this->assertNotEmpty( $before['posts'] );
		$this->assertNotEmpty( $before['term_relationships'] );

		$this->visit_everything();
		$after_first = $this->snapshot();

		$this->visit_everything();
		$after_second = $this->snapshot();

		foreach ( array_keys( $before ) as $table ) {
			$this->assertSame( array(), $this->changes( $before[ $table ], $after_first[ $table ] ), "the first page views changed {$table}" );
			$this->assertSame( array(), $this->changes( $before[ $table ], $after_second[ $table ] ), "repeated page views changed {$table}" );
		}
	}

	/**
	 * Rows that were added, removed or changed between two snapshots of a table, shortened for the failure message.
	 *
	 * @param array[] $before Rows before.
	 * @param array[] $after  Rows after.
	 * @return string[]
	 */
	private function changes( array $before, array $after ) {
		$old     = array_map( 'wp_json_encode', $before );
		$new     = array_map( 'wp_json_encode', $after );
		$changes = array();
		foreach ( array_diff( $old, $new ) as $row ) {
			$changes[] = 'before: ' . substr( $row, 0, 240 );
		}
		foreach ( array_diff( $new, $old ) as $row ) {
			$changes[] = 'after: ' . substr( $row, 0, 240 );
		}
		return $changes;
	}

	public function test_the_snapshot_would_notice_a_change() {
		$before = $this->snapshot();

		update_option( 'blogname', 'Changed by a page view' );
		wp_update_post(
			array(
				'ID'         => $this->site['plain'],
				'post_title' => 'Changed by a page view',
			)
		);
		wp_update_term( $this->site['child'], 'category', array( 'parent' => 0 ) );
		$after = $this->snapshot();

		$this->assertNotSame( array(), $this->changes( $before['options'], $after['options'] ), 'an option change is seen' );
		$this->assertNotSame( array(), $this->changes( $before['posts'], $after['posts'] ), 'a post change is seen' );
		$this->assertNotSame( array(), $this->changes( $before['term_taxonomy'], $after['term_taxonomy'] ), 'a category parent change is seen' );
		$this->assertSame( array(), $this->changes( $before['users'], $after['users'] ), 'and an untouched table reports nothing' );
	}

	public function test_the_front_page_settings_and_the_site_title_are_untouched() {
		$this->visit_everything();
		$this->assertSame( 'posts', get_option( 'show_on_front' ) );
		$this->assertSame( '0', (string) get_option( 'page_on_front' ) );
		$this->assertSame( '0', (string) get_option( 'page_for_posts' ) );
		$this->assertSame( 'Thuê xe máy Nguyễn Hà', get_option( 'blogname' ) );
		$this->assertSame( 'Thuê xe máy, xe 50cc và xe điện tại Hà Nội', get_option( 'blogdescription' ) );
		$this->assertSame( '/%postname%/', get_option( 'permalink_structure' ) );
	}

	public function test_category_ids_parents_and_slugs_are_stable_across_page_views() {
		$site   = $this->site;
		$child  = get_term( $site['child'], 'category' );
		$parent = $child->parent;
		$slug   = $child->slug;
		$link   = get_category_link( $child );
		$post   = get_permalink( $site['rich'] );

		$this->visit_everything();

		$child = get_term( $site['child'], 'category' );
		$this->assertSame( $parent, $child->parent );
		$this->assertSame( $slug, $child->slug );
		$this->assertSame( $link, get_category_link( $child ), 'the category URL did not move' );
		$this->assertSame( $post, get_permalink( $site['rich'] ), 'the post URL did not move' );
	}

	public function test_no_page_view_makes_an_outgoing_http_request() {
		$requests = array();
		add_filter(
			'pre_http_request',
			static function ( $preempt, $args, $url ) use ( &$requests ) {
				$requests[] = $url;
				return new WP_Error( 'blocked', 'No outgoing request is allowed while showing a page.' );
			},
			10,
			3
		);

		$this->visit_everything();

		$this->assertSame( array(), $requests, 'a page view contacted another host' );
	}
}
