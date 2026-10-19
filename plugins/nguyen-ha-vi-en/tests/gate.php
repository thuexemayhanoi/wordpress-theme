<?php
/**
 * Offline checks of the gate when wp-config.php does NOT define NH_VIEN_ENABLE_EN_ROUTES: the switch of the dashboard,
 * the confirmation of a production site, the address of the site, the removal of the rules, the refusals and the
 * rollback. Constants cannot be undefined in PHP, so the script ends with the two it can still test (a production
 * constant that says no, then the first constant that takes the decision away from the dashboard); the constant
 * that says false has its own script, gate-config.php.
 */

// Test tooling: runs from the command line only, never through a web server.
if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/../../../../' );
define( 'OBJECT', 'OBJECT' );
define( 'NH_VIEN_VERSION', '0.5.1' );
define( 'NH_VIEN_PLUGIN_FILE', dirname( __DIR__ ) . '/nguyen-ha-vi-en.php' );

$GLOBALS['env']          = 'staging';
$GLOBALS['options']      = array();
$GLOBALS['autoload']     = array();
$GLOBALS['writes']       = 0;
$GLOBALS['actions']      = array();
$GLOBALS['top']          = array();
$GLOBALS['flushes']      = 0;
$GLOBALS['flush_works']  = true;
$GLOBALS['refuse_write'] = '';
$GLOBALS['published']    = array();
$GLOBALS['base_rules']   = array(
	'^wp-json/?$'              => 'index.php?rest_route=/',
	'category/(.+?)/?$'        => 'index.php?category_name=$matches[1]',
	'([^/]+)(?:/([0-9]+))?/?$' => 'index.php?name=$matches[1]&page=$matches[2]',
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
	public function __construct( $code = '', $message = '' ) {}
}
class WP_Term {}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_get_environment_type() { return $GLOBALS['env']; }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['options'] ) ? $GLOBALS['options'][ $key ] : $default; }
function update_option( $key, $value, $autoload = null ) {
	if ( $GLOBALS['refuse_write'] === $key ) {
		return false;
	}
	$GLOBALS['options'][ $key ]  = $value;
	$GLOBALS['autoload'][ $key ] = $autoload;
	++$GLOBALS['writes'];
	return true;
}
function delete_option( $key ) { unset( $GLOBALS['options'][ $key ] ); ++$GLOBALS['writes']; return true; }
function do_action( $tag ) { $GLOBALS['actions'][] = $tag; }
function add_rewrite_rule( $regex, $query, $position ) { if ( 'top' === $position ) { $GLOBALS['top'][ $regex ] = $query; } }
function flush_rewrite_rules( $hard = true ) {
	++$GLOBALS['flushes'];
	$GLOBALS['options']['rewrite_rules'] = $GLOBALS['flush_works'] ? array_merge( $GLOBALS['top'], $GLOBALS['base_rules'] ) : $GLOBALS['base_rules'];
}
function get_page_by_path( $path ) { return null; }
$GLOBALS['categories'] = array();
function get_term_by( $field, $value, $taxonomy ) { return isset( $GLOBALS['categories'][ $value ] ) ? $GLOBALS['categories'][ $value ] : false; }
function get_posts( $args ) {
	$found = array();
	if ( isset( $args['name'] ) ) {
		foreach ( $GLOBALS['published'] as $post ) {
			if ( $post->post_name === $args['name'] && $post->post_type === $args['post_type'] && $post->post_status === $args['post_status'] ) {
				$found[] = $post->ID;
			}
		}
	}
	return $found;
}
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function user_trailingslashit( $url ) { return rtrim( $url, '/' ) . '/'; }
function must( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }

foreach ( array( 'languages', 'links', 'gate', 'routes', 'context' ) as $part ) {
	require dirname( __DIR__ ) . '/includes/class-nh-vien-' . $part . '.php';
}

/** A fresh request: no memo, no rule registered yet, nothing recorded. */
function fresh() {
	NH_VIEN_Routes::reset();
	NH_VIEN_Links::flush_cache();
	NH_VIEN_Context::reset();
	$GLOBALS['top']     = array();
	$GLOBALS['actions'] = array();
	$GLOBALS['writes']  = 0;
	$GLOBALS['flushes'] = 0;
}

/** The plugin's own keys among the stored rewrite rules. */
function stored_ours() {
	$stored = isset( $GLOBALS['options']['rewrite_rules'] ) && is_array( $GLOBALS['options']['rewrite_rules'] ) ? $GLOBALS['options']['rewrite_rules'] : array();
	return array_intersect_key( $stored, NH_VIEN_Routes::rules() );
}

$GLOBALS['options']['home']                = 'https://example.test';
$GLOBALS['options']['permalink_structure'] = '/%postname%/';
$GLOBALS['options']['rewrite_rules']       = $GLOBALS['base_rules'];

// ---- A site that never switched anything on: closed everywhere, state empty.
foreach ( array( 'local', 'development', 'staging', 'production' ) as $env ) {
	$GLOBALS['env'] = $env;
	fresh();
	must( ! NH_VIEN_Gate::open() && ! NH_VIEN_Routes::enabled(), "A fresh install must be closed on $env" );
}
$GLOBALS['env'] = 'staging';
fresh();
must( ! NH_VIEN_Gate::managed_by_config(), 'No constant is defined at this point' );
must( array(
	'on'        => false,
	'stale'     => false,
	'confirmed' => false,
	'user'      => 0,
	'time'      => 0,
) === NH_VIEN_Gate::state(), 'The empty state is wrong' );
must( ! NH_VIEN_Gate::production() && ! NH_VIEN_Gate::confirmation_needed(), 'Staging is not production and needs no confirmation' );

// ---- Corrupt or foreign values never open the gate.
foreach ( array( 'yes', '1', 1, true, array( 'on' => 'yes' ), array( 'on' => 1, 'site' => 'x' ), array( 'on' => true ), array( 'on' => true, 'site' => 12345 ), array( 'on' => false, 'site' => 'x' ), null ) as $bad ) {
	$GLOBALS['options'][ NH_VIEN_Gate::OPTION ] = $bad;
	fresh();
	must( ! NH_VIEN_Gate::open(), 'A corrupt value opened the gate: ' . json_encode( $bad ) );
}
// The values the unmerged 0.5.1 branches used are not ours either.
unset( $GLOBALS['options'][ NH_VIEN_Gate::OPTION ] );
foreach ( array( 'nh_vien_routes_enabled_v1' => '1', 'nh_vien_production_enabled_v1' => 'yes' ) as $key => $value ) {
	$GLOBALS['options'][ $key ] = $value;
}
fresh();
must( ! NH_VIEN_Gate::open(), 'The options of the other branches must not open the gate' );
unset( $GLOBALS['options']['nh_vien_routes_enabled_v1'], $GLOBALS['options']['nh_vien_production_enabled_v1'] );

// ---- Switching on, on a staging site: no confirmation, the rules are registered and stored, the decision is kept.
fresh();
$result = NH_VIEN_Gate::switch_on( false, 7 );
must( 'routes_enabled' === $result, 'Switching on a staging site failed: ' . $result );
must( 0 === $GLOBALS['flushes'] && 8 === count( $GLOBALS['top'] ), 'Switching on must register the 8 rules and put them on top of the stored ones without regenerating the others' );
must( 8 === count( stored_ours() ), 'The English rules must be stored' );
must( array_keys( NH_VIEN_Routes::rules() ) === array_slice( array_keys( $GLOBALS['options']['rewrite_rules'] ), 0, 8 ), 'The English rules come first' );
must( $GLOBALS['base_rules'] === array_slice( $GLOBALS['options']['rewrite_rules'], 8, null, true ), 'Every other stored rule must stay as it was, in its order' );
must( true === $GLOBALS['autoload'][ NH_VIEN_Gate::OPTION ], 'The decision is read on every request: it must be autoloaded' );
must( in_array( 'nh_vien_pairs_changed', $GLOBALS['actions'], true ), 'Caches of both languages must be purged' );
fresh();
$state = NH_VIEN_Gate::state();
must( $state['on'] && ! $state['stale'] && ! $state['confirmed'] && 7 === $state['user'] && $state['time'] > 1700000000, 'The stored decision is wrong: ' . json_encode( $state ) );
must( NH_VIEN_Gate::open() && NH_VIEN_Routes::enabled() && NH_VIEN_Routes::live(), 'The gate must be open and live after switching on' );
foreach ( array( 'local', 'development', 'staging' ) as $env ) {
	$GLOBALS['env'] = $env;
	fresh();
	must( NH_VIEN_Gate::open(), "The decision must hold on $env" );
}

// ---- The same decision does not carry over to a production site: the confirmation was never given there.
$GLOBALS['env'] = 'production';
fresh();
must( NH_VIEN_Gate::production() && NH_VIEN_Gate::confirmation_needed(), 'Production needs the confirmation' );
must( ! NH_VIEN_Gate::open() && ! NH_VIEN_Routes::live(), 'A decision taken on staging must not open a production site' );
$before                = $GLOBALS['options'];
$result                = NH_VIEN_Gate::switch_on( false, 7 );
must( 'nh_vien_confirm_required' === $result && $before === $GLOBALS['options'] && 0 === $GLOBALS['writes'] && 0 === $GLOBALS['flushes'], 'Switching on a production site without the confirmation must change nothing: ' . $result );
foreach ( array( 'yes', 1, 'true', null, array() ) as $not_true ) {
	$result = NH_VIEN_Gate::switch_on( $not_true, 7 );
	must( 'nh_vien_confirm_required' === $result, 'Only a real true confirms: ' . json_encode( $not_true ) );
}
fresh();
$result = NH_VIEN_Gate::switch_on( true, 9 );
must( 'routes_enabled' === $result, 'Switching on a production site with the confirmation failed: ' . $result );
fresh();
$state = NH_VIEN_Gate::state();
must( $state['on'] && $state['confirmed'] && 9 === $state['user'], 'The confirmation must be kept: ' . json_encode( $state ) );
must( NH_VIEN_Gate::open() && NH_VIEN_Routes::live(), 'A confirmed production site must be open' );

// ---- A database copied to another address (staging copy, new domain, https) stays closed until it is switched on there.
$GLOBALS['options']['home'] = 'https://staging.example.test';
fresh();
$state = NH_VIEN_Gate::state();
must( ! $state['on'] && $state['stale'] && ! NH_VIEN_Gate::open(), 'Another address must close the gate and say why' );
$GLOBALS['options']['home'] = 'https://example.test/';
fresh();
must( NH_VIEN_Gate::open(), 'The trailing slash of the address must not matter' );
$GLOBALS['options']['home'] = 'https://example.test';
fresh();
must( NH_VIEN_Gate::open() && ! NH_VIEN_Gate::state()['stale'], 'Back at the address it was switched on at, the decision counts again' );

// ---- Switching off: the decision changes, the rules of the plugin leave the stored rules and nothing else does.
$GLOBALS['options']['rewrite_rules'] = array_merge( NH_VIEN_Routes::rules(), $GLOBALS['base_rules'], array( 'other-plugin/?$' => 'index.php?other=1' ) );
fresh();
$result = NH_VIEN_Gate::switch_off( 3 );
must( 'routes_disabled' === $result, 'Switching off failed: ' . $result );
must( 0 === count( stored_ours() ), 'Switching off must remove the rules of the plugin' );
must( array_merge( $GLOBALS['base_rules'], array( 'other-plugin/?$' => 'index.php?other=1' ) ) === $GLOBALS['options']['rewrite_rules'], 'Switching off must keep every other rule, in order' );
must( 0 === $GLOBALS['flushes'], 'Switching off must not regenerate the rules of other plugins' );
must( in_array( 'nh_vien_pairs_changed', $GLOBALS['actions'], true ), 'Caches must be purged after switching off' );
fresh();
$state = NH_VIEN_Gate::state();
must( ! $state['on'] && 3 === $state['user'] && $state['time'] > 0 && ! NH_VIEN_Gate::open() && ! NH_VIEN_Routes::live(), 'The closed state is wrong: ' . json_encode( $state ) );
// Closing twice is harmless.
$result = NH_VIEN_Gate::switch_off( 3 );
must( 'routes_disabled' === $result && 0 === count( stored_ours() ), 'Switching off twice must work' );

// ---- A rule of another plugin that only looks like one of ours (the same expression, another query) is not ours.
$lookalike                           = array( NH_VIEN_Routes::HOME => 'index.php?other=1' );
$GLOBALS['options']['rewrite_rules'] = $lookalike + $GLOBALS['base_rules'];
fresh();
must( ! NH_VIEN_Routes::rules_stored() && 0 === count( array_filter( stored_ours(), function ( $query, $regex ) { return NH_VIEN_Routes::rules()[ $regex ] === $query; }, ARRAY_FILTER_USE_BOTH ) ), 'A lookalike rule must not count as stored' );
must( true === NH_VIEN_Routes::remove_stored_rules() && $lookalike + $GLOBALS['base_rules'] === $GLOBALS['options']['rewrite_rules'], 'A lookalike rule of another plugin must stay when the rules of this plugin are removed' );
$GLOBALS['options']['rewrite_rules'] = $GLOBALS['base_rules'];

// ---- No stored rules to extend (plain permalinks never stored any): WordPress regenerates them, with ours registered.
$GLOBALS['env']                      = 'staging';
$GLOBALS['options']['rewrite_rules'] = array();
NH_VIEN_Gate::switch_off( 7 );
fresh();
must( 'routes_enabled' === NH_VIEN_Gate::switch_on( false, 7 ) && 1 === $GLOBALS['flushes'] && 8 === count( stored_ours() ), 'Without stored rules WordPress must regenerate them once, with the rules of the plugin' );
$GLOBALS['options']['rewrite_rules'] = $GLOBALS['base_rules'];
NH_VIEN_Gate::switch_off( 7 );

// ---- Switching off when the decision cannot be saved: it is still on, so its rules are left alone and the answer says so.
$GLOBALS['options']['rewrite_rules'] = array_merge( NH_VIEN_Routes::rules(), $GLOBALS['base_rules'] );
fresh();
NH_VIEN_Gate::switch_on( false, 7 );
fresh();
$GLOBALS['refuse_write'] = NH_VIEN_Gate::OPTION;
$before                  = $GLOBALS['options'];
$result                  = NH_VIEN_Gate::switch_off( 3 );
must( 'nh_vien_gate_save_failed' === $result && $before === $GLOBALS['options'] && 8 === count( stored_ours() ) && ! in_array( 'nh_vien_pairs_changed', $GLOBALS['actions'], true ), 'A decision that cannot be saved must leave the rules alone and say so: ' . $result );
$GLOBALS['refuse_write'] = '';
NH_VIEN_Gate::switch_off( 7 );

// ---- Switching off when the rules cannot be removed: the gate is closed anyway.
$GLOBALS['options']['rewrite_rules'] = array_merge( NH_VIEN_Routes::rules(), $GLOBALS['base_rules'] );
$GLOBALS['refuse_write']             = 'rewrite_rules';
fresh();
$result = NH_VIEN_Gate::switch_off( 3 );
must( 'nh_vien_rules_left' === $result && ! NH_VIEN_Gate::open(), 'A stuck rule must be reported, and the gate must be closed: ' . $result );
$GLOBALS['refuse_write'] = '';
$GLOBALS['options']['rewrite_rules'] = $GLOBALS['base_rules'];

// ---- Refusals change nothing.
$GLOBALS['env'] = 'staging';
$GLOBALS['options']['permalink_structure'] = '';
fresh();
$before = $GLOBALS['options'];
$result = NH_VIEN_Gate::switch_on( false, 7 );
must( 'nh_vien_pretty_links_required' === $result && $before === $GLOBALS['options'] && 0 === $GLOBALS['writes'] && 0 === $GLOBALS['flushes'], 'Plain permalinks must refuse: ' . $result );
$GLOBALS['options']['permalink_structure'] = '/%postname%/';
$GLOBALS['published']                      = array( new WP_Post( 1, 'en' ) );
fresh();
$before = $GLOBALS['options'];
$result = NH_VIEN_Gate::switch_on( false, 7 );
must( 'address_taken' === $result && $before === $GLOBALS['options'] && 0 === $GLOBALS['writes'] && 0 === $GLOBALS['flushes'], 'A post at /en/ must refuse: ' . $result );
// The permalink structure that puts the category into the address: a category called en has the posts of /en/ already.
$GLOBALS['options']['permalink_structure'] = '/%category%/%postname%/';
$GLOBALS['categories']                     = array( 'en' => new WP_Term() );
fresh();
$before = $GLOBALS['options'];
$result = NH_VIEN_Gate::switch_on( false, 7 );
must( 'address_taken' === $result && $before === $GLOBALS['options'] && 0 === $GLOBALS['writes'], 'A category called en in the address of its posts must refuse: ' . $result );
$GLOBALS['options']['permalink_structure'] = '/%postname%/';
fresh();
must( ! NH_VIEN_Routes::category_at_en(), 'A category called en is harmless when the address of a post has no category' );
$GLOBALS['categories']                     = array();
$GLOBALS['options']['permalink_structure'] = '/%category%/%postname%/';
fresh();
must( ! NH_VIEN_Routes::category_at_en(), 'No category called en, no problem' );
$GLOBALS['options']['permalink_structure'] = '/index.php/%postname%/';
fresh();
$before = $GLOBALS['options'];
$result = NH_VIEN_Gate::switch_on( false, 7 );
must( 'nh_vien_pretty_links_required' === $result && $before === $GLOBALS['options'] && 0 === $GLOBALS['writes'], 'Permalinks that start with /index.php/ must refuse: ' . $result );
$GLOBALS['options']['permalink_structure'] = '/%postname%/';
$GLOBALS['published'] = array( new WP_Post( 1, 'en', 'publish', 'page' ), new WP_Post( 2, 'en', 'draft' ) );
fresh();
must( 'routes_enabled' === NH_VIEN_Gate::switch_on( false, 7 ), 'A page or a draft with the slug en must not refuse' );
$GLOBALS['published'] = array();
NH_VIEN_Gate::switch_off( 7 );

// ---- A switch-on that WordPress does not store is undone: no decision, no rule.
$GLOBALS['flush_works']  = false;
$GLOBALS['refuse_write'] = 'rewrite_rules';
unset( $GLOBALS['options'][ NH_VIEN_Gate::OPTION ] );
fresh();
$result = NH_VIEN_Gate::switch_on( false, 7 );
must( 'nh_vien_rewrite_failed' === $result, 'A failed store must be reported: ' . $result );
must( ! array_key_exists( NH_VIEN_Gate::OPTION, $GLOBALS['options'] ), 'A failed switch-on without a previous decision must leave no decision' );
must( 0 === count( stored_ours() ) && ! NH_VIEN_Gate::open(), 'A failed switch-on must leave the gate closed and no rule behind' );
// With a previous (closed) decision, that decision comes back.
NH_VIEN_Gate::switch_off( 5 );
$closed = $GLOBALS['options'][ NH_VIEN_Gate::OPTION ];
fresh();
$result = NH_VIEN_Gate::switch_on( false, 7 );
must( 'nh_vien_rewrite_failed' === $result && $closed === $GLOBALS['options'][ NH_VIEN_Gate::OPTION ], 'The previous decision must come back after a failed switch-on' );
$GLOBALS['flush_works']  = true;
$GLOBALS['refuse_write'] = '';

// ---- Deactivating the plugin takes the rules out and forgets the decision, so that activating it again starts closed.
fresh();
must( 'routes_enabled' === NH_VIEN_Gate::switch_on( false, 7 ), 'Switch-on before the deactivation failed' );
fresh();
NH_VIEN_Routes::deactivate();
must( 0 === count( stored_ours() ) && ! array_key_exists( NH_VIEN_Gate::OPTION, $GLOBALS['options'] ), 'Deactivation must remove the rules and forget the decision' );
must( $GLOBALS['base_rules'] === $GLOBALS['options']['rewrite_rules'], 'Deactivation must keep every other rule' );
fresh();
$GLOBALS['writes'] = 0;
NH_VIEN_Routes::deactivate();
must( 0 === $GLOBALS['writes'], 'Deactivation without anything to remove must not write' );
must( ! NH_VIEN_Gate::open(), 'After a deactivation the gate must be closed' );

// ---- Every token the gate can return has a notice on the Tools screen.
define( 'NH_VIEN_ADMIN_TEST', true );
function add_action( $tag, $callback = null ) {}
function __( $text ) { return $text; }
require dirname( __DIR__ ) . '/includes/class-nh-vien-admin.php';
$messages = NH_VIEN_Admin::messages();
foreach ( array( 'routes_enabled', 'routes_disabled', 'nh_vien_rules_left', 'nh_vien_rules_not_stored', 'nh_vien_gate_save_failed', 'nh_vien_config_controls', 'nh_vien_production_denied', 'nh_vien_confirm_required', 'nh_vien_pretty_links_required', 'nh_vien_rewrite_failed', 'nh_vien_invalid_mode', 'address_taken' ) as $token ) {
	must( isset( $messages[ $token ][0], $messages[ $token ][1] ) && in_array( $messages[ $token ][0], array( 'notice-success', 'notice-warning', 'notice-error' ), true ), "No notice for $token" );
}
foreach ( array( 'routes_enabled', 'routes_disabled' ) as $token ) {
	must( 'notice-success' === $messages[ $token ][0], "$token must be a success notice" );
}

// ---- A production constant that says no beats the dashboard, even a confirmed one.
$GLOBALS['env'] = 'production';
fresh();
must( 'routes_enabled' === NH_VIEN_Gate::switch_on( true, 7 ) && NH_VIEN_Gate::open(), 'A confirmed production site must be open before the constant' );
define( 'NH_VIEN_ALLOW_PRODUCTION', false );
fresh();
must( ! NH_VIEN_Gate::open(), 'NH_VIEN_ALLOW_PRODUCTION = false must close a production site whatever the dashboard says' );
must( NH_VIEN_Gate::production_denied_by_config() && ! NH_VIEN_Gate::confirmation_needed(), 'The constant that says no is reported' );
$before = $GLOBALS['options'];
$result = NH_VIEN_Gate::switch_on( true, 7 );
must( 'nh_vien_production_denied' === $result && $before === $GLOBALS['options'], 'The dashboard must not switch a production site on against the constant: ' . $result );
$GLOBALS['env'] = 'staging';
fresh();
must( NH_VIEN_Gate::open() && ! NH_VIEN_Gate::production_denied_by_config(), 'The production constant does not concern staging' );

// ---- The first constant takes the decision away from the dashboard: it decides alone, as in the earlier versions.
define( 'NH_VIEN_ENABLE_EN_ROUTES', true );
$GLOBALS['env'] = 'staging';
NH_VIEN_Gate::switch_off( 7 );
fresh();
$closed_for_the_dashboard = $GLOBALS['options'][ NH_VIEN_Gate::OPTION ];
must( NH_VIEN_Gate::managed_by_config() && NH_VIEN_Gate::open(), 'The constant true must open a staging site whatever the dashboard says' );
$before = $GLOBALS['options'];
must( 'nh_vien_config_controls' === NH_VIEN_Gate::switch_on( true, 7 ) && 'nh_vien_config_controls' === NH_VIEN_Gate::switch_off( 7 ), 'The dashboard must not switch what the constant decides' );
must( $before === $GLOBALS['options'], 'Refused switches must change nothing' );
$GLOBALS['env'] = 'production';
fresh();
must( ! NH_VIEN_Gate::open(), 'The constant true and a production constant that says no must keep production closed' );

echo "PASS: gate without the constant - switch on and off, confirmation, site address, refusals, rollback, deactivation, constants.\n";
