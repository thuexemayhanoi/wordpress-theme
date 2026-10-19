<?php
/**
 * Minimal accessible VI/EN switcher; frontend remains off by default.
 * @package Nguyen_Ha_VI_EN
 */
defined( 'ABSPATH' ) || exit;

final class NH_VIEN_Switcher {
	public static function boot() {
		if ( ! NH_VIEN_Routes::enabled() ) {
			return;
		}
		add_filter( 'the_content', array( __CLASS__, 'prepend' ), 8 );
		add_shortcode( 'nh_vien_switcher', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'styles' ) );
	}

	public static function styles() {
		wp_enqueue_style( 'nh-vien-switcher', plugins_url( 'assets/switcher.css', NH_VIEN_PLUGIN_FILE ), array(), NH_VIEN_VERSION );
	}

	public static function prepend( $content ) {
		if ( ! is_singular( 'post' ) || ! is_main_query() || ! in_the_loop() || is_feed() || has_shortcode( $content, 'nh_vien_switcher' ) ) {
			return $content;
		}
		return self::html( get_queried_object_id() ) . $content;
	}

	public static function shortcode() {
		if ( ! is_singular( 'post' ) ) {
			return '';
		}
		return self::html( get_queried_object_id() );
	}

	public static function html( $post_id ) {
		if ( ! NH_VIEN_Routes::enabled() ) {
			return '';
		}
		$pair = NH_VIEN_Links::for_post( $post_id );
		if ( ! is_array( $pair ) ) {
			return '';
		}
		$en_url = NH_VIEN_Routes::english_url( $pair['en'] );
		$vi = get_post( (int) $pair['vi'] );
		$vi_url = $vi instanceof WP_Post && 'publish' === $vi->post_status ? get_permalink( $vi->ID ) : '';
		if ( ! $vi_url || ! $en_url ) {
			return '';
		}
		$current = (int) $post_id === (int) $pair['en'] ? 'en' : 'vi';
		$vi_current = 'vi' === $current ? ' aria-current="page"' : '';
		$en_current = 'en' === $current ? ' aria-current="page"' : '';
		return '<nav class="nh-vien-switcher" aria-label="Language">'
			. '<a class="nh-vien-switcher__link" href="' . esc_url( $vi_url ) . '" lang="vi" hreflang="vi"' . $vi_current . '>VI</a>'
			. '<a class="nh-vien-switcher__link" href="' . esc_url( $en_url ) . '" lang="en" hreflang="en"' . $en_current . '>EN</a>'
			. '</nav>';
	}
}
