<?php
/**
 * Homepage and footer SEO matrix: Pillar -> Hub -> Silo -> Topic Cluster.
 *
 * Curated from existing published WordPress pages and posts on 2026-10-22.
 * No permalinks, canonical tags, metadata, terms, or writer records are edited.
 * IDs are checked on every render, and any missing/unpublished item is omitted
 * or replaced with its already-published related article.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

/**
 * Native site language, not browser locale. The EN plugin owns /en/ routing.
 *
 * @return bool
 */
function nh_editorial_seo_is_en() {
	return class_exists( 'NH_VIEN_Routes' )
		&& class_exists( 'NH_VIEN_Context' )
		&& NH_VIEN_Routes::live()
		&& NH_VIEN_Context::is_english();
}

/**
 * Each matrix entry: short navigation label, preferred pillar/page, and
 * supporting post IDs. The body and footer share one catalog, never two
 * conflicting hand-written link lists.
 *
 * @param bool $en English page.
 * @return array
 */
function nh_editorial_seo_priority_catalog( $en ) {
	if ( $en ) {
		return array(
			array( 'label' => 'Hanoi Rental',    'pillar' => 1827, 'posts' => array( 1869, 1922 ), 'group' => 'rental' ),
			array( 'label' => 'Monthly Rental',  'pillar' => 1869, 'posts' => array( 2240, 2647 ), 'group' => 'rental' ),
			array( 'label' => 'Near You',        'pillar' => 2272, 'posts' => array( 1895, 1873 ), 'group' => 'rental' ),
			array( 'label' => 'Rental Prices',   'pillar' => 1760, 'posts' => array( 2645, 2723 ), 'group' => 'rental' ),
			array( 'label' => '50cc Rental',     'pillar' => 1828, 'posts' => array( 1819, 2240 ), 'group' => 'vehicles' ),
			array( 'label' => 'Honda Vision',    'pillar' => 1809, 'posts' => array( 2068, 2160 ), 'group' => 'vehicles' ),
			array( 'label' => 'Long Bien',       'pillar' => 1834, 'posts' => array( 1895, 2272 ), 'group' => 'areas' ),
			array( 'label' => 'Hoan Kiem',       'pillar' => 1832, 'posts' => array( 1873, 2492 ), 'group' => 'areas' ),
			array( 'label' => 'Tay Ho',          'pillar' => 1889, 'posts' => array( 1895 ), 'group' => 'areas' ),
			array( 'label' => 'Old Quarter',     'pillar' => 1833, 'posts' => array( 1823 ), 'group' => 'areas' ),
			array( 'label' => 'Ba Dinh',         'pillar' => 1835, 'posts' => array( 1875 ), 'group' => 'areas' ),
			array( 'label' => 'Rental Areas',    'pillar' => 1831, 'posts' => array( 1875, 1889 ), 'group' => 'areas' ),
		);
	}

	return array(
		array( 'label' => 'Thuê xe máy',  'pillar' => 16,  'posts' => array( 333, 165 ),  'group' => 'rental' ),
		array( 'label' => 'Thuê xe tháng',   'pillar' => 164, 'posts' => array( 406, 163 ),  'group' => 'rental' ),
		array( 'label' => 'Thuê gần đây',    'pillar' => 387, 'posts' => array( 397, 395 ),  'group' => 'rental' ),
		array( 'label' => 'Giá thuê xe',     'pillar' => 19,  'posts' => array( 406, 402 ),  'group' => 'rental' ),
		array( 'label' => 'Xe máy 50cc',    'pillar' => 17,  'posts' => array( 422, 1129 ), 'group' => 'vehicles' ),
		array( 'label' => 'Honda Vision',   'pillar' => 1018,'posts' => array( 853, 799 ),  'group' => 'vehicles' ),
		array( 'label' => 'Long Biên',      'pillar' => 26,  'posts' => array( 381, 1191 ), 'group' => 'areas' ),
		array( 'label' => 'Hoàn Kiếm',      'pillar' => 21,  'posts' => array( 385, 1154 ), 'group' => 'areas' ),
		array( 'label' => 'Tây Hồ',         'pillar' => 389, 'posts' => array( 1134 ),     'group' => 'areas' ),
		array( 'label' => 'Phố Cổ',         'pillar' => 22,  'posts' => array( 383 ),      'group' => 'areas' ),
		array( 'label' => 'Ba Đình',        'pillar' => 27,  'posts' => array( 391, 1140 ), 'group' => 'areas' ),
		array( 'label' => 'Theo khu vực',   'pillar' => 20,  'posts' => array( 381, 385 ),  'group' => 'areas' ),
	);
}

/**
 * Resolve the actual permalink only for a published, viewable page or post of
 * the expected language. Do not send EN readers to an unpaired Vietnamese URL.
 *
 * @param int  $id Target.
 * @param bool $en Target language.
 * @return string Empty when invalid.
 */
function nh_editorial_seo_published_url( $id, $en ) {
	$id   = absint( $id );
	$post = get_post( $id );
	if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || '' !== (string) $post->post_password || ! in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
		return '';
	}
	$url  = (string) get_permalink( $id );
	$path = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
	if ( ! $url || (bool) ( 'en' === $path || 0 === strpos( $path, 'en/' ) ) !== (bool) $en ) {
		return '';
	}
	return $url;
}

/**
 * Resolve first available primary landing and up to two distinct supporting
 * articles. The support links preserve the real article title as anchor text.
 *
 * @param array $entry Catalog entry.
 * @param bool  $en Language.
 * @return array|null
 */
function nh_editorial_seo_resolve_entry( $entry, $en ) {
	$ids    = array_merge( array( $entry['pillar'] ), $entry['posts'] );
	$found  = array();
	foreach ( $ids as $id ) {
		$url = nh_editorial_seo_published_url( $id, $en );
		if ( '' !== $url && ! isset( $found[ $url ] ) ) {
			$found[ $url ] = array( 'id' => (int) $id, 'url' => $url, 'title' => get_the_title( $id ) );
		}
	}
	if ( ! $found ) {
		return null;
	}
	$links = array_values( $found );
	return array(
		'label'   => $entry['label'],
		'group'   => $entry['group'],
		'primary' => array_shift( $links ),
		'support' => array_slice( $links, 0, 2 ),
	);
}

/**
 * Published related subjects only. These are a topic-discovery fallback, not
 * newly minted empty taxonomy archives or keyword-stuffed doorway pages.
 *
 * @param bool $en Language.
 * @return array
 */
function nh_editorial_seo_topic_catalog( $en ) {
	if ( $en ) {
		return array(
			'Driving Licence'    => 2212,
			'Rider Safety'       => 2751,
			'Bike Maintenance'   => 2653,
			'Urban Transport'    => 2492,
			'Hanoi Travel'       => 1875,
			'Vietnam Travel'     => 1811,
			'Motorbike Touring'  => 1899,
			'Touring Routes'     => 1938,
			'Local Attractions'  => 1877,
			'Local Food'         => 1946,
			'Vietnamese Culture' => 1946,
			'Trip Budget'        => 1942,
			'Independent Travel'=> 1938,
		);
	}
	return array(
		'Giấy tờ xe'      => 412,
		'An toàn lái xe'   => 693,
		'Bảo dưỡng xe'     => 989,
		'Giao thông đô thị'=> 737,
		'Du lịch Hà Nội'   => 1140,
		'Du lịch xe máy'   => 1181,
		'Cung đường phượt'=> 1181,
		'Địa điểm tham quan'=> 1177,
		'Chi phí du lịch'  => 329,
		'Kinh nghiệm tự túc'=> 165,
	);
}

/**
 * Print homepage pillar and cluster structure in three clearly grouped areas.
 * Does not replace the normal latest-post listing or its pagination.
 *
 * @return void
 */
function nh_editorial_seo_home_matrix() {
	$en      = nh_editorial_seo_is_en();
	$catalog = nh_editorial_seo_priority_catalog( $en );
	$groups  = array(
		'rental'   => $en ? 'Hanoi motorbike rental and prices' : 'Thuê xe máy Hà Nội và giá thuê',
		'vehicles' => $en ? '50cc motorbikes and Honda Vision' : 'Xe máy 50cc và Honda Vision',
		'areas'    => $en ? 'Motorbike rental by Hanoi area' : 'Thuê xe máy theo khu vực Hà Nội',
	);
	echo '<section class="section seo-matrix" id="seo-priority" aria-labelledby="seo-priority-title"><div class="wrap">';
	echo '<div class="section-head"><div class="section-head__text"><h2 class="section-head__title" id="seo-priority-title">'
		. esc_html( $en ? 'Hanoi motorbike rental guides' : 'Hướng dẫn thuê xe máy Hà Nội' ) . '</h2>';
	echo '<p class="section-head__desc">' . esc_html( $en
		? 'Start with rental prices, monthly plans, 50cc motorbikes and area-specific guides.'
		: 'Ưu tiên bảng giá, thuê xe tháng, xe máy 50cc và hướng dẫn từng khu vực Hà Nội.' ) . '</p></div></div>';

	foreach ( $groups as $key => $heading ) {
		$resolved = array();
		foreach ( $catalog as $entry ) {
			if ( $entry['group'] !== $key ) {
				continue;
			}
			$item = nh_editorial_seo_resolve_entry( $entry, $en );
			if ( $item ) {
				$resolved[] = $item;
			}
		}
		if ( ! $resolved ) {
			continue;
		}
		echo '<section class="seo-matrix__group" aria-label="' . esc_attr( $heading ) . '"><h3 class="seo-matrix__heading">' . esc_html( $heading ) . '</h3><div class="seo-matrix__grid">';
		foreach ( $resolved as $item ) {
			echo '<article class="seo-matrix__card"><h4><a href="' . esc_url( $item['primary']['url'] ) . '">' . esc_html( $item['label'] ) . '</a></h4>';
			if ( $item['support'] ) {
				echo '<ul class="seo-matrix__support">';
				foreach ( $item['support'] as $link ) {
					echo '<li><a href="' . esc_url( $link['url'] ) . '">' . esc_html( $link['title'] ) . '</a></li>';
				}
				echo '</ul>';
			}
			echo '</article>';
		}
		echo '</div></section>';
	}

	$topics = nh_editorial_seo_topic_catalog( $en );
	$valid  = array();
	foreach ( $topics as $label => $id ) {
		$url = nh_editorial_seo_published_url( $id, $en );
		if ( $url && ! isset( $valid[ $url ] ) ) {
			$valid[ $url ] = $label;
		}
	}
	if ( $valid ) {
		echo '<div class="seo-matrix__related"><h3>' . esc_html( $en ? 'Related riding and travel topics' : 'Chủ đề liên quan: lái xe và du lịch' ) . '</h3><ul class="seo-matrix__chips">';
		foreach ( $valid as $url => $label ) {
			echo '<li><a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></li>';
		}
		echo '</ul></div>';
	}
	echo '</div></section>';
}

/**
 * Shared short-link footer strip, beneath existing navigation, on VI and EN.
 * Makes selected money-page links stable even as the AI Writer adds new posts.
 *
 * @return void
 */
function nh_editorial_seo_footer_links() {
	$en    = nh_editorial_seo_is_en();
	$items = array();
	foreach ( nh_editorial_seo_priority_catalog( $en ) as $entry ) {
		$resolved = nh_editorial_seo_resolve_entry( $entry, $en );
		if ( $resolved ) {
			$items[ $resolved['primary']['url'] ] = $resolved['label'];
		}
	}
	if ( ! $items ) {
		return;
	}
	echo '<nav class="seo-footer-links" aria-label="' . esc_attr( $en ? 'Priority guides' : 'Hướng dẫn ưu tiên' ) . '"><h2>'
		. esc_html( $en ? 'Rental guides' : 'Hướng dẫn thuê xe' ) . '</h2><ul>';
	foreach ( $items as $url => $label ) {
		echo '<li><a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></li>';
	}
	echo '</ul></nav>';
}

/**
 * Short visible labels for the existing category Hub links in the footer.
 * Category names, URLs and terms remain unchanged.
 *
 * @param array $hub Array from nh_editorial_get_hubs().
 * @return string
 */
function nh_editorial_seo_footer_hub_label( $hub ) {
	$id = isset( $hub['id'] ) ? (int) $hub['id'] : 0;
	$short = array(
		46 => 'Rental Guides',
		47 => 'Hanoi Routes',
		48 => 'Rider Safety',
		15 => 'Cẩm nang',
		9  => 'Khu vực',
		3  => 'Dịch vụ',
	);
	return isset( $short[ $id ] ) ? $short[ $id ] : (string) $hub['name'];
}
