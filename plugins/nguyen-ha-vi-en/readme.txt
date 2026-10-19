=== Nguyen Ha VI-EN Core ===
Contributors: nguyen-ha-editorial
Tags: multilingual, vietnamese, english
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later

== Description ==
Safety-first scaffold for a future native VI/EN language system on blog.rentbikehanoi.com. This version exposes read-only language metadata and registers no routes, redirects, switcher, content linkage, admin UI, Yoast filters, or sitemap endpoints.

== Installation ==
Development branch only. Do not install on production until a separate WordPress compatibility QA and deployment approval.

== Safety ==
- Preserves every existing Vietnamese post, URL, term and SEO value.
- Does not create or update options, post meta, posts, terms or database tables.
- Does not register activation/uninstall/deactivation mutations or rewrite rules.
- Does not override Yoast SEO or register sitemap, canonical or hreflang tags.
- Remains independent of any WordPress theme and of third-party translation plugins.

== Next milestones ==
1. Translation relationship storage and nonce/capability-protected editorial UI.
2. English URLs and correct 404/redirect behavior after permalink review.
3. VI/EN switcher, translated categories and language-aware search.
4. Reciprocal hreflang, canonical, Yoast sitemap integration and cache separation.
5. WordPress staging tests, no-regression checks and separate plugin ZIP release.
