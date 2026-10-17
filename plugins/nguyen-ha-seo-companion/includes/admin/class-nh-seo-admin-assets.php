<?php
/**
 * Admin assets: loaded only on the plugin's own screens, the post editors and the term and profile screens.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * Decides on which admin screens the plugin has something to show, and enqueues its CSS and JS there.
 */
final class NH_SEO_Admin_Assets {

	/**
	 * Taxonomies whose term screens carry the Pillar field.
	 *
	 * @var string[]
	 */
	const TERM_TAXONOMIES = array( 'category', NH_SEO_Keys::TAXONOMY_CLUSTER );

	/**
	 * Which kind of plugin screen this is.
	 *
	 * @param WP_Screen|null $screen      Current screen.
	 * @param string         $hook_suffix Hook suffix of the admin page.
	 * @return string settings | post | term | user, or '' when the plugin has nothing to show here.
	 */
	public static function context( $screen, $hook_suffix = '' ) {
		if ( 'settings_page_' . NH_SEO_Settings_Page::SLUG === $hook_suffix ) {
			return 'settings';
		}
		if ( ! $screen instanceof WP_Screen ) {
			return '';
		}

		if ( 'post' === $screen->base && in_array( $screen->post_type, NH_SEO_Data_Model::POST_TYPES, true ) ) {
			return 'post';
		}
		if ( in_array( $screen->base, array( 'edit-tags', 'term' ), true ) && in_array( $screen->taxonomy, self::TERM_TAXONOMIES, true ) ) {
			return 'term';
		}
		if ( in_array( $screen->base, array( 'profile', 'user-edit' ), true ) ) {
			return 'user';
		}
		return '';
	}

	/**
	 * Enqueue the admin stylesheet and script where they are needed.
	 *
	 * @param string $hook_suffix Hook suffix of the admin page.
	 * @return void
	 */
	public static function enqueue( $hook_suffix ) {
		$context = self::context( function_exists( 'get_current_screen' ) ? get_current_screen() : null, (string) $hook_suffix );
		if ( '' === $context ) {
			return;
		}

		wp_enqueue_style( 'nh-seo-admin', NH_SEO_URL . 'assets/admin.css', array(), NH_SEO_VERSION );
		wp_enqueue_script( 'nh-seo-admin', NH_SEO_URL . 'assets/admin.js', array(), NH_SEO_VERSION, true );
		wp_localize_script(
			'nh-seo-admin',
			'nhSeoAdmin',
			array(
				'context' => $context,
				'i18n'    => array(
					'selectLogo'   => __( 'Choose a logo', 'nguyen-ha-seo-companion' ),
					'useLogo'      => __( 'Use this image', 'nguyen-ha-seo-companion' ),
					'noLogo'       => __( 'No logo selected.', 'nguyen-ha-seo-companion' ),
					'itemAdded'    => __( 'Item added.', 'nguyen-ha-seo-companion' ),
					'itemRemoved'  => __( 'Item removed.', 'nguyen-ha-seo-companion' ),
					'itemMoved'    => __( 'Item moved.', 'nguyen-ha-seo-companion' ),
					'limitReached' => __( 'The maximum number of items is reached.', 'nguyen-ha-seo-companion' ),
				),
			)
		);

		if ( 'settings' === $context ) {
			wp_enqueue_media(); // The logo picker uses the media library.
		}
	}
}
