<?php
/**
 * The VI/EN language switcher: a small accessible navigation that is printed at the top of every front-end page
 * (and can be placed by hand with the [nh_vien_switcher] shortcode).
 *
 * On a post that has a translation it links to the translation. On every other page it links to the home page of the
 * other language, and it is left out altogether while there is no English page to link to.
 *
 * @package Nguyen_Ha_VI_EN
 */

defined( 'ABSPATH' ) || exit;

/**
 * Accessible language switcher.
 */
final class NH_VIEN_Switcher {
	/** Theme support flag: the theme prints the switcher itself, right after its skip link (see after_skip_link()). */
	const THEME_SUPPORT = 'nguyen-ha-vi-en-switcher';

	/** The action the Nguyen Ha editorial theme fires right after its "Skip to content" link. */
	const THEME_ACTION = 'nh_editorial_after_skip_link';

	/** Register the switcher hooks when the English side is switched on. */
	public static function boot() {
		if ( ! NH_VIEN_Routes::enabled() ) {
			return;
		}
		add_action( 'wp_body_open', array( __CLASS__, 'bar' ), 5 );
		add_action( self::THEME_ACTION, array( __CLASS__, 'after_skip_link' ) );
		add_filter( 'the_content', array( __CLASS__, 'prepend' ), 8 );
		add_shortcode( 'nh_vien_switcher', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'styles' ) );
	}

	/** Enqueue the lightweight navigation stylesheet, on the pages that show the switcher only. */
	public static function styles() {
		if ( null === self::alternates() ) {
			return;
		}
		wp_enqueue_style( 'nh-vien-switcher', plugins_url( 'assets/switcher.css', NH_VIEN_PLUGIN_FILE ), array(), NH_VIEN_VERSION );
	}

	/**
	 * Print the switcher right after the opening of the body, where themes call wp_body_open().
	 *
	 * A theme that declares support for the switcher prints it after its own skip link instead (see after_skip_link()),
	 * so that "Skip to content" stays the first thing a keyboard or screen reader user reaches.
	 */
	public static function bar() {
		if ( ! current_theme_supports( self::THEME_SUPPORT ) ) {
			self::print_bar();
		}
	}

	/** Print the switcher where a supporting theme asks for it: after its skip link. */
	public static function after_skip_link() {
		if ( current_theme_supports( self::THEME_SUPPORT ) ) {
			self::print_bar();
		}
	}

	/** Print the bar that holds the switcher. */
	private static function print_bar() {
		/**
		 * Filters whether the switcher is printed automatically at the top of the page.
		 *
		 * @param bool $print True by default. Return false to place the switcher with the shortcode instead.
		 */
		if ( ! apply_filters( 'nh_vien_switcher_auto_placement', true ) ) {
			return;
		}
		$html = self::html();
		if ( '' !== $html ) {
			echo '<div class="nh-vien-bar">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built and escaped in html().
		}
	}

	/**
	 * For themes that never call wp_body_open(): place the switcher before the content of a single post.
	 *
	 * The content of a block theme is built before the page starts (before wp_head), and wp_body_open() fires afterwards;
	 * a classic theme that calls wp_body_open() has done so before the loop. Only a page that has started and has not
	 * fired wp_body_open() by the time its content is built belongs to a theme that never will.
	 *
	 * @param string $content WordPress post body.
	 * @return string Original or augmented content.
	 */
	public static function prepend( $content ) {
		if ( did_action( 'wp_body_open' ) || ! did_action( 'wp_head' ) || ! is_singular( 'post' ) || ! is_main_query() || ! in_the_loop() || is_feed() || has_shortcode( $content, 'nh_vien_switcher' ) ) {
			return $content;
		}
		return self::html() . $content;
	}

	/**
	 * Render the explicit editor shortcode.
	 *
	 * @return string Navigation markup or empty string.
	 */
	public static function shortcode() {
		return self::html();
	}

	/**
	 * Where each language goes for the page being answered.
	 *
	 * @return array{vi: string, en: string, current: string}|null Null when the switcher is not shown.
	 */
	public static function alternates() {
		if ( ! NH_VIEN_Routes::live() ) {
			return null;
		}
		$english = NH_VIEN_Context::is_english();
		$page    = NH_VIEN_Urls::current_url();
		$vi      = $english ? NH_VIEN_Urls::vi_home() : $page;
		$en      = $english ? $page : NH_VIEN_Urls::en_home();
		if ( is_singular( 'post' ) ) {
			$id = (int) get_queried_object_id();
			if ( $english ) {
				$partner = NH_VIEN_Links::vi_for_en( $id );
				$link    = $partner ? get_permalink( $partner ) : '';
				$vi      = $link ? $link : $vi;
				$en      = NH_VIEN_Routes::english_url( $id );
			} else {
				$partner = NH_VIEN_Links::en_for_vi( $id );
				$link    = $partner ? NH_VIEN_Routes::english_url( $partner ) : '';
				$en      = '' !== $link ? $link : $en;
				$vi      = (string) get_permalink( $id );
			}
		}
		if ( '' === $vi || '' === $en ) {
			return null;
		}
		return array(
			'vi'      => $vi,
			'en'      => $en,
			'current' => $english ? 'en' : 'vi',
		);
	}

	/**
	 * Build the switcher for the page being answered.
	 *
	 * @return string Escaped navigation markup or empty string.
	 */
	public static function html() {
		$alternates = self::alternates();
		if ( ! $alternates ) {
			return '';
		}
		$items = '';
		foreach ( array( 'vi', 'en' ) as $code ) {
			$language = NH_VIEN_Languages::get( $code );
			$current  = $code === $alternates['current'] ? ' aria-current="page"' : '';
			// The accessible name starts with the visible text ("VI"), so that people who speak the label can reach the link.
			$name   = strtoupper( $code ) . ': ' . $language['label'];
			$items .= '<a class="nh-vien-switcher__link" href="' . esc_url( $alternates[ $code ] ) . '" lang="' . esc_attr( $code ) . '" hreflang="' . esc_attr( $code ) . '" aria-label="' . esc_attr( $name ) . '"' . $current . '>' . esc_html( strtoupper( $code ) ) . '</a>';
		}
		// The name of the navigation is in the language of the page the visitor is reading.
		$label = 'en' === $alternates['current'] ? 'Language' : 'Ngôn ngữ';
		return '<nav class="nh-vien-switcher" aria-label="' . esc_attr( $label ) . '">' . $items . '</nav>';
	}
}
