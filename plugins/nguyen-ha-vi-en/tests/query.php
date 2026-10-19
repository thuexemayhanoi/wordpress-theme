<?php
/** Offline checks for the per-language scoping of lists, terms, previous/next links and empty archives. */

// Test tooling: runs from the command line only, never through a web server.
if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/../../../../' );
define( 'OBJECT', 'OBJECT' );
define( 'NH_VIEN_VERSION', '0.4.0' );
define( 'NH_VIEN_PLUGIN_FILE', dirname( __DIR__ ) . '/nguyen-ha-vi-en.php' );
define( 'NH_VIEN_ENABLE_EN_ROUTES', true );

$GLOBALS['env']       = 'staging';
$GLOBALS['options']   = array();
$GLOBALS['hooks']     = array();
$GLOBALS['published'] = array();
$GLOBALS['terms']     = array();
$GLOBALS['assigned']  = array();
$GLOBALS['any_posts'] = array();
$GLOBALS['admin']     = false;
$GLOBALS['status']    = null;

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
	public $taxonomy;
	public $slug;
	public $count;
	public function __construct( $id, $taxonomy, $slug, $count ) {
		$this->term_id  = $id;
		$this->taxonomy = $taxonomy;
		$this->slug     = $slug;
		$this->count    = $count;
	}
}
class WP_Error {}
/** A query with the few members the scoping reads and writes. */
class WP_Query {
	public $vars        = array();
	public $is_home     = false;
	public $is_archive  = false;
	public $is_single   = false;
	public $is_404      = false;
	public $is_category = false;
	public $is_tag      = false;
	public $main        = true;
	public $posts_found = 0;
	public $queried     = null;
	public function get( $key ) { return isset( $this->vars[ $key ] ) ? $this->vars[ $key ] : ''; }
	public function set( $key, $value ) { $this->vars[ $key ] = $value; }
	public function is_singular() { return $this->is_single; }
	public function is_attachment() { return false; }
	public function is_main_query() { return $this->main; }
	public function is_home() { return $this->is_home; }
	public function is_category() { return $this->is_category; }
	public function is_tag() { return $this->is_tag; }
	public function is_404() { return $this->is_404; }
	public function have_posts() { return $this->posts_found > 0; }
	public function set_404() { $this->is_404 = true; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_get_environment_type() { return $GLOBALS['env']; }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['options'] ) ? $GLOBALS['options'][ $key ] : $default; }
function add_action( $tag ) { $GLOBALS['hooks'][] = $tag; }
function add_filter( $tag ) { $GLOBALS['hooks'][] = $tag; }
function is_admin() { return $GLOBALS['admin']; }
function wp_doing_ajax() { return false; }
function wp_doing_cron() { return false; }
function status_header( $code ) { $GLOBALS['status'] = $code; }
function nocache_headers() {}
function __( $text ) { return $text; }
function get_queried_object() { return $GLOBALS['wp_query']->queried; }
function get_page_by_path() { return null; }
function get_posts( $args ) {
	if ( isset( $args['tax_query'] ) ) {
		return $GLOBALS['any_posts'];
	}
	$found = array();
	foreach ( $GLOBALS['published'] as $post ) {
		if ( in_array( $post->ID, $args['post__in'], true ) && 'publish' === $post->post_status ) {
			$found[] = 'ids' === ( isset( $args['fields'] ) ? $args['fields'] : '' ) ? $post->ID : $post;
		}
	}
	return $found;
}
function wp_get_object_terms( $ids, $taxonomies, $args ) {
	$rows = array();
	foreach ( $ids as $id ) {
		foreach ( isset( $GLOBALS['assigned'][ $id ] ) ? $GLOBALS['assigned'][ $id ] : array() as $term_id ) {
			$rows[] = 'ids' === $args['fields'] ? $term_id : $GLOBALS['terms'][ $term_id ];
		}
	}
	return $rows;
}
function get_ancestors() { return array(); }
function get_term_children() { return array(); }
function get_term( $id ) { return isset( $GLOBALS['terms'][ $id ] ) ? $GLOBALS['terms'][ $id ] : null; }
function must( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }

foreach ( array( 'languages', 'links', 'routes', 'context', 'query' ) as $part ) {
	require dirname( __DIR__ ) . '/includes/class-nh-vien-' . $part . '.php';
}

$rules = NH_VIEN_Routes::rules();

/** A fresh request in the given language. */
function fresh( $language ) {
	NH_VIEN_Routes::reset();
	NH_VIEN_Links::flush_cache();
	NH_VIEN_Context::reset();
	NH_VIEN_Context::set( $language );
	$GLOBALS['hooks']  = array();
	$GLOBALS['status'] = null;
	$GLOBALS['admin']  = false;
}

$GLOBALS['options']['rewrite_rules']         = $rules;
$GLOBALS['options'][ NH_VIEN_Links::OPTION ] = array(
	11 => 22,
	33 => 44,
);
// 22 is shown; 44 is hidden because its Vietnamese post 33 is a draft.
$GLOBALS['published'] = array(
	new WP_Post( 11, 'bai-viet' ),
	new WP_Post( 22, 'english-article' ),
	new WP_Post( 33, 'bai-nhap', 'draft' ),
	new WP_Post( 44, 'hidden-english' ),
);
// Terms: 1 is used by Vietnamese posts only, 2 by a shown English post and a Vietnamese one, 3 by English posts only, 4 is a tag of English posts only.
$GLOBALS['terms']    = array(
	1 => new WP_Term( 1, 'category', 'vi-only', 5 ),
	2 => new WP_Term( 2, 'category', 'shared', 3 ),
	3 => new WP_Term( 3, 'category', 'en-only', 2 ),
	4 => new WP_Term( 4, 'post_tag', 'en-tag', 2 ),
	5 => new WP_Term( 5, 'custom_taxonomy', 'other', 9 ),
);
$GLOBALS['assigned'] = array(
	22 => array( 2, 3, 4 ),
	44 => array( 3, 4 ),
);

function query( $vars = array() ) {
	$query       = new WP_Query();
	$query->vars = $vars;
	return $query;
}

// ---- Vietnamese lists leave the English posts out, whatever their state, and keep what was excluded already.
fresh( 'vi' );
$q = query();
NH_VIEN_Query::scope( $q );
must( array( 22, 44 ) === $q->get( 'post__not_in' ), 'Vietnamese list must exclude every English post: ' . json_encode( $q->get( 'post__not_in' ) ) );
$q = query( array( 'post__not_in' => array( 9, 22 ) ) );
NH_VIEN_Query::scope( $q );
must( array( 9, 22, 44 ) === $q->get( 'post__not_in' ), 'Existing exclusions must be kept without duplicates' );

// ---- English lists show the English posts that may be shown, nothing else.
fresh( 'en' );
$q = query();
NH_VIEN_Query::scope( $q );
must( array( 22 ) === $q->get( 'post__in' ), 'English list must show the public English posts only' );
$q = query( array( 'post__in' => array( 22, 99 ) ) );
NH_VIEN_Query::scope( $q );
must( array( 22 ) === $q->get( 'post__in' ), 'An explicit post__in must be intersected, not replaced' );
$q = query( array( 'post__in' => array( 99 ) ) );
NH_VIEN_Query::scope( $q );
must( array( 0 ) === $q->get( 'post__in' ), 'A list with no English post must be empty, never unrestricted' );
$q          = query();
$q->is_home = true;
NH_VIEN_Query::scope( $q );
must( ! $q->is_home && $q->is_archive && 'home' === $q->get( 'nh_vien_view' ), 'The English list must not be read as the home page' );
$q          = query();
$q->is_home = true;
$q->main    = false;
NH_VIEN_Query::scope( $q );
must( $q->is_home && '' === $q->get( 'nh_vien_view' ), 'Only the main query becomes the English list page' );

// ---- Queries that must not be touched.
foreach ( array( 'en', 'vi' ) as $language ) {
	fresh( $language );
	$q            = query();
	$q->is_single = true;
	NH_VIEN_Query::scope( $q );
	must( array() === $q->vars, "A single post must not be scoped ($language)" );
	$q = query( array( 'post_type' => 'page' ) );
	NH_VIEN_Query::scope( $q );
	must( array( 'post_type' => 'page' ) === $q->vars, "A query for pages must not be scoped ($language)" );
	$q = query( array( NH_VIEN_Routes::SCOPE_VAR => 'off' ) );
	NH_VIEN_Query::scope( $q );
	must( array( NH_VIEN_Routes::SCOPE_VAR => 'off' ) === $q->vars, "An opt-out must be respected ($language)" );
	$q = query( array( 'post_type' => array( 'post', 'page' ) ) );
	NH_VIEN_Query::scope( $q );
	must( isset( $q->vars['post__in'] ) || isset( $q->vars['post__not_in'] ), "A query that includes posts must be scoped ($language)" );
	$GLOBALS['admin'] = true;
	$q                = query();
	NH_VIEN_Query::scope( $q );
	must( array() === $q->vars, "The administration must see every post ($language)" );
	$GLOBALS['admin'] = false;
}
NH_VIEN_Routes::reset();
$GLOBALS['options']['rewrite_rules'] = array();
$q                                   = query();
NH_VIEN_Query::scope( $q );
must( array() === $q->vars, 'A site whose rules are not stored must not be touched' );
$GLOBALS['options']['rewrite_rules'] = $rules;

// ---- Previous and next links stay inside the language.
fresh( 'vi' );
$post = new WP_Post( 11, 'bai-viet' );
must( ' AND p.ID NOT IN (22,44)' === substr( NH_VIEN_Query::adjacent_where( 'WHERE x', false, '', 'category', $post ), 7 ), 'Vietnamese neighbours must exclude English posts' );
fresh( 'en' );
must( 'WHERE x AND p.ID IN (22)' === NH_VIEN_Query::adjacent_where( 'WHERE x', false, '', 'category', $post ), 'English neighbours must be English posts' );
must( 'WHERE x' === NH_VIEN_Query::adjacent_where( 'WHERE x', false, '', 'category', new WP_Post( 5, 'p', 'publish', 'page' ) ), 'Other post types must be left alone' );

// ---- Term lists.
$all = array( $GLOBALS['terms'][1], $GLOBALS['terms'][2], $GLOBALS['terms'][3], $GLOBALS['terms'][4], $GLOBALS['terms'][5] );
fresh( 'en' );
$kept = NH_VIEN_Query::filter_terms( $all, array( 'category' ), array() );
must( array( 2, 3, 4, 5 ) === array_map( static function ( $term ) { return $term->term_id; }, $kept ), 'English pages must list the terms of English posts (and other taxonomies untouched)' );
fresh( 'vi' );
$kept = NH_VIEN_Query::filter_terms( $all, array( 'category' ), array() );
must( array( 1, 2, 5 ) === array_map( static function ( $term ) { return $term->term_id; }, $kept ), 'Vietnamese pages must drop the terms whose posts are all English' );
must( $all === NH_VIEN_Query::filter_terms( $all, array( 'category' ), array( 'include' => array( 3 ) ) ), 'An explicit include must not be filtered' );
must( array( 1, 2 ) === NH_VIEN_Query::filter_terms( array( 1, 2 ), array( 'category' ), array( 'fields' => 'ids' ) ), 'Lists of IDs are left alone' );

// ---- Empty archives are a 404 only when the language is the reason.
fresh( 'en' );
$GLOBALS['wp_query']              = query( array( 'nh_vien_view' => 'home' ) );
$GLOBALS['wp_query']->posts_found = 0;
NH_VIEN_Query::send_empty_404();
must( $GLOBALS['wp_query']->is_404 && 404 === $GLOBALS['status'], 'An empty English list must answer 404' );
fresh( 'en' );
$GLOBALS['wp_query']              = query();
$GLOBALS['wp_query']->is_category = true;
$GLOBALS['wp_query']->posts_found = 3;
NH_VIEN_Query::send_empty_404();
must( ! $GLOBALS['wp_query']->is_404, 'A category with English posts must not be a 404' );
fresh( 'vi' );
$GLOBALS['wp_query']              = query();
$GLOBALS['wp_query']->is_category = true;
$GLOBALS['wp_query']->queried     = $GLOBALS['terms'][3];
$GLOBALS['any_posts']             = array( 22 );
NH_VIEN_Query::send_empty_404();
must( $GLOBALS['wp_query']->is_404 && 404 === $GLOBALS['status'], 'A Vietnamese archive emptied by the language must answer 404' );
fresh( 'vi' );
$GLOBALS['wp_query']              = query();
$GLOBALS['wp_query']->is_category = true;
$GLOBALS['wp_query']->queried     = $GLOBALS['terms'][1];
$GLOBALS['any_posts']             = array();
NH_VIEN_Query::send_empty_404();
must( ! $GLOBALS['wp_query']->is_404, 'A category that was empty before must keep its default behaviour' );
fresh( 'vi' );
$GLOBALS['wp_query']              = query();
$GLOBALS['wp_query']->is_category = true;
$GLOBALS['wp_query']->queried     = $GLOBALS['terms'][3];
$GLOBALS['wp_query']->posts_found = 2;
$GLOBALS['any_posts']             = array( 22 );
NH_VIEN_Query::send_empty_404();
must( ! $GLOBALS['wp_query']->is_404, 'A Vietnamese archive with posts must not be a 404' );

// ---- The administration, REST requests, cron and the command line see every post.
define( 'REST_REQUEST', true );
fresh( 'vi' );
$q = query();
NH_VIEN_Query::scope( $q );
must( array() === $q->vars, 'REST requests must see every post' );

echo "PASS: language scoping of lists, terms, neighbours and empty archives.\n";
