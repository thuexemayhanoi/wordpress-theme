<?php
/** Offline checks for staging-only routes/switcher. */

// Test tooling: runs from the command line only, never through a web server.
if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ );
define( 'OBJECT', 'OBJECT' );
define( 'NH_VIEN_VERSION', '0.3.0' );
define( 'NH_VIEN_PLUGIN_FILE', __DIR__ . '/nguyen-ha-vi-en.php' );
define( 'NH_VIEN_ENABLE_EN_ROUTES', true );
$GLOBALS['env'] = 'production';
$GLOBALS['hooks'] = array();
$GLOBALS['posts'] = array();
$GLOBALS['pages'] = array();
$GLOBALS['ids_by_slug'] = array();
$GLOBALS['query'] = array();
$GLOBALS['queried_id'] = 22;
class WP_Post {
	public $ID; public $post_name; public $post_status; public $post_type;
	public function __construct( $id, $slug, $status = 'publish', $type = 'post' ) {
		$this->ID = $id; $this->post_name = $slug; $this->post_status = $status; $this->post_type = $type;
	}
}
class NH_VIEN_Links {
	public static function for_post( $id ) {
		return in_array( (int) $id, array( 11, 22 ), true ) ? array( 'vi' => 11, 'en' => 22 ) : null;
	}
}
function wp_get_environment_type() { return $GLOBALS['env']; }
function add_action( $event, $callback, $priority = 10 ) { $GLOBALS['hooks'][] = $event; }
function add_filter( $event, $callback, $priority = 10, $count = 1 ) { $GLOBALS['hooks'][] = $event; }
function add_shortcode( $tag, $callback ) { $GLOBALS['hooks'][] = $tag; }
function add_rewrite_rule( $pattern, $query, $position ) { $GLOBALS['rewrite'] = array( $pattern, $query, $position ); }
function get_page_by_path( $path, $output = OBJECT, $post_type = 'page' ) { return isset( $GLOBALS['pages'][ $path ] ) ? $GLOBALS['pages'][ $path ] : null; }
function get_posts( $query ) { return isset( $GLOBALS['ids_by_slug'][ $query['name'] ] ) ? $GLOBALS['ids_by_slug'][ $query['name'] ] : array(); }
function get_post( $id ) { return isset( $GLOBALS['posts'][ $id ] ) ? $GLOBALS['posts'][ $id ] : null; }
function home_url( $path ) { return 'https://example.test' . $path; }
function get_permalink( $id ) { return 'https://example.test/vi-' . $id . '/'; }
function esc_url( $url ) { return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' ); }
function is_singular( $type ) { return true; }
function get_query_var( $name, $default = '' ) { return isset( $GLOBALS['query'][ $name ] ) ? $GLOBALS['query'][ $name ] : $default; }
function get_queried_object_id() { return $GLOBALS['queried_id']; }
function is_main_query() { return true; }
function in_the_loop() { return true; }
function is_feed() { return false; }
function has_shortcode( $content, $tag ) { return false !== strpos( $content, '[' . $tag . ']' ); }
function plugins_url( $path, $file ) { return 'https://example.test/wp-content/plugins/vi-en/' . $path; }
function wp_enqueue_style( $handle, $url, $deps, $version ) {}
function must( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }

require dirname( __DIR__ ) . '/includes/class-nh-vien-routes.php';
require dirname( __DIR__ ) . '/includes/class-nh-vien-switcher.php';
$GLOBALS['posts'][11] = new WP_Post( 11, 'bai-viet-tieng-viet' );
$GLOBALS['posts'][22] = new WP_Post( 22, 'english-article' );
$GLOBALS['ids_by_slug']['english-article'] = array( 22 );
must( ! NH_VIEN_Routes::enabled(), 'Production gate failed' );
NH_VIEN_Routes::boot(); NH_VIEN_Switcher::boot();
must( array() === $GLOBALS['hooks'], 'Production registered hooks' );
must( '' === NH_VIEN_Routes::english_url( 22 ), 'Production leaked EN URL' );
$GLOBALS['env'] = 'staging';
must( NH_VIEN_Routes::enabled(), 'Staging gate failed' );
NH_VIEN_Routes::boot(); NH_VIEN_Switcher::boot();
must( 7 === count( $GLOBALS['hooks'] ), 'Staging hook count mismatch' );
NH_VIEN_Routes::register();
must( NH_VIEN_Routes::REWRITE === $GLOBALS['rewrite'][0], 'Wrong rewrite pattern' );
must( array( 'public', 'nh_vien_en_slug' ) === NH_VIEN_Routes::query_vars( array( 'public' ) ), 'Query vars wrong' );
$wp = (object) array( 'matched_rule' => NH_VIEN_Routes::REWRITE, 'query_vars' => array( 'nh_vien_en_slug' => 'english-article' ) );
NH_VIEN_Routes::parse_request( $wp );
must( 22 === $wp->query_vars['p'], 'Valid EN route failed' );
must( 'https://example.test/en/english-article/' === NH_VIEN_Routes::english_url( 22 ), 'EN URL wrong' );
must( '' === NH_VIEN_Routes::english_url( 11 ), 'Vietnamese post routed to /en/' );
$GLOBALS['query'] = array( 'nh_vien_en_slug' => 'english-article' );
must( false === NH_VIEN_Routes::prevent_core_redirect( '/english-article/', '/en/english-article/' ), 'Verified EN canonical redirect not suppressed' );
must( false !== strpos( NH_VIEN_Switcher::html( 22 ), 'aria-current="page">EN' ), 'EN switcher active state absent' );
must( false !== strpos( NH_VIEN_Switcher::html( 11 ), 'aria-current="page">VI' ), 'VI switcher active state absent' );
must( false !== strpos( NH_VIEN_Switcher::prepend( 'Body' ), '<nav' ), 'Missing content switcher' );
must( 'Text [nh_vien_switcher]' === NH_VIEN_Switcher::prepend( 'Text [nh_vien_switcher]' ), 'Duplicate shortcode switcher' );
$GLOBALS['ids_by_slug']['english-article'] = array( 22, 99 );
must( 0 === NH_VIEN_Routes::post_id_for_slug( 'english-article' ), 'Duplicate slug exposed' );
$GLOBALS['ids_by_slug']['english-article'] = array( 22 );
$GLOBALS['posts'][22]->post_status = 'draft';
must( 0 === NH_VIEN_Routes::post_id_for_slug( 'english-article' ), 'Draft EN exposed' );
$GLOBALS['posts'][22]->post_status = 'publish';
$GLOBALS['posts'][11]->post_status = 'draft';
must( 0 === NH_VIEN_Routes::post_id_for_slug( 'english-article' ), 'Draft VI exposed' );
$GLOBALS['posts'][11]->post_status = 'publish';
$GLOBALS['pages']['en/english-article'] = new WP_Post( 55, 'english-article', 'publish', 'page' );
must( '' === NH_VIEN_Routes::english_url( 22 ), 'Existing page not protected' );
$wp = (object) array( 'matched_rule' => NH_VIEN_Routes::REWRITE, 'query_vars' => array( 'nh_vien_en_slug' => 'english-article' ) );
NH_VIEN_Routes::parse_request( $wp );
must( 55 === $wp->query_vars['page_id'], 'Existing page hijacked' );
unset( $GLOBALS['pages']['en/english-article'] );
$wp = (object) array( 'matched_rule' => NH_VIEN_Routes::REWRITE, 'query_vars' => array( 'nh_vien_en_slug' => 'invalid!' ) );
NH_VIEN_Routes::parse_request( $wp );
must( '404' === $wp->query_vars['error'], 'Invalid slug not 404' );
$wp = (object) array( 'matched_rule' => 'other-rule', 'query_vars' => array( 'nh_vien_en_slug' => 'english-article' ) );
NH_VIEN_Routes::parse_request( $wp );
must( ! isset( $wp->query_vars['p'] ), 'Unrelated route hijacked' );
$GLOBALS['env'] = 'production';
must( '' === NH_VIEN_Switcher::html( 22 ), 'Production switcher exposed' );
echo "PASS: staging gate, EN post route, duplicate and unpublished guards, 404, page collision, VI/EN switcher.\n";
