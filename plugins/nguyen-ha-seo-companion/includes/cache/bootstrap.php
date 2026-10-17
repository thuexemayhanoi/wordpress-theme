<?php
/**
 * Cache module bootstrap: purge page caches once, at shutdown, after content changes.
 *
 * See docs/COMPANION-PLUGIN.md, "Module cache". Setting: purge_caches. Action for other caches: nh_seo_purge_urls.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-nh-seo-cache-urls.php';
require_once __DIR__ . '/class-nh-seo-cache.php';

NH_SEO_Cache::init();
