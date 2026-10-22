<?php
/** Offline checks for the switch of the English side: preflight, setting, rewrite rules, smoke test, rollback, undo. */

// Test tooling: runs from the command line only, never through a web server.
if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/../../../../' );
define( 'OBJECT', 'OBJECT' );
define( 'NH_VIEN_VERSION', '0.5.3' );
define( 'NH_VIEN_PLUGIN_FILE', dirname( __DIR__ ) . '/nguyen-ha-vi-en.php' );

$GLOBALS['env']        = 'production';
$GLOBALS['options']    = array( 'permalink_structure' => '/%postname%/' );
$GLOBALS['writes']     = array();
$GLOBALS['hooks']      = array();
$GLOBALS['fired']      = array();
$GLOBALS['pages']      = array();
$GLOBALS['published']  = array();
$GLOBALS['can_manage'] = true;
$GLOBALS['responses']  = array();
$GLOBALS['requests']   = array();
$GLOBALS['flushes']    = 0;
$GLOBALS['flush_fails'] = false;
$GLOBALS['base_rules'] = array(
	'^wp-json/?$'              => 'index.php?rest_route=/',
	'category/(.+?)/?$'        => 'index.php?category_name=$matches[1]',
	'([^/]+)(?:/([0-9]+))?/?$' => 'index.php?name=$matches[1]&page=$matches[2]',
);
$wp_rewrite = (object) array(
	'extra_rules_top' => array(),
	'extra_rules'     => array(),
);

class WP_Post {
	public $ID;
	public $post_name;
	public $post_status;
	public $post_type;
	public function __construct( $id, $slug, $status = 'publish', $type = 'post' ) {
		$this->ID          = $id;
		$this->post_name   = $slug;
		$this->post_status = $status;
		$this->post_type   = $type;
	}
}
class WP_Error {
	private $code;
	private $message;
	private $data;
	public function __construct( $code = '', $message = '', $data = array() ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_get_environment_type() { return $GLOBALS['env']; }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['options'] ) ? $GLOBALS['options'][ $key ] : $default; }
function update_option( $key, $value, $autoload = null ) {
	if ( array_key_exists( $key, $GLOBALS['options'] ) && $GLOBALS['options'][ $key ] === $value ) {
		return false;
	}
	$GLOBALS['options'][ $key ] = $value;
	$GLOBALS['writes'][]        = array( $key, $value, $autoload );
	return true;
}
function delete_option( $key ) { unset( $GLOBALS['options'][ $key ] ); $GLOBALS['writes'][] = array( $key, null, 'deleted' ); return true; }
function add_action( $tag ) { $GLOBALS['hooks'][] = $tag; }
function add_filter( $tag ) { $GLOBALS['hooks'][] = $tag; }
function do_action( $tag ) { $GLOBALS['fired'][] = func_get_args(); }
function apply_filters( $tag, $value ) { return $value; }
function add_rewrite_rule( $regex, $query, $position ) { global $wp_rewrite; $wp_rewrite->extra_rules_top[ $regex ] = $query; }
function flush_rewrite_rules( $hard = true ) {
	global $wp_rewrite;
	++$GLOBALS['flushes'];
	$GLOBALS['options']['rewrite_rules'] = $GLOBALS['flush_fails']
		? $GLOBALS['base_rules']
		: array_merge( $wp_rewrite->extra_rules_top, $GLOBALS['base_rules'], $wp_rewrite->extra_rules );
}
function get_page_by_path( $path ) { return isset( $GLOBALS['pages'][ $path ] ) ? $GLOBALS['pages'][ $path ] : null; }
function get_posts( $args ) {
	if ( isset( $args['name'] ) ) {
		foreach ( $GLOBALS['published'] as $post ) {
			if ( $post->post_name === $args['name'] && $post->post_type === $args['post_type'] && $post->post_status === $args['post_status'] ) {
				return array( $post->ID );
			}
		}
		return array();
	}
	$found = array();
	foreach ( $GLOBALS['published'] as $post ) {
		if ( in_array( $post->ID, $args['post__in'], true ) && 'publish' === $post->post_status ) {
			$found[] = $post;
		}
	}
	return $found;
}
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function user_trailingslashit( $url ) { return rtrim( $url, '/' ) . '/'; }
function add_query_arg( $key, $value, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $key . '=' . $value; }
function current_user_can( $cap ) { return $GLOBALS['can_manage']; }
function get_current_user_id() { return 7; }
function get_post_types() { return array(); }
function get_taxonomies() { return array(); }
function __( $text, $domain = null ) { return $text; }
function sanitize_text_field( $text ) { return trim( strip_tags( $text ) ); }
function wp_remote_get( $url, $args = array() ) {
	$GLOBALS['requests'][] = array( $url, $args );
	return array_shift( $GLOBALS['responses'] );
}
function wp_remote_retrieve_response_code( $response ) { return is_array( $response ) ? $response['code'] : ''; }
function wp_remote_retrieve_header( $response, $header ) { return is_array( $response ) && isset( $response['headers'][ $header ] ) ? $response['headers'][ $header ] : ''; }
function must( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }
function error_is( $result, $code ) { return is_wp_error( $result ) && $code === $result->get_error_code(); }

require dirname( __DIR__ ) . '/includes/class-nh-vien-languages.php';
require dirname( __DIR__ ) . '/includes/class-nh-vien-links.php';
require dirname( __DIR__ ) . '/includes/class-nh-vien-routes.php';
require dirname( __DIR__ ) . '/includes/class-nh-vien-context.php';
require dirname( __DIR__ ) . '/includes/class-nh-vien-settings.php';

/** An answer of the site to the smoke test. */
function answer( $code, $marker = 'en' ) {
	return array(
		'code'    => $code,
		'headers' => '' === $marker ? array() : array( 'x-nh-vien-lang' => $marker ),
	);
}

/** A fresh request: no memo, no recorded writes. */
function fresh() {
	NH_VIEN_Routes::reset();
	NH_VIEN_Links::flush_cache();
	NH_VIEN_Context::reset();
	$GLOBALS['writes']   = array();
	$GLOBALS['fired']    = array();
	$GLOBALS['requests'] = array();
	$GLOBALS['flushes']  = 0;
}

$ours = NH_VIEN_Routes::rules();
$home = NH_VIEN_Routes::url();

// ---- Nothing happens without the capability.
fresh();
$GLOBALS['can_manage'] = false;
must( error_is( NH_VIEN_Settings::enable( true ), 'nh_vien_forbidden' ) && error_is( NH_VIEN_Settings::disable(), 'nh_vien_forbidden' ) && error_is( NH_VIEN_Settings::undo(), 'nh_vien_forbidden' ), 'A non-administrator must be refused' );
must( array() === $GLOBALS['writes'] && 0 === $GLOBALS['flushes'], 'A refused switch must not write' );
$GLOBALS['can_manage'] = true;

// ---- Production needs the confirmation; the preflight must pass; neither failure writes anything.
fresh();
must( error_is( NH_VIEN_Settings::enable( false ), 'nh_vien_confirm_required' ), 'Production must ask for the confirmation' );
$GLOBALS['options']['permalink_structure'] = '';
$result = NH_VIEN_Settings::enable( true );
must( error_is( $result, 'nh_vien_preflight_failed' ) && isset( $result->get_error_data()['checks'] ) && 409 === $result->get_error_data()['status'], 'A failed preflight must refuse the switch with its checks' );
must( array() === $GLOBALS['writes'] && 0 === $GLOBALS['flushes'] && array() === $GLOBALS['requests'], 'A refused switch must not write, flush or request anything' );
must( ! NH_VIEN_Routes::enabled(), 'The gate must stay closed after a refusal' );
$GLOBALS['options']['permalink_structure'] = '/%postname%/';

// ---- Switching on: setting, rules, smoke test, memory of the previous state.
$GLOBALS['pages']['en']  = new WP_Post( 5, 'en', 'publish', 'page' );
$GLOBALS['responses'][] = answer( 200 );
fresh();
$result = NH_VIEN_Settings::enable( true );
must( is_array( $result ) && true === $result['enabled'] && 'ok' === $result['smoke']['result'], 'The switch on must succeed: ' . ( is_wp_error( $result ) ? $result->get_error_code() : 'ok' ) );
must( '1' === get_option( NH_VIEN_Routes::CONTROL_OPTION ) && NH_VIEN_Routes::enabled() && 'setting_on' === NH_VIEN_Routes::source(), 'The setting must be on' );
must( array( NH_VIEN_Routes::CONTROL_OPTION, '1', true ) === $GLOBALS['writes'][0], 'The setting must be autoloaded (it is read on every request)' );
must( 1 === $GLOBALS['flushes'] && NH_VIEN_Routes::rules_stored() && NH_VIEN_Routes::live(), 'The rules must be stored with one flush' );
must( array_keys( $ours ) === array_slice( array_keys( get_option( 'rewrite_rules' ) ), 0, count( $ours ) ), 'Our rules must come first' );
must( 1 === count( $GLOBALS['requests'] ) && 0 === strpos( $GLOBALS['requests'][0][0], $home . '?nh_vien_probe=' ) && 0 === $GLOBALS['requests'][0][1]['redirection'], 'The smoke test must ask the site for /en/ with a probe parameter and without following redirects' );
must( in_array( array( 'nh_vien_pairs_changed', 0, 0 ), $GLOBALS['fired'], true ), 'The caches must be told' );
$change = NH_VIEN_Settings::last_change();
must( 'enable' === $change['action'] && null === $change['previous']['setting'] && false === $change['previous']['rules_stored'] && 7 === $change['user'] && $change['at'] > 0, 'The previous state must be remembered' );
$status = NH_VIEN_Settings::status();
must( true === $status['enabled'] && true === $status['live'] && 5 === $status['home_page'] && 'production' === $status['environment'] && '0.5.3' === $status['version'] && is_array( $status['preflight'] ), 'The status must describe the switched-on site' );

// Switching on again changes nothing and does not fail.
$GLOBALS['responses'][] = answer( 200 );
fresh();
$result = NH_VIEN_Settings::enable( true );
must( is_array( $result ) && true === $result['enabled'], 'Switching on twice must be harmless' );
must( array() === array_filter( $GLOBALS['writes'], function ( $write ) { return NH_VIEN_Routes::CONTROL_OPTION === $write[0]; } ), 'Switching on twice must not write the setting again' );

// ---- Switching off: the setting, and only our rules leave the stored rules; no flush is needed.
fresh();
$result = NH_VIEN_Settings::disable();
must( is_array( $result ) && false === $result['enabled'] && false === $result['rules_stored'], 'The switch off must succeed' );
must( '0' === get_option( NH_VIEN_Routes::CONTROL_OPTION ) && ! NH_VIEN_Routes::enabled() && 'setting_off' === NH_VIEN_Routes::source(), 'The setting must be off' );
must( $GLOBALS['base_rules'] === get_option( 'rewrite_rules' ), 'Only our rules may leave the stored rules, in order' );
must( 0 === $GLOBALS['flushes'], 'Switching off must not regenerate the rules of everyone else' );
must( array() === $wp_rewrite->extra_rules_top, 'Switching off must drop our runtime rules' );
$change = NH_VIEN_Settings::last_change();
must( 'disable' === $change['action'] && '1' === $change['previous']['setting'] && true === $change['previous']['rules_stored'], 'The switch off must remember the state before it' );

// ---- Undo: back to the state before the last switch (here: on), rules stored again.
fresh();
$result = NH_VIEN_Settings::undo();
must( is_array( $result ) && true === $result['enabled'] && NH_VIEN_Routes::live() && 1 === $GLOBALS['flushes'], 'Undo must restore the switched-on state with its rules' );
must( null === NH_VIEN_Settings::last_change(), 'An undone change cannot be undone again' );
must( error_is( NH_VIEN_Settings::undo(), 'nh_vien_nothing_to_undo' ), 'Nothing to undo must be said' );

// Undo of a switch on goes back to "never set".
fresh();
NH_VIEN_Settings::disable();
fresh();
$GLOBALS['responses'][] = answer( 200 );
NH_VIEN_Settings::enable( true );
$GLOBALS['options'][ NH_VIEN_Routes::CONTROL_OPTION ] = '1';
fresh();
// Pretend the switch on had found no setting at all.
$GLOBALS['options'][ NH_VIEN_Settings::LAST_CHANGE_OPTION ]['previous'] = array( 'setting' => null, 'rules_stored' => false );
NH_VIEN_Settings::undo();
must( ! array_key_exists( NH_VIEN_Routes::CONTROL_OPTION, $GLOBALS['options'] ) && $GLOBALS['base_rules'] === get_option( 'rewrite_rules' ), 'Undo must delete a setting that did not exist before and take our rules out' );

// ---- The smoke test decides: an answer without the marker means something else answered /en/, so back to before.
fresh();
$GLOBALS['responses'][] = answer( 200, '' );
$result = NH_VIEN_Settings::enable( true );
must( error_is( $result, 'nh_vien_smoke_failed' ) && 'failed' === $result->get_error_data()['smoke']['result'] && 200 === $result->get_error_data()['smoke']['status'], 'An answer without the marker must fail the smoke test' );
must( ! array_key_exists( NH_VIEN_Routes::CONTROL_OPTION, $GLOBALS['options'] ) && ! NH_VIEN_Routes::enabled() && $GLOBALS['base_rules'] === get_option( 'rewrite_rules' ) && null === NH_VIEN_Settings::last_change(), 'A failed smoke test must restore the state before the switch' );
must( 1 === $GLOBALS['flushes'], 'The rollback of a switch on removes our rules without a second flush' );
fresh();
$GLOBALS['responses'][] = answer( 404, '' );
must( error_is( NH_VIEN_Settings::enable( true ), 'nh_vien_smoke_failed' ) && ! NH_VIEN_Routes::enabled(), 'A 404 without the marker must fail the smoke test' );
// The marker with a 404 while a home page exists: the English side answered, but wrongly.
fresh();
$GLOBALS['responses'][] = answer( 404 );
must( error_is( NH_VIEN_Settings::enable( true ), 'nh_vien_smoke_failed' ) && ! NH_VIEN_Routes::enabled(), 'A 404 with the marker must fail while the English home page exists' );
// Without a home page and without posts a 404 with the marker is the expected answer.
$GLOBALS['pages'] = array();
fresh();
$GLOBALS['responses'][] = answer( 404 );
$result = NH_VIEN_Settings::enable( true );
must( is_array( $result ) && 'ok' === $result['smoke']['result'] && NH_VIEN_Routes::enabled(), 'A 404 with the marker is fine while there is nothing English to show' );
fresh();
NH_VIEN_Settings::disable();
$GLOBALS['pages']['en'] = new WP_Post( 5, 'en', 'publish', 'page' );
// A blocked, redirected or failed request proves nothing: the switch stays on and says so.
foreach ( array( answer( 403, '' ), answer( 503, '' ), answer( 301, '' ), new WP_Error( 'http_request_failed', 'cURL error 28' ) ) as $response ) {
	fresh();
	$GLOBALS['options'][ NH_VIEN_Routes::CONTROL_OPTION ] = '0';
	$GLOBALS['responses'][] = $response;
	$result                 = NH_VIEN_Settings::enable( true );
	must( is_array( $result ) && 'inconclusive' === $result['smoke']['result'] && NH_VIEN_Routes::enabled() && NH_VIEN_Routes::live(), 'An inconclusive smoke test must leave the switch on' );
	must( '0' === NH_VIEN_Settings::last_change()['previous']['setting'], 'The previous setting must be remembered after an inconclusive smoke test' );
}

// ---- The rules cannot be stored: back to before, with a clear error.
fresh();
NH_VIEN_Settings::disable();
fresh();
$GLOBALS['flush_fails'] = true;
$GLOBALS['responses'][] = answer( 200 );
$result                 = NH_VIEN_Settings::enable( true );
must( error_is( $result, 'nh_vien_rewrite_failed' ) && '0' === get_option( NH_VIEN_Routes::CONTROL_OPTION ) && ! NH_VIEN_Routes::enabled(), 'Unstored rules must restore the previous setting' );
must( array() === $GLOBALS['requests'], 'No smoke test runs when the rules were not stored' );
$GLOBALS['flush_fails'] = false;

// ---- The English site name: cleaned, autoloaded, in the status, removable, administrators only.
fresh();
$result = NH_VIEN_Settings::set_site_name( ' <i>Nguyen Ha</i> Motorbike Rental ' );
must( array( 'site_name_en' => 'Nguyen Ha Motorbike Rental' ) === $result && array( array( NH_VIEN_Settings::SITE_NAME_OPTION, 'Nguyen Ha Motorbike Rental', true ) ) === $GLOBALS['writes'], 'The English site name must be saved clean and autoloaded' );
must( 'Nguyen Ha Motorbike Rental' === NH_VIEN_Settings::status()['site_name_en'], 'The status must report the English site name' );
must( in_array( array( 'nh_vien_pairs_changed', 0, 0 ), $GLOBALS['fired'], true ), 'The caches must be told about the name' );
fresh();
NH_VIEN_Settings::set_site_name( '' );
must( ! array_key_exists( NH_VIEN_Settings::SITE_NAME_OPTION, $GLOBALS['options'] ) && '' === NH_VIEN_Settings::status()['site_name_en'], 'An empty name must remove the setting' );
$GLOBALS['can_manage'] = false;
must( error_is( NH_VIEN_Settings::set_site_name( 'X' ), 'nh_vien_forbidden' ), 'The name needs an administrator' );
$GLOBALS['can_manage'] = true;

// ---- The emergency constant cannot be tested here (constants are final); see rest.php.
echo "PASS: capability, confirmation, preflight refusal, switch on with smoke test, idempotence, switch off, undo, smoke failures and rollback, inconclusive answers, rewrite failure, English site name.\n";
