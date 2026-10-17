<?php
/**
 * Search module bootstrap: internal search index with a clean fallback to native WordPress search.
 *
 * See docs/COMPANION-PLUGIN.md, "Module search".
 *
 *  table        {$wpdb->prefix}nh_search_index, created with dbDelta on `nh_seo_upgrade`
 *  option       nh_seo_search_state (state), transient nh_seo_search_off (ten-minute pause after a SQL error)
 *  cron hooks   nh_seo_search_reindex_term, nh_seo_search_reindex_ids (unscheduled on deactivation)
 *  tools panel  action nh_seo_render_tools_panel
 *  status line  filter nh_seo_search_status
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-nh-seo-search-text.php';
require_once __DIR__ . '/class-nh-seo-search-index.php';
require_once __DIR__ . '/class-nh-seo-search-document.php';
require_once __DIR__ . '/class-nh-seo-search-state.php';
require_once __DIR__ . '/class-nh-seo-search-indexer.php';
require_once __DIR__ . '/class-nh-seo-search-builder.php';
require_once __DIR__ . '/class-nh-seo-search-query.php';
require_once __DIR__ . '/class-nh-seo-search-admin.php';

/**
 * Whether the results of a search query are the relaxed ones (any of the words) because no post matched all words.
 *
 * Themes can use it to say "No exact match, showing partial matches".
 *
 * @param WP_Query|null $query Query, or null for the main query.
 * @return bool
 */
function nh_seo_search_was_relaxed( $query = null ) {
	return NH_SEO_Search_Query::was_relaxed( $query );
}

/**
 * Create or upgrade the index table. Runs from NH_SEO_Plugin::maybe_upgrade() and on activation.
 *
 * @return void
 */
function nh_seo_search_upgrade() {
	if ( ! NH_SEO_Search_Index::create_table() ) {
		NH_SEO_Search_State::save(
			array(
				'last_error' => __( 'The index table could not be created. InnoDB full-text indexes need MySQL 5.6 or MariaDB 10.0.5 or newer.', 'nguyen-ha-seo-companion' ),
			)
		);
	}
}

/**
 * Activation: lift an old pause. Building the index is always an explicit action in the Tools panel.
 *
 * @return void
 */
function nh_seo_search_activate() {
	NH_SEO_Search_State::clear_breaker();
}

add_action( 'nh_seo_upgrade', 'nh_seo_search_upgrade' );
add_action( 'nh_seo_activate', 'nh_seo_search_activate' );
add_action( 'nh_seo_deactivate', array( 'NH_SEO_Search_Indexer', 'on_deactivate' ) );

NH_SEO_Search_Indexer::init();
NH_SEO_Search_Query::init();
NH_SEO_Search_Admin::init();
