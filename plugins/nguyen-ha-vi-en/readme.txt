=== Nguyen Ha VI-EN Core ===
Contributors: nguyen-ha-editorial
Tags: multilingual, vietnamese, english
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later

== Description ==
Development-only VI/EN relationship manager for blog.rentbikehanoi.com. Preserves existing Vietnamese content. No translation URLs or public SEO hooks are enabled.

== Behavior ==
- VI is the default language; /en/ is reserved metadata, not a live route.
- Administrators may explicitly link existing WordPress posts via Tools > VI/EN Links after eventual controlled installation.
- Links are one-to-one in the private, non-autoloaded option nh_vien_translation_pairs_v1.
- Both posts must exist, be normal posts (not pages/products), and not be trashed/auto-drafts.
- Every link/unlink requires administrator capability, edit permission on both IDs, and a valid WordPress nonce.
- Conflicts and duplicate mappings are rejected. An unchanged link is idempotent. No automatic deletion or content edits.
- Plugin does not write anything on install, activation, normal requests or admin page views. Writes are only possible when an administrator explicitly saves or removes a pair.
- Post title, content, existing post meta, taxonomy, permalinks, canonical, sitemap and Yoast fields are untouched.
- A fresh standalone option holds the relationship map; no old Vietnamese WordPress post record is modified.
- This version does not infer which unlinked posts are in English. Public routing, switcher, hreflang and cache segregation remain future work.

== Safety / deployment ==
Do not activate on production until the complete staged integration and rollback checks are reviewed. Do not merge this development branch into main without QA and explicit approval.

== Developer checks ==
php -l each plugin PHP file
php plugins/nguyen-ha-vi-en/tests/smoke.php
php plugins/nguyen-ha-vi-en/tests/links.php

== Next steps ==
Step 4: English URL routing and VI/EN switcher (after permalink and Yoast collision review).
Step 5+: language-aware category/search and SEO integration with site-specific staging tests.
