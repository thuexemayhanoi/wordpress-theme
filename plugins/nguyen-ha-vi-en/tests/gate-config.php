<?php
/**
 * Offline checks of the emergency brake: wp-config.php defines NH_VIEN_ENABLE_EN_ROUTES as anything but true. The English
 * side is then closed whatever the dashboard decided, and the dashboard cannot switch it back on. (The constant true,
 * and the production constant, are checked in routes.php, boot.php and gate.php.)
 */

// Test tooling: runs from the command line only, never through a web server.
if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/../../../../' );
define( 'OBJECT', 'OBJECT' );
define( 'NH_VIEN_VERSION', '0.5.1' );
define( 'NH_VIEN_PLUGIN_FILE', dirname( __DIR__ ) . '/nguyen-ha-vi-en.php' );
define( 'NH_VIEN_ENABLE_EN_ROUTES', false );

$GLOBALS['env']     = 'staging';
$GLOBALS['options'] = array();
$GLOBALS['writes']  = 0;
$GLOBALS['hooks']   = array();

class WP_Post {}
class WP_Error {}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_get_environment_type() { return $GLOBALS['env']; }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['options'] ) ? $GLOBALS['options'][ $key ] : $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][ $key ] = $value; ++$GLOBALS['writes']; return true; }
function delete_option( $key ) { unset( $GLOBALS['options'][ $key ] ); ++$GLOBALS['writes']; return true; }
function add_action( $tag ) { $GLOBALS['hooks'][] = $tag; }
function add_filter( $tag ) { $GLOBALS['hooks'][] = $tag; }
function do_action( $tag ) {}
function flush_rewrite_rules( $hard = true ) { throw new RuntimeException( 'The rewrite rules must not be flushed when wp-config.php decides' ); }
function must( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }

foreach ( array( 'languages', 'links', 'gate', 'routes', 'context' ) as $part ) {
	require dirname( __DIR__ ) . '/includes/class-nh-vien-' . $part . '.php';
}

// The dashboard said yes, wp-config.php says no: closed, on every environment.
$GLOBALS['options']['home']                 = 'https://example.test';
$GLOBALS['options'][ NH_VIEN_Gate::OPTION ] = array(
	'on'        => true,
	'site'      => substr( md5( 'https://example.test' ), 0, 16 ),
	'confirmed' => true,
	'user'      => 1,
	'time'      => 1800000000,
);
must( NH_VIEN_Gate::managed_by_config() && NH_VIEN_Gate::state()['on'], 'The dashboard decision exists and wp-config.php defines the constant' );
foreach ( array( 'local', 'development', 'staging', 'production' ) as $env ) {
	$GLOBALS['env'] = $env;
	NH_VIEN_Routes::reset();
	must( ! NH_VIEN_Gate::open() && ! NH_VIEN_Routes::enabled() && ! NH_VIEN_Routes::live(), "A constant that says false must close $env whatever the dashboard says" );
}
$GLOBALS['env'] = 'staging';

// Nothing registers while it says no (the closed gate guards stored rules only, checked in boot.php).
NH_VIEN_Routes::boot();
must( array() === $GLOBALS['hooks'], 'A closed gate must register nothing: ' . implode( ',', $GLOBALS['hooks'] ) );

// The dashboard cannot switch it back on, nor off, and changes nothing.
$before = $GLOBALS['options'];
must( 'nh_vien_config_controls' === NH_VIEN_Gate::switch_on( true, 1 ), 'Switching on against the constant must be refused' );
must( 'nh_vien_config_controls' === NH_VIEN_Gate::switch_off( 1 ), 'Switching off against the constant must be refused' );
must( $before === $GLOBALS['options'] && 0 === $GLOBALS['writes'], 'Refused switches must change nothing' );

// Deactivation still cleans up: its own rules go, the decision is forgotten.
$GLOBALS['options']['rewrite_rules'] = array_merge( NH_VIEN_Routes::rules(), array( 'other/?$' => 'index.php?x=1' ) );
NH_VIEN_Routes::deactivate();
must( array( 'other/?$' => 'index.php?x=1' ) === $GLOBALS['options']['rewrite_rules'], 'Deactivation must remove the rules of the plugin and keep the others' );
must( ! array_key_exists( NH_VIEN_Gate::OPTION, $GLOBALS['options'] ), 'Deactivation must forget the decision' );

echo "PASS: a wp-config.php constant that is not true closes the gate whatever the dashboard says, and the dashboard cannot override it.\n";
