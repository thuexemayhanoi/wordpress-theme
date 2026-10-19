<?php
/**
 * Language metadata only; this class does not register routes or translate content.
 *
 * @package Nguyen_Ha_VI_EN
 */

defined( 'ABSPATH' ) || exit;

final class NH_VIEN_Languages {
	/**
	 * Language identifiers and display metadata.
	 *
	 * The prefix is reserved metadata, not a registered URL rewrite.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function all() {
		return array(
			'vi' => array(
				'code'       => 'vi',
				'label'      => 'Tiếng Việt',
				'locale'     => 'vi',
				'url_prefix' => '',
			),
			'en' => array(
				'code'       => 'en',
				'label'      => 'English',
				'locale'     => 'en_US',
				'url_prefix' => 'en',
			),
		);
	}

	/**
	 * The existing Vietnamese site remains the default.
	 *
	 * @return string
	 */
	public static function default_code() {
		return 'vi';
	}

	/**
	 * Look up one configured language without changing WordPress locale.
	 *
	 * @param mixed $code Language code.
	 * @return array<string, string>|null
	 */
	public static function get( $code ) {
		if ( ! is_string( $code ) ) {
			return null;
		}

		$languages = self::all();
		$code      = strtolower( trim( $code ) );

		return isset( $languages[ $code ] ) ? $languages[ $code ] : null;
	}

	/**
	 * @param mixed $code Language code.
	 * @return bool
	 */
	public static function is_supported( $code ) {
		return null !== self::get( $code );
	}
}
