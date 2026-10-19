<?php
/** Offline checks for the per-language scoping of lists, terms, previous/next links and empty archives. */

// Test tooling: runs from the command line only, never through a web server.
if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/../../../../' );
define( 'OBJECT', 'OBJECT' );
define( 'NH_VIEN_VERSION', '0.5.0' );
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
function get_query_var( $name ) { return 'sitemap' === $name ? ( isset( $GLOBALS['sitemap'] ) ? $GLOBALS['sitemap'] : '' ) : ''; }
define( 'WPSEO_VERSION', '28.7' );
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
/** Parent of a term, by ID (a child => its parent); empty unless a test sets it. */
$GLOBALS['parents'] = array();
function get_ancestors( $id ) {
	$out = array();
	while ( isset( $GLOBALS['parents'][ $id ] ) ) {
		$id    = $GLOBALS['parents'][ $id ];
		$out[] = $id;
	}
	return $out;
}
function get_term_children( $id ) {
	$out = array();
	foreach ( $GLOBALS['parents'] as $child => $parent ) {
		if ( $parent === $id ) {
			$out[] = $child;
			$out   = array_merge( $out, get_term_children( $child ) );
		}
	}
	return $out;
}
function get_term( $id ) { return isset( $GLOBALS['terms'][ $id ] ) ? $GLOBALS['terms'][ $id ] : null; }
function add_query_arg( $key, $value, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $key . '=' . $value; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function trailingslashit( $value ) { return rtrim( $value, '/' ) . '/'; }
function must( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }
/** A REST request with the few parameters the suggestions look at. */
class WP_REST_Request {
	private $params;
	public function __construct( $params = array() ) { $this->params = $params; }
	public function get_param( $key ) { return isset( $this->params[ $key ] ) ? $this->params[ $key ] : null; }
}

foreach ( array( 'languages', 'links', 'routes', 'context', 'urls', 'query' ) as $part ) {
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

// ---- The parent of an English-only category is English only as well, unless it has Vietnamese posts of its own.
$GLOBALS['terms'][6] = new WP_Term( 6, 'category', 'en-parent', 0 );
$GLOBALS['terms'][7] = new WP_Term( 7, 'category', 'mixed-parent', 4 );
$GLOBALS['parents']  = array(
	3 => 6,
	2 => 7,
);
fresh( 'vi' );
$only = NH_VIEN_Links::en_only_term_ids();
must( isset( $only[3] ) && isset( $only[4] ), 'The English-only category and tag must be English only' );
must( isset( $only[6] ), 'The parent of an English-only category must be English only' );
must( ! isset( $only[7] ) && ! isset( $only[2] ) && ! isset( $only[1] ), 'A category with Vietnamese posts, its own or in a child, must not be English only' );
$kept = NH_VIEN_Query::filter_terms( array( $GLOBALS['terms'][1], $GLOBALS['terms'][2], $GLOBALS['terms'][6], $GLOBALS['terms'][7] ), array( 'category' ), array() );
must( array( 1, 2, 7 ) === array_map( static function ( $term ) { return $term->term_id; }, $kept ), 'Vietnamese pages must drop the English-only parent' );
// XML sitemap term queries must preserve English-only terms for Yoast's sitemap_entry hook.
$GLOBALS['sitemap'] = 'category';
$kept = NH_VIEN_Query::filter_terms( array( $GLOBALS['terms'][1], $GLOBALS['terms'][2], $GLOBALS['terms'][6] ), array( 'category' ), array() );
must( array( 1, 2, 6 ) === array_map( static function ( $term ) { return $term->term_id; }, $kept ), 'Yoast category sitemap must retain English-only terms for rewriting' );
$GLOBALS['sitemap'] = 'post_tag';
$kept = NH_VIEN_Query::filter_terms( array( $GLOBALS['terms'][4] ), array( 'post_tag' ), array() );
must( array( 4 ) === array_map( static function ( $term ) { return $term->term_id; }, $kept ), 'Yoast tag sitemap must retain English-only terms for rewriting' );
$GLOBALS['sitemap'] = '';
fresh( 'en' );
$kept = NH_VIEN_Query::filter_terms( array( $GLOBALS['terms'][1], $GLOBALS['terms'][2], $GLOBALS['terms'][6], $GLOBALS['terms'][7] ), array( 'category' ), array() );
must( array( 2, 6, 7 ) === array_map( static function ( $term ) { return $term->term_id; }, $kept ), 'English pages must keep the parents of the categories of English posts' );
$GLOBALS['parents'] = array();
unset( $GLOBALS['terms'][6], $GLOBALS['terms'][7] );

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

/** Save the original fixture: subsequent REST tests require the original paired English articles. */
$saved_options = $GLOBALS['options'];
$saved_posts = $GLOBALS['published'];
$saved_terms = $GLOBALS['terms'];
$saved_parents = $GLOBALS['parents'];
$saved_assigned = $GLOBALS['assigned'];

/** Independent English posts enter only the English side, without any VI pair. */
$GLOBALS['options'][ NH_VIEN_Links::OPTION ] = array();
$GLOBALS['options'][ NH_VIEN_Links::INDEPENDENT_OPTION ] = array( 77 );
$GLOBALS['options'][ NH_VIEN_Links::EN_TERMS_OPTION ] = array( 201, 202 );
$GLOBALS['published'][] = new WP_Post( 77, 'new-english-guide' );
$GLOBALS['terms'][201] = new WP_Term( 201, 'category', 'guides-english', 0 );
$GLOBALS['terms'][202] = new WP_Term( 202, 'category', 'rental-guides-english', 1 );
$GLOBALS['terms'][999] = new WP_Term( 999, 'category', 'unmodified-vietnamese', 3 );
$GLOBALS['parents'][202] = 201;
$GLOBALS['assigned'][77] = array( 202 );
fresh( 'en' );
must( array( 77 ) === NH_VIEN_Links::public_en_ids(), 'Independent article missing from public English set' );
$q = query();
NH_VIEN_Query::scope( $q );
must( array( 77 ) === $q->get( 'post__in' ), 'English query omitted the standalone article' );
must( isset( NH_VIEN_Links::en_term_ids()[201] ) && isset( NH_VIEN_Links::en_term_ids()[202] ), 'English parent/child terms missing' );
fresh( 'vi' );
$q = query();
NH_VIEN_Query::scope( $q );
must( array( 77 ) === $q->get( 'post__not_in' ), 'Vietnamese query must exclude the independent English article' );
$terms = NH_VIEN_Query::filter_terms( array( $GLOBALS['terms'][201], $GLOBALS['terms'][202], $GLOBALS['terms'][999] ), array( 'category' ) );
must( array( $GLOBALS['terms'][999] ) === $terms, 'English category tree leaked into Vietnamese terms' );


$GLOBALS['options'] = $saved_options;
$GLOBALS['published'] = $saved_posts;
$GLOBALS['terms'] = $saved_terms;
$GLOBALS['parents'] = $saved_parents;
$GLOBALS['assigned'] = $saved_assigned;
fresh( 'vi' );

// ---- The administration, REST requests, cron and the command line see every post.
define( 'REST_REQUEST', true );
fresh( 'vi' );
$q = query();
NH_VIEN_Query::scope( $q );
must( array() === $q->vars, 'REST requests must see every post' );

// ---- The suggestions under a search box: the page tells the REST route which language it is in.
$language_of = function ( $language ) {
	return new WP_REST_Request( array( NH_VIEN_Routes::LANG_VAR => $language ) );
};
$base = array( 'post_type' => 'post' );
fresh( 'vi' );
must( array( 'post_type' => 'post', 'post__in' => array( 22 ) ) === NH_VIEN_Query::rest_search_query( $base, $language_of( 'en' ) ), 'English suggestions must be the public English posts' );
$out = NH_VIEN_Query::rest_search_query( array( 'post__not_in' => array( 9, 22 ) ), $language_of( 'vi' ) );
must( array( 9, 22, 44 ) === $out['post__not_in'], 'Vietnamese suggestions must exclude every English post and keep the exclusions that were there' );
must( $base === NH_VIEN_Query::rest_search_query( $base, new WP_REST_Request( array() ) ), 'A request without a language must be answered as before' );
must( $base === NH_VIEN_Query::rest_search_query( $base, $language_of( 'xx' ) ), 'An unknown language must be ignored' );
must( $base === NH_VIEN_Query::rest_search_query( $base, $language_of( array( 'en' ) ) ), 'A language that is not a string must be ignored' );
must( $base === NH_VIEN_Query::rest_search_query( $base, null ), 'Anything that is not a REST request must be ignored' );
$saved                = $GLOBALS['published'];
$GLOBALS['published'] = array( new WP_Post( 11, 'bai-viet' ) );
fresh( 'vi' );
must( array( 0 ) === NH_VIEN_Query::rest_search_query( $base, $language_of( 'en' ) )['post__in'], 'English suggestions without English posts must be empty, never unrestricted' );
$GLOBALS['published'] = $saved;
NH_VIEN_Routes::reset();
$GLOBALS['options']['rewrite_rules'] = array();
must( $base === NH_VIEN_Query::rest_search_query( $base, $language_of( 'en' ) ), 'A site whose rules are not stored must not be touched' );
$GLOBALS['options']['rewrite_rules'] = $rules;

// ---- The address of the REST search route carries the language, inside the body of a page that has English pairs.
$route = 'https://example.test/wp-json/wp/v2/search';
fresh( 'en' );
must( $route === NH_VIEN_Urls::rest_url( $route, 'wp/v2/search' ), 'Nothing may change in the head of the page' );
NH_VIEN_Urls::open_body_phase();
must( $route . '?nh_vien_lang=en' === NH_VIEN_Urls::rest_url( $route, 'wp/v2/search' ), 'An English page must ask for English suggestions' );
must( $route . '?nh_vien_lang=en' === NH_VIEN_Urls::rest_url( $route, '/wp/v2/search/' ), 'The route is recognised with slashes' );
must( 'https://example.test/?rest_route=/wp/v2/search&nh_vien_lang=en' === NH_VIEN_Urls::rest_url( 'https://example.test/?rest_route=/wp/v2/search', 'wp/v2/search' ), 'Plain permalinks must work too' );
must( 'https://example.test/wp-json/wp/v2/posts' === NH_VIEN_Urls::rest_url( 'https://example.test/wp-json/wp/v2/posts', 'wp/v2/posts' ), 'Other routes must be left alone' );
fresh( 'vi' );
must( $route . '?nh_vien_lang=vi' === NH_VIEN_Urls::rest_url( $route, 'wp/v2/search' ), 'A Vietnamese page must ask for Vietnamese suggestions' );
$GLOBALS['options'][ NH_VIEN_Links::OPTION ] = array();
fresh( 'vi' );
must( $route === NH_VIEN_Urls::rest_url( $route, 'wp/v2/search' ), 'Without English posts the address must stay as it is' );
NH_VIEN_Urls::close_body_phase();

// ---- WordPress finds an English post by its address: embeds, oEmbed and link cards.
$GLOBALS['options'][ NH_VIEN_Links::OPTION ] = array(
	11 => 22,
	33 => 44,
);
fresh( 'vi' );
must( 'https://example.test/?p=22' === NH_VIEN_Urls::url_to_postid( 'https://example.test/en/english-article/' ), 'An English post must be found at its English address' );
must( 'https://example.test/?p=22' === NH_VIEN_Urls::url_to_postid( 'https://example.test/en/english-article' ), 'The trailing slash must not matter' );
must( 'https://example.test/?p=22' === NH_VIEN_Urls::url_to_postid( 'https://example.test/en/english-article/?utm_source=x' ), 'A query string must not matter' );
foreach ( array( 'https://example.test/en/hidden-english/', 'https://example.test/en/nope/', 'https://example.test/en/', 'https://example.test/en/english-article/2/', 'https://example.test/bai-viet/', 'https://example.test/english-article/', 'https://example.test/entropy/english-article/' ) as $other ) {
	must( $other === NH_VIEN_Urls::url_to_postid( $other ), "An address that is not an English post must be left alone: $other" );
}
must( 42 === NH_VIEN_Urls::url_to_postid( 42 ), 'Anything that is not an address must be left alone' );
NH_VIEN_Routes::reset();
$GLOBALS['options']['rewrite_rules'] = array();
must( 'https://example.test/en/english-article/' === NH_VIEN_Urls::url_to_postid( 'https://example.test/en/english-article/' ), 'A site whose rules are not stored must not be touched' );
$GLOBALS['options']['rewrite_rules'] = $rules;


echo "PASS: language scoping of lists, terms, neighbours, empty archives and search suggestions.\n";
