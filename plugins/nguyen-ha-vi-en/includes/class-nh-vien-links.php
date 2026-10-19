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
	const OPTION = 'nh_vien_translation_pairs_v1';
	const NONCE_ACTION = 'nh_vien_manage_links';

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
		$seen = array();
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
			$seen[ $en ] = true;
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
		return update_option( self::OPTION, $pairs, false ) ? true : new WP_Error( 'nh_vien_save_failed', 'Could not save translation link.' );
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
		$vi = (int) $vi;
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
		unset( $pairs[ $vi ] );
		return update_option( self::OPTION, $pairs, false ) ? true : new WP_Error( 'nh_vien_save_failed', 'Could not remove translation link.' );
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
