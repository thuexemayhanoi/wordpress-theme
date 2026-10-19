<?php
/**
 * Deliberately inert plugin service entrypoint.
 *
 * @package Nguyen_Ha_VI_EN
 */

defined( 'ABSPATH' ) || exit;

final class NH_VIEN_Plugin {
	/** @var NH_VIEN_Plugin|null */
	private static $instance = null;

	/**
	 * Initialize only in memory. Future modules must opt in explicitly.
	 *
	 * @return NH_VIEN_Plugin
	 */
	public static function boot() {
		return self::instance();
	}

	/**
	 * @return NH_VIEN_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {}

	/**
	 * @return array<string, array<string, string>>
	 */
	public function languages() {
		return NH_VIEN_Languages::all();
	}

	/**
	 * @return string
	 */
	public function default_language() {
		return NH_VIEN_Languages::default_code();
	}
}
