<?php
/**
 * Search index storage and maintenance: table, documents, which posts are indexed, hooks, cron walks.
 *
 * Runs in autocommit mode against InnoDB FULLTEXT (see NH_Search_Test_Case for why).
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

// Autocommit base class.
require_once __DIR__ . '/class-nh-search-test-case.php';

/**
 * Index maintenance tests.
 */
class NH_Test_Search_Index extends NH_Search_Test_Case {

	public function test_table_is_innodb_with_three_fulltext_indexes() {
		global $wpdb;
		$table = NH_SEO_Search_Index::table();
		$this->assertSame( $wpdb->prefix . 'nh_search_index', $table );
		$this->assertTrue( NH_SEO_Search_Index::table_exists() );

		$create = $wpdb->get_row( "SHOW CREATE TABLE {$table}", ARRAY_N )[1];
		$this->assertStringContainsString( 'ENGINE=InnoDB', $create );
		$this->assertStringContainsString( 'utf8mb4', $create );
		foreach ( array( 'post_id', 'post_type', 'title_fold', 'tax_fold', 'body_fold', 'updated' ) as $column ) {
			$this->assertStringContainsString( '`' . $column . '`', $create );
		}
		$this->assertMatchesRegularExpression( '/PRIMARY KEY \(`post_id`\)/', $create );
		$this->assertMatchesRegularExpression( '/FULLTEXT KEY `ft_all` \(`title_fold`,`tax_fold`,`body_fold`\)/', $create );
		$this->assertMatchesRegularExpression( '/FULLTEXT KEY `ft_title` \(`title_fold`\)/', $create );
		$this->assertMatchesRegularExpression( '/FULLTEXT KEY `ft_tax` \(`tax_fold`\)/', $create );
		$this->assertMatchesRegularExpression( '/`post_id` bigint\(20\) unsigned NOT NULL/', $create );
		$this->assertMatchesRegularExpression( '/`body_fold` longtext NOT NULL/', $create );
	}

	public function test_create_table_is_idempotent_and_keeps_the_data() {
		global $wpdb;
		$post_id = $this->make_post( array( 'post_title' => 'Idempotent table' ) );
		$this->flush_index();
		$this->assertNotNull( NH_SEO_Search_Index::get( $post_id ) );

		$this->assertTrue( NH_SEO_Search_Index::create_table() );
		$this->assertTrue( NH_SEO_Search_Index::create_table() );

		$this->assertNotNull( NH_SEO_Search_Index::get( $post_id ), 'Running dbDelta again must not touch the rows.' );
		$indexes = $wpdb->get_results( 'SHOW INDEX FROM ' . NH_SEO_Search_Index::table() );
		$names   = array_unique( wp_list_pluck( $indexes, 'Key_name' ) );
		sort( $names );
		$this->assertSame( array( 'PRIMARY', 'ft_all', 'ft_tax', 'ft_title' ), $names, 'No duplicated or extra indexes after repeated upgrades.' );
		$this->assertCount( 6, $indexes, 'One row per indexed column: 1 primary + 3 + 1 + 1.' );
	}

	public function test_upgrade_hook_recreates_a_missing_table() {
		NH_SEO_Search_Index::drop_table();
		$this->assertFalse( NH_SEO_Search_Index::table_exists() );

		do_action( 'nh_seo_upgrade', '', NH_SEO_DB_VERSION );
		NH_SEO_Search_Index::forget_table_state();
		$this->assertTrue( NH_SEO_Search_Index::table_exists() );

		// And the plugin's own lifecycle: activation creates the table before it fires the upgrade action.
		NH_SEO_Search_Index::drop_table();
		NH_SEO_Plugin::activate();
		NH_SEO_Search_Index::forget_table_state();
		$this->assertTrue( NH_SEO_Search_Index::table_exists() );
	}

	public function test_activation_lifts_an_old_pause_but_never_builds_anything() {
		NH_SEO_Search_State::trip( 'old error' );
		$this->assertNotNull( NH_SEO_Search_State::breaker() );
		$this->make_post( array( 'post_title' => 'Existing post' ) );

		do_action( 'nh_seo_activate' );

		$this->assertNull( NH_SEO_Search_State::breaker() );
		$this->assertSame( 0, NH_SEO_Search_Index::count(), 'Building is always an explicit action.' );
		$this->assertFalse( NH_SEO_Search_State::get()['ready'] );
	}

	public function test_embedded_stop_word_list_equals_the_default_list_of_this_server() {
		global $wpdb;
		$suppress = $wpdb->suppress_errors( true );
		$server   = $wpdb->get_col( 'SELECT value FROM information_schema.INNODB_FT_DEFAULT_STOPWORD' );
		$wpdb->suppress_errors( $suppress );
		if ( empty( $server ) ) {
			$this->markTestSkipped( 'information_schema.INNODB_FT_DEFAULT_STOPWORD is not readable on this server.' );
		}
		$server = array_values( array_unique( $server ) );
		$ours   = NH_SEO_Search_Text::default_stopwords();
		sort( $server );
		sort( $ours );
		$this->assertSame( $server, $ours );
	}

	public function test_document_holds_folded_title_taxonomy_words_and_unique_body_tokens() {
		// An administrator may save unfiltered HTML, so the script element below really reaches the content.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		register_taxonomy( 'topic_cluster', 'post', array( 'public' => false ) );
		try {
			$category = self::factory()->category->create( array( 'name' => 'Thuê xe máy' ) );
			$cluster  = wp_insert_term( 'Xe số tự động', 'topic_cluster' );

			$content = '<!-- wp:heading --><h2>Giấy tờ cần thiết</h2><!-- /wp:heading --><p>Bạn cần bằng lái và hộ chiếu. Bằng lái xe máy rất quan trọng.</p>[gallery ids="1"]<script>var hiddenscript = 1;</script>';
			$post_id = $this->make_post(
				array(
					'post_title'    => 'Thuê xe máy Hà Nội: giá và thủ tục',
					'post_content'  => $content,
					'post_excerpt'  => 'Tóm tắt thủ tục',
					'post_category' => array( $category ),
					'tags_input'    => array( 'Du lịch', 'Scooter' ),
				)
			);
			// A network (multisite) strips scripts from posts saved without the unfiltered_html capability: store the raw content.
			global $wpdb;
			$wpdb->update( $wpdb->posts, array( 'post_content' => $content ), array( 'ID' => $post_id ) );
			clean_post_cache( $post_id );
			wp_set_object_terms( $post_id, array( (int) $cluster['term_id'] ), 'topic_cluster' );
			update_post_meta( $post_id, NH_SEO_Keys::META_KEYWORDS, 'xe tay ga, đặt xe online' );
			update_post_meta( $post_id, NH_SEO_Keys::META_SUMMARY, 'Câu trả lời nhanh' );
			update_post_meta( $post_id, NH_SEO_Keys::META_TAKEAWAYS, array( 'Mang theo hộ chiếu' ) );
			update_post_meta(
				$post_id,
				NH_SEO_Keys::META_FAQ,
				array(
					array(
						'question' => 'Cần đặt cọc không?',
						'answer'   => '<p>Tùy từng cửa hàng.</p>',
					),
				)
			);
			update_post_meta( $post_id, '_yoast_wpseo_focuskw', 'thuê xe máy hà nội' );
			update_post_meta( $post_id, 'rank_math_focus_keyword', 'cho thuê xe, giá rẻ' );

			$document = NH_SEO_Search_Document::build( get_post( $post_id ) );
		} finally {
			unregister_taxonomy( 'topic_cluster' );
		}

		$this->assertSame( 'post', $document['post_type'] );
		$this->assertSame( 'thue xe may ha noi gia va thu tuc', $document['title_fold'] );

		$tax = explode( ' ', $document['tax_fold'] );
		foreach ( array( 'thue', 'xe', 'may', 'du', 'lich', 'scooter', 'so', 'tu', 'dong', 'tay', 'ga', 'dat', 'online', 'ha', 'noi', 'cho', 'gia', 're' ) as $word ) {
			$this->assertContains( $word, $tax, $word . ' (category, tag, cluster, keywords or focus keywords)' );
		}

		$body = explode( ' ', $document['body_fold'] );
		$this->assertSame( array( 'cau', 'tra', 'loi', 'nhanh' ), array_slice( $body, 0, 4 ), 'The summary comes first.' );
		$this->assertSame( array( 'tom', 'tat', 'thu', 'tuc' ), array_slice( $body, 4, 4 ), 'Then the manual excerpt.' );
		$this->assertSame( array( 'giay', 'can', 'thiet' ), array_slice( $body, 8, 3 ), 'Then the headings.' );
		foreach ( array( 'bang', 'lai', 'chieu', 'quan', 'trong', 'mang', 'theo', 'dat', 'coc', 'tung', 'cua', 'hang' ) as $word ) {
			$this->assertContains( $word, $body, $word . ' from the content, takeaways or FAQ' );
		}
		foreach ( array( 'hiddenscript', 'gallery', 'var', 'script', 'wp', 'heading' ) as $word ) {
			$this->assertNotContains( $word, $body );
		}
		$this->assertSame( $body, array_values( array_unique( $body ) ), 'Body tokens are unique.' );
		foreach ( $body as $token ) {
			$this->assertGreaterThanOrEqual( 3, strlen( $token ), 'Body tokens are at least three characters.' );
		}
		$this->assertSame( $document['title_fold'], strtolower( $document['title_fold'] ) );
	}

	public function test_body_token_cap_is_filterable_and_bounded() {
		$post_id = $this->make_post( array( 'post_content' => implode( ' ', array_map( 'strval', range( 100, 999 ) ) ) ) );

		$this->assertCount( 900, explode( ' ', NH_SEO_Search_Document::build( get_post( $post_id ) )['body_fold'] ) );

		add_filter(
			'nh_seo_search_body_token_cap',
			static function () {
				return 150;
			}
		);
		$this->assertCount( 150, explode( ' ', NH_SEO_Search_Document::build( get_post( $post_id ) )['body_fold'] ) );

		remove_all_filters( 'nh_seo_search_body_token_cap' );
		add_filter(
			'nh_seo_search_body_token_cap',
			static function () {
				return 1;
			}
		);
		$this->assertCount( 100, explode( ' ', NH_SEO_Search_Document::build( get_post( $post_id ) )['body_fold'] ), 'The cap never drops below 100.' );
	}

	public function test_only_public_posts_of_indexed_types_are_indexed() {
		$published  = $this->make_post( array( 'post_title' => 'Published' ) );
		$page       = $this->make_post(
			array(
				'post_type'  => 'page',
				'post_title' => 'A page',
			)
		);
		$excluded   = array(
			'draft'    => $this->make_post( array( 'post_status' => 'draft' ) ),
			'pending'  => $this->make_post( array( 'post_status' => 'pending' ) ),
			'private'  => $this->make_post( array( 'post_status' => 'private' ) ),
			'future'   => $this->make_post(
				array(
					'post_status' => 'future',
					'post_date'   => gmdate( 'Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS ),
				)
			),
			'trash'    => $this->make_post( array( 'post_status' => 'trash' ) ),
			'password' => $this->make_post( array( 'post_password' => 'secret' ) ),
		);
		$attachment = self::factory()->attachment->create_object( 'index-test.jpg', 0, array( 'post_mime_type' => 'image/jpeg' ) );
		$revision   = wp_save_post_revision( $published );

		$this->flush_index();
		$this->assertSame( array( $published, $page ), NH_SEO_Search_Index::ids() );
		foreach ( $excluded as $label => $id ) {
			$this->assertNull( NH_SEO_Search_Index::get( $id ), $label . ' must not be indexed.' );
		}
		$this->assertNull( NH_SEO_Search_Index::get( $attachment ) );
		$this->assertTrue( ! $revision || null === NH_SEO_Search_Index::get( $revision ), 'Revisions are never indexed.' );
		$this->assertSame( 'page', NH_SEO_Search_Index::get( $page )['post_type'] );
	}

	public function test_the_indexed_post_types_are_filterable() {
		register_post_type(
			'nh_guide',
			array(
				'public'       => true,
				'show_in_rest' => true,
			)
		);
		try {
			$guide = $this->make_post(
				array(
					'post_type'  => 'nh_guide',
					'post_title' => 'A guide',
				)
			);
			$this->flush_index();
			$this->assertNull( NH_SEO_Search_Index::get( $guide ), 'Not indexed by default.' );

			add_filter(
				'nh_seo_search_post_types',
				static function ( $types ) {
					$types[] = 'nh_guide';
					$types[] = 'does_not_exist';
					$types[] = 'revision';
					return $types;
				}
			);
			$this->assertSame( array( 'post', 'page', 'nh_guide' ), NH_SEO_Search_Index::indexable_types(), 'Unknown and non-viewable types are ignored.' );

			wp_update_post(
				array(
					'ID'         => $guide,
					'post_title' => 'A guide again',
				)
			);
			$this->flush_index();
			$this->assertNotNull( NH_SEO_Search_Index::get( $guide ) );
		} finally {
			unregister_post_type( 'nh_guide' );
		}
	}

	public function test_saving_queues_the_post_once_and_the_flush_writes_the_final_state() {
		$post_id = $this->make_post( array( 'post_title' => 'First title' ) );
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Second title',
			)
		);
		update_post_meta( $post_id, NH_SEO_Keys::META_KEYWORDS, 'final keyword' );
		wp_set_object_terms( $post_id, array( 'finaltag' ), 'post_tag' );

		$this->assertSame( array( $post_id ), NH_SEO_Search_Indexer::pending(), 'Many changes, one queue entry.' );
		$this->assertNull( NH_SEO_Search_Index::get( $post_id ), 'Nothing is written before the flush.' );
		$this->assertSame( 5, has_action( 'shutdown', array( 'NH_SEO_Search_Indexer', 'flush' ) ), 'The flush runs at shutdown.' );

		$this->record_sql();
		$this->flush_index();
		$writes = $this->sql_containing( 'REPLACE INTO' );
		$this->stop_sql_log();
		$this->assertCount( 1, $writes, 'The row is written once, from the final state.' );

		$row = NH_SEO_Search_Index::get( $post_id );
		$this->assertSame( 'second title', $row['title_fold'] );
		$this->assertStringContainsString( 'final keyword', $row['tax_fold'] );
		$this->assertStringContainsString( 'finaltag', $row['tax_fold'] );
		$this->assertSame( array(), NH_SEO_Search_Indexer::pending() );
	}

	public function test_an_update_replaces_the_old_words() {
		$post_id = $this->make_post(
			array(
				'post_title'   => 'Oldtitle words',
				'post_content' => 'Oldcontent here',
			)
		);
		$this->flush_index();
		$this->assertStringContainsString( 'oldtitle', NH_SEO_Search_Index::get( $post_id )['title_fold'] );

		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_title'   => 'Newtitle words',
				'post_content' => 'Newcontent here',
			)
		);
		$this->flush_index();

		$row = NH_SEO_Search_Index::get( $post_id );
		$this->assertStringContainsString( 'newtitle', $row['title_fold'] );
		$this->assertStringNotContainsString( 'oldtitle', $row['title_fold'] );
		$this->assertStringContainsString( 'newcontent', $row['body_fold'] );
		$this->assertStringNotContainsString( 'oldcontent', $row['body_fold'] );
		$this->assertCount( 1, NH_SEO_Search_Index::ids() );
	}

	public function test_leaving_publish_removes_the_row_at_once_and_coming_back_restores_it() {
		$post_id = $this->make_post( array( 'post_title' => 'Moving post' ) );
		$this->flush_index();
		$this->assertNotNull( NH_SEO_Search_Index::get( $post_id ) );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'draft',
			)
		);
		$this->assertNull( NH_SEO_Search_Index::get( $post_id ), 'Unpublishing removes the row immediately, without waiting for the flush.' );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);
		$this->assertNull( NH_SEO_Search_Index::get( $post_id ), 'Publishing again is queued.' );
		$this->flush_index();
		$this->assertNotNull( NH_SEO_Search_Index::get( $post_id ) );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'private',
			)
		);
		$this->assertNull( NH_SEO_Search_Index::get( $post_id ), 'Private posts are not indexed.' );
	}

	public function test_trash_untrash_and_delete_keep_the_index_correct() {
		$post_id = $this->make_post( array( 'post_title' => 'Trash cycle' ) );
		$this->flush_index();

		wp_trash_post( $post_id );
		$this->assertNull( NH_SEO_Search_Index::get( $post_id ), 'Trashed.' );

		wp_untrash_post( $post_id );
		wp_publish_post( $post_id );
		$this->flush_index();
		$this->assertNotNull( NH_SEO_Search_Index::get( $post_id ), 'Restored and published again.' );

		wp_delete_post( $post_id, true );
		$this->assertNull( NH_SEO_Search_Index::get( $post_id ), 'Deleted for good.' );
		$this->assertSame( array(), NH_SEO_Search_Index::ids() );
	}

	public function test_deleting_a_queued_post_drops_it_from_the_queue() {
		$post_id = $this->make_post( array( 'post_title' => 'Gone before the flush' ) );
		$this->assertSame( array( $post_id ), NH_SEO_Search_Indexer::pending() );
		wp_delete_post( $post_id, true );
		$this->assertSame( array(), NH_SEO_Search_Indexer::pending() );
		$this->flush_index();
		$this->assertSame( array(), NH_SEO_Search_Index::ids() );
	}

	public function test_a_password_removes_the_row_and_removing_it_restores_the_row() {
		$post_id = $this->make_post( array( 'post_title' => 'Protected later' ) );
		$this->flush_index();
		$this->assertNotNull( NH_SEO_Search_Index::get( $post_id ) );

		wp_update_post(
			array(
				'ID'            => $post_id,
				'post_password' => 'secret',
			)
		);
		$this->flush_index();
		$this->assertNull( NH_SEO_Search_Index::get( $post_id ) );

		wp_update_post(
			array(
				'ID'            => $post_id,
				'post_password' => '',
			)
		);
		$this->flush_index();
		$this->assertNotNull( NH_SEO_Search_Index::get( $post_id ) );
	}

	public function test_autosaves_and_revisions_never_queue_anything() {
		require_once ABSPATH . 'wp-admin/includes/post.php';
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$post_id = $this->make_post( array( 'post_title' => 'Has revisions' ) );
		$this->flush_index();
		NH_SEO_Search_Indexer::reset();

		$revision = wp_save_post_revision( $post_id );
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => 'Changed so that a revision exists',
			)
		);
		NH_SEO_Search_Indexer::reset();
		if ( $revision ) {
			NH_SEO_Search_Indexer::on_save_post( $revision );
			$this->assertSame( array(), NH_SEO_Search_Indexer::pending() );
		}

		$autosave = wp_create_post_autosave(
			array(
				'post_ID'      => $post_id,
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_title'   => 'Autosaved title',
				'post_content' => 'Autosaved content',
				'post_excerpt' => '',
			)
		);
		$this->assertNotWPError( $autosave );
		$this->assertNotContains( (int) $autosave, NH_SEO_Search_Indexer::pending() );
		$this->flush_index();
		$this->assertSame( array( $post_id ), NH_SEO_Search_Index::ids() );
	}

	public function test_term_changes_refresh_the_taxonomy_words() {
		$post_id = $this->make_post( array( 'post_title' => 'Term follower' ) );
		$this->flush_index();
		$this->assertStringNotContainsString( 'banana', NH_SEO_Search_Index::get( $post_id )['tax_fold'] );

		wp_set_object_terms( $post_id, array( 'Banana split' ), 'post_tag' );
		$this->flush_index();
		$this->assertStringContainsString( 'banana split', NH_SEO_Search_Index::get( $post_id )['tax_fold'] );

		wp_set_object_terms( $post_id, array(), 'post_tag' );
		$this->flush_index();
		$this->assertStringNotContainsString( 'banana', NH_SEO_Search_Index::get( $post_id )['tax_fold'] );

		$category = self::factory()->category->create( array( 'name' => 'Cherry pie' ) );
		wp_set_post_categories( $post_id, array( $category ) );
		$this->flush_index();
		$this->assertStringContainsString( 'cherry pie', NH_SEO_Search_Index::get( $post_id )['tax_fold'] );
		$this->assertStringNotContainsString( 'uncategorized', NH_SEO_Search_Index::get( $post_id )['tax_fold'] );

		wp_remove_object_terms( $post_id, array( $category ), 'category' );
		$this->flush_index();
		$this->assertStringNotContainsString( 'cherry', NH_SEO_Search_Index::get( $post_id )['tax_fold'] );
	}

	public function test_meta_changes_refresh_the_document() {
		$post_id = $this->make_post( array( 'post_title' => 'Meta follower' ) );
		$this->flush_index();

		update_post_meta( $post_id, NH_SEO_Keys::META_KEYWORDS, 'keywordone' );
		update_post_meta( $post_id, '_yoast_wpseo_focuskw', 'focusyoast' );
		update_post_meta( $post_id, 'rank_math_focus_keyword', 'focusrank' );
		update_post_meta( $post_id, NH_SEO_Keys::META_SUMMARY, 'summaryword' );
		$this->flush_index();
		$row = NH_SEO_Search_Index::get( $post_id );
		foreach ( array( 'keywordone', 'focusyoast', 'focusrank' ) as $word ) {
			$this->assertStringContainsString( $word, $row['tax_fold'] );
		}
		$this->assertStringContainsString( 'summaryword', $row['body_fold'] );

		delete_post_meta( $post_id, NH_SEO_Keys::META_KEYWORDS );
		delete_post_meta( $post_id, NH_SEO_Keys::META_SUMMARY );
		$this->flush_index();
		$row = NH_SEO_Search_Index::get( $post_id );
		$this->assertStringNotContainsString( 'keywordone', $row['tax_fold'] );
		$this->assertStringNotContainsString( 'summaryword', $row['body_fold'] );

		// Unrelated meta does not even queue the post.
		update_post_meta( $post_id, '_edit_last', 1 );
		update_post_meta( $post_id, 'some_plugin_value', 'x' );
		$this->assertSame( array(), NH_SEO_Search_Indexer::pending() );
	}

	public function test_renaming_a_term_schedules_a_cron_walk_instead_of_looping() {
		$term = wp_insert_term( 'Oldname', 'category' );
		$ids  = $this->bulk_posts(
			105,
			static function ( $i ) {
				return array(
					'title'   => 'Walk post ' . $i,
					'content' => 'Content ' . $i,
				);
			}
		);
		foreach ( $ids as $id ) {
			wp_set_object_terms( $id, array( (int) $term['term_id'] ), 'category' );
		}
		NH_SEO_Search_Indexer::reset();
		$this->index_all();
		$this->assertStringContainsString( 'oldname', NH_SEO_Search_Index::get( $ids[0] )['tax_fold'] );

		wp_update_term( (int) $term['term_id'], 'category', array( 'name' => 'Newname' ) );

		$args = array( 'category', (int) $term['term_id'], 0 );
		$this->assertNotFalse( wp_next_scheduled( NH_SEO_Search_Indexer::CRON_TERM, $args ), 'A rename schedules the walk.' );
		$this->assertSame( array(), NH_SEO_Search_Indexer::pending(), 'No blocking loop in the request.' );
		$this->assertStringContainsString( 'oldname', NH_SEO_Search_Index::get( $ids[0] )['tax_fold'], 'Nothing changed yet.' );

		// What WP-Cron does: the first batch handles 100 posts and schedules the next one.
		$this->assertSame( 100, NH_SEO_Search_Indexer::cron_reindex_term( 'category', (int) $term['term_id'], 0 ) );
		$this->assertStringContainsString( 'newname', NH_SEO_Search_Index::get( $ids[0] )['tax_fold'] );
		$this->assertStringContainsString( 'newname', NH_SEO_Search_Index::get( $ids[99] )['tax_fold'] );
		$this->assertStringContainsString( 'oldname', NH_SEO_Search_Index::get( $ids[104] )['tax_fold'] );

		$next = array( 'category', (int) $term['term_id'], $ids[99] );
		$this->assertNotFalse( wp_next_scheduled( NH_SEO_Search_Indexer::CRON_TERM, $next ), 'The next batch continues after the last post.' );
		$this->assertSame( 5, NH_SEO_Search_Indexer::cron_reindex_term( 'category', (int) $term['term_id'], $ids[99] ) );
		$this->assertStringContainsString( 'newname', NH_SEO_Search_Index::get( $ids[104] )['tax_fold'] );
		$this->assertFalse(
			wp_next_scheduled( NH_SEO_Search_Indexer::CRON_TERM, array( 'category', (int) $term['term_id'], $ids[104] ) ),
			'A short batch ends the walk.'
		);
	}

	public function test_editing_something_other_than_the_name_does_not_schedule_a_walk() {
		$term = wp_insert_term( 'Steadyname', 'category' );
		wp_update_term(
			(int) $term['term_id'],
			'category',
			array(
				'name'        => 'Steadyname',
				'description' => 'A new description',
			)
		);
		$this->assertFalse( wp_next_scheduled( NH_SEO_Search_Indexer::CRON_TERM, array( 'category', (int) $term['term_id'], 0 ) ) );

		wp_update_term( (int) $term['term_id'], 'category', array( 'name' => 'Changedname' ) );
		$this->assertNotFalse( wp_next_scheduled( NH_SEO_Search_Indexer::CRON_TERM, array( 'category', (int) $term['term_id'], 0 ) ) );
	}

	public function test_deleting_a_term_queues_the_posts_that_had_it() {
		$tag  = wp_insert_term( 'Doomedtag', 'post_tag' );
		$one  = $this->make_post( array( 'post_title' => 'Tagged one' ) );
		$two  = $this->make_post( array( 'post_title' => 'Tagged two' ) );
		$none = $this->make_post( array( 'post_title' => 'Untagged' ) );
		wp_set_object_terms( $one, array( (int) $tag['term_id'] ), 'post_tag' );
		wp_set_object_terms( $two, array( (int) $tag['term_id'] ), 'post_tag' );
		$this->flush_index();
		$this->assertStringContainsString( 'doomedtag', NH_SEO_Search_Index::get( $one )['tax_fold'] );

		wp_delete_term( (int) $tag['term_id'], 'post_tag' );
		$pending = NH_SEO_Search_Indexer::pending();
		$this->assertContains( $one, $pending );
		$this->assertContains( $two, $pending );
		$this->assertNotContains( $none, $pending );

		$this->flush_index();
		$this->assertStringNotContainsString( 'doomedtag', NH_SEO_Search_Index::get( $one )['tax_fold'] );
		$this->assertStringNotContainsString( 'doomedtag', NH_SEO_Search_Index::get( $two )['tax_fold'] );
	}

	public function test_a_flush_never_re_indexes_more_than_the_inline_limit_and_hands_the_rest_to_cron() {
		$ids = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$ids[] = $this->make_post( array( 'post_title' => 'Inline limit ' . $i ) );
		}
		add_filter(
			'nh_seo_search_inline_limit',
			static function () {
				return 2;
			}
		);

		$this->flush_index();
		$this->assertSame( array( $ids[0], $ids[1] ), NH_SEO_Search_Index::ids(), 'Only two posts are written inline.' );
		$rest = array( $ids[2], $ids[3], $ids[4] );
		$this->assertNotFalse( wp_next_scheduled( NH_SEO_Search_Indexer::CRON_IDS, array( $rest ) ), 'The rest goes to WP-Cron.' );

		$this->assertSame( 3, NH_SEO_Search_Indexer::cron_reindex_ids( $rest ) );
		$this->assertSame( $ids, NH_SEO_Search_Index::ids() );
		$this->assertSame( 0, NH_SEO_Search_Indexer::cron_reindex_ids( 'not an array' ) );
	}

	public function test_a_huge_change_is_chunked_and_flags_the_index_as_stale_beyond_the_cap() {
		$method = new ReflectionMethod( 'NH_SEO_Search_Indexer', 'schedule_ids' );
		$method->setAccessible( true );
		$method->invoke( null, range( 1, 250 ) );
		$this->assertNotFalse( wp_next_scheduled( NH_SEO_Search_Indexer::CRON_IDS, array( range( 1, 100 ) ) ) );
		$this->assertNotFalse( wp_next_scheduled( NH_SEO_Search_Indexer::CRON_IDS, array( range( 201, 250 ) ) ) );
		$this->assertFalse( NH_SEO_Search_State::get()['stale'] );

		$method->invoke( null, range( 1, ( NH_SEO_Search_Indexer::MAX_CRON_EVENTS * NH_SEO_Search_Indexer::CRON_BATCH ) + 50 ) );
		$this->assertTrue( NH_SEO_Search_State::get()['stale'], 'More than the cap: the index is flagged for a rebuild.' );
		$this->assertStringContainsString( 'rebuild', NH_SEO_Search_State::status_line() );
	}

	public function test_deactivation_unschedules_every_pending_event_but_keeps_the_data() {
		$post_id = $this->make_post( array( 'post_title' => 'Survivor' ) );
		$this->flush_index();
		NH_SEO_Search_Indexer::schedule_term( 'category', 5, 0 );
		wp_schedule_single_event( time() + 60, NH_SEO_Search_Indexer::CRON_IDS, array( array( 1, 2 ) ) );
		$this->assertNotFalse( wp_next_scheduled( NH_SEO_Search_Indexer::CRON_TERM, array( 'category', 5, 0 ) ) );

		do_action( 'nh_seo_deactivate' );

		$this->assertFalse( wp_next_scheduled( NH_SEO_Search_Indexer::CRON_TERM, array( 'category', 5, 0 ) ) );
		$this->assertFalse( wp_next_scheduled( NH_SEO_Search_Indexer::CRON_IDS, array( array( 1, 2 ) ) ) );
		$this->assertNotNull( NH_SEO_Search_Index::get( $post_id ), 'Deactivation keeps the index.' );
	}

	public function test_changes_are_ignored_quietly_while_the_table_does_not_exist() {
		$post_id = $this->make_post( array( 'post_title' => 'No table yet' ) );
		NH_SEO_Search_Index::drop_table();

		$this->flush_index();
		wp_trash_post( $post_id );
		wp_delete_post( $post_id, true );
		$this->assertSame( 0, NH_SEO_Search_Indexer::cron_reindex_term( 'category', 1, 0 ) );
		$this->assertSame( 0, NH_SEO_Search_Indexer::cron_reindex_ids( array( 1, 2, 3 ) ) );
		$this->assertFalse( NH_SEO_Search_Index::table_exists() );
	}

	public function test_prune_removes_only_rows_without_a_public_post() {
		$keep    = $this->make_post( array( 'post_title' => 'Keep me' ) );
		$draft   = $this->make_post( array( 'post_title' => 'Becomes draft' ) );
		$private = $this->make_post( array( 'post_title' => 'Becomes private' ) );
		$this->flush_index();
		$this->assertCount( 3, NH_SEO_Search_Index::ids() );

		// Change the posts behind the plugin's back (direct SQL), as an importer or a bug could.
		global $wpdb;
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'draft' ), array( 'ID' => $draft ) );
		$wpdb->update( $wpdb->posts, array( 'post_password' => 'x' ), array( 'ID' => $private ) );
		$wpdb->insert(
			NH_SEO_Search_Index::table(),
			array(
				'post_id'    => 999999,
				'post_type'  => 'post',
				'title_fold' => 'orphan',
				'tax_fold'   => '',
				'body_fold'  => '',
				'updated'    => gmdate( 'Y-m-d H:i:s' ),
			)
		);
		clean_post_cache( $draft );
		clean_post_cache( $private );

		$this->assertSame( 3, NH_SEO_Search_Index::prune() );
		$this->assertSame( array( $keep ), NH_SEO_Search_Index::ids() );
		$this->assertSame( 0, NH_SEO_Search_Index::prune(), 'Nothing left to prune.' );
	}

	public function test_counts_and_cursors_only_see_indexable_posts() {
		$a = $this->make_post( array( 'post_title' => 'A' ) );
		$this->make_post( array( 'post_status' => 'draft' ) );
		$c = $this->make_post(
			array(
				'post_type'  => 'page',
				'post_title' => 'C',
			)
		);
		$this->make_post( array( 'post_password' => 'x' ) );

		$this->assertSame( 2, NH_SEO_Search_Index::count_indexable() );
		$this->assertSame( 1, NH_SEO_Search_Index::count_indexable( array( 'page' ) ) );
		$this->assertSame( 0, NH_SEO_Search_Index::count_indexable( array() ) );
		$this->assertSame( array( $a, $c ), NH_SEO_Search_Index::next_indexable_ids( 0, 10 ) );
		$this->assertSame( array( $c ), NH_SEO_Search_Index::next_indexable_ids( $a, 10 ) );
		$this->assertSame( array( $a ), NH_SEO_Search_Index::next_indexable_ids( 0, 1 ) );
		$this->assertSame( array( $c ), NH_SEO_Search_Index::next_indexable_ids( 0, 10, array( 'page' ) ) );
		$this->assertSame( array(), NH_SEO_Search_Index::next_indexable_ids( 0, 10, array() ) );
	}

	public function test_the_plugin_never_changes_content() {
		global $wpdb;
		$post_id = $this->make_post(
			array(
				'post_title'   => 'Untouched title',
				'post_content' => 'Untouched content',
				'post_name'    => 'untouched-slug',
			)
		);
		$before  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE ID = %d", $post_id ), ARRAY_A );
		$meta    = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d ORDER BY meta_id", $post_id ), ARRAY_A );

		$this->index_all();
		$this->flush_index();
		wp_set_object_terms( $post_id, array( 'unrelated' ), 'post_tag' );
		$this->flush_index();

		$this->assertSame( $before, $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE ID = %d", $post_id ), ARRAY_A ) );
		$this->assertSame( $meta, $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d ORDER BY meta_id", $post_id ), ARRAY_A ) );
	}
}
