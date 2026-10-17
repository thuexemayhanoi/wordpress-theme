<?php
/**
 * The shape of the live site the theme is built for: a three-level category tree whose top levels hold no
 * articles of their own, Vietnamese names, a default "Uncategorized" category with one article, pretty
 * permalinks of the form /%postname%/, many pages, and articles published through the REST API.
 *
 * The tree, the slugs and the page list come from the public inventory of the live blog (snapshot of
 * 2026-10-17: 22 categories, 18 pages). Only names and structure are copied; every article here is
 * generated test data.
 *
 * @package nguyen-ha-editorial
 */

/**
 * Behavior on the real category structure.
 */
class NH_Test_Production_Shape extends NH_Render_Test_Case {

	/**
	 * Term IDs by slug.
	 *
	 * @var array<string, int>
	 */
	private $cats = array();

	/**
	 * Same structure as the live site, with permalinks of the live site.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->use_permalinks( '/%postname%/' );
		$this->build_shape();
	}

	/**
	 * Create the categories (with the live names, slugs and nesting) and a few articles per leaf.
	 *
	 * @return void
	 */
	private function build_shape() {
		$tree = array(
			array(
				'Dịch vụ',
				'dich-vu',
				array(
					array(
						'Xe máy',
						'xe-may',
						array(
							array( 'Xe 50cc', 'xe-50cc', array(), 3 ),
							array( 'Xe số', 'xe-so', array(), 5 ),
							array( 'Xe ga', 'xe-ga', array(), 6 ),
						),
						1,
					),
					array( 'Xe điện', 'xe-dien', array(), 4 ),
				),
				0,
			),
			array(
				'Khu vực',
				'khu-vuc',
				array(
					array(
						'Hoàn Kiếm',
						'hoan-kiem',
						array(
							array( 'Phố Cổ', 'pho-co', array(), 2 ),
						),
						2,
					),
					array( 'Long Biên', 'long-bien', array(), 2 ),
					array( 'Ba Đình', 'ba-dinh', array(), 2 ),
					array( 'Gia Lâm', 'gia-lam', array(), 2 ),
				),
				0,
			),
			array(
				'Cẩm nang',
				'cam-nang',
				array(
					array(
						'Ứng dụng',
						'ung-dung',
						array(
							array( 'App xe máy', 'app-xe-may', array(), 3 ),
							array( 'App xe điện', 'app-xe-dien', array(), 2 ),
						),
						0,
					),
					array(
						'Hãng xe',
						'hang-xe',
						array(
							array( 'Hãng xe máy', 'hang-xe-may', array(), 7 ),
							array( 'Hãng xe điện', 'hang-xe-dien', array(), 3 ),
						),
						1,
					),
					array( 'Kinh nghiệm', 'kinh-nghiem', array(), 12 ),
					array( 'Giao thông', 'giao-thong', array(), 4 ),
				),
				2,
			),
		);

		$number = 0;
		$make   = function ( array $nodes, $parent ) use ( &$make, &$number ) {
			foreach ( $nodes as $node ) {
				$term_id                = self::factory()->category->create(
					array(
						'name'   => $node[0],
						'slug'   => $node[1],
						'parent' => $parent,
					)
				);
				$this->cats[ $node[1] ] = $term_id;
				$make( $node[2], $term_id );
				for ( $i = 0; $i < $node[3]; $i++ ) {
					++$number;
					self::factory()->post->create(
						array(
							'post_title'    => 'Bài kiểm thử ' . $node[0] . ' số ' . $number . '?',
							'post_name'     => 'excel-' . $number . '-bai-kiem-thu-' . $node[1],
							'post_content'  => '<p>Mở đầu ' . $number . '.</p><h2>Trả lời nhanh</h2><p>Nội dung.</p><h2>Phân tích thực tế</h2><p>Nội dung.</p><h2>Lưu ý cuối</h2><p>Nội dung.</p>',
							'post_category' => array( $term_id ),
							'post_date'     => gmdate( 'Y-m-d H:i:s', strtotime( '-' . $number . ' hours' ) ),
						)
					);
				}
			}
		};
		$make( $tree, 0 );

		// The default category keeps its one article, as on the live site.
		$this->cats['uncategorized'] = (int) get_option( 'default_category' );
		self::factory()->post->create( array( 'post_title' => 'Bài chưa phân loại' ) );

		foreach ( array(
			'dieu-khoan' => 'Điều khoản',
			'gia-lam'    => 'Thuê xe máy Gia Lâm',
			'lien-he'    => 'Liên hệ',
			'hoi-dap'    => 'Hỏi đáp',
			'cam-nang'   => 'Cẩm nang',
			'bang-gia'   => 'Bảng giá',
			'gioi-thieu' => 'Giới thiệu',
			'khu-vuc'    => 'Khu vực',
			'dich-vu'    => 'Dịch vụ',
			'xe-dien'    => 'Thuê xe điện Hà Nội',
		) as $slug => $title ) {
			self::factory()->post->create(
				array(
					'post_type'  => 'page',
					'post_title' => $title,
					'post_name'  => $slug,
				)
			);
		}
		nh_editorial_cache_bump_now();
		wp_set_current_user( 0 );
	}

	public function test_permalinks_follow_the_live_structure() {
		$post = get_posts(
			array(
				'name'      => 'excel-1-bai-kiem-thu-xe-50cc',
				'post_type' => 'post',
			)
		);
		$this->assertCount( 1, $post );
		$this->assertSame( home_url( '/excel-1-bai-kiem-thu-xe-50cc/' ), get_permalink( $post[0] ) );
		$this->assertSame( home_url( '/category/cam-nang/hang-xe/hang-xe-may/' ), get_category_link( $this->cats['hang-xe-may'] ) );
		$this->assertSame( home_url( '/cam-nang/' ), get_permalink( get_page_by_path( 'cam-nang' ) ), 'the page and the category share a slug without clashing' );

		$html = $this->render_url( home_url( '/category/cam-nang/hang-xe/hang-xe-may/' ) );
		$this->assertSame( 1, $this->count_nodes( $html, '//h1' ) );
		$this->assertSame( 'Hãng xe máy', $this->xpath( $html )->query( '//h1' )->item( 0 )->textContent );
	}

	public function test_top_levels_without_articles_of_their_own_are_hubs_ranked_by_their_real_size() {
		$hubs = nh_editorial_get_hubs( 6 );
		$this->assertSame( array( 'Cẩm nang', 'Dịch vụ', 'Khu vực' ), wp_list_pluck( $hubs, 'name' ), 'Uncategorized is not a hub while real hubs exist; order follows the size of the whole tree' );

		$counts = wp_list_pluck( $hubs, 'count', 'slug' );
		$this->assertSame( 2 + 0 + 3 + 2 + 1 + 7 + 3 + 12 + 4, $counts['cam-nang'], 'articles of children and grandchildren are counted' );
		$this->assertSame( 1 + 3 + 5 + 6 + 4, $counts['dich-vu'] );
		$this->assertSame( 2 + 2 + 2 + 2 + 2, $counts['khu-vuc'] );
	}

	public function test_a_hub_page_lists_child_hubs_with_exact_counts_even_when_the_articles_sit_two_levels_down() {
		$html = $this->render_url( get_category_link( $this->cats['cam-nang'] ) );
		$xp   = $this->xpath( $html );

		$this->assertSame( 1, $xp->query( '//h1' )->length );

		$cards = array();
		foreach ( $xp->query( '//section[contains(@class,"section--subtopics")]//article' ) as $card ) {
			$title           = trim( $xp->query( './/*[contains(@class,"card__title")]', $card )->item( 0 )->textContent );
			$cards[ $title ] = trim( $xp->query( './/p[contains(@class,"meta")]', $card )->item( 0 )->textContent );
		}
		$this->assertEqualsCanonicalizing( array( 'Ứng dụng', 'Hãng xe', 'Kinh nghiệm', 'Giao thông' ), array_keys( $cards ), 'all four child hubs are listed' );
		$this->assertSame( '5 articles', $cards['Ứng dụng'], 'zero direct articles, five in the two leaves below' );
		$this->assertSame( '11 articles', $cards['Hãng xe'] );
		$this->assertSame( '12 articles', $cards['Kinh nghiệm'] );
	}

	public function test_the_primary_menu_built_from_categories_offers_the_three_hubs_with_their_children() {
		remove_theme_mod( 'nav_menu_locations' );
		$html = $this->render_url( home_url( '/' ) );
		$xp   = $this->xpath( $html );

		$tops  = $xp->query( '//nav[@id="primary-navigation"]/ul/li/a' );
		$names = array();
		foreach ( $tops as $link ) {
			$names[] = trim( $link->textContent );
		}
		$this->assertSame( array( 'Cẩm nang', 'Dịch vụ', 'Khu vực' ), $names );
		$this->assertSame( 3, $xp->query( '//nav[@id="primary-navigation"]//button[contains(@class,"submenu-toggle")]' )->length );
		$this->assertSame( 1, $xp->query( '//nav[@id="primary-navigation"]//a[@href="' . get_category_link( $this->cats['hang-xe'] ) . '"]' )->length );
		$this->assertSame( array(), $this->duplicate_ids( $html ) );
	}

	public function test_an_article_in_a_leaf_gets_the_full_breadcrumb_and_neighbours_from_its_own_silo() {
		$posts = get_posts(
			array(
				'category'       => $this->cats['hang-xe-may'],
				'posts_per_page' => 3,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'post_type'      => 'post',
			)
		);
		$this->assertGreaterThanOrEqual( 3, count( $posts ) );
		$middle = $posts[1];

		$html  = $this->render_url( get_permalink( $middle ) );
		$xp    = $this->xpath( $html );
		$trail = array();
		foreach ( $xp->query( '//nav[contains(@class,"breadcrumbs")]//li' ) as $item ) {
			$trail[] = trim( $item->textContent );
		}
		$this->assertSame( array( 'Home', 'Cẩm nang', 'Hãng xe', 'Hãng xe máy', trim( wp_strip_all_tags( $middle->post_title ) ) ), $trail );
		$this->assertSame( wp_list_pluck( nh_seo_breadcrumb_trail(), 'name' ), array_merge( array_slice( $trail, 0, 4 ), array( nh_editorial_plain_title( $middle ) ) ), 'the visible trail equals the plugin trail' );

		$prev_next = $xp->query( '//nav[contains(@class,"post-nav")]//a' );
		$this->assertSame( 2, $prev_next->length );
		foreach ( $prev_next as $link ) {
			$neighbour = url_to_postid( $link->getAttribute( 'href' ) );
			$this->assertContains( $this->cats['hang-xe-may'], wp_get_post_categories( $neighbour ), 'previous and next stay inside the same category' );
		}

		$related = $xp->query( $this->by_class( 'related', 'section' ) . '//article//*[contains(@class,"card__title")]/a' );
		$this->assertSame( 3, $related->length );
		foreach ( $related as $link ) {
			$this->assertContains( $this->cats['hang-xe-may'], wp_get_post_categories( url_to_postid( $link->getAttribute( 'href' ) ) ), 'related articles come from the same leaf first' );
		}
	}

	public function test_a_leaf_with_few_articles_borrows_related_articles_from_its_parent_levels() {
		$posts   = get_posts(
			array(
				'category'       => $this->cats['xe-dien'],
				'posts_per_page' => 1,
				'post_type'      => 'post',
			)
		);
		$related = nh_editorial_get_related_ids( $posts[0]->ID, 6 );
		$this->assertCount( 6, $related, 'sibling leaves and parent levels fill the list' );
		$this->assertNotContains( $posts[0]->ID, $related );
		$this->assertSame( count( $related ), count( array_unique( $related ) ) );
	}

	public function test_the_uncategorized_article_still_renders_without_a_topic_trail() {
		$posts = get_posts(
			array(
				'category'  => $this->cats['uncategorized'],
				'post_type' => 'post',
			)
		);
		$this->assertCount( 1, $posts );
		$html = $this->render_url( get_permalink( $posts[0] ) );
		$this->assertSame( 1, $this->count_nodes( $html, '//h1' ) );
		$this->assertSame( array(), $this->duplicate_ids( $html ) );
	}

	public function test_every_page_of_the_live_inventory_renders_with_one_h1() {
		foreach ( array( 'dieu-khoan', 'gia-lam', 'lien-he', 'hoi-dap', 'cam-nang', 'bang-gia', 'gioi-thieu', 'khu-vuc', 'dich-vu', 'xe-dien' ) as $slug ) {
			$html = $this->render_url( home_url( '/' . $slug . '/' ) );
			$this->assertSame( 1, $this->count_nodes( $html, '//h1' ), $slug );
			$this->assertSame( array(), $this->heading_skips( $html ), $slug );
		}
	}
}
