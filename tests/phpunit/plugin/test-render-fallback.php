<?php
/**
 * Tests for the fallback renderer that appends the editorial extras through `the_content`.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * NH_SEO_Fallback_Renderer.
 */
class NH_Test_Render_Fallback extends NH_Unit_Test_Case {

	/**
	 * Headings of every block, by feature.
	 *
	 * @var array<string, string>
	 */
	private $headings = array(
		'updated'   => 'Content updated:',
		'summary'   => 'Quick answer',
		'takeaways' => 'Key takeaways',
		'faq'       => 'Frequently asked questions',
		'sources'   => 'Sources',
	);

	public function set_up() {
		parent::set_up();
		remove_theme_support( 'nguyen-ha-companion' );
		update_option( NH_SEO_Keys::OPTION_SETTINGS, array( 'fallback_render' => 1 ) );
		$this->reset_styles();
		$this->use_permalinks();
	}

	public function tear_down() {
		$this->reset_styles();
		remove_theme_support( 'nguyen-ha-companion' );
		parent::tear_down();
	}

	/**
	 * Start from an empty style queue.
	 *
	 * @return void
	 */
	private function reset_styles() {
		$GLOBALS['wp_styles'] = new WP_Styles(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test isolation.
	}

	/**
	 * Create a published post or page with every extra filled in.
	 *
	 * @param array $args Post arguments.
	 * @return int
	 */
	private function post_with_extras( array $args = array() ) {
		$post_id = self::factory()->post->create(
			array_merge(
				array(
					'post_status'  => 'publish',
					'post_title'   => 'Which scooter?',
					'post_content' => 'The article body.',
				),
				$args
			)
		);
		update_post_meta( $post_id, 'nh_summary', 'Pick an automatic scooter.' );
		update_post_meta( $post_id, 'nh_takeaways', array( 'Automatic is easiest.', 'Check the helmet.' ) );
		update_post_meta(
			$post_id,
			'nh_faq',
			array(
				array(
					'question' => 'Can I rent without experience?',
					'answer'   => '<p>Ask the shop.</p>',
				),
			)
		);
		update_post_meta( $post_id, 'nh_sources', array( array( 'url' => 'https://example.org/guide' ) ) );
		update_post_meta( $post_id, 'nh_content_updated', '2024-01-15' );
		return $post_id;
	}

	/**
	 * Run the main loop of a URL and return what the_content() prints for the first post.
	 *
	 * @param string $url URL to visit.
	 * @return string
	 */
	private function main_content( $url ) {
		$this->go_to( $url );
		$this->assertTrue( have_posts(), 'the main query has a post for ' . $url );
		the_post();
		ob_start();
		the_content();
		return ob_get_clean();
	}

	/**
	 * Assert which blocks a piece of HTML contains.
	 *
	 * @param string   $html     HTML.
	 * @param string[] $expected Features expected to be present; all others must be absent.
	 * @return void
	 */
	private function assert_blocks( $html, array $expected ) {
		foreach ( $this->headings as $feature => $heading ) {
			if ( in_array( $feature, $expected, true ) ) {
				$this->assertStringContainsString( $heading, $html, "$feature should render" );
			} else {
				$this->assertStringNotContainsString( $heading, $html, "$feature should not render" );
			}
		}
	}

	public function test_every_extra_is_rendered_when_the_theme_declares_nothing() {
		$post_id = $this->post_with_extras();
		$html    = $this->main_content( get_permalink( $post_id ) );

		$this->assert_blocks( $html, array( 'updated', 'summary', 'takeaways', 'faq', 'sources' ) );
		$this->assertStringContainsString( 'The article body.', $html );
		$this->assertStringContainsString( 'Pick an automatic scooter.', $html );
		$this->assertStringContainsString( 'Can I rent without experience?', $html );
		$this->assertStringContainsString( 'https://example.org/guide', $html );
	}

	public function test_summary_and_takeaways_come_first_and_faq_and_sources_last() {
		$post_id = $this->post_with_extras();
		$html    = $this->main_content( get_permalink( $post_id ) );

		$positions = array(
			'updated'   => strpos( $html, 'nh-seo-updated' ),
			'summary'   => strpos( $html, 'nh-seo-summary' ),
			'takeaways' => strpos( $html, 'nh-seo-takeaways' ),
			'content'   => strpos( $html, 'The article body.' ),
			'faq'       => strpos( $html, 'nh-seo-faq' ),
			'sources'   => strpos( $html, 'nh-seo-sources' ),
		);
		foreach ( $positions as $name => $position ) {
			$this->assertNotFalse( $position, $name );
		}
		$ordered = $positions;
		asort( $ordered );
		$this->assertSame( array( 'updated', 'summary', 'takeaways', 'content', 'faq', 'sources' ), array_keys( $ordered ) );
	}

	public function test_only_the_features_the_theme_does_not_declare_are_rendered() {
		$features = array( 'updated', 'summary', 'takeaways', 'faq', 'sources' );
		$post_id  = $this->post_with_extras();

		foreach ( $features as $declared ) {
			remove_theme_support( 'nguyen-ha-companion' );
			add_theme_support( 'nguyen-ha-companion', array( $declared => true ) );
			$html = $this->main_content( get_permalink( $post_id ) );
			$this->assert_blocks( $html, array_values( array_diff( $features, array( $declared ) ) ) );
		}
	}

	public function test_a_theme_declaring_several_features_leaves_only_the_rest() {
		$post_id = $this->post_with_extras();
		add_theme_support(
			'nguyen-ha-companion',
			array(
				'summary' => true,
				'faq'     => true,
				'updated' => true,
			)
		);

		$this->assert_blocks( $this->main_content( get_permalink( $post_id ) ), array( 'takeaways', 'sources' ) );
	}

	public function test_features_declared_false_are_still_rendered_by_the_fallback() {
		$post_id = $this->post_with_extras();
		add_theme_support(
			'nguyen-ha-companion',
			array(
				'summary' => true,
				'faq'     => false,
			)
		);

		$this->assert_blocks( $this->main_content( get_permalink( $post_id ) ), array( 'updated', 'takeaways', 'faq', 'sources' ) );
	}

	public function test_nothing_is_added_when_the_theme_declares_every_feature() {
		$post_id = $this->post_with_extras();
		add_theme_support(
			'nguyen-ha-companion',
			array(
				'summary'   => true,
				'takeaways' => true,
				'faq'       => true,
				'sources'   => true,
				'updated'   => true,
			)
		);

		$html = $this->main_content( get_permalink( $post_id ) );

		$this->assertStringNotContainsString( 'nh-seo-', $html );
		$this->assertStringContainsString( 'The article body.', $html );
	}

	public function test_declaring_support_without_arguments_means_the_theme_renders_everything() {
		$post_id = $this->post_with_extras();
		add_theme_support( 'nguyen-ha-companion' );

		$this->assertStringNotContainsString( 'nh-seo-', $this->main_content( get_permalink( $post_id ) ) );
	}

	public function test_the_fallback_can_be_switched_off() {
		$post_id = $this->post_with_extras();
		update_option( NH_SEO_Keys::OPTION_SETTINGS, array( 'fallback_render' => 0 ) );

		$html = $this->main_content( get_permalink( $post_id ) );

		$this->assertStringNotContainsString( 'nh-seo-', $html );
		$this->assertStringContainsString( 'The article body.', $html );
	}

	public function test_only_extras_that_have_data_are_rendered() {
		$post_id = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => 'Body.',
			)
		);
		update_post_meta( $post_id, 'nh_summary', 'Only a summary.' );

		$this->assert_blocks( $this->main_content( get_permalink( $post_id ) ), array( 'summary' ) );
	}

	public function test_content_is_unchanged_for_posts_without_extras() {
		$post_id = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => "Plain body.\n\nSecond paragraph.",
			)
		);
		$with    = $this->main_content( get_permalink( $post_id ) );

		remove_filter( 'the_content', array( 'NH_SEO_Fallback_Renderer', 'filter_content' ), 20 );
		$without = $this->main_content( get_permalink( $post_id ) );

		$this->assertSame( $without, $with );
		$this->assertStringContainsString( '<p>Plain body.</p>', $with );
	}

	public function test_the_filter_runs_after_the_core_content_filters() {
		$this->assertSame( 20, has_filter( 'the_content', array( 'NH_SEO_Fallback_Renderer', 'filter_content' ) ) );
		$this->assertLessThan( 20, has_filter( 'the_content', 'wpautop' ) );
		$this->assertLessThan( 20, has_filter( 'the_content', 'do_shortcode' ) );
	}

	public function test_pages_render_the_extras_too() {
		$page_id = $this->post_with_extras( array( 'post_type' => 'page' ) );

		$html = $this->main_content( get_permalink( $page_id ) );

		$this->assert_blocks( $html, array( 'updated', 'summary', 'takeaways', 'faq', 'sources' ) );
	}

	public function test_nothing_is_added_on_archives_search_and_the_blog_home() {
		$category = self::factory()->category->create( array( 'name' => 'Rentals' ) );
		$post_id  = $this->post_with_extras( array( 'post_category' => array( $category ) ) );
		unset( $post_id );

		foreach ( array( home_url( '/' ), get_category_link( $category ), home_url( '/?s=scooter' ), get_author_posts_url( 1 ), home_url( '/?m=' . gmdate( 'Y' ) ) ) as $url ) {
			$this->go_to( $url );
			$this->assertFalse( is_singular(), $url );
			$printed = '';
			while ( have_posts() ) {
				the_post();
				ob_start();
				the_content();
				$printed .= ob_get_clean();
			}
			$this->assertStringNotContainsString( 'nh-seo-', $printed, $url );
		}

		$this->go_to( home_url( '/definitely-not-here/' ) );
		$this->assertTrue( is_404() );
		$this->assertStringNotContainsString( 'nh-seo-', apply_filters( 'the_content', 'Text' ) );
	}

	public function test_nothing_is_added_outside_the_loop() {
		$post_id = $this->post_with_extras();
		$this->go_to( get_permalink( $post_id ) );
		$this->assertTrue( is_singular() );
		$this->assertFalse( in_the_loop() );

		$this->assertSame( "<p>Text</p>\n", apply_filters( 'the_content', 'Text' ) );
	}

	public function test_secondary_queries_never_get_the_blocks_of_the_main_post() {
		$main  = $this->post_with_extras( array( 'post_content' => 'Main body.' ) );
		$other = $this->post_with_extras( array( 'post_content' => 'Other body.' ) );

		$this->go_to( get_permalink( $main ) );
		the_post();

		$secondary = new WP_Query( array( 'p' => $other ) );
		$this->assertTrue( $secondary->have_posts() );
		$secondary->the_post();
		ob_start();
		the_content();
		$other_html = ob_get_clean();
		wp_reset_postdata();

		$this->assertStringContainsString( 'Other body.', $other_html );
		$this->assertStringNotContainsString( 'nh-seo-', $other_html, 'another post inside the main loop gets nothing' );

		ob_start();
		the_content();
		$main_html = ob_get_clean();
		$this->assertStringContainsString( 'nh-seo-summary', $main_html );
		$this->assertStringContainsString( 'Main body.', $main_html );
	}

	public function test_nothing_is_added_in_feeds() {
		$post_id = $this->post_with_extras();

		$html = $this->main_content( add_query_arg( 'feed', 'rss2', get_permalink( $post_id ) ) );

		$this->assertTrue( is_feed() );
		$this->assertStringNotContainsString( 'nh-seo-', $html );
	}

	public function test_excerpts_do_not_start_with_a_fallback_block() {
		$post_id = $this->post_with_extras(
			array(
				'post_content' => 'Plain article body for the excerpt.',
				'post_excerpt' => '',
			)
		);
		$this->go_to( get_permalink( $post_id ) );
		the_post();

		$excerpt = get_the_excerpt();

		$this->assertStringContainsString( 'Plain article body for the excerpt.', $excerpt );
		$this->assertStringNotContainsString( 'Quick answer', $excerpt );
		$this->assertStringNotContainsString( 'Pick an automatic scooter.', $excerpt );
	}

	public function test_nothing_is_added_for_password_protected_posts() {
		$post_id = $this->post_with_extras( array( 'post_password' => 'secret' ) );

		$html = $this->main_content( get_permalink( $post_id ) );

		$this->assertStringContainsString( 'post-password-form', $html );
		$this->assertStringNotContainsString( 'nh-seo-', $html );
		$this->assertStringNotContainsString( 'Pick an automatic scooter.', $html );
		$this->assertStringNotContainsString( 'Can I rent without experience?', $html );
	}

	public function test_blocks_appear_once_a_password_protected_post_is_unlocked() {
		require_once ABSPATH . WPINC . '/class-phpass.php';
		$hasher  = new PasswordHash( 8, true );
		$post_id = $this->post_with_extras( array( 'post_password' => 'secret' ) );
		$cookie  = 'wp-postpass_' . COOKIEHASH;

		$_COOKIE[ $cookie ] = $hasher->HashPassword( 'secret' );
		try {
			$html = $this->main_content( get_permalink( $post_id ) );
		} finally {
			unset( $_COOKIE[ $cookie ] );
		}

		$this->assertStringNotContainsString( 'post-password-form', $html );
		$this->assertStringContainsString( 'nh-seo-summary', $html );
		$this->assertStringContainsString( 'The article body.', $html );
	}

	public function test_other_post_types_are_left_alone() {
		register_post_type(
			'nh_test_book',
			array(
				'public'   => true,
				'supports' => array( 'title', 'editor' ),
			)
		);
		try {
			$post_id = $this->post_with_extras( array( 'post_type' => 'nh_test_book' ) );
			$html    = $this->main_content( home_url( '/?post_type=nh_test_book&p=' . $post_id ) );
		} finally {
			unregister_post_type( 'nh_test_book' );
		}

		$this->assertStringContainsString( 'The article body.', $html );
		$this->assertStringNotContainsString( 'nh-seo-', $html );
	}

	public function test_drafts_are_decorated_when_an_editor_previews_them() {
		$post_id = $this->post_with_extras( array( 'post_status' => 'draft' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$html = $this->main_content( home_url( '/?p=' . $post_id . '&preview=true' ) );

		$this->assertTrue( is_preview() );
		$this->assertStringContainsString( 'nh-seo-summary', $html );
	}

	public function test_hostile_stored_values_cannot_inject_markup_through_the_whole_pipeline() {
		global $wpdb;
		$post_id = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => 'Safe body.',
			)
		);
		foreach ( array(
			'nh_summary'   => '<script>alert(1)</script>Summary <img src=x onerror=alert(2)>',
			'nh_takeaways' => maybe_serialize( array( '<img src=x onerror=alert(3)>', '"><script>alert(4)</script>' ) ),
			'nh_faq'       => maybe_serialize(
				array(
					array(
						'question' => '<script>alert(5)</script>Question',
						'answer'   => '<p onclick="evil()">Answer</p><iframe src="javascript:alert(6)"></iframe><a href="javascript:alert(7)">x</a>',
					),
				)
			),
			'nh_sources'   => maybe_serialize(
				array(
					array(
						'title' => '<svg onload=alert(8)>',
						'url'   => 'https://example.org/x" onmouseover="alert(9)',
					),
					array( 'url' => 'javascript:alert(10)' ),
				)
			),
		) as $key => $value ) {
			$wpdb->insert(
				$wpdb->postmeta,
				array(
					'post_id'    => $post_id,
					'meta_key'   => $key,
					'meta_value' => $value,
				)
			);
		}
		wp_cache_delete( $post_id, 'post_meta' );

		$html = $this->main_content( get_permalink( $post_id ) );

		foreach ( array( '<script', '<img', '<iframe', '<svg', 'onclick', 'onerror', 'onload', 'javascript:' ) as $needle ) {
			$this->assertStringNotContainsStringIgnoringCase( $needle, $html, $needle );
		}
		$this->assertDoesNotMatchRegularExpression( '/<[^>]*\son[a-z]+\s*=/i', $html, 'no event handler attributes' );
		$this->assertStringContainsString( 'Safe body.', $html );
		$this->assertStringContainsString( 'nh-seo-faq', $html );
	}

	public function test_the_stylesheet_is_enqueued_in_the_head_only_when_a_block_will_show() {
		$with    = $this->post_with_extras();
		$without = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$this->go_to( get_permalink( $with ) );
		do_action( 'wp_enqueue_scripts' );
		$this->assertTrue( wp_style_is( 'nh-seo-frontend', 'enqueued' ) );
		$registered = wp_styles()->registered['nh-seo-frontend'];
		$this->assertStringEndsWith( 'assets/frontend.css', $registered->src );
		$this->assertSame( NH_SEO_VERSION, $registered->ver );

		$this->reset_styles();
		$this->go_to( get_permalink( $without ) );
		do_action( 'wp_enqueue_scripts' );
		$this->assertFalse( wp_style_is( 'nh-seo-frontend', 'enqueued' ), 'a post without extras loads no stylesheet' );

		$this->go_to( home_url( '/' ) );
		do_action( 'wp_enqueue_scripts' );
		$this->assertFalse( wp_style_is( 'nh-seo-frontend', 'enqueued' ), 'archives load no stylesheet' );
	}

	public function test_the_stylesheet_is_not_enqueued_when_nothing_will_render() {
		$post_id = $this->post_with_extras();

		add_theme_support( 'nguyen-ha-companion' );
		$this->go_to( get_permalink( $post_id ) );
		do_action( 'wp_enqueue_scripts' );
		$this->assertFalse( wp_style_is( 'nh-seo-frontend', 'enqueued' ), 'the theme renders everything' );
		remove_theme_support( 'nguyen-ha-companion' );

		update_option( NH_SEO_Keys::OPTION_SETTINGS, array( 'fallback_render' => 0 ) );
		do_action( 'wp_enqueue_scripts' );
		$this->assertFalse( wp_style_is( 'nh-seo-frontend', 'enqueued' ), 'the fallback is off' );
		update_option( NH_SEO_Keys::OPTION_SETTINGS, array( 'fallback_render' => 1 ) );

		$protected = $this->post_with_extras( array( 'post_password' => 'secret' ) );
		$this->go_to( get_permalink( $protected ) );
		do_action( 'wp_enqueue_scripts' );
		$this->assertFalse( wp_style_is( 'nh-seo-frontend', 'enqueued' ), 'protected posts show nothing' );
	}

	public function test_the_stylesheet_is_a_safety_net_when_blocks_render_without_the_head_hook() {
		$post_id = $this->post_with_extras();
		$this->assertFalse( wp_style_is( 'nh-seo-frontend', 'enqueued' ) );

		$this->main_content( get_permalink( $post_id ) );

		$this->assertTrue( wp_style_is( 'nh-seo-frontend', 'enqueued' ) );
	}

	public function test_the_hooks_are_registered_by_the_plugin() {
		$this->assertIsInt( has_action( 'wp_enqueue_scripts', array( 'NH_SEO_Fallback_Renderer', 'maybe_enqueue_style' ) ) );
	}
}
