<?php
/** Offline WordPress stub tests: explicit reciprocal VI/EN post linking. */

// Test tooling: runs from the command line only, never through a web server.
if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/../../../../' );
$GLOBALS['mock_options'] = array();
$GLOBALS['mock_writes'] = array();
$GLOBALS['mock_posts'] = array();
$GLOBALS['allow_manage'] = true;
$GLOBALS['allow_edit'] = true;
$GLOBALS['nonce_ok'] = true;
$GLOBALS['hooks'] = array();

class WP_Post {
	public $post_type = 'post';
	public $post_status = 'publish';
	public function __construct( $type = 'post', $status = 'publish' ) {
		$this->post_type = $type;
		$this->post_status = $status;
	}
}
class WP_Error {
	private $code;
	public function __construct( $code, $message ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['mock_options'] ) ? $GLOBALS['mock_options'][ $key ] : $default;
}
function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['mock_options'][ $key ] = $value;
	$GLOBALS['mock_writes'][] = array( $key, $value, $autoload );
	return true;
}
function get_post( $id ) { return isset( $GLOBALS['mock_posts'][ $id ] ) ? $GLOBALS['mock_posts'][ $id ] : null; }
function current_user_can( $cap, $id = null ) { return 'manage_options' === $cap ? $GLOBALS['allow_manage'] : $GLOBALS['allow_edit']; }
function wp_verify_nonce( $nonce, $action ) { return $GLOBALS['nonce_ok'] && 'ok' === $nonce && 'nh_vien_manage_links' === $action; }
function add_action( $name, $callback, $priority = 10 ) { $GLOBALS['hooks'][] = $name; }
function is_admin() { return false; }
function must( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }
function error_is( $result, $code ) { return is_wp_error( $result ) && $code === $result->get_error_code(); }

require dirname( __DIR__ ) . '/nguyen-ha-vi-en.php';

foreach ( array( 11, 22, 33, 44, 55, 66, 77 ) as $id ) {
	$GLOBALS['mock_posts'][ $id ] = new WP_Post();
}
$GLOBALS['mock_posts'][55] = new WP_Post( 'page' );
$GLOBALS['mock_posts'][66] = new WP_Post( 'post', 'trash' );
$GLOBALS['mock_posts'][77] = new WP_Post( 'revision', 'inherit' );

must( '0.3.0' === NH_VIEN_VERSION, 'Wrong version' );
must( array() === NH_VIEN_Links::all(), 'Map should start empty' );
must( array() === $GLOBALS['mock_writes'], 'Bootstrap must not write' );
must( null === NH_VIEN_Links::for_post( 11 ), 'No implicit pairing' );
must( error_is( NH_VIEN_Links::link( 11, 22, 'bad' ), 'nh_vien_bad_nonce' ), 'Bad nonce accepted' );
$GLOBALS['allow_manage'] = false;
must( error_is( NH_VIEN_Links::link( 11, 22, 'ok' ), 'nh_vien_forbidden' ), 'Non-admin accepted' );
$GLOBALS['allow_manage'] = true;
$GLOBALS['allow_edit'] = false;
must( error_is( NH_VIEN_Links::link( 11, 22, 'ok' ), 'nh_vien_forbidden' ), 'Edit permission ignored' );
$GLOBALS['allow_edit'] = true;
must( error_is( NH_VIEN_Links::link( 11, 11, 'ok' ), 'nh_vien_invalid_ids' ), 'Self-link accepted' );
must( error_is( NH_VIEN_Links::link( '11x', 22, 'ok' ), 'nh_vien_invalid_ids' ), 'Dirty ID accepted' );
must( error_is( NH_VIEN_Links::link( 11, 999, 'ok' ), 'nh_vien_invalid_posts' ), 'Missing post accepted' );
must( error_is( NH_VIEN_Links::link( 11, 55, 'ok' ), 'nh_vien_invalid_posts' ), 'Page accepted' );
must( error_is( NH_VIEN_Links::link( 11, 66, 'ok' ), 'nh_vien_invalid_posts' ), 'Trash accepted' );
must( error_is( NH_VIEN_Links::link( 11, 77, 'ok' ), 'nh_vien_invalid_posts' ), 'Revision accepted' );
must( true === NH_VIEN_Links::link( 11, 22, 'ok' ), 'Valid pair rejected' );
must( count( $GLOBALS['mock_writes'] ) === 1, 'Unexpected write count' );
must( NH_VIEN_Links::OPTION === $GLOBALS['mock_writes'][0][0], 'Unexpected data destination' );
must( false === $GLOBALS['mock_writes'][0][2], 'Option must not autoload' );
must( array( 11 => 22 ) === NH_VIEN_Links::all(), 'Map mismatch' );
must( array( 'vi' => 11, 'en' => 22 ) === NH_VIEN_Links::for_post( 11 ), 'VI lookup failed' );
must( array( 'vi' => 11, 'en' => 22 ) === NH_VIEN_Links::for_post( 22 ), 'EN lookup failed' );
must( true === NH_VIEN_Links::link( 11, 22, 'ok' ), 'Repeat pair not idempotent' );
must( 1 === count( $GLOBALS['mock_writes'] ), 'Repeat pair triggered write' );
must( error_is( NH_VIEN_Links::link( 11, 33, 'ok' ), 'nh_vien_conflict' ), 'VI conflict allowed' );
must( error_is( NH_VIEN_Links::link( 33, 22, 'ok' ), 'nh_vien_conflict' ), 'EN conflict allowed' );
must( error_is( NH_VIEN_Links::link( 22, 33, 'ok' ), 'nh_vien_conflict' ), 'EN reused as VI' );
must( error_is( NH_VIEN_Links::link( 33, 11, 'ok' ), 'nh_vien_conflict' ), 'VI reused as EN' );
must( true === NH_VIEN_Links::link( 33, 44, 'ok' ), 'Independent pair rejected' );
must( count( NH_VIEN_Links::all() ) === 2, 'Second pair not stored' );
must( error_is( NH_VIEN_Links::unlink( 11, 'bad' ), 'nh_vien_bad_nonce' ), 'Unlink nonce ignored' );
$GLOBALS['allow_edit'] = false;
must( error_is( NH_VIEN_Links::unlink( 11, 'ok' ), 'nh_vien_forbidden' ), 'Unlink edit permission ignored' );
$GLOBALS['allow_edit'] = true;
must( true === NH_VIEN_Links::unlink( 11, 'ok' ), 'Unlink failed' );
must( null === NH_VIEN_Links::for_post( 22 ), 'Old counterpart still resolves' );
must( array( 33 => 44 ) === NH_VIEN_Links::all(), 'Unlink removed wrong pair' );
must( error_is( NH_VIEN_Links::unlink( 11, 'ok' ), 'nh_vien_not_found' ), 'Missing pair ignored' );
$GLOBALS['mock_options'][NH_VIEN_Links::OPTION] = array( 11 => 44, 33 => 44 );
must( is_wp_error( NH_VIEN_Links::all() ), 'Duplicate target allowed' );
$old_writes = count( $GLOBALS['mock_writes'] );
must( error_is( NH_VIEN_Links::link( 11, 22, 'ok' ), 'nh_vien_invalid_store' ), 'Bad map not rejected' );
must( $old_writes === count( $GLOBALS['mock_writes'] ), 'Bad map overwritten' );
$GLOBALS['mock_options'][NH_VIEN_Links::OPTION] = array( 11 => 22, 22 => 33 );
must( is_wp_error( NH_VIEN_Links::all() ), 'Language-column overlap allowed' );
$GLOBALS['mock_options'][NH_VIEN_Links::OPTION] = array( 11 => 22, 33 => 11 );
must( is_wp_error( NH_VIEN_Links::all() ), 'Reversed language-column overlap allowed' );
$GLOBALS['mock_options'][NH_VIEN_Links::OPTION] = 'bad';
must( is_wp_error( NH_VIEN_Links::all() ), 'Non-array store allowed' );
must( array( 'plugins_loaded' ) === $GLOBALS['hooks'], 'Non-admin bootstrap added unexpected hooks' );
echo "PASS: reciprocal links, one-to-one conflicts, CSRF/capability, corrupt-store and write boundaries.\n";
