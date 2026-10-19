<?php
/**
 * Fixture for the site tests: a small bilingual blog.
 *
 * Run it through WP-CLI on a throw-away site where the plugin is active:
 *   wp eval-file plugins/nguyen-ha-vi-en/tests/site/seed.php
 *
 * It writes the IDs it created to $NH_VIEN_FIXTURE (default: wp-content/nh-vien-fixture.json) for run.php.
 * TEST DATA ONLY; never run it on a site that has real content.
 *
 * @package Nguyen_Ha_VI_EN
 */

// Test tooling: runs through WP-CLI only.
if ( 'cli' !== PHP_SAPI || ! defined( 'ABSPATH' ) || ! class_exists( 'NH_VIEN_Links' ) ) {
	exit;
}

$nh_seed_age  = 0;
$nh_seed_step = getenv( 'NH_VIEN_SEED_STEP' ) ? getenv( 'NH_VIEN_SEED_STEP' ) : 'all';
$nh_seed_out  = getenv( 'NH_VIEN_FIXTURE' ) ? getenv( 'NH_VIEN_FIXTURE' ) : WP_CONTENT_DIR . '/nh-vien-fixture.json';

/** Create a post; the date moves back a little for each one so that the order of the lists is known. */
function nh_seed_post( $title, $slug, $category_ids = array(), $extra = array() ) {
	global $nh_seed_age;
	++$nh_seed_age;
	$id = wp_insert_post(
		array_merge(
			array(
				'post_type'     => 'post',
				'post_status'   => 'publish',
				'post_title'    => $title,
				'post_name'     => $slug,
				'post_content'  => 'Content of ' . $title . ".\n\n<!--more-->\n\nMore of " . $title . '.',
				'post_category' => $category_ids,
				'post_date'     => gmdate( 'Y-m-d H:i:s', time() - $nh_seed_age * 3600 ),
			),
			$extra
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		WP_CLI::error( 'post ' . $slug . ': ' . $id->get_error_message() );
	}
	return (int) $id;
}

/** Create a category. */
function nh_seed_category( $name, $slug, $parent = 0 ) {
	$term = wp_insert_term(
		$name,
		'category',
		array(
			'slug'   => $slug,
			'parent' => $parent,
		)
	);
	if ( is_wp_error( $term ) ) {
		WP_CLI::error( 'category ' . $slug . ': ' . $term->get_error_message() );
	}
	return (int) $term['term_id'];
}

// The administrator creates everything, so that HTML in a title is kept as typed (and the plugin has to escape it).
wp_set_current_user( 1 );

if ( 'pairs' !== $nh_seed_step ) {
	$guides = nh_seed_category( 'Guides', 'guides' );
	$rental = nh_seed_category( 'Motorbike rental', 'motorbike-rental', $guides );
	$tips   = nh_seed_category( 'Travel tips', 'travel-tips' );
	$thue   = nh_seed_category( 'Thuê xe', 'thue-xe' );
	$kinh   = nh_seed_category( 'Kinh nghiệm', 'kinh-nghiem' );

	$fx = array();

	// Vietnamese posts.
	$fx['vi_rent']        = nh_seed_post( 'Thuê xe máy Hà Nội', 'thue-xe-may-ha-noi', array( $thue ) );
	$fx['vi_tips']        = nh_seed_post( 'Kinh nghiệm đi phượt', 'kinh-nghiem-di-phuot', array( $kinh ) );
	$fx['vi_untranslated'] = nh_seed_post( 'Bài chưa dịch', 'bai-chua-dich', array( $kinh ) );
	$fx['vi_draft']       = nh_seed_post( 'Bài tiếng Việt nháp', 'bai-viet-nhap', array( $kinh ), array( 'post_status' => 'draft' ) );
	$fx['vi_collision']   = nh_seed_post( 'Bài trùng trang', 'bai-trung-trang', array( $kinh ) );
	$fx['vi_reserved']    = nh_seed_post( 'Bài slug dành riêng', 'bai-slug-rieng', array( $kinh ) );
	$fx['vi_special']     = nh_seed_post( 'Bài ký tự đặc biệt', 'bai-ky-tu-dac-biet', array( $kinh ) );
	$fx['vi_unpaired_draft_en'] = nh_seed_post( 'Bài có bản dịch nháp', 'bai-co-ban-dich-nhap', array( $kinh ) );

	// English posts.
	$fx['en_rent']      = nh_seed_post( 'Motorbike rental in Hanoi', 'motorbike-rental-in-hanoi', array( $rental ), array( 'tags_input' => array( 'hanoi' ) ) );
	$fx['en_tips']      = nh_seed_post( 'Travel tips for riders', 'travel-tips-for-riders', array( $tips ), array( 'tags_input' => array( 'hanoi', 'safety' ) ) );
	$fx['en_draft']     = nh_seed_post( 'English draft', 'english-draft', array( $tips ), array( 'post_status' => 'draft' ) );
	$fx['en_hidden']    = nh_seed_post( 'Hidden English post', 'hidden-english-post', array( $tips ) );
	$fx['en_collision'] = nh_seed_post( 'Collision post', 'collision-post', array( $tips ) );
	$fx['en_reserved']  = nh_seed_post( 'Reserved slug post', 'category', array( $tips ) );
	$fx['en_special']   = nh_seed_post( 'Bikes & "scooters" <img src=x onerror=alert(1)> cars', 'bikes-and-scooters', array( $tips ) );
	$fx['en_draft2']    = nh_seed_post( 'Translation in progress', 'translation-in-progress', array( $tips ), array( 'post_status' => 'draft' ) );

	// Enough pairs for the English list to have a second page (12 per page): 14 more English posts.
	$fx['bulk'] = array();
	for ( $i = 1; $i <= 14; $i++ ) {
		$suffix        = sprintf( '%02d', $i );
		$vi            = nh_seed_post( 'Bài hàng loạt ' . $suffix, 'bai-hang-loat-' . $suffix, array( $thue ) );
		$en            = nh_seed_post( 'Bulk article ' . $suffix, 'bulk-article-' . $suffix, array( $rental ) );
		$fx['bulk'][] = array( $vi, $en );
	}

	// A draft English home page (the owner publishes it), with a page below it that takes the address of an English post.
	$parent_status = '1' === getenv( 'NH_VIEN_SEED_HOME_PAGE' ) ? 'publish' : 'draft';
	$fx['en_page'] = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_title'   => 'English',
			'post_name'    => 'en',
			'post_status'  => $parent_status,
			'post_content' => '[nh_vien_posts count="50"]',
		)
	);
	$fx['en_page_collision'] = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_title'   => 'Collision',
			'post_name'    => 'collision-post',
			'post_parent'  => $fx['en_page'],
			'post_status'  => 'publish',
			'post_content' => 'A real page below /en/.',
		)
	);
	$fx['en_page_about'] = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_title'   => 'About',
			'post_name'    => 'about',
			'post_parent'  => $fx['en_page'],
			'post_status'  => 'publish',
			'post_content' => 'About (English page).',
		)
	);

	// Menus: the Vietnamese one, and the English one that English pages use (same name with the suffix -en).
	$menu_vi = wp_create_nav_menu( 'primary' );
	$menu_en = wp_create_nav_menu( 'primary-en' );
	if ( ! is_wp_error( $menu_vi ) && ! is_wp_error( $menu_en ) ) {
		wp_update_nav_menu_item(
			$menu_vi,
			0,
			array(
				'menu-item-title'  => 'Trang chủ VI',
				'menu-item-url'    => home_url( '/' ),
				'menu-item-status' => 'publish',
			)
		);
		wp_update_nav_menu_item(
			$menu_en,
			0,
			array(
				'menu-item-title'  => 'English Menu Item',
				'menu-item-url'    => home_url( '/en/' ),
				'menu-item-status' => 'publish',
			)
		);
		set_theme_mod( 'nav_menu_locations', array( 'primary' => (int) $menu_vi ) );
	}

	$fx['terms'] = compact( 'guides', 'rental', 'tips', 'thue', 'kinh' );
	file_put_contents( $nh_seed_out, wp_json_encode( $fx ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test tooling.
	if ( ! get_user_by( 'login', 'sub1' ) ) {
		wp_insert_user(
			array(
				'user_login' => 'sub1',
				'user_pass'  => 'sub1pass',
				'user_email' => 'sub1@example.org',
				'role'       => 'subscriber',
			)
		);
	}
}

if ( 'content' !== $nh_seed_step ) {
	$fx = json_decode( (string) file_get_contents( $nh_seed_out ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Test tooling.
	// Pairs, made the way an administrator makes them (capability and nonce included).
	wp_set_current_user( 1 );
	$nh_seed_nonce = wp_create_nonce( NH_VIEN_Links::NONCE_ACTION );
	$nh_seed_pairs = array(
		array( $fx['vi_rent'], $fx['en_rent'] ),
		array( $fx['vi_tips'], $fx['en_tips'] ),
		array( $fx['vi_untranslated'], $fx['en_draft'] ),
		array( $fx['vi_draft'], $fx['en_hidden'] ),
		array( $fx['vi_collision'], $fx['en_collision'] ),
		array( $fx['vi_reserved'], $fx['en_reserved'] ),
		array( $fx['vi_special'], $fx['en_special'] ),
		array( $fx['vi_unpaired_draft_en'], $fx['en_draft2'] ),
	);
	$nh_seed_pairs = array_merge( $nh_seed_pairs, $fx['bulk'] );
	foreach ( $nh_seed_pairs as $pair ) {
		// The English post of a pair has to be a draft (or pending) when it is paired, so that a Vietnamese post that is already
		// live can never become English by a wrong ID; the administrator publishes it afterwards, with the date it had.
		$english = get_post( $pair[1] );
		$live    = $english && 'publish' === $english->post_status;
		if ( $live ) {
			wp_update_post(
				array(
					'ID'          => $pair[1],
					'post_status' => 'draft',
				)
			);
		}
		$result = NH_VIEN_Links::link( $pair[0], $pair[1], $nh_seed_nonce );
		if ( $live ) {
			wp_update_post(
				array(
					'ID'            => $pair[1],
					'post_status'   => 'publish',
					'post_date'     => $english->post_date,
					'post_date_gmt' => $english->post_date_gmt,
					'edit_date'     => true,
				)
			);
		}
		if ( true !== $result ) {
			WP_CLI::error( 'pair ' . $pair[0] . '/' . $pair[1] . ': ' . ( is_wp_error( $result ) ? $result->get_error_code() : 'failed' ) );
		}
	}
}

WP_CLI::success( 'fixture step ' . $nh_seed_step . ' done: ' . $nh_seed_out );
