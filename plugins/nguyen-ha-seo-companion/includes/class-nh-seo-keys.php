<?php
/**
 * Storage keys and limits shared by all modules.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * Storage keys and limits shared by all modules. Keep in sync with docs/DATA-CONTRACT.md.
 */
final class NH_SEO_Keys {

	const TAXONOMY_CLUSTER = 'topic_cluster';

	// Post meta (posts and pages).
	const META_SUMMARY         = 'nh_summary';
	const META_TAKEAWAYS       = 'nh_takeaways';
	const META_FAQ             = 'nh_faq';
	const META_SOURCES         = 'nh_sources';
	const META_UPDATED         = 'nh_content_updated';
	const META_CLUSTER_ALIAS   = 'nh_topic_cluster';
	const META_PRIMARY_CLUSTER = 'nh_primary_cluster';
	const META_PILLAR          = 'nh_pillar_id';
	const META_HUB_TERM        = 'nh_hub_term';
	const META_LOCAL_BUSINESS  = 'nh_local_business';
	const META_KEYWORDS        = 'nh_keywords';

	// Term meta (category and topic_cluster).
	const TERM_PILLAR = 'nh_pillar_id';

	// User meta.
	const USER_JOB_TITLE = 'nh_job_title';
	const USER_SAMEAS    = 'nh_sameas';

	// Options.
	const OPTION_BUSINESS     = 'nh_seo_business';
	const OPTION_SETTINGS     = 'nh_seo_settings';
	const OPTION_SEARCH_STATE = 'nh_seo_search_state';

	// Limits (characters / items).
	const LIMIT_SUMMARY          = 800;
	const LIMIT_TAKEAWAYS        = 10;
	const LIMIT_TAKEAWAY_CHARS   = 300;
	const LIMIT_FAQ_ITEMS        = 20;
	const LIMIT_FAQ_QUESTION     = 300;
	const LIMIT_FAQ_ANSWER       = 3000;
	const LIMIT_SOURCES          = 15;
	const LIMIT_SOURCE_TITLE     = 200;
	const LIMIT_SOURCE_PUBLISHER = 120;
	const LIMIT_KEYWORDS         = 300;
	const LIMIT_CLUSTER_ALIAS    = 120;
}
