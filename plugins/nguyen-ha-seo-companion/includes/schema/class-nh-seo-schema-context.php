<?php
/**
 * Describes what the main query is showing, for the schema module.
 *
 * One place decides whether structured data may be printed at all (never for 404, previews, drafts,
 * private or password protected content) and which page type the request is.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * Request context.
 */
final class NH_SEO_Schema_Context {

	/**
	 * Detect the context of the current main query.
	 *
	 * Returned array keys:
	 *  kind  singular | home | author | search | archive
	 *  post  WP_Post|null (singular only)
	 *  user  WP_User|null (author archives only)
	 *  url   canonical URL of the page (never carries query strings or "page N")
	 *  front true on the front page
	 *
	 * @return array<string, mixed>|null Null when nothing may be printed for this request.
	 */
	public static function detect() {
		if ( is_404() || is_preview() || is_feed() || is_embed() ) {
			return null;
		}

		$front = is_front_page();

		if ( is_singular() ) {
			$post = get_queried_object();
			if ( ! $post instanceof WP_Post || ! nh_seo_is_public_post( $post ) ) {
				return null;
			}
			$url = get_permalink( $post );
			if ( ! $url ) {
				return null;
			}
			return array(
				'kind'  => 'singular',
				'post'  => $post,
				'user'  => null,
				'url'   => $url,
				'front' => $front,
			);
		}

		if ( $front || is_home() ) {
			$posts_page = (int) get_option( 'page_for_posts' );
			$url        = ( ! $front && 'page' === get_option( 'show_on_front' ) && $posts_page ) ? get_permalink( $posts_page ) : home_url( '/' );
			return array(
				'kind'  => 'home',
				'post'  => null,
				'user'  => null,
				'url'   => $url ? $url : home_url( '/' ),
				'front' => $front,
			);
		}

		if ( is_author() ) {
			$user = get_queried_object();
			return $user instanceof WP_User ? self::archive_context( 'author', $user ) : null;
		}

		if ( is_search() ) {
			return self::archive_context( 'search', null );
		}

		if ( is_archive() ) {
			return self::archive_context( 'archive', null );
		}

		return null;
	}

	/**
	 * Context for archive-like pages. The URL comes from the canonical trail, whose last item is the page itself
	 * (so paged archives never leak "page N" or tracking parameters into the markup).
	 *
	 * @param string       $kind Context kind.
	 * @param WP_User|null $user Author for author archives.
	 * @return array<string, mixed>|null
	 */
	private static function archive_context( $kind, $user ) {
		$trail = nh_seo_breadcrumb_trail();
		$last  = end( $trail );
		if ( ! is_array( $last ) || empty( $last['url'] ) ) {
			return null;
		}
		return array(
			'kind'  => $kind,
			'post'  => null,
			'user'  => $user,
			'url'   => (string) $last['url'],
			'front' => false,
		);
	}
}
