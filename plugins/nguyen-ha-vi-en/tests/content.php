<?php
/** Offline checks for the English menus (never the Vietnamese menu on an English page) and the feed links of English pages. */

// Test tooling: runs from the command line only, never through a web server.
if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/../../../../' );
define( 'OBJECT', 'OBJECT' );
define( 'NH_VIEN_VERSION', '0.5.1' );
define( 'NH_VIEN_PLUGIN_FILE', dirname( __DIR__ ) . '/nguyen-ha-vi-en.php' );
define( 'NH_VIEN_ENABLE_EN_ROUTES', true );

$GLOBALS['env']          = 'staging';
$GLOBALS['options']      = array();
$GLOBALS['hooks']        = array();
$GLOBALS['removed']      = array();
$GLOBALS['published']    = array();
$GLOBALS['pages']        = array();
$GLOBALS['terms']        = array();
$GLOBALS['assigned']     = array();
$GLOBALS['locations']    = array();
$GLOBALS['menus']        = array();
$GLOBALS['terms_calls']  = array();
$GLOBALS['permalinks']   = array();

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
	public $name;
	public $parent;
	public function __construct( $id, $slug, $name, $parent = 0, $taxonomy = 'category' ) {
		$this->term_id  = $id;
		$this->slug     = $slug;
		$this->name     = $name;
		$this->parent   = $parent;
		$this->taxonomy = $taxonomy;
	}
}
class WP_Error {}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_get_environment_type() { return $GLOBALS['env']; }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['options'] ) ? $GLOBALS['options'][ $key ] : $default; }
function add_action( $tag, $callback = null, $priority = 10 ) { $GLOBALS['hooks'][] = $tag; }
function add_filter( $tag, $callback = null, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][] = $tag; }
function add_shortcode( $tag ) { $GLOBALS['hooks'][] = 'shortcode:' . $tag; }
function remove_action( $tag, $callback, $priority = 10 ) { $GLOBALS['removed'][] = $tag . ':' . $callback . ':' . $priority; }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function esc_url( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function user_trailingslashit( $url ) { return rtrim( $url, '/' ) . '/'; }
function trailingslashit( $value ) { return rtrim( $value, '/' ) . '/'; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function sanitize_text_field( $value ) { return trim( (string) $value ); }
function wp_unslash( $value ) { return $value; }
function must( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }

/** The menus of the site, by slug (a test fills $GLOBALS['menus']) and the menu assigned to each theme location. */
function get_nav_menu_locations() { return $GLOBALS['locations']; }
function wp_get_nav_menu_object( $menu ) {
	foreach ( $GLOBALS['menus'] as $object ) {
		if ( $object->slug === $menu || $object->term_id === $menu ) {
			return $object;
		}
	}
	return false;
}
function get_page_by_path( $path ) { return isset( $GLOBALS['pages'][ $path ] ) ? $GLOBALS['pages'][ $path ] : null; }
function get_permalink( $id ) { return 'https://example.test/en/' . $GLOBALS['permalinks'][ $id ] . '/'; }
function get_term_link( $term ) { return 'https://example.test/en/category/' . $term->slug . '/'; }
function get_posts( $args ) {
	if ( isset( $args['post_parent'] ) ) {
		$found = array();
		foreach ( $GLOBALS['pages'] as $path => $page ) {
			if ( 0 === strpos( $path, 'en/' ) && 'publish' === $page->post_status ) {
				$found[] = $page;
			}
		}
		return $found;
	}
	return array();
}
function wp_get_object_terms( $ids, $taxonomies, $args ) {
	$rows = array();
	foreach ( $ids as $id ) {
		foreach ( isset( $GLOBALS['assigned'][ $id ] ) ? $GLOBALS['assigned'][ $id ] : array() as $term_id ) {
			$rows[] = $GLOBALS['terms'][ $term_id ];
		}
	}
	return $rows;
}
function get_ancestors( $id ) {
	$out = array();
	while ( isset( $GLOBALS['terms'][ $id ] ) && $GLOBALS['terms'][ $id ]->parent ) {
		$id    = $GLOBALS['terms'][ $id ]->parent;
		$out[] = $id;
	}
	return $out;
}
function get_terms( $args ) {
	$GLOBALS['terms_calls'][] = $args;
	$found                    = array();
	foreach ( $GLOBALS['terms'] as $term ) {
		if ( $term->taxonomy === $args['taxonomy'] && in_array( $term->term_id, $args['include'], true ) && (int) $term->parent === $args['parent'] ) {
			$found[] = $term;
		}
	}
	usort( $found, static function ( $a, $b ) { return strcmp( $a->name, $b->name ); } );
	return array_slice( $found, 0, $args['number'] );
}

require __DIR__ . '/lib/db-stub.php';
foreach ( array( 'languages', 'links', 'gate', 'routes', 'context', 'content' ) as $part ) {
	require dirname( __DIR__ ) . '/includes/class-nh-vien-' . $part . '.php';
}

/** A fresh English request with the rules stored. */
function fresh( $language = 'en' ) {
	NH_VIEN_Routes::reset();
	NH_VIEN_Links::flush_cache();
	NH_VIEN_Context::reset();
	NH_VIEN_Context::set( $language );
	$GLOBALS['options']['rewrite_rules'] = NH_VIEN_Routes::rules();
	$GLOBALS['hooks']                    = array();
	$GLOBALS['removed']                  = array();
	$GLOBALS['terms_calls']              = array();
}

/** The menu object WordPress would hand out: term_id, slug. */
function menu( $id, $slug ) {
	return (object) array(
		'term_id' => $id,
		'slug'    => $slug,
	);
}

/** What wp_nav_menu() does with the arguments before it looks for a menu: the filter, then the object. */
function nav_args( $args ) {
	return (object) NH_VIEN_Content::nav_menu_args( $args );
}

$primary = array(
	'theme_location' => 'primary',
	'container'      => false,
	'menu_class'     => 'nav-list',
	'menu_id'        => 'primary-menu',
	'fallback_cb'    => 'nh_editorial_fallback_menu',
);

// ---- Which menu an English page gets.
$GLOBALS['menus']     = array( menu( 10, 'primary' ), menu( 11, 'footer' ) );
$GLOBALS['locations'] = array(
	'primary' => 10,
	'footer'  => 11,
);
fresh( 'vi' );
must( $primary === NH_VIEN_Content::nav_menu_args( $primary ), 'A Vietnamese page keeps its menu' );
fresh( 'en' );
must( 'not an array' === NH_VIEN_Content::nav_menu_args( 'not an array' ), 'Arguments that are not an array must pass through' );
$by_name = array( 'menu' => 'primary' );
must( $by_name === NH_VIEN_Content::nav_menu_args( $by_name ), 'A menu that a theme asks for by name, without a location, is left as it is' );

$out = NH_VIEN_Content::nav_menu_args( $primary );
must( true === $out[ NH_VIEN_Content::FALLBACK_ARG ] && ! isset( $out['menu'] ), 'Without an English menu the location must be marked for the English-only menu: ' . json_encode( $out ) );
must( 'primary' === $out['theme_location'], 'The location must stay: the answer is given before any menu is looked up' );

$GLOBALS['menus'][] = menu( 20, 'primary-en' );
$out                = NH_VIEN_Content::nav_menu_args( $primary );
must( 20 === $out['menu']->term_id && ! isset( $out[ NH_VIEN_Content::FALLBACK_ARG ] ), 'The menu primary-en must be used for the location primary' );

$GLOBALS['menus']     = array( menu( 10, 'main' ), menu( 21, 'main-en' ) );
$GLOBALS['locations'] = array( 'primary' => 10 );
$out                  = NH_VIEN_Content::nav_menu_args( $primary );
must( 21 === $out['menu']->term_id, 'The English menu of the menu assigned to the location (main-en) must be used' );
$footer = array( 'theme_location' => 'footer' );
$out    = NH_VIEN_Content::nav_menu_args( $footer );
must( true === $out[ NH_VIEN_Content::FALLBACK_ARG ], 'A location without any menu gets the English-only menu' );

// ---- The English-only menu replaces the menu before WordPress looks for one: not even the first menu with items shows.
$GLOBALS['menus']     = array( menu( 10, 'vietnamese-main' ) );
$GLOBALS['locations'] = array( 'primary' => 10 );
$args                 = nav_args( $primary );
must( null === NH_VIEN_Content::fallback_menu_output( null, (object) $primary ), 'A menu that was not marked must be left to WordPress' );
must( 'already built' === NH_VIEN_Content::fallback_menu_output( 'already built', $args ), 'Output that another filter produced must be kept' );
must( null === NH_VIEN_Content::fallback_menu_output( null, 'not an object' ), 'Arguments that are not an object must be ignored' );

// Home only, while nothing else exists.
fresh( 'en' );
$html = NH_VIEN_Content::fallback_menu_output( null, $args );
must( is_string( $html ) && 0 === strpos( $html, '<ul class="nav-list" id="primary-menu">' ), 'The English-only menu must keep the class and the id the theme asked for: ' . $html );
must( 1 === substr_count( $html, '<li' ) && false !== strpos( $html, '<a href="https://example.test/en/">Home</a>' ), 'Without pages or categories the menu is the home: ' . $html );
must( array() === $GLOBALS['terms_calls'], 'Without English posts no category is looked up (an empty include would list every category)' );

// Pages below /en/: About, Prices and Contact, when published; nothing else.
$GLOBALS['pages']      = array(
	'en'          => new WP_Post( 5, 'en', 'publish', 'page' ),
	'en/about'    => new WP_Post( 7, 'about', 'publish', 'page' ),
	'en/contact'  => new WP_Post( 8, 'contact', 'draft', 'page' ),
	'en/random'   => new WP_Post( 9, 'random', 'publish', 'page' ),
);
$GLOBALS['permalinks'] = array(
	7 => 'about',
	8 => 'contact',
	9 => 'random',
);
fresh( 'en' );
$html = NH_VIEN_Content::fallback_menu_output( null, $args );
must( false !== strpos( $html, '>About</a>' ) && false === strpos( $html, 'Contact' ) && false === strpos( $html, 'random' ), 'The menu must list the pages About, Prices and Contact that are published: ' . $html );

// Categories: only those that have English posts, top level only, never one that would lead to a 404.
$GLOBALS['options'][ NH_VIEN_Links::INDEPENDENT_OPTION ] = array( 77 );
$GLOBALS['options'][ NH_VIEN_Links::EN_TERMS_OPTION ]    = array( 201, 203 );
$GLOBALS['published'][]                                  = new WP_Post( 77, 'english-guide' );
$GLOBALS['terms']                                        = array(
	201 => new WP_Term( 201, 'guides', 'Guides' ),
	202 => new WP_Term( 202, 'rental-guides', 'Rental guides', 201 ),
	203 => new WP_Term( 203, 'declared-but-empty', 'Declared but empty' ),
	204 => new WP_Term( 204, 'english-tag', 'English tag', 0, 'post_tag' ),
	300 => new WP_Term( 300, 'vietnamese-only', 'Vietnamese only' ),
);
$GLOBALS['assigned']                                     = array( 77 => array( 202, 204 ) );
fresh( 'en' );
$html = NH_VIEN_Content::fallback_menu_output( null, $args );
must( false !== strpos( $html, 'https://example.test/en/category/guides/">Guides</a>' ), 'The parent of a category that has English posts must be listed: ' . $html );
must( false === strpos( $html, 'Rental guides' ) && false === strpos( $html, 'Declared' ) && false === strpos( $html, 'Vietnamese only' ) && false === strpos( $html, 'English tag' ), 'Only top-level categories that have English posts may be listed (no child, no empty one, no tag, no Vietnamese one): ' . $html );
must( 1 === count( $GLOBALS['terms_calls'] ) && 'category' === $GLOBALS['terms_calls'][0]['taxonomy'] && 0 === $GLOBALS['terms_calls'][0]['parent'] && 6 === $GLOBALS['terms_calls'][0]['number'], 'One query for the categories, top level, six at most' );
$included = $GLOBALS['terms_calls'][0]['include'];
sort( $included );
must( array( 201, 202, 204 ) === $included, 'The query must be limited to the terms of the English posts: ' . json_encode( $included ) );

// What is printed is escaped, and the id and the class are used as given by the theme (and have defaults).
$GLOBALS['terms'][201]->name = '<b>Guides</b> & "tips"';
$GLOBALS['terms'][201]->slug = 'guides&"x';
fresh( 'en' );
$html = NH_VIEN_Content::fallback_menu_output( null, (object) array( NH_VIEN_Content::FALLBACK_ARG => true, 'menu_class' => 'footer-menu__list', 'menu_id' => 'english-footer-menu' ) );
must( false !== strpos( $html, '&lt;b&gt;Guides&lt;/b&gt; &amp; &quot;tips&quot;' ) && 0 === strpos( $html, '<ul class="footer-menu__list" id="english-footer-menu">' ), 'The names of categories must be escaped: ' . $html );
must( false !== strpos( $html, 'href="https://example.test/en/category/guides&amp;&quot;x/"' ), 'The addresses of categories must be escaped: ' . $html );
$html = NH_VIEN_Content::fallback_menu_output( null, (object) array( NH_VIEN_Content::FALLBACK_ARG => true, 'menu_class' => '', 'menu_id' => '' ) );
must( 0 === strpos( $html, '<ul class="nav-list" id="english-navigation">' ), 'An empty class or id must fall back to the defaults: ' . $html );
$GLOBALS['terms'][201]->name = 'Guides';
$GLOBALS['terms'][201]->slug = 'guides';

// The footer of the theme calls the function directly: it echoes, as before.
ob_start();
NH_VIEN_Content::fallback_english_menu( array( 'menu_class' => 'footer-menu__list', 'menu_id' => 'english-footer-menu' ) );
$echoed = ob_get_clean();
must( 0 === strpos( $echoed, '<ul class="footer-menu__list" id="english-footer-menu">' ), 'The menu function must still print its list' );

// ---- Feed links: English pages advertise none (there is no English feed, and /en/{slug}/feed/ is a 404).
fresh( 'en' );
NH_VIEN_Content::drop_feed_links();
must( array( 'wp_head:feed_links:2', 'wp_head:feed_links_extra:3' ) === $GLOBALS['removed'], 'English pages must drop both feed links: ' . json_encode( $GLOBALS['removed'] ) );
fresh( 'vi' );
NH_VIEN_Content::drop_feed_links();
must( array() === $GLOBALS['removed'], 'Vietnamese pages keep their feed links' );
fresh( 'en' );
$GLOBALS['options']['rewrite_rules'] = array();
NH_VIEN_Routes::reset();
NH_VIEN_Content::drop_feed_links();
must( array() === $GLOBALS['removed'], 'A site whose rules are not stored keeps every feed link' );

// ---- The hooks of the module when the gate is open.
fresh( 'en' );
NH_VIEN_Content::boot();
$hooks = $GLOBALS['hooks'];
sort( $hooks );
must( array( 'pre_wp_nav_menu', 'shortcode:nh_vien_posts', 'template_include', 'the_title', 'wp', 'wp_nav_menu_args' ) === $hooks, 'Unexpected hooks: ' . json_encode( $hooks ) );

echo "PASS: English menus never fall back to the Vietnamese menu, categories without English posts are left out, English pages advertise no feed.\n";
