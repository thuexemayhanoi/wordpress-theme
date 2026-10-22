<?php
/**
 * Performance utilities: image attributes, LCP handling, core asset trimming.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

// Print core block CSS only for blocks that are actually on the page (supported since WordPress 5.8).
add_filter( 'should_load_separate_core_block_assets', '__return_true' );

/**
 * Whether the next large image may claim the LCP slot (eager, high priority). Only the first caller
 * for a given main query gets true, so exactly one image per page is prioritised and everything else
 * stays lazy. The claim belongs to the main query object, so a long-running PHP process (or a test)
 * that builds a new main query per page starts with a free slot.
 *
 * @return bool
 */
function nh_editorial_claim_lcp() {
	static $claimed_by = null;

	$query = isset( $GLOBALS['wp_the_query'] ) ? $GLOBALS['wp_the_query'] : false;
	if ( $claimed_by === $query ) {
		return false;
	}
	$claimed_by = $query;
	return true;
}

/**
 * Image presets per layout context: WordPress size, the "sizes" attribute that matches the CSS layout,
 * and whether the image is a candidate for the Largest Contentful Paint.
 *
 * No custom image sizes are registered on purpose: existing uploads keep working without regenerating
 * thumbnails and uploads do not create extra files.
 *
 * @return array<string, array{size: string, sizes: string, lcp: bool}>
 */
function nh_editorial_image_contexts() {
	return array(
		'hero'     => array(
			'size'  => 'large',
			'sizes' => '(min-width: 1100px) 740px, 100vw',
			'lcp'   => true,
		),
		'featured' => array(
			'size'  => 'large',
			'sizes' => '(min-width: 1100px) 1000px, 100vw',
			'lcp'   => true,
		),
		'card'     => array(
			'size'  => 'medium_large',
			'sizes' => '(min-width: 1100px) 360px, (min-width: 640px) 45vw, 92vw',
			'lcp'   => false,
		),
		'thumb'    => array(
			'size'  => 'thumbnail',
			'sizes' => '112px',
			'lcp'   => false,
		),
	);
}

/**
 * Attributes for a post thumbnail in a given context.
 *
 * @param string    $context  hero | featured | card | thumb.
 * @param bool|null $priority True asks for the LCP slot (eager, high priority) regardless of context,
 *                            false never does, null follows the context preset.
 * @return array<string, string>
 */
function nh_editorial_image_attributes( $context, $priority = null ) {
	$contexts = nh_editorial_image_contexts();
	$preset   = isset( $contexts[ $context ] ) ? $contexts[ $context ] : $contexts['card'];

	$attributes = array(
		'sizes'    => $preset['sizes'],
		'decoding' => 'async',
	);

	$wants_lcp = null === $priority ? $preset['lcp'] : (bool) $priority;
	if ( $wants_lcp && nh_editorial_claim_lcp() ) {
		$attributes['loading']       = 'eager';
		$attributes['fetchpriority'] = 'high';
	} else {
		$attributes['loading'] = 'lazy';
	}

	/**
	 * Filters thumbnail attributes for a context.
	 *
	 * @param array  $attributes Attributes.
	 * @param string $context    Context.
	 */
	return apply_filters( 'nh_editorial_image_attributes', $attributes, $context );
}

/**
 * Responsive thumbnail HTML for a post, or '' when it has none.
 *
 * Width and height come from the attachment so the browser reserves the space (no layout shift).
 *
 * @param int       $post_id  Post ID.
 * @param string    $context  hero | featured | card | thumb.
 * @param bool|null $priority See nh_editorial_image_attributes().
 * @return string Safe HTML from core.
 */
/**
 * An editorial image for EN homepage cards that do not yet have a post thumbnail.
 *
 * This is display-only: no _thumbnail_id is written, no misleading featured-image
 * metadata is added and uploads can replace it normally. The library IDs refer
 * to the site's previously uploaded lightweight English WebP illustrations.
 * Missing/deleted media falls back to the existing inline SVG.
 *
 * @param int $post_id Article ID.
 * @return int Attachment ID, or zero when unavailable/not applicable.
 */
function nh_editorial_en_home_card_fallback_id( $post_id ) {
	if ( ! is_front_page() && ! is_home() ) {
		return 0;
	}

	$path = trim( (string) wp_parse_url( (string) get_permalink( $post_id ), PHP_URL_PATH ), '/' );
	if ( 0 !== strpos( $path, 'en/' ) ) {
		return 0;
	}

	$title = strtolower( wp_strip_all_tags( (string) get_the_title( $post_id ) ) );
	// Ordered from specific subjects to broad topics; matching is deterministic.
	$rules = array(
		'helmet'        => 3472,
		'protective'    => 3472,
		'phone mount'   => 3472,
		'brake light'   => 3481,
		'indicator'     => 3481,
		'traffic-light' => 3481,
		'traffic light' => 3481,
		'road sign'     => 3482,
		'lane marking'  => 3479,
		'roundabout'    => 3473,
		'u-turn'        => 3473,
		'left turn'     => 3468,
		'right turn'    => 3483,
		'intersection'  => 3483,
		'tyre'          => 3470,
		'braking'       => 3468,
		'parking'       => 3470,
		'parked'        => 3470,
		'rolling'       => 3470,
		'checklist'     => 3470,
		'handover'      => 3477,
		'passenger'     => 3474,
		'pedestrian'    => 3474,
		'bus'           => 3474,
		'monthly'       => 3476,
		'weekly'        => 3476,
		'payment'       => 3484,
		'receipt'       => 3469,
		'price'         => 3480,
		'deposit'       => 3480,
		'contract'      => 3476,
		'rain'          => 3478,
		'flood'         => 3478,
		'traffic'       => 3478,
		'countryside'   => 3471,
		'day trip'      => 3471,
		'heat'          => 3471,
		'fatigue'       => 3471,
		'fatigued'      => 3471,
		'navigation'    => 3475,
		'route'         => 3475,
	);

	$attachment_id = 3474; // General city-riding illustration.
	foreach ( $rules as $phrase => $media_id ) {
		if ( false !== strpos( $title, $phrase ) ) {
			$attachment_id = $media_id;
			break;
		}
	}

	return wp_attachment_is_image( $attachment_id ) ? $attachment_id : 0;
}

/**
 * Responsive post thumbnail, with a temporary English-homepage editorial image
 * when the post has not been assigned a dedicated featured image yet.
 *
 * @param int       $post_id  Post ID.
 * @param string    $context  hero | featured | card | thumb.
 * @param bool|null $priority Whether to claim the single LCP slot.
 * @return string Core-escaped HTML or an empty string.
 */
function nh_editorial_thumbnail( $post_id, $context = 'card', $priority = null ) {
	$contexts   = nh_editorial_image_contexts();
	$size       = isset( $contexts[ $context ] ) ? $contexts[ $context ]['size'] : 'medium_large';
	$attributes = nh_editorial_image_attributes( $context, $priority );

	if ( has_post_thumbnail( $post_id ) ) {
		return get_the_post_thumbnail( $post_id, $size, $attributes );
	}

	$attachment_id = nh_editorial_en_home_card_fallback_id( $post_id );
	if ( ! $attachment_id ) {
		return '';
	}

	$attributes['class'] = 'nh-editorial-home-fallback';
	$attributes['alt']   = sprintf( 'Editorial illustration for %s', wp_strip_all_tags( (string) get_the_title( $post_id ) ) );
	return wp_get_attachment_image( $attachment_id, $size, false, $attributes );
}

/**
 * Avatars (comments, author box and author page) are small and secondary: never eager, never high
 * priority. WordPress would leave the first few images of a page eager, which in a short article means
 * the comment avatars.
 *
 * @param array  $attributes Loading optimization attributes.
 * @param string $tag_name   Tag name.
 * @param array  $attr       Image attributes.
 * @param string $context    Where the image is printed.
 * @return array
 */
function nh_editorial_lazy_avatars( $attributes, $tag_name, $attr, $context ) {
	if ( 'img' === $tag_name && 'get_avatar' === $context && ! is_admin() ) {
		$attributes['loading'] = 'lazy';
		unset( $attributes['fetchpriority'] );
	}
	return $attributes;
}
add_filter( 'wp_get_loading_optimization_attributes', 'nh_editorial_lazy_avatars', 10, 4 );

/**
 * Published articles of an author, counted with a plain COUNT and kept in the fragment cache.
 *
 * @param int $author_id Author ID.
 * @return int
 */
function nh_editorial_author_article_count( $author_id ) {
	$author_id = absint( $author_id );

	return (int) nh_editorial_cache_remember(
		'author-count:' . $author_id,
		static function () use ( $author_id ) {
			return (int) count_user_posts( $author_id, 'post', true );
		}
	);
}

/**
 * Whether the main query is a plain author archive that the fast counter may serve.
 *
 * Anything that narrows the query (taxonomy, meta, date, search, extra authors, other post types)
 * keeps the core behavior, so the counter can never disagree with the query it replaces.
 *
 * @param WP_Query $query Main query.
 * @return bool
 */
function nh_editorial_is_plain_author_archive( $query ) {
	if ( is_admin() || is_user_logged_in() || ! $query->is_main_query() || ! $query->is_author() ) {
		return false;
	}
	if ( ! in_array( (string) $query->get( 'post_type' ), array( '', 'post' ), true ) || '' !== (string) $query->get( 'post_status' ) ) {
		return false;
	}
	foreach ( array( 's', 'cat', 'category_name', 'category__in', 'category__and', 'tag', 'tag_id', 'tag__in', 'tag__and', 'tax_query', 'meta_key', 'meta_value', 'meta_query', 'date_query', 'year', 'monthnum', 'day', 'm', 'w', 'post__in', 'post__not_in', 'post_parent', 'author__in', 'author__not_in', 'has_password' ) as $var ) {
		$value = $query->get( $var );
		if ( ! empty( $value ) ) {
			return false;
		}
	}
	return true;
}

/**
 * Author archives: skip SQL_CALC_FOUND_ROWS.
 *
 * MySQL has no index that serves "this author's articles, newest first", so SQL_CALC_FOUND_ROWS must
 * test every article of the author: 0.6 s for 10,000 articles by one author on a 50,000 article site,
 * against 2 ms for the page itself and 2 ms for a COUNT. The total is read from the fragment cache
 * (invalidated on publish, unpublish and delete) once the page's rows are known.
 *
 * @param WP_Query $query Query.
 * @return void
 */
function nh_editorial_author_archive_query( $query ) {
	if ( nh_editorial_is_plain_author_archive( $query ) && nh_editorial_cache_enabled() ) {
		$query->set( 'no_found_rows', true );
		$query->set( 'nh_counted_author', true );
	}
}
add_action( 'pre_get_posts', 'nh_editorial_author_archive_query' );

/**
 * Supply the pagination totals of an author archive that was queried without SQL_CALC_FOUND_ROWS.
 *
 * @param WP_Post[] $posts Posts of the page.
 * @param WP_Query  $query Query.
 * @return WP_Post[]
 */
function nh_editorial_author_archive_totals( $posts, $query ) {
	if ( ! $query->get( 'nh_counted_author' ) ) {
		return $posts;
	}

	$author_id = (int) $query->get( 'author' );
	$per_page  = (int) $query->get( 'posts_per_page' );
	// The author comes from the URL: counting (and caching a count) for a user that does not exist would let any
	// visitor write one cache row per number they request.
	if ( $author_id < 1 || $per_page < 1 || ! get_userdata( $author_id ) ) {
		return $posts;
	}

	$total                = nh_editorial_author_article_count( $author_id );
	$query->found_posts   = $total;
	$query->max_num_pages = (int) ceil( $total / $per_page );
	return $posts;
}
add_filter( 'the_posts', 'nh_editorial_author_archive_totals', 10, 2 );

/**
 * Whether the number of published articles is too large for the built-in search to answer a request
 * per keystroke.
 *
 * WordPress searches titles, excerpts and bodies with LIKE '%word%', which reads every article:
 * 60 ms at 10,000 articles, 2 to 3.5 s at 50,000. The companion plugin's search index removes that
 * cost; without it the suggestions are switched off on large sites and the normal results page
 * remains available.
 *
 * @return bool
 */
function nh_editorial_search_is_too_heavy_for_suggestions() {
	/**
	 * Filters the number of published articles above which the built-in search is considered too slow
	 * for suggestions while typing. Zero disables the guard.
	 *
	 * @param int $limit Article count.
	 */
	$limit = (int) apply_filters( 'nh_editorial_suggest_article_limit', 15000 );
	if ( $limit < 1 ) {
		return false;
	}

	/**
	 * Filters whether a fast search index (the companion plugin's) is serving the search.
	 *
	 * @param bool $active Whether an index is active.
	 */
	if ( apply_filters( 'nh_editorial_search_index_active', false ) ) {
		return false;
	}

	$published = (int) nh_editorial_cache_remember(
		'published-articles',
		static function () {
			return (int) wp_count_posts( 'post' )->publish;
		}
	);
	return $published > $limit;
}
