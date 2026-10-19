<?php
/** CLI smoke tests; run `php plugins/nguyen-ha-vi-en/tests/smoke.php`. */

// Test tooling: runs from the command line only, never through a web server.
if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/../../../../' );
$GLOBALS['nh_vien_smoke_hooks'] = array();

function add_action( $tag, $callback, $priority = 10 ) {
	$GLOBALS['nh_vien_smoke_hooks'][] = array( $tag, $callback, $priority );
	return true;
}

function get_option( $key, $default = false ) {
	return $default;
}

function nh_vien_expect( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

require dirname( __DIR__ ) . '/nguyen-ha-vi-en.php';

nh_vien_expect( '0.4.0' === NH_VIEN_VERSION, 'Version mismatch' );
nh_vien_expect( 1 === count( $GLOBALS['nh_vien_smoke_hooks'] ), 'Expected one bootstrap hook' );
nh_vien_expect( 'plugins_loaded' === $GLOBALS['nh_vien_smoke_hooks'][0][0], 'Unexpected WordPress hook' );
nh_vien_expect( 20 === $GLOBALS['nh_vien_smoke_hooks'][0][2], 'Unexpected hook priority' );
call_user_func( $GLOBALS['nh_vien_smoke_hooks'][0][1] );

$plugin = nh_vien();
nh_vien_expect( $plugin instanceof NH_VIEN_Plugin, 'Plugin did not initialize' );
nh_vien_expect( $plugin === NH_VIEN_Plugin::boot(), 'Plugin instance is not stable' );
nh_vien_expect( 'vi' === $plugin->default_language(), 'Must keep Vietnamese as default' );
nh_vien_expect( array( 'vi', 'en' ) === array_keys( $plugin->languages() ), 'Language registry mismatch' );
nh_vien_expect( NH_VIEN_Languages::is_supported( 'VI' ), 'VI should be supported' );
nh_vien_expect( NH_VIEN_Languages::is_supported( ' en ' ), 'Trimmed English should be supported' );
nh_vien_expect( ! NH_VIEN_Languages::is_supported( 'fr' ), 'Unsupported language accepted' );
nh_vien_expect( ! NH_VIEN_Languages::is_supported( array( 'en' ) ), 'Non-string language accepted' );
nh_vien_expect( null === NH_VIEN_Languages::get( '../en' ), 'Unsafe language accepted' );
nh_vien_expect( '' === NH_VIEN_Languages::get( 'vi' )['url_prefix'], 'Vietnamese URL must remain unprefixed' );
nh_vien_expect( 'en' === NH_VIEN_Languages::get( 'en' )['url_prefix'], 'English route metadata mismatch' );

$source_files = array(
	dirname( __DIR__ ) . '/nguyen-ha-vi-en.php',
	dirname( __DIR__ ) . '/includes/class-nh-vien-languages.php',
	dirname( __DIR__ ) . '/includes/class-nh-vien-plugin.php',
);
$forbidden = array(
	'flush_rewrite_rules(', 'add_rewrite_rule(', 'register_activation_hook(',
	'update_option(', 'add_option(', 'delete_option(', 'wp_insert_post(',
	'wp_update_post(', 'update_post_meta(', 'delete_post_meta(',
	'wp_insert_term(', 'wp_delete_term(', 'register_rest_route(',
);
foreach ( $source_files as $file ) {
	$content = file_get_contents( $file );
	foreach ( $forbidden as $symbol ) {
		nh_vien_expect( false === stripos( $content, $symbol ), 'Forbidden write/router API in ' . basename( $file ) . ': ' . $symbol );
	}
}

echo "PASS: plugin scaffold smoke checks + forbidden API scan.\n";
