<?php
/**
 * Related articles: same Topic Cluster first, then the same category, then the parent and top-level
 * categories only when more are needed.
 *
 * Published posts only (never drafts, private, trashed or password-protected posts), never the current
 * post, newest first inside each stage. The resulting ID list is cached in the versioned fragment cache
 * and refreshed automatically when anything is published, unpublished or re-categorised.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

/**
 * Number of related articles to show.
 *
 * @return int
 */
function nh_editorial_related_count() {
	$count = (int) nh_editorial_option( 'nh_related_count' );

	/**
	 * Filters the number of related articles (3 by default).
	 *
	 * @param int $count Number of articles.
	 */
	return max( 1, min( 12, (int) apply_filters( 'nh_editorial_related_count', $count ) ) );
}

/**
 * Category query fragment.
 *
 * @param int  $term_id  Category ID.
 * @param bool $children Whether descendants count.
 * @return array
 */
function nh_editorial_category_stage( $term_id, $children = true ) {
	return array(
		'tax_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Indexed term lookup; results are cached.
			array(
				'taxonomy'         => 'category',
				'field'            => 'term_id',
				'terms'            => array( (int) $term_id ),
				'include_children' => (bool) $children,
			),
		),
	);
}

/**
 * Ordered query stages for a post: most specific first.
 *
 * @param int $post_id Post ID.
 * @return array<int, array> Query fragments.
 */
function nh_editorial_related_stages( $post_id ) {
	$stages = array();
	$silo   = nh_editorial_get_silo( $post_id );

	// A real cluster (taxonomy or workflow alias) comes first; a category silo is handled below.
	if ( $silo && ( 'meta' === $silo['type'] || ( $silo['term'] && 'category' !== $silo['term']->taxonomy ) ) ) {
		$stages[] = nh_editorial_silo_query_args( $silo );
	}

	$category = nh_editorial_primary_category( $post_id );
	if ( $category ) {
		$seen     = array( (int) $category->term_id );
		$stages[] = nh_editorial_category_stage( $category->term_id );

		if ( $category->parent && ! in_array( (int) $category->parent, $seen, true ) ) {
			$seen[]   = (int) $category->parent;
			$stages[] = nh_editorial_category_stage( $category->parent );
		}

		$top = nh_editorial_top_category( $category );
		if ( ! in_array( (int) $top->term_id, $seen, true ) ) {
			$stages[] = nh_editorial_category_stage( $top->term_id );
		}
	}

	return $stages;
}

/**
 * IDs of related articles for a post.
 *
 * @param int $post_id Post ID (defaults to the current post).
 * @param int $count   Number of IDs (defaults to the Customizer setting).
 * @return int[]
 */
function nh_editorial_get_related_ids( $post_id = 0, $count = 0 ) {
	$post_id = $post_id ? absint( $post_id ) : (int) get_the_ID();
	$count   = $count ? max( 1, absint( $count ) ) : nh_editorial_related_count();
	if ( ! $post_id ) {
		return array();
	}

	return nh_editorial_cache_remember(
		'related:' . $post_id . ':' . $count,
		static function () use ( $post_id, $count ) {
			$found = array();

			foreach ( nh_editorial_related_stages( $post_id ) as $stage ) {
				$needed = $count - count( $found );
				if ( $needed <= 0 ) {
					break;
				}

				$ids = nh_editorial_query_public_ids(
					array_merge(
						array( 'post__not_in' => array_merge( array( $post_id ), $found ) ),
						$stage
					),
					$needed
				);

				foreach ( $ids as $id ) {
					$found[] = (int) $id;
				}
			}

			return array_slice( $found, 0, $count );
		}
	);
}
