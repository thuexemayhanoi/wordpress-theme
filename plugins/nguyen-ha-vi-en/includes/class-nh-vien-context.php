<?php
/**
 * The language of the request being answered.
 *
 * Vietnamese is the default. A request becomes English when one of the English routes matched it (see
 * NH_VIEN_Routes::parse_request()). The locale is switched earlier, from the address alone, so that the theme and
 * WordPress load English strings and print lang="en-US" on the pages below /en/.
 *
 * @package Nguyen_Ha_VI_EN
 */

defined( 'ABSPATH' ) || exit;

/**
 * Language of the current request.
 */
final class NH_VIEN_Context {
	/**
	 * The language a matched route decided on.
	 *
	 * @var string
	 */
	private static $language = 'vi';

	/**
	 * Memo of the early look at the address.
	 *
	 * @var bool|null
	 */
	private static $early = null;

	/** Register the locale and cache filters when the English side is switched on. */
	public static function boot() {
		if ( NH_VIEN_Routes::enabled() ) {
			add_filter( 'locale', array( __CLASS__, 'filter_locale' ), 1 );
			// The Nguyen Ha Editorial theme keeps menus, hubs and lists in transients under a versioned key.
			add_filter( 'nh_editorial_cache_version', array( __CLASS__, 'cache_version' ) );
		}
	}

	/**
	 * Keep what is cached for English pages apart from what is cached for Vietnamese pages: menus, hubs and lists
	 * hold addresses and posts of one language. Vietnamese keeps the version it always had.
	 *
	 * @param string $version The cache version.
	 * @return string
	 */
	public static function cache_version( $version ) {
		return self::is_english() ? $version . '-en' : $version;
	}

	/**
	 * Set the language of this request.
	 *
	 * @param string $code Language code.
	 */
	public static function set( $code ) {
		if ( NH_VIEN_Languages::is_supported( $code ) ) {
			self::$language = NH_VIEN_Languages::get( $code )['code'];
		}
	}

	/**
	 * The language of this request.
	 *
	 * @return string 'vi' or 'en'.
	 */
	public static function language() {
		return self::$language;
	}

	/**
	 * Whether this request is answered in English.
	 *
	 * @return bool
	 */
	public static function is_english() {
		return 'en' === self::$language;
	}

	/** Back to the default; for tests and for code that runs several requests in one process. */
	public static function reset() {
		self::$language = 'vi';
		self::$early    = null;
	}

	/**
	 * Use the English locale for the front-end pages below /en/.
	 *
	 * @param string $locale The locale WordPress would use.
	 * @return string
	 */
	public static function filter_locale( $locale ) {
		if ( is_admin() || ! self::address_is_english() ) {
			return $locale;
		}
		$english = NH_VIEN_Languages::get( 'en' );
		return $english ? $english['locale'] : $locale;
	}

	/**
	 * Whether the address that was asked for is /en or below it, judged before WordPress has parsed the request.
	 *
	 * @return bool
	 */
	public static function address_is_english() {
		if ( null !== self::$early ) {
			return self::$early;
		}
		self::$early = false;
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$path = (string) wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
			$home = trailingslashit( (string) wp_parse_url( home_url(), PHP_URL_PATH ) );
			if ( 0 === strpos( $path, $home ) ) {
				$relative    = substr( $path, strlen( $home ) );
				self::$early = 'en' === $relative || 0 === strpos( $relative, 'en/' );
			}
		}
		return self::$early;
	}
}
