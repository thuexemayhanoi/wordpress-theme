<?php
/**
 * Regression tests for the defects an independent review found in the theme (each one was reproduced on a
 * throw-away site before it was fixed): private data of password-protected posts, cache invalidation and
 * cost on large sites, rows written by anonymous requests, the table wrapper, attachment pages, stale
 * author counts and array-typed meta.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

/**
 * Review findings.
 */
class NH_Test_Review_Findings extends NH_Render_Test_Case {

	/**
	 * Permalinks of the live site and a complete fixture.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->use_permalinks( '/%postname%/' );
		$this->build_site( 14 );
	}

	/**
	 * Companion meta that must never be shown next to a password form.
	 *
	 * @return array<string, mixed>
	 */
	private function secret_meta() {
		return array(
			'nh_summary'         => 'SECRET-SUMMARY',
			'nh_takeaways'       => array( 'SECRET-TAKEAWAY one', 'SECRET-TAKEAWAY two' ),
			'nh_faq'             => array(
				array(
					'question' => 'SECRET-QUESTION one?',
					'answer'   => 'SECRET-ANSWER one',
				),
				array(
					'question' => 'SECRET-QUESTION two?',
					'answer'   => 'SECRET-ANSWER two',
				),
			),
			'nh_sources'         => array(
				array(
					'title' => 'SECRET-SOURCE',
					'url'   => 'https://example.org/secret-source',
				),
			),
			'nh_content_updated' => gmdate( 'Y-m-d', strtotime( '-1 day' ) ),
		);
	}

	/**
	 * A protected article or page with a long body, headings and every companion extra.
	 *
	 * @param string $type          post or page.
	 * @param string $template_meta Page template, for pages.
	 * @return int
	 */
	private function protected_item( $type = 'post', $template_meta = '' ) {
		$body = '<p>' . str_repeat( 'SECRET-BODY words in the protected text. ', 700 ) . '</p>'
			. '<h2>SECRET-HEADING one</h2><p>text</p><h2>SECRET-HEADING two</h2><p>text</p><h2>SECRET-HEADING three</h2><p>text</p>';
		$args = array(
			'post_type'     => $type,
			'post_title'    => 'Protected ' . $type,
			'post_content'  => $body,
			'post_password' => 'secret',
			'post_status'   => 'publish',
			'meta_input'    => $this->secret_meta(),
		);
		if ( 'post' === $type ) {
			$args['post_category'] = array( $this->site['child'] );
		}
		if ( '' !== $template_meta ) {
			$args['meta_input']['_wp_page_template'] = $template_meta;
		}
		return self::factory()->post->create( $args );
	}

	/**
	 * Fail when anything that is private to a protected item reaches the page.
	 *
	 * @param string $html  Page HTML.
	 * @param string $label Description.
	 * @return void
	 */
	private function assert_nothing_private_is_printed( $html, $label ) {
		$this->assertStringContainsString( 'post-password-form', $html, $label . ': the password form is shown' );
		foreach ( array( 'SECRET-SUMMARY', 'SECRET-TAKEAWAY', 'SECRET-QUESTION', 'SECRET-ANSWER', 'SECRET-SOURCE', 'SECRET-HEADING', 'SECRET-BODY' ) as $needle ) {
			$this->assertStringNotContainsString( $needle, $html, $label . ': ' . $needle . ' must not reach a visitor without the password' );
		}
		$this->assertStringNotContainsString( 'min read', $html, $label . ': the reading time would reveal the length of the protected text' );
	}

	public function test_a_protected_article_prints_no_summary_faq_sources_or_reading_time() {
		$id = $this->protected_item();
		$this->assert_nothing_private_is_printed( $this->render_url( get_permalink( $id ) ), 'article' );
	}

	public function test_a_protected_page_and_a_protected_pillar_page_print_nothing_private() {
		$page   = $this->protected_item( 'page' );
		$pillar = $this->protected_item( 'page', 'page-templates/template-pillar.php' );
		$this->assert_nothing_private_is_printed( $this->render_url( get_permalink( $page ) ), 'page' );
		$this->assert_nothing_private_is_printed( $this->render_url( get_permalink( $pillar ) ), 'pillar page' );
	}

	public function test_the_extras_return_once_the_password_was_given() {
		$id = $this->protected_item();
		$this->assertSame( '', nh_editorial_get_extras( $id )['summary'], 'locked' );

		// post_password_required() is the one place that knows about the visitor's cookie.
		add_filter( 'post_password_required', '__return_false' );
		$unlocked = nh_editorial_get_extras( $id );
		$html     = $this->render_url( get_permalink( $id ) );
		remove_filter( 'post_password_required', '__return_false' );

		$this->assertSame( 'SECRET-SUMMARY', $unlocked['summary'], 'unlocked' );
		$this->assertStringContainsString( 'SECRET-SUMMARY', $html );
		$this->assertStringContainsString( 'SECRET-ANSWER one', $html );
		$this->assertSame( '', nh_editorial_get_extras( $id )['summary'], 'a value cached while unlocked must not outlive the password' );
	}

	public function test_protected_posts_never_appear_in_the_lists_and_the_main_query_has_no_password_condition() {
		$protected = self::factory()->post->create(
			array(
				'post_title'    => 'Newest but protected',
				'post_password' => 'secret',
				'post_category' => array( $this->site['child'] ),
				'post_date'     => gmdate( 'Y-m-d H:i:s' ),
			)
		);
		nh_editorial_cache_bump_now();

		$queries = array();
		$logger  = static function ( $sql ) use ( &$queries ) {
			$queries[] = $sql;
			return $sql;
		};
		add_filter( 'query', $logger );
		$child   = get_term( $this->site['child'], 'category' );
		$top     = get_term( $this->site['top'], 'category' );
		$support = nh_editorial_get_supporting_ids( $top, 6 );
		$related = nh_editorial_get_related_ids( $this->site['rich'], 4 );
		$latest  = nh_editorial_get_latest_ids( 5 );
		$feature = nh_editorial_get_featured_ids( 4 );
		remove_filter( 'query', $logger );

		$this->assertNotContains( $protected, $support );
		$this->assertNotContains( $protected, $related );
		$this->assertNotContains( $protected, $latest );
		$this->assertNotContains( $protected, $feature );
		$this->assertCount( 6, $support, 'the list is still full: a few extra ids are fetched for the protected ones' );
		$this->assertNotEmpty( $child );

		// A password condition in the big date-ordered query makes MySQL read whole rows (5 s on 50,000 articles).
		foreach ( $queries as $sql ) {
			if ( false !== strpos( $sql, 'ORDER BY' ) && false !== strpos( $sql, 'post_date' ) ) {
				$this->assertStringNotContainsString( 'post_password', $sql, 'the list query must not filter by password in SQL' );
			}
		}
	}

	public function test_editing_the_text_of_a_published_post_does_not_invalidate_the_cache() {
		$id = $this->site['plain'];
		nh_editorial_cache_bump_now();
		remove_action( 'shutdown', 'nh_editorial_cache_bump_now', 5 );

		wp_update_post(
			array(
				'ID'           => $id,
				'post_content' => '<p>Only a typo was fixed.</p>',
			)
		);
		$this->assertFalse( has_action( 'shutdown', 'nh_editorial_cache_bump_now' ), 'a content-only save of a published post changes no cached list' );

		wp_update_post(
			array(
				'ID'        => $id,
				'post_date' => gmdate( 'Y-m-d H:i:s', strtotime( '-3 days' ) ),
			)
		);
		$this->assertNotFalse( has_action( 'shutdown', 'nh_editorial_cache_bump_now' ), 'a new date reorders the lists' );
		remove_action( 'shutdown', 'nh_editorial_cache_bump_now', 5 );

		wp_update_post(
			array(
				'ID'            => $id,
				'post_password' => 'hidden-now',
			)
		);
		$this->assertNotFalse( has_action( 'shutdown', 'nh_editorial_cache_bump_now' ), 'a password hides the post from the lists' );
		remove_action( 'shutdown', 'nh_editorial_cache_bump_now', 5 );

		$other = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_update_post(
			array(
				'ID'          => $id,
				'post_author' => $other,
			)
		);
		$this->assertNotFalse( has_action( 'shutdown', 'nh_editorial_cache_bump_now' ), 'another author changes the author counts' );
		remove_action( 'shutdown', 'nh_editorial_cache_bump_now', 5 );
	}

	public function test_unknown_authors_write_nothing_to_the_options_table() {
		global $wpdb;

		// Warm everything a 404 page itself needs, then count what further unknown authors add.
		$this->render_url( home_url( '/?author=900001' ) );
		$count  = static function () use ( $wpdb ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_nh\\_ed\\_%'" );
		};
		$before = $count();

		for ( $author = 900002; $author <= 900012; $author++ ) {
			$html = $this->render_url( home_url( '/?author=' . $author ) );
			$this->assertStringContainsString( '<h1', $html );
		}

		$this->assertSame( $before, $count(), 'a request for an author that does not exist must not add cache rows' );
	}

	public function test_a_deleted_author_whose_posts_are_reassigned_refreshes_the_counts() {
		$temp = self::factory()->user->create( array( 'role' => 'author' ) );
		self::factory()->post->create_many(
			3,
			array(
				'post_author'   => $temp,
				'post_category' => array( $this->site['child'] ),
			)
		);
		$first = nh_editorial_author_article_count( $this->site['author'] );

		remove_action( 'shutdown', 'nh_editorial_cache_bump_now', 5 );
		wp_delete_user( $temp, $this->site['author'] );
		$this->assertNotFalse( has_action( 'shutdown', 'nh_editorial_cache_bump_now' ), 'reassigned posts change the count of the receiving author' );

		nh_editorial_cache_bump_now();
		$this->assertSame( $first + 3, nh_editorial_author_article_count( $this->site['author'] ) );
	}

	public function test_the_table_wrapper_wraps_the_outer_table_once() {
		$table = '<table><thead><tr><th>A</th></tr></thead><tbody><tr><td>cell</td></tr></tbody></table>';
		$post  = self::factory()->post->create(
			array(
				'post_title'    => 'Tables',
				'post_content'  => '<p>before</p>' . $table . '<p>between</p>' . $table . '<p>after</p>',
				'post_category' => array( $this->site['child'] ),
			)
		);
		$this->go_to( get_permalink( $post ) );
		$html = nh_editorial_wrap_tables( '<p>before</p>' . $table . '<p>between</p>' . $table . '<p>after</p>' );

		$this->assertSame( 2, substr_count( $html, 'class="table-scroll"' ) );
		$this->assertStringContainsString( '<p>before</p><div class="table-scroll"', $html );
		$this->assertStringContainsString( '</table></div><p>between</p>', $html );
		$this->assertStringContainsString( '</table></div><p>after</p>', $html );
	}

	public function test_the_table_wrapper_handles_nested_tables_of_any_size_and_never_erases_the_body() {
		$this->go_to( get_permalink( $this->site['plain'] ) );

		$inner  = '<table><tr><td>inner cell</td></tr></table>';
		$small  = '<p>before</p><table><tr><td>' . $inner . '</td></tr></table><p>after the table</p>';
		$result = nh_editorial_wrap_tables( $small );
		$this->assertSame( 1, substr_count( $result, 'class="table-scroll"' ), 'one wrapper around the outer table' );
		$this->assertStringContainsString( '</table></td></tr></table></div><p>after the table</p>', $result, 'the paragraph after the table stays outside the wrapper' );

		$big = '<p>before</p><table><tr><td>' . str_repeat( 'x', 40000 ) . $inner . '</td></tr></table><p>after the big table</p>';
		$res = nh_editorial_wrap_tables( $big );
		$this->assertSame( 1, substr_count( $res, 'class="table-scroll"' ) );
		$this->assertStringContainsString( '</table></div><p>after the big table</p>', $res, 'a table over 24 KB behaves like a small one' );

		$huge = '<p>text before</p><table><tr><td>' . str_repeat( 'cell ', 250000 ) . '</td></tr></table><p>text after</p>';
		$res  = nh_editorial_wrap_tables( $huge );
		$this->assertStringContainsString( 'text before', $res, 'a table of over a megabyte must not blank the article' );
		$this->assertStringContainsString( 'text after', $res );
		$this->assertGreaterThan( 1000000, strlen( $res ) );
	}

	public function test_the_table_wrapper_leaves_unbalanced_markup_alone() {
		$this->go_to( get_permalink( $this->site['plain'] ) );
		foreach ( array( '<p>a</p><table><tr><td>never closed</td></tr><p>b</p>', '<p>a</p></table><p>stray close</p>', '<table-chart>custom element</table-chart>' ) as $markup ) {
			$this->assertSame( $markup, nh_editorial_wrap_tables( $markup ) );
		}
	}

	public function test_an_attachment_page_does_not_name_a_trashed_parent() {
		update_option( 'wp_attachment_pages_enabled', '1' );
		$parent     = self::factory()->post->create(
			array(
				'post_title'  => 'TRASHED-PARENT internal memo',
				'post_status' => 'publish',
			)
		);
		$attachment = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/2004-07-22-DSC_0007.jpg', $parent );
		$this->assertNotEmpty( $attachment );

		$live = $this->render_url( get_attachment_link( $attachment ) );
		$this->assertStringContainsString( 'TRASHED-PARENT internal memo', $live, 'a published parent is linked' );

		wp_trash_post( $parent );
		$trashed = $this->render_url( get_attachment_link( $attachment ) );
		$this->assertStringNotContainsString( 'TRASHED-PARENT', $trashed, 'a trashed article must not be named or linked' );
	}

	public function test_array_valued_meta_does_not_warn_and_prints_no_array_link() {
		$author = $this->site['author'];
		update_user_meta( $author, 'nh_sameas', array( 'https://example.org/a', 'https://example.org/b' ) );
		update_user_meta( $author, 'description', array( 'not', 'a', 'string' ) );

		$profile = nh_editorial_get_author_profile( $author );
		$this->assertIsArray( $profile );
		$this->assertSame( '', $profile['bio'] );
		foreach ( $profile['links'] as $link ) {
			$this->assertStringNotContainsString( 'Array', $link['url'] );
		}

		// An array in nh_hub_term is ignored; the page falls back to the category that names it as its pillar.
		update_post_meta( $this->site['pillar'], 'nh_hub_term', array( 'category:1' ) );
		$term = nh_editorial_get_hub_term( $this->site['pillar'] );
		$this->assertInstanceOf( WP_Term::class, $term );
		$this->assertSame( (int) $this->site['top'], (int) $term->term_id );
	}
}
