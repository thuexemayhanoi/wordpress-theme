<?php
/**
 * Base test case with helpers shared by theme and plugin integration tests.
 *
 * @package nguyen-ha-editorial
 */

/**
 * WP_UnitTestCase plus helpers for pretty permalinks, template rendering and HTML inspection.
 */
abstract class NH_Unit_Test_Case extends WP_UnitTestCase {

	/**
	 * The theme's companion-plugin declaration before the test (see tear_down()).
	 *
	 * @var array|bool
	 */
	private $companion_support = false;

	/**
	 * WP_UnitTestCase unregisters every registered meta key after each test, so the plugin's taxonomy and
	 * meta (normally registered once on `init`) would only exist for the first test of a run. Register them
	 * again before every test to keep tests independent of their execution order.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->companion_support = get_theme_support( 'nguyen-ha-companion' );
		if ( class_exists( 'NH_SEO_Data_Model' ) ) {
			NH_SEO_Data_Model::register();
		}
	}

	/**
	 * Tests change the theme's `nguyen-ha-companion` declaration to check the handshake. WordPress does not
	 * reset theme features between tests, so put the declaration back to keep tests independent.
	 *
	 * @return void
	 */
	public function tear_down() {
		remove_theme_support( 'nguyen-ha-companion' );
		if ( true === $this->companion_support ) {
			add_theme_support( 'nguyen-ha-companion' );
		} elseif ( is_array( $this->companion_support ) && $this->companion_support ) {
			call_user_func_array( 'add_theme_support', array_merge( array( 'nguyen-ha-companion' ), $this->companion_support ) );
		}
		parent::tear_down();
	}

	/**
	 * Enable pretty permalinks properly: the test suite registers taxonomies before the permalink
	 * structure exists, so rewrite tags such as %category% must be registered again.
	 *
	 * @param string $structure Permalink structure.
	 * @return void
	 */
	protected function use_permalinks( $structure = '/%category%/%postname%/' ) {
		global $wp_rewrite;
		$this->set_permalink_structure( $structure );
		create_initial_taxonomies();
		$wp_rewrite->flush_rules();
	}

	/**
	 * Make sure the topic_cluster taxonomy exists (the companion plugin registers it when active).
	 *
	 * @return void
	 */
	protected function ensure_cluster_taxonomy() {
		if ( ! taxonomy_exists( 'topic_cluster' ) ) {
			register_taxonomy(
				'topic_cluster',
				'post',
				array(
					'public'  => false,
					'rewrite' => false,
				)
			);
		}
	}

	/**
	 * Template file WordPress would load for the current query (same order as template-loader.php).
	 *
	 * @return string Absolute path.
	 */
	protected function current_template() {
		$map = array(
			'is_404'               => 'get_404_template',
			'is_search'            => 'get_search_template',
			'is_front_page'        => 'get_front_page_template',
			'is_home'              => 'get_home_template',
			'is_privacy_policy'    => 'get_privacy_policy_template',
			'is_post_type_archive' => 'get_post_type_archive_template',
			'is_tax'               => 'get_taxonomy_template',
			'is_attachment'        => 'get_attachment_template',
			'is_single'            => 'get_single_template',
			'is_page'              => 'get_page_template',
			'is_singular'          => 'get_singular_template',
			'is_category'          => 'get_category_template',
			'is_tag'               => 'get_tag_template',
			'is_author'            => 'get_author_template',
			'is_date'              => 'get_date_template',
			'is_archive'           => 'get_archive_template',
		);
		foreach ( $map as $conditional => $getter ) {
			if ( $conditional() ) {
				$template = $getter();
				if ( $template ) {
					return $template;
				}
			}
		}
		return get_index_template();
	}

	/**
	 * Load a URL into the main query and return the full page HTML.
	 *
	 * @param string $url URL to visit.
	 * @return string
	 */
	protected function render_url( $url ) {
		$this->go_to( $url );
		return $this->render_current_page();
	}

	/**
	 * Render the template of the current main query.
	 *
	 * @return string
	 * @throws Throwable Whatever the template throws, after its output buffer is closed.
	 */
	protected function render_current_page() {
		// Every page view is a fresh request: no script or style is already "printed".
		unset( $GLOBALS['wp_styles'], $GLOBALS['wp_scripts'] );

		$template  = $this->current_template();
		$callbacks = array();
		foreach ( array( 'header', 'footer', 'sidebar' ) as $part ) {
			$callbacks[ $part ] = function ( $name = null ) use ( $part ) {
				$this->print_part_again( $part, $name );
			};
			add_action( 'get_' . $part, $callbacks[ $part ], 0 );
		}

		ob_start();
		try {
			include $template;
			$html = (string) ob_get_clean();
		} catch ( Throwable $error ) {
			ob_end_clean();
			throw $error;
		} finally {
			foreach ( $callbacks as $part => $callback ) {
				remove_action( 'get_' . $part, $callback, 0 );
			}
		}
		return $html;
	}

	/**
	 * Print a header, footer or sidebar again although this PHP process already loaded it.
	 *
	 * The core functions get_header(), get_footer() and get_sidebar() load their file with require_once,
	 * so only the first page rendered by a PHP process would get a header. A real request renders one
	 * page per process; tests render many. Print the real file here on every call, then let WordPress
	 * require_once an empty stand-in for the one it is about to locate.
	 *
	 * @param string      $part Header, footer or sidebar.
	 * @param string|null $name Template name suffix.
	 * @return void
	 */
	private function print_part_again( $part, $name ) {
		$suffix = ( is_string( $name ) && '' !== $name ) ? '-' . $name : '';
		$real   = get_stylesheet_directory() . '/' . $part . $suffix . '.php';
		if ( ! is_readable( $real ) ) {
			return;
		}
		include $real;

		$stub = sys_get_temp_dir() . '/nh-render-stubs-' . getmypid();
		if ( ! is_dir( $stub ) ) {
			mkdir( $stub, 0777, true );
			register_shutdown_function(
				static function () use ( $stub ) {
					foreach ( (array) glob( $stub . '/*.php' ) as $file ) {
						unlink( $file );
					}
					rmdir( $stub );
				}
			);
		}
		if ( ! file_exists( $stub . '/' . $part . $suffix . '.php' ) ) {
			file_put_contents( $stub . '/' . $part . $suffix . '.php', "<?php\n" );
		}

		// locate_template() runs next and finds the stand-in; the real directories are back as soon as
		// load_template() is about to include it.
		$divert  = static function () use ( $stub ) {
			return $stub;
		};
		$restore = null;
		$restore = static function () use ( $divert, &$restore ) {
			remove_filter( 'stylesheet_directory', $divert, 1 );
			remove_filter( 'template_directory', $divert, 1 );
			remove_action( 'wp_before_load_template', $restore, 0 );
		};
		add_filter( 'stylesheet_directory', $divert, 1 );
		add_filter( 'template_directory', $divert, 1 );
		add_action( 'wp_before_load_template', $restore, 0 );
	}

	/**
	 * Parse HTML for XPath assertions.
	 *
	 * @param string $html HTML document or fragment.
	 * @return DOMXPath
	 */
	protected function xpath( $html ) {
		$previous = libxml_use_internal_errors( true );
		$doc      = new DOMDocument();
		$doc->loadHTML( '<?xml encoding="utf-8"?>' . $html );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		return new DOMXPath( $doc );
	}

	/**
	 * Count nodes matching an XPath expression.
	 *
	 * @param string $html  HTML.
	 * @param string $query XPath.
	 * @return int
	 */
	protected function count_nodes( $html, $query ) {
		return $this->xpath( $html )->query( $query )->length;
	}

	/**
	 * Create published posts in a category, oldest first, one hour apart.
	 *
	 * @param int $category_id Category ID.
	 * @param int $count       Number of posts.
	 * @param int $start       Unix time of the first post.
	 * @return int[]
	 */
	protected function make_posts( $category_id, $count, $start = 0 ) {
		$start = $start ? $start : time() - ( $count * HOUR_IN_SECONDS * 2 );
		$ids   = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$ids[] = self::factory()->post->create(
				array(
					'post_title'    => 'Post ' . $category_id . '-' . $i,
					'post_status'   => 'publish',
					'post_date'     => gmdate( 'Y-m-d H:i:s', $start + ( $i * HOUR_IN_SECONDS ) ),
					'post_category' => array( $category_id ),
				)
			);
		}
		return $ids;
	}
}

/**
 * Fixture site and DOM assertions for tests that render whole pages.
 */
abstract class NH_Render_Test_Case extends NH_Unit_Test_Case {

	/**
	 * IDs created by build_site().
	 *
	 * @var array<string, mixed>
	 */
	protected $site = array();

	/**
	 * Pretty permalinks, and no structured data from the companion plugin: these tests describe what
	 * the theme itself prints.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->use_permalinks();
		add_filter( 'nh_seo_schema_mode', array( $this, 'schema_off' ) );
		nh_editorial_cache_bump_now();
	}

	/**
	 * Filter callback: structured data off.
	 *
	 * @return string
	 */
	public function schema_off() {
		return 'off';
	}

	/**
	 * Create a small but complete site: a hub with a child hub and a Pillar page, one fully equipped
	 * article, plain articles, an author, a tag, pages with each template and every kind of hidden
	 * content (draft, private, scheduled, pending, trashed).
	 *
	 * @param int $article_count Number of plain articles in the child category.
	 * @return array<string, mixed>
	 */
	protected function build_site( $article_count = 14 ) {
		$author = self::factory()->user->create(
			array(
				'role'         => 'author',
				'display_name' => 'Linh Tran',
				'description'  => 'Writes practical guides.',
			)
		);
		update_user_meta( $author, 'nh_job_title', 'Staff writer' );

		$top   = self::factory()->category->create( array( 'name' => 'Guides' ) );
		$child = self::factory()->category->create(
			array(
				'name'   => 'Hanoi tips',
				'parent' => $top,
			)
		);
		$empty = self::factory()->category->create( array( 'name' => 'Nothing here' ) );
		$tag   = self::factory()->term->create(
			array(
				'taxonomy' => 'post_tag',
				'name'     => 'handy',
			)
		);

		$pillar = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'Complete Guide',
				'post_content' => '<p>The complete guide.</p><h2>Start here</h2><p>Body.</p>',
				'meta_input'   => array( '_wp_page_template' => 'page-templates/template-pillar.php' ),
			)
		);
		update_term_meta( $top, 'nh_pillar_id', $pillar );

		$wide = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Wide page',
				'meta_input' => array( '_wp_page_template' => 'page-templates/template-fullwidth.php' ),
			)
		);
		$page = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'About us',
				'post_content' => '<p>About.</p><h2>Who we are</h2><p>Text.</p>',
			)
		);

		$posts = array();
		for ( $i = 0; $i < $article_count; $i++ ) {
			$posts[] = self::factory()->post->create(
				array(
					'post_title'    => 'Guide number ' . $i,
					'post_excerpt'  => 'Short excerpt ' . $i . '.',
					'post_author'   => $author,
					'post_category' => array( $child ),
					'post_date'     => gmdate( 'Y-m-d H:i:s', strtotime( '-' . ( 40 + $article_count - $i ) . ' hours' ) ),
				)
			);
		}

		$rich = self::factory()->post->create(
			array(
				'post_title'    => 'The rich guide',
				'post_author'   => $author,
				'post_category' => array( $child ),
				'tags_input'    => array( 'handy' ),
				'post_date'     => gmdate( 'Y-m-d H:i:s', strtotime( '-10 days' ) ),
				'post_content'  => '<p>Intro paragraph.</p><h2>First section</h2><p>Text.</p><h3>Detail A</h3><p>Text.</p>'
					. '<h2>Second section</h2><table><thead><tr><th>Plan</th><th>Note</th></tr></thead><tbody><tr><td>A</td><td>B</td></tr></tbody></table>'
					. '<h2>Third section</h2><p>More text.</p>',
				'meta_input'    => array(
					'nh_summary'         => 'The short answer in one sentence.',
					'nh_takeaways'       => array( 'First point', 'Second point' ),
					'nh_faq'             => array(
						array(
							'question' => 'Is it simple?',
							'answer'   => '<p>Yes, <strong>very</strong>.</p>',
						),
						array(
							'question' => 'Is it cheap?',
							'answer'   => 'It depends.',
						),
					),
					'nh_sources'         => array(
						array(
							'title' => 'Example source',
							'url'   => 'https://example.org/source',
						),
					),
					'nh_content_updated' => gmdate( 'Y-m-d', strtotime( '-2 days' ) ),
				),
			)
		);
		self::factory()->comment->create_post_comments( $rich, 2 );

		$plain = self::factory()->post->create(
			array(
				'post_title'    => 'A plain note',
				'post_content'  => '<p>Only a paragraph.</p>',
				'post_category' => array( $child ),
				'post_date'     => gmdate( 'Y-m-d H:i:s', strtotime( '-20 days' ) ),
			)
		);

		$protected = self::factory()->post->create(
			array(
				'post_title'    => 'Protected guide',
				'post_content'  => 'Secret body text.',
				'post_password' => 'secret',
				'post_category' => array( $child ),
				'post_date'     => gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) ),
			)
		);

		$hidden = array();
		foreach ( array( 'draft', 'private', 'pending' ) as $status ) {
			$hidden[ $status ] = self::factory()->post->create(
				array(
					'post_title'    => 'Hidden ' . $status . ' guide',
					'post_status'   => $status,
					'post_category' => array( $child ),
					'meta_input'    => array( 'nh_summary' => 'Hidden summary ' . $status ),
				)
			);
		}
		$hidden['future'] = self::factory()->post->create(
			array(
				'post_title'    => 'Hidden future guide',
				'post_status'   => 'future',
				'post_date'     => gmdate( 'Y-m-d H:i:s', strtotime( '+3 days' ) ),
				'post_category' => array( $child ),
			)
		);
		$hidden['trash']  = self::factory()->post->create(
			array(
				'post_title'    => 'Hidden trash guide',
				'post_category' => array( $child ),
			)
		);
		wp_trash_post( $hidden['trash'] );

		nh_editorial_cache_bump_now();

		$this->site = compact( 'author', 'top', 'child', 'empty', 'tag', 'pillar', 'wide', 'page', 'posts', 'rich', 'plain', 'protected', 'hidden' );
		return $this->site;
	}

	/**
	 * Give posts a featured image (a large photo from the WordPress test data).
	 *
	 * @param int[] $post_ids Posts.
	 * @return int Attachment ID.
	 */
	protected function attach_featured_image( array $post_ids ) {
		$attachment = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/2004-07-22-DSC_0007.jpg' );
		update_post_meta( $attachment, '_wp_attachment_image_alt', 'A test photograph' );
		foreach ( $post_ids as $post_id ) {
			set_post_thumbnail( $post_id, $attachment );
		}
		return $attachment;
	}

	/**
	 * Headings of a document in reading order.
	 *
	 * @param string $html HTML.
	 * @return array<int, array{level: int, text: string}>
	 */
	protected function headings( $html ) {
		$list = array();
		foreach ( $this->xpath( $html )->query( '//h1|//h2|//h3|//h4|//h5|//h6' ) as $node ) {
			$list[] = array(
				'level' => (int) substr( $node->nodeName, 1 ),
				'text'  => trim( (string) preg_replace( '/\s+/u', ' ', $node->textContent ) ),
			);
		}
		return $list;
	}

	/**
	 * Heading jumps deeper than one level (h2 followed by h4, ...).
	 *
	 * @param string $html HTML.
	 * @return string[] Human readable violations.
	 */
	protected function heading_skips( $html ) {
		$skips = array();
		$last  = 0;
		foreach ( $this->headings( $html ) as $heading ) {
			if ( $heading['level'] > $last + 1 ) {
				$skips[] = sprintf( 'h%d after h%d: "%s"', $heading['level'], $last, $heading['text'] );
			}
			$last = $heading['level'];
		}
		return $skips;
	}

	/**
	 * XPath predicate for an element carrying a CSS class.
	 *
	 * @param string $class_name Class name.
	 * @param string $tag        Tag name or *.
	 * @return string
	 */
	protected function by_class( $class_name, $tag = '*' ) {
		return '//' . $tag . '[contains(concat(" ", normalize-space(@class), " "), " ' . $class_name . ' ")]';
	}

	/**
	 * Duplicate id attributes in a document.
	 *
	 * @param string $html HTML.
	 * @return string[]
	 */
	protected function duplicate_ids( $html ) {
		$ids = array();
		foreach ( $this->xpath( $html )->query( '//*[@id]' ) as $node ) {
			$ids[] = $node->getAttribute( 'id' );
		}
		return array_keys(
			array_filter(
				array_count_values( $ids ),
				static function ( $count ) {
					return $count > 1;
				}
			)
		);
	}

	/**
	 * Every href in a document.
	 *
	 * @param string $html HTML.
	 * @return string[]
	 */
	protected function hrefs( $html ) {
		$hrefs = array();
		foreach ( $this->xpath( $html )->query( '//a[@href]' ) as $node ) {
			$hrefs[] = $node->getAttribute( 'href' );
		}
		return $hrefs;
	}

	/**
	 * Inline event handler attributes as "tag:attribute".
	 *
	 * @param string $html HTML.
	 * @return string[]
	 */
	protected function inline_handlers( $html ) {
		$found = array();
		foreach ( $this->xpath( $html )->query( '//*[@*[starts-with(name(), "on")]]' ) as $node ) {
			foreach ( $node->attributes as $attribute ) {
				if ( 0 === strpos( $attribute->name, 'on' ) ) {
					$found[] = $node->nodeName . ':' . $attribute->name;
				}
			}
		}
		return $found;
	}
}
