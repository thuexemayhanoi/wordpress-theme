<?php
/**
 * Author information for the author box and author archive.
 *
 * Only data the author (or the site owner) entered is shown: display name, biography, job title,
 * website and profile links. No credentials, expertise or social proof is ever generated.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

/**
 * User meta keys that hold a single profile URL (Yoast SEO and WordPress contact methods).
 *
 * @return string[]
 */
function nh_editorial_author_profile_keys() {
	return array( 'facebook', 'twitter', 'instagram', 'linkedin', 'youtube', 'wikipedia', 'pinterest' );
}

/**
 * Profile of an author.
 *
 * @param int $user_id User ID.
 * @return array{id: int, name: string, bio: string, job: string, url: string, avatar: string, links: array}|null
 */
function nh_editorial_get_author_profile( $user_id ) {
	$user_id = absint( $user_id );
	$user    = $user_id ? get_userdata( $user_id ) : false;
	if ( ! $user instanceof WP_User ) {
		return null;
	}

	$urls = array();
	foreach ( preg_split( '/\R/u', (string) get_user_meta( $user_id, 'nh_sameas', true ) ) as $line ) {
		$urls[] = trim( $line );
	}
	foreach ( nh_editorial_author_profile_keys() as $key ) {
		$value = get_user_meta( $user_id, $key, true );
		if ( is_string( $value ) && preg_match( '#^https?://#i', $value ) ) {
			$urls[] = $value;
		}
	}
	if ( '' !== $user->user_url ) {
		$urls[] = $user->user_url;
	}

	$links = array();
	$seen  = array();
	foreach ( $urls as $url ) {
		$item = nh_editorial_describe_social_url( $url );
		if ( $item && ! isset( $seen[ $item['url'] ] ) ) {
			$seen[ $item['url'] ] = true;
			$links[]              = $item;
		}
	}

	$avatar = get_avatar(
		$user_id,
		96,
		'',
		'',
		array(
			'class'    => 'author__avatar',
			'loading'  => 'lazy',
			'decoding' => 'async',
		)
	);

	$profile = array(
		'id'     => $user_id,
		'name'   => $user->display_name,
		'bio'    => trim( wp_strip_all_tags( (string) get_user_meta( $user_id, 'description', true ) ) ),
		'job'    => nh_editorial_clean_text( get_user_meta( $user_id, 'nh_job_title', true ), 120 ),
		'url'    => get_author_posts_url( $user_id ),
		'avatar' => is_string( $avatar ) ? $avatar : '',
		'links'  => $links,
	);

	/**
	 * Filters an author profile.
	 *
	 * @param array $profile Profile data.
	 * @param int   $user_id User ID.
	 */
	return apply_filters( 'nh_editorial_author_profile', $profile, $user_id );
}

/**
 * Whether the author box should be shown for a profile, according to the Customizer.
 *
 * @param array|null $profile Profile from nh_editorial_get_author_profile().
 * @return bool
 */
function nh_editorial_author_box_visible( $profile ) {
	if ( empty( $profile ) ) {
		return false;
	}
	$mode = (string) nh_editorial_option( 'nh_author_box' );
	if ( 'never' === $mode ) {
		return false;
	}
	if ( 'always' === $mode ) {
		return true;
	}
	return '' !== $profile['bio'];
}
