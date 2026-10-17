<?php
/**
 * Editorial extras, table of contents, template tags and search helpers.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

/**
 * Summary, takeaways, FAQ, sources and the real update date.
 */
class NH_Test_Extras extends NH_Unit_Test_Case {

	public function test_extras_are_empty_for_a_plain_post() {
		$post_id = self::factory()->post->create();
		$extras  = nh_editorial_get_extras( $post_id );
		$this->assertSame( '', $extras['summary'] );
		$this->assertSame( array(), $extras['takeaways'] );
		$this->assertSame( array(), $extras['faq'] );
		$this->assertSame( array(), $extras['sources'] );
		$this->assertSame( '', $extras['updated'] );
	}

	public function test_extras_are_validated_on_read() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'nh_summary', '<b>Short</b> answer ' . str_repeat( 'x', 2000 ) );
		update_post_meta( $post_id, 'nh_takeaways', "One\n\n<i>Two</i>" );
		update_post_meta(
			$post_id,
			'nh_faq',
			array(
				array(
					'question' => 'Q1?',
					'answer'   => '<p>Fine <a href="https://example.org" onclick="x()">link</a></p><script>alert(1)</script><img src=x onerror=alert(1)>',
				),
				array(
					'question' => '',
					'answer'   => 'ignored',
				),
				array(
					'q' => 'Alias?',
					'a' => 'Short alias answer.',
				),
			)
		);
		update_post_meta(
			$post_id,
			'nh_sources',
			array(
				array(
					'title' => 'Good',
					'url'   => 'https://example.org/x',
				),
				array( 'url' => 'javascript:alert(1)' ),
				array( 'url' => 'data:text/html,hi' ),
			)
		);

		$extras = nh_editorial_get_extras( $post_id );
		$this->assertLessThanOrEqual( 800, mb_strlen( $extras['summary'] ) );
		$this->assertStringStartsWith( 'Short answer', $extras['summary'] );
		$this->assertSame( array( 'One', 'Two' ), $extras['takeaways'] );
		$this->assertCount( 2, $extras['faq'] );
		$this->assertStringContainsString( '<a href="https://example.org">link</a>', $extras['faq'][0]['answer'] );
		$this->assertStringNotContainsString( 'script', $extras['faq'][0]['answer'] );
		$this->assertStringNotContainsString( 'onerror', $extras['faq'][0]['answer'] );
		$this->assertStringNotContainsString( 'onclick', $extras['faq'][0]['answer'] );
		$this->assertCount( 1, $extras['sources'] );
		$this->assertSame( 'https://example.org/x', $extras['sources'][0]['url'] );
	}

	public function test_updated_date_requires_a_real_change() {
		$published = strtotime( '-10 days' );
		$post_id   = self::factory()->post->create(
			array(
				'post_date'     => gmdate( 'Y-m-d H:i:s', $published ),
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $published ),
			)
		);

		// A modified date equal to the publish date is never shown.
		global $wpdb;
		$wpdb->update(
			$wpdb->posts,
			array(
				'post_modified'     => gmdate( 'Y-m-d H:i:s', $published + HOUR_IN_SECONDS ),
				'post_modified_gmt' => gmdate( 'Y-m-d H:i:s', $published + HOUR_IN_SECONDS ),
			),
			array( 'ID' => $post_id )
		);
		clean_post_cache( $post_id );
		$this->assertNull( nh_editorial_updated_date( $post_id ) );

		// A modification at least a day later is shown.
		$wpdb->update(
			$wpdb->posts,
			array(
				'post_modified'     => gmdate( 'Y-m-d H:i:s', $published + 3 * DAY_IN_SECONDS ),
				'post_modified_gmt' => gmdate( 'Y-m-d H:i:s', $published + 3 * DAY_IN_SECONDS ),
			),
			array( 'ID' => $post_id )
		);
		clean_post_cache( $post_id );
		$updated = nh_editorial_updated_date( $post_id );
		$this->assertNotNull( $updated );
		$this->assertNotSame( '', $updated['label'] );
	}

	public function test_workflow_update_date_wins_and_cannot_predate_publication() {
		$published = strtotime( '-10 days' );
		$post_id   = self::factory()->post->create(
			array(
				'post_date'     => gmdate( 'Y-m-d H:i:s', $published ),
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $published ),
			)
		);

		update_post_meta( $post_id, 'nh_content_updated', gmdate( 'Y-m-d', strtotime( '-2 days' ) ) );
		$updated = nh_editorial_updated_date( $post_id );
		$this->assertSame( gmdate( 'Y-m-d', strtotime( '-2 days' ) ), $updated['iso'] );

		// Before publication: ignored. With a modified date that is not materially later, nothing is shown.
		global $wpdb;
		$wpdb->update(
			$wpdb->posts,
			array(
				'post_modified'     => gmdate( 'Y-m-d H:i:s', $published + HOUR_IN_SECONDS ),
				'post_modified_gmt' => gmdate( 'Y-m-d H:i:s', $published + HOUR_IN_SECONDS ),
			),
			array( 'ID' => $post_id )
		);
		clean_post_cache( $post_id );
		update_post_meta( $post_id, 'nh_content_updated', gmdate( 'Y-m-d', strtotime( '-30 days' ) ) );
		$this->assertNull( nh_editorial_updated_date( $post_id ) );

		// In the future: invalid.
		update_post_meta( $post_id, 'nh_content_updated', gmdate( 'Y-m-d', strtotime( '+20 days' ) ) );
		$this->assertSame( '', nh_editorial_get_extras( $post_id )['updated'] );
	}
}

/**
 * Table of contents.
 */
class NH_Test_Toc extends NH_Unit_Test_Case {

	public function test_headings_get_unique_stable_ids_and_text_is_untouched() {
		nh_editorial_toc_store( array() );
		$html = '<h2>Intro</h2><p>a</p><h2>Intro</h2><h3 id="keep-me">Keep me</h3><h3>Détails &amp; più</h3><h2>Comments</h2><h4>Ignored</h4><h2> </h2><h2><strong>Bold</strong> title</h2>';
		$out  = nh_editorial_toc_filter( $html );
		$toc  = nh_editorial_toc_store();

		$this->assertSame( array( 'intro', 'intro-2', 'keep-me', 'details-piu', 'comments-2', 'bold-title' ), wp_list_pluck( $toc, 'id' ) );
		$this->assertSame( array( 2, 2, 3, 3, 2, 2 ), wp_list_pluck( $toc, 'level' ) );
		$this->assertSame( 'Bold title', $toc[5]['text'] );
		$this->assertStringContainsString( '<h2 id="intro">Intro</h2>', $out );
		$this->assertStringContainsString( '<h2 id="intro-2">Intro</h2>', $out );
		$this->assertStringContainsString( '<h3 id="keep-me">Keep me</h3>', $out );
		$this->assertStringContainsString( '<h4>Ignored</h4>', $out );
		$this->assertStringContainsString( '<h2><strong>Bold</strong> title</h2>' === '' ? '' : '<strong>Bold</strong> title</h2>', $out );
		// Heading text and order are unchanged: stripping the generated ids gives the original markup.
		$this->assertSame( $html, preg_replace( '/ id="(intro|intro-2|details-piu|comments-2|bold-title)"/', '', $out ) );
	}

	public function test_ids_do_not_collide_with_ids_already_in_the_content() {
		nh_editorial_toc_store( array() );
		$out = nh_editorial_toc_filter( '<div id="overview">x</div><h2>Overview</h2>' );
		$this->assertStringContainsString( '<h2 id="overview-2">Overview</h2>', $out );
	}

	public function test_vietnamese_headings_get_ascii_ids() {
		nh_editorial_toc_store( array() );
		nh_editorial_toc_filter( '<h2>Thuê xe máy ở Hà Nội</h2><h2>Đặt cọc &amp; giấy tờ</h2>' );
		$this->assertSame( array( 'thue-xe-may-o-ha-noi', 'dat-coc-giay-to' ), wp_list_pluck( nh_editorial_toc_store(), 'id' ) );
	}

	public function test_article_is_prepared_once_and_respects_the_minimum() {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => '<h2>One</h2><p>x</p><h2>Two</h2><p>y</p>',
			)
		);
		$this->go_to( get_permalink( $post_id ) );
		the_post();

		set_theme_mod( 'nh_toc_min', 3 );
		$article = nh_editorial_prepare_article();
		$this->assertSame( array(), $article['toc'], 'Two headings are below the minimum of three.' );
		$this->assertStringContainsString( 'id="one"', $article['content'] );

		set_theme_mod( 'nh_toc_min', 2 );
		$this->assertCount( 2, nh_editorial_prepare_article()['toc'] );

		set_theme_mod( 'nh_toc', false );
		$this->assertSame( array(), nh_editorial_prepare_article()['toc'] );
		remove_theme_mods();
	}

	public function test_prepare_article_runs_the_normal_content_filters() {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => "<!-- wp:paragraph -->\n<p>Block paragraph</p>\n<!-- /wp:paragraph -->\n\n[nh_test_shortcode]\n\n<h2>A</h2><h2>B</h2><h2>C</h2>",
			)
		);
		add_shortcode(
			'nh_test_shortcode',
			static function () {
				return '<p class="from-shortcode">Shortcode output</p>';
			}
		);
		$this->go_to( get_permalink( $post_id ) );
		the_post();
		$article = nh_editorial_prepare_article();
		$this->assertStringContainsString( 'from-shortcode', $article['content'] );
		$this->assertStringContainsString( 'Block paragraph', $article['content'] );
		$this->assertStringNotContainsString( '<!-- wp:paragraph -->', $article['content'] );
		$this->assertCount( 3, $article['toc'] );
		remove_shortcode( 'nh_test_shortcode' );
	}

	public function test_component_nests_sub_headings_and_handles_an_orphan_h3() {
		ob_start();
		get_template_part(
			'template-parts/components/toc',
			null,
			array(
				'items' => array(
					array(
						'id'    => 'orphan',
						'text'  => 'Orphan H3',
						'level' => 3,
					),
					array(
						'id'    => 'a',
						'text'  => 'A',
						'level' => 2,
					),
					array(
						'id'    => 'a1',
						'text'  => 'A1',
						'level' => 3,
					),
					array(
						'id'    => 'a2',
						'text'  => 'A2',
						'level' => 3,
					),
					array(
						'id'    => 'b',
						'text'  => 'B',
						'level' => 2,
					),
				),
			)
		);
		$html = ob_get_clean();

		$this->assertSame( 3, $this->count_nodes( $html, '//ol[contains(@class,"toc__list")]/li' ), 'orphan, A and B at top level' );
		$this->assertSame( 2, $this->count_nodes( $html, '//ol[contains(@class,"toc__sub")]/li' ) );
		$this->assertSame( 0, $this->count_nodes( $html, '//details[@open]' ), 'rendered collapsed; the script opens it on wide screens' );
		$this->assertSame( 1, $this->count_nodes( $html, '//nav[@aria-label]' ) );
	}

	public function test_component_prints_nothing_without_items() {
		ob_start();
		get_template_part( 'template-parts/components/toc', null, array( 'items' => array() ) );
		$this->assertSame( '', trim( ob_get_clean() ) );
	}
}

/**
 * Template tags and content helpers.
 */
class NH_Test_Template_Tags extends NH_Unit_Test_Case {

	public function test_heading_tag_is_clamped_and_never_h1() {
		$this->assertSame( 'h2', nh_editorial_heading_tag( 1 ) );
		$this->assertSame( 'h2', nh_editorial_heading_tag( 'h1' ) );
		$this->assertSame( 'h3', nh_editorial_heading_tag( 'h3' ) );
		$this->assertSame( 'h4', nh_editorial_heading_tag( 9 ) );
		$this->assertSame( 'h2', nh_editorial_heading_tag( 'nonsense' ) );
	}

	public function test_reading_time() {
		$short = self::factory()->post->create( array( 'post_content' => 'one two three' ) );
		$long  = self::factory()->post->create( array( 'post_content' => '<p>' . str_repeat( 'word ', 650 ) . '</p>' ) );
		$vi    = self::factory()->post->create( array( 'post_content' => str_repeat( 'chữ có dấu ', 220 ) ) );
		$this->assertSame( 1, nh_editorial_reading_time( $short ) );
		$this->assertSame( 4, nh_editorial_reading_time( $long ) );
		$this->assertSame( 4, nh_editorial_reading_time( $vi ), 'multibyte words are counted' );
	}

	public function test_count_label_and_initials() {
		$this->assertSame( '1 article', nh_editorial_count_label( 1 ) );
		$this->assertSame( '1,234 articles', nh_editorial_count_label( 1234 ) );
		update_option( 'blogname', 'Nguyen Ha Test Blog' );
		$this->assertSame( 'NH', nh_editorial_site_initials() );
		update_option( 'blogname', 'Đông' );
		$this->assertSame( 'Đ', nh_editorial_site_initials() );
		update_option( 'blogname', '' );
		$this->assertSame( 'N', nh_editorial_site_initials() );
	}

	public function test_tables_are_wrapped_in_a_focusable_scroll_region() {
		$this->go_to( get_permalink( self::factory()->post->create() ) );
		$out = nh_editorial_wrap_tables( '<p>x</p><table><tr><td>a</td></tr></table><p>y</p><figure class="wp-block-table"><table><tr><td>b</td></tr></table></figure>' );
		$this->assertSame( 2, substr_count( $out, 'class="table-scroll"' ) );
		$this->assertSame( 2, substr_count( $out, 'tabindex="0"' ) );
		$this->assertSame( 2, substr_count( $out, 'role="region"' ) );
		$this->assertSame( 2, substr_count( $out, '</table></div>' ) );
		$this->assertSame( 2, $this->count_nodes( $out, '//div[@class="table-scroll"]//table' ) );
	}

	public function test_nested_or_unbalanced_tables_are_left_alone() {
		$this->go_to( get_permalink( self::factory()->post->create() ) );
		$nested = '<table><tr><td><table><tr><td>inner</td></tr></table></td></tr></table>';
		$this->assertSame( $nested, nh_editorial_wrap_tables( $nested ) );
		$broken = '<table><tr><td>never closed';
		$this->assertSame( $broken, nh_editorial_wrap_tables( $broken ) );
		$this->assertSame( '<p>no tables</p>', nh_editorial_wrap_tables( '<p>no tables</p>' ) );
	}

	public function test_featured_posts_put_sticky_first_and_never_expose_hidden_posts() {
		$cat    = self::factory()->category->create();
		$ids    = $this->make_posts( $cat, 6 );
		$draft  = self::factory()->post->create(
			array(
				'post_status'   => 'draft',
				'post_category' => array( $cat ),
			)
		);
		$secret = self::factory()->post->create(
			array(
				'post_status'   => 'private',
				'post_category' => array( $cat ),
			)
		);
		$locked = self::factory()->post->create(
			array(
				'post_status'   => 'publish',
				'post_password' => 'x',
				'post_category' => array( $cat ),
			)
		);

		stick_post( $ids[0] );
		stick_post( $draft );
		nh_editorial_cache_bump_now();
		$featured = nh_editorial_get_featured_ids( 4 );

		$this->assertCount( 4, $featured );
		$this->assertSame( $ids[0], $featured[0], 'sticky post leads' );
		$this->assertNotContains( $draft, $featured );
		$this->assertNotContains( $secret, $featured );
		$this->assertNotContains( $locked, $featured );
		$this->assertSame( array_unique( $featured ), $featured );

		$latest = nh_editorial_get_latest_ids( 3, array( $ids[5] ) );
		$this->assertNotContains( $ids[5], $latest );
		$this->assertCount( 3, $latest );
	}

	public function test_post_meta_markup_is_escaped_and_complete() {
		$user = self::factory()->user->create( array( 'role' => 'editor' ) );
		// WordPress filters markup out of display names on save; force it in to prove output escaping.
		global $wpdb;
		$wpdb->update( $wpdb->users, array( 'display_name' => 'Jane <b>Doe</b>' ), array( 'ID' => $user ) );
		clean_user_cache( $user );
		$cat = self::factory()->category->create( array( 'name' => 'Rides & Routes' ) );
		$id  = self::factory()->post->create(
			array(
				'post_author'   => $user,
				'post_category' => array( $cat ),
				'post_content'  => str_repeat( 'word ', 400 ),
			)
		);
		$this->go_to( get_permalink( $id ) );
		the_post();

		ob_start();
		nh_editorial_post_meta(
			array(
				'author'   => true,
				'date'     => true,
				'reading'  => true,
				'category' => true,
			)
		);
		$html = ob_get_clean();

		$this->assertStringNotContainsString( '<b>Doe</b>', $html );
		$this->assertStringContainsString( 'Jane &lt;b&gt;Doe&lt;/b&gt;', $html );
		$this->assertStringContainsString( 'Rides &amp; Routes', $html );
		$this->assertSame( 1, $this->count_nodes( $html, '//time[@datetime]' ) );
		$this->assertStringContainsString( 'rel="author"', $html );
		$this->assertStringContainsString( '2 min read', $html );

		ob_start();
		nh_editorial_post_meta( array() );
		$this->assertSame( '', trim( ob_get_clean() ) );
	}
}

/**
 * Search helpers.
 */
class NH_Test_Search_Helpers extends NH_Unit_Test_Case {

	public function test_highlight_wraps_matches_and_escapes_everything() {
		$out = nh_editorial_highlight( 'Rent a <scooter> & ride', 'scooter ride' );
		$this->assertSame( 'Rent a &lt;<mark>scooter</mark>&gt; &amp; <mark>ride</mark>', $out );
		$this->assertStringNotContainsString( '<scooter>', $out );
	}

	public function test_highlight_never_splits_an_entity_or_breaks_on_regex_characters() {
		$this->assertSame( 'Tom &amp; Jerry', nh_editorial_highlight( 'Tom & Jerry', 'amp' ), 'a match inside &amp; must be ignored' );
		$this->assertSame( 'a (b) c', nh_editorial_highlight( 'a (b) c', '(((' ), 'unbalanced brackets are harmless' );
		$this->assertSame( '<mark>C++</mark> guide', nh_editorial_highlight( 'C++ guide', 'C++' ) );
		$this->assertSame( 'plain', nh_editorial_highlight( 'plain', '' ) );
		$this->assertSame( '', nh_editorial_highlight( '', 'x' ) );
	}

	public function test_highlight_is_case_insensitive_and_handles_vietnamese() {
		$this->assertSame( 'Thuê <mark>xe</mark> <mark>máy</mark> Hà Nội', nh_editorial_highlight( 'Thuê xe máy Hà Nội', 'XE Máy' ) );
	}

	public function test_search_words_are_limited_and_deduplicated() {
		$this->assertSame( array( 'rent', 'bike' ), nh_editorial_search_words( ' rent, bike; a rent ' ) );
		$this->assertCount( 8, nh_editorial_search_words( 'aa bb cc dd ee ff gg hh ii jj' ) );
	}

	public function test_main_search_is_limited_to_posts_and_pages() {
		self::factory()->post->create( array( 'post_title' => 'Findable scooter post' ) );
		self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Findable scooter page',
			)
		);
		self::factory()->attachment->create_object(
			array(
				'file'           => 'findable-scooter.jpg',
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Findable scooter attachment',
			)
		);

		$this->go_to( home_url( '/?s=Findable' ) );
		global $wp_query;
		$types = array_unique( wp_list_pluck( $wp_query->posts, 'post_type' ) );
		sort( $types );
		$this->assertSame( array( 'page', 'post' ), $types );
	}

	public function test_suggest_attributes_follow_the_setting() {
		$this->assertStringContainsString( 'data-suggest-url="' . esc_url( rest_url( 'wp/v2/search' ) ) . '"', nh_editorial_search_suggest_attributes() );
		set_theme_mod( 'nh_search_suggest', false );
		$this->assertSame( '', nh_editorial_search_suggest_attributes() );
		remove_theme_mods();
	}

	public function test_core_rest_search_returns_the_fields_the_script_needs() {
		$id = self::factory()->post->create( array( 'post_title' => 'Rest searchable scooter' ) );
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'GET', '/wp/v2/search' );
		$request->set_query_params(
			array(
				'search'   => 'searchable',
				'per_page' => 5,
				'type'     => 'post',
				'subtype'  => 'post',
				'_fields'  => 'id,title,url',
			)
		);
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( $id, $data[0]['id'] );
		$this->assertSame( get_permalink( $id ), $data[0]['url'] );
		$this->assertSame( array( 'id', 'title', 'url' ), array_keys( $data[0] ) );
	}
}
