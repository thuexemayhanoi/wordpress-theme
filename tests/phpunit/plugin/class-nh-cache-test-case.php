<?php
/**
 * Shared fixtures for the cache module tests.
 *
 * SIMULATIONS. Real cache plugins (LiteSpeed Cache, WP Rocket, W3 Total Cache, WP Super Cache) are not
 * installed or executed. The tests use spies on the hooks and function names those plugins publish; they can
 * prove what the cache module hands over and when, not that a real cache purges its files.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * Base class: a small site (silo of categories, tags, author, pages) and URL helpers.
 */
abstract class NH_Cache_Test_Case extends NH_Unit_Test_Case {

	/**
	 * Root, middle and leaf category (a chain).
	 *
	 * @var int[]
	 */
	protected $cats = array();

	/**
	 * Author.
	 *
	 * @var int
	 */
	protected $author;

	/**
	 * Remote requests attempted.
	 *
	 * @var int
	 */
	protected $http_attempts = 0;

	public function set_up() {
		parent::set_up();
		remove_theme_support( 'nguyen-ha-companion' );
		delete_option( NH_SEO_Keys::OPTION_SETTINGS );
		update_option( 'show_on_front', 'posts' );
		update_option( 'page_for_posts', 0 );
		$this->use_permalinks( '/%category%/%postname%/' );

		$this->author = self::factory()->user->create(
			array(
				'role'       => 'author',
				'user_login' => 'cache-author',
			)
		);
		$root         = self::factory()->category->create(
			array(
				'name' => 'Rental',
				'slug' => 'rental',
			)
		);
		$mid          = self::factory()->category->create(
			array(
				'name'   => 'Scooters',
				'slug'   => 'scooters',
				'parent' => $root,
			)
		);
		$leaf         = self::factory()->category->create(
			array(
				'name'   => 'Automatic',
				'slug'   => 'automatic',
				'parent' => $mid,
			)
		);
		$this->cats   = array( $root, $mid, $leaf );

		$GLOBALS['nh_test_cache_calls'] = array();
		unset( $GLOBALS['nh_test_cache_throw'] );
		$this->http_attempts = 0;
		add_filter( 'pre_http_request', array( $this, 'count_http_request' ), 1, 3 );

		NH_SEO_Cache::reset();
	}

	/**
	 * Block and count remote requests: the module must never make one.
	 *
	 * @param mixed  $preempt Preempt value.
	 * @param array  $args    Request args.
	 * @param string $url     URL.
	 * @return WP_Error
	 */
	public function count_http_request( $preempt, $args, $url ) {
		++$this->http_attempts;
		return new WP_Error( 'nh_blocked', 'Remote requests are blocked in tests: ' . $url );
	}

	/**
	 * Create a post in the leaf category by default.
	 *
	 * @param array $args wp_insert_post arguments.
	 * @return int
	 */
	protected function make_post( array $args = array() ) {
		static $counter = 0;
		++$counter;
		return self::factory()->post->create(
			array_merge(
				array(
					'post_title'    => 'Cache post ' . $counter,
					'post_content'  => 'Content',
					'post_excerpt'  => '',
					'post_author'   => $this->author,
					'post_category' => array( $this->cats[2] ),
					'post_status'   => 'publish',
				),
				$args
			)
		);
	}

	/**
	 * Publish a post the way the editor does (wp_update_post), with an explicit date. wp_publish_post() is
	 * avoided on purpose: it does not assign a slug to a draft.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $date    Local date and time.
	 * @return void
	 */
	protected function publish( $post_id, $date = '2026-02-15 10:00:00' ) {
		wp_update_post(
			array(
				'ID'            => $post_id,
				'post_status'   => 'publish',
				'post_date'     => $date,
				'post_date_gmt' => get_gmt_from_date( $date ),
				'edit_date'     => true,
			)
		);
	}

	/**
	 * A sorted copy of a URL list, for comparisons that ignore order.
	 *
	 * @param string[] $urls URLs.
	 * @return string[]
	 */
	protected function sorted( array $urls ) {
		$urls = array_values( $urls );
		sort( $urls );
		return $urls;
	}

	/**
	 * Assert two URL lists hold the same URLs, once each, in any order.
	 *
	 * @param string[] $expected Expected URLs.
	 * @param string[] $actual   Actual URLs.
	 * @param string   $message  Message.
	 * @return void
	 */
	protected function assertSameUrls( array $expected, array $actual, $message = '' ) {
		$this->assertSame( $actual, array_values( array_unique( $actual ) ), 'No URL twice. ' . $message );
		$this->assertSame( $this->sorted( $expected ), $this->sorted( $actual ), $message );
	}

	/**
	 * Calls recorded by the simulated cache plugin functions.
	 *
	 * @return array<int, array{0: string, 1: mixed}>
	 */
	protected function cache_calls() {
		return $GLOBALS['nh_test_cache_calls'];
	}

	/**
	 * Record every call to the generic purge action.
	 *
	 * @return ArrayObject
	 */
	protected function spy_on_purge_action() {
		$calls = new ArrayObject();
		add_action(
			'nh_seo_purge_urls',
			static function ( $urls ) use ( $calls ) {
				$calls[] = $urls;
			}
		);
		return $calls;
	}
}
