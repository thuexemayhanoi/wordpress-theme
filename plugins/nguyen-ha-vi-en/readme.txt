=== Nguyen Ha VI-EN Core ===
Contributors: thuexemayhanoi
Tags: multilingual, vietnamese, english, hreflang, seo
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.5.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Native Vietnamese and English sections for one WordPress site: paired posts, /en/ addresses, a language switcher, hreflang and Yoast SEO aware canonicals and sitemaps. It does nothing until the site opts in.

== Description ==

The Vietnamese site stays exactly where it is. The English side is a second set of ordinary WordPress posts that live under `/en/`, each paired by hand with the Vietnamese post it translates. There is no automatic translation, no external service and no English post is ever published by the plugin.

**What it does, once the site has opted in**

* **Paired posts.** An administrator pairs an English post with a Vietnamese one, by post ID, under Tools > VI/EN Links. A pair is one to one and works in both directions. Each post keeps its own title, content, slug, excerpt and SEO fields.
* **English addresses.** A public English post is served at `/en/{slug}/`. The English home is `/en/` (with `/en/page/2/`, ...), categories and tags are `/en/category/{slug}/` and `/en/tag/{slug}/`, and the search is `/en/?s=...`. WordPress finds an English post by that address like any other post, so oEmbed, embeds and link cards work for it.
* **One language per list.** Vietnamese lists (home, categories, tags, archives, search, widgets and previous/next links of the theme) never show English posts, English lists never show Vietnamese ones. A language that has nothing in an archive answers 404 there. The suggestions under a search box follow too: inside the body of a page, the address of the REST search route (`/wp-json/wp/v2/search`) carries `nh_vien_lang=vi|en`, and a request that carries it is answered with that language only; any other REST client sees both languages.
* **No duplicate content.** The ordinary address of an English post (`/{slug}/`) redirects with a 301 to `/en/{slug}/`, and the English post has one canonical address. Paired English posts that may not be shown yet (draft, unpublished Vietnamese partner, a slug that cannot be routed, a page that owns the address) answer 404 on every address; a post that is not paired stays an ordinary post at its own address.
* **Language switcher.** A small navigation with VI and EN links, 44 px touch targets, keyboard and screen reader friendly. On a post it links to the translation, elsewhere to the home page of the other language, and it is left out where there is nothing to link to.
* **Search engines.** `hreflang` (vi, en, x-default) between the two posts of a pair and between the two home pages, a separate canonical per language, English breadcrumbs, and the English addresses in the sitemap (without an SEO plugin the sitemap of WordPress lists the English posts at their English address and leaves out the archives that only English posts fill, which would answer 404). Yoast SEO is supported through its documented filters and stays in charge of titles, canonicals and its single sitemap index.
* **English pages are English.** Inside `/en/` the locale is `en_US` (`<html lang="en-US">`, WordPress and theme strings in English), the logo and the search form of the theme lead to `/en/`, and a menu named `{menu-slug}-en` replaces the Vietnamese menu of the same location when it exists.
* **Caches stay apart.** The fragment cache of the Nguyen Ha Editorial theme gets a language suffix, and it and the page cache purge of the Nguyen Ha SEO Companion plugin are told when a pair changes. Both are optional.

**What it never does**

* It never edits, deletes, re-slugs or redirects a Vietnamese post, never touches the SEO fields of any post, and never writes to a post: its own data is one option (the list of pairs), the stored rewrite rules (one button, and again on deactivation, to take its own rules out) and, when you press a button, one draft page.
* It never translates, publishes or schedules content, never calls an external service, never loads a script, and never adds `noindex`.
* It never replaces Polylang, WPML or another multilingual plugin and is not meant to run beside one.

== Installation ==

1. Upload the zip (Plugins > Add New > Upload Plugin) and activate it. **Nothing changes**: the plugin is switched off until the site opts in (below).
2. Create the English posts as ordinary posts (draft first). Give each an ASCII slug (letters, digits, hyphens, for instance `motorbike-rental-in-hanoi`); a slug the plugin cannot route keeps the post hidden.
3. Pair each English post with its Vietnamese post under Tools > VI/EN Links.
4. Opt in (next section), store the rewrite rules once, publish the English posts.

== Switch it on in WordPress — no Hostinger required ==

Version 0.5.1 lets administrators switch English routing on and off directly under **Tools > VI/EN Links**. It does not require editing `wp-config.php` or installing WPCode.

1. Back up the database and files and check that Vietnamese pages are intact.
2. Create English pages and category trees separately. The English home can be a published WordPress Page with slug `en`. Confirm the English page copy, prices and verified contact information before enabling.
3. Open **Tools > VI/EN Links** in WordPress. Click **Enable English routing**. The action requires an administrator account, a security nonce, pretty permalinks and no conflicting published post at the root `/en/`. WordPress stores the setting and refreshes the URL rules; a failed rule update restores the previous setting.
4. Verify `/en/`, English page URLs and category archives; confirm English menus, canonical, hreflang, Yoast `/sitemap_index.xml`, Vietnamese homepage and mobile navigation. Use a fresh backup and plan a rollback before any public rollout.
5. Click **Disable English routing** to turn off the plugin-specific routes. **This does not unpublish English WordPress Pages** (their own WordPress page URLs may still work) or delete posts, terms, menus, SEO or content. Draft those Pages separately if public access must stop.

Legacy settings: if the WordPress toggle has never been used, the earlier `NH_VIEN_ENABLE_EN_ROUTES` and `NH_VIEN_ALLOW_PRODUCTION` constants remain supported. Once an administrator saves an explicit on/off choice through WordPress, that choice takes precedence.

**Safety:** Installing or activating the plugin alone does not enable English routes. A non-administrator cannot toggle them. The plugin does not create a second sitemap or overwrite the Vietnamese site.

== The switcher ==

* It is printed at the top of the page: right after the theme's skip link when the theme says it supports that (the Nguyen Ha Editorial theme does: it fires the action `nh_editorial_after_skip_link` and declares theme support `nguyen-ha-vi-en-switcher`), otherwise where the theme calls `wp_body_open()`.
* A theme that never calls `wp_body_open()` gets the switcher before the content of a single post. Elsewhere, and wherever you want it, use the shortcode `[nh_vien_switcher]`.
* The links are named "VI: Tiếng Việt" and "EN: English"; the navigation is named in the language of the page.

== Shortcodes, menus and widgets ==

* `[nh_vien_posts count="12" category="slug"]` lists the latest English posts that may be shown (count 1 to 50). Put it in a published page at `/en/` to build the English home by hand; the Tools screen can create that page as a draft for you.
* A menu with the slug `{slug}-en` (menu "primary" gets "primary-en") replaces the menu assigned to the same theme location on English pages. A location without an English menu keeps its menu.
* The lists that a theme or a widget builds (latest posts, categories, tags, previous and next post) follow the language of the page.

== Yoast SEO ==

* Yoast SEO keeps `/sitemap_index.xml`, its post, category and tag sitemaps, titles and descriptions. English posts are listed at their English address and English posts that may not be shown yet are left out. The English home is added to the sitemap of posts. In the sitemaps of categories and tags (when Yoast builds them) every term Yoast lists gets its English archive: a term that only English posts use has its entry moved to the English address (so its sitemap is never empty because of the language, which would make Yoast answer 404 for it), and a term that both languages use keeps its entry and gets the English archive right after it. The plugin adds no sitemap and no sitemap index.
* The canonical and `og:url` of an English page are the English address. A canonical an editor typed in Yoast SEO for a post is respected. The Yoast breadcrumb on English pages starts at the English home and links to English addresses, so the page and the BreadcrumbList stay the same.
* Yoast does not write hreflang for separate posts: this plugin prints it, for pairs and for the two home pages only.
* The Yoast integration is exercised in the project's CI against Yoast SEO from wordpress.org; Rank Math, All in One SEO, SEOPress and The SEO Framework are detected (the plugin does not print a second canonical beside them) but not tested.

== Hooks for developers ==

* Filter `nh_vien_switcher_auto_placement` (bool, default true): return false to place the switcher yourself with the shortcode.
* Filter `nh_vien_seo_plugin_active` (bool): tell the plugin whether an SEO plugin already prints canonical addresses.
* Action `nh_vien_pairs_changed( $vi_id, $en_id )`: fired when a pair is made or removed (with zero IDs when the rules are stored again). Purge your own caches here.
* Theme support `nguyen-ha-vi-en-switcher` together with the action `nh_editorial_after_skip_link`: how a theme places the switcher after its skip link.
* Query variable `nh_vien_scope=off` on a `WP_Query`/`get_posts()` call: a list that must see both languages (a sitemap, an administration tool).
* Request parameter `nh_vien_lang=vi|en` on the REST search route: the language of the suggestions (the plugin adds it to the route's address inside the pages it prints).
* Constants: `NH_VIEN_ENABLE_EN_ROUTES`, `NH_VIEN_ALLOW_PRODUCTION`.

== Tests ==

The offline scripts need nothing but PHP and run on PHP 7.4 to 8.4 in the project's CI:

php plugins/nguyen-ha-vi-en/tests/smoke.php
php plugins/nguyen-ha-vi-en/tests/links.php
php plugins/nguyen-ha-vi-en/tests/routes.php
php plugins/nguyen-ha-vi-en/tests/boot.php
php plugins/nguyen-ha-vi-en/tests/query.php

The CI also installs the plugin from its zip on real WordPress sites (WordPress 7.1.3 and the oldest supported 6.3.5; the Nguyen Ha Editorial theme with and without Yoast SEO, and a default block theme) and runs `tests/site/run.php` over HTTP (routes, redirects, language lists, switcher, SEO, security, administration, the Vietnamese pages compared with and without the plugin, deactivation) and the Playwright tests in `tests/e2e/` (switcher at phone, tablet and desktop widths, keyboard, axe). These files are not part of the zip.

== Known limitations ==

* One site per installation: WordPress multisite is not tested.
* Only posts are paired: pages and other post types are not translated by this plugin.
* There is no English feed (`/en/feed/` answers 404); the main feed stays Vietnamese.
* Without an SEO plugin the sitemap of WordPress does not list the English home or the English archives (WordPress gives plugins no way to add entries to it).
* The post counts that WordPress keeps for categories and tags, and the monthly Archives widget, count both languages.
* The logo and the search form follow the language through `home_url( '/' )`. A theme that builds those links another way (some block themes) keeps linking to the Vietnamese home.
* Only English posts with a plain ASCII slug can be routed. Menu items, widgets and text that a person typed by hand are not translated.
* The first matching rule wins: a real page at `/en/` or below it takes priority over the plugin's addresses, and a page at `/en/` has no page 2.

== Changelog ==

= 0.5.1 =
* Add administrator-only buttons to enable/disable the English routes through Tools > VI/EN Links, without editing wp-config.php.
* Rebuild the plugin's own rewrite rules on toggle and revert settings if verification fails; keep support for legacy constants.
* Leave all Vietnamese posts, pages, menus, URLs and SEO data untouched; disabling routing does not automatically unpublish English pages.


= 0.5.0 =
* Permit administrator-designated English articles without mandatory Vietnamese partners; no content or slug is rewritten.
* Permit explicitly designated empty parent/child English categories without changing or taking over Vietnamese categories.
* Link independently authored VI and EN pages by ID only when both serve equivalent visitor intents; unmatched English pages under /en/ remain valid.
* Keep production routes disabled until both explicit production gates and the administrator rewrite-rule action are completed.


= 0.4.0 =
* The English side: addresses for posts, home, categories, tags, pagination and search; one language per list; locale, menus and caches per language; redirect of the ordinary English post address; hreflang, canonical and sitemap entries; Yoast SEO integration; accessible switcher; administration status screen with the rewrite and English home page buttons.
* Off until the site opts in with a constant (and a second one on production); inert until the rules are stored; its own rewrite rules are removed when the plugin is deactivated.
