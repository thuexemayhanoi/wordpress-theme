<?php
/**
 * An article published through the WordPress REST API, the way the AI Writer publisher does it
 * (repository thuexemayhanoi/wp-aiwriter, scripts/engine.py): create with title, slug, status, content
 * and one category, read it back privately and publicly, open the permalink.
 *
 * The publisher's payload carries no meta. Everything below must therefore work for a plain article, and
 * the same flow must also work when the payload carries the companion plugin's meta keys.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

/**
 * Publishing through REST keeps every list, neighbour, search and cache current.
 */
class NH_Test_Publish_Flow extends NH_Render_Test_Case {

	/**
	 * Category IDs of the fixture.
	 *
	 * @var int[]
	 */
	private $cats = array();

	/**
	 * A hub with a child, six existing articles in the child, the production permalink structure.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->use_permalinks( '/%postname%/' );

		$hub        = self::factory()->category->create( array( 'name' => 'Cẩm nang' ) );
		$leaf       = self::factory()->category->create(
			array(
				'name'   => 'Hãng xe máy',
				'parent' => $hub,
			)
		);
		$this->cats = array(
			'hub'  => $hub,
			'leaf' => $leaf,
		);
		for ( $i = 1; $i <= 6; $i++ ) {
			self::factory()->post->create(
				array(
					'post_title'    => 'Bài có sẵn số ' . $i,
					'post_category' => array( $leaf ),
					'post_date'     => gmdate( 'Y-m-d H:i:s', strtotime( '-' . ( $i + 5 ) . ' days' ) ),
				)
			);
		}
		nh_editorial_cache_bump_now();

		// Warm the fragment cache, so the test proves it is refreshed rather than never filled.
		$this->go_to( get_category_link( $leaf ) );
		nh_editorial_get_hubs( 6 );
	}

	/**
	 * Create a post through the REST route with the publisher's payload shape.
	 *
	 * @param array $overrides Fields to replace.
	 * @return array{status: int, data: array}
	 */
	private function rest_create( array $overrides = array() ) {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$payload = array_merge(
			array(
				'title'      => 'Honda Wave đi đường hỗn hợp nội đô và ngoại thành ra sao?',
				'slug'       => 'excel-309-honda-wave-di-duong-hon-hop-noi-do-va-ngoai-thanh-ra-sao',
				'status'     => 'publish',
				'content'    => "<!-- wp-aiwriter:matrix_id=309;sha256=abc123 -->\n<h2>Trả lời nhanh</h2>\n<p>Honda Wave là xe số phổ biến, đi hỗn hợp ổn định.</p>\n<h2>Phân tích thực tế</h2>\n<p>Nội dung xequyetdinh phân tích.</p>\n<h2>Lưu ý cuối</h2>\n<p>Kiểm tra giấy tờ trước khi thuê.</p>",
				'categories' => array( $this->cats['leaf'] ),
			),
			$overrides
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $payload ) );

		// WordPress 7.1 core reads undefined $prepared_post->id and ->post_parent properties when a draft is
		// created with a slug (class-wp-rest-posts-controller.php). The publisher does exactly that for
		// category mismatches. Those two warnings are core's and harmless, so they are the only ones let through.
		set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_error_reporting, WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Test only.
			static function ( $number, $message, $file ) {
				return 0 === strpos( $message, 'Undefined property: stdClass::' ) && false !== strpos( $file, 'class-wp-rest-posts-controller.php' );
			}
		);
		try {
			$response = rest_do_request( $request );
		} finally {
			restore_error_handler();
		}

		$this->assertSame( 201, $response->get_status(), 'the post is created' );

		// The end of the request: WordPress runs the shutdown hooks, which is where the theme invalidates its cache.
		$this->assertNotFalse( has_action( 'shutdown', 'nh_editorial_cache_bump_now' ), 'publishing asks for a cache refresh at the end of the request' );
		nh_editorial_cache_bump_now();
		wp_set_current_user( 0 );

		return array(
			'status' => $response->get_status(),
			'data'   => $response->get_data(),
		);
	}

	public function test_the_publisher_readback_sees_exactly_what_it_sent() {
		$created = $this->rest_create();
		$id      = (int) $created['data']['id'];

		$this->assertSame( 'publish', $created['data']['status'] );
		$this->assertSame( 'excel-309-honda-wave-di-duong-hon-hop-noi-do-va-ngoai-thanh-ra-sao', $created['data']['slug'] );
		$this->assertSame( array( $this->cats['leaf'] ), $created['data']['categories'], 'no category is added or removed by the theme or the plugin' );

		// Public readback: rendered content must not gain anything from the theme or the plugin.
		$public = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/posts/' . $id ) );
		$this->assertSame( 200, $public->get_status() );
		$rendered = $public->get_data()['content']['rendered'];

		$post = get_post( $id );
		remove_filter( 'the_content', 'nh_editorial_wrap_tables', 30 );
		remove_filter( 'the_content', array( 'NH_SEO_Fallback_Renderer', 'filter_content' ), 20 );
		$core_only = apply_filters( 'the_content', $post->post_content );
		$this->assertSame( $core_only, $rendered, 'REST content is byte-identical to what WordPress core alone would render' );
		$this->assertStringNotContainsString( 'nh-seo', $rendered );
		$this->assertStringNotContainsString( 'table-scroll', $rendered );
	}

	public function test_the_permalink_shows_title_content_breadcrumb_and_one_h1() {
		$created = $this->rest_create();
		$html    = $this->render_url( $created['data']['link'] );
		$xp      = $this->xpath( $html );

		$this->assertSame( home_url( '/excel-309-honda-wave-di-duong-hon-hop-noi-do-va-ngoai-thanh-ra-sao/' ), $created['data']['link'] );
		$this->assertSame( 1, $xp->query( '//h1' )->length );
		$this->assertSame( 'Honda Wave đi đường hỗn hợp nội đô và ngoại thành ra sao?', trim( $xp->query( '//h1' )->item( 0 )->textContent ) );
		$this->assertStringContainsString( 'Honda Wave là xe số phổ biến', $html );
		$this->assertSame( 3, $xp->query( $this->by_class( 'toc__item', 'li' ) )->length, 'the three H2 headings of the article become the table of contents' );

		$trail = array();
		foreach ( $xp->query( '//nav[contains(@class,"breadcrumbs")]//li' ) as $item ) {
			$trail[] = trim( $item->textContent );
		}
		$this->assertSame( array( 'Home', 'Cẩm nang', 'Hãng xe máy' ), array_slice( $trail, 0, 3 ) );
		$this->assertSame( 0, $xp->query( $this->by_class( 'article-intro' ) )->length, 'no summary or takeaways are invented for a plain article' );
		$this->assertSame( 0, $xp->query( '//section[@id="faq"]' )->length );
		$this->assertSame( 0, $xp->query( '//aside[contains(@class,"pillar-box")]//*[contains(@class,"pillar-box__link")]' )->length, 'no guide link without a mapped Pillar page' );
		$this->assertSame( 1, $xp->query( '//aside[contains(@class,"pillar-box")]//*[contains(@class,"pillar-box__hub")]/a[@href="' . get_category_link( $this->cats['hub'] ) . '"]' )->length, 'the topic box still links up to the hub category' );
		$this->assertSame( 1, $xp->query( '//head/link[@rel="canonical"]' )->length, 'core prints the single canonical when no SEO plugin is active' );
	}

	public function test_the_new_article_appears_in_hub_lists_home_neighbours_related_and_search() {
		$before_hub  = array_column( nh_editorial_get_hubs( 6 ), 'count', 'slug' );
		$newest_old  = get_posts(
			array(
				'category'       => $this->cats['leaf'],
				'posts_per_page' => 1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'post_type'      => 'post',
			)
		)[0];
		$related_old = nh_editorial_get_related_ids( $newest_old->ID, 3 );

		$created = $this->rest_create();
		$id      = (int) $created['data']['id'];
		$url     = $created['data']['link'];

		// Hub counts and category pages.
		$after_hub = array_column( nh_editorial_get_hubs( 6 ), 'count', 'slug' );
		$this->assertSame( $before_hub[ get_category( $this->cats['hub'] )->slug ] + 1, $after_hub[ get_category( $this->cats['hub'] )->slug ] );
		foreach ( array( 'leaf', 'hub' ) as $key ) {
			$html = $this->render_url( get_category_link( $this->cats[ $key ] ) );
			$this->assertContains( $url, $this->hrefs( $html ), $key . ' archive lists the new article' );
			$first = $this->xpath( $html )->query( $this->by_class( 'card--post', 'article' ) . '//*[contains(@class,"card__title")]/a' )->item( 0 );
			$this->assertSame( $url, $first->getAttribute( 'href' ), $key . ' archive shows it first (newest)' );
		}

		// The front page.
		$this->assertContains( $url, $this->hrefs( $this->render_url( home_url( '/' ) ) ), 'the front page lists it' );

		// Previous/next inside the silo: the old newest article now has the new one as its next article.
		$adjacent = nh_editorial_get_adjacent_ids( $newest_old->ID );
		$this->assertSame( $id, (int) $adjacent['next'], 'the neighbour of the previous newest article is the new article' );
		$html = $this->render_url( get_permalink( $newest_old ) );
		$this->assertContains( $url, $this->hrefs( $html ) );

		// Related articles are recomputed.
		$related_new = nh_editorial_get_related_ids( $newest_old->ID, 3 );
		$this->assertNotSame( $related_old, $related_new );
		$this->assertContains( $id, $related_new );

		// Search (core search; the companion plugin's index is covered by the plugin suite).
		$html = $this->render_url( home_url( '/?s=xequyetdinh' ) );
		$this->assertContains( $url, $this->hrefs( $html ), 'the built-in search finds the new article' );
	}

	public function test_a_draft_created_for_a_category_mismatch_is_invisible_everywhere() {
		$created = $this->rest_create(
			array(
				'status'     => 'draft',
				'title'      => 'Bản nháp chờ chủ sở hữu duyệt',
				'slug'       => 'excel-310-ban-nhap',
				'categories' => array(),
			)
		);
		$id      = (int) $created['data']['id'];
		$this->assertSame( 'draft', $created['data']['status'] );

		$public = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/posts/' . $id ) );
		$this->assertContains( $public->get_status(), array( 401, 403, 404 ), 'a draft is not readable without credentials' );

		foreach ( array(
			'home'     => home_url( '/' ),
			'category' => get_category_link( $this->cats['leaf'] ),
			'search'   => home_url( '/?s=' . rawurlencode( 'Bản nháp' ) ),
		) as $label => $url ) {
			$html = $this->render_url( $url );
			$this->assertStringNotContainsString( 'Bản nháp chờ chủ sở hữu duyệt', $html, $label );
			$this->assertStringNotContainsString( 'excel-310-ban-nhap', $html, $label );
		}
		$this->assertNotContains(
			$id,
			nh_editorial_get_related_ids(
				get_posts(
					array(
						'category'       => $this->cats['leaf'],
						'posts_per_page' => 1,
					)
				)[0]->ID,
				6
			)
		);
	}

	public function test_a_payload_with_the_companion_meta_is_rendered_and_validated() {
		$created = $this->rest_create(
			array(
				'slug' => 'excel-311-co-meta',
				'meta' => array(
					'nh_summary'         => 'Trả lời ngắn gọn một câu.',
					'nh_takeaways'       => array( 'Ý chính một', 'Ý chính hai' ),
					'nh_faq'             => array(
						array(
							'question' => 'Có đắt không?',
							'answer'   => '<p>Tùy loại xe.</p><script>alert(1)</script>',
						),
					),
					'nh_sources'         => array(
						array(
							'title' => 'Nguồn chính thức',
							'url'   => 'https://example.org/nguon',
						),
					),
					'nh_content_updated' => gmdate( 'Y-m-d', strtotime( '+1 day' ) ),
					'nh_topic_cluster'   => 'Thuê xe số',
					'unknown_meta_key'   => 'ignored',
				),
			)
		);
		$id      = (int) $created['data']['id'];

		$this->assertSame( 'Trả lời ngắn gọn một câu.', get_post_meta( $id, 'nh_summary', true ) );
		$this->assertSame( '', (string) get_post_meta( $id, 'unknown_meta_key', true ), 'unregistered meta is not stored through REST' );
		$this->assertCount( 1, wp_get_object_terms( $id, 'topic_cluster' ), 'the alias became a Topic Cluster term' );

		$html = $this->render_url( $created['data']['link'] );
		$xp   = $this->xpath( $html );
		$this->assertSame( 1, $xp->query( $this->by_class( 'summary', 'section' ) )->length );
		$this->assertSame( 1, $xp->query( '//section[@id="faq"]' )->length );
		$this->assertSame( 1, $xp->query( '//section[@id="sources"]' )->length );
		$this->assertSame( 0, $xp->query( '//script[contains(.,"alert(")]' )->length );
		$this->assertSame( 0, $xp->query( $this->by_class( 'meta__updated' ) )->length, 'an update date in the future is rejected, so no fake freshness appears' );
	}
}
