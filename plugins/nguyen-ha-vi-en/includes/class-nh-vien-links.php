<?php
/**
 * Explicit, bidirectional editorial post relationships stored in a plugin-owned option.
 * Does not touch WordPress posts, post meta, permalinks, translations or SEO values.
 *
 * @package Nguyen_Ha_VI_EN
 */

defined( 'ABSPATH' ) || exit;

final class NH_VIEN_Links {
	const OPTION = 'nh_vien_translation_pairs_v1';
	const NONCE_ACTION = 'nh_vien_manage_links';

	/**
	 * Read the one-to-one map: original Vietnamese post ID => English post ID.
	 * Fail closed on corrupted data, instead of silently overwriting it.
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
			$seen[ $en ] = true;
		}
		return $pairs;
	}

	/**
	 * Get a reciprocal VI/EN pair, without emitting public-language metadata.
	 *
	 * @param int $post_id Existing post ID.
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
				return array( 'vi' => $vi, 'en' => $en );
			}
		}
		return null;
	}

	/**
	 * Add a pair only following an explicit, privileged, nonce-authenticated request.
	 * Never replaces an existing relationship; unlink first to correct mistakes.
	 *
	 * @param mixed $vi    Existing Vietnamese post ID.
	 * @param mixed $en    Existing English post ID.
	 * @param mixed $nonce Action nonce.
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
			return new WP_Error( 'nh_vien_invalid_posts', 'Both IDs must be existing non-trashed posts.' );
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
	 * Remove one existing pair by its Vietnamese post ID, only on explicit request.
	 *
	 * @param mixed $vi    Vietnamese post ID.
	 * @param mixed $nonce Action nonce.
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

	/** @return true|WP_Error */
	private static function authorize( $nonce ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'nh_vien_forbidden', 'Administrator permission required.' );
		}
		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return new WP_Error( 'nh_vien_bad_nonce', 'Invalid or expired request nonce.' );
		}
		return true;
	}

	/** @return bool */
	private static function positive_id( $value ) {
		return ( is_int( $value ) && $value > 0 ) ||
			( is_string( $value ) && 1 === preg_match( '/^[1-9][0-9]*$/D', $value ) && (string) (int) $value === $value );
	}

	/** @return bool */
	private static function eligible_post( $post_id ) {
		$post = get_post( $post_id );
		return $post instanceof WP_Post && 'post' === $post->post_type &&
			! in_array( $post->post_status, array( 'trash', 'auto-draft', 'inherit' ), true );
	}
}
