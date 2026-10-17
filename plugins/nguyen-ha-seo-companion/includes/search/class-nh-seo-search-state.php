<?php
/**
 * State of the internal search index and the rules that decide whether it is used.
 *
 * Option `nh_seo_search_state` (not autoloaded):
 *  ready       true only after a COMPLETE build (a partial index is never used in "auto" mode).
 *  total       posts the last build had to index.
 *  indexed     rows written by the last build step.
 *  last_run    Unix timestamp of the last build step (0 = never).
 *  last_error  last problem worth showing to an administrator ('' = none).
 *  building    a build started from the Tools panel or WP-CLI has not finished.
 *  build       token of that build; a second build supersedes the first.
 *  cursor      highest post ID the build has processed.
 *  stale       a taxonomy change touched more posts than could be refreshed automatically.
 *  gap         the plugin was activated while the index was out of step with the content by more posts than it
 *              repairs in that request; in "auto" mode the index is not used until it is rebuilt.
 *
 * A database error pauses the index for ten minutes (transient `nh_seo_search_off`) and searches fall back
 * to native WordPress search.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * Index state.
 */
final class NH_SEO_Search_State {

	/**
	 * Transient that pauses the index after a database error.
	 */
	const BREAKER = 'nh_seo_search_off';

	/**
	 * Seconds the index stays paused after an error.
	 */
	const BREAKER_SECONDS = 600;

	/**
	 * Transient that remembers the last comparison of the index with the content, for the admin screens.
	 */
	const DRIFT_CACHE = 'nh_seo_search_drift';

	/**
	 * Seconds an admin screen may reuse that comparison.
	 */
	const DRIFT_CACHE_SECONDS = 300;

	/**
	 * Default state.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'ready'      => false,
			'total'      => 0,
			'indexed'    => 0,
			'last_run'   => 0,
			'last_error' => '',
			'building'   => false,
			'build'      => '',
			'cursor'     => 0,
			'stale'      => false,
			'gap'        => false,
		);
	}

	/**
	 * Current state with every key present and typed.
	 *
	 * @return array<string, mixed>
	 */
	public static function get() {
		$stored = get_option( NH_SEO_Keys::OPTION_SEARCH_STATE, array() );
		$state  = array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );

		return array(
			'ready'      => ! empty( $state['ready'] ),
			'total'      => absint( $state['total'] ),
			'indexed'    => absint( $state['indexed'] ),
			'last_run'   => absint( $state['last_run'] ),
			'last_error' => is_scalar( $state['last_error'] ) ? (string) $state['last_error'] : '',
			'building'   => ! empty( $state['building'] ),
			'build'      => is_scalar( $state['build'] ) ? (string) $state['build'] : '',
			'cursor'     => absint( $state['cursor'] ),
			'stale'      => ! empty( $state['stale'] ),
			'gap'        => ! empty( $state['gap'] ),
		);
	}

	/**
	 * Merge changes into the stored state.
	 *
	 * @param array<string, mixed> $changes Keys to change.
	 * @return array<string, mixed> The new state.
	 */
	public static function save( array $changes ) {
		$state = array_merge( self::get(), array_intersect_key( $changes, self::defaults() ) );
		update_option( NH_SEO_Keys::OPTION_SEARCH_STATE, $state, false );
		return self::get();
	}

	/**
	 * Forget everything (used by "Clear index").
	 *
	 * @return array<string, mixed>
	 */
	public static function reset() {
		update_option( NH_SEO_Keys::OPTION_SEARCH_STATE, self::defaults(), false );
		self::forget_drift();
		return self::get();
	}

	/**
	 * Search mode from the settings: auto | native | index.
	 *
	 * @return string
	 */
	public static function mode() {
		$settings = nh_seo_get_settings();
		return (string) $settings['search_mode'];
	}

	/**
	 * Whether searches should use the index right now.
	 *
	 * Mode "native" never uses it. Mode "auto" uses it only after a complete build, and not while it is known to
	 * miss posts (the gap flag). Mode "index" uses it whenever the table exists, even while it is still being
	 * built. In every mode a database error pauses the index for ten minutes.
	 *
	 * The theme asks this on page views too (see NH_SEO_Search_Query::filter_index_active()), so the checks
	 * run from the cheapest to the most expensive: a setting, then the state option, and only for an index
	 * that would otherwise be used the pause transient (which costs two option reads without an object cache).
	 *
	 * @return bool
	 */
	public static function is_active() {
		$mode = self::mode();
		if ( 'native' === $mode ) {
			return false;
		}
		if ( 'index' === $mode ) {
			$usable = NH_SEO_Search_Index::table_exists();
		} else {
			$state  = self::get();
			$usable = $state['ready'] && ! $state['gap'];
		}
		return $usable && ! self::breaker();
	}

	/**
	 * How far the index is from the content, and what WP-Cron still has to do for it.
	 *
	 * Comparing the two tables reads every public post: about 2 seconds on a site of 50,000 articles. Page views never
	 * do it. An admin screen passes $allow_cache to reuse a comparison made in the last five minutes (a settings page
	 * shows the status twice); everything else, WP-CLI included, compares afresh. Every comparison refreshes the
	 * remembered one, and a rebuild, a clear or the repair at activation forget it.
	 *
	 * @param bool $allow_cache Reuse a recent comparison of the two tables.
	 * @return array{missing: int, outdated: int, queued: int, overdue: int}
	 */
	public static function drift( $allow_cache = false ) {
		$drift = array(
			'missing'  => 0,
			'outdated' => 0,
			'queued'   => 0,
			'overdue'  => 0,
		);
		if ( ! NH_SEO_Search_Index::table_exists() ) {
			return $drift;
		}

		$tables = $allow_cache ? get_transient( self::DRIFT_CACHE ) : false;
		if ( ! is_array( $tables ) || ! isset( $tables['missing'], $tables['outdated'] ) ) {
			$tables = NH_SEO_Search_Index::drift();
			set_transient( self::DRIFT_CACHE, $tables, self::DRIFT_CACHE_SECONDS );
		}

		// The WP-Cron queue is one option read: always current.
		return array_merge( $drift, array_map( 'absint', array_intersect_key( $tables, $drift ) ), NH_SEO_Search_Indexer::queued_updates() );
	}

	/**
	 * Forget the remembered comparison (the index or the content changed in a way that makes it wrong).
	 *
	 * @return void
	 */
	public static function forget_drift() {
		delete_transient( self::DRIFT_CACHE );
	}

	/**
	 * The active pause, if any.
	 *
	 * @return array{until: int, error: string}|null
	 */
	public static function breaker() {
		$value = get_transient( self::BREAKER );
		if ( ! is_array( $value ) ) {
			return false === $value ? null : array(
				'until' => time() + self::BREAKER_SECONDS,
				'error' => '',
			);
		}
		return array(
			'until' => isset( $value['until'] ) ? absint( $value['until'] ) : 0,
			'error' => isset( $value['error'] ) ? (string) $value['error'] : '',
		);
	}

	/**
	 * Pause the index for ten minutes after a database error and remember why.
	 *
	 * @param string $error Database error message.
	 * @return void
	 */
	public static function trip( $error ) {
		$error = nh_seo_truncate( sanitize_text_field( (string) $error ), 300 );
		set_transient(
			self::BREAKER,
			array(
				'until' => time() + self::BREAKER_SECONDS,
				'error' => $error,
			),
			self::BREAKER_SECONDS
		);
		self::save( array( 'last_error' => $error ) );
	}

	/**
	 * Lift the pause.
	 *
	 * @return void
	 */
	public static function clear_breaker() {
		delete_transient( self::BREAKER );
	}

	/**
	 * Invalidate cached WP_Query results: the index changed without a post being saved.
	 *
	 * @return void
	 */
	public static function bump_query_caches() {
		if ( function_exists( 'wp_cache_set_posts_last_changed' ) ) {
			wp_cache_set_posts_last_changed();
		}
	}

	/**
	 * One-line, plain text status for the plugin's Status box and WP-CLI.
	 *
	 * @param bool $allow_cache Let the admin screens reuse a recent comparison of the index with the content (see drift()).
	 * @return string
	 */
	public static function status_line( $allow_cache = false ) {
		$state = self::get();
		$mode  = self::mode();

		if ( 'native' === $mode ) {
			return __( 'Native WordPress search (the index is switched off in the settings).', 'nguyen-ha-seo-companion' );
		}

		$breaker = self::breaker();
		if ( $breaker ) {
			return sprintf(
				/* translators: 1: time when the index is tried again, 2: database error message. */
				__( 'Index paused until %1$s after a database error; native search is used meanwhile. %2$s', 'nguyen-ha-seo-companion' ),
				wp_date( get_option( 'time_format' ), $breaker['until'] ),
				$breaker['error']
			);
		}

		if ( ! NH_SEO_Search_Index::table_exists() ) {
			return __( 'The index table does not exist yet. Build the index in the Tools tab.', 'nguyen-ha-seo-companion' );
		}

		$line = '';
		if ( $state['building'] ) {
			$percent = $state['total'] > 0 ? min( 100, (int) floor( 100 * $state['indexed'] / $state['total'] ) ) : 0;
			$line    = sprintf(
				/* translators: 1: posts indexed so far, 2: posts to index, 3: percent done. */
				__( 'Building: %1$s of %2$s posts indexed (%3$d%%). Native search is used until the build completes.', 'nguyen-ha-seo-companion' ),
				number_format_i18n( $state['indexed'] ),
				number_format_i18n( $state['total'] ),
				$percent
			);
		} elseif ( $state['ready'] && $state['gap'] ) {
			$line = 'index' === $mode
				? __( 'Index mode: searches use the index, but it no longer matches the content (it was out of step when the plugin was activated). Rebuild it in the Tools tab.', 'nguyen-ha-seo-companion' )
				: __( 'The index no longer matches the content (it was out of step when the plugin was activated), so native search is used until it is rebuilt (Tools tab).', 'nguyen-ha-seo-companion' );
		} elseif ( $state['ready'] ) {
			$line = sprintf(
				/* translators: 1: number of posts indexed, 2: date and time of the last build. */
				__( 'Ready: %1$s posts indexed (last build %2$s). Searches use the index.', 'nguyen-ha-seo-companion' ),
				number_format_i18n( NH_SEO_Search_Index::count() ),
				$state['last_run'] ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $state['last_run'] ) : __( 'unknown', 'nguyen-ha-seo-companion' )
			);
		} elseif ( 'index' === $mode ) {
			$line = sprintf(
				/* translators: %s: number of posts in the index. */
				__( 'Index mode: searches use the index even though the build is not complete (%s posts indexed).', 'nguyen-ha-seo-companion' ),
				number_format_i18n( NH_SEO_Search_Index::count() )
			);
		} else {
			$line = __( 'Not built yet: native search is used until the index is built (Tools tab).', 'nguyen-ha-seo-companion' );
		}

		if ( $state['ready'] && ! $state['building'] ) {
			$drift = self::drift( $allow_cache );
			if ( $drift['missing'] + $drift['outdated'] > 0 ) {
				$line .= ' ' . sprintf(
					/* translators: 1: number of published posts that have no row in the index, 2: number of posts edited after they were indexed. */
					__( 'Out of step with the content. Posts missing from the index: %1$s. Posts edited after they were indexed: %2$s. Rebuild the index (Tools tab) if this does not clear up by itself.', 'nguyen-ha-seo-companion' ),
					number_format_i18n( $drift['missing'] ),
					number_format_i18n( $drift['outdated'] )
				);
			}
			if ( $drift['queued'] > 0 ) {
				$line .= ' ' . sprintf(
					/* translators: 1: number of index updates waiting in WP-Cron, 2: how many of them are long overdue. */
					__( 'Index updates waiting in WP-Cron: %1$s (overdue: %2$s). If WP-Cron does not run on this site, rebuild the index.', 'nguyen-ha-seo-companion' ),
					number_format_i18n( $drift['queued'] ),
					number_format_i18n( $drift['overdue'] )
				);
			}
		}

		if ( $state['stale'] ) {
			$line .= ' ' . __( 'A taxonomy change affected more posts than could be refreshed automatically: rebuild the index.', 'nguyen-ha-seo-companion' );
		}
		return $line;
	}
}
