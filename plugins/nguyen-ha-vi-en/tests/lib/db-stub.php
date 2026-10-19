<?php
/**
 * Test tooling shared by the offline scripts (required by them, never run on its own, never loaded by WordPress, and left
 * out of the installable zip with the rest of tests/): the database and object-cache calls behind
 * NH_VIEN_Links::public_en_posts(), answered from $GLOBALS['published'] (a list of WP_Post-like objects with ID, post_name,
 * post_status and post_type).
 *
 * The cache is off unless a test turns it on with $GLOBALS['cache_on'], so that a test which changes the posts and starts a
 * fresh request always sees the change. $GLOBALS['rows_queries'] counts the queries that reached the database.
 */

// Test tooling: runs from the command line only, never through a web server.
if ( 'cli' !== PHP_SAPI ) {
	exit;
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

$GLOBALS['rows_queries'] = 0;
$GLOBALS['last_sql']     = '';
$GLOBALS['cache_on']     = false;
$GLOBALS['cache']        = array();
$GLOBALS['last_changed'] = '1';

/** The few members of wpdb that the plugin uses for its one direct query. */
class NH_VIEN_Test_Wpdb {
	public $posts = 'wp_posts';
	/**
	 * The statement is not built: the IDs are what the answer depends on.
	 *
	 * @param string $sql  Statement with %d placeholders.
	 * @param array  $args Values for the placeholders.
	 * @return array
	 */
	public function prepare( $sql, $args ) {
		// One %d for every ID, no other placeholder, and the list is the only thing that varies.
		if ( ! is_array( $args ) || substr_count( $sql, '%d' ) !== count( $args ) || substr_count( $sql, '%' ) !== count( $args ) ) {
			throw new RuntimeException( 'The statement and its values do not match: ' . $sql );
		}
		$GLOBALS['last_sql'] = $sql;
		return array(
			'sql' => $sql,
			'ids' => $args,
		);
	}
	/**
	 * Published posts (not pages) among the IDs, with the two columns the plugin selects.
	 *
	 * @param array $query What prepare() returned.
	 * @return array
	 */
	public function get_results( $query ) {
		++$GLOBALS['rows_queries'];
		$rows = array();
		foreach ( $GLOBALS['published'] as $post ) {
			if ( in_array( $post->ID, $query['ids'], true ) && 'post' === $post->post_type && 'publish' === $post->post_status ) {
				$rows[] = (object) array(
					'ID'        => (string) $post->ID,
					'post_name' => $post->post_name,
				);
			}
		}
		return $rows;
	}
}

$GLOBALS['wpdb'] = new NH_VIEN_Test_Wpdb();

function wp_cache_get_last_changed( $group ) { return $GLOBALS['last_changed']; }
function wp_cache_get( $key, $group = '' ) { return $GLOBALS['cache_on'] && isset( $GLOBALS['cache'][ $group . $key ] ) ? $GLOBALS['cache'][ $group . $key ] : false; }
function wp_cache_set( $key, $data, $group = '', $expire = 0 ) {
	if ( $GLOBALS['cache_on'] ) {
		$GLOBALS['cache'][ $group . $key ] = $data;
	}
	return true;
}
