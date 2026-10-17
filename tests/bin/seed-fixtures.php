<?php
/**
 * Deterministic fixture site for tests and screenshots. Run through WP-CLI:
 *
 *   wp eval-file tests/bin/seed-fixtures.php
 *
 * TEST DATA ONLY: every business value below is a clearly fake placeholder on the reserved
 * example.org domain. It is never used by the theme or the plugin outside a test site.
 *
 * Creates (idempotently): hub categories with children (English and Vietnamese), tags, an author with a
 * biography, ~46 posts with generated featured images, two sticky posts, one fully featured article
 * (summary, takeaways, FAQ, sources, cluster, tables), a Pillar page mapped to a category, a password
 * post, a private post, a draft, a menu, widgets-free footer, a few comments and Customizer values.
 *
 * @package nguyen-ha-editorial
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) ) {
	exit;
}

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

wp_defer_term_counting( true );

/**
 * Make (or find) a category.
 *
 * @param string $name   Name.
 * @param string $slug   Slug.
 * @param int    $parent Parent ID.
 * @param string $desc   Description.
 * @return int
 */
function nh_seed_cat( $name, $slug, $parent = 0, $desc = '' ) {
	$existing = get_term_by( 'slug', $slug, 'category' );
	if ( $existing ) {
		return (int) $existing->term_id;
	}
	$result = wp_insert_term(
		$name,
		'category',
		array(
			'slug'        => $slug,
			'parent'      => $parent,
			'description' => $desc,
		)
	);
	return (int) $result['term_id'];
}

/**
 * Generate a gradient JPEG and register it as an attachment.
 *
 * @param string $label Text drawn on the image.
 * @param int    $hue   0-359.
 * @return int Attachment ID.
 */
function nh_seed_image( $label, $hue ) {
	$existing = get_posts(
		array(
			'post_type'      => 'attachment',
			'title'          => $label,
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'post_status'    => 'inherit',
		)
	);
	if ( $existing ) {
		return (int) $existing[0];
	}

	$width  = 1600;
	$height = 900;
	$img    = imagecreatetruecolor( $width, $height );
	for ( $y = 0; $y < $height; $y++ ) {
		$t = $y / $height;
		$r = (int) ( 20 + 60 * sin( deg2rad( $hue ) ) * $t + 20 * $t );
		$g = (int) ( 70 + 90 * $t * cos( deg2rad( $hue ) ) + 30 );
		$b = (int) ( 140 + 90 * $t );
		imagefilledrectangle( $img, 0, $y, $width, $y + 1, imagecolorallocate( $img, min( 255, abs( $r ) ), min( 255, abs( $g ) ), min( 255, $b ) ) );
	}
	imagestring( $img, 5, 40, $height - 60, $label, imagecolorallocate( $img, 255, 255, 255 ) );

	$upload = wp_upload_dir();
	$file   = $upload['path'] . '/' . sanitize_title( $label ) . '.jpg';
	imagejpeg( $img, $file, 82 );
	imagedestroy( $img );

	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/jpeg',
			'post_title'     => $label,
			'post_status'    => 'inherit',
		),
		$file
	);
	update_post_meta( $attachment_id, '_wp_attachment_image_alt', $label );
	wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $file ) );
	return (int) $attachment_id;
}

/**
 * Body markup with headings, lists, a table, a quote and an image placeholder-free structure.
 *
 * @param string $topic Topic words.
 * @param int    $depth Number of H2 sections.
 * @return string
 */
function nh_seed_body( $topic, $depth = 4 ) {
	$out = '<p>' . sprintf( 'This article explains %s in plain language, with practical steps and the details people usually ask about before they decide.', $topic ) . '</p>';
	for ( $i = 1; $i <= $depth; $i++ ) {
		$out .= '<h2>' . sprintf( 'Part %d: what to know about %s', $i, $topic ) . '</h2>';
		$out .= '<p>Good preparation saves time and money. Compare options calmly, read every condition, and keep copies of the documents you hand over. Ask questions until the answers are clear.</p>';
		$out .= '<p>Short paragraphs, concrete numbers confirmed by the provider, and honest limits make an article useful. This sentence is filler to give the reading-time estimate something to count.</p>';
		if ( 2 === $i ) {
			$out .= '<h3>A quick checklist</h3><ul><li>Check the vehicle condition before leaving.</li><li>Confirm the price and the deposit in writing.</li><li>Take photos of any existing damage.</li></ul>';
		}
		if ( 3 === $i ) {
			$out .= '<h3>Comparison</h3><table><thead><tr><th>Option</th><th>Best for</th><th>Note</th></tr></thead><tbody><tr><td>Automatic scooter</td><td>City riding</td><td>Easy for beginners</td></tr><tr><td>Manual bike</td><td>Experienced riders</td><td>Needs clutch skill</td></tr><tr><td>Electric bike</td><td>Short trips</td><td>Confirm range with the provider</td></tr></tbody></table>';
		}
		if ( 4 === $i ) {
			$out .= '<blockquote><p>Never rely on a verbal promise: if it matters, get it in writing.</p></blockquote>';
		}
	}
	return $out;
}

// ---------------------------------------------------------------------------------------------
// Users.
// ---------------------------------------------------------------------------------------------
$author_id = username_exists( 'jane_editor' );
if ( ! $author_id ) {
	$author_id = wp_insert_user(
		array(
			'user_login'   => 'jane_editor',
			'user_pass'    => wp_generate_password( 24 ),
			'user_email'   => 'jane@example.org',
			'display_name' => 'Jane Doe',
			'role'         => 'editor',
		)
	);
}
update_user_meta( $author_id, 'description', 'Jane writes practical guides about renting and riding motorbikes. This biography is test data.' );
update_user_meta( $author_id, 'nh_job_title', 'Travel writer' );
update_user_meta( $author_id, 'nh_sameas', "https://example.org/jane\nhttps://www.linkedin.com/in/jane-example" );

// ---------------------------------------------------------------------------------------------
// Categories (hubs and sub-hubs).
// ---------------------------------------------------------------------------------------------
$rental  = nh_seed_cat( 'Motorbike Rental', 'motorbike-rental', 0, 'Everything about renting a motorbike: choosing a model, prices, deposits and the paperwork.' );
$scoot   = nh_seed_cat( 'Scooters', 'scooters', $rental, 'Automatic scooters for city riding.' );
$manual  = nh_seed_cat( 'Manual Bikes', 'manual-bikes', $rental, 'Semi-automatic and manual motorbikes.' );
$pricing = nh_seed_cat( 'Pricing and Deposits', 'pricing-and-deposits', $rental );
$travel  = nh_seed_cat( 'Hanoi Travel Guides', 'hanoi-travel-guides', 0, 'Neighbourhoods, routes and day trips around Hanoi.' );
$old     = nh_seed_cat( 'Old Quarter', 'old-quarter', $travel );
$trips   = nh_seed_cat( 'Day Trips', 'day-trips', $travel );
$safety  = nh_seed_cat( 'Riding Safety and Rules', 'riding-safety-and-rules', 0, 'Licences, traffic rules and staying safe on the road.' );
$docs    = nh_seed_cat( 'Documents and Licences', 'documents-and-licences', $safety );
$rules   = nh_seed_cat( 'Traffic Rules', 'traffic-rules', $safety );
$vi_hub  = nh_seed_cat( 'Du lịch Hà Nội', 'du-lich-ha-noi', 0, 'Kinh nghiệm du lịch Hà Nội: phố cổ, ẩm thực và những điểm đến nên ghé.' );
$vi_pho  = nh_seed_cat( 'Phố cổ', 'pho-co', $vi_hub );
$vi_food = nh_seed_cat( 'Ẩm thực', 'am-thuc', $vi_hub );

// Tags.
foreach ( array( 'beginners', 'safety', 'prices', 'night-riding', 'documents' ) as $tag ) {
	if ( ! term_exists( $tag, 'post_tag' ) ) {
		wp_insert_term( $tag, 'post_tag' );
	}
}

// Topic clusters (only when the companion plugin is active).
$clusters = array();
if ( taxonomy_exists( 'topic_cluster' ) ) {
	foreach ( array(
		'scooter-basics'   => 'Scooter basics',
		'first-time-riders' => 'First-time riders',
		'deposit-paperwork' => 'Deposits and paperwork',
	) as $slug => $name ) {
		$term = get_term_by( 'slug', $slug, 'topic_cluster' );
		if ( ! $term ) {
			$made = wp_insert_term( $name, 'topic_cluster', array( 'slug' => $slug ) );
			$term = get_term( (int) $made['term_id'], 'topic_cluster' );
		}
		$clusters[ $slug ] = (int) $term->term_id;
	}
}

// Images.
$images = array();
foreach ( array( 'Scooter day', 'Old Quarter streets', 'Mountain road', 'Helmet check', 'City lights', 'Rice terraces', 'Coffee stop', 'Lake view' ) as $n => $label ) {
	$images[] = nh_seed_image( $label, $n * 40 );
}

// ---------------------------------------------------------------------------------------------
// Posts.
// ---------------------------------------------------------------------------------------------
$specs = array(
	array( $scoot, 'Which scooter should a first-time visitor rent?', 'choosing an automatic scooter' ),
	array( $scoot, 'How to check a rental scooter before you ride', 'inspecting a scooter before riding' ),
	array( $scoot, 'Automatic scooters compared: 110cc, 125cc and 150cc', 'engine sizes for city riding' ),
	array( $scoot, 'Riding a scooter with luggage: what is realistic', 'carrying luggage on a scooter' ),
	array( $scoot, 'Rain, helmets and scooters: a practical guide', 'riding a scooter in the rain' ),
	array( $manual, 'Manual or semi-automatic: which suits you?', 'choosing between manual and semi-automatic' ),
	array( $manual, 'Hill roads on a manual bike', 'climbing and descending safely' ),
	array( $manual, 'Gear shifting basics for beginners', 'shifting gears smoothly' ),
	array( $pricing, 'Typical rental price structure explained', 'how daily, weekly and monthly prices work' ),
	array( $pricing, 'Deposits: what is normal and what is not', 'rental deposits and refunds' ),
	array( $pricing, 'Insurance and damage: reading the fine print', 'rental insurance basics' ),
	array( $pricing, 'Negotiating a weekly rate politely', 'asking for a better long-term rate' ),
	array( $old, 'A slow morning walk through the Old Quarter', 'exploring the Old Quarter on foot' ),
	array( $old, 'Where to park a motorbike in the Old Quarter', 'parking in a crowded neighbourhood' ),
	array( $old, 'Street food stops worth the detour', 'planning a food walk' ),
	array( $trips, 'A relaxed day trip to a nearby village', 'planning a village day trip' ),
	array( $trips, 'Packing list for a long riding day', 'what to bring on a day trip' ),
	array( $trips, 'Sunset routes outside the city', 'timing a ride for sunset' ),
	array( $trips, 'Fuel stops and breaks on longer rides', 'planning fuel and rest' ),
	array( $docs, 'Which licence do you need to ride legally?', 'riding licences for visitors' ),
	array( $docs, 'Documents to carry on every ride', 'the paperwork to keep with you' ),
	array( $docs, 'Translating and validating a foreign licence', 'licence validation steps' ),
	array( $rules, 'Roundabouts and right of way', 'traffic flow in busy junctions' ),
	array( $rules, 'Speed limits and police checks', 'speed limits and what to expect' ),
	array( $rules, 'Riding at night: visibility and lights', 'night riding safety' ),
	array( $rules, 'Helmets: the rules and the real risks', 'helmet choice and rules' ),
	array( $rules, 'Parking rules and fines', 'where you may and may not park' ),
	array( $safety, 'Ten habits of safe riders', 'habits that prevent accidents' ),
);

$post_ids = array();
$time     = strtotime( '-60 days' );
$tag_names = array( 'beginners', 'safety', 'prices', 'night-riding', 'documents' );
foreach ( $specs as $index => $spec ) {
	$slug     = sanitize_title( $spec[1] );
	$existing = get_page_by_path( $slug, OBJECT, 'post' );
	if ( $existing ) {
		$post_ids[] = (int) $existing->ID;
		continue;
	}
	$time    += DAY_IN_SECONDS + ( $index * 600 );
	$post_id  = wp_insert_post(
		array(
			'post_title'    => $spec[1],
			'post_name'     => $slug,
			'post_status'   => 'publish',
			'post_author'   => 0 === $index % 3 ? $author_id : 1,
			'post_date'     => gmdate( 'Y-m-d H:i:s', $time ),
			'post_category' => array( $spec[0] ),
			'post_content'  => nh_seed_body( $spec[2], 3 + ( $index % 3 ) ),
			'post_excerpt'  => 0 === $index % 4 ? sprintf( 'A short manual excerpt about %s.', $spec[2] ) : '',
			'tags_input'    => array( $tag_names[ $index % 5 ] ),
		)
	);
	set_post_thumbnail( $post_id, $images[ $index % count( $images ) ] );
	$post_ids[] = $post_id;
}

// Vietnamese articles (diacritics in titles, slugs and search).
$vi_specs = array(
	array( $vi_pho, 'Đi bộ khám phá phố cổ Hà Nội vào buổi sáng', 'khám phá phố cổ' ),
	array( $vi_pho, 'Gửi xe máy ở khu phố cổ: những điều cần biết', 'gửi xe ở phố cổ' ),
	array( $vi_pho, 'Cà phê trứng và những quán nên thử', 'quán cà phê' ),
	array( $vi_food, 'Bún chả, phở và những món ăn sáng quen thuộc', 'món ăn sáng' ),
	array( $vi_food, 'Ăn gì khi đi xe máy một ngày dài', 'ăn uống khi đi xa' ),
	array( $vi_food, 'Chợ đêm và ẩm thực đường phố', 'ẩm thực buổi tối' ),
);
foreach ( $vi_specs as $index => $spec ) {
	$slug     = sanitize_title( $spec[1] );
	$existing = get_page_by_path( $slug, OBJECT, 'post' );
	if ( $existing ) {
		$post_ids[] = (int) $existing->ID;
		continue;
	}
	$time    += DAY_IN_SECONDS;
	$post_id  = wp_insert_post(
		array(
			'post_title'    => $spec[1],
			'post_name'     => $slug,
			'post_status'   => 'publish',
			'post_author'   => $author_id,
			'post_date'     => gmdate( 'Y-m-d H:i:s', $time ),
			'post_category' => array( $spec[0] ),
			'post_content'  => '<p>Bài viết về ' . $spec[2] . ' bằng tiếng Việt, có dấu đầy đủ để kiểm tra tìm kiếm và hiển thị.</p><h2>Phần 1: chuẩn bị</h2><p>Chuẩn bị kỹ giúp chuyến đi nhẹ nhàng hơn. Hãy đọc kỹ điều kiện và hỏi lại khi chưa rõ.</p><h2>Phần 2: lưu ý</h2><p>Luôn giữ giấy tờ bên người và tuân thủ luật giao thông.</p><h2>Phần 3: gợi ý</h2><p>Một vài gợi ý nhỏ để bạn có trải nghiệm tốt hơn.</p>',
		)
	);
	set_post_thumbnail( $post_id, $images[ $index % count( $images ) ] );
	$post_ids[] = $post_id;
}

// The fully featured article.
$feature_slug = 'complete-guide-to-choosing-your-first-scooter';
$feature      = get_page_by_path( $feature_slug, OBJECT, 'post' );
if ( ! $feature ) {
	$feature_id = wp_insert_post(
		array(
			'post_title'    => 'The complete guide to choosing your first scooter',
			'post_name'     => $feature_slug,
			'post_status'   => 'publish',
			'post_author'   => $author_id,
			'post_date'     => gmdate( 'Y-m-d H:i:s', strtotime( '-3 days' ) ),
			'post_category' => array( $scoot, $rental ),
			'post_content'  => nh_seed_body( 'choosing your first scooter', 5 ),
		)
	);
	set_post_thumbnail( $feature_id, $images[0] );
	wp_set_post_tags( $feature_id, array( 'beginners', 'safety' ) );
} else {
	$feature_id = (int) $feature->ID;
}
update_post_meta( $feature_id, 'nh_summary', 'For a first visit pick an automatic 110 to 125 cc scooter: it is the easiest to ride in city traffic. Confirm the price and deposit in writing before you leave.' );
update_post_meta( $feature_id, 'nh_takeaways', array( 'Automatic scooters are the easiest choice for beginners.', 'Inspect the vehicle and photograph any damage before riding.', 'Get the price and deposit confirmed in writing.', 'Carry the documents you need at all times.' ) );
update_post_meta(
	$feature_id,
	'nh_faq',
	array(
		array(
			'question' => 'Can I rent a scooter without riding experience?',
			'answer'   => '<p>Many visitors do, but ask the provider about their requirements and consider a quiet area to practise first.</p>',
		),
		array(
			'question' => 'What should I check before leaving the shop?',
			'answer'   => '<p>Brakes, lights, mirrors, tyres and the fuel level. Take <strong>dated photos</strong> of any existing damage.</p>',
		),
		array(
			'question' => 'Is a helmet included?',
			'answer'   => '<p>Ask the provider; <a href="https://example.org/helmets">read our helmet guide</a> for what to look for.</p>',
		),
		array(
			'question' => 'XSS probe: does the theme escape this?',
			'answer'   => '<p>Safe text.</p><script>window.__nh_xss = 1;</script><img src=x onerror="window.__nh_xss = 2">',
		),
		array(
			'question' => 'How long can I keep the scooter?',
			'answer'   => '<p>Daily, weekly and monthly terms exist; confirm availability and the price with the provider.</p>',
		),
	)
);
update_post_meta(
	$feature_id,
	'nh_sources',
	array(
		array(
			'title'     => 'Official road safety guidance',
			'url'       => 'https://example.org/road-safety',
			'publisher' => 'Example Authority',
		),
		array(
			'title' => 'Motorbike rental terms explained',
			'url'   => 'https://example.org/terms',
		),
		array(
			'title' => 'Not allowed',
			'url'   => 'javascript:alert(1)',
		),
	)
);
update_post_meta( $feature_id, 'nh_content_updated', gmdate( 'Y-m-d', strtotime( '-1 day' ) ) );
update_post_meta( $feature_id, 'nh_topic_cluster', 'scooter-basics' );
if ( ! empty( $clusters['scooter-basics'] ) ) {
	wp_set_object_terms( $feature_id, array( $clusters['scooter-basics'] ), 'topic_cluster' );
	update_post_meta( $feature_id, 'nh_primary_cluster', $clusters['scooter-basics'] );
	// A few more posts in the same cluster for related/adjacent tests.
	foreach ( array_slice( $post_ids, 0, 6 ) as $cluster_post ) {
		wp_set_object_terms( $cluster_post, array( $clusters['scooter-basics'] ), 'topic_cluster' );
	}
}
$post_ids[] = $feature_id;

// A post with a cluster alias only (works without the plugin).
$alias_posts = array_slice( $post_ids, 8, 4 );
foreach ( $alias_posts as $alias_post ) {
	update_post_meta( $alias_post, 'nh_topic_cluster', 'deposit-paperwork' );
}

// Sticky (featured) posts.
update_option( 'sticky_posts', array( $feature_id, $post_ids[1] ) );

// Non-public content that must never be linked.
$hidden = array(
	'draft'   => array( 'Hidden draft article', 'draft' ),
	'private' => array( 'Hidden private article', 'private' ),
	'future'  => array( 'Hidden scheduled article', 'future' ),
);
foreach ( $hidden as $key => $data ) {
	if ( ! get_page_by_path( sanitize_title( $data[0] ), OBJECT, 'post' ) ) {
		wp_insert_post(
			array(
				'post_title'    => $data[0],
				'post_status'   => $data[1],
				'post_date'     => 'future' === $key ? gmdate( 'Y-m-d H:i:s', strtotime( '+30 days' ) ) : gmdate( 'Y-m-d H:i:s' ),
				'post_category' => array( $scoot ),
				'post_content'  => '<p>This must never appear in public lists.</p>',
			)
		);
	}
}
if ( ! get_page_by_path( 'password-protected-article', OBJECT, 'post' ) ) {
	wp_insert_post(
		array(
			'post_title'    => 'Password protected article',
			'post_name'     => 'password-protected-article',
			'post_status'   => 'publish',
			'post_password' => 'secret',
			'post_category' => array( $scoot ),
			'post_content'  => '<p>Protected body.</p>',
		)
	);
}

// ---------------------------------------------------------------------------------------------
// Pages: Pillar page mapped to a category, plain pages.
// ---------------------------------------------------------------------------------------------
$pillar = get_page_by_path( 'motorbike-rental-guide' );
if ( ! $pillar ) {
	$pillar_id = wp_insert_post(
		array(
			'post_title'   => 'Motorbike rental guide',
			'post_name'    => 'motorbike-rental-guide',
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_content' => nh_seed_body( 'renting a motorbike from start to finish', 6 ),
		)
	);
} else {
	$pillar_id = (int) $pillar->ID;
}
update_post_meta( $pillar_id, '_wp_page_template', 'page-templates/template-pillar.php' );
update_post_meta( $pillar_id, 'nh_hub_term', 'category:' . $rental );
update_post_meta( $pillar_id, 'nh_summary', 'The complete path from choosing a bike to returning it and getting your deposit back.' );
update_term_meta( $rental, 'nh_pillar_id', $pillar_id );

foreach ( array(
	'about'   => 'About us',
	'contact' => 'Contact',
) as $slug => $title ) {
	if ( ! get_page_by_path( $slug ) ) {
		wp_insert_post(
			array(
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '<p>' . $title . ' page content for testing.</p><h2>Details</h2><p>More details here.</p>',
			)
		);
	}
}

// ---------------------------------------------------------------------------------------------
// Comments on one article.
// ---------------------------------------------------------------------------------------------
if ( 0 === (int) get_comments_number( $post_ids[0] ) ) {
	foreach ( array( 'Alex', 'Sam', 'Lan' ) as $name ) {
		wp_insert_comment(
			array(
				'comment_post_ID'      => $post_ids[0],
				'comment_author'       => $name,
				'comment_author_email' => strtolower( $name ) . '@example.org',
				'comment_content'      => 'Thanks, this was useful. (test comment by ' . $name . ')',
				'comment_approved'     => 1,
			)
		);
	}
}

// ---------------------------------------------------------------------------------------------
// Menus.
// ---------------------------------------------------------------------------------------------
$menu = wp_get_nav_menu_object( 'Primary' );
if ( ! $menu ) {
	$menu_id = wp_create_nav_menu( 'Primary' );
	foreach ( array(
		array( $rental, array( $scoot, $manual, $pricing ) ),
		array( $travel, array( $old, $trips ) ),
		array( $safety, array( $docs, $rules ) ),
	) as $group ) {
		$parent_item = wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'     => get_term( $group[0] )->name,
				'menu-item-object'    => 'category',
				'menu-item-object-id' => $group[0],
				'menu-item-type'      => 'taxonomy',
				'menu-item-status'    => 'publish',
			)
		);
		foreach ( $group[1] as $child ) {
			wp_update_nav_menu_item(
				$menu_id,
				0,
				array(
					'menu-item-title'     => get_term( $child )->name,
					'menu-item-object'    => 'category',
					'menu-item-object-id' => $child,
					'menu-item-type'      => 'taxonomy',
					'menu-item-parent-id' => $parent_item,
					'menu-item-status'    => 'publish',
				)
			);
		}
	}
	wp_update_nav_menu_item(
		$menu_id,
		0,
		array(
			'menu-item-title'     => 'Contact',
			'menu-item-object'    => 'page',
			'menu-item-object-id' => get_page_by_path( 'contact' )->ID,
			'menu-item-type'      => 'post_type',
			'menu-item-status'    => 'publish',
		)
	);
}

$footer_menu = wp_get_nav_menu_object( 'Footer' );
if ( ! $footer_menu ) {
	$footer_id = wp_create_nav_menu( 'Footer' );
	foreach ( array( 'about', 'contact' ) as $slug ) {
		$page = get_page_by_path( $slug );
		wp_update_nav_menu_item(
			$footer_id,
			0,
			array(
				'menu-item-title'     => $page->post_title,
				'menu-item-object'    => 'page',
				'menu-item-object-id' => $page->ID,
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
			)
		);
	}
}

$locations = get_theme_mod( 'nav_menu_locations', array() );
if ( 'nofallback' !== getenv( 'NH_SEED_MENU' ) ) {
	$locations['primary'] = wp_get_nav_menu_object( 'Primary' )->term_id;
	$locations['footer']  = wp_get_nav_menu_object( 'Footer' )->term_id;
	set_theme_mod( 'nav_menu_locations', $locations );
}

// ---------------------------------------------------------------------------------------------
// Customizer + business profile (fake test values on example.org).
// ---------------------------------------------------------------------------------------------
set_theme_mod( 'nh_cta_label', 'Message us' );
set_theme_mod( 'nh_cta_url', 'https://example.org/contact' );
set_theme_mod( 'nh_cta_text', 'Questions about renting a bike? Send us a message.' );
set_theme_mod( 'nh_social_urls', "https://www.facebook.com/example\nhttps://www.youtube.com/@example" );
set_theme_mod( 'nh_footer_text', 'Test footer note. <a href="https://example.org">Example</a>' );

update_option(
	'nh_seo_business',
	array(
		'name'     => 'Example Rentals (test data)',
		'type'     => 'LocalBusiness',
		'phone'    => '+00 000 000 000',
		'email'    => 'hello@example.org',
		'street'   => '1 Example Street',
		'locality' => 'Example City',
		'country'  => 'VN',
		'hours'    => array( 'Mo-Su 09:00-21:00' ),
	)
);

wp_defer_term_counting( false );
foreach ( get_terms(
	array(
		'taxonomy'   => array( 'category', 'post_tag', 'topic_cluster' ),
		'hide_empty' => false,
		'fields'     => 'ids',
	)
) as $term_id ) {
	$term = get_term( $term_id );
	if ( $term && ! is_wp_error( $term ) ) {
		wp_update_term_count_now( array( (int) $term->term_taxonomy_id ), $term->taxonomy );
	}
}

flush_rewrite_rules();
if ( function_exists( 'nh_editorial_cache_bump_now' ) ) {
	nh_editorial_cache_bump_now();
}

WP_CLI::success( sprintf( 'Fixtures ready: %d posts, pillar page #%d, feature post #%d.', count( $post_ids ), $pillar_id, $feature_id ) );
