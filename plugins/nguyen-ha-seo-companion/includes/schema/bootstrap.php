<?php
/**
 * Schema module bootstrap: JSON-LD structured data (full graph, or FAQPage and LocalBusiness extras).
 *
 * See docs/COMPANION-PLUGIN.md, "Module schema".
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-nh-seo-schema-detect.php';
require_once __DIR__ . '/class-nh-seo-schema-context.php';
require_once __DIR__ . '/class-nh-seo-schema-builder.php';
require_once __DIR__ . '/class-nh-seo-schema.php';

NH_SEO_Schema::init();
