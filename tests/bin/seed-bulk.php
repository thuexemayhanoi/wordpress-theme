<?php
/**
 * Large-site fixture: thousands of articles for performance and scalability checks.
 *
 * Run it through WP-CLI on a disposable site with: NH_BULK_POSTS=10000 wp eval-file tests/bin/seed-bulk.php
 *
 * TEST DATA ONLY. Everything is generated from a fixed seed, so two runs produce the same site. Posts and
 * relationships are written with multi-row SQL (a 50,000 article site takes seconds, not hours), then the
 * term counts and caches are rebuilt.
 *
 * Environment:
 *   NH_BULK_POSTS    number of articles         (default 10000)
 *   NH_BULK_HUBS     top-level categories       (default 12)
 *   NH_BULK_CHILDREN child categories per hub   (default 8)
 *   NH_BULK_TAGS     tags                       (default 200)
 *
 * @package nguyen-ha-editorial
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) ) {
	exit;
}

/**
 * Read a positive integer from the environment.
 *
 * @param string $name          Variable name.
 * @param int    $default_value Default.
 * @return int
 */
function nh_bulk_env( $name, $default_value ) {
	$value = getenv( $name );
	return ( false !== $value && (int) $value > 0 ) ? (int) $value : $default_value;
}

/**
 * Deterministic pseudo-random sentence generator.
 *
 * @param int $words Number of words.
 * @return string
 */
function nh_bulk_sentence( $words ) {
	static $vocabulary = array(
		'scooter',
		'motorbike',
		'rental',
		'helmet',
		'rain',
		'traffic',
		'old',
		'quarter',
		'lake',
		'street',
		'food',
		'market',
		'route',
		'loop',
		'mountain',
		'pass',
		'ferry',
		'bridge',
		'night',
		'morning',
		'coffee',
		'insurance',
		'licence',
		'deposit',
		'price',
		'weekly',
		'monthly',
		'automatic',
		'manual',
		'electric',
		'battery',
		'fuel',
		'repair',
		'breakdown',
		'parking',
		'map',
		'guide',
		'tip',
		'safety',
		'checklist',
		'budget',
		'family',
		'solo',
		'group',
		'season',
		'weather',
		'festival',
		'xe',
		'máy',
		'thuê',
		'phố',
		'cổ',
		'hồ',
		'Tây',
		'Hà',
		'Nội',
		'đường',
		'mưa',
		'mũ',
		'bảo',
		'hiểm',
		'giá',
		'đặt',
		'cọc',
		'tay',
		'ga',
		'số',
		'điện',
		'bãi',
		'đỗ',
		'bản',
		'đồ',
		'hướng',
		'dẫn',
		'kinh',
		'nghiệm',
		'an',
		'toàn',
		'mùa',
		'lễ',
		'hội',
	);
	$count             = count( $vocabulary );
	$out               = array();
	for ( $i = 0; $i < $words; $i++ ) {
		$out[] = $vocabulary[ mt_rand( 0, $count - 1 ) ];
	}
	return implode( ' ', $out );
}

/**
 * Article body: an introduction, several sections and sometimes a table.
 *
 * @param int $number Article number.
 * @return string
 */
function nh_bulk_content( $number ) {
	$html     = '<p>' . ucfirst( nh_bulk_sentence( mt_rand( 28, 60 ) ) ) . '.</p>';
	$sections = mt_rand( 3, 6 );
	for ( $i = 1; $i <= $sections; $i++ ) {
		$html .= '<h2>' . ucfirst( nh_bulk_sentence( mt_rand( 3, 6 ) ) ) . '</h2>';
		$html .= '<p>' . ucfirst( nh_bulk_sentence( mt_rand( 60, 140 ) ) ) . '.</p>';
		if ( 0 === $i % 2 ) {
			$html .= '<h3>' . ucfirst( nh_bulk_sentence( mt_rand( 2, 5 ) ) ) . '</h3><p>' . ucfirst( nh_bulk_sentence( mt_rand( 40, 90 ) ) ) . '.</p>';
		}
	}
	if ( 0 === $number % 5 ) {
		$html .= '<table><thead><tr><th>Plan</th><th>Note</th></tr></thead><tbody><tr><td>' . nh_bulk_sentence( 2 ) . '</td><td>' . nh_bulk_sentence( 4 ) . '</td></tr></tbody></table>';
	}
	return $html;
}

/**
 * Create a term, or find it again when the seed runs twice.
 *
 * @param string $name     Name.
 * @param string $taxonomy Taxonomy.
 * @param string $slug     Slug.
 * @param int    $parent_id Parent term ID.
 * @return WP_Term
 */
function nh_bulk_term( $name, $taxonomy, $slug, $parent_id = 0 ) {
	$created = wp_insert_term(
		$name,
		$taxonomy,
		array(
			'slug'   => $slug,
			'parent' => $parent_id,
		)
	);
	if ( is_wp_error( $created ) ) {
		return get_term_by( 'slug', $slug, $taxonomy );
	}
	return get_term( (int) $created['term_id'], $taxonomy );
}

/**
 * Seed the site.
 *
 * @return void
 */
function nh_bulk_seed() {
	global $wpdb;

	$post_total = nh_bulk_env( 'NH_BULK_POSTS', 10000 );
	$hub_total  = nh_bulk_env( 'NH_BULK_HUBS', 12 );
	$kid_total  = nh_bulk_env( 'NH_BULK_CHILDREN', 8 );
	$tag_total  = nh_bulk_env( 'NH_BULK_TAGS', 200 );

	if ( ! preg_match( '#^https?://(127\.0\.0\.1|localhost)#', home_url() ) ) {
		WP_CLI::error( 'Refusing to seed a site that is not local: ' . home_url() );
	}

	mt_srand( 20260101 );
	wp_defer_term_counting( true );
	wp_suspend_cache_invalidation( true );

	WP_CLI::log( 'Authors, categories and tags' );
	$authors = array();
	for ( $i = 1; $i <= 5; $i++ ) {
		$login = 'bulk_author_' . $i;
		$user  = get_user_by( 'login', $login );
		if ( ! $user ) {
			$user_id = wp_insert_user(
				array(
					'user_login'   => $login,
					'user_pass'    => wp_generate_password(),
					'user_email'   => $login . '@example.org',
					'display_name' => 'Bulk Author ' . $i,
					'role'         => 'author',
					'description'  => 'Generated author ' . $i . ' for the large-site fixture.',
				)
			);
		} else {
			$user_id = $user->ID;
		}
		$authors[] = (int) $user_id;
	}

	$leaf_tt = array();
	$hub_ids = array();
	for ( $h = 1; $h <= $hub_total; $h++ ) {
		$hub           = nh_bulk_term( 'Hub ' . $h . ' ' . nh_bulk_sentence( 1 ), 'category', 'bulk-hub-' . $h );
		$hub_ids[ $h ] = (int) $hub->term_id;
		for ( $c = 1; $c <= $kid_total; $c++ ) {
			$kid       = nh_bulk_term( 'Topic ' . $h . '.' . $c . ' ' . nh_bulk_sentence( 1 ), 'category', 'bulk-topic-' . $h . '-' . $c, $hub_ids[ $h ] );
			$leaf_tt[] = (int) $kid->term_taxonomy_id;
		}
	}
	$tag_tt = array();
	for ( $t = 1; $t <= $tag_total; $t++ ) {
		$tag      = nh_bulk_term( 'tag ' . $t . ' ' . nh_bulk_sentence( 1 ), 'post_tag', 'bulk-tag-' . $t );
		$tag_tt[] = (int) $tag->term_taxonomy_id;
	}

	WP_CLI::log( 'Pillar pages (one per hub)' );
	foreach ( $hub_ids as $h => $term_id ) {
		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Complete guide to hub ' . $h,
				'post_name'    => 'bulk-pillar-' . $h,
				'post_content' => nh_bulk_content( $h ),
			)
		);
		update_term_meta( $term_id, 'nh_pillar_id', $page_id );
	}

	WP_CLI::log( "Articles ($post_total) by multi-row SQL" );
	$first_id = (int) $wpdb->get_var( "SELECT COALESCE(MAX(ID), 0) + 1 FROM {$wpdb->posts}" );
	$site     = untrailingslashit( home_url() );
	$now      = time();
	$span     = 3 * YEAR_IN_SECONDS;
	$leafs    = count( $leaf_tt );
	$tags     = count( $tag_tt );
	$batch    = 200;

	for ( $offset = 0; $offset < $post_total; $offset += $batch ) {
		$post_rows = array();
		$rel_rows  = array();
		$meta_rows = array();
		$limit     = min( $offset + $batch, $post_total );
		for ( $n = $offset; $n < $limit; $n++ ) {
			$id     = $first_id + $n;
			$author = $authors[ $n % 5 ];
			$time   = $now - (int) ( ( $n / $post_total ) * $span ) - mt_rand( 0, 3600 );
			$date   = gmdate( 'Y-m-d H:i:s', $time );
			$title  = ucfirst( nh_bulk_sentence( mt_rand( 4, 9 ) ) );
			$slug   = sanitize_title( $n . '-' . $title );

			$post_rows[] = $wpdb->prepare(
				'(%d,%d,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%d,%s,%d,%s,%s,%d)',
				$id,
				$author,
				$date,
				$date,
				nh_bulk_content( $n ),
				$title,
				ucfirst( nh_bulk_sentence( 22 ) ) . '.',
				'publish',
				'open',
				'open',
				'',
				$slug,
				'',
				'',
				$date,
				$date,
				'',
				0,
				$site . '/?p=' . $id,
				0,
				'post',
				'',
				0
			);

			$leaf       = $leaf_tt[ $n % $leafs ];
			$rel_rows[] = $wpdb->prepare( '(%d,%d,0)', $id, $leaf );
			if ( 0 === $n % 7 ) {
				$other = $leaf_tt[ ( $n * 3 + 1 ) % $leafs ];
				if ( $other !== $leaf ) {
					$rel_rows[] = $wpdb->prepare( '(%d,%d,0)', $id, $other );
				}
			}
			$used = array();
			for ( $k = 0, $tag_count = mt_rand( 0, 3 ); $k < $tag_count; $k++ ) {
				$tag_id = $tag_tt[ mt_rand( 0, $tags - 1 ) ];
				if ( ! isset( $used[ $tag_id ] ) ) {
					$used[ $tag_id ] = true;
					$rel_rows[]      = $wpdb->prepare( '(%d,%d,0)', $id, $tag_id );
				}
			}

			if ( mt_rand( 1, 100 ) <= 8 ) {
				$meta_rows[] = $wpdb->prepare( '(%d,%s,%s)', $id, 'nh_summary', 'The short answer for article ' . $n . ': ' . nh_bulk_sentence( 14 ) . '.' );
				$meta_rows[] = $wpdb->prepare(
					'(%d,%s,%s)',
					$id,
					'nh_faq',
					wp_json_encode(
						array(
							array(
								'question' => 'Is ' . nh_bulk_sentence( 2 ) . ' simple?',
								'answer'   => '<p>' . nh_bulk_sentence( 18 ) . '.</p>',
							),
						)
					)
				);
			}
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( "INSERT INTO {$wpdb->posts} (ID,post_author,post_date,post_date_gmt,post_content,post_title,post_excerpt,post_status,comment_status,ping_status,post_password,post_name,to_ping,pinged,post_modified,post_modified_gmt,post_content_filtered,post_parent,guid,menu_order,post_type,post_mime_type,comment_count) VALUES " . implode( ',', $post_rows ) );
		$wpdb->query( "INSERT INTO {$wpdb->term_relationships} (object_id,term_taxonomy_id,term_order) VALUES " . implode( ',', $rel_rows ) );
		if ( ! empty( $meta_rows ) ) {
			$wpdb->query( "INSERT INTO {$wpdb->postmeta} (post_id,meta_key,meta_value) VALUES " . implode( ',', $meta_rows ) );
		}
		// phpcs:enable

		if ( 0 === ( $offset / $batch ) % 10 ) {
			WP_CLI::log( '  ' . $limit . ' / ' . $post_total );
		}
	}

	WP_CLI::log( 'Sticky posts, term counts, caches' );
	update_option( 'sticky_posts', array( $first_id, $first_id + 1 ) );

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	$wpdb->query(
		"UPDATE {$wpdb->term_taxonomy} tt SET tt.count = (
			SELECT COUNT(*) FROM {$wpdb->term_relationships} tr
			INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id
			WHERE tr.term_taxonomy_id = tt.term_taxonomy_id AND p.post_status = 'publish' AND p.post_type = 'post'
		) WHERE tt.taxonomy IN ('category','post_tag')"
	);
	// phpcs:enable

	wp_suspend_cache_invalidation( false );
	wp_defer_term_counting( false );

	// Terms were created with invalidation suspended: rebuild the stored hierarchy so that category
	// archives include the posts of their child categories.
	delete_option( 'category_children' );
	delete_option( 'post_tag_children' );
	wp_cache_flush();
	if ( function_exists( 'nh_editorial_cache_bump_now' ) ) {
		nh_editorial_cache_bump_now();
	}

	WP_CLI::success( sprintf( 'Seeded %d articles, %d hubs with %d children each, %d tags. First post ID %d.', $post_total, $hub_total, $kid_total, $tag_total, $first_id ) );
}

nh_bulk_seed();
