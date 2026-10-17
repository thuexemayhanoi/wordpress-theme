<?php
/**
 * WP-CLI commands of the companion plugin: `wp nh-seo reindex`, `wp nh-seo index-status`, `wp nh-seo purge`.
 *
 * The class never touches WP_CLI itself: every message goes through an NH_SEO_CLI_Output object (the WP-CLI
 * adapter by default), so the commands can be called and tested without WP-CLI being loaded. WP-CLI turns the
 * public methods into sub-commands (index_status becomes index-status) and reads the option documentation
 * from the doc comments.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manage the internal search index and the page-cache purge from the command line.
 */
class NH_SEO_CLI_Command {

	/**
	 * Output channel.
	 *
	 * @var NH_SEO_CLI_Output
	 */
	private $out;

	/**
	 * Create the command object. WP-CLI calls this without arguments; tests pass their own output channel.
	 *
	 * @param NH_SEO_CLI_Output|null $output Output channel, or null for WP-CLI's.
	 */
	public function __construct( $output = null ) {
		$this->out = $output instanceof NH_SEO_CLI_Output ? $output : new NH_SEO_CLI_Output_WPCLI();
	}

	/**
	 * Rebuilds the internal search index in batches.
	 *
	 * Without --post-type the whole index is emptied and rebuilt, and it is marked ready when every post has been
	 * walked. With --post-type only those post types are re-indexed (the index is not emptied, leftovers are
	 * pruned, readiness is not changed). Only public posts and pages are ever indexed; content is never changed.
	 *
	 * ## OPTIONS
	 *
	 * [--batch=<n>]
	 * : Posts per batch, 10 to 500. Without it 200 are used.
	 *
	 * [--post-type=<types>]
	 * : Comma separated post types to re-index. Without it every indexed post type is rebuilt (post,page).
	 *
	 * ## EXAMPLES
	 *
	 *     # Rebuild everything.
	 *     wp nh-seo reindex
	 *
	 *     # Smaller batches on a slow server.
	 *     wp nh-seo reindex --batch=50
	 *
	 *     # Only refresh the pages.
	 *     wp nh-seo reindex --post-type=page
	 *
	 * @when after_wp_load
	 *
	 * @param string[]             $args       Positional arguments (none).
	 * @param array<string, mixed> $assoc_args Options.
	 * @return void
	 */
	public function reindex( $args, $assoc_args ) {
		$assoc_args           = (array) $assoc_args;
		$size                 = $this->parse_batch( $assoc_args );
		list( $types, $full ) = $this->parse_types( $assoc_args );

		if ( 'native' === NH_SEO_Search_State::mode() ) {
			$this->out->warning( __( 'The search mode is "native": the index is built, but searches do not use it.', 'nguyen-ha-seo-companion' ) );
		}

		$this->out->progress_start( __( 'Indexing posts', 'nguyen-ha-seo-companion' ), NH_SEO_Search_Index::count_indexable( $types ) );
		$progress = function ( $batch ) {
			$this->out->progress_tick( $batch['processed'] );
		};
		$result   = $full ? NH_SEO_Search_Builder::rebuild( $size, $progress ) : NH_SEO_Search_Builder::reindex_types( $types, $size, $progress );
		$this->out->progress_finish();

		if ( is_wp_error( $result ) ) {
			$this->out->error( $result->get_error_message() );
			return;
		}
		if ( empty( $result['done'] ) ) {
			$this->out->warning( __( 'The walk stopped before the last post. Run the command again.', 'nguyen-ha-seo-companion' ) );
			return;
		}

		if ( $full ) {
			$this->out->success(
				sprintf(
					/* translators: %s: number of posts in the index. */
					__( 'Indexed %s posts. The index is ready.', 'nguyen-ha-seo-companion' ),
					number_format_i18n( (int) $result['indexed'] )
				)
			);
			return;
		}
		$this->out->success(
			sprintf(
				/* translators: 1: post types, 2: number of posts in the index. */
				__( 'Re-indexed the post types %1$s. The index now holds %2$s posts; whether it is ready is unchanged.', 'nguyen-ha-seo-companion' ),
				implode( ', ', $types ),
				number_format_i18n( (int) $result['indexed'] )
			)
		);
	}

	/**
	 * Shows the state of the internal search index.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format: table, json, csv or yaml. Without it a table is printed.
	 *
	 * ## EXAMPLES
	 *
	 *     wp nh-seo index-status
	 *     wp nh-seo index-status --format=json
	 *
	 * @when after_wp_load
	 * @subcommand index-status
	 *
	 * @param string[]             $args       Positional arguments (none).
	 * @param array<string, mixed> $assoc_args Options.
	 * @return void
	 */
	public function index_status( $args, $assoc_args ) {
		$assoc_args = (array) $assoc_args;
		$format     = isset( $assoc_args['format'] ) ? sanitize_key( (string) $assoc_args['format'] ) : 'table';
		if ( ! in_array( $format, array( 'table', 'json', 'csv', 'yaml' ), true ) ) {
			$this->out->error( __( '--format must be table, json, csv or yaml.', 'nguyen-ha-seo-companion' ) );
			return;
		}
		$this->out->table( $format, self::status_rows(), array( 'key', 'value' ) );
	}

	/**
	 * Purges the page caches for one post.
	 *
	 * Hands the URLs that a change of the post makes stale (the post, home, the posts page, its category and tag
	 * archives, the author archive, its Pillar page and its neighbours) to the page caches that are installed.
	 * An explicit purge ignores the automatic-purge setting.
	 *
	 * ## OPTIONS
	 *
	 * --post=<id>
	 * : ID of the post or page.
	 *
	 * [--dry-run]
	 * : List the URLs without purging anything.
	 *
	 * ## EXAMPLES
	 *
	 *     wp nh-seo purge --post=123
	 *     wp nh-seo purge --post=123 --dry-run
	 *
	 * @when after_wp_load
	 *
	 * @param string[]             $args       Positional arguments (none).
	 * @param array<string, mixed> $assoc_args Options.
	 * @return void
	 */
	public function purge( $args, $assoc_args ) {
		$assoc_args = (array) $assoc_args;
		$raw        = self::option_text( $assoc_args, 'post' );
		if ( '' === $raw ) {
			$this->out->error( __( 'Specify the post with --post=<id>.', 'nguyen-ha-seo-companion' ) );
			return;
		}
		if ( 1 !== preg_match( '/^[1-9][0-9]{0,18}$/', $raw ) ) {
			$this->out->error( __( '--post must be a post ID (a positive whole number).', 'nguyen-ha-seo-companion' ) );
			return;
		}

		$post = get_post( (int) $raw );
		if ( ! $post instanceof WP_Post ) {
			$this->out->error(
				sprintf(
					/* translators: %d: post ID. */
					__( 'Post %d does not exist.', 'nguyen-ha-seo-companion' ),
					(int) $raw
				)
			);
			return;
		}

		$urls = NH_SEO_Cache::urls_for_post( $post->ID );
		if ( empty( $urls ) ) {
			$this->out->warning( __( 'Nothing to purge for this post.', 'nguyen-ha-seo-companion' ) );
			return;
		}
		if ( 'publish' !== $post->post_status ) {
			$this->out->warning(
				sprintf(
					/* translators: 1: post ID, 2: post status. */
					__( 'Post %1$d is "%2$s", not published: its own address is not part of the purge.', 'nguyen-ha-seo-companion' ),
					$post->ID,
					$post->post_status
				)
			);
		}
		foreach ( $urls as $url ) {
			$this->out->log( '  ' . $url );
		}

		if ( ! empty( $assoc_args['dry-run'] ) ) {
			$this->out->success(
				sprintf(
					/* translators: %s: number of URLs. */
					__( 'Dry run: %s URLs would be purged.', 'nguyen-ha-seo-companion' ),
					number_format_i18n( count( $urls ) )
				)
			);
			return;
		}

		if ( ! NH_SEO_Cache::enabled() ) {
			$this->out->warning( __( 'Automatic purging is switched off in the settings; this explicit purge goes ahead.', 'nguyen-ha-seo-companion' ) );
		}
		NH_SEO_Cache::dispatch( $urls );
		$this->out->success(
			sprintf(
				/* translators: %s: number of URLs. */
				__( 'Handed %s URLs to the page caches.', 'nguyen-ha-seo-companion' ),
				number_format_i18n( count( $urls ) )
			)
		);
	}

	/**
	 * The rows of `index-status`: one fact per row.
	 *
	 * @return array<int, array{key: string, value: string}>
	 */
	public static function status_rows() {
		$state   = NH_SEO_Search_State::get();
		$breaker = NH_SEO_Search_State::breaker();
		$table   = NH_SEO_Search_Index::table_exists();
		$drift   = $state['ready'] ? NH_SEO_Search_State::drift() : array(
			'missing'  => 0,
			'outdated' => 0,
			'queued'   => 0,
			'overdue'  => 0,
		);
		$yes_no  = static function ( $value ) {
			return $value ? 'yes' : 'no';
		};
		$time    = static function ( $timestamp ) {
			return $timestamp ? gmdate( 'Y-m-d H:i:s', (int) $timestamp ) . ' UTC' : '';
		};

		return array(
			array(
				'key'   => 'status',
				'value' => NH_SEO_Search_State::status_line(),
			),
			array(
				'key'   => 'mode',
				'value' => NH_SEO_Search_State::mode(),
			),
			array(
				'key'   => 'used_for_searches',
				'value' => $yes_no( NH_SEO_Search_State::is_active() ),
			),
			array(
				'key'   => 'ready',
				'value' => $yes_no( $state['ready'] ),
			),
			array(
				'key'   => 'building',
				'value' => $yes_no( $state['building'] ),
			),
			array(
				'key'   => 'table',
				'value' => NH_SEO_Search_Index::table() . ( $table ? '' : ' (missing)' ),
			),
			array(
				'key'   => 'posts_in_index',
				'value' => (string) ( $table ? NH_SEO_Search_Index::count() : 0 ),
			),
			array(
				'key'   => 'posts_at_last_build',
				'value' => (string) $state['total'],
			),
			array(
				'key'   => 'posts_missing',
				'value' => (string) $drift['missing'],
			),
			array(
				'key'   => 'posts_outdated',
				'value' => (string) $drift['outdated'],
			),
			array(
				'key'   => 'wp_cron_updates_queued',
				'value' => (string) $drift['queued'],
			),
			array(
				'key'   => 'wp_cron_updates_overdue',
				'value' => (string) $drift['overdue'],
			),
			array(
				'key'   => 'last_run',
				'value' => $time( $state['last_run'] ),
			),
			array(
				'key'   => 'paused_until',
				'value' => $breaker ? $time( $breaker['until'] ) : '',
			),
			array(
				'key'   => 'last_error',
				'value' => $state['last_error'],
			),
			array(
				'key'   => 'rebuild_recommended',
				'value' => $yes_no( $state['stale'] || $state['gap'] || $drift['missing'] + $drift['outdated'] > 0 || $drift['overdue'] > 0 ),
			),
		);
	}

	/**
	 * One option as trimmed text. A flag given without a value (WP-CLI passes true), an array or a missing option
	 * is the empty string, so "--post" alone can never be read as post 1.
	 *
	 * @param array<string, mixed> $assoc_args Options.
	 * @param string               $name       Option name.
	 * @return string
	 */
	private static function option_text( array $assoc_args, $name ) {
		if ( ! isset( $assoc_args[ $name ] ) || ! ( is_string( $assoc_args[ $name ] ) || is_int( $assoc_args[ $name ] ) ) ) {
			return '';
		}
		return trim( (string) $assoc_args[ $name ] );
	}

	/**
	 * Read --batch.
	 *
	 * @param array<string, mixed> $assoc_args Options.
	 * @return int Batch size.
	 */
	private function parse_batch( array $assoc_args ) {
		if ( ! isset( $assoc_args['batch'] ) ) {
			return NH_SEO_Search_Builder::batch_size();
		}
		$raw = self::option_text( $assoc_args, 'batch' );
		if ( 1 !== preg_match( '/^[1-9][0-9]{0,6}$/', $raw ) ) {
			$this->out->error( __( '--batch must be a positive whole number (10 to 500).', 'nguyen-ha-seo-companion' ) );
			return NH_SEO_Search_Builder::batch_size();
		}

		$requested = (int) $raw;
		$size      = NH_SEO_Search_Builder::batch_size( $requested );
		if ( $size !== $requested ) {
			$this->out->warning(
				sprintf(
					/* translators: %d: batch size actually used. */
					__( 'Batch size adjusted to %d (allowed: 10 to 500).', 'nguyen-ha-seo-companion' ),
					$size
				)
			);
		}
		return $size;
	}

	/**
	 * Read --post-type.
	 *
	 * @param array<string, mixed> $assoc_args Options.
	 * @return array{0: string[], 1: bool} Post types and whether that is every indexed type (a full rebuild).
	 */
	private function parse_types( array $assoc_args ) {
		$indexed = NH_SEO_Search_Index::indexable_types();
		if ( empty( $indexed ) ) {
			$this->out->error( __( 'No post type is indexed.', 'nguyen-ha-seo-companion' ) );
			return array( array(), true );
		}
		if ( ! isset( $assoc_args['post-type'] ) ) {
			return array( $indexed, true );
		}

		$wanted = explode( ',', self::option_text( $assoc_args, 'post-type' ) );
		$wanted = array_values( array_unique( array_filter( array_map( 'sanitize_key', $wanted ) ) ) );
		if ( empty( $wanted ) ) {
			$this->out->error( __( '--post-type needs at least one post type.', 'nguyen-ha-seo-companion' ) );
			return array( $indexed, true );
		}
		foreach ( $wanted as $type ) {
			if ( ! in_array( $type, $indexed, true ) ) {
				$this->out->error(
					sprintf(
						/* translators: 1: post type, 2: list of indexed post types. */
						__( 'The post type "%1$s" is not indexed. Indexed post types: %2$s.', 'nguyen-ha-seo-companion' ),
						$type,
						implode( ', ', $indexed )
					)
				);
				return array( $indexed, true );
			}
		}
		return array( $wanted, array() === array_diff( $indexed, $wanted ) );
	}
}
