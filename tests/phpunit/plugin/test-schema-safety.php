<?php
/**
 * Schema module safety: injection, valid JSON everywhere, and never any invented rating, review or offer.
 *
 * SIMULATIONS: no SEO plugin is installed. Where a test needs "extras" mode it uses the documented
 * nh_seo_detected_seo_plugin filter. Real Yoast SEO / Rank Math output was not exercised.
 *
 * @package nguyen-ha-seo-companion
 */

// Shared fixtures and helpers.
require_once __DIR__ . '/class-nh-schema-test-case.php';

/**
 * Safety tests.
 */
class NH_Test_Schema_Safety extends NH_Schema_Test_Case {

	/** @var string */
	const HOSTILE = '</script><script>alert(1)</script><img src=x onerror=alert(2)>';

	/** @var int */
	private $post_id;
	/** @var int */
	private $cat_id;
	/** @var int */
	private $author;

	public function set_up() {
		parent::set_up();

		// Administrators may save unfiltered HTML, so the hostile markup really reaches the database. The
		// module must neutralise it on output whatever WordPress did (or did not do) on save.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		update_option( 'blogname', 'Site </script><b>Name</b> & Co' );
		update_option( 'blogdescription', 'Tagline ' . self::HOSTILE );

		$this->author = self::factory()->user->create(
			array(
				'role'         => 'author',
				'display_name' => 'Evil ' . self::HOSTILE,
			)
		);
		update_user_meta( $this->author, NH_SEO_Keys::USER_JOB_TITLE, 'Editor ' . self::HOSTILE );

		$this->cat_id = self::factory()->category->create( array( 'name' => 'Cat <b>bold</b> </script>' ) );

		$this->post_id = self::factory()->post->create(
			array(
				'post_title'    => 'Title ' . self::HOSTILE . ' Thuê xe máy Hà Nội',
				'post_content'  => 'Body <script>alert(3)</script> text ' . self::HOSTILE,
				'post_excerpt'  => 'Excerpt ' . self::HOSTILE,
				'post_author'   => $this->author,
				'post_category' => array( $this->cat_id ),
				'tags_input'    => array( 'tag </script>', 'tag-two' ),
			)
		);
		update_post_meta( $this->post_id, NH_SEO_Keys::META_SUMMARY, 'Summary ' . self::HOSTILE );
		update_post_meta( $this->post_id, NH_SEO_Keys::META_LOCAL_BUSINESS, 1 );
		update_post_meta(
			$this->post_id,
			NH_SEO_Keys::META_FAQ,
			array(
				array(
					'question' => 'Q1 ' . self::HOSTILE,
					'answer'   => '<p>A1 <script>alert(4)</script><a href="javascript:alert(5)" onclick="x()">link</a> ' . self::HOSTILE . '</p>',
				),
				array(
					'question' => 'Q2',
					'answer'   => 'A2 </script><script>alert(6)</script>',
				),
			)
		);

		$this->set_profile(
			$this->complete_profile(
				array(
					'name'     => 'Shop ' . self::HOSTILE,
					'street'   => '1 Road </script>',
					'locality' => 'City <b>x</b>',
				)
			)
		);

		$this->force_hostile_values();
		wp_set_current_user( 0 );
	}

	/**
	 * Term names and display names are always filtered by WordPress on save, and the companion plugin's own
	 * data layer sanitizes its meta and options on save (and again on read). Write the hostile values straight
	 * into the tables, as an import, a migration or a database edit could, so that the module's own output
	 * escaping is what gets tested.
	 *
	 * @return void
	 */
	private function force_hostile_values() {
		global $wpdb;
		$tag = get_term_by( 'name', 'tag-two', 'post_tag' );
		$wpdb->update( $wpdb->terms, array( 'name' => 'Cat <b>bold</b> </script>' ), array( 'term_id' => $this->cat_id ) );
		$wpdb->update( $wpdb->terms, array( 'name' => 'tag </script><i>x</i>' ), array( 'term_id' => $tag->term_id ) );
		$wpdb->update( $wpdb->users, array( 'display_name' => 'Evil ' . self::HOSTILE ), array( 'ID' => $this->author ) );

		$this->raw_meta( $wpdb->postmeta, 'post_id', $this->post_id, NH_SEO_Keys::META_SUMMARY, 'Summary ' . self::HOSTILE );
		$this->raw_meta(
			$wpdb->postmeta,
			'post_id',
			$this->post_id,
			NH_SEO_Keys::META_FAQ,
			array(
				array(
					'question' => 'Q1 ' . self::HOSTILE,
					'answer'   => '<p>A1 <script>alert(4)</script><a href="javascript:alert(5)" onclick="x()">link</a> ' . self::HOSTILE . '</p>',
				),
				array(
					'question' => 'Q2',
					'answer'   => 'A2 </script><script>alert(6)</script>',
				),
			)
		);
		$this->raw_meta( $wpdb->usermeta, 'user_id', $this->author, NH_SEO_Keys::USER_JOB_TITLE, 'Editor ' . self::HOSTILE );

		$wpdb->delete( $wpdb->options, array( 'option_name' => NH_SEO_Keys::OPTION_BUSINESS ) );
		$wpdb->insert(
			$wpdb->options,
			array(
				'option_name'  => NH_SEO_Keys::OPTION_BUSINESS,
				'option_value' => maybe_serialize(
					$this->complete_profile(
						array(
							'name'     => 'Shop ' . self::HOSTILE,
							'street'   => '1 Road </script>',
							'locality' => 'City <b>x</b>',
						)
					)
				),
				'autoload'     => 'no',
			)
		);

		clean_term_cache( array( $this->cat_id, $tag->term_id ), '', false );
		clean_user_cache( $this->author );
		wp_cache_flush();
	}

	/**
	 * Replace a meta row by direct SQL, bypassing every sanitize callback.
	 *
	 * @param string $table     Meta table.
	 * @param string $id_column Object ID column.
	 * @param int    $id        Object ID.
	 * @param string $key       Meta key.
	 * @param mixed  $value     Value (serialized when it is an array).
	 * @return void
	 */
	private function raw_meta( $table, $id_column, $id, $key, $value ) {
		global $wpdb;
		$wpdb->delete(
			$table,
			array(
				$id_column => $id,
				'meta_key' => $key,
			)
		);
		$wpdb->insert(
			$table,
			array(
				$id_column   => $id,
				'meta_key'   => $key,
				'meta_value' => maybe_serialize( $value ),
			)
		);
	}

	/**
	 * Captured output of the head hook at a URL, in the current mode.
	 *
	 * @param string $url URL.
	 * @return array{html: string, blocks: string[]}
	 */
	private function head_at( $url ) {
		$this->go_to( $url );
		ob_start();
		NH_SEO_Schema::print_head();
		$html = ob_get_clean();
		return array(
			'html'   => $html,
			'blocks' => NH_SEO_Schema::blocks(),
		);
	}

	/**
	 * Assert hostile input cannot leave the JSON string it lives in.
	 *
	 * @param array{html: string, blocks: string[]} $head  Captured head.
	 * @param string                                $label Message.
	 * @return void
	 */
	private function assert_hostile_input_is_neutralised( array $head, $label ) {
		$count = count( $head['blocks'] );
		$this->assertGreaterThan( 0, $count, $label . ': something must be printed to test it.' );
		$this->assertSame( $count, substr_count( $head['html'], '<script' ), $label . ': only the plugin script elements may exist.' );
		$this->assertSame( $count, substr_count( $head['html'], '</script' ), $label . ': no value may close the script element.' );
		$this->assertStringNotContainsString( '<img', $head['html'], $label . ': no markup can be injected.' );

		foreach ( $head['blocks'] as $json ) {
			$this->assertStringNotContainsString( '<', $json, $label . ': "<" is escaped as \\u003C.' );
			$this->assertStringNotContainsString( '>', $json, $label . ': ">" is escaped as \\u003E.' );
			$this->assertStringNotContainsString( '&', $json, $label . ': "&" is escaped as \\u0026.' );
			$this->decode( $json );
		}
	}

	/**
	 * Every string in a structure, with its path.
	 *
	 * @param mixed  $data Data.
	 * @param string $path Path.
	 * @return array<string, string>
	 */
	private function strings_in( $data, $path = '' ) {
		$out = array();
		if ( is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				$out += $this->strings_in( $value, $path . '/' . $key );
			}
		} elseif ( is_string( $data ) ) {
			$out[ $path ] = $data;
		}
		return $out;
	}

	public function test_fixture_really_holds_hostile_markup_in_the_database() {
		// Guards against a vacuous test: the injection must really be stored before it is neutralised on output.
		$this->assertStringContainsString( '<script>', get_post_field( 'post_title', $this->post_id, 'raw' ) );
		$this->assertStringContainsString( '<script>', get_post_field( 'post_excerpt', $this->post_id, 'raw' ) );
		$this->assertStringContainsString( '</script>', get_term( $this->cat_id, 'category' )->name );
		$this->assertStringContainsString( '</script>', get_userdata( $this->author )->display_name );
		$this->assertStringContainsString( '&lt;/script&gt;', (string) get_option( 'blogname' ), 'WordPress stores the site title entity-encoded.' );

		// The data layer's own fields, as stored by an import or a database edit (the save-time sanitizers are bypassed).
		global $wpdb;
		$stored = static function ( $table, $id_column, $id, $key ) use ( $wpdb ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table and column names come from $wpdb and this test.
			return (string) $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$table} WHERE {$id_column} = %d AND meta_key = %s", $id, $key ) );
		};
		$this->assertStringContainsString( '</script>', $stored( $wpdb->postmeta, 'post_id', $this->post_id, NH_SEO_Keys::META_SUMMARY ) );
		$this->assertStringContainsString( '</script>', $stored( $wpdb->postmeta, 'post_id', $this->post_id, NH_SEO_Keys::META_FAQ ) );
		$this->assertStringContainsString( '</script>', $stored( $wpdb->usermeta, 'user_id', $this->author, NH_SEO_Keys::USER_JOB_TITLE ) );
		$this->assertStringContainsString( '</script>', (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", NH_SEO_Keys::OPTION_BUSINESS ) ) );
	}

	public function test_hostile_input_is_neutralised_in_the_full_graph() {
		$head = $this->head_at( get_permalink( $this->post_id ) );
		$this->assert_hostile_input_is_neutralised( $head, 'full graph' );

		$types = $this->types_of( $this->nodes_of( $head['blocks'] ) );
		$this->assertContains( 'Article', $types );
		$this->assertContains( 'FAQPage', $types );
		$this->assertContains( 'AutoRental', $types );
		$this->assertContains( 'Person', $types );
	}

	public function test_hostile_input_is_neutralised_in_extras_blocks() {
		$this->simulate_seo_plugin( 'yoast' );
		$head = $this->head_at( get_permalink( $this->post_id ) );
		$this->assertCount( 2, $head['blocks'], 'FAQPage and LocalBusiness, each in its own element.' );
		$this->assert_hostile_input_is_neutralised( $head, 'extras' );
	}

	public function test_hostile_input_is_neutralised_on_archives_authors_and_search() {
		$urls = array(
			get_category_link( $this->cat_id ),
			get_term_link( get_term_by( 'slug', 'tag-two', 'post_tag' ) ),
			get_author_posts_url( $this->author ),
			home_url( '/' ),
			home_url( '/?s=' . rawurlencode( self::HOSTILE ) ),
		);
		foreach ( $urls as $url ) {
			$this->assert_hostile_input_is_neutralised( $this->head_at( $url ), $url );
		}
	}

	public function test_plain_text_properties_never_carry_markup() {
		$nodes = $this->nodes_of( $this->blocks_at( get_permalink( $this->post_id ) ) );
		foreach ( $nodes as $node ) {
			foreach ( $this->strings_in( $node ) as $path => $value ) {
				if ( false !== strpos( $path, '/acceptedAnswer/text' ) ) {
					continue; // FAQ answers hold the sanitized subset of HTML, checked below.
				}
				$this->assertSame( wp_strip_all_tags( $value ), $value, $node['@type'] . $path . ' must be plain text: ' . $value );
			}
		}

		$article = $this->node( $nodes, 'Article' );
		$this->assertStringContainsString( 'Thuê xe máy Hà Nội', $article['headline'], 'The harmless part of the title survives.' );
		$this->assertStringNotContainsString( '<', $article['headline'] );
	}

	public function test_text_helper_removes_markup_and_script_content_and_decodes_entities() {
		$this->assertSame( 'Hello', NH_SEO_Schema_Builder::text( '<script>alert(1)</script>Hello' ) );
		$this->assertSame( 'Hello world', NH_SEO_Schema_Builder::text( "  <b>Hello</b>\n\n  <style>p{}</style>world " ) );
		$this->assertSame( 'Tom & Jerry', NH_SEO_Schema_Builder::text( 'Tom &amp; Jerry' ) );
		$this->assertSame( 'x', NH_SEO_Schema_Builder::text( '&lt;b&gt;x&lt;/b&gt;' ), 'Markup that was stored entity-encoded is stripped too.' );
		$this->assertSame( '5 < 6', NH_SEO_Schema_Builder::text( '5 < 6' ) );
		$this->assertSame( '', NH_SEO_Schema_Builder::text( array( 'x' ) ) );
	}

	public function test_faq_answers_keep_only_the_allowed_subset_of_html() {
		$faq = $this->node( $this->nodes_of( $this->blocks_at( get_permalink( $this->post_id ) ) ), 'FAQPage' );
		foreach ( $faq['mainEntity'] as $question ) {
			$text = $question['acceptedAnswer']['text'];
			$this->assertSame( $text, strip_tags( $text, '<a><p><br><strong><b><em><i><ul><ol><li><code>' ), 'Only allowed tags remain.' );
			$this->assertStringNotContainsString( '<script', $text );
			$this->assertStringNotContainsString( 'javascript:', $text );
			$this->assertStringNotContainsString( 'onclick', $text );
		}
	}

	public function test_business_type_is_always_a_known_schema_type() {
		add_filter(
			'nh_seo_business_profile',
			static function ( $profile ) {
				$profile['type'] = 'Evil</script>Type';
				return $profile;
			}
		);
		$business = null;
		foreach ( $this->nodes_of( $this->blocks_at( get_permalink( $this->post_id ) ) ) as $node ) {
			if ( home_url( '/' ) . '#localbusiness' === ( isset( $node['@id'] ) ? $node['@id'] : '' ) ) {
				$business = $node;
			}
		}
		$this->assertIsArray( $business );
		$this->assertSame( 'LocalBusiness', $business['@type'] );
	}

	public function test_no_rating_review_or_offer_key_appears_on_any_page_in_any_mode() {
		$forbidden = array( 'aggregateRating', 'review', 'reviews', 'offers', 'offer', 'makesOffer', 'ratingValue', 'ratingCount', 'reviewCount', 'bestRating', 'price', 'priceCurrency', 'priceRange', 'interactionStatistic' );

		$urls = array(
			get_permalink( $this->post_id ),
			home_url( '/' ),
			get_category_link( $this->cat_id ),
			get_author_posts_url( $this->author ),
			home_url( '/?s=scooter' ),
		);
		$this->set_settings( array( 'localbusiness_on_home' => 1 ) );

		foreach ( array( 'full', 'extras' ) as $mode ) {
			$this->set_settings(
				array(
					'schema_mode'           => $mode,
					'localbusiness_on_home' => 1,
				)
			);
			foreach ( $urls as $url ) {
				$keys = array();
				foreach ( $this->blocks_at( $url ) as $json ) {
					$keys = array_merge( $keys, $this->all_keys( $this->decode( $json ) ) );
				}
				$this->assertSame( array(), array_values( array_intersect( $forbidden, $keys ) ), $mode . ' ' . $url );
			}
		}
	}

	public function test_price_range_is_printed_once_and_only_when_the_profile_has_it() {
		$this->set_profile( $this->complete_profile( array( 'price_range' => '$$' ) ) );
		$nodes = $this->nodes_of( $this->blocks_at( get_permalink( $this->post_id ) ) );

		$with = array();
		foreach ( $nodes as $node ) {
			if ( array_key_exists( 'priceRange', $node ) ) {
				$with[] = $node['@type'];
			}
		}
		$this->assertSame( array( 'AutoRental' ), $with );
		$this->assertSame( '$$', $this->node( $nodes, 'AutoRental' )['priceRange'] );
	}

	public function test_every_page_prints_valid_json_with_unique_ids_and_types_in_every_mode() {
		$urls = array(
			get_permalink( $this->post_id ),
			home_url( '/' ),
			get_category_link( $this->cat_id ),
			get_author_posts_url( $this->author ),
			home_url( '/?s=scooter' ),
			home_url( '/definitely-missing/' ),
		);
		foreach ( array( 'full', 'extras', 'off' ) as $mode ) {
			$this->set_settings(
				array(
					'schema_mode'           => $mode,
					'localbusiness_on_home' => 1,
				)
			);
			foreach ( $urls as $url ) {
				$blocks = $this->blocks_at( $url );
				$this->assert_well_formed( $blocks );
				if ( 'off' === $mode ) {
					$this->assertSame( array(), $blocks );
				}
			}
		}
	}

	public function test_unicode_and_slashes_are_not_escaped_in_the_output() {
		$json = $this->blocks_at( get_permalink( $this->post_id ) )[0];
		$this->assertStringContainsString( 'Thuê xe máy Hà Nội', $json );
		$this->assertStringContainsString( 'https://', $json );
		$this->assertStringNotContainsString( '\\/', $json );
		$this->assertStringNotContainsString( '\\u00ea', $json );
	}

	public function test_invalid_utf8_still_produces_valid_json() {
		$json = NH_SEO_Schema::encode(
			array(
				'@context' => 'https://schema.org',
				'name'     => "bad \xB1\x31 bytes",
			)
		);
		$this->assertNotSame( '', $json );
		$this->assertIsArray( json_decode( $json, true, 512, JSON_THROW_ON_ERROR ) );
	}

	public function test_data_that_cannot_be_encoded_prints_nothing_rather_than_broken_json() {
		add_filter(
			'nh_seo_schema_graph',
			static function ( $nodes ) {
				$nodes[] = array(
					'@type' => 'Thing',
					'@id'   => home_url( '/#nan' ),
					'x'     => NAN,
				);
				return $nodes;
			}
		);
		$this->assertSame( '', NH_SEO_Schema::encode( array( 'x' => NAN ) ) );
		$this->assertSame( array(), $this->blocks_at( get_permalink( $this->post_id ) ) );
	}

	public function test_graph_nodes_never_hold_empty_properties() {
		$nodes = $this->nodes_of( $this->blocks_at( home_url( '/' ) ) );
		foreach ( $nodes as $node ) {
			foreach ( $this->strings_in( $node ) as $path => $value ) {
				$this->assertNotSame( '', trim( $value ), $node['@type'] . $path . ' is empty.' );
			}
			$this->assertNoEmptyContainers( $node, $node['@type'] );
		}
	}

	/**
	 * Fail when an array or object property is empty anywhere in the node.
	 *
	 * @param mixed  $data Data.
	 * @param string $path Path.
	 * @return void
	 */
	private function assertNoEmptyContainers( $data, $path ) {
		if ( ! is_array( $data ) ) {
			return;
		}
		$this->assertNotSame( array(), $data, $path . ' is an empty container.' );
		foreach ( $data as $key => $value ) {
			$this->assertNoEmptyContainers( $value, $path . '/' . $key );
		}
	}
}
