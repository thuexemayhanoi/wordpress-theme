<?php
/** Offline checks for the page-cache purge: what a change of a post makes stale when the post has an English side. */

// Test tooling: runs from the command line only, never through a web server.
if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/../../../../' );
define( 'OBJECT', 'OBJECT' );
define( 'NH_VIEN_VERSION', '0.5.1' );
define( 'NH_VIEN_PLUGIN_FILE', dirname( __DIR__ ) . '/nguyen-ha-vi-en.php' );
define( 'NH_VIEN_ENABLE_EN_ROUTES', true );

$GLOBALS['env']       = 'staging';
$GLOBALS['options']   = array();
$GLOBALS['published'] = array();
$GLOBALS['pages']     = array();
$GLOBALS['terms']     = array();
$GLOBALS['assigned']  = array();
$GLOBALS['post_terms'] = array();
$GLOBALS['hooks']     = array();

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
	public $parent = 0;
	public function __construct( $id, $taxonomy, $slug ) {
		$this->term_id  = $id;
		$this->taxonomy = $taxonomy;
		$this->slug     = $slug;
	}
}
class WP_Error {}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_get_environment_type() { return $GLOBALS['env']; }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['options'] ) ? $GLOBALS['options'][ $key ] : $default; }
function add_action( $tag ) { $GLOBALS['hooks'][] = $tag; }
function add_filter( $tag ) { $GLOBALS['hooks'][] = $tag; }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function user_trailingslashit( $url ) { return rtrim( $url, '/' ) . '/'; }
function get_page_by_path() { return null; }
function get_posts( $args ) { return array(); }
function is_taxonomy_hierarchical() { return true; }
function get_ancestors() { return array(); }
function get_term() { return null; }
function wp_get_object_terms( $ids, $taxonomies, $args ) {
	$rows = array();
	foreach ( $ids as $id ) {
		foreach ( isset( $GLOBALS['assigned'][ $id ] ) ? $GLOBALS['assigned'][ $id ] : array() as $term_id ) {
			$rows[] = $GLOBALS['terms'][ $term_id ];
		}
	}
	return $rows;
}
/** The terms of a post, as get_the_terms() answers (false when there are none). */
function get_the_terms( $post, $taxonomy ) {
	$found = array();
	foreach ( isset( $GLOBALS['assigned'][ $post->ID ] ) ? $GLOBALS['assigned'][ $post->ID ] : array() as $term_id ) {
		if ( $GLOBALS['terms'][ $term_id ]->taxonomy === $taxonomy ) {
			$found[] = $GLOBALS['terms'][ $term_id ];
		}
	}
	return $found ? $found : false;
}
/** The address of a post: the address the plugin gives a public English post, else the ordinary one. */
function get_permalink( $id ) {
	$english = NH_VIEN_Routes::english_url( $id );
	foreach ( $GLOBALS['published'] as $post ) {
		if ( $post->ID === $id ) {
			return '' !== $english ? $english : 'https://example.test/' . $post->post_name . '/';
		}
	}
	return false;
}
function must( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }

require __DIR__ . '/lib/db-stub.php';
foreach ( array( 'languages', 'links', 'gate', 'routes', 'context', 'urls', 'cache' ) as $part ) {
	require dirname( __DIR__ ) . '/includes/class-nh-vien-' . $part . '.php';
}

/** A fresh request with the rules stored. */
function fresh() {
	NH_VIEN_Routes::reset();
	NH_VIEN_Links::flush_cache();
	NH_VIEN_Context::reset();
	$GLOBALS['options']['rewrite_rules'] = NH_VIEN_Routes::rules();
}

// Pair 11 <-> 22 (both published), pair 33 <-> 44 (the Vietnamese post is a draft: its English side is hidden), an independent
// English post 55, a Vietnamese post 66 without English side. The English posts 22 and 55 are in category 201.
$GLOBALS['options'][ NH_VIEN_Links::OPTION ]             = array(
	11 => 22,
	33 => 44,
);
$GLOBALS['options'][ NH_VIEN_Links::INDEPENDENT_OPTION ] = array( 55 );
$GLOBALS['published']                                    = array(
	new WP_Post( 11, 'bai-viet' ),
	new WP_Post( 22, 'english-article' ),
	new WP_Post( 33, 'bai-nhap', 'draft' ),
	new WP_Post( 44, 'hidden-english' ),
	new WP_Post( 55, 'standalone' ),
	new WP_Post( 66, 'bai-khac' ),
	new WP_Post( 77, 'a-page', 'publish', 'page' ),
);
$GLOBALS['terms']                                        = array(
	201 => new WP_Term( 201, 'category', 'guides' ),
	202 => new WP_Term( 202, 'category', 'vietnamese-only' ),
	203 => new WP_Term( 203, 'post_tag', 'english-tag' ),
	204 => new WP_Term( 204, 'category', 'only-on-a-hidden-post' ),
);
$GLOBALS['assigned']                                     = array(
	11 => array( 202 ),
	44 => array( 204 ),
	22 => array( 201, 203 ),
	55 => array( 201 ),
	66 => array( 202 ),
);
$post = static function ( $id ) {
	foreach ( $GLOBALS['published'] as $candidate ) {
		if ( $candidate->ID === $id ) {
			return $candidate;
		}
	}
	return null;
};
$base = array( 'https://example.test/', 'https://example.test/something/' );

// ---- The filter is only there when the English side is switched on.
$GLOBALS['hooks'] = array();
NH_VIEN_Cache::boot();
must( array( 'nh_vien_pairs_changed', 'nh_seo_purge_post_urls' ) === $GLOBALS['hooks'], 'The purge filter must be registered with the listener: ' . json_encode( $GLOBALS['hooks'] ) );

// ---- A Vietnamese post with an English side: the English side is purged too (its language switcher names this post).
fresh();
$out = NH_VIEN_Cache::purge_urls( $base, $post( 11 ) );
must( array_merge( $base, array( 'https://example.test/en/english-article/' ) ) === $out, 'The English side of a Vietnamese post must be purged: ' . json_encode( $out ) );

// ---- An English post of a pair: the Vietnamese side, the English home and the English archives of its categories and tags.
fresh();
$out = NH_VIEN_Cache::purge_urls( $base, $post( 22 ) );
must(
	array_merge(
		$base,
		array(
			'https://example.test/bai-viet/',
			'https://example.test/en/',
			'https://example.test/en/category/guides/',
			'https://example.test/en/tag/english-tag/',
		)
	) === $out,
	'An English post must purge its Vietnamese side, the English home and its English archives: ' . json_encode( $out )
);

// ---- An independent English post has no other side.
fresh();
$out = NH_VIEN_Cache::purge_urls( $base, $post( 55 ) );
must( array_merge( $base, array( 'https://example.test/en/', 'https://example.test/en/category/guides/' ) ) === $out, 'An independent English post purges the English home and its archives: ' . json_encode( $out ) );

// ---- A pair whose English side is hidden: the English address does not exist, so there is nothing to purge for it, and its
// terms have no English archive.
fresh();
$out = NH_VIEN_Cache::purge_urls( $base, $post( 44 ) );
must( array_merge( $base, array( 'https://example.test/bai-nhap/', 'https://example.test/en/' ) ) === $out, 'A hidden English post purges its Vietnamese side and the English home, and no empty address: ' . json_encode( $out ) );

// ---- Everything else is left alone.
fresh();
must( $base === NH_VIEN_Cache::purge_urls( $base, $post( 66 ) ), 'A post without an English side needs nothing more' );
must( $base === NH_VIEN_Cache::purge_urls( $base, $post( 77 ) ), 'A page needs nothing more' );
must( 'x' === NH_VIEN_Cache::purge_urls( 'x', $post( 11 ) ), 'What is not a list of addresses must be returned as it came' );
must( $base === NH_VIEN_Cache::purge_urls( $base, 11 ), 'What is not a post must be ignored' );
$GLOBALS['options']['rewrite_rules'] = array();
NH_VIEN_Routes::reset();
must( $base === NH_VIEN_Cache::purge_urls( $base, $post( 22 ) ), 'A site whose rules are not stored needs nothing more' );

echo "PASS: a change of a post purges the other language of its pair, the English home and the English archives.\n";
