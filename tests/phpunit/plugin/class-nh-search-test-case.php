<?php
/**
 * Base class for the search index tests.
 *
 * WHY THESE TESTS DO NOT RUN INSIDE A TRANSACTION
 *
 * The WordPress test suite wraps every test in a transaction and rolls it back. InnoDB FULLTEXT indexes only
 * see COMMITTED rows (a MATCH ... AGAINST inside the transaction that wrote the rows finds nothing), and
 * FULLTEXT indexes cannot exist on temporary tables, which is what the suite turns CREATE TABLE into. So this
 * class does the following instead:
 *
 *  1. start_transaction() (called by the suite's set_up()) is overridden: it commits and switches the
 *     session to autocommit. No temporary-table filters are installed, so dbDelta() creates the real table.
 *  2. Everything a test writes is therefore committed. tear_down() removes it again: all posts, comments,
 *     terms and users (the suite's own _delete_all_data()), every row of the index table, and every option
 *     the test added or changed (a snapshot of wp_options is taken in set_up()).
 *  3. The suite's tear_down_after_class() calls _delete_all_data() as well, so nothing survives a class.
 *
 * Other test classes keep their transactions: the first thing their set_up() does is "SET autocommit = 0".
 *
 * The tests run against the MariaDB of this sandbox (InnoDB FULLTEXT). The SQL is plain MySQL 5.6+ syntax
 * (no MariaDB-only features) but it was NOT executed on MySQL 5.7 or 8 here.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * Autocommit test case with index helpers.
 */
abstract class NH_Search_Test_Case extends NH_Unit_Test_Case {

	/**
	 * Rows of wp_options at the start of the test, keyed by option name.
	 *
	 * @var array<string, array<string, string>>
	 */
	private $options_snapshot = array();

	/**
	 * SQL recorded by record_sql().
	 *
	 * @var string[]
	 */
	protected $sql_log = array();

	public static function set_up_before_class() {
		parent::set_up_before_class();
		NH_SEO_Search_Index::forget_table_state();
		NH_SEO_Search_Index::ensure_table();
	}

	/**
	 * Replace the suite's transaction by autocommit (see the class docblock).
	 *
	 * @return void
	 */
	public function start_transaction() {
		global $wpdb;
		$wpdb->query( 'COMMIT' );
		$wpdb->query( 'SET autocommit = 1' );
	}

	public function set_up() {
		parent::set_up();

		$this->snapshot_options();
		NH_SEO_Search_Indexer::reset();
		NH_SEO_Search_Index::forget_table_state();
		NH_SEO_Search_Index::ensure_table();
		NH_SEO_Search_Index::truncate();
		delete_option( NH_SEO_Keys::OPTION_SETTINGS );
		remove_theme_support( 'nguyen-ha-companion' );
		$this->sql_log = array();
	}

	public function tear_down() {
		global $wpdb;

		$this->stop_sql_log();
		NH_SEO_Search_Indexer::reset();
		$wpdb->suppress_errors( false );

		// Posts, meta, terms, users and comments the test committed.
		_delete_all_data();
		NH_SEO_Search_Index::forget_table_state();
		if ( NH_SEO_Search_Index::ensure_table() ) {
			NH_SEO_Search_Index::truncate();
		}
		$this->restore_options();
		$GLOBALS['wp_rest_server'] = null;

		parent::tear_down();
	}

	/**
	 * Remember wp_options so that changes can be undone (autocommit cannot roll back).
	 *
	 * @return void
	 */
	private function snapshot_options() {
		global $wpdb;
		$this->options_snapshot = array();
		$rows                   = $wpdb->get_results( "SELECT option_name, option_value, autoload FROM {$wpdb->options}", ARRAY_A );
		foreach ( (array) $rows as $row ) {
			$this->options_snapshot[ $row['option_name'] ] = $row;
		}
	}

	/**
	 * Restore wp_options: delete what the test added, put back what it changed or removed.
	 *
	 * @return void
	 */
	private function restore_options() {
		global $wpdb;
		$current = array();
		$rows    = $wpdb->get_results( "SELECT option_name, option_value, autoload FROM {$wpdb->options}", ARRAY_A );
		foreach ( (array) $rows as $row ) {
			$current[ $row['option_name'] ] = $row;
		}

		foreach ( $current as $name => $row ) {
			if ( ! isset( $this->options_snapshot[ $name ] ) ) {
				$wpdb->delete( $wpdb->options, array( 'option_name' => $name ) );
			} elseif ( $this->options_snapshot[ $name ] !== $row ) {
				$wpdb->update(
					$wpdb->options,
					array(
						'option_value' => $this->options_snapshot[ $name ]['option_value'],
						'autoload'     => $this->options_snapshot[ $name ]['autoload'],
					),
					array( 'option_name' => $name )
				);
			}
		}
		foreach ( $this->options_snapshot as $name => $row ) {
			if ( ! isset( $current[ $name ] ) ) {
				$wpdb->insert( $wpdb->options, $row );
			}
		}
		wp_cache_flush();
	}

	/**
	 * Start recording every SQL statement.
	 *
	 * @return void
	 */
	protected function record_sql() {
		$this->sql_log = array();
		add_filter( 'query', array( $this, 'log_sql' ), 99999 );
	}

	/**
	 * Stop recording and return the statements.
	 *
	 * @return string[]
	 */
	protected function stop_sql_log() {
		remove_filter( 'query', array( $this, 'log_sql' ), 99999 );
		return $this->sql_log;
	}

	/**
	 * Filter callback: record a statement.
	 *
	 * @param string $query SQL.
	 * @return string
	 */
	public function log_sql( $query ) {
		$this->sql_log[] = $query;
		return $query;
	}

	/**
	 * Recorded statements that contain a fragment.
	 *
	 * @param string $needle Fragment.
	 * @return string[]
	 */
	protected function sql_containing( $needle ) {
		return array_values(
			array_filter(
				$this->sql_log,
				static function ( $sql ) use ( $needle ) {
					return false !== strpos( $sql, $needle );
				}
			)
		);
	}

	/**
	 * Create a published post with predictable content (the factory defaults are random strings).
	 *
	 * @param array $args wp_insert_post arguments.
	 * @return int
	 */
	protected function make_post( array $args = array() ) {
		static $counter = 0;
		++$counter;
		return self::factory()->post->create(
			array_merge(
				array(
					'post_title'   => 'Plain title ' . $counter,
					'post_content' => 'Plain content',
					'post_excerpt' => '',
					'post_status'  => 'publish',
					'post_date'    => gmdate( 'Y-m-d H:i:s', strtotime( '2026-01-01 00:00:00' ) + $counter * 60 ),
				),
				$args
			)
		);
	}

	/**
	 * Insert many published posts with direct SQL (fast), and return their IDs.
	 *
	 * @param int      $count    Number of posts.
	 * @param callable $builder  Receives the index (0-based), returns array( 'title' => , 'content' => ).
	 * @param string   $type     Post type.
	 * @return int[]
	 */
	protected function bulk_posts( $count, callable $builder, $type = 'post' ) {
		global $wpdb;
		$author = 1;
		$ids    = array();
		$chunks = array_chunk( range( 0, $count - 1 ), 250 );
		foreach ( $chunks as $chunk ) {
			$values = array();
			foreach ( $chunk as $i ) {
				$data     = $builder( $i );
				$date     = gmdate( 'Y-m-d H:i:s', strtotime( '2025-01-01 00:00:00' ) + $i * 600 );
				$values[] = $wpdb->prepare(
					'(%d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)',
					$author,
					$date,
					get_gmt_from_date( $date ),
					$data['content'],
					$data['title'],
					'',
					'publish',
					'open',
					'open',
					'bulk-' . $type . '-' . $i . '-' . wp_generate_password( 6, false ),
					$date,
					get_gmt_from_date( $date ),
					'',
					$type
				);
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Values are prepared one by one above.
			$wpdb->query( "INSERT INTO {$wpdb->posts} (post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt, post_status, comment_status, ping_status, post_name, post_modified, post_modified_gmt, post_content_filtered, post_type) VALUES " . implode( ', ', $values ) );
			$first = (int) $wpdb->insert_id;
			$ids   = array_merge( $ids, range( $first, $first + count( $chunk ) - 1 ) );
		}
		return $ids;
	}

	/**
	 * Build the complete index and return the final batch result.
	 *
	 * @param int $size Batch size.
	 * @return array<string, mixed>
	 */
	protected function index_all( $size = 200 ) {
		$result = NH_SEO_Search_Builder::rebuild( $size );
		$this->assertNotWPError( $result );
		return $result;
	}

	/**
	 * Write the queued changes now (WordPress does it at shutdown).
	 *
	 * @return void
	 */
	protected function flush_index() {
		NH_SEO_Search_Indexer::flush();
	}

	/**
	 * Run a search WP_Query and return the IDs in result order.
	 *
	 * @param string $search Search string.
	 * @param array  $args   Extra query arguments.
	 * @return int[]
	 */
	protected function search_ids( $search, array $args = array() ) {
		$query = new WP_Query( array_merge( array( 's' => $search ), $args ) );
		return array_map( 'intval', wp_list_pluck( $query->posts, 'ID' ) );
	}

	/**
	 * Set the search mode.
	 *
	 * @param string $mode auto | native | index.
	 * @return void
	 */
	protected function set_search_mode( $mode ) {
		update_option( NH_SEO_Keys::OPTION_SETTINGS, array( 'search_mode' => $mode ) );
	}
}
