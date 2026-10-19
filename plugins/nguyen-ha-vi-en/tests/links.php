<?php
/** No-network contract tests for one-to-one translation links and WP permission boundaries. */
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
	public function __construct( $type = 'post', $status = 'publish' ) { $this->post_type = $type; $this->post_status = $status; }
}
class WP_Error {
	private $code;
	public function __construct( $code, $message ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['mock_options'] ) ? $GLOBALS['mock_options'][ $key ] : $default; }
function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['mock_options'][ $key ] = $value;
	$GLOBALS['mock_writes'][] = array( $key, $value, $autoload );
	return true;
}
function get_post( $id ) { return isset( $GLOBALS['mock_posts'][ $id ] ) ? $GLOBALS['mock_posts'][ $id ] : null; }
function current_user_can( $cap, $id = null ) { return 'manage_options' === $cap ? $GLOBALS['allow_manage'] : $GLOBALS['allow_edit']; }
function wp_verify_nonce( $nonce, $action ) { return $GLOBALS['nonce_ok'] && 'ok' === $nonce && 'nh_vien_manage_links' === $action; }
function add_action( $name, $callable, $priority = 10 ) { $GLOBALS['hooks'][] = $name; }
function is_admin() { return false; }
function must( $truth, $msg ) { if ( ! $truth ) { throw new RuntimeException( $msg ); } }
function code_is( $result, $code ) { return is_wp_error( $result ) && $code === $result->get_error_code(); }

require dirname( __DIR__ ) . '/nguyen-ha-vi-en.php';
$posts = array( 11, 22, 33, 44, 55, 66, 77, 88 );
foreach ( $posts as $id ) { $GLOBALS['mock_posts'][ $id ] = new WP_Post(); }
$GLOBALS['mock_posts'][55] = new WP_Post( 'page' );
$GLOBALS['mock_posts'][66] = new WP_Post( 'post', 'trash' );
$GLOBALS['mock_posts'][77] = new WP_Post( 'revision', 'inherit' );

must( '0.2.0' === NH_VIEN_VERSION, 'Unexpected version' );
must( array() === NH_VIEN_Links::all(), 'Should read empty list by default' );
must( array() === $GLOBALS['mock_writes'], 'Boot must not write' );
must( null === NH_VIEN_Links::for_post( 11 ), 'Unlinked post must not infer English' );
must( code_is( NH_VIEN_Links::link( 11, 22, 'wrong' ), 'nh_vien_bad_nonce' ), 'Reject invalid nonce' );
must( array() === $GLOBALS['mock_writes'], 'Invalid nonce must not write' );
$GLOBALS['allow_manage'] = false;
must( code_is( NH_VIEN_Links::link( 11, 22, 'ok' ), 'nh_vien_forbidden' ), 'Reject non-admin' );
$GLOBALS['allow_manage'] = true;
$GLOBALS['allow_edit'] = false;
must( code_is( NH_VIEN_Links::link( 11, 22, 'ok' ), 'nh_vien_forbidden' ), 'Both post edit permissions needed' );
$GLOBALS['allow_edit'] = true;
must( code_is( NH_VIEN_Links::link( 11, 11, 'ok' ), 'nh_vien_invalid_ids' ), 'Reject self link' );
must( code_is( NH_VIEN_Links::link( '11x', 22, 'ok' ), 'nh_vien_invalid_ids' ), 'Reject dirty IDs' );
must( code_is( NH_VIEN_Links::link( 11, 999, 'ok' ), 'nh_vien_invalid_posts' ), 'Reject missing post' );
must( code_is( NH_VIEN_Links::link( 11, 55, 'ok' ), 'nh_vien_invalid_posts' ), 'Reject pages' );
must( code_is( NH_VIEN_Links::link( 11, 66, 'ok' ), 'nh_vien_invalid_posts' ), 'Reject trashed posts' );
must( code_is( NH_VIEN_Links::link( 11, 77, 'ok' ), 'nh_vien_invalid_posts' ), 'Reject revisions' );
must( true === NH_VIEN_Links::link( 11, 22, 'ok' ), 'Valid link should work' );
must( 1 === count( $GLOBALS['mock_writes'] ), 'One authorized change expected' );
must( false === $GLOBALS['mock_writes'][0][2], 'Option must not autoload' );
must( array( 11 => 22 ) === NH_VIEN_Links::all(), 'Stored mapping mismatch' );
must( array( 'vi' => 11, 'en' => 22 ) === NH_VIEN_Links::for_post( 11 ), 'VI lookup mismatch' );
must( array( 'vi' => 11, 'en' => 22 ) === NH_VIEN_Links::for_post( 22 ), 'EN lookup mismatch' );
must( true === NH_VIEN_Links::link( 11, 22, 'ok' ), 'Pair save should be idempotent' );
must( 1 === count( $GLOBALS['mock_writes'] ), 'Idempotent save must not write' );
must( code_is( NH_VIEN_Links::link( 11, 33, 'ok' ), 'nh_vien_conflict' ), 'Reject source overwrite' );
must( code_is( NH_VIEN_Links::link( 33, 22, 'ok' ), 'nh_vien_conflict' ), 'Reject target reuse' );
must( code_is( NH_VIEN_Links::link( 22, 33, 'ok' ), 'nh_vien_conflict' ), 'Reject EN being used as VI' );
must( code_is( NH_VIEN_Links::link( 33, 11, 'ok' ), 'nh_vien_conflict' ), 'Reject VI being used as EN' );
must( true === NH_VIEN_Links::link( 33, 44, 'ok' ), 'Second independent pair failed' );
must( 2 === count( NH_VIEN_Links::all() ), 'Second pair missing' );
must( code_is( NH_VIEN_Links::unlink( 11, 'bad' ), 'nh_vien_bad_nonce' ), 'Unlink must check nonce' );
$GLOBALS['allow_edit'] = false;
must( code_is( NH_VIEN_Links::unlink( 11, 'ok' ), 'nh_vien_forbidden' ), 'Unlink must check edit permission' );
$GLOBALS['allow_edit'] = true;
must( true === NH_VIEN_Links::unlink( 11, 'ok' ), 'Valid unlink failed' );
must( null === NH_VIEN_Links::for_post( 22 ), 'Unlinked EN post must not resolve' );
must( array( 33 => 44 ) === NH_VIEN_Links::all(), 'Unlink must preserve other mapping' );
must( code_is( NH_VIEN_Links::unlink( 11, 'ok' ), 'nh_vien_not_found' ), 'Repeat unlink must reject' );
$GLOBALS['mock_options'][NH_VIEN_Links::OPTION] = array( 11 => 44, 33 => 44 );
must( is_wp_error( NH_VIEN_Links::all() ), 'Corrupt duplicate map not detected' );
$writes = count( $GLOBALS['mock_writes'] );
must( code_is( NH_VIEN_Links::link( 11, 22, 'ok' ), 'nh_vien_invalid_store' ), 'Corrupt map must fail closed' );
must( $writes === count( $GLOBALS['mock_writes'] ), 'Corrupt map must not be overwritten' );
$GLOBALS['mock_options'][NH_VIEN_Links::OPTION] = 'wrong';
must( is_wp_error( NH_VIEN_Links::all() ), 'Non-array store not detected' );

// Route, post updates and SEO changes are intentionally outside step 3.
must( 1 === count( $GLOBALS['hooks'] ), 'Non-admin boot may only register original plugin hook' );
echo "PASS: 35+ translation linkage assertions; no content, post meta, URL, or SEO edits.\n";
