<?php
/** Offline WordPress stub tests: explicit reciprocal VI/EN post linking. */

// Test tooling: runs from the command line only, never through a web server.
if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/../../../../' );
$GLOBALS['mock_options'] = array();
$GLOBALS['mock_writes'] = array();
$GLOBALS['mock_posts'] = array();
$GLOBALS['allow_manage'] = true;
$GLOBALS['allow_edit'] = true;
$GLOBALS['nonce_ok'] = true;
$GLOBALS['hooks'] = array();
$GLOBALS['fired'] = array();

class WP_Post {
	public $post_type = 'post';
	public $post_status = 'publish';
	public function __construct( $type = 'post', $status = 'publish' ) {
		$this->post_type = $type;
		$this->post_status = $status;
	}
}
class WP_Term {
	public $term_id;
	public $parent;
	public $count;
	public $name;
	public function __construct( $id, $parent = 0, $count = 0, $name = '' ) {
		$this->term_id = $id;
		$this->parent = $parent;
		$this->count = $count;
		$this->name = $name;
	}
}
$GLOBALS['mock_terms'] = array();
function get_term( $id, $taxonomy = 'category' ) {
	return isset( $GLOBALS['mock_terms'][ $id ] ) ? $GLOBALS['mock_terms'][ $id ] : null;
}
function get_term_children( $id, $taxonomy = 'category' ) {
	$out = array();
	foreach ( $GLOBALS['mock_terms'] as $term ) {
		if ( $term->parent === $id ) {
			$out[] = $term->term_id;
			$out = array_merge( $out, get_term_children( $term->term_id, $taxonomy ) );
		}
	}
	return $out;
}
class WP_Error {
	private $code;
	public function __construct( $code, $message ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['mock_options'] ) ? $GLOBALS['mock_options'][ $key ] : $default;
}
function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['mock_options'][ $key ] = $value;
	$GLOBALS['mock_writes'][] = array( $key, $value, $autoload );
	return true;
}
$GLOBALS['page_uris'] = array();
function get_page_uri( $id ) {
	return isset( $GLOBALS['page_uris'][ $id ] ) ? $GLOBALS['page_uris'][ $id ] : '';
}
function get_post( $id ) { return isset( $GLOBALS['mock_posts'][ $id ] ) ? $GLOBALS['mock_posts'][ $id ] : null; }
// As in WordPress: nobody may edit a post that no longer exists (the capability maps to do_not_allow).
function current_user_can( $cap, $id = null ) {
	if ( 'manage_options' === $cap ) {
		return $GLOBALS['allow_manage'];
	}
	if ( null !== $id && ! isset( $GLOBALS['mock_posts'][ $id ] ) ) {
		return false;
	}
	return $GLOBALS['allow_edit'];
}
function wp_verify_nonce( $nonce, $action ) { return $GLOBALS['nonce_ok'] && 'ok' === $nonce && 'nh_vien_manage_links' === $action; }
function add_action( $name, $callback, $priority = 10 ) { $GLOBALS['hooks'][] = $name; }
function register_deactivation_hook() {}
function do_action( $name ) { $GLOBALS['fired'][] = func_get_args(); }
function is_admin() { return false; }
function must( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }
function error_is( $result, $code ) { return is_wp_error( $result ) && $code === $result->get_error_code(); }

require dirname( __DIR__ ) . '/nguyen-ha-vi-en.php';

foreach ( array( 11, 22, 33, 44, 45, 55, 66, 77 ) as $id ) {
	$GLOBALS['mock_posts'][ $id ] = new WP_Post();
}
$GLOBALS['mock_posts'][55] = new WP_Post( 'page' );
$GLOBALS['mock_posts'][66] = new WP_Post( 'post', 'trash' );
$GLOBALS['mock_posts'][77] = new WP_Post( 'revision', 'inherit' );
// 11, 33 and 44 are published Vietnamese posts; the English side of a pair must still be a draft or pending (22, 45).
$GLOBALS['mock_posts'][22] = new WP_Post( 'post', 'draft' );
$GLOBALS['mock_posts'][45] = new WP_Post( 'post', 'pending' );

must( '0.5.1' === NH_VIEN_VERSION, 'Wrong version' );
must( array() === NH_VIEN_Links::all(), 'Map should start empty' );
must( array() === $GLOBALS['mock_writes'], 'Bootstrap must not write' );
must( null === NH_VIEN_Links::for_post( 11 ), 'No implicit pairing' );
must( error_is( NH_VIEN_Links::link( 11, 22, 'bad' ), 'nh_vien_bad_nonce' ), 'Bad nonce accepted' );
$GLOBALS['allow_manage'] = false;
must( error_is( NH_VIEN_Links::link( 11, 22, 'ok' ), 'nh_vien_forbidden' ), 'Non-admin accepted' );
$GLOBALS['allow_manage'] = true;
$GLOBALS['allow_edit'] = false;
must( error_is( NH_VIEN_Links::link( 11, 22, 'ok' ), 'nh_vien_forbidden' ), 'Edit permission ignored' );
$GLOBALS['allow_edit'] = true;
must( error_is( NH_VIEN_Links::link( 11, 11, 'ok' ), 'nh_vien_invalid_ids' ), 'Self-link accepted' );
must( error_is( NH_VIEN_Links::link( '11x', 22, 'ok' ), 'nh_vien_invalid_ids' ), 'Dirty ID accepted' );
must( error_is( NH_VIEN_Links::link( 11, 999, 'ok' ), 'nh_vien_invalid_posts' ), 'Missing post accepted' );
must( error_is( NH_VIEN_Links::link( 11, 55, 'ok' ), 'nh_vien_invalid_posts' ), 'Page accepted' );
must( error_is( NH_VIEN_Links::link( 11, 66, 'ok' ), 'nh_vien_invalid_posts' ), 'Trash accepted' );
must( error_is( NH_VIEN_Links::link( 11, 77, 'ok' ), 'nh_vien_invalid_posts' ), 'Revision accepted' );
must( true === NH_VIEN_Links::link( 11, 22, 'ok' ), 'Valid pair rejected' );
must( array( array( 'nh_vien_pairs_changed', 11, 22 ) ) === $GLOBALS['fired'], 'A new pair must announce itself so that caches are purged' );
must( count( $GLOBALS['mock_writes'] ) === 1, 'Unexpected write count' );
must( NH_VIEN_Links::OPTION === $GLOBALS['mock_writes'][0][0], 'Unexpected data destination' );
must( false === $GLOBALS['mock_writes'][0][2], 'Option must not autoload' );
must( array( 11 => 22 ) === NH_VIEN_Links::all(), 'Map mismatch' );
must( array( 'vi' => 11, 'en' => 22 ) === NH_VIEN_Links::for_post( 11 ), 'VI lookup failed' );
must( array( 'vi' => 11, 'en' => 22 ) === NH_VIEN_Links::for_post( 22 ), 'EN lookup failed' );
must( 22 === NH_VIEN_Links::en_for_vi( 11 ) && 11 === NH_VIEN_Links::vi_for_en( 22 ), 'Partner lookups wrong' );
must( 0 === NH_VIEN_Links::en_for_vi( 22 ) && 0 === NH_VIEN_Links::vi_for_en( 11 ) && 0 === NH_VIEN_Links::vi_for_en( 999 ), 'A partner was invented' );
must( 'en' === NH_VIEN_Links::language_of( 22 ) && 'vi' === NH_VIEN_Links::language_of( 11 ) && 'vi' === NH_VIEN_Links::language_of( 999 ), 'Language of a post wrong' );
must( array( 22 ) === NH_VIEN_Links::en_ids() && NH_VIEN_Links::is_en( 22 ) && ! NH_VIEN_Links::is_en( 11 ), 'English post set wrong' );
must( true === NH_VIEN_Links::link( 11, 22, 'ok' ), 'Repeat pair not idempotent' );
must( 1 === count( $GLOBALS['mock_writes'] ), 'Repeat pair triggered write' );
must( error_is( NH_VIEN_Links::link( 11, 33, 'ok' ), 'nh_vien_conflict' ), 'VI conflict allowed' );
must( error_is( NH_VIEN_Links::link( 33, 22, 'ok' ), 'nh_vien_conflict' ), 'EN conflict allowed' );
must( error_is( NH_VIEN_Links::link( 22, 33, 'ok' ), 'nh_vien_conflict' ), 'EN reused as VI' );
must( error_is( NH_VIEN_Links::link( 33, 11, 'ok' ), 'nh_vien_conflict' ), 'VI reused as EN' );
must( error_is( NH_VIEN_Links::link( 33, 44, 'ok' ), 'nh_vien_invalid_posts' ), 'A published post became the English side of a pair' );
must( count( $GLOBALS['mock_writes'] ) === 1, 'A refused pair must not write' );
must( true === NH_VIEN_Links::link( 33, 45, 'ok' ), 'Independent pair rejected (a pending English post)' );
must( count( NH_VIEN_Links::all() ) === 2, 'Second pair not stored' );
must( error_is( NH_VIEN_Links::unlink( 11, 'bad' ), 'nh_vien_bad_nonce' ), 'Unlink nonce ignored' );
$GLOBALS['allow_edit'] = false;
must( error_is( NH_VIEN_Links::unlink( 11, 'ok' ), 'nh_vien_forbidden' ), 'Unlink edit permission ignored' );
$GLOBALS['allow_edit'] = true;
must( true === NH_VIEN_Links::unlink( 11, 'ok' ), 'Unlink failed' );
must( array( 'nh_vien_pairs_changed', 11, 22 ) === end( $GLOBALS['fired'] ), 'Unlink must announce itself with the pair it removed' );
must( null === NH_VIEN_Links::for_post( 22 ), 'Old counterpart still resolves' );
must( array( 33 => 45 ) === NH_VIEN_Links::all(), 'Unlink removed wrong pair' );
must( error_is( NH_VIEN_Links::unlink( 11, 'ok' ), 'nh_vien_not_found' ), 'Missing pair ignored' );
$GLOBALS['mock_options'][NH_VIEN_Links::OPTION] = array( 11 => 44, 33 => 44 );
must( is_wp_error( NH_VIEN_Links::all() ), 'Duplicate target allowed' );
$old_writes = count( $GLOBALS['mock_writes'] );
must( error_is( NH_VIEN_Links::link( 11, 22, 'ok' ), 'nh_vien_invalid_store' ), 'Bad map not rejected' );
must( $old_writes === count( $GLOBALS['mock_writes'] ), 'Bad map overwritten' );
$GLOBALS['mock_options'][NH_VIEN_Links::OPTION] = array( 11 => 22, 22 => 33 );
must( is_wp_error( NH_VIEN_Links::all() ), 'Language-column overlap allowed' );
$GLOBALS['mock_options'][NH_VIEN_Links::OPTION] = array( 11 => 22, 33 => 11 );
must( is_wp_error( NH_VIEN_Links::all() ), 'Reversed language-column overlap allowed' );
$GLOBALS['mock_options'][NH_VIEN_Links::OPTION] = 'bad';
must( is_wp_error( NH_VIEN_Links::all() ), 'Non-array store allowed' );

/** Once the English post of a pair is published, saving the same pair again is still a harmless repeat. */
$GLOBALS['mock_options'][ NH_VIEN_Links::OPTION ] = array( 11 => 22 );
NH_VIEN_Links::flush_cache();
$GLOBALS['mock_posts'][22]->post_status = 'publish';
$writes = count( $GLOBALS['mock_writes'] );
must( true === NH_VIEN_Links::link( 11, 22, 'ok' ), 'A repeat of a live pair must stay harmless' );
must( $writes === count( $GLOBALS['mock_writes'] ), 'A repeat of a live pair must not write' );
$GLOBALS['mock_posts'][22]->post_status = 'draft';
$GLOBALS['mock_options'][ NH_VIEN_Links::OPTION ] = array();
NH_VIEN_Links::flush_cache();

/** Standalone EN articles: no automatic translation and no change to Vietnamese article data. */
$GLOBALS['mock_posts'][88] = new WP_Post( 'post', 'draft' );
$GLOBALS['mock_options'][ NH_VIEN_Links::OPTION ] = array();
NH_VIEN_Links::flush_cache();
must( array() === NH_VIEN_Links::independent_ids(), 'No EN article should be pre-registered' );
must( error_is( NH_VIEN_Links::mark_english( 44, 'bad' ), 'nh_vien_bad_nonce' ), 'English marker ignored nonce' );
must( error_is( NH_VIEN_Links::mark_english( 55, 'ok' ), 'nh_vien_invalid_posts' ), 'English marker accepted a page' );
must( error_is( NH_VIEN_Links::mark_english( 66, 'ok' ), 'nh_vien_invalid_posts' ), 'English marker accepted a trashed post' );
must( error_is( NH_VIEN_Links::mark_english( 44, 'ok' ), 'nh_vien_invalid_posts' ), 'Existing published Vietnamese article was relabeled' );
must( true === NH_VIEN_Links::mark_english( 88, 'ok' ), 'Could not designate standalone English draft article' );
must( array( 88 ) === NH_VIEN_Links::en_ids(), 'Standalone English ID not included' );
must( 'en' === NH_VIEN_Links::language_of( 88 ), 'Standalone article has wrong language' );
must( null === NH_VIEN_Links::for_post( 88 ), 'Standalone article should not invent a Vietnamese counterpart' );
must( true === NH_VIEN_Links::link( 11, 22, 'ok' ), 'Valid pair should still work' );
must( error_is( NH_VIEN_Links::mark_english( 11, 'ok' ), 'nh_vien_conflict' ), 'Vietnamese half of a pair was marked English' );
must( error_is( NH_VIEN_Links::link( 88, 33, 'ok' ), 'nh_vien_conflict' ), 'Standalone English article reused as Vietnamese' );
must( true === NH_VIEN_Links::unmark_english( 88, 'ok' ), 'Could not unregister independent English article' );
must( 'vi' === NH_VIEN_Links::language_of( 88 ), 'Unregistered article did not return to default language' );
must( true === NH_VIEN_Links::unlink( 11, 'ok' ), 'Could not unlink test pair' );

/** A post is either the English side of a pair or an independent English article, never both. */
must( true === NH_VIEN_Links::mark_english( 88, 'ok' ), 'Registering a draft English article failed' );
must( error_is( NH_VIEN_Links::link( 11, 88, 'ok' ), 'nh_vien_conflict' ), 'An independent English article became the English side of a pair' );
must( true === NH_VIEN_Links::link( 11, 22, 'ok' ), 'Pairing a draft English post failed' );
must( error_is( NH_VIEN_Links::mark_english( 22, 'ok' ), 'nh_vien_conflict' ), 'The English side of a pair was registered as independent' );
$GLOBALS['mock_options'][ NH_VIEN_Links::INDEPENDENT_OPTION ] = array( 88, 22, 11 );
NH_VIEN_Links::flush_cache();
must( array( 88 ) === NH_VIEN_Links::independent_ids(), 'A post of a pair must not count as independent whatever the stored list says: ' . json_encode( NH_VIEN_Links::independent_ids() ) );
must( true === NH_VIEN_Links::unlink( 11, 'ok' ) && true === NH_VIEN_Links::unmark_english( 88, 'ok' ), 'Cleaning up the exclusivity test failed' );

/** A damaged option never turns into post 1 or term 1, and a link to a deleted post can still be removed. */
$GLOBALS['mock_options'][ NH_VIEN_Links::INDEPENDENT_OPTION ] = array( true, 'x', '1abc', 1.9, array( 'a' ), '5', 7, -3, 0, null, ' 8', '9 ', '007', 7 );
NH_VIEN_Links::flush_cache();
must( array( 5, 7 ) === NH_VIEN_Links::independent_ids(), 'Damaged values must be ignored, not coerced: ' . json_encode( NH_VIEN_Links::independent_ids() ) );
$GLOBALS['mock_options'][ NH_VIEN_Links::EN_TERMS_OPTION ] = array( true, 'x', '1abc', 1.9, array( 'a' ), '6', 9, -3, 0 );
NH_VIEN_Links::flush_cache();
must( array( 6, 9 ) === NH_VIEN_Links::declared_en_terms(), 'Damaged term values must be ignored, not coerced: ' . json_encode( NH_VIEN_Links::declared_en_terms() ) );
$GLOBALS['mock_options'][ NH_VIEN_Links::INDEPENDENT_OPTION ] = 'broken';
$GLOBALS['mock_options'][ NH_VIEN_Links::EN_TERMS_OPTION ]    = 'broken';
NH_VIEN_Links::flush_cache();
must( array() === NH_VIEN_Links::independent_ids() && array() === NH_VIEN_Links::declared_en_terms(), 'A stored value that is not a list must read as empty' );
unset( $GLOBALS['mock_options'][ NH_VIEN_Links::INDEPENDENT_OPTION ], $GLOBALS['mock_options'][ NH_VIEN_Links::EN_TERMS_OPTION ] );
NH_VIEN_Links::flush_cache();
// Deleted for good: nobody can edit the post any more, yet the link to it must go.
$GLOBALS['mock_options'][ NH_VIEN_Links::OPTION ] = array( 11 => 22 );
NH_VIEN_Links::flush_cache();
unset( $GLOBALS['mock_posts'][22] );
must( true === NH_VIEN_Links::unlink( 11, 'ok' ), 'A pair whose English post was deleted could not be unlinked' );
$GLOBALS['mock_posts'][22] = new WP_Post( 'post', 'draft' );
$GLOBALS['mock_options'][ NH_VIEN_Links::INDEPENDENT_OPTION ] = array( 88 );
NH_VIEN_Links::flush_cache();
unset( $GLOBALS['mock_posts'][88] );
must( true === NH_VIEN_Links::unmark_english( 88, 'ok' ), 'An English article that was deleted could not be unregistered' );
$GLOBALS['mock_posts'][88] = new WP_Post( 'post', 'draft' );
// A post that still exists needs the right to edit it, as before.
$GLOBALS['mock_options'][ NH_VIEN_Links::OPTION ] = array( 11 => 22 );
NH_VIEN_Links::flush_cache();
$GLOBALS['allow_edit'] = false;
must( error_is( NH_VIEN_Links::unlink( 11, 'ok' ), 'nh_vien_forbidden' ), 'Unlinking existing posts without the right to edit them was allowed' );
$GLOBALS['allow_edit'] = true;
$GLOBALS['mock_options'][ NH_VIEN_Links::OPTION ] = array();
NH_VIEN_Links::flush_cache();

/** English category trees must start empty and must not adopt a Vietnamese parent. */
$GLOBALS['mock_terms'] = array(
	201 => new WP_Term( 201, 0, 0, 'English guides' ),
	202 => new WP_Term( 202, 201, 0, 'Rental guides' ),
	301 => new WP_Term( 301, 0, 4, 'Vietnamese guide' ),
	302 => new WP_Term( 302, 301, 0, 'Child of Vietnamese guide' ),
);
must( error_is( NH_VIEN_Links::mark_english_category( 202, 'ok' ), 'nh_vien_category_conflict' ), 'Accepted English child without English parent' );
must( error_is( NH_VIEN_Links::mark_english_category( 301, 'ok' ), 'nh_vien_category_conflict' ), 'Changed populated Vietnamese category' );
must( error_is( NH_VIEN_Links::mark_english_category( 302, 'ok' ), 'nh_vien_category_conflict' ), 'Changed child of Vietnamese category' );
must( true === NH_VIEN_Links::mark_english_category( 201, 'ok' ), 'Failed to register English parent category' );
must( true === NH_VIEN_Links::mark_english_category( 202, 'ok' ), 'Failed to register English child category' );
must( array( 201, 202 ) === NH_VIEN_Links::declared_en_terms(), 'English parent/child registry mismatch' );
must( isset( NH_VIEN_Links::en_only_term_ids()[201] ), 'English-only parent must be hidden in Vietnamese lists' );
must( isset( NH_VIEN_Links::en_term_ids()[202] ), 'Empty English child must have an EN identity' );
must( error_is( NH_VIEN_Links::unmark_english_category( 201, 'ok' ), 'nh_vien_category_conflict' ), 'Removed parent before English child' );
must( true === NH_VIEN_Links::unmark_english_category( 202, 'ok' ), 'Failed to unmark English child' );
must( true === NH_VIEN_Links::unmark_english_category( 201, 'ok' ), 'Failed to unmark English parent' );
must( array() === NH_VIEN_Links::declared_en_terms(), 'English categories were not cleared' );


/** Page pairs: independently authored English pages must sit under /en/ and preserve both originals. */
$GLOBALS['mock_posts'][501] = new WP_Post( 'page', 'publish' );
$GLOBALS['mock_posts'][502] = new WP_Post( 'page', 'publish' );
$GLOBALS['mock_posts'][503] = new WP_Post( 'page', 'draft' );
$GLOBALS['page_uris'] = array(
	501 => 'gioi-thieu',
	502 => 'en/about',
	503 => 'en/prices',
);
must( array() === NH_VIEN_Links::page_pairs(), 'Page pairs should start empty' );
must( error_is( NH_VIEN_Links::link_page( 501, 502, 'bad' ), 'nh_vien_bad_nonce' ), 'Page-link nonce bypassed' );
must( error_is( NH_VIEN_Links::link_page( 502, 501, 'ok' ), 'nh_vien_invalid_posts' ), 'An English page was accepted as VI' );
must( error_is( NH_VIEN_Links::link_page( 501, 55, 'ok' ), 'nh_vien_invalid_posts' ), 'A post was accepted as EN page' );
must( true === NH_VIEN_Links::link_page( 501, 502, 'ok' ), 'Could not pair independently authored pages' );
must( array( 501 => 502 ) === NH_VIEN_Links::page_pairs(), 'Page link store mismatch' );
must( array( 'vi' => 501, 'en' => 502 ) === NH_VIEN_Links::for_page( 502 ), 'Page mapping did not work from English side' );
must( error_is( NH_VIEN_Links::link_page( 501, 503, 'ok' ), 'nh_vien_conflict' ), 'Duplicate Vietnamese page pair accepted' );
must( true === NH_VIEN_Links::unlink_page( 501, 'ok' ), 'Could not unlink page pair' );
must( null === NH_VIEN_Links::for_page( 502 ), 'Old English page still paired after unlink' );
must( true === NH_VIEN_Links::link_page( 501, 502, 'ok' ), 'Could not pair the pages again' );
unset( $GLOBALS['mock_posts'][502] );
must( true === NH_VIEN_Links::unlink_page( 501, 'ok' ), 'A page pair whose English page was deleted could not be unlinked' );

must( array( 'plugins_loaded' ) === $GLOBALS['hooks'], 'Non-admin bootstrap added unexpected hooks' );
echo "PASS: reciprocal links, one-to-one conflicts, CSRF/capability, corrupt-store and write boundaries.\n";
