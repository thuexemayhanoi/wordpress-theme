<?php
/**
 * Deliberately inert plugin service entrypoint.
 *
 * @package Nguyen_Ha_VI_EN
 */

defined( 'ABSPATH' ) || exit;

/**
 * Standalone plugin service container.
 */
final class NH_VIEN_Plugin {
	/**
	 * Cached plugin instance.
	 *
	 * @var NH_VIEN_Plugin|null Singleton instance.
	 */
	private static $instance = null;

	/**
	 * Initialize only in memory. Future modules must opt in explicitly.
	 *
	 * @return NH_VIEN_Plugin
	 */
	public static function boot() {
		$instance = self::instance();
		// Staging-only: production never registers /en/ rewrite rules or switcher. The rewrite rule is registered on
		// every request, administration screens included: Settings > Permalinks flushes the stored rules from the
		// admin, and a flush that never saw the rule would leave the preview route out of them.
		NH_VIEN_Routes::boot();
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			NH_VIEN_Admin::boot();
		} else {
			NH_VIEN_Switcher::boot();
		}
		return $instance;
	}

	/**
	 * Get the singleton without running additional hooks.
	 *
	 * @return NH_VIEN_Plugin Plugin instance.
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/** Prevent direct construction of the service. */
	private function __construct() {}

	/**
	 * Return the language registry.
	 *
	 * @return array<string, array<string, string>> Language definitions.
	 */
	public function languages() {
		return NH_VIEN_Languages::all();
	}

	/**
	 * Get the default language identifier.
	 *
	 * @return string Default language.
	 */
	public function default_language() {
		return NH_VIEN_Languages::default_code();
	}
	/**
	 * Look up an explicit VI/EN pair, without registering language routes.
	 *
	 * @param int $post_id Source ID.
	 * @return array<string,int>|null
	 */
	public function translation_for( $post_id ) {
		return NH_VIEN_Links::for_post( $post_id );
	}
}
