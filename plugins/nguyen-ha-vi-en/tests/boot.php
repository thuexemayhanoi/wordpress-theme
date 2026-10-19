<?php
/** Offline checks: which hooks NH_VIEN_Plugin::boot() registers for each environment and request context. */

// Test tooling: runs from the command line only, never through a web server.
if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/../../../../' );
define( 'NH_VIEN_VERSION', '0.5.1' );
define( 'NH_VIEN_PLUGIN_FILE', dirname( __DIR__ ) . '/nguyen-ha-vi-en.php' );

$GLOBALS['env']      = 'staging';
$GLOBALS['is_admin'] = false;
$GLOBALS['hooks']    = array();
$GLOBALS['callbacks'] = array();
$GLOBALS['options']  = array();

function wp_get_environment_type() { return $GLOBALS['env']; }
function is_admin() { return $GLOBALS['is_admin']; }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['options'] ) ? $GLOBALS['options'][ $key ] : $default; }
function add_action( $tag, $callback = null ) { $GLOBALS['hooks'][] = $tag; $GLOBALS['callbacks'][ $tag ][] = $callback; }
function add_filter( $tag, $callback = null ) { $GLOBALS['hooks'][] = $tag; $GLOBALS['callbacks'][ $tag ][] = $callback; }
function add_shortcode( $tag, $callback = null ) { $GLOBALS['hooks'][] = 'shortcode:' . $tag; $GLOBALS['callbacks'][ 'shortcode:' . $tag ][] = $callback; }
function plugin_basename( $file ) { return basename( dirname( $file ) ) . '/' . basename( $file ); }
function must( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }

/** Every callback that was registered must exist and be public: WordPress would fatal on the first request otherwise. */
function callbacks_exist( $where ) {
	foreach ( $GLOBALS['callbacks'] as $tag => $callbacks ) {
		foreach ( $callbacks as $callback ) {
			must( is_callable( $callback ), "The callback registered for $tag does not exist or is not public ($where): " . json_encode( $callback ) );
		}
	}
}

foreach ( array( 'languages', 'links', 'admin', 'gate', 'routes', 'context', 'urls', 'query', 'cache', 'content', 'seo', 'switcher', 'plugin' ) as $part ) {
	require dirname( __DIR__ ) . '/includes/class-nh-vien-' . $part . '.php';
}

/** Run the plugin bootstrap in one context and return the sorted list of hooks it registered. */
function boot_hooks( $env, $admin ) {
	$GLOBALS['env']       = $env;
	$GLOBALS['is_admin']  = $admin;
	$GLOBALS['hooks']     = array();
	$GLOBALS['callbacks'] = array();
	NH_VIEN_Plugin::boot();
	callbacks_exist( "$env, " . ( $admin ? 'admin' : 'front end' ) );
	$hooks = $GLOBALS['hooks'];
	sort( $hooks );
	return $hooks;
}

/** The hooks the given modules register on their own, sorted. */
function hooks_of( ...$classes ) {
	$GLOBALS['env']       = 'staging';
	$GLOBALS['hooks']     = array();
	$GLOBALS['callbacks'] = array();
	foreach ( $classes as $class ) {
		$class::boot();
	}
	callbacks_exist( implode( ', ', $classes ) );
	$hooks = $GLOBALS['hooks'];
	sort( $hooks );
	return $hooks;
}

// Without the explicit constant only the pair-change listener exists (it purges caches and does nothing else).
must( array( 'nh_vien_pairs_changed' ) === boot_hooks( 'staging', false ), 'Front end registered hooks without the explicit constant' );
$admin_only = hooks_of( 'NH_VIEN_Cache', 'NH_VIEN_Admin' );
must( $admin_only === boot_hooks( 'staging', true ), 'Admin must only get its own hooks without the explicit constant' );

// Rules stored by an earlier run must never answer with the Vietnamese home page: the closed gate still guards the request.
$own = NH_VIEN_Routes::rules();
foreach ( array( NH_VIEN_Routes::HOME, NH_VIEN_Routes::REWRITE ) as $one ) {
	// One rule left over is enough, even when all the others are gone.
	$GLOBALS['options']['rewrite_rules'] = array( '^wp-json/?$' => 'index.php?rest_route=/', $one => $own[ $one ] );
	$hooks                               = boot_hooks( 'staging', false );
	must( array( 'nh_vien_pairs_changed', 'parse_request' ) === $hooks, 'A stale stored rule must be answered with a 404 even when the gate is closed: ' . $one );
}
// The same expression with another query belongs to another plugin: it is none of our business.
$GLOBALS['options']['rewrite_rules'] = array( NH_VIEN_Routes::HOME => 'index.php?other=1' );
must( array( 'nh_vien_pairs_changed' ) === boot_hooks( 'staging', false ), 'A rule of another plugin must not be guarded' );
$GLOBALS['options']['rewrite_rules'] = 'corrupt';
must( array( 'nh_vien_pairs_changed' ) === boot_hooks( 'staging', false ), 'A corrupt option must not be guarded' );
$GLOBALS['options'] = array();

// The switch of the dashboard (no constant in wp-config.php) opens the gate; a production site also needs its confirmation.
must( in_array( 'admin_post_nh_vien_toggle', $admin_only, true ), 'The administration must offer the switch' );
must( in_array( 'plugin_action_links_nguyen-ha-vi-en/nguyen-ha-vi-en.php', $admin_only, true ), 'The Plugins screen must link to the switch' );
$GLOBALS['options']['home']                 = 'https://example.test';
$GLOBALS['options'][ NH_VIEN_Gate::OPTION ] = array(
	'on'        => true,
	'site'      => substr( md5( 'https://example.test' ), 0, 16 ),
	'confirmed' => false,
	'user'      => 1,
	'time'      => 1800000000,
);
$dashboard_front = boot_hooks( 'staging', false );
must( in_array( 'init', $dashboard_front, true ) && in_array( 'pre_get_posts', $dashboard_front, true ) && in_array( 'wp_body_open', $dashboard_front, true ), 'The switch of the dashboard must open the gate on staging' );
must( array( 'nh_vien_pairs_changed' ) === boot_hooks( 'production', false ), 'A production site that was not confirmed must stay closed' );
$GLOBALS['options'][ NH_VIEN_Gate::OPTION ]['confirmed'] = true;
must( $dashboard_front === boot_hooks( 'production', false ), 'A confirmed production site must run the English side' );
$GLOBALS['options'][ NH_VIEN_Gate::OPTION ]['site'] = substr( md5( 'https://other.example.test' ), 0, 16 );
must( array( 'nh_vien_pairs_changed' ) === boot_hooks( 'staging', false ), 'A decision taken at another address must not open the gate' );
$GLOBALS['options'] = array();

define( 'NH_VIEN_ENABLE_EN_ROUTES', true );

// The constant alone is not enough on production.
must( array( 'nh_vien_pairs_changed' ) === boot_hooks( 'production', false ), 'Production front end registered hooks' );
must( $admin_only === boot_hooks( 'production', true ), 'Production admin registered English hooks' );

// Staging, administration: the rewrite rules exist in the request that stores them, and nothing of the front end does.
$expected_admin = hooks_of( 'NH_VIEN_Routes', 'NH_VIEN_Context', 'NH_VIEN_Urls', 'NH_VIEN_Cache', 'NH_VIEN_Admin' );
$booted_admin   = boot_hooks( 'staging', true );
must( $expected_admin === $booted_admin, 'Admin request hooks differ' );
must( in_array( 'init', $booted_admin, true ) && in_array( 'admin_menu', $booted_admin, true ), 'Admin request misses the rewrite registration or the menu' );
foreach ( array( 'wp_body_open', 'nh_editorial_after_skip_link', 'pre_get_posts', 'rest_post_search_query', 'wpseo_canonical', 'the_content' ) as $front_hook ) {
	must( ! in_array( $front_hook, $booted_admin, true ), "Front-end hook $front_hook registered in the admin" );
}

// Staging, front end: routes, addresses, queries, content, SEO and the switcher; nothing from the administration.
$expected_front = hooks_of( 'NH_VIEN_Routes', 'NH_VIEN_Context', 'NH_VIEN_Urls', 'NH_VIEN_Cache', 'NH_VIEN_Query', 'NH_VIEN_Content', 'NH_VIEN_Seo', 'NH_VIEN_Switcher' );
$booted_front   = boot_hooks( 'staging', false );
must( $expected_front === $booted_front, 'Front end request hooks differ' );
foreach ( array( 'init', 'parse_request', 'template_redirect', 'pre_get_posts', 'post_link', 'rest_url', 'rest_post_search_query', 'wp_body_open', 'nh_editorial_after_skip_link', 'wpseo_canonical', 'wp_head', 'the_title', 'wp_nav_menu_args', 'pre_wp_nav_menu', 'wp', 'nh_seo_purge_post_urls', 'shortcode:nh_vien_switcher', 'shortcode:nh_vien_posts' ) as $front_hook ) {
	must( in_array( $front_hook, $booted_front, true ), "Front-end hook $front_hook missing" );
}
foreach ( array( 'admin_menu', 'admin_post_nh_vien_link', 'admin_post_nh_vien_flush', 'admin_post_nh_vien_toggle', 'plugin_action_links_nguyen-ha-vi-en/nguyen-ha-vi-en.php' ) as $admin_hook ) {
	must( ! in_array( $admin_hook, $booted_front, true ), "Admin hook $admin_hook registered on the front end" );
}

// The other development environments behave like staging.
must( $expected_admin === boot_hooks( 'local', true ), 'Local admin hooks differ' );
must( $expected_front === boot_hooks( 'development', false ), 'Development front end hooks differ' );

// A production site that sets the second constant behaves like staging.
define( 'NH_VIEN_ALLOW_PRODUCTION', true );
must( $expected_front === boot_hooks( 'production', false ), 'Production with both constants must run the English side' );

echo "PASS: bootstrap hooks per environment and context, closed gate, switch of the dashboard, stale rules, rewrite registration in the admin and on the front end.\n";
