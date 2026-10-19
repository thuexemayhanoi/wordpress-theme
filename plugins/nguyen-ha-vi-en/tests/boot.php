<?php
/** Offline checks: which hooks NH_VIEN_Plugin::boot() registers for each environment and request context. */

// Test tooling: runs from the command line only, never through a web server.
if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/../../../../' );
define( 'NH_VIEN_VERSION', '0.3.0' );
define( 'NH_VIEN_PLUGIN_FILE', dirname( __DIR__ ) . '/nguyen-ha-vi-en.php' );

$GLOBALS['env']      = 'staging';
$GLOBALS['is_admin'] = false;
$GLOBALS['hooks']    = array();

function wp_get_environment_type() { return $GLOBALS['env']; }
function is_admin() { return $GLOBALS['is_admin']; }
function add_action( $tag ) { $GLOBALS['hooks'][] = $tag; }
function add_filter( $tag ) { $GLOBALS['hooks'][] = $tag; }
function add_shortcode( $tag ) { $GLOBALS['hooks'][] = 'shortcode:' . $tag; }
function must( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }

foreach ( array( 'languages', 'links', 'admin', 'routes', 'switcher', 'plugin' ) as $part ) {
	require dirname( __DIR__ ) . '/includes/class-nh-vien-' . $part . '.php';
}

/** Run the plugin bootstrap in one context and return the sorted list of hooks it registered. */
function boot_hooks( $env, $admin ) {
	$GLOBALS['env']      = $env;
	$GLOBALS['is_admin'] = $admin;
	$GLOBALS['hooks']    = array();
	NH_VIEN_Plugin::boot();
	$hooks = $GLOBALS['hooks'];
	sort( $hooks );
	return $hooks;
}

/** Sorted union of hook lists. */
function hooks_of( ...$lists ) {
	$all = array_merge( ...$lists );
	sort( $all );
	return $all;
}

$admin    = array( 'admin_menu', 'admin_post_nh_vien_link', 'admin_post_nh_vien_unlink' );
$routes   = array( 'init', 'parse_request', 'query_vars', 'redirect_canonical' );
$switcher = array( 'shortcode:nh_vien_switcher', 'the_content', 'wp_enqueue_scripts' );

// Without the explicit constant nothing about the English preview is registered, whatever the environment.
must( array() === boot_hooks( 'staging', false ), 'Front end registered hooks without the explicit constant' );
must( hooks_of( $admin ) === boot_hooks( 'staging', true ), 'Admin must only get its own hooks without the explicit constant' );

define( 'NH_VIEN_ENABLE_EN_ROUTES', true );

// The constant alone is not enough on production.
must( array() === boot_hooks( 'production', false ), 'Production front end registered hooks' );
must( hooks_of( $admin ) === boot_hooks( 'production', true ), 'Production admin registered preview hooks' );

// Staging, administration: the rewrite rule exists in the request that flushes the stored rules.
must( hooks_of( $admin, $routes ) === boot_hooks( 'staging', true ), 'Admin request misses the rewrite registration or got front-end hooks' );

// Staging, front end: the route and the switcher, nothing from the administration.
must( hooks_of( $routes, $switcher ) === boot_hooks( 'staging', false ), 'Front end request hooks differ' );

// The other development environments behave like staging.
must( hooks_of( $admin, $routes ) === boot_hooks( 'local', true ), 'Local admin hooks differ' );
must( hooks_of( $routes, $switcher ) === boot_hooks( 'development', false ), 'Development front end hooks differ' );

echo "PASS: bootstrap hooks per environment, explicit gate, and rewrite registration in the admin and on the front end.\n";
