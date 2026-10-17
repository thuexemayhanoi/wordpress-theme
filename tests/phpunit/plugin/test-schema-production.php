<?php
/**
 * The production situation: Yoast SEO owns titles, canonicals, robots and the structured data graph, the
 * Nguyen Ha Editorial theme renders the page, and this plugin must only ADD what Yoast does not print.
 *
 * SIMULATION: Yoast SEO is NOT installed and was never executed. It is simulated through the documented
 * nh_seo_detected_seo_plugin filter, because defining the real WPSEO_VERSION constant would leak into every
 * other test of the PHP process (constants cannot be undefined). The line in nh_seo_detect_seo_plugin() that
 * looks for the constant WPSEO_VERSION is therefore not executed by any test. What these tests do use for real:
 * the stored schema_mode stays at its default "auto", the real theme templates and header (wp_head), the
 * theme's own FAQ markup and the plugin's real hooks.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

// Shared fixtures and helpers.
require_once __DIR__ . '/class-nh-schema-test-case.php';

/**
 * Yoast plus theme plus plugin.
 */
class NH_Test_Schema_Production extends NH_Schema_Test_Case {

	/**
	 * Node types this plugin may print next to Yoast's graph: the FAQPage and the owner's business type.
	 */
	const ALLOWED_TYPES = array( 'FAQPage', 'AutoRental' );

	/**
	 * Keys that would claim ratings, reviews or offers.
	 */
	const FORBIDDEN_KEYS = array( 'aggregateRating', 'review', 'reviews', 'offers', 'offer', 'makesOffer', 'ratingValue', 'ratingCount', 'reviewCount', 'bestRating', 'price', 'priceCurrency', 'priceRange', 'interactionStatistic' );

	/** @var int */
	private $post_id;
	/** @var int */
	private $page_id;
	/** @var int */
	private $author;
	/** @var int */
	private $cat_id;

	public function set_up() {
		// The declaration the theme made at after_setup_theme; the shared base class removes it.
		$declared = get_theme_support( 'nguyen-ha-companion' );
		parent::set_up();
		if ( true === $declared ) {
			add_theme_support( 'nguyen-ha-companion' );
		} elseif ( is_array( $declared ) && $declared ) {
			call_user_func_array( 'add_theme_support', array_merge( array( 'nguyen-ha-companion' ), $declared ) );
		}
		if ( function_exists( 'nh_editorial_cache_bump_now' ) ) {
			nh_editorial_cache_bump_now();
		}

		// Production settings: nothing saved, so schema_mode is "auto" and Yoast decides.
		$this->simulate_seo_plugin( 'yoast' );
		$this->set_profile( $this->complete_profile() );

		$this->author  = self::factory()->user->create( array( 'role' => 'author' ) );
		$this->cat_id  = self::factory()->category->create( array( 'name' => 'Guides' ) );
		$this->post_id = self::factory()->post->create(
			array(
				'post_title'    => 'Scooter rental basics',
				'post_content'  => 'A plain article about renting a scooter.',
				'post_excerpt'  => '',
				'post_author'   => $this->author,
				'post_category' => array( $this->cat_id ),
				'tags_input'    => array( 'Hanoi' ),
			)
		);
		$this->page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'Contact and rental desk',
				'post_content' => 'Opening hours and how to reach us.',
				'post_excerpt' => '',
			)
		);
		foreach ( array( $this->post_id, $this->page_id ) as $id ) {
			$this->set_faq( $id, 3 );
			update_post_meta( $id, NH_SEO_Keys::META_LOCAL_BUSINESS, 1 );
		}
	}

	/**
	 * Every kind of public page, by label.
	 *
	 * @return array<string, string>
	 */
	private function urls() {
		return array(
			'post'     => get_permalink( $this->post_id ),
			'page'     => get_permalink( $this->page_id ),
			'home'     => home_url( '/' ),
			'category' => get_category_link( $this->cat_id ),
			'tag'      => get_term_link( get_term_by( 'slug', 'hanoi', 'post_tag' ) ),
			'author'   => get_author_posts_url( $this->author ),
			'year'     => get_year_link( (int) get_the_date( 'Y', $this->post_id ) ),
			'search'   => home_url( '/?s=scooter' ),
			'missing'  => home_url( '/definitely-missing/' ),
		);
	}

	/**
	 * What this plugin should print per page next to Yoast, with every extra enabled.
	 *
	 * @return array<string, string[]>
	 */
	private function expected_types() {
		return array(
			'post'     => array( 'FAQPage', 'AutoRental' ),
			'page'     => array( 'FAQPage', 'AutoRental' ),
			'home'     => array( 'AutoRental' ),
			'category' => array(),
			'tag'      => array(),
			'author'   => array(),
			'year'     => array(),
			'search'   => array(),
			'missing'  => array(),
		);
	}

	/**
	 * Owner of a callback: the file that defines it, or '' when it cannot be told.
	 *
	 * @param mixed $callback_function A WordPress callback.
	 * @return string
	 */
	private function defined_in( $callback_function ) {
		try {
			if ( is_array( $callback_function ) ) {
				return (string) ( new ReflectionMethod( $callback_function[0], $callback_function[1] ) )->getFileName();
			}
			if ( is_object( $callback_function ) && ! $callback_function instanceof Closure ) {
				return (string) ( new ReflectionMethod( $callback_function, '__invoke' ) )->getFileName();
			}
			if ( $callback_function instanceof Closure || is_string( $callback_function ) ) {
				return (string) ( new ReflectionFunction( $callback_function ) )->getFileName();
			}
		} catch ( ReflectionException $e ) {
			return '';
		}
		return '';
	}

	/**
	 * Callbacks that this plugin's files define on a hook, as "priority class::method" or "priority closure".
	 *
	 * @param string $hook Hook name.
	 * @return string[]
	 */
	private function plugin_callbacks( $hook ) {
		global $wp_filter;
		$found = array();
		if ( empty( $wp_filter[ $hook ] ) ) {
			return $found;
		}
		foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$file = $this->defined_in( $callback['function'] );
				if ( '' !== $file && 0 === strpos( $file, NH_SEO_DIR ) ) {
					$function = $callback['function'];
					$found[]  = $priority . ' ' . ( is_array( $function ) ? ( is_object( $function[0] ) ? get_class( $function[0] ) : $function[0] ) . '::' . $function[1] : ( $function instanceof Closure ? 'closure in ' . basename( $file ) : (string) $function ) );
				}
			}
		}
		return $found;
	}

	/**
	 * Normalise whitespace.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private function squash( $text ) {
		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	public function test_the_effective_mode_is_extras_with_the_default_settings_and_a_detected_yoast() {
		$this->assertFalse( get_option( NH_SEO_Keys::OPTION_SETTINGS ), 'Nothing is saved: the production default.' );
		$this->assertSame( 'auto', nh_seo_get_settings()['schema_mode'] );
		$this->assertSame( 'extras', nh_seo_schema_mode() );

		// Saving the settings page unchanged keeps it that way.
		update_option( NH_SEO_Keys::OPTION_SETTINGS, nh_seo_settings_defaults() );
		$this->assertSame( 'extras', nh_seo_schema_mode() );

		// Without Yoast the same settings give the full graph.
		remove_all_filters( 'nh_seo_detected_seo_plugin' );
		$this->assertSame( 'full', nh_seo_schema_mode() );
	}

	public function test_no_page_gets_a_second_graph_however_much_is_enabled() {
		$this->set_settings( array( 'localbusiness_on_home' => 1 ) );

		$graph_types = array( 'Organization', 'WebSite', 'WebPage', 'CollectionPage', 'ProfilePage', 'SearchResultsPage', 'ItemPage', 'BreadcrumbList', 'Article', 'BlogPosting', 'NewsArticle', 'Person', 'ImageObject', 'SiteNavigationElement' );
		foreach ( $this->expected_types() as $label => $expected ) {
			$url   = $this->urls()[ $label ];
			$nodes = $this->assert_well_formed( $this->blocks_at( $url ) );
			$types = $this->types_of( $nodes );

			$this->assertSame( array(), array_values( array_intersect( $graph_types, $types ) ), $label . ': Yoast prints the graph, this plugin never does next to it.' );
			$this->assertSame( array(), array_values( array_diff( $types, self::ALLOWED_TYPES ) ), $label . ': only the FAQPage and the business may be added.' );
			$this->assertSame( $expected, $types, $label );
		}
	}

	public function test_nothing_is_ever_a_rating_a_review_or_an_offer() {
		$this->set_settings( array( 'localbusiness_on_home' => 1 ) );
		foreach ( $this->urls() as $label => $url ) {
			$keys = array();
			foreach ( $this->blocks_at( $url ) as $json ) {
				$keys = array_merge( $keys, $this->all_keys( $this->decode( $json ) ) );
			}
			$this->assertSame( array(), array_values( array_intersect( self::FORBIDDEN_KEYS, $keys ) ), $label );
		}
	}

	public function test_this_plugin_hooks_nothing_that_could_print_a_second_canonical_robots_or_meta() {
		$this->assertSame( array( '20 NH_SEO_Schema::print_head' ), $this->plugin_callbacks( 'wp_head' ), 'The one head callback prints the JSON-LD blocks and nothing else.' );

		// Canonical, robots and title belong to the SEO plugin and to core; so does every hook of the SEO plugins.
		$hooks = array( 'wp_robots', 'get_canonical_url', 'pre_get_document_title', 'document_title_parts', 'document_title', 'wp_title' );
		global $wp_filter;
		foreach ( array_keys( $wp_filter ) as $name ) {
			if ( 1 === preg_match( '/^(wpseo|rank_math|aioseo|seopress|the_seo_framework|slim_seo|sq_|smartcrawl|yoast)/i', (string) $name ) ) {
				$hooks[] = (string) $name;
			}
		}
		foreach ( array_unique( $hooks ) as $hook ) {
			$this->assertSame( array(), $this->plugin_callbacks( $hook ), $hook );
		}
	}

	public function test_the_real_head_of_every_page_holds_only_what_core_and_the_theme_print_plus_the_json_ld_blocks() {
		$this->set_settings( array( 'localbusiness_on_home' => 1 ) );

		foreach ( $this->expected_types() as $label => $expected ) {
			$html = $this->render_url( $this->urls()[ $label ] );
			$xp   = $this->xpath( $html );

			$this->assertSame( count( $expected ), $xp->query( '//script[@type="application/ld+json"]' )->length, $label . ': the JSON-LD blocks of this plugin, none from the theme.' );
			$this->assertSame( count( NH_SEO_Schema::blocks() ), $xp->query( '//script[@type="application/ld+json"]' )->length, $label );
			$this->assertLessThanOrEqual( 1, $xp->query( '//link[@rel="canonical"]' )->length, $label . ': at most the one canonical WordPress core prints.' );
			$this->assertLessThanOrEqual( 1, $xp->query( '//meta[@name="robots"]' )->length, $label . ': at most one robots meta.' );
			$this->assertSame( 0, $xp->query( '//meta[@name="description"]' )->length, $label . ': no meta description from the theme or this plugin.' );
			$this->assertSame( 0, $xp->query( '//meta[starts-with(@property, "og:")]|//meta[starts-with(@name, "twitter:")]' )->length, $label . ': no Open Graph or Twitter cards.' );
			$this->assertSame( 0, $xp->query( '//link[@hreflang]' )->length, $label . ': no hreflang.' );
		}
	}

	public function test_the_faq_that_is_marked_up_is_the_faq_the_real_theme_shows() {
		$this->assertTrue( nh_seo_theme_supports( 'faq' ), 'The active theme declares that it renders the FAQ.' );

		foreach ( array(
			'post' => $this->post_id,
			'page' => $this->page_id,
		) as $label => $id ) {
			$html = $this->render_url( get_permalink( $id ) );
			$xp   = $this->xpath( $html );

			$this->assertSame( 1, $xp->query( '//section[@id="faq"]' )->length, $label . ': the theme shows one FAQ section.' );
			$visible_questions = array();
			foreach ( $xp->query( '//section[@id="faq"]//*[contains(@class, "faq__question")]' ) as $node ) {
				$visible_questions[] = $this->squash( $node->textContent );
			}
			$visible_answers = array();
			foreach ( $xp->query( '//section[@id="faq"]//*[contains(@class, "faq__answer")]' ) as $node ) {
				$visible_answers[] = $this->squash( $node->textContent );
			}

			$faq_nodes = array();
			foreach ( $xp->query( '//script[@type="application/ld+json"]' ) as $script ) {
				$data = json_decode( $script->textContent, true );
				if ( is_array( $data ) && isset( $data['@type'] ) && 'FAQPage' === $data['@type'] ) {
					$faq_nodes[] = $data;
				}
			}
			$this->assertCount( 1, $faq_nodes, $label . ': exactly one FAQPage.' );

			$marked_questions = array();
			$marked_answers   = array();
			foreach ( $faq_nodes[0]['mainEntity'] as $question ) {
				$marked_questions[] = $this->squash( $question['name'] );
				$marked_answers[]   = $this->squash( wp_strip_all_tags( $question['acceptedAnswer']['text'] ) );
			}

			$this->assertCount( 3, $marked_questions );
			$this->assertSame( $visible_questions, $marked_questions, $label . ': every marked-up question is visible, in the same order.' );
			$this->assertSame( $visible_answers, $marked_answers, $label . ': every marked-up answer is the visible answer.' );
		}
	}

	public function test_the_theme_and_the_plugin_keep_the_same_faq_items_even_for_awkward_input() {
		if ( ! function_exists( 'nh_editorial_clean_faq' ) ) {
			$this->markTestSkipped( 'The active theme has no FAQ sanitizer to compare with.' );
		}

		$many = array();
		for ( $i = 1; $i <= 25; $i++ ) {
			$many[] = array(
				'question' => 'Question ' . $i . '?',
				'answer'   => '<p>Answer ' . $i . '</p>',
			);
		}
		$pair  = static function ( $question, $answer ) {
			return array(
				'question' => $question,
				'answer'   => $answer,
			);
		};
		$cases = array(
			'plain'                   => array( $pair( 'Is it simple?', '<p>Yes.</p>' ), $pair( 'Is it cheap?', 'Cheap enough.' ) ),
			'empty question'          => array( $pair( '', '<p>Orphan answer</p>' ), $pair( 'Kept?', 'Yes' ) ),
			'empty answer'            => array( $pair( 'No answer?', '' ), $pair( 'Kept?', 'Yes' ) ),
			'whitespace only'         => array( $pair( '   ', " \n " ), $pair( 'Kept?', 'Yes' ) ),
			'html in the question'    => array( $pair( 'Is <b>this</b> <script>alert(1)</script>safe?', '<p>Yes</p>' ) ),
			'entities'                => array( $pair( 'Tom &amp; Jerry?', '<p>Cat &amp; mouse &lt;3</p>' ) ),
			'disallowed answer html'  => array( $pair( 'Q?', '<p onclick="x()">A <script>1</script><iframe src="x"></iframe><a href="javascript:alert(1)">bad</a> <a href="https://example.org/x">good</a></p>' ) ),
			'long question'           => array( $pair( str_repeat( 'Why ', 120 ) . '?', '<p>Short</p>' ) ),
			'long answer'             => array( $pair( 'Q?', '<p>' . str_repeat( 'Long answer. ', 400 ) . '</p>' ) ),
			'more items than allowed' => $many,
			'not a list'              => 'just a string',
			'missing keys'            => array( array( 'question' => 'Only a question' ), array( 'answer' => 'Only an answer' ) ),
		);

		// What a reader sees: the plain text of each question and answer, entities decoded.
		$text = function ( $items ) {
			$out = array();
			foreach ( (array) $items as $item ) {
				$out[] = array(
					$this->squash( wp_strip_all_tags( html_entity_decode( (string) $item['question'] ) ) ),
					$this->squash( wp_strip_all_tags( html_entity_decode( (string) $item['answer'] ) ) ),
				);
			}
			return $out;
		};
		foreach ( $cases as $label => $raw ) {
			$this->assertSame( $text( nh_editorial_clean_faq( $raw ) ), $text( nh_seo_sanitize_faq( $raw ) ), $label . ': the FAQPage must describe exactly the items the theme shows.' );
		}
	}

	public function test_a_theme_that_does_not_declare_the_faq_and_a_switched_off_fallback_get_no_faq_markup() {
		remove_theme_support( 'nguyen-ha-companion' );
		$this->set_settings( array( 'fallback_render' => 0 ) );

		$types = $this->types_of( $this->nodes_of( $this->blocks_at( get_permalink( $this->post_id ) ) ) );
		$this->assertNotContains( 'FAQPage', $types, 'The FAQ would be invisible, so it is not described.' );
		$this->assertContains( 'AutoRental', $types, 'The business is a separate question.' );
	}

	public function test_a_local_business_needs_a_complete_profile_entered_by_the_owner() {
		$post_url = get_permalink( $this->post_id );
		$home_url = home_url( '/' );
		$this->set_settings( array( 'localbusiness_on_home' => 1 ) );

		// No profile at all (the state of a fresh install): nothing, although the page is flagged and the home page allowed.
		delete_option( NH_SEO_Keys::OPTION_BUSINESS );
		$this->assertNotContains( 'AutoRental', $this->types_of( $this->nodes_of( $this->blocks_at( $post_url ) ) ) );
		$this->assertSame( array(), $this->blocks_at( $home_url ) );

		// Each of the four required fields is needed (the site name is never borrowed for a missing name).
		foreach ( array( 'name', 'phone', 'street', 'locality' ) as $missing ) {
			$this->set_profile( $this->complete_profile( array( $missing => '' ) ) );
			$this->assertNotContains( 'AutoRental', $this->types_of( $this->nodes_of( $this->blocks_at( $post_url ) ) ), 'without ' . $missing );
			$this->assertSame( array(), $this->blocks_at( $home_url ), 'without ' . $missing );
		}

		// A complete profile on a page the owner flagged.
		$this->set_profile( $this->complete_profile() );
		$this->assertContains( 'AutoRental', $this->types_of( $this->nodes_of( $this->blocks_at( $post_url ) ) ) );

		// The flag matters: the same profile on an unflagged page prints no business.
		delete_post_meta( $this->post_id, NH_SEO_Keys::META_LOCAL_BUSINESS );
		$this->assertNotContains( 'AutoRental', $this->types_of( $this->nodes_of( $this->blocks_at( $post_url ) ) ) );
	}

	public function test_the_business_is_not_printed_when_a_local_seo_add_on_prints_its_own() {
		$post_url = get_permalink( $this->post_id );
		$this->assertContains( 'AutoRental', $this->types_of( $this->nodes_of( $this->blocks_at( $post_url ) ) ) );

		add_filter( 'nh_seo_has_external_localbusiness', '__return_true' );
		$this->assertNotContains( 'AutoRental', $this->types_of( $this->nodes_of( $this->blocks_at( $post_url ) ) ) );
		remove_filter( 'nh_seo_has_external_localbusiness', '__return_true' );
	}

	public function test_a_post_that_already_holds_a_yoast_faq_block_gets_no_second_faqpage_but_keeps_everything_else() {
		wp_update_post(
			array(
				'ID'           => $this->post_id,
				'post_content' => "<!-- wp:yoast/faq-block {\"questions\":[]} -->\n<div class=\"schema-faq wp-block-yoast-faq-block\"></div>\n<!-- /wp:yoast/faq-block -->",
			)
		);
		$types = $this->types_of( $this->nodes_of( $this->blocks_at( get_permalink( $this->post_id ) ) ) );
		$this->assertSame( array( 'AutoRental' ), $types );
	}
}
