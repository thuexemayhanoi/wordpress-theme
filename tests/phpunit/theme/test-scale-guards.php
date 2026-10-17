<?php
/**
 * Guards that keep large sites fast: author archive counting and the search suggestion limit.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

/**
 * Author archives are counted without SQL_CALC_FOUND_ROWS and still paginate correctly.
 */
class NH_Test_Author_Archive_Counting extends NH_Render_Test_Case {

	/**
	 * SQL of the main query of the last go_to().
	 *
	 * @var string
	 */
	private $main_sql = '';

	/**
	 * Create an author with a number of published articles.
	 *
	 * @param int $count Articles.
	 * @return int Author ID.
	 */
	private function author_with_posts( $count ) {
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		for ( $i = 0; $i < $count; $i++ ) {
			self::factory()->post->create(
				array(
					'post_author' => $author,
					'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '-' . ( $i + 1 ) . ' hours' ) ),
				)
			);
		}
		nh_editorial_cache_bump_now();
		return $author;
	}

	/**
	 * Capture the main query's SQL.
	 *
	 * @return void
	 */
	private function capture_sql() {
		$this->main_sql = '';
		add_filter(
			'posts_request',
			function ( $sql, $query ) {
				if ( $query->is_main_query() ) {
					$this->main_sql = $sql;
				}
				return $sql;
			},
			10,
			2
		);
	}

	public function test_the_page_is_queried_without_sql_calc_found_rows_and_pages_add_up() {
		$author = $this->author_with_posts( 25 );
		$this->capture_sql();

		$this->go_to( get_author_posts_url( $author ) );
		$this->assertStringNotContainsString( 'SQL_CALC_FOUND_ROWS', $this->main_sql );
		$this->assertSame( 25, (int) $GLOBALS['wp_query']->found_posts );
		$this->assertSame( 3, (int) $GLOBALS['wp_query']->max_num_pages );
		$this->assertCount( 10, $GLOBALS['wp_query']->posts );

		$html = $this->render_current_page();
		$this->assertStringContainsString( '25 articles', $html );
		$this->assertSame( 1, $this->count_nodes( $html, '//nav[contains(@class,"pagination")]' ) );

		$this->go_to( trailingslashit( get_author_posts_url( $author ) ) . 'page/3/' );
		$this->assertFalse( is_404() );
		$this->assertCount( 5, $GLOBALS['wp_query']->posts, 'the last page holds the remainder' );

		$this->go_to( trailingslashit( get_author_posts_url( $author ) ) . 'page/4/' );
		$this->assertTrue( is_404(), 'a page beyond the last is a 404' );
	}

	public function test_the_count_follows_publishing_and_unpublishing() {
		$author = $this->author_with_posts( 11 );
		$this->go_to( get_author_posts_url( $author ) );
		$this->assertSame( 2, (int) $GLOBALS['wp_query']->max_num_pages );

		// Publishing schedules one cache invalidation for the end of its own request.
		$extra = self::factory()->post->create( array( 'post_author' => $author ) );
		$this->assertNotFalse( has_action( 'shutdown', 'nh_editorial_cache_bump_now' ), 'publishing asks for an invalidation' );
		nh_editorial_cache_bump_now();
		$this->go_to( get_author_posts_url( $author ) );
		$this->assertSame( 12, (int) $GLOBALS['wp_query']->found_posts, 'the next visitor sees the new article counted' );

		wp_update_post(
			array(
				'ID'          => $extra,
				'post_status' => 'draft',
			)
		);
		nh_editorial_cache_bump_now();
		$this->go_to( get_author_posts_url( $author ) );
		$this->assertSame( 11, (int) $GLOBALS['wp_query']->found_posts, 'a draft is never counted' );

		self::factory()->post->create(
			array(
				'post_author' => $author,
				'post_status' => 'private',
			)
		);
		nh_editorial_cache_bump_now();
		$this->go_to( get_author_posts_url( $author ) );
		$this->assertSame( 11, (int) $GLOBALS['wp_query']->found_posts, 'a private article is never counted for visitors' );
	}

	public function test_signed_in_users_and_narrowed_queries_keep_the_core_behavior() {
		$author = $this->author_with_posts( 12 );
		$this->capture_sql();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->go_to( get_author_posts_url( $author ) );
		$this->assertStringContainsString( 'SQL_CALC_FOUND_ROWS', $this->main_sql, 'editors may see private articles, so the core count is used' );
		$this->assertSame( 12, (int) $GLOBALS['wp_query']->found_posts );
		wp_set_current_user( 0 );

		$cat = self::factory()->category->create();
		$this->capture_sql();
		$this->go_to( add_query_arg( 'cat', $cat, get_author_posts_url( $author ) ) );
		$this->assertStringContainsString( 'SQL_CALC_FOUND_ROWS', $this->main_sql, 'a narrowed query is left to core' );
	}

	public function test_other_archives_are_untouched() {
		$cat = self::factory()->category->create();
		$this->make_posts( $cat, 12 );
		$this->capture_sql();
		$this->go_to( get_category_link( $cat ) );
		$this->assertStringContainsString( 'SQL_CALC_FOUND_ROWS', $this->main_sql );
		$this->assertSame( 12, (int) $GLOBALS['wp_query']->found_posts );
	}
}

/**
 * Search suggestions switch themselves off where the built-in search would scan the whole table.
 */
class NH_Test_Suggestion_Guard extends NH_Render_Test_Case {

	public function test_suggestions_run_on_a_small_site() {
		$this->assertNotSame( '', nh_editorial_search_suggest_attributes() );
		$this->assertFalse( nh_editorial_search_is_too_heavy_for_suggestions() );
	}

	public function test_suggestions_stop_above_the_limit_unless_an_index_is_active() {
		self::factory()->post->create_many( 5 );
		add_filter(
			'nh_editorial_suggest_article_limit',
			static function () {
				return 3;
			}
		);
		nh_editorial_cache_bump_now();

		$this->assertTrue( nh_editorial_search_is_too_heavy_for_suggestions() );
		$this->assertSame( '', nh_editorial_search_suggest_attributes(), 'the form carries no suggestion attributes' );
		$this->go_to( home_url( '/' ) );
		$this->assertSame( 0, $this->count_nodes( $this->render_current_page(), '//form[@data-suggest-url]' ), 'and the script has nothing to attach to' );

		add_filter( 'nh_editorial_search_index_active', '__return_true' );
		$this->assertFalse( nh_editorial_search_is_too_heavy_for_suggestions(), 'an active index makes the search cheap again' );
		$this->assertNotSame( '', nh_editorial_search_suggest_attributes() );
		remove_filter( 'nh_editorial_search_index_active', '__return_true' );
	}

	public function test_the_limit_can_be_disabled() {
		self::factory()->post->create_many( 5 );
		add_filter(
			'nh_editorial_suggest_article_limit',
			static function () {
				return 0;
			}
		);
		nh_editorial_cache_bump_now();
		$this->assertFalse( nh_editorial_search_is_too_heavy_for_suggestions() );
	}
}
