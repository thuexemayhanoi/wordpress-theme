<?php
/** Offline checks for the sitemap entries of Yoast SEO: English-only terms move to their English address, shared terms get an extra entry. */

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
$GLOBALS['published'] = array();
$GLOBALS['terms']     = array();
$GLOBALS['assigned']  = array();
$GLOBALS['parents']   = array();

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
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_get_environment_type() { return $GLOBALS['env']; }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['options'] ) ? $GLOBALS['options'][ $key ] : $default; }
function add_action( $tag ) {}
function add_filter( $tag ) {}
function __( $text ) { return $text; }
function esc_url( $url ) { return $url; }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function trailingslashit( $value ) { return rtrim( $value, '/' ) . '/'; }
function user_trailingslashit( $url ) { return rtrim( $url, '/' ) . '/'; }
function is_taxonomy_hierarchical( $taxonomy ) { return 'category' === $taxonomy; }
function get_page_by_path() { return null; }
function get_posts( $args ) {
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
function must( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }

foreach ( array( 'languages', 'links', 'routes', 'context', 'urls', 'seo' ) as $part ) {
	require dirname( __DIR__ ) . '/includes/class-nh-vien-' . $part . '.php';
}

/** A fresh request. */
function fresh() {
	NH_VIEN_Routes::reset();
	NH_VIEN_Links::flush_cache();
	NH_VIEN_Context::reset();
}

$GLOBALS['options']['rewrite_rules']         = NH_VIEN_Routes::rules();
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
// 1: Vietnamese posts only. 2: both languages. 3 (with the parent 6) and 4 (a tag): the shown English post only (and the
// hidden one). 8: the hidden English post only.
$GLOBALS['terms']    = array(
	1 => new WP_Term( 1, 'category', 'vi-only', 5 ),
	2 => new WP_Term( 2, 'category', 'shared', 3 ),
	3 => new WP_Term( 3, 'category', 'en-only', 2 ),
	4 => new WP_Term( 4, 'post_tag', 'en-tag', 2 ),
	6 => new WP_Term( 6, 'category', 'en-parent', 0 ),
	8 => new WP_Term( 8, 'category', 'hidden-only', 1 ),
);
$GLOBALS['assigned'] = array(
	22 => array( 2, 3, 4 ),
	44 => array( 3, 4, 8 ),
);
$GLOBALS['parents']  = array( 3 => 6 );

$entry = function ( $term ) {
	return array(
		'loc' => 'https://example.test/category/' . $term->slug . '/',
		'mod' => '2026-01-01 00:00:00',
	);
};
$term  = function ( $id ) {
	return $GLOBALS['terms'][ $id ];
};

// ---- A term that only English posts use moves to its English address; everything else about the entry stays.
fresh();
$out = NH_VIEN_Seo::sitemap_entry( $entry( $term( 3 ) ), 'term', $term( 3 ) );
must( 'https://example.test/en/category/en-parent/en-only/' === $out['loc'] && '2026-01-01 00:00:00' === $out['mod'], 'An English-only category must take its English address: ' . json_encode( $out ) );
$out = NH_VIEN_Seo::sitemap_entry( $entry( $term( 4 ) ), 'term', $term( 4 ) );
must( 'https://example.test/en/tag/en-tag/' === $out['loc'], 'An English-only tag must take its English address: ' . json_encode( $out ) );
$out = NH_VIEN_Seo::sitemap_entry( $entry( $term( 6 ) ), 'term', $term( 6 ) );
must( 'https://example.test/en/category/en-parent/' === $out['loc'], 'The parent of an English-only category must take its English address: ' . json_encode( $out ) );

// ---- A term that only a post that may not be shown yet uses has no archive: the entry is dropped.
must( array() === NH_VIEN_Seo::sitemap_entry( $entry( $term( 8 ) ), 'term', $term( 8 ) ), 'A term of hidden English posts only must leave the sitemap' );

// ---- Terms of both languages keep their entry; their English archive comes after the others, once.
$shared = $entry( $term( 2 ) );
must( $shared === NH_VIEN_Seo::sitemap_entry( $shared, 'term', $term( 2 ) ), 'A term of both languages must keep its entry' );
NH_VIEN_Seo::sitemap_entry( $shared, 'term', $term( 2 ) );
$vi_only = $entry( $term( 1 ) );
must( $vi_only === NH_VIEN_Seo::sitemap_entry( $vi_only, 'term', $term( 1 ) ), 'A Vietnamese-only term must keep its entry' );
$xml = NH_VIEN_Seo::sitemap_categories( '' );
must( "\t<url>\n\t\t<loc>https://example.test/en/category/shared/</loc>\n\t</url>\n" === $xml, 'The English archive of a shared term must follow, once: ' . json_encode( $xml ) );
must( '' === NH_VIEN_Seo::sitemap_categories( '' ), 'What was added must not be added again' );
must( '' === NH_VIEN_Seo::sitemap_tags( '' ), 'No tag of both languages, no English tag entry' );
must( "kept\n" === NH_VIEN_Seo::sitemap_categories( "kept\n" ), 'Entries that were already there must be kept' );

// ---- What is not a term, or not live, is left alone.
$post_entry = array( 'loc' => 'https://example.test/bai-viet/' );
must( $post_entry === NH_VIEN_Seo::sitemap_entry( $post_entry, 'post', new WP_Post( 11, 'bai-viet' ) ), 'A post entry must be left alone' );
must( false === NH_VIEN_Seo::sitemap_entry( false, 'term', $term( 3 ) ), 'An entry that is already gone must stay gone' );
NH_VIEN_Routes::reset();
$GLOBALS['options']['rewrite_rules'] = array();
$plain                               = $entry( $term( 3 ) );
must( $plain === NH_VIEN_Seo::sitemap_entry( $plain, 'term', $term( 3 ) ), 'A site whose rules are not stored must not be touched' );
must( '' === NH_VIEN_Seo::sitemap_categories( '' ), 'A site whose rules are not stored must get no English entry' );

echo "PASS: sitemap entries of English-only terms, shared terms, hidden terms and the closed gate.\n";
