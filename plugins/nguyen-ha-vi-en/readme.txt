=== Nguyen Ha VI-EN Core ===
Contributors: nguyen-ha-editorial
Tags: multilingual, vietnamese, english
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 0.3.0
License: GPLv2 or later

== Description ==
Development-only plugin for explicit VI/EN post pairs. Stage 4 adds an opt-in EN URL and accessible switcher PREVIEW. Do not deploy to production.

== Language behavior ==
- All existing Vietnamese posts, slugs, permalinks, canonical and SEO fields are untouched.
- Pair management from step 3 remains administrator-only and is independent from routing.
- The /en/{english-slug}/ preview works only for a uniquely matched published WordPress post explicitly paired as EN to a published Vietnamese post.
- Invalid, unlinked or unpublished EN slugs return 404.
- An existing WordPress page at /en/{slug}/ takes priority, to protect previous URLs.
- Switcher appears in the main single-post content for valid pairs or via shortcode [nh_vien_switcher]; it is never displayed for unpaired/draft posts.
- Core canonical redirect is suppressed for verified EN preview URLs only. This is not a Yoast canonical integration.

== Preview gate ==
Preview is OFF by default, even if the plugin is installed. It requires BOTH:
1. WordPress environment type set to local, development or staging.
2. Constant NH_VIEN_ENABLE_EN_ROUTES defined as true, explicitly in staging configuration.

No rewrite flush or database operation runs automatically. On an isolated staging site, after enabling the preview, refresh Permalinks once via WP-CLI or Settings > Permalinks to register the route. After disabling preview, refresh rewrites again to remove any stale rule.

== Not production ready ==
- EN routes support only single posts with simple ASCII slugs, not English homepages, taxonomy pages, pagination, search, feeds or sitemaps.
- The existing ordinary English-post URL may still be accessible and has not been redirected. Duplicate canonicalization MUST be solved before production.
- Yoast canonical/hreflang/sitemap and language-aware taxonomy/search/cache remain future steps.
- Staging must be checked for conflicting /en/ pages, redirects and external SEO plugins.
- No production activation, merge or content migration is authorized by this development branch.

== Offline checks ==
php plugins/nguyen-ha-vi-en/tests/smoke.php
php plugins/nguyen-ha-vi-en/tests/links.php
php plugins/nguyen-ha-vi-en/tests/routes.php
php -l plugins/nguyen-ha-vi-en/includes/class-nh-vien-routes.php
php -l plugins/nguyen-ha-vi-en/includes/class-nh-vien-switcher.php
