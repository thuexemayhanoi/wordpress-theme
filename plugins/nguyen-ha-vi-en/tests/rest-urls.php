<?php
/**
 * Offline regression checks: English REST category links and oEmbed pages.
 * These tests do not boot a website or change content/SEO settings.
 */
if ( 'cli' !== PHP_SAPI ) {
    exit;
}
define( 'ABSPATH', __DIR__ . '/../../../../' );

function must( $ok, $message ) {
    if ( ! $ok ) {
        throw new RuntimeException( $message );
    }
}
class WP_Term {
    public $term_id;
    public $taxonomy;
    public $slug;
    public function __construct( $id, $taxonomy, $slug ) {
        $this->term_id = $id;
        $this->taxonomy = $taxonomy;
        $this->slug = $slug;
    }
}
class WP_Post {
    public $ID;
    public function __construct( $id ) {
        $this->ID = $id;
    }
}
class WP_REST_Response {
    public $data;
    public function __construct( $data ) {
        $this->data = $data;
    }
}
class NH_VIEN_Context {
    public static function is_english() { return false; }
}
class NH_VIEN_Links {
    public static function en_term_ids() { return array( 46 => true, 51 => true, 87 => true ); }
}
class NH_VIEN_Routes {
    public static $live = true;
    public static function enabled() { return true; }
    public static function live() { return self::$live; }
    public static function url( $path = '' ) { return 'https://blog.rentbikehanoi.com/en/' . trim( $path, '/' ) . '/'; }
    public static function home_page() { return new WP_Post( 1758 ); }
    public static function page_at( $path ) {
        $pages = array( 'en/about' => 1759, 'en/faq' => 1763, 'en/guides/advanced' => 1999 );
        return isset( $pages[ $path ] ) ? new WP_Post( $pages[ $path ] ) : null;
    }
}
function is_taxonomy_hierarchical( $tax ) { return 'category' === $tax; }
function get_ancestors( $id, $tax, $type ) { return 51 === $id ? array( 46 ) : array(); }
function get_term( $id, $tax ) { return 46 === $id ? new WP_Term( 46, 'category', 'hanoi-motorbike-rental' ) : null; }
function get_term_by( $field, $value, $taxonomy ) {
    if ( 'slug' !== $field || 'category' !== $taxonomy ) {
        return null;
    }
    if ( '50cc-motorbikes' === $value ) { return new WP_Term( 51, 'category', '50cc-motorbikes' ); }
    if ( 'vi-only' === $value ) { return new WP_Term( 301, 'category', 'vi-only' ); }
    return null;
}
function get_term_link( $term ) {
    if ( 51 === $term->term_id ) {
        return 'https://blog.rentbikehanoi.com/category/hanoi-motorbike-rental/50cc-motorbikes/';
    }
    return 'https://blog.rentbikehanoi.com/category/vi-only/';
}
function is_wp_error( $value ) { return false; }

function wp_parse_url( $value, $component = -1 ) { return parse_url( $value, $component ); }
function get_option( $name ) { return 'https://blog.rentbikehanoi.com'; }
function set_url_scheme( $url ) { return $url; }
function trailingslashit( $value ) { return rtrim( $value, '/' ) . '/'; }
function home_url( $path = '' ) { return 'https://blog.rentbikehanoi.com' . $path; }
$GLOBALS['filters'] = array();
function add_filter( $tag, $callback, $priority = 10, $accepted = 1 ) {
    $GLOBALS['filters'][ $tag ] = $callback;
}
function add_action( $name, $callback, $priority = 10 ) {}

require dirname( __DIR__ ) . '/includes/class-nh-vien-urls.php';
NH_VIEN_Urls::boot();
must( isset( $GLOBALS['filters']['rest_prepare_category'] ), 'Category REST filter was not registered' );
must( isset( $GLOBALS['filters']['rest_prepare_post_tag'] ), 'Tag REST filter was not registered' );
must( isset( $GLOBALS['filters']['oembed_request_post_id'] ), 'oEmbed page filter was not registered' );

$term = new WP_Term( 51, 'category', '50cc-motorbikes' );
$old = 'https://blog.rentbikehanoi.com/category/hanoi-motorbike-rental/50cc-motorbikes/';
$response = new WP_REST_Response( array( 'id' => 51, 'slug' => '50cc-motorbikes', 'link' => $old ) );
$out = NH_VIEN_Urls::rest_term_link( $response, $term );
must( $out === $response, 'Must preserve REST response instance' );
must( 'https://blog.rentbikehanoi.com/en/category/hanoi-motorbike-rental/50cc-motorbikes/' === $out->data['link'], 'EN category archive URI is wrong' );
must( 51 === $out->data['id'] && '50cc-motorbikes' === $out->data['slug'], 'REST identity changed' );

$vi = new WP_REST_Response( array( 'link' => 'https://blog.rentbikehanoi.com/category/vi-only/' ) );
NH_VIEN_Urls::rest_term_link( $vi, new WP_Term( 301, 'category', 'vi-only' ) );
must( 'https://blog.rentbikehanoi.com/category/vi-only/' === $vi->data['link'], 'VI category changed' );
$en_tag = new WP_REST_Response( array( 'link' => 'https://blog.rentbikehanoi.com/tag/example/' ) );
NH_VIEN_Urls::rest_term_link( $en_tag, new WP_Term( 87, 'post_tag', 'example' ) );
must( 'https://blog.rentbikehanoi.com/en/tag/example/' === $en_tag->data['link'], 'EN tag URL not corrected' );
NH_VIEN_Routes::$live = false;
$closed = new WP_REST_Response( array( 'link' => $old ) );
NH_VIEN_Urls::rest_term_link( $closed, $term );
must( $old === $closed->data['link'], 'Closed routes should not rewrite REST' );
NH_VIEN_Routes::$live = true;

must( 1758 === NH_VIEN_Urls::oembed_page_id( 0, 'https://blog.rentbikehanoi.com/en/' ), 'EN home oEmbed unresolved' );
must( 1759 === NH_VIEN_Urls::oembed_page_id( 0, 'https://blog.rentbikehanoi.com/en/about/' ), 'EN about oEmbed unresolved' );
must( 1763 === NH_VIEN_Urls::oembed_page_id( 0, 'https://blog.rentbikehanoi.com/en/faq/' ), 'EN faq oEmbed unresolved' );
must( 1999 === NH_VIEN_Urls::oembed_page_id( 0, 'https://blog.rentbikehanoi.com/en/guides/advanced/' ), 'Nested EN page unresolved' );
must( 888 === NH_VIEN_Urls::oembed_page_id( 888, 'https://blog.rentbikehanoi.com/en/about/' ), 'Existing post ID overwritten' );
foreach ( array(
    'https://blog.rentbikehanoi.com/about/',
    'https://evil.example/en/about/',
    'https://blog.rentbikehanoi.com.evil.example/en/about/',
    'http://blog.rentbikehanoi.com/en/about/',
    'https://blog.rentbikehanoi.com/en/about/?p=123',
    'https://blog.rentbikehanoi.com/en/missing/',
    'https://blog.rentbikehanoi.com/en/about%2Fsecret/',
    'https://blog.rentbikehanoi.com/en/category/hanoi-motorbike-rental/',
) as $invalid ) {
    must( 0 === NH_VIEN_Urls::oembed_page_id( 0, $invalid ), 'Unexpected oEmbed ID for ' . $invalid );
}
must( 'https://blog.rentbikehanoi.com/en/category/hanoi-motorbike-rental/50cc-motorbikes/' ===
    NH_VIEN_Urls::legacy_en_category_target( '/category/hanoi-motorbike-rental/50cc-motorbikes/' ),
    'Legacy English-only category should redirect to canonical English archive' );
must( '' === NH_VIEN_Urls::legacy_en_category_target( '/category/vi-only/' ), 'Vietnamese category should not redirect' );
must( '' === NH_VIEN_Urls::legacy_en_category_target( '/category/hanoi-motorbike-rental/vi-only/' ), 'Wrong nested category path accepted' );
must( '' === NH_VIEN_Urls::legacy_en_category_target( '/en/category/hanoi-motorbike-rental/50cc-motorbikes/' ), 'English route would redirect' );
must( '' === NH_VIEN_Urls::legacy_en_category_target( '/category/hanoi-motorbike-rental/50cc-motorbikes/extra/' ), 'Malformed legacy path accepted' );
NH_VIEN_Routes::$live = false;
must( '' === NH_VIEN_Urls::legacy_en_category_target( '/category/hanoi-motorbike-rental/50cc-motorbikes/' ), 'Closed routes emitted redirect' );
NH_VIEN_Routes::$live = false;
must( 0 === NH_VIEN_Urls::oembed_page_id( 0, 'https://blog.rentbikehanoi.com/en/' ), 'Disabled English routes leaked oEmbed' );

echo "English REST term and page oEmbed contract tests PASS\n";
