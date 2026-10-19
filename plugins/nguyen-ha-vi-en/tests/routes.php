<?php
/** Offline checks for the English routes: gate, rules, slugs, request resolution, addresses. */

// Test tooling: runs from the command line only, never through a web server.
if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/../../../../' );
define( 'OBJECT', 'OBJECT' );
define( 'NH_VIEN_VERSION', '0.5.1' );
define( 'NH_VIEN_PLUGIN_FILE', dirname( __DIR__ ) . '/nguyen-ha-vi-en.php' );

$GLOBALS['env']       = 'staging';
$GLOBALS['options']   = array();
$GLOBALS['writes']    = 0;
$GLOBALS['hooks']     = array();
$GLOBALS['rewrite']   = array();
$GLOBALS['pages']     = array();
$GLOBALS['published'] = array();
$GLOBALS['page_lookups']  = 0;
$GLOBALS['child_queries'] = 0;

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
function get_page_by_path( $path ) { ++$GLOBALS['page_lookups']; return isset( $GLOBALS['pages'][ $path ] ) ? $GLOBALS['pages'][ $path ] : null; }
function get_posts( $args ) {
	if ( isset( $args['post_parent'] ) ) {
		// The published pages directly below the page /en/.
		++$GLOBALS['child_queries'];
		$found = array();
		$en    = isset( $GLOBALS['pages']['en'] ) ? $GLOBALS['pages']['en']->ID : -1;
		foreach ( $GLOBALS['pages'] as $path => $page ) {
			if ( 0 === strpos( $path, 'en/' ) && false === strpos( substr( $path, 3 ), '/' ) && $args['post_parent'] === $en && 'publish' === $page->post_status ) {
				$found[] = $page;
			}
		}
		return $found;
	}
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
require dirname( __DIR__ ) . '/includes/class-nh-vien-gate.php';
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
// A rule set that someone cut down answers 404 under /en/, never a Vietnamese list or the Vietnamese home page.
foreach ( array( NH_VIEN_Routes::HOME, NH_VIEN_Routes::REWRITE ) as $one ) {
	fresh();
	$wp = request( $one, array( 'nh_vien_lang' => 'en', 'nh_vien_en_slug' => 'english-article' ) );
	NH_VIEN_Routes::parse_request( $wp );
	must( array( 'error' => '404' ) === $wp->query_vars && ! NH_VIEN_Context::is_english(), 'A cut-down rule set must answer 404, not a Vietnamese page: ' . $one );
}
// The same expression with another query is not ours.
$GLOBALS['options']['rewrite_rules'] = array_merge( array_slice( $rules, 0, 7, true ), array( NH_VIEN_Routes::REWRITE => 'index.php?other=1' ) );
fresh();
must( ! NH_VIEN_Routes::rules_stored(), 'A lookalike rule must not count as the stored rule' );
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

// ---- Removing is exact (same expression and same query), changes only what it must and says whether it worked.
$lookalike                           = array( NH_VIEN_Routes::HOME => 'index.php?other=1' );
$GLOBALS['options']['rewrite_rules'] = array_merge( $lookalike, array( 'x/?$' => 'index.php?x=1' ), array_diff_key( $ours, $lookalike ) );
$GLOBALS['writes']                   = 0;
must( true === NH_VIEN_Routes::remove_stored_rules(), 'Removing the rules of the plugin must report success' );
must( array_merge( $lookalike, array( 'x/?$' => 'index.php?x=1' ) ) === $GLOBALS['options']['rewrite_rules'] && 1 === $GLOBALS['writes'], 'A lookalike rule must stay and the others keep their order' );
must( true === NH_VIEN_Routes::remove_stored_rules() && 1 === $GLOBALS['writes'], 'Nothing of ours is left: nothing to write' );
$GLOBALS['options']['rewrite_rules'] = $ours;
must( true === NH_VIEN_Routes::remove_stored_rules() && array() === $GLOBALS['options']['rewrite_rules'], 'Removing every rule must leave an empty set' );

// ---- Storing: ours on top, the others untouched and in order; nothing to extend means false (the caller lets WordPress regenerate).
$GLOBALS['options']['rewrite_rules'] = $others;
$GLOBALS['writes']                   = 0;
must( true === NH_VIEN_Routes::store_rules() && 1 === $GLOBALS['writes'], 'Storing the rules must work and write once' );
must( array_keys( $ours ) === array_slice( array_keys( $GLOBALS['options']['rewrite_rules'] ), 0, 8 ) && $others === array_slice( $GLOBALS['options']['rewrite_rules'], 8, null, true ), 'Our rules go on top, every other rule stays in its order' );
$GLOBALS['options']['rewrite_rules'] = array_merge( array( NH_VIEN_Routes::REWRITE => 'index.php?other=1' ), $others );
must( true === NH_VIEN_Routes::store_rules() && 'index.php?nh_vien_lang=en' === substr( $GLOBALS['options']['rewrite_rules'][ NH_VIEN_Routes::REWRITE ], 0, 25 ), 'A lookalike under one of our expressions must give way to ours' );
foreach ( array( array(), '', false, 'corrupt' ) as $nothing ) {
	$GLOBALS['options']['rewrite_rules'] = $nothing;
	$GLOBALS['writes']                   = 0;
	must( false === NH_VIEN_Routes::store_rules() && 0 === $GLOBALS['writes'], 'Without stored rules there is nothing to extend: ' . json_encode( $nothing ) );
}
unset( $GLOBALS['options']['rewrite_rules'] );

// ---- Many English posts must not cost a lookup each: one lookup of the page /en/ and one of its children answer for all of them.
$GLOBALS['options'][ NH_VIEN_Links::OPTION ] = array();
$GLOBALS['published']                        = array();
$GLOBALS['pages']                            = array(
	'en'              => new WP_Post( 5, 'en', 'draft', 'page' ),
	'en/taken'        => new WP_Post( 6, 'taken', 'publish', 'page' ),
	'en/not-yet'      => new WP_Post( 7, 'not-yet', 'draft', 'page' ),
	'en/deep'         => new WP_Post( 8, 'deep', 'publish', 'page' ),
	'en/deep/deeper'  => new WP_Post( 9, 'deeper', 'publish', 'page' ),
);
for ( $i = 1; $i <= 120; $i++ ) {
	$GLOBALS['options'][ NH_VIEN_Links::OPTION ][ 1000 + $i ] = 2000 + $i;
	$GLOBALS['published'][]                                    = new WP_Post( 1000 + $i, 'vi-' . $i );
	$GLOBALS['published'][]                                    = new WP_Post( 2000 + $i, 'en-' . $i );
}
$GLOBALS['options'][ NH_VIEN_Links::OPTION ][1999] = 2999;
$GLOBALS['published'][]                            = new WP_Post( 1999, 'vi-taken' );
$GLOBALS['published'][]                            = new WP_Post( 2999, 'taken' );
$GLOBALS['options'][ NH_VIEN_Links::OPTION ][1998] = 2998;
$GLOBALS['published'][]                            = new WP_Post( 1998, 'vi-not-yet' );
$GLOBALS['published'][]                            = new WP_Post( 2998, 'not-yet' );
$GLOBALS['options'][ NH_VIEN_Links::INDEPENDENT_OPTION ] = array( 2997 );
$GLOBALS['published'][]                                  = new WP_Post( 2997, 'deeper' );
fresh();
$GLOBALS['page_lookups']  = 0;
$GLOBALS['child_queries'] = 0;
$public                   = NH_VIEN_Links::public_en_ids();
must( 120 + 1 + 1 === count( $public ), 'Public set wrong with many posts: ' . count( $public ) );
must( ! in_array( 2999, $public, true ), 'A page below /en/ must still take the address of the English post' );
must( in_array( 2998, $public, true ), 'A draft page below /en/ must not take an address' );
must( in_array( 2997, $public, true ), 'Only a page directly below /en/ takes an address (en/deeper is not en/deep/deeper)' );
must( 1 === $GLOBALS['page_lookups'], 'Looking at 122 English posts must cost one page lookup, not ' . $GLOBALS['page_lookups'] );
must( 1 === $GLOBALS['child_queries'], 'Looking at 122 English posts must cost one query for the pages below /en/, not ' . $GLOBALS['child_queries'] );
NH_VIEN_Links::public_en_posts();
NH_VIEN_Links::public_en_ids();
must( 1 === $GLOBALS['page_lookups'] && 1 === $GLOBALS['child_queries'], 'The answer must be remembered for the rest of the request' );
// Without the page /en/ nothing can be taken: no lookup of the children at all.
$GLOBALS['pages'] = array();
fresh();
$GLOBALS['page_lookups']  = 0;
$GLOBALS['child_queries'] = 0;
must( 123 === count( NH_VIEN_Links::public_en_ids() ), 'Without the page /en/ every routable English post is public' );
must( 1 === $GLOBALS['page_lookups'] && 0 === $GLOBALS['child_queries'], 'Without the page /en/ only the page itself is looked up' );
$GLOBALS['options'] = array();

echo "PASS: gate, rules, slugs, live state, pairs, request resolution, English addresses, deactivation, one lookup for the pages below /en/.\n";
