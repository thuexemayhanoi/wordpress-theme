<?php
/**
 * Shared fixtures and helpers for the schema module tests.
 *
 * SIMULATIONS. No third-party SEO plugin is installed or executed by these tests. "Yoast" and "Rank Math"
 * are simulated through the documented extension points only (the nh_seo_detected_seo_plugin filter, FAQ
 * block markup inside post content, the nh_seo_local_seo_probes filter and the stored list of Rank Math
 * modules). Behaviour against the real plugins was NOT verified here.
 *
 * @package nguyen-ha-seo-companion
 */

/**
 * Base class: clean options, pretty permalinks, request rendering and graph inspection helpers.
 */
abstract class NH_Schema_Test_Case extends NH_Unit_Test_Case {

	/**
	 * Number of remote HTTP requests attempted during the test.
	 *
	 * @var int
	 */
	protected $http_attempts = 0;

	public function set_up() {
		parent::set_up();
		remove_theme_support( 'nguyen-ha-companion' );
		delete_option( NH_SEO_Keys::OPTION_SETTINGS );
		delete_option( NH_SEO_Keys::OPTION_BUSINESS );
		delete_option( 'rank_math_modules' );
		update_option( 'timezone_string', 'Asia/Ho_Chi_Minh' );
		update_option( 'page_for_posts', 0 );
		update_option( 'page_on_front', 0 );
		update_option( 'show_on_front', 'posts' );
		$this->use_permalinks( '/%category%/%postname%/' );

		$this->http_attempts = 0;
		add_filter( 'pre_http_request', array( $this, 'count_http_request' ), 1, 3 );
	}

	/**
	 * Record (and block) any remote request: the module must never make one.
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
	 * Simulate an active SEO plugin that owns titles and the graph.
	 *
	 * @param string $slug yoast | rank-math | ...
	 * @return void
	 */
	protected function simulate_seo_plugin( $slug = 'yoast' ) {
		add_filter(
			'nh_seo_detected_seo_plugin',
			static function () use ( $slug ) {
				return $slug;
			}
		);
	}

	/**
	 * Store settings (merged over defaults by the shared sanitizer).
	 *
	 * @param array $settings Settings.
	 * @return void
	 */
	protected function set_settings( array $settings ) {
		update_option( NH_SEO_Keys::OPTION_SETTINGS, $settings );
	}

	/**
	 * Store a business profile.
	 *
	 * @param array $overrides Profile fields.
	 * @return void
	 */
	protected function set_profile( array $overrides ) {
		update_option( NH_SEO_Keys::OPTION_BUSINESS, $overrides );
	}

	/**
	 * A complete, clearly fictional profile (name, phone, street, locality, plus extras).
	 *
	 * @param array $overrides Fields to change.
	 * @return array
	 */
	protected function complete_profile( array $overrides = array() ) {
		return array_merge(
			array(
				'name'     => 'Example Scooter Hire',
				'type'     => 'AutoRental',
				'url'      => 'https://example.org/',
				'phone'    => '+00 000 000 000',
				'email'    => 'hello@example.org',
				'street'   => '1 Example Street',
				'locality' => 'Example City',
				'region'   => 'Example Region',
				'postal'   => '00000',
				'country'  => 'VN',
				'lat'      => '21.0285',
				'lng'      => '105.8542',
				'hours'    => array( 'Mo-Su 09:00-21:00' ),
				'social'   => array( 'https://example.org/social' ),
			),
			$overrides
		);
	}

	/**
	 * Store a FAQ on a post.
	 *
	 * @param int $post_id Post ID.
	 * @param int $count   Number of items.
	 * @return void
	 */
	protected function set_faq( $post_id, $count = 2 ) {
		$items = array();
		for ( $i = 1; $i <= $count; $i++ ) {
			$items[] = array(
				'question' => 'Question number ' . $i . '?',
				'answer'   => '<p>Answer number ' . $i . ' with a <a href="https://example.org/guide">guide</a>.</p>',
			);
		}
		update_post_meta( $post_id, NH_SEO_Keys::META_FAQ, $items );
	}

	/**
	 * Create an image attachment without touching the filesystem and return its ID.
	 *
	 * @param int $width  Width.
	 * @param int $height Height.
	 * @return int
	 */
	protected function create_image( $width = 1200, $height = 630 ) {
		$file = 'nh-test-' . wp_generate_password( 6, false ) . '.jpg';
		$id   = self::factory()->attachment->create_object(
			$file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Test image',
			)
		);
		update_post_meta(
			$id,
			'_wp_attachment_metadata',
			array(
				'width'  => $width,
				'height' => $height,
				'file'   => $file,
				'sizes'  => array(),
			)
		);
		return $id;
	}

	/**
	 * Go to a URL and return the JSON strings the schema module prints there.
	 *
	 * @param string $url URL.
	 * @return string[]
	 */
	protected function blocks_at( $url ) {
		$this->go_to( $url );
		return NH_SEO_Schema::blocks();
	}

	/**
	 * Decode JSON strictly.
	 *
	 * @param string $json JSON.
	 * @return array
	 */
	protected function decode( $json ) {
		$data = json_decode( $json, true, 512, JSON_THROW_ON_ERROR );
		$this->assertIsArray( $data );
		return $data;
	}

	/**
	 * Flatten printed blocks to a list of top-level nodes (a @graph is expanded).
	 *
	 * @param string[] $blocks JSON strings.
	 * @return array<int, array<string, mixed>>
	 */
	protected function nodes_of( array $blocks ) {
		$nodes = array();
		foreach ( $blocks as $json ) {
			$data = $this->decode( $json );
			$this->assertSame( 'https://schema.org', $data['@context'] );
			if ( isset( $data['@graph'] ) ) {
				foreach ( $data['@graph'] as $node ) {
					$nodes[] = $node;
				}
			} else {
				unset( $data['@context'] );
				$nodes[] = $data;
			}
		}
		return $nodes;
	}

	/**
	 * Node types as strings.
	 *
	 * @param array $nodes Nodes.
	 * @return string[]
	 */
	protected function types_of( array $nodes ) {
		return array_map(
			static function ( $node ) {
				return is_array( $node['@type'] ) ? implode( '+', $node['@type'] ) : $node['@type'];
			},
			$nodes
		);
	}

	/**
	 * First node of a type, or fail.
	 *
	 * @param array  $nodes Nodes.
	 * @param string $type  Type.
	 * @return array
	 */
	protected function node( array $nodes, $type ) {
		foreach ( $nodes as $node ) {
			if ( $type === $node['@type'] ) {
				return $node;
			}
		}
		$this->fail( 'No node of type ' . $type . ' in: ' . implode( ', ', $this->types_of( $nodes ) ) );
	}

	/**
	 * Assert the invariants every printed page must hold: valid JSON, unique @id, unique @type.
	 *
	 * @param string[] $blocks JSON strings.
	 * @return array<int, array<string, mixed>> Nodes.
	 */
	protected function assert_well_formed( array $blocks ) {
		$nodes = $this->nodes_of( $blocks );
		$ids   = array();
		foreach ( $nodes as $node ) {
			if ( isset( $node['@id'] ) ) {
				$ids[] = $node['@id'];
			}
		}
		$this->assertSame( $ids, array_values( array_unique( $ids ) ), 'Every @id must be unique on a page.' );

		$types = $this->types_of( $nodes );
		$this->assertSame( $types, array_values( array_unique( $types ) ), 'No @type may be printed twice on a page: ' . implode( ', ', $types ) );
		return $nodes;
	}

	/**
	 * All array keys used anywhere in a structure.
	 *
	 * @param mixed $data Data.
	 * @return string[]
	 */
	protected function all_keys( $data ) {
		$keys = array();
		if ( is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				if ( is_string( $key ) ) {
					$keys[] = $key;
				}
				$keys = array_merge( $keys, $this->all_keys( $value ) );
			}
		}
		return $keys;
	}
}
