<?php
/**
 * Explicit one-to-one VI/EN relationships, stored in plugin-owned options.
 * No changes to existing posts, post meta, slugs, or SEO metadata.
 *
 * @package Nguyen_Ha_VI_EN
 */

defined( 'ABSPATH' ) || exit;

/**
 * Validate and manage one-to-one translation relationships.
 */
final class NH_VIEN_Links {
	const OPTION       = 'nh_vien_translation_pairs_v1';
	const NONCE_ACTION = 'nh_vien_manage_links';

	/**
	 * What this request has read: the validated pairs and the English posts that may be shown.
	 *
	 * @var array<string,mixed>
	 */
	private static $memo = array();

	/** Forget what this request has read, so that the next lookup reads the store again. */
	public static function flush_cache() {
		self::$memo = array();
	}

	/**
	 * Read validated Vietnamese-post-ID => English-post-ID relationships.
	 * Malformed or ambiguous maps are never overwritten automatically.
	 *
	 * @return array<int,int>|WP_Error
	 */
	public static function all() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			return new WP_Error( 'nh_vien_invalid_store', 'Translation map is not an array.' );
		}
		$pairs = array();
		$seen  = array();
		foreach ( $stored as $vi => $en ) {
			if ( ! self::positive_id( $vi ) || ! self::positive_id( $en ) ) {
				return new WP_Error( 'nh_vien_invalid_store', 'Invalid ID in translation map.' );
			}
			$vi = (int) $vi;
			$en = (int) $en;
			if ( $vi === $en || isset( $seen[ $en ] ) ) {
				return new WP_Error( 'nh_vien_invalid_store', 'Duplicate or self-linked translation.' );
			}
			$pairs[ $vi ] = $en;
			$seen[ $en ]  = true;
		}
		// Reject a single post used as both a Vietnamese and English entry.
		foreach ( $pairs as $vi => $en ) {
			if ( isset( $pairs[ $en ] ) || isset( $seen[ $vi ] ) ) {
				return new WP_Error( 'nh_vien_invalid_store', 'A post is used in both languages.' );
			}
		}
		return $pairs;
	}

	/**
	 * Resolve a stored pair in either direction.
	 *
	 * @param mixed $post_id WordPress post ID.
	 * @return array<string,int>|null
	 */
	public static function for_post( $post_id ) {
		if ( ! self::positive_id( $post_id ) ) {
			return null;
		}
		$pairs = self::all();
		if ( is_wp_error( $pairs ) ) {
			return null;
		}
		$post_id = (int) $post_id;
		foreach ( $pairs as $vi => $en ) {
			if ( $post_id === $vi || $post_id === $en ) {
				if ( ! self::eligible_post( $vi ) || ! self::eligible_post( $en ) ) {
					return null;
				}
				return array(
					'vi' => $vi,
					'en' => $en,
				);
			}
		}
		return null;
	}

	/**
	 * Explicit administrator-authorized link. Refuses implicit relinking.
	 *
	 * @param mixed $vi Vietnamese post ID.
	 * @param mixed $en English post ID.
	 * @param mixed $nonce Nonce for this operation.
	 * @return true|WP_Error
	 */
	public static function link( $vi, $en, $nonce ) {
		$security = self::authorize( $nonce );
		if ( is_wp_error( $security ) ) {
			return $security;
		}
		if ( ! self::positive_id( $vi ) || ! self::positive_id( $en ) || (int) $vi === (int) $en ) {
			return new WP_Error( 'nh_vien_invalid_ids', 'Specify two different positive post IDs.' );
		}
		$vi = (int) $vi;
		$en = (int) $en;
		if ( ! self::eligible_post( $vi ) || ! self::eligible_post( $en ) ) {
			return new WP_Error( 'nh_vien_invalid_posts', 'Both IDs must be existing non-trashed WordPress posts.' );
		}
		if ( ! current_user_can( 'edit_post', $vi ) || ! current_user_can( 'edit_post', $en ) ) {
			return new WP_Error( 'nh_vien_forbidden', 'You must be allowed to edit both posts.' );
		}
		$pairs = self::all();
		if ( is_wp_error( $pairs ) ) {
			return $pairs;
		}
		if ( isset( $pairs[ $vi ] ) ) {
			return $pairs[ $vi ] === $en ? true : new WP_Error( 'nh_vien_conflict', 'The Vietnamese post is already linked.' );
		}
		if ( isset( $pairs[ $en ] ) || in_array( $vi, $pairs, true ) || in_array( $en, $pairs, true ) ) {
			return new WP_Error( 'nh_vien_conflict', 'One of the posts is already linked.' );
		}
		$pairs[ $vi ] = $en;
		$saved        = update_option( self::OPTION, $pairs, false );
		self::flush_cache();
		if ( ! $saved ) {
			return new WP_Error( 'nh_vien_save_failed', 'Could not save translation link.' );
		}
		do_action( 'nh_vien_pairs_changed', $vi, $en );
		return true;
	}

	/**
	 * Explicit administrator-authorized unlink; no content deletion.
	 *
	 * @param mixed $vi Vietnamese post ID.
	 * @param mixed $nonce Nonce for this operation.
	 * @return true|WP_Error
	 */
	public static function unlink( $vi, $nonce ) {
		$security = self::authorize( $nonce );
		if ( is_wp_error( $security ) ) {
			return $security;
		}
		if ( ! self::positive_id( $vi ) ) {
			return new WP_Error( 'nh_vien_invalid_ids', 'Invalid Vietnamese post ID.' );
		}
		$vi    = (int) $vi;
		$pairs = self::all();
		if ( is_wp_error( $pairs ) ) {
			return $pairs;
		}
		if ( ! isset( $pairs[ $vi ] ) ) {
			return new WP_Error( 'nh_vien_not_found', 'Translation link was not found.' );
		}
		if ( ! current_user_can( 'edit_post', $vi ) || ! current_user_can( 'edit_post', $pairs[ $vi ] ) ) {
			return new WP_Error( 'nh_vien_forbidden', 'You must be allowed to edit both posts.' );
		}
		$en = $pairs[ $vi ];
		unset( $pairs[ $vi ] );
		$saved = update_option( self::OPTION, $pairs, false );
		self::flush_cache();
		if ( ! $saved ) {
			return new WP_Error( 'nh_vien_save_failed', 'Could not remove translation link.' );
		}
		do_action( 'nh_vien_pairs_changed', $vi, $en );
		return true;
	}

	/**
	 * Vietnamese-post-ID => English-post-ID pairs for this request; an invalid store reads as no pairs at all.
	 *
	 * @return array<int,int>
	 */
	private static function pairs() {
		if ( ! isset( self::$memo['pairs'] ) ) {
			$pairs               = self::all();
			self::$memo['pairs'] = is_wp_error( $pairs ) ? array() : $pairs;
		}
		return self::$memo['pairs'];
	}

	/**
	 * English-post-ID => Vietnamese-post-ID pairs for this request.
	 *
	 * @return array<int,int>
	 */
	private static function reverse() {
		if ( ! isset( self::$memo['reverse'] ) ) {
			self::$memo['reverse'] = array_flip( self::pairs() );
		}
		return self::$memo['reverse'];
	}

	/**
	 * Every post paired as the English side, whatever state the pair is in.
	 *
	 * @return int[]
	 */
	public static function en_ids() {
		return array_keys( self::reverse() );
	}

	/**
	 * Whether a post is the English side of a pair.
	 *
	 * @param mixed $post_id Post ID.
	 * @return bool
	 */
	public static function is_en( $post_id ) {
		$reverse = self::reverse();
		return isset( $reverse[ (int) $post_id ] );
	}

	/**
	 * The language a post belongs to: English when it is the English side of a pair, Vietnamese for every other post.
	 *
	 * @param mixed $post_id Post ID.
	 * @return string 'en' or 'vi'.
	 */
	public static function language_of( $post_id ) {
		return self::is_en( $post_id ) ? 'en' : 'vi';
	}

	/**
	 * The Vietnamese partner of an English post.
	 *
	 * @param mixed $en_id English post ID.
	 * @return int Post ID, or 0 when the post is not paired as English.
	 */
	public static function vi_for_en( $en_id ) {
		$reverse = self::reverse();
		return isset( $reverse[ (int) $en_id ] ) ? $reverse[ (int) $en_id ] : 0;
	}

	/**
	 * The English partner of a Vietnamese post.
	 *
	 * @param mixed $vi_id Vietnamese post ID.
	 * @return int Post ID, or 0 when the post has no English partner.
	 */
	public static function en_for_vi( $vi_id ) {
		$pairs = self::pairs();
		return isset( $pairs[ (int) $vi_id ] ) ? $pairs[ (int) $vi_id ] : 0;
	}

	/**
	 * The English posts that may be shown: the English post and its Vietnamese partner are both published posts, and
	 * the English slug can be routed. Any other English post stays hidden, so nothing English goes public before the
	 * Vietnamese post it belongs to does.
	 *
	 * @return array<int,WP_Post> English post ID => post.
	 */
	public static function public_en_posts() {
		if ( isset( self::$memo['public'] ) ) {
			return self::$memo['public'];
		}
		self::$memo['public'] = array();
		$pairs                = self::pairs();
		if ( ! $pairs ) {
			return array();
		}
		$ids       = array_merge( array_keys( $pairs ), array_values( $pairs ) );
		$published = get_posts(
			array(
				'post_type'              => 'post',
				'post_status'            => 'publish',
				'post__in'               => $ids,
				'posts_per_page'         => count( $ids ),
				'orderby'                => 'none',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'suppress_filters'       => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'nh_vien_scope'          => 'off',
			)
		);
		$by_id     = array();
		foreach ( $published as $post ) {
			$by_id[ (int) $post->ID ] = $post;
		}
		// Only a page below /en/ can take an address away from an English post.
		$pages_below_en = null !== get_page_by_path( 'en', OBJECT, 'page' );
		foreach ( $pairs as $vi => $en ) {
			if ( ! isset( $by_id[ $vi ], $by_id[ $en ] ) || ! NH_VIEN_Routes::slug_is_routable( $by_id[ $en ]->post_name ) ) {
				continue;
			}
			if ( $pages_below_en && null !== NH_VIEN_Routes::page_at( 'en/' . $by_id[ $en ]->post_name ) ) {
				continue;
			}
			self::$memo['public'][ $en ] = $by_id[ $en ];
		}
		return self::$memo['public'];
	}

	/**
	 * IDs of the English posts that may be shown.
	 *
	 * @return int[]
	 */
	public static function public_en_ids() {
		return array_keys( self::public_en_posts() );
	}

	/**
	 * The categories and tags that have an English archive: the terms of the English posts that may be shown, and the
	 * parents of those categories, whose archives list the posts of their children too.
	 *
	 * @return array<int,true> Term ID => true.
	 */
	public static function en_term_ids() {
		if ( isset( self::$memo['terms'] ) ) {
			return self::$memo['terms'];
		}
		self::$memo['terms'] = array();
		$ids                 = self::public_en_ids();
		if ( ! $ids ) {
			return array();
		}
		$term_ids = wp_get_object_terms( $ids, array( 'category', 'post_tag' ), array( 'fields' => 'ids' ) );
		if ( is_wp_error( $term_ids ) ) {
			return array();
		}
		foreach ( $term_ids as $term_id ) {
			self::$memo['terms'][ (int) $term_id ] = true;
			foreach ( get_ancestors( (int) $term_id, 'category', 'taxonomy' ) as $ancestor_id ) {
				self::$memo['terms'][ (int) $ancestor_id ] = true;
			}
		}
		return self::$memo['terms'];
	}

	/**
	 * The categories and tags whose published posts, and those of their child categories, are all English: their
	 * Vietnamese archive would be empty, so Vietnamese pages do not list them.
	 *
	 * @return array<int,true> Term ID => true.
	 */
	public static function en_only_term_ids() {
		if ( isset( self::$memo['en_only'] ) ) {
			return self::$memo['en_only'];
		}
		self::$memo['en_only'] = array();
		$english               = self::en_ids();
		if ( ! $english ) {
			return array();
		}
		$published = get_posts(
			array(
				'post_type'              => 'post',
				'post_status'            => 'publish',
				'post__in'               => $english,
				'posts_per_page'         => count( $english ),
				'fields'                 => 'ids',
				'orderby'                => 'none',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'suppress_filters'       => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'nh_vien_scope'          => 'off',
			)
		);
		if ( ! $published ) {
			return array();
		}
		$rows = wp_get_object_terms( $published, array( 'category', 'post_tag' ), array( 'fields' => 'all_with_object_id' ) );
		if ( is_wp_error( $rows ) ) {
			return array();
		}
		$english_posts = array();
		$terms         = array();
		foreach ( $rows as $term ) {
			$term_id                   = (int) $term->term_id;
			$terms[ $term_id ]         = $term;
			$english_posts[ $term_id ] = isset( $english_posts[ $term_id ] ) ? $english_posts[ $term_id ] + 1 : 1;
		}
		foreach ( $terms as $term_id => $term ) {
			$ids = array( $term_id );
			if ( 'category' === $term->taxonomy ) {
				$ids = array_merge( $ids, array_map( 'intval', get_term_children( $term_id, 'category' ) ) );
			}
			$has_vietnamese = false;
			foreach ( $ids as $id ) {
				// Published posts of the term itself that are not English.
				if ( isset( $terms[ $id ] ) ) {
					$others = (int) $terms[ $id ]->count - $english_posts[ $id ];
				} else {
					$other  = get_term( $id );
					$others = $other instanceof WP_Term ? (int) $other->count : 0;
				}
				if ( $others > 0 ) {
					$has_vietnamese = true;
					break;
				}
			}
			if ( ! $has_vietnamese ) {
				self::$memo['en_only'][ $term_id ] = true;
			}
		}
		return self::$memo['en_only'];
	}

	/**
	 * Whether a post is an English post that may be shown.
	 *
	 * @param mixed $post_id Post ID.
	 * @return bool
	 */
	public static function is_public_en( $post_id ) {
		if ( ! self::is_en( $post_id ) ) {
			return false;
		}
		$public = self::public_en_posts();
		return isset( $public[ (int) $post_id ] );
	}

	/**
	 * Check the administrator capability and request nonce.
	 *
	 * @param mixed $nonce Submitted request token.
	 * @return true|WP_Error Whether authorized.
	 */
	private static function authorize( $nonce ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'nh_vien_forbidden', 'Administrator permission required.' );
		}
		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return new WP_Error( 'nh_vien_bad_nonce', 'Invalid or expired request nonce.' );
		}
		return true;
	}

	/**
	 * Validate an integer or an exact decimal post identifier.
	 *
	 * @param mixed $value Supplied identifier.
	 * @return bool Whether positive and valid.
	 */
	private static function positive_id( $value ) {
		return ( is_int( $value ) && $value > 0 ) ||
			( is_string( $value ) && 1 === preg_match( '/^[1-9][0-9]*$/D', $value ) && (string) (int) $value === $value );
	}

	/**
	 * Check that a post is suitable for manual translation linking.
	 *
	 * @param int $post_id WordPress post identifier.
	 * @return bool Whether eligible.
	 */
	private static function eligible_post( $post_id ) {
		$post = get_post( $post_id );
		return $post instanceof WP_Post && 'post' === $post->post_type &&
			! in_array( $post->post_status, array( 'trash', 'auto-draft', 'inherit' ), true );
	}
}
