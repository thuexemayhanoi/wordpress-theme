<?php
/** Offline checks for the English routes: gate, rules, slugs, request resolution, addresses. */

// Test tooling: runs from the command line only, never through a web server.
if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/../../../../' );
define( 'OBJECT', 'OBJECT' );
define( 'NH_VIEN_VERSION', '0.5.0' );
define( 'NH_VIEN_PLUGIN_FILE', dirname( __DIR__ ) . '/nguyen-ha-vi-en.php' );

$GLOBALS['env']       = 'staging';
$GLOBALS['options']   = array();
$GLOBALS['writes']    = 0;
$GLOBALS['hooks']     = array();
$GLOBALS['rewrite']   = array();
$GLOBALS['pages']     = array();
$GLOBALS['published'] = array();

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
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_get_environment_type() { return $GLOBALS['env']; }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['options'] ) ? $GLOBALS['options'][ $key ] : $default; }
function update_option( $key, $value ) { $GLOBALS['options'][ $key ] = $value; ++$GLOBALS['writes']; return true; }
function add_action( $tag ) { $GLOBALS['hooks'][] = $tag; }
function add_filter( $tag ) { $GLOBALS['hooks'][] = $tag; }
function add_rewrite_rule( $regex, $query, $position ) { $GLOBALS['rewrite'][] = array( $regex, $query, $position ); }
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
function must( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }

require dirname( __DIR__ ) . '/includes/class-nh-vien-languages.php';
require dirname( __DIR__ ) . '/includes/class-nh-vien-links.php';
require dirname( __DIR__ ) . '/includes/class-nh-vien-routes.php';
require dirname( __DIR__ ) . '/includes/class-nh-vien-context.php';

/** A fresh request: no memo, Vietnamese context, no recorded hooks. */
function fresh() {
	NH_VIEN_Routes::reset();
	NH_VIEN_Links::flush_cache();
	NH_VIEN_Context::reset();
	$GLOBALS['hooks']   = array();
	$GLOBALS['rewrite'] = array();
}

/** A parsed request as WP::parse_request() leaves it. */
function request( $rule, $vars ) {
	return (object) array(
		'matched_rule' => $rule,
		'query_vars'   => $vars,
	);
}

// ---- Gate: the constant is required, and a production site needs a second constant.
fresh();
must( ! NH_VIEN_Routes::enabled(), 'Gate open without the constant' );
define( 'NH_VIEN_ENABLE_EN_ROUTES', true );
$GLOBALS['env'] = 'production';
must( ! NH_VIEN_Routes::enabled(), 'Production gate open with the first constant only' );
foreach ( array( 'local', 'development', 'staging' ) as $env ) {
	$GLOBALS['env'] = $env;
	must( NH_VIEN_Routes::enabled(), "Gate closed on $env" );
}
$GLOBALS['env'] = 'production';
define( 'NH_VIEN_ALLOW_PRODUCTION', true );
must( NH_VIEN_Routes::enabled(), 'Production gate closed with both constants' );
$GLOBALS['env'] = 'staging';

// ---- Rules: all of them live below /en/, the specific ones first, each flagged as English.
$rules = NH_VIEN_Routes::rules();
$keys  = array_keys( $rules );
must( 8 === count( $rules ), 'Unexpected number of rules' );
foreach ( $rules as $regex => $query ) {
	must( 0 === strpos( $regex, '^en/' ), "Rule outside /en/: $regex" );
	must( 0 === strpos( $query, 'index.php?nh_vien_lang=en' ), "Rule not flagged as English: $regex" );
}
must( $keys[0] === NH_VIEN_Routes::HOME && $keys[1] === NH_VIEN_Routes::HOME_PAGE, 'Home rules must come first' );
must( array_search( NH_VIEN_Routes::HOME_PAGE, $keys, true ) < array_search( NH_VIEN_Routes::MULTIPAGE, $keys, true ), 'page/N must beat {slug}/N' );
must( NH_VIEN_Routes::REWRITE === end( $keys ), 'The generic post rule must be last' );
fresh();
NH_VIEN_Routes::register();
must( count( $GLOBALS['rewrite'] ) === 8 && 'top' === $GLOBALS['rewrite'][0][2], 'Rules not registered on top' );

// ---- Slugs.
foreach ( array( 'english-article', 'a', '2026-guide', 'a_b' ) as $slug ) {
	must( NH_VIEN_Routes::slug_is_routable( $slug ), "Valid slug refused: $slug" );
}
foreach ( array( '', 'Upper', 'a/b', '-x', 'page', 'category', 'tag', 'feed', 'search', 'wp-json', "a\nb", str_repeat( 'a', 192 ) ) as $slug ) {
	must( ! NH_VIEN_Routes::slug_is_routable( $slug ), 'Bad or reserved slug accepted: ' . $slug );
}
must( ! NH_VIEN_Routes::slug_is_routable( array( 'a' ) ), 'Non-string slug accepted' );

// ---- Live: the gate is open and every rule is stored.
fresh();
must( ! NH_VIEN_Routes::live(), 'Live without stored rules' );
$GLOBALS['options']['rewrite_rules'] = array_slice( $rules, 0, 7, true );
fresh();
must( ! NH_VIEN_Routes::rules_stored(), 'A missing rule counts as stored' );
$GLOBALS['options']['rewrite_rules'] = $rules;
fresh();
must( NH_VIEN_Routes::rules_stored() && NH_VIEN_Routes::live(), 'Stored rules not live' );
$GLOBALS['env'] = 'production';
$GLOBALS['options']['rewrite_rules'] = $rules;
$GLOBALS['env'] = 'staging';

// ---- Pairs: 11 <-> 22 shown, 33 <-> 44 hidden (Vietnamese draft), 55 <-> 66 reserved slug, 77 <-> 88 takes a page's address.
$GLOBALS['options'][ NH_VIEN_Links::OPTION ] = array(
	11 => 22,
	33 => 44,
	55 => 66,
	77 => 88,
);
$GLOBALS['published'] = array(
	new WP_Post( 11, 'bai-viet' ),
	new WP_Post( 22, 'english-article' ),
	new WP_Post( 33, 'bai-nhap', 'draft' ),
	new WP_Post( 44, 'hidden-english' ),
	new WP_Post( 55, 'bai-khac' ),
	new WP_Post( 66, 'category' ),
	new WP_Post( 77, 'bai-trung' ),
	new WP_Post( 88, 'collision' ),
);
fresh();
must( array( 22, 88 ) === NH_VIEN_Links::public_en_ids(), 'Public set wrong before the page exists: ' . implode( ',', NH_VIEN_Links::public_en_ids() ) );
$GLOBALS['pages']['en']           = new WP_Post( 5, 'en', 'draft', 'page' );
$GLOBALS['pages']['en/collision'] = new WP_Post( 6, 'collision', 'publish', 'page' );
fresh();
must( array( 22 ) === NH_VIEN_Links::public_en_ids(), 'A page at /en/collision must hide the English post' );
must( null === NH_VIEN_Routes::home_page(), 'A draft page is not the English home' );
must( 22 === NH_VIEN_Routes::post_id_for_slug( 'english-article' ), 'Public post not found' );
foreach ( array( 'hidden-english', 'category', 'collision', 'nope', 'Bad!' ) as $slug ) {
	must( 0 === NH_VIEN_Routes::post_id_for_slug( $slug ), "Slug resolved although it must not: $slug" );
}
must( 'https://example.test/en/english-article/' === NH_VIEN_Routes::english_url( 22 ), 'English address wrong' );
must( '' === NH_VIEN_Routes::english_url( 44 ) && '' === NH_VIEN_Routes::english_url( 11 ), 'Hidden or Vietnamese post got an English address' );
must( 'https://example.test/en/' === NH_VIEN_Routes::url(), 'English home address wrong' );
must( 'https://example.test/en/category/guides/' === NH_VIEN_Routes::url( 'category/guides' ), 'Path address wrong' );

// ---- parse_request.
fresh();
$wp = request( NH_VIEN_Routes::REWRITE, array( 'nh_vien_en_slug' => 'english-article', 'nh_vien_lang' => 'en' ) );
NH_VIEN_Routes::parse_request( $wp );
must( 22 === $wp->query_vars['p'] && 'en' === $wp->query_vars['nh_vien_lang'], 'Public English post not resolved' );
must( NH_VIEN_Context::is_english(), 'A matched route must make the request English' );
foreach ( array( 'hidden-english', 'category', 'nope', 'Bad!' ) as $slug ) {
	fresh();
	$wp = request( NH_VIEN_Routes::REWRITE, array( 'nh_vien_en_slug' => $slug ) );
	NH_VIEN_Routes::parse_request( $wp );
	must( array( 'error' => '404' ) === $wp->query_vars, "Not 404: $slug" );
}
fresh();
$wp = request( NH_VIEN_Routes::MULTIPAGE, array( 'nh_vien_en_slug' => 'english-article', 'page' => '2' ) );
NH_VIEN_Routes::parse_request( $wp );
must( 22 === $wp->query_vars['p'] && '2' === $wp->query_vars['page'], 'Page of a multi-page post lost' );
fresh();
$GLOBALS['pages']['en/about'] = new WP_Post( 7, 'about', 'publish', 'page' );
$wp                           = request( NH_VIEN_Routes::REWRITE, array( 'nh_vien_en_slug' => 'about' ) );
NH_VIEN_Routes::parse_request( $wp );
must( 7 === $wp->query_vars['page_id'] && ! isset( $wp->query_vars['p'] ), 'A page below /en/ lost its address' );
fresh();
$wp = request( NH_VIEN_Routes::REWRITE, array( 'nh_vien_en_slug' => 'collision' ) );
NH_VIEN_Routes::parse_request( $wp );
must( 6 === $wp->query_vars['page_id'], 'The page must win over the English post' );
fresh();
$wp = request( 'some-other-rule', array( 'nh_vien_lang' => 'en', 'nh_vien_en_slug' => 'english-article' ) );
NH_VIEN_Routes::parse_request( $wp );
must( ! isset( $wp->query_vars['nh_vien_lang'] ) && ! isset( $wp->query_vars['nh_vien_en_slug'] ), 'A raw query variable posed as a route' );
must( ! NH_VIEN_Context::is_english(), 'A raw query variable made the request English' );
fresh();
$wp = (object) array( 'query_vars' => array( 'nh_vien_lang' => 'en' ) );
NH_VIEN_Routes::parse_request( $wp );
must( ! isset( $wp->query_vars['nh_vien_lang'] ), 'No matched rule: the variable must be dropped' );

// Home: the list when posts exist, a 404 when none, the real page when there is one, no page 2 of a real page.
fresh();
$wp = request( NH_VIEN_Routes::HOME, array( 'nh_vien_lang' => 'en' ) );
NH_VIEN_Routes::parse_request( $wp );
must( array( 'nh_vien_lang' => 'en' ) === $wp->query_vars, 'The English home list must be left to the main query' );
fresh();
$wp = request( NH_VIEN_Routes::HOME, array( 'nh_vien_lang' => 'en', 's' => 'bike' ) );
NH_VIEN_Routes::parse_request( $wp );
must( isset( $wp->query_vars['s'] ), 'A search below /en/ lost its term' );
$GLOBALS['pages']['en'] = new WP_Post( 5, 'en', 'publish', 'page' );
fresh();
$wp = request( NH_VIEN_Routes::HOME, array( 'nh_vien_lang' => 'en' ) );
NH_VIEN_Routes::parse_request( $wp );
must( 5 === $wp->query_vars['page_id'], 'A published page at /en/ must be the English home' );
fresh();
$wp = request( NH_VIEN_Routes::HOME_PAGE, array( 'nh_vien_lang' => 'en', 'paged' => '2' ) );
NH_VIEN_Routes::parse_request( $wp );
must( array( 'error' => '404' ) === $wp->query_vars, 'Page 2 of a real home page must be a 404' );
fresh();
$wp = request( NH_VIEN_Routes::HOME_PAGE, array( 'nh_vien_lang' => 'en', 'paged' => '2', 's' => 'x' ) );
NH_VIEN_Routes::parse_request( $wp );
must( isset( $wp->query_vars['s'] ) && ! isset( $wp->query_vars['error'] ), 'Search results of page 2 must stay' );
unset( $GLOBALS['pages']['en'] );
$GLOBALS['published'] = array();
fresh();
$wp = request( NH_VIEN_Routes::HOME, array( 'nh_vien_lang' => 'en' ) );
NH_VIEN_Routes::parse_request( $wp );
must( array( 'error' => '404' ) === $wp->query_vars, 'The English home without English posts must be a 404' );

// ---- boot: the rules are registered whenever the gate is open (the closed gate is checked in boot.php).
fresh();
NH_VIEN_Routes::boot();
must( array( 'init', 'query_vars', 'parse_request' ) === $GLOBALS['hooks'], 'Open gate: wrong hooks' );

// ---- A published post that already has the address /en/ is noticed (a page is the English home, not a collision).
$GLOBALS['published'] = array( new WP_Post( 1, 'something' ), new WP_Post( 2, 'en', 'publish', 'page' ), new WP_Post( 3, 'en', 'draft' ) );
must( ! NH_VIEN_Routes::post_at_en(), 'A page or a draft with the slug en must not count as a post at /en/' );
$GLOBALS['published'][] = new WP_Post( 4, 'en' );
must( NH_VIEN_Routes::post_at_en(), 'A published post with the slug en must be noticed' );
$GLOBALS['published'] = array();

// ---- Deactivation takes the plugin's own rules out of the stored ones and nothing else.
$ours   = NH_VIEN_Routes::rules();
$others = array(
	'^wp-json/?$'                       => 'index.php?rest_route=/',
	'category/(.+?)/?$'                 => 'index.php?category_name=$matches[1]',
	'([^/]+)(?:/([0-9]+))?/?$'          => 'index.php?name=$matches[1]&page=$matches[2]',
	'^en/([^/]+)/?$-lookalike'          => 'index.php?x=1',
);
$GLOBALS['options']['rewrite_rules'] = array_merge( $ours, $others );
$GLOBALS['writes']                   = 0;
NH_VIEN_Routes::deactivate();
must( 1 === $GLOBALS['writes'], 'Deactivation must write the stored rules once' );
must( $others === $GLOBALS['options']['rewrite_rules'], 'Deactivation must remove every rule of this plugin and keep every other rule, in order' );
$GLOBALS['writes'] = 0;
NH_VIEN_Routes::deactivate();
must( 0 === $GLOBALS['writes'], 'Deactivation without rules of this plugin must not write anything' );
$GLOBALS['options'] = array();
NH_VIEN_Routes::deactivate();
must( 0 === $GLOBALS['writes'] && array() === $GLOBALS['options'], 'Deactivation without stored rules must not create the option' );
$GLOBALS['options']['rewrite_rules'] = '';
NH_VIEN_Routes::deactivate();
must( 0 === $GLOBALS['writes'], 'Deactivation with a corrupt option must leave it alone' );
$GLOBALS['options'] = array();

echo "PASS: gate, rules, slugs, live state, pairs, request resolution, English addresses, deactivation.\n";
