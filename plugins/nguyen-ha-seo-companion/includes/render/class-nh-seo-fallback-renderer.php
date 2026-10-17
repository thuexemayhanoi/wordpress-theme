<?php
/**
 * Fallback renderer: shows the editorial extras the active theme does not render itself.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * Appends the summary, takeaways, FAQ and sources to the article content, but only for the features the
 * theme did not declare with add_theme_support( 'nguyen-ha-companion', ... ) and only while the setting
 * "Fallback rendering" is on.
 *
 * The summary (and the takeaways and update date) come first, before the content; the FAQ and the sources
 * come last. Nothing is rendered outside the main loop of a single post or page, for password protected
 * posts, in feeds, or while excerpts are generated.
 */
final class NH_SEO_Fallback_Renderer {

	/**
	 * Stylesheet handle.
	 *
	 * @var string
	 */
	const STYLE_HANDLE = 'nh-seo-frontend';

	/**
	 * Extras the fallback can render, in the order they are checked.
	 *
	 * @var string[]
	 */
	const FEATURES = array( 'updated', 'summary', 'takeaways', 'faq', 'sources' );

	/**
	 * Wrap the content of the main post with the blocks the theme does not render.
	 *
	 * Hooked to `the_content` at priority 20, after the core formatting filters.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public static function filter_content( $content ) {
		if ( ! self::is_main_article_loop() ) {
			return $content;
		}

		$post = get_post();
		if ( ! $post instanceof WP_Post || get_queried_object_id() !== (int) $post->ID || post_password_required( $post ) ) {
			return $content;
		}

		$features = self::unsupported_features();
		if ( ! $features ) {
			return $content;
		}

		$extras = nh_seo_get_post_extras( $post->ID );
		$before = '';
		$after  = '';
		if ( in_array( 'updated', $features, true ) ) {
			$before .= NH_SEO_Renderer::updated( $extras['updated'] );
		}
		if ( in_array( 'summary', $features, true ) ) {
			$before .= NH_SEO_Renderer::summary( $extras['summary'] );
		}
		if ( in_array( 'takeaways', $features, true ) ) {
			$before .= NH_SEO_Renderer::takeaways( $extras['takeaways'] );
		}
		if ( in_array( 'faq', $features, true ) ) {
			$after .= NH_SEO_Renderer::faq( $extras['faq'] );
		}
		if ( in_array( 'sources', $features, true ) ) {
			$after .= NH_SEO_Renderer::sources( $extras['sources'] );
		}

		if ( '' === $before && '' === $after ) {
			return $content;
		}

		// Safety net: the style is normally enqueued in the head (see maybe_enqueue_style()).
		self::enqueue_style();

		return $before . $content . $after;
	}

	/**
	 * Enqueue the tiny stylesheet in the head, only on pages that will actually show a fallback block.
	 *
	 * @return void
	 */
	public static function maybe_enqueue_style() {
		if ( ! is_singular( NH_SEO_Data_Model::POST_TYPES ) ) {
			return;
		}
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post || post_password_required( $post ) ) {
			return;
		}

		foreach ( self::unsupported_features() as $feature ) {
			if ( self::has_data( $post->ID, $feature ) ) {
				self::enqueue_style();
				return;
			}
		}
	}

	/**
	 * Enqueue the stylesheet.
	 *
	 * @return void
	 */
	public static function enqueue_style() {
		wp_enqueue_style( self::STYLE_HANDLE, NH_SEO_URL . 'assets/frontend.css', array(), NH_SEO_VERSION );
	}

	/**
	 * Whether the current `the_content` call belongs to the main loop of a single post or page.
	 *
	 * @return bool
	 */
	private static function is_main_article_loop() {
		if ( is_admin() || is_feed() || is_embed() ) {
			return false;
		}
		if ( ! is_singular( NH_SEO_Data_Model::POST_TYPES ) || ! is_main_query() || ! in_the_loop() ) {
			return false;
		}
		// Excerpts are built from the content: they must not start with a fallback block.
		return ! doing_filter( 'get_the_excerpt' );
	}

	/**
	 * Features the theme does not render, or an empty list when the fallback is switched off.
	 *
	 * @return string[]
	 */
	private static function unsupported_features() {
		$settings = nh_seo_get_settings();
		if ( empty( $settings['fallback_render'] ) ) {
			return array();
		}

		$features = array();
		foreach ( self::FEATURES as $feature ) {
			if ( ! nh_seo_theme_supports( $feature ) ) {
				$features[] = $feature;
			}
		}
		return $features;
	}

	/**
	 * Cheap check on the raw meta (no sanitizing): does the post have anything stored for a feature?
	 *
	 * @param int    $post_id Post ID.
	 * @param string $feature One of FEATURES.
	 * @return bool
	 */
	private static function has_data( $post_id, $feature ) {
		$keys = array(
			'updated'   => NH_SEO_Keys::META_UPDATED,
			'summary'   => NH_SEO_Keys::META_SUMMARY,
			'takeaways' => NH_SEO_Keys::META_TAKEAWAYS,
			'faq'       => NH_SEO_Keys::META_FAQ,
			'sources'   => NH_SEO_Keys::META_SOURCES,
		);
		return ! empty( $keys[ $feature ] ) && ! empty( get_post_meta( $post_id, $keys[ $feature ], true ) );
	}
}
