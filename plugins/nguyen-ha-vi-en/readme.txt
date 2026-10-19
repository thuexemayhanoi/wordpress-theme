=== Nguyen Ha VI-EN Core ===
Contributors: nguyen-ha-editorial
Tags: multilingual, vietnamese, english
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later

== Description ==
Development-only VI/EN post-pair manager for blog.rentbikehanoi.com. This version does not enable translated URLs or frontend SEO.

== Features in step 3 ==
- Vietnamese remains the default; /en/ is only reserved metadata.
- Site administrators may manually link two existing normal WordPress posts using Tools > VI/EN Links once installed on staging.
- Each post may belong to only one VI/EN pair; reusing an ID is rejected.
- Pairs are stored only in the plugin-owned non-autoloaded option nh_vien_translation_pairs_v1, not in post content, post meta, Yoast metadata, or taxonomy.
- Link/unlink require administrator capability, edit permission for BOTH posts, and a valid WordPress nonce. They reject self-links, invalid IDs, missing posts, trash, revisions, pages and duplicate relationships.
- Existing posts, slugs, permalinks, canonical, Yoast, sitemap and hreflang remain untouched.
- No write happens during plugin load or admin reads; only an explicit authorized link/unlink changes the private option.
- Corrupt relationship maps fail closed without overwriting data.

== Limits ==
- Direction VI -> EN is selected manually; language detection and automatic translation do not exist in this version.
- There is no /en/ route, language switcher, translation editor, hreflang, canonical or sitemap integration yet.
- The option map is not transaction-safe for simultaneous administrators editing at the same instant; review concurrent-write handling before production release.
- Staging WordPress, PHP 7.4 and Yoast integration tests are still required before merging or installing production.

== CLI checks ==
php plugins/nguyen-ha-vi-en/tests/smoke.php
php plugins/nguyen-ha-vi-en/tests/links.php
php -l plugins/nguyen-ha-vi-en/includes/class-nh-vien-links.php

== Deployment policy ==
Keep this code on a separate development branch. Do not merge into main or activate in production without specific approval and staging verification.
