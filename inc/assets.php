<?php
/**
 * Front-end assets.
 *
 * Strategy (docs/ARCHITECTURE.md, "Performance"):
 * - critical CSS (tokens, base, header, layout, cards, article basics) is inlined in <head>;
 * - the rest of the CSS loads without blocking rendering (media=print swap, with a <noscript> fallback);
 * - one small deferred script, no framework, no third-party requests, system fonts;
 * - a tiny inline script applies the saved or preferred color mode before first paint (no flash).
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

/**
 * URL and version of a built asset, or null when the file does not exist.
 *
 * @param string $relative Path inside the theme, e.g. 'assets/css/main.min.css'.
 * @return array{path: string, url: string, version: string}|null
 */
function nh_editorial_asset( $relative ) {
	$path = NH_EDITORIAL_DIR . $relative;
	if ( ! is_readable( $path ) ) {
		return null;
	}
	return array(
		'path'    => $path,
		'url'     => NH_EDITORIAL_URI . $relative,
		// File time during development so edits are never stale; the release version in production.
		'version' => ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? (string) filemtime( $path ) : NH_EDITORIAL_VERSION,
	);
}

/**
 * Whether critical CSS is inlined and the main stylesheet is loaded without blocking.
 *
 * @return bool
 */
function nh_editorial_use_critical_css() {
	$use = ! is_customize_preview()
		&& ! is_admin()
		&& null !== nh_editorial_asset( 'assets/css/critical.min.css' )
		&& null !== nh_editorial_asset( 'assets/css/main.min.css' );

	/**
	 * Filters whether critical CSS is inlined.
	 *
	 * @param bool $use Whether to inline critical CSS.
	 */
	return (bool) apply_filters( 'nh_editorial_use_critical_css', $use );
}

/**
 * Enqueue styles and scripts.
 *
 * @return void
 */
function nh_editorial_enqueue_assets() {
	$main     = nh_editorial_asset( 'assets/css/main.min.css' );
	$critical = nh_editorial_asset( 'assets/css/critical.min.css' );
	$script   = nh_editorial_asset( 'assets/js/theme.min.js' );

	if ( nh_editorial_use_critical_css() ) {
		// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Inline-only handle, no URL.
		wp_register_style( 'nh-editorial-critical', false );
		wp_enqueue_style( 'nh-editorial-critical' );
		// Local theme file, not a remote request.
		wp_add_inline_style( 'nh-editorial-critical', trim( (string) file_get_contents( $critical['path'] ) ) . nh_editorial_accent_css() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		wp_enqueue_style( 'nh-editorial-main', $main['url'], array(), $main['version'] );
	} else {
		// Customizer preview, debugging or a missing build: plain blocking styles.
		if ( $critical ) {
			wp_enqueue_style( 'nh-editorial-critical-file', $critical['url'], array(), $critical['version'] );
		}
		if ( $main ) {
			wp_enqueue_style( 'nh-editorial-main', $main['url'], array( 'nh-editorial-critical-file' ), $main['version'] );
		}
		$accent = nh_editorial_accent_css();
		if ( '' !== $accent && wp_style_is( 'nh-editorial-main', 'enqueued' ) ) {
			wp_add_inline_style( 'nh-editorial-main', $accent );
		}
	}

	if ( $script ) {
		wp_enqueue_script(
			'nh-editorial',
			$script['url'],
			array(),
			$script['version'],
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}
}
add_action( 'wp_enqueue_scripts', 'nh_editorial_enqueue_assets' );

/**
 * Load the main stylesheet without blocking first paint when critical CSS is inlined.
 *
 * @param string $tag    Link tag.
 * @param string $handle Style handle.
 * @param string $href   Stylesheet URL.
 * @return string
 */
function nh_editorial_style_loader_tag( $tag, $handle, $href ) {
	if ( 'nh-editorial-main' !== $handle || ! nh_editorial_use_critical_css() ) {
		return $tag;
	}

	// The stylesheet is enqueued with wp_enqueue_style(); only its tag is rewritten to load without blocking.
	// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
	return '<link rel="stylesheet" id="nh-editorial-main-css" href="' . esc_url( $href ) . '" media="print" onload="this.media=\'all\';this.onload=null;">' . "\n"
		. '<noscript><link rel="stylesheet" href="' . esc_url( $href ) . '"></noscript>' . "\n";
	// phpcs:enable WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
}
add_filter( 'style_loader_tag', 'nh_editorial_style_loader_tag', 10, 3 );

/**
 * Apply the saved or preferred color mode before first paint.
 *
 * @return void
 */
function nh_editorial_print_mode_script() {
	if ( ! nh_editorial_option( 'nh_dark_mode' ) ) {
		return;
	}
	$js = "(function(){try{var m=localStorage.getItem('nh-editorial-color-mode');if(m!=='dark'&&m!=='light'){m=window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';}document.documentElement.setAttribute('data-theme',m);}catch(e){}})();";
	wp_print_inline_script_tag( $js, array( 'id' => 'nh-editorial-mode' ) );
}
add_action( 'wp_head', 'nh_editorial_print_mode_script', 0 );

/**
 * Browser UI colors and no-JavaScript fallbacks.
 *
 * @return void
 */
function nh_editorial_print_head_extras() {
	echo '<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">' . "\n";
	echo '<meta name="theme-color" content="#0b0f17" media="(prefers-color-scheme: dark)">' . "\n";

	// Without JavaScript the collapsed menus and the search panel must stay reachable.
	// !important is needed: the critical CSS has [hidden]{display:none!important}, and the header must not stay sticky
	// (nor squeeze the menu beside the brand) once the whole menu is shown inside it.
	echo '<noscript><style>'
		. '.sub-menu[hidden],.header-search[hidden]{display:block!important}'
		. '.search-toggle,.submenu-toggle,.nav-toggle,.mode-toggle{display:none!important}'
		. '.has-sticky-header .site-header{position:static!important}.header-row{flex-wrap:wrap}'
		. '.primary-nav{display:block!important;position:static!important;flex:1 1 100%!important;order:3;max-height:none!important;padding:0!important;border:0!important;box-shadow:none!important;overflow:visible!important}'
		. '.nav-list{display:flex!important;flex-wrap:wrap}.nav-list>li{display:block!important;border-bottom:0!important}'
		. '.mega-panel{position:static!important;display:block!important;max-height:none!important;padding:0 0 0 14px!important;border:0!important;box-shadow:none!important}'
		. '</style></noscript>' . "\n";
}
add_action( 'wp_head', 'nh_editorial_print_head_extras', 1 );
