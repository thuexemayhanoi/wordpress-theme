<?php
/** Offline checks for the REST API: every route needs an administrator, the handlers answer with the right status. */

// Test tooling: runs from the command line only, never through a web server.
if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/../../../../' );
define( 'OBJECT', 'OBJECT' );
define( 'NH_VIEN_VERSION', '0.5.3' );
define( 'NH_VIEN_PLUGIN_FILE', dirname( __DIR__ ) . '/nguyen-ha-vi-en.php' );
// The emergency constant: the English side stays off whatever the setting says.
define( 'NH_VIEN_ENABLE_EN_ROUTES', false );

$GLOBALS['routes']     = array();
$GLOBALS['options']    = array( 'permalink_structure' => '/%postname%/' );
$GLOBALS['can_manage'] = true;
$GLOBALS['terms']      = array();
$GLOBALS['hooks']      = array();
$GLOBALS['fired']      = array();
$GLOBALS['posts']      = array();

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
class WP_Term {
	public $term_id;
	public $parent;
	public $count;
	public $name;
	public function __construct( $id, $parent = 0, $count = 0, $name = '' ) {
		$this->term_id = $id;
		$this->parent  = $parent;
		$this->count   = $count;
		$this->name    = $name;
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
class Fake_Request {
	private $params;
	public function __construct( array $params ) { $this->params = $params; }
	public function get_param( $key ) { return isset( $this->params[ $key ] ) ? $this->params[ $key ] : null; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_get_environment_type() { return 'production'; }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['options'] ) ? $GLOBALS['options'][ $key ] : $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][ $key ] = $value; return true; }
function delete_option( $key ) { unset( $GLOBALS['options'][ $key ] ); return true; }
function add_action( $tag ) { $GLOBALS['hooks'][] = $tag; }
function add_filter( $tag ) { $GLOBALS['hooks'][] = $tag; }
function do_action( $tag ) { $GLOBALS['fired'][] = func_get_args(); }
function register_rest_route( $ns, $route, $args ) { $GLOBALS['routes'][ $ns . $route ] = $args; return true; }
function current_user_can( $cap, $id = null ) { return $GLOBALS['can_manage']; }
function rest_authorization_required_code() { return 401; }
function rest_sanitize_boolean( $value ) { return in_array( $value, array( true, 1, '1', 'true' ), true ); }
function wp_create_nonce( $action ) { return 'ok'; }
function wp_verify_nonce( $nonce, $action ) { return 'ok' === $nonce && NH_VIEN_Links::NONCE_ACTION === $action; }
function get_term( $id, $taxonomy = 'category' ) { return isset( $GLOBALS['terms'][ $id ] ) ? $GLOBALS['terms'][ $id ] : null; }
function get_term_children( $id, $taxonomy = 'category' ) {
	$out = array();
	foreach ( $GLOBALS['terms'] as $term ) {
		if ( $term->parent === $id ) {
			$out[] = $term->term_id;
		}
	}
	return $out;
}
function get_post( $id ) { return isset( $GLOBALS['posts'][ $id ] ) ? $GLOBALS['posts'][ $id ] : null; }
function get_page_by_path( $path ) { return null; }
function get_posts( $args ) { return array(); }
function get_page_uri( $id ) { return ''; }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function user_trailingslashit( $url ) { return rtrim( $url, '/' ) . '/'; }
function get_post_types() { return array(); }
function get_taxonomies() { return array(); }
function __( $text, $domain = null ) { return $text; }
function sanitize_text_field( $text ) { return trim( strip_tags( $text ) ); }
function must( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }
function error_is( $result, $code ) { return is_wp_error( $result ) && $code === $result->get_error_code(); }
function status_of( $result ) { return is_wp_error( $result ) ? $result->get_error_data()['status'] : 200; }

foreach ( array( 'languages', 'links', 'routes', 'context', 'settings', 'rest' ) as $part ) {
	require dirname( __DIR__ ) . '/includes/class-nh-vien-' . $part . '.php';
}

// ---- Registration: every route is below nh-vien/v1 and needs the administrator permission.
NH_VIEN_Rest::boot();
must( array( 'rest_api_init' ) === $GLOBALS['hooks'], 'The routes must be registered on rest_api_init' );
NH_VIEN_Rest::register();
$expected = array(
	'GET /status', 'POST /routes', 'POST /routes/undo', 'POST /settings',
	'POST /categories', 'DELETE /categories/(?P<id>[1-9][0-9]*)',
	'POST /posts', 'DELETE /posts/(?P<id>[1-9][0-9]*)',
	'POST /pairs', 'DELETE /pairs/(?P<vi>[1-9][0-9]*)',
	'POST /pages', 'DELETE /pages/(?P<vi>[1-9][0-9]*)',
);
$registered = array();
foreach ( $GLOBALS['routes'] as $route => $args ) {
	must( 0 === strpos( $route, 'nh-vien/v1/' ), "Route outside the namespace: $route" );
	must( array( 'NH_VIEN_Rest', 'permission' ) === $args['permission_callback'], "Route without the administrator permission: $route" );
	must( is_callable( $args['callback'] ), "Route with a missing handler: $route" );
	$registered[] = $args['methods'] . ' ' . substr( $route, strlen( 'nh-vien/v1' ) );
}
sort( $expected );
sort( $registered );
must( $expected === $registered, 'Unexpected routes: ' . implode( ', ', $registered ) );
foreach ( $GLOBALS['routes'] as $route => $args ) {
	if ( isset( $args['args'] ) ) {
		foreach ( $args['args'] as $name => $arg ) {
			must( in_array( $arg['type'], array( 'integer', 'boolean', 'string' ), true ), "Argument $name of $route must be typed" );
			must( 'string' !== $arg['type'] || 'sanitize_text_field' === $arg['sanitize_callback'], "Text argument $name of $route must be sanitised" );
		}
	}
}

// ---- Permission: a subscriber is refused with the status WordPress uses for a missing login or capability.
$GLOBALS['can_manage'] = false;
$denied                = NH_VIEN_Rest::permission();
must( error_is( $denied, 'nh_vien_forbidden' ) && 401 === $denied->get_error_data()['status'], 'A non-administrator must be refused' );
must( error_is( NH_VIEN_Rest::routes( new Fake_Request( array( 'enabled' => true, 'confirm' => true ) ) ), 'nh_vien_forbidden' ), 'The switch must refuse a non-administrator even when called directly' );
must( 403 === status_of( NH_VIEN_Rest::mark_category( new Fake_Request( array( 'id' => 46 ) ) ) ), 'A registration must refuse a non-administrator with 403' );
$GLOBALS['can_manage'] = true;
must( true === NH_VIEN_Rest::permission(), 'An administrator must be allowed' );

// ---- The switch: the emergency constant wins over the request, with a 409 and no write.
$result = NH_VIEN_Rest::routes( new Fake_Request( array( 'enabled' => true, 'confirm' => true ) ) );
must( error_is( $result, 'nh_vien_constant_off' ) && 409 === status_of( $result ), 'The emergency constant must refuse the switch on' );
must( ! array_key_exists( NH_VIEN_Routes::CONTROL_OPTION, $GLOBALS['options'] ) && 'constant_off' === NH_VIEN_Routes::source(), 'The emergency constant must leave the setting alone' );
$GLOBALS['options'][ NH_VIEN_Routes::CONTROL_OPTION ] = '1';
must( ! NH_VIEN_Routes::enabled() && 'constant_off' === NH_VIEN_Routes::source(), 'The emergency constant must win over a saved setting' );
unset( $GLOBALS['options'][ NH_VIEN_Routes::CONTROL_OPTION ] );

// ---- Status: readable by an administrator, describes the site.
$status = NH_VIEN_Rest::status();
must( is_array( $status ) && false === $status['enabled'] && 'constant_off' === $status['source'] && 'production' === $status['environment'] && is_array( $status['preflight'] ) && array() === $status['english_categories'], 'The status must describe the site' );

// ---- Registrations go through the same checks as the Tools screen and answer with a status.
$GLOBALS['terms'] = array(
	46 => new WP_Term( 46, 0, 0, 'Motorbike Rental in Hanoi' ),
	49 => new WP_Term( 49, 46, 0, 'Manual Motorbikes' ),
	3  => new WP_Term( 3, 0, 12, 'Cẩm nang' ),
);
$result = NH_VIEN_Rest::mark_category( new Fake_Request( array( 'id' => 49 ) ) );
must( error_is( $result, 'nh_vien_category_conflict' ) && 409 === status_of( $result ), 'A child whose parent is not English must be refused with 409' );
$result = NH_VIEN_Rest::mark_category( new Fake_Request( array( 'id' => 46 ) ) );
must( is_array( $result ) && true === $result['saved'] && array( 46 ) === $result['english_categories'], 'The parent must be registered and listed' );
$result = NH_VIEN_Rest::mark_category( new Fake_Request( array( 'id' => 49 ) ) );
must( is_array( $result ) && array( 46, 49 ) === $result['english_categories'], 'The child must be registered after its parent' );
$result = NH_VIEN_Rest::mark_category( new Fake_Request( array( 'id' => 3 ) ) );
must( error_is( $result, 'nh_vien_category_conflict' ), 'A Vietnamese category with posts must be refused' );
$result = NH_VIEN_Rest::mark_category( new Fake_Request( array( 'id' => 999 ) ) );
must( error_is( $result, 'nh_vien_invalid_category' ) && 400 === status_of( $result ), 'A missing category must be refused with 400' );
$result = NH_VIEN_Rest::unmark_category( new Fake_Request( array( 'id' => 46 ) ) );
must( error_is( $result, 'nh_vien_category_conflict' ), 'A parent cannot be unregistered before its children' );
$result = NH_VIEN_Rest::unmark_category( new Fake_Request( array( 'id' => 49 ) ) );
must( is_array( $result ) && array( 46 ) === $result['english_categories'], 'The child must be unregistered' );
$result = NH_VIEN_Rest::unmark_category( new Fake_Request( array( 'id' => 49 ) ) );
must( error_is( $result, 'nh_vien_not_found' ) && 404 === status_of( $result ), 'Unregistering twice must answer 404' );

$GLOBALS['posts'] = array(
	11 => new WP_Post( 11, 'bai-viet' ),
	22 => new WP_Post( 22, 'english-draft', 'draft' ),
	23 => new WP_Post( 23, 'english-live' ),
);
$result = NH_VIEN_Rest::mark_post( new Fake_Request( array( 'id' => 23 ) ) );
must( error_is( $result, 'nh_vien_invalid_posts' ) && 400 === status_of( $result ), 'A published post cannot be registered as English after the fact' );
$result = NH_VIEN_Rest::mark_post( new Fake_Request( array( 'id' => 22 ) ) );
must( is_array( $result ) && array( 22 ) === $result['independent_posts'], 'A draft must be registered as English' );
$result = NH_VIEN_Rest::unmark_post( new Fake_Request( array( 'id' => 22 ) ) );
must( is_array( $result ) && array() === $result['independent_posts'], 'The registration must be removable' );
$result = NH_VIEN_Rest::link_posts( new Fake_Request( array( 'vi' => 11, 'en' => 22 ) ) );
must( is_array( $result ) && array( 11 => 22 ) === $result['pairs'], 'A pair must be saved and listed' );
$result = NH_VIEN_Rest::mark_post( new Fake_Request( array( 'id' => 11 ) ) );
must( error_is( $result, 'nh_vien_conflict' ) && 409 === status_of( $result ), 'The Vietnamese side of a pair cannot be registered as English' );
$result = NH_VIEN_Rest::unlink_posts( new Fake_Request( array( 'vi' => 11 ) ) );
must( is_array( $result ) && array() === $result['pairs'], 'A pair must be removable' );
$result = NH_VIEN_Rest::unlink_posts( new Fake_Request( array( 'vi' => 11 ) ) );
must( error_is( $result, 'nh_vien_not_found' ) && 404 === status_of( $result ), 'Unlinking twice must answer 404' );
$result = NH_VIEN_Rest::link_pages( new Fake_Request( array( 'vi' => 11, 'en' => 22 ) ) );
must( error_is( $result, 'nh_vien_invalid_posts' ) && 400 === status_of( $result ), 'Posts are not pages' );
$result = NH_VIEN_Rest::undo();
must( error_is( $result, 'nh_vien_nothing_to_undo' ) && 404 === status_of( $result ), 'Nothing to undo must answer 404' );

// ---- The English site name: saved clean, reported in the status, administrators only.
$result = NH_VIEN_Rest::settings( new Fake_Request( array( 'site_name_en' => ' Nguyen Ha Motorbike Rental ' ) ) );
must( is_array( $result ) && 'Nguyen Ha Motorbike Rental' === $result['result']['site_name_en'] && 'Nguyen Ha Motorbike Rental' === $result['status']['site_name_en'], 'The English site name must be saved and reported' );
$GLOBALS['can_manage'] = false;
must( 403 === status_of( NH_VIEN_Rest::settings( new Fake_Request( array( 'site_name_en' => 'X' ) ) ) ), 'The name must refuse a non-administrator with 403' );
$GLOBALS['can_manage'] = true;

echo "PASS: route registration, administrator permission, emergency constant, status, category, post, pair and page registrations with HTTP statuses, English site name.\n";
