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
	const INDEPENDENT_OPTION = 'nh_vien_independent_en_posts_v1';
	const EN_TERMS_OPTION   = 'nh_vien_english_categories_v1';
	const PAGE_OPTION       = 'nh_vien_page_pairs_v1';
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
		if ( in_array( $vi, self::independent_ids(), true ) ) {
			return new WP_Error( 'nh_vien_conflict', 'The Vietnamese post has already been designated as English.' );
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
	 * Explicitly designated English articles. No existing Vietnamese article is modified.
	 *
	 * @return int[] Registered WordPress post IDs.
	 */
	public static function independent_ids() {
		if ( ! isset( self::$memo['independent'] ) ) {
			$stored = get_option( self::INDEPENDENT_OPTION, array() );
			$ids    = is_array( $stored ) ? array_map( 'intval', $stored ) : array();
			$ids    = array_values( array_unique( array_filter( $ids, static function ( $id ) {
				return $id > 0;
			} ) ) );
			self::$memo['independent'] = array_values( array_diff( $ids, array_keys( self::pairs() ) ) );
		}
		return self::$memo['independent'];
	}

	/**
	 * Assign the English language to an article without requiring a Vietnamese counterpart.
	 * Requires a real administrator action; cannot relabel the Vietnamese side of an existing pair.
	 *
	 * @param mixed $post_id Article ID.
	 * @param mixed $nonce Action nonce.
	 * @return true|WP_Error
	 */
	public static function mark_english( $post_id, $nonce ) {
		$security = self::authorize( $nonce );
		if ( is_wp_error( $security ) ) {
			return $security;
		}
		if ( ! self::positive_id( $post_id ) || ! self::eligible_post( (int) $post_id ) ) {
			return new WP_Error( 'nh_vien_invalid_posts', 'Enter an existing WordPress article ID.' );
		}
		$post_id = (int) $post_id;
		if ( ! current_user_can( 'edit_post', $post_id ) || isset( self::pairs()[ $post_id ] ) ) {
			return new WP_Error( 'nh_vien_conflict', 'The article cannot be designated English.' );
		}
		$ids = self::independent_ids();
		if ( in_array( $post_id, $ids, true ) ) {
			return true;
		}
		$ids[] = $post_id;
		if ( ! update_option( self::INDEPENDENT_OPTION, $ids, false ) ) {
			return new WP_Error( 'nh_vien_save_failed', 'Could not save the English article.' );
		}
		self::flush_cache();
		do_action( 'nh_vien_pairs_changed', 0, $post_id );
		return true;
	}

	/**
	 * Remove an independent English designation without deleting or editing the article.
	 *
	 * @param mixed $post_id Article ID.
	 * @param mixed $nonce Action nonce.
	 * @return true|WP_Error
	 */
	public static function unmark_english( $post_id, $nonce ) {
		$security = self::authorize( $nonce );
		if ( is_wp_error( $security ) ) {
			return $security;
		}
		if ( ! self::positive_id( $post_id ) || ! current_user_can( 'edit_post', (int) $post_id ) ) {
			return new WP_Error( 'nh_vien_forbidden', 'You must be allowed to edit this article.' );
		}
		$post_id = (int) $post_id;
		$ids     = self::independent_ids();
		if ( ! in_array( $post_id, $ids, true ) ) {
			return new WP_Error( 'nh_vien_not_found', 'No independent English designation exists.' );
		}
		$ids = array_values( array_diff( $ids, array( $post_id ) ) );
		if ( ! update_option( self::INDEPENDENT_OPTION, $ids, false ) ) {
			return new WP_Error( 'nh_vien_save_failed', 'Could not update the English article list.' );
		}
		self::flush_cache();
		do_action( 'nh_vien_pairs_changed', 0, $post_id );
		return true;
	}


	/**
	 * IDs of newly created categories reserved for the English section.
	 *
	 * @return int[] Explicitly English category IDs, even when empty.
	 */
	public static function declared_en_terms() {
		if ( ! isset( self::$memo['declared_terms'] ) ) {
			$stored = get_option( self::EN_TERMS_OPTION, array() );
			$ids    = is_array( $stored ) ? array_map( 'intval', $stored ) : array();
			self::$memo['declared_terms'] = array_values( array_unique( array_filter( $ids, static function ( $id ) {
				return $id > 0;
			} ) ) );
		}
		return self::$memo['declared_terms'];
	}

	/**
	 * Reserve an empty category for English. A child category can only have an English parent.
	 * Never relabel a populated Vietnamese category or change any term's name, parent or slug.
	 *
	 * @param mixed $term_id Category term ID.
	 * @param mixed $nonce Security nonce.
	 * @return true|WP_Error
	 */
	public static function mark_english_category( $term_id, $nonce ) {
		$security = self::authorize( $nonce );
		if ( is_wp_error( $security ) ) {
			return $security;
		}
		if ( ! self::positive_id( $term_id ) ) {
			return new WP_Error( 'nh_vien_invalid_ids', 'Enter a valid category ID.' );
		}
		$term = get_term( (int) $term_id, 'category' );
		if ( ! $term instanceof WP_Term ) {
			return new WP_Error( 'nh_vien_invalid_category', 'Category does not exist.' );
		}
		$ids = self::declared_en_terms();
		if ( in_array( (int) $term->term_id, $ids, true ) ) {
			return true;
		}
		if ( (int) $term->count > 0 || ( $term->parent && ! in_array( (int) $term->parent, $ids, true ) ) ) {
			return new WP_Error( 'nh_vien_category_conflict', 'Choose an empty category whose parent is also English.' );
		}
		$children = get_term_children( $term->term_id, 'category' );
		if ( is_wp_error( $children ) ) {
			return new WP_Error( 'nh_vien_invalid_category', 'Cannot inspect category children.' );
		}
		foreach ( $children as $child_id ) {
			$child = get_term( (int) $child_id, 'category' );
			if ( $child instanceof WP_Term && (int) $child->count > 0 ) {
				return new WP_Error( 'nh_vien_category_conflict', 'A child category already has articles.' );
			}
		}
		$ids[] = (int) $term->term_id;
		if ( ! update_option( self::EN_TERMS_OPTION, $ids, false ) ) {
			return new WP_Error( 'nh_vien_save_failed', 'Could not register English category.' );
		}
		self::flush_cache();
		do_action( 'nh_vien_pairs_changed', 0, 0 );
		return true;
	}

	/**
	 * Remove an English category designation. Never mutate or delete the WordPress category.
	 *
	 * @param mixed $term_id Category term ID.
	 * @param mixed $nonce Security nonce.
	 * @return true|WP_Error
	 */
	public static function unmark_english_category( $term_id, $nonce ) {
		$security = self::authorize( $nonce );
		if ( is_wp_error( $security ) ) {
			return $security;
		}
		if ( ! self::positive_id( $term_id ) ) {
			return new WP_Error( 'nh_vien_invalid_ids', 'Invalid category ID.' );
		}
		$term_id = (int) $term_id;
		$ids     = self::declared_en_terms();
		if ( ! in_array( $term_id, $ids, true ) ) {
			return new WP_Error( 'nh_vien_not_found', 'Category is not registered as English.' );
		}
		foreach ( $ids as $other_id ) {
			$other = get_term( $other_id, 'category' );
			if ( $other instanceof WP_Term && (int) $other->parent === $term_id ) {
				return new WP_Error( 'nh_vien_category_conflict', 'Unregister English child categories first.' );
			}
		}
		$ids = array_values( array_diff( $ids, array( $term_id ) ) );
		if ( ! update_option( self::EN_TERMS_OPTION, $ids, false ) ) {
			return new WP_Error( 'nh_vien_save_failed', 'Could not update English categories.' );
		}
		self::flush_cache();
		do_action( 'nh_vien_pairs_changed', 0, 0 );
		return true;
	}


	/**
	 * Page-to-page correspondences, separate from article translations. Pages retain their own wording.
	 *
	 * @return array<int,int>|WP_Error Validated Vietnamese page ID => English page ID.
	 */
	public static function page_pairs() {
		$stored = get_option( self::PAGE_OPTION, array() );
		if ( ! is_array( $stored ) ) {
			return new WP_Error( 'nh_vien_invalid_store', 'Page link store is invalid.' );
		}
		$pairs = array();
		$seen  = array();
		foreach ( $stored as $vi => $en ) {
			if ( ! self::positive_id( $vi ) || ! self::positive_id( $en ) || (int) $vi === (int) $en || isset( $seen[ (int) $en ] ) ) {
				return new WP_Error( 'nh_vien_invalid_store', 'Page link store has conflicting IDs.' );
			}
			$pairs[ (int) $vi ] = (int) $en;
			$seen[ (int) $en ] = true;
		}
		foreach ( $pairs as $vi => $en ) {
			if ( isset( $pairs[ $en ] ) || isset( $seen[ $vi ] ) ) {
				return new WP_Error( 'nh_vien_invalid_store', 'A page is used for both languages.' );
			}
		}
		return $pairs;
	}

	/**
	 * Lookup an explicit page pair. An English page without a pair is legitimate and has no hreflang pair.
	 *
	 * @param mixed $page_id Page identifier.
	 * @return array<string,int>|null Pair, or null.
	 */
	public static function for_page( $page_id ) {
		$pairs = self::page_pairs();
		if ( is_wp_error( $pairs ) || ! self::positive_id( $page_id ) ) {
			return null;
		}
		foreach ( $pairs as $vi => $en ) {
			if ( (int) $page_id === $vi || (int) $page_id === $en ) {
				return array( 'vi' => $vi, 'en' => $en );
			}
		}
		return null;
	}

	/**
	 * Link two independently authored pages, with the English one strictly below /en/.
	 *
	 * @param mixed $vi Vietnamese page ID.
	 * @param mixed $en English page ID.
	 * @param mixed $nonce Nonce for this operation.
	 * @return true|WP_Error
	 */
	public static function link_page( $vi, $en, $nonce ) {
		$security = self::authorize( $nonce );
		if ( is_wp_error( $security ) ) {
			return $security;
		}
		if ( ! self::positive_id( $vi ) || ! self::positive_id( $en ) || (int) $vi === (int) $en ) {
			return new WP_Error( 'nh_vien_invalid_ids', 'Specify two different page IDs.' );
		}
		$vi = (int) $vi;
		$en = (int) $en;
		if ( ! self::eligible_page( $vi ) || ! self::eligible_page( $en ) ||
			0 === strpos( (string) get_page_uri( $vi ), 'en/' ) || 'en' === get_page_uri( $vi ) ||
			0 !== strpos( (string) get_page_uri( $en ), 'en/' ) ) {
			return new WP_Error( 'nh_vien_invalid_posts', 'Choose a Vietnamese page and an English child page under /en/.' );
		}
		if ( ! current_user_can( 'edit_post', $vi ) || ! current_user_can( 'edit_post', $en ) ) {
			return new WP_Error( 'nh_vien_forbidden', 'You must be able to edit both pages.' );
		}
		$pairs = self::page_pairs();
		if ( is_wp_error( $pairs ) ) {
			return $pairs;
		}
		if ( isset( $pairs[ $vi ] ) ) {
			return $pairs[ $vi ] === $en ? true : new WP_Error( 'nh_vien_conflict', 'Vietnamese page is already linked.' );
		}
		if ( isset( $pairs[ $en ] ) || in_array( $vi, $pairs, true ) || in_array( $en, $pairs, true ) ) {
			return new WP_Error( 'nh_vien_conflict', 'One of the pages is already linked.' );
		}
		$pairs[ $vi ] = $en;
		if ( ! update_option( self::PAGE_OPTION, $pairs, false ) ) {
			return new WP_Error( 'nh_vien_save_failed', 'Could not save page link.' );
		}
		self::flush_cache();
		do_action( 'nh_vien_pairs_changed', $vi, $en );
		return true;
	}

	/**
	 * Unlink a page pair without modifying either page.
	 *
	 * @param mixed $vi Vietnamese page ID.
	 * @param mixed $nonce Nonce.
	 * @return true|WP_Error
	 */
	public static function unlink_page( $vi, $nonce ) {
		$security = self::authorize( $nonce );
		if ( is_wp_error( $security ) ) {
			return $security;
		}
		$pairs = self::page_pairs();
		if ( is_wp_error( $pairs ) ) {
			return $pairs;
		}
		$vi = (int) $vi;
		if ( ! isset( $pairs[ $vi ] ) ) {
			return new WP_Error( 'nh_vien_not_found', 'Page link does not exist.' );
		}
		if ( ! current_user_can( 'edit_post', $vi ) || ! current_user_can( 'edit_post', $pairs[ $vi ] ) ) {
			return new WP_Error( 'nh_vien_forbidden', 'You must be able to edit both pages.' );
		}
		$en = $pairs[ $vi ];
		unset( $pairs[ $vi ] );
		if ( ! update_option( self::PAGE_OPTION, $pairs, false ) ) {
			return new WP_Error( 'nh_vien_save_failed', 'Could not unlink pages.' );
		}
		self::flush_cache();
		do_action( 'nh_vien_pairs_changed', $vi, $en );
		return true;
	}

	/** Whether a page can participate in an explicit translation link. */
	private static function eligible_page( $page_id ) {
		$page = get_post( $page_id );
		return $page instanceof WP_Post && 'page' === $page->post_type &&
			! in_array( $page->post_status, array( 'trash', 'auto-draft', 'inherit' ), true );
	}

	/**
	 * Every post paired as the English side, whatever state the pair is in.
	 *
	 * @return int[]
	 */
	public static function en_ids() {
		return array_values( array_unique( array_merge( array_keys( self::reverse() ), self::independent_ids() ) ) );
	}

	/**
	 * Whether a post is the English side of a pair.
	 *
	 * @param mixed $post_id Post ID.
	 * @return bool
	 */
	public static function is_en( $post_id ) {
		$reverse = self::reverse();
		return isset( $reverse[ (int) $post_id ] ) || in_array( (int) $post_id, self::independent_ids(), true );
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
		$standalone           = self::independent_ids();
		if ( ! $pairs && ! $standalone ) {
			return array();
		}
		$ids       = array_values( array_unique( array_merge( array_keys( $pairs ), array_values( $pairs ), $standalone ) ) );
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
		foreach ( $standalone as $en ) {
			if ( ! isset( $by_id[ $en ] ) || ! NH_VIEN_Routes::slug_is_routable( $by_id[ $en ]->post_name ) ) {
				continue;
			}
			if ( $pages_below_en && null !== NH_VIEN_Routes::page_at( 'en/' . $by_id[ $en ]->post_name ) ) {
				continue;
			}
			self::$memo['public'][ $en ] = $by_id[ $en ];
		}
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
		self::$memo['terms'] = array_fill_keys( self::declared_en_terms(), true );
		$ids                 = self::public_en_ids();
		if ( ! $ids ) {
			return self::$memo['terms'];
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
		self::$memo['en_only'] = array_fill_keys( self::declared_en_terms(), true );
		$english               = self::en_ids();
		if ( ! $english ) {
			return self::$memo['en_only'];
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
			return self::$memo['en_only'];
		}
		$rows = wp_get_object_terms( $published, array( 'category', 'post_tag' ), array( 'fields' => 'all_with_object_id' ) );
		if ( is_wp_error( $rows ) ) {
			return self::$memo['en_only'];
		}
		$english_posts = array();
		$terms         = array();
		foreach ( $rows as $term ) {
			$term_id                   = (int) $term->term_id;
			$terms[ $term_id ]         = $term;
			$english_posts[ $term_id ] = isset( $english_posts[ $term_id ] ) ? $english_posts[ $term_id ] + 1 : 1;
		}
		// The parents of those categories list the English posts of their children too, so they can be English only as well.
		foreach ( array_keys( $terms ) as $term_id ) {
			if ( 'category' !== $terms[ $term_id ]->taxonomy ) {
				continue;
			}
			foreach ( get_ancestors( $term_id, 'category', 'taxonomy' ) as $ancestor_id ) {
				$ancestor_id = (int) $ancestor_id;
				$ancestor    = isset( $terms[ $ancestor_id ] ) ? null : get_term( $ancestor_id, 'category' );
				if ( $ancestor instanceof WP_Term ) {
					$terms[ $ancestor_id ]         = $ancestor;
					$english_posts[ $ancestor_id ] = 0;
				}
			}
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
