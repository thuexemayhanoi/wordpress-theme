<?php
/**
 * Works out which public URLs a content change makes stale.
 *
 * For a post: its own URL, the home page, the posts page, the archive of every public taxonomy term it has
 * (category archives include their ancestors), the author archive, the Pillar page that links to it and the
 * previous / next neighbours inside its silo (their navigation shows this post's title and link).
 *
 * Pure reading: nothing is written and no request is made. The Pillar and silo rules are those of
 * docs/DATA-CONTRACT.md section 3.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * URL resolution.
 */
final class NH_SEO_Cache_Urls {

	/**
	 * Every URL affected by a change of a post, from the current database state.
	 *
	 * The post's own URL is included only while the post is published: the old URL of a post that was just
	 * unpublished or renamed is captured earlier, before the change (see NH_SEO_Cache).
	 *
	 * @param WP_Post|int $post Post or ID.
	 * @return string[]
	 */
	public static function for_post( $post ) {
		$post = get_post( $post );
		if ( ! $post instanceof WP_Post || ! is_post_type_viewable( $post->post_type ) || 'auto-draft' === $post->post_status || 'revision' === $post->post_type ) {
			return array();
		}

		$urls = array();
		if ( 'publish' === $post->post_status ) {
			$urls[] = get_permalink( $post );
		}

		$urls   = array_merge( $urls, self::home_urls() );
		$object = get_post_type_object( $post->post_type );
		if ( $object && $object->has_archive ) {
			$urls[] = get_post_type_archive_link( $post->post_type );
		}

		foreach ( get_object_taxonomies( $post->post_type ) as $taxonomy ) {
			if ( ! is_taxonomy_viewable( $taxonomy ) ) {
				continue;
			}
			$terms = get_the_terms( $post, $taxonomy );
			if ( empty( $terms ) || is_wp_error( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$urls = array_merge( $urls, self::for_term( $term ) );
			}
		}

		if ( 'post' === $post->post_type ) {
			$urls[] = get_author_posts_url( (int) $post->post_author );

			$pillar = self::pillar( $post );
			if ( $pillar instanceof WP_Post ) {
				$urls[] = get_permalink( $pillar );
			}
			foreach ( self::neighbour_ids( $post ) as $neighbour_id ) {
				$urls[] = get_permalink( $neighbour_id );
			}
		}

		/**
		 * Filters the URLs a post change makes stale (add date archives, feeds or theme specific pages).
		 *
		 * @param string[] $urls URLs.
		 * @param WP_Post  $post The post.
		 */
		$urls = (array) apply_filters( 'nh_seo_purge_post_urls', $urls, $post );

		return self::clean( $urls );
	}

	/**
	 * The home page and, when one is configured, the posts page.
	 *
	 * @return string[]
	 */
	public static function home_urls() {
		$urls = array( home_url( '/' ) );
		if ( 'page' === get_option( 'show_on_front' ) ) {
			$posts_page = (int) get_option( 'page_for_posts' );
			if ( $posts_page && 'publish' === get_post_status( $posts_page ) ) {
				$urls[] = get_permalink( $posts_page );
			}
		}
		return $urls;
	}

	/**
	 * The archive URL of a term, plus the archives of its ancestors when the taxonomy is hierarchical.
	 *
	 * @param WP_Term|int|string $term     Term object or ID.
	 * @param string             $taxonomy Taxonomy when $term is not an object.
	 * @return string[]
	 */
	public static function for_term( $term, $taxonomy = '' ) {
		$term = $term instanceof WP_Term ? $term : get_term( $term, $taxonomy );
		if ( ! $term instanceof WP_Term || ! is_taxonomy_viewable( $term->taxonomy ) ) {
			return array();
		}

		$urls = array();
		$link = get_term_link( $term );
		if ( ! is_wp_error( $link ) ) {
			$urls[] = $link;
		}
		if ( is_taxonomy_hierarchical( $term->taxonomy ) ) {
			foreach ( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) as $ancestor_id ) {
				$ancestor_link = get_term_link( (int) $ancestor_id, $term->taxonomy );
				if ( ! is_wp_error( $ancestor_link ) ) {
					$urls[] = $ancestor_link;
				}
			}
		}
		return $urls;
	}

	/**
	 * Silo term of a post: the primary topic cluster when there is one, otherwise the primary category.
	 *
	 * @param WP_Post $post Post.
	 * @return WP_Term|null
	 */
	public static function silo_term( WP_Post $post ) {
		$cluster = self::primary_cluster( $post );
		return $cluster instanceof WP_Term ? $cluster : nh_seo_get_primary_category( $post );
	}

	/**
	 * Primary topic cluster of a post (docs/DATA-CONTRACT.md section 2).
	 *
	 * The data module of the plugin supplies nh_seo_get_primary_cluster(); the same rule is applied here only
	 * when that function is not loaded: the cluster named by nh_primary_cluster when it is assigned to the
	 * post, otherwise the assigned cluster with the lowest term ID.
	 *
	 * @param WP_Post $post Post.
	 * @return WP_Term|null
	 */
	public static function primary_cluster( WP_Post $post ) {
		if ( function_exists( 'nh_seo_get_primary_cluster' ) ) {
			$cluster = nh_seo_get_primary_cluster( $post->ID );
			return $cluster instanceof WP_Term ? $cluster : null;
		}

		$terms = get_the_terms( $post, NH_SEO_Keys::TAXONOMY_CLUSTER );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return null;
		}
		$by_id = array();
		foreach ( $terms as $term ) {
			$by_id[ (int) $term->term_id ] = $term;
		}
		$chosen = (int) get_post_meta( $post->ID, NH_SEO_Keys::META_PRIMARY_CLUSTER, true );
		if ( $chosen && isset( $by_id[ $chosen ] ) ) {
			return $by_id[ $chosen ];
		}
		ksort( $by_id );
		return reset( $by_id );
	}

	/**
	 * The Pillar page of a post (docs/DATA-CONTRACT.md section 3): the post's own mapping, then the primary
	 * cluster's, then the primary category's, then its ancestors' (nearest first). The target must be
	 * published, public and not the post itself.
	 *
	 * @param WP_Post $post Post.
	 * @return WP_Post|null
	 */
	public static function pillar( WP_Post $post ) {
		$candidates = array( (int) get_post_meta( $post->ID, NH_SEO_Keys::META_PILLAR, true ) );

		$cluster = self::primary_cluster( $post );
		if ( $cluster instanceof WP_Term ) {
			$candidates[] = (int) get_term_meta( $cluster->term_id, NH_SEO_Keys::TERM_PILLAR, true );
		}

		$category = nh_seo_get_primary_category( $post );
		if ( $category instanceof WP_Term ) {
			$candidates[] = (int) get_term_meta( $category->term_id, NH_SEO_Keys::TERM_PILLAR, true );
			foreach ( get_ancestors( $category->term_id, 'category', 'taxonomy' ) as $ancestor_id ) {
				$candidates[] = (int) get_term_meta( (int) $ancestor_id, NH_SEO_Keys::TERM_PILLAR, true );
			}
		}

		foreach ( $candidates as $candidate_id ) {
			if ( $candidate_id && $candidate_id !== (int) $post->ID && nh_seo_is_public_post( $candidate_id ) ) {
				return get_post( $candidate_id );
			}
		}
		return null;
	}

	/**
	 * IDs of the posts right before and right after a post, by publish date (ties by ID), inside its silo term.
	 * Only published, public posts of the same type count; the post itself never does.
	 *
	 * @param WP_Post $post Post.
	 * @return int[] Zero, one or two IDs: previous first.
	 */
	public static function neighbour_ids( WP_Post $post ) {
		global $wpdb;

		$silo = self::silo_term( $post );
		if ( ! $silo instanceof WP_Term ) {
			return array();
		}

		$ids = array();

		// Reads WordPress's own tables by indexed columns, LIMIT 1, at most once per changed post.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$previous = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} AS p INNER JOIN {$wpdb->term_relationships} AS tr ON tr.object_id = p.ID WHERE tr.term_taxonomy_id = %d AND p.post_type = %s AND p.post_status = 'publish' AND p.post_password = '' AND p.ID <> %d AND ( p.post_date < %s OR ( p.post_date = %s AND p.ID < %d ) ) ORDER BY p.post_date DESC, p.ID DESC LIMIT 1",
				$silo->term_taxonomy_id,
				$post->post_type,
				$post->ID,
				$post->post_date,
				$post->post_date,
				$post->ID
			)
		);
		$next     = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} AS p INNER JOIN {$wpdb->term_relationships} AS tr ON tr.object_id = p.ID WHERE tr.term_taxonomy_id = %d AND p.post_type = %s AND p.post_status = 'publish' AND p.post_password = '' AND p.ID <> %d AND ( p.post_date > %s OR ( p.post_date = %s AND p.ID > %d ) ) ORDER BY p.post_date ASC, p.ID ASC LIMIT 1",
				$silo->term_taxonomy_id,
				$post->post_type,
				$post->ID,
				$post->post_date,
				$post->post_date,
				$post->ID
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		foreach ( array( $previous, $next ) as $found ) {
			if ( $found ) {
				$ids[] = (int) $found;
			}
		}
		return $ids;
	}

	/**
	 * Keep real http(s) URLs once each, in order.
	 *
	 * @param array<int, mixed> $urls URLs.
	 * @return string[]
	 */
	public static function clean( array $urls ) {
		$clean = array();
		foreach ( $urls as $url ) {
			if ( ! is_string( $url ) || '' === $url ) {
				continue;
			}
			$url = esc_url_raw( $url, array( 'http', 'https' ) );
			// Relative paths would mean different pages to different caches: absolute URLs only.
			if ( 1 === preg_match( '#^https?://#i', $url ) ) {
				$clean[ $url ] = $url;
			}
		}
		return array_values( $clean );
	}
}
