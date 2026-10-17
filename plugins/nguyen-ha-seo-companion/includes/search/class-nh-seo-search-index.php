<?php
/**
 * Storage of the internal search index: the table and its rows.
 *
 * One row per public post or page: folded title, folded taxonomy words, folded body tokens. The table is
 * InnoDB with three FULLTEXT indexes (all columns, title, taxonomy). Nothing here reads or writes post
 * content: the plugin only ever writes its own table.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

// This class owns the plugin's own table: every query is prepared, and WordPress caches do not apply to it.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

/**
 * Index table access.
 */
final class NH_SEO_Search_Index {

	/**
	 * Table name without the site prefix.
	 */
	const TABLE_SUFFIX = 'nh_search_index';

	/**
	 * Results of table_exists() for the current request, by table name (every site of a network has its own table).
	 *
	 * @var array<string, bool>
	 */
	private static $exists = array();

	/**
	 * Full table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	/**
	 * Post types that are indexed.
	 *
	 * @return string[]
	 */
	public static function indexable_types() {
		/**
		 * Filters the post types that are indexed and searched through the index. When a public post type is
		 * searchable but not listed here, the index is not used for searches that include it (native search is).
		 *
		 * @param string[] $types Post types. Default post and page.
		 */
		$types = (array) apply_filters( 'nh_seo_search_post_types', array( 'post', 'page' ) );

		$clean = array();
		foreach ( $types as $type ) {
			if ( is_string( $type ) && '' !== $type && strlen( $type ) <= 20 && post_type_exists( $type ) && is_post_type_viewable( $type ) ) {
				$clean[] = $type;
			}
		}
		return array_values( array_unique( $clean ) );
	}

	/**
	 * Taxonomies whose term names feed the taxonomy column.
	 *
	 * @return string[]
	 */
	public static function indexable_taxonomies() {
		/**
		 * Filters the taxonomies whose term names are searchable.
		 *
		 * @param string[] $taxonomies Taxonomies. Default category, post_tag and topic_cluster.
		 */
		$taxonomies = (array) apply_filters( 'nh_seo_search_taxonomies', array( 'category', 'post_tag', NH_SEO_Keys::TAXONOMY_CLUSTER ) );

		$clean = array();
		foreach ( $taxonomies as $taxonomy ) {
			if ( is_string( $taxonomy ) && '' !== $taxonomy ) {
				$clean[] = $taxonomy;
			}
		}
		return array_values( array_unique( $clean ) );
	}

	/**
	 * Whether a post belongs in the index: public, not password protected, an indexed type, not a revision.
	 *
	 * @param WP_Post|int|null $post Post or ID.
	 * @return bool
	 */
	public static function is_indexable( $post ) {
		$post = get_post( $post );
		if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, self::indexable_types(), true ) ) {
			return false;
		}
		return nh_seo_is_public_post( $post );
	}

	/**
	 * Create or upgrade the table. Safe to run repeatedly.
	 *
	 * @return bool Whether the table exists afterwards.
	 */
	public static function create_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		// dbDelta() needs this exact shape: one definition per line and two spaces after PRIMARY KEY.
		$sql = "CREATE TABLE {$table} (
			post_id bigint(20) unsigned NOT NULL,
			post_type varchar(20) NOT NULL,
			title_fold text NOT NULL,
			tax_fold text NOT NULL,
			body_fold longtext NOT NULL,
			updated datetime NOT NULL,
			PRIMARY KEY  (post_id),
			FULLTEXT KEY ft_all (title_fold,tax_fold,body_fold),
			FULLTEXT KEY ft_title (title_fold),
			FULLTEXT KEY ft_tax (tax_fold)
		) ENGINE=InnoDB {$charset};";

		dbDelta( $sql );

		self::$exists = array();
		return self::table_exists();
	}

	/**
	 * Whether the table of the current site exists. Cached for the request; create_table() and drop_table() reset the cache.
	 *
	 * @return bool
	 */
	public static function table_exists() {
		global $wpdb;
		$table = self::table();
		if ( ! isset( self::$exists[ $table ] ) ) {
			$found                  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
			self::$exists[ $table ] = ( $found === $table );
		}
		return self::$exists[ $table ];
	}

	/**
	 * Forget the cached "table exists" answer (tests change the schema behind the cache's back).
	 *
	 * @return void
	 */
	public static function forget_table_state() {
		self::$exists = array();
	}

	/**
	 * Create the table when it is missing.
	 *
	 * @return bool Whether the table exists.
	 */
	public static function ensure_table() {
		return self::table_exists() || self::create_table();
	}

	/**
	 * Drop the table. The plugin itself only does this in tests; uninstall removes it for real.
	 *
	 * @return void
	 */
	public static function drop_table() {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', self::table() ) );
		self::$exists = array();
	}

	/**
	 * Remove every row.
	 *
	 * @return void
	 */
	public static function truncate() {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', self::table() ) );
	}

	/**
	 * Number of indexed posts.
	 *
	 * @return int
	 */
	public static function count() {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', self::table() ) );
	}

	/**
	 * One stored row.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, string>|null
	 */
	public static function get( $post_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE post_id = %d', self::table(), absint( $post_id ) ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * IDs currently stored, ascending (used by tests and consistency checks).
	 *
	 * @return int[]
	 */
	public static function ids() {
		global $wpdb;
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT post_id FROM %i ORDER BY post_id ASC', self::table() ) ) );
	}

	/**
	 * Insert or replace the row of a post.
	 *
	 * @param int                   $post_id  Post ID.
	 * @param array<string, string> $document Fields from NH_SEO_Search_Document::build().
	 * @return bool
	 */
	public static function upsert( $post_id, array $document ) {
		global $wpdb;
		$result = $wpdb->replace(
			self::table(),
			array(
				'post_id'    => absint( $post_id ),
				'post_type'  => (string) $document['post_type'],
				'title_fold' => (string) $document['title_fold'],
				'tax_fold'   => (string) $document['tax_fold'],
				'body_fold'  => (string) $document['body_fold'],
				'updated'    => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);
		return false !== $result;
	}

	/**
	 * Remove the row of a post.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function delete_post( $post_id ) {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'post_id' => absint( $post_id ) ), array( '%d' ) );
	}

	/**
	 * Number of posts that should be indexed.
	 *
	 * @param string[]|null $types Post types, or null for every indexed type.
	 * @return int
	 */
	public static function count_indexable( $types = null ) {
		global $wpdb;
		$types = null === $types ? self::indexable_types() : array_values( $types );
		if ( empty( $types ) ) {
			return 0;
		}
		$placeholders = implode( ', ', array_fill( 0, count( $types ), '%s' ) );
		// The placeholder list is built from a count, never from data.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$sql = $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_password = '' AND post_type IN ($placeholders)", $types );
		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * How far the table is from the content: posts that should have a row and have none, and rows older than the
	 * post they describe (the post was edited afterwards).
	 *
	 * One pass over the posts that should be indexed, joined to the table by primary key. For the status line,
	 * WP-CLI and the repair at activation; never for page views.
	 *
	 * @return array{missing: int, outdated: int}
	 */
	public static function drift() {
		global $wpdb;
		$types = self::indexable_types();
		if ( empty( $types ) ) {
			return array(
				'missing'  => 0,
				'outdated' => 0,
			);
		}
		$placeholders = implode( ', ', array_fill( 0, count( $types ), '%s' ) );
		// The placeholder list is built from a count, never from data.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$sql = $wpdb->prepare( "SELECT COALESCE(SUM(i.post_id IS NULL), 0) AS missing, COALESCE(SUM(i.post_id IS NOT NULL AND p.post_modified_gmt > i.updated), 0) AS outdated FROM {$wpdb->posts} AS p LEFT JOIN %i AS i ON i.post_id = p.ID WHERE p.post_status = 'publish' AND p.post_password = '' AND p.post_type IN ($placeholders)", array_merge( array( self::table() ), $types ) );
		$row = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return array(
			'missing'  => is_array( $row ) ? (int) $row['missing'] : 0,
			'outdated' => is_array( $row ) ? (int) $row['outdated'] : 0,
		);
	}

	/**
	 * IDs of posts whose row is missing or older than the post, ascending.
	 *
	 * @param int $limit Maximum number of IDs.
	 * @return int[]
	 */
	public static function unsynced_ids( $limit ) {
		global $wpdb;
		$types = self::indexable_types();
		if ( empty( $types ) ) {
			return array();
		}
		$placeholders = implode( ', ', array_fill( 0, count( $types ), '%s' ) );
		$args         = array_merge( array( self::table() ), $types, array( max( 1, absint( $limit ) ) ) );
		// The placeholder list is built from a count, never from data.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$sql = $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} AS p LEFT JOIN %i AS i ON i.post_id = p.ID WHERE p.post_status = 'publish' AND p.post_password = '' AND p.post_type IN ($placeholders) AND (i.post_id IS NULL OR p.post_modified_gmt > i.updated) ORDER BY p.ID ASC LIMIT %d", $args );
		return array_map( 'intval', (array) $wpdb->get_col( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * IDs of the next posts that should be indexed after a cursor, ascending.
	 *
	 * @param int           $after_id Cursor: only IDs above this one.
	 * @param int           $limit    Maximum number of IDs.
	 * @param string[]|null $types    Post types, or null for every indexed type.
	 * @return int[]
	 */
	public static function next_indexable_ids( $after_id, $limit, $types = null ) {
		global $wpdb;
		$types = null === $types ? self::indexable_types() : array_values( $types );
		if ( empty( $types ) ) {
			return array();
		}
		$placeholders = implode( ', ', array_fill( 0, count( $types ), '%s' ) );
		$args         = array_merge( array( absint( $after_id ) ), $types, array( max( 1, absint( $limit ) ) ) );
		// The placeholder list is built from a count, never from data.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$sql = $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID > %d AND post_status = 'publish' AND post_password = '' AND post_type IN ($placeholders) ORDER BY ID ASC LIMIT %d", $args );
		return array_map( 'intval', (array) $wpdb->get_col( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Delete rows whose post is gone, no longer public, password protected or of a type that is not indexed.
	 *
	 * One statement; used after partial re-indexing to clear leftovers.
	 *
	 * @return int Rows deleted.
	 */
	public static function prune() {
		global $wpdb;
		$types = self::indexable_types();
		if ( empty( $types ) ) {
			self::truncate();
			return 0;
		}
		$placeholders = implode( ', ', array_fill( 0, count( $types ), '%s' ) );
		// The placeholder list is built from a count, never from data.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$sql = $wpdb->prepare( "DELETE i FROM %i AS i LEFT JOIN {$wpdb->posts} AS p ON p.ID = i.post_id WHERE p.ID IS NULL OR p.post_status <> 'publish' OR p.post_password <> '' OR p.post_type NOT IN ($placeholders)", array_merge( array( self::table() ), $types ) );
		$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->rows_affected;
	}
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
