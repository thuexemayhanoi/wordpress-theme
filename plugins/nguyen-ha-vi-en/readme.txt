=== Nguyen Ha VI-EN Core ===
Contributors: thuexemayhanoi
Tags: multilingual, vietnamese, english, hreflang, seo
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.5.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Native Vietnamese and English sections for one WordPress site: paired posts, /en/ addresses, a language switcher, hreflang and Yoast SEO aware canonicals and sitemaps. It does nothing until an administrator switches it on.

== Description ==

The Vietnamese site stays exactly where it is. The English side is a second set of ordinary WordPress posts that live under `/en/`, each paired by hand with the Vietnamese post it translates. There is no automatic translation, no external service and no English post is ever published by the plugin.

**What it does, once an administrator has switched it on**

* **Paired posts.** An administrator pairs an English post with a Vietnamese one, by post ID, under Tools > VI/EN Links. A pair is one to one and works in both directions. Each post keeps its own title, content, slug, excerpt and SEO fields.
* **English addresses.** A public English post is served at `/en/{slug}/`. The English home is `/en/` (with `/en/page/2/`, ...), categories and tags are `/en/category/{slug}/` and `/en/tag/{slug}/`, and the search is `/en/?s=...`. WordPress finds an English post by that address like any other post, so oEmbed, embeds and link cards work for it.
* **One language per list.** Vietnamese lists (home, categories, tags, archives, search, widgets and previous/next links of the theme) never show English posts, English lists never show Vietnamese ones. A language that has nothing in an archive answers 404 there. The suggestions under a search box follow too: inside the body of a page, the address of the REST search route (`/wp-json/wp/v2/search`) carries `nh_vien_lang=vi|en`, and a request that carries it is answered with that language only; any other REST client sees both languages.
* **No duplicate content.** The ordinary address of an English post (`/{slug}/`) redirects with a 301 to `/en/{slug}/`, and the English post has one canonical address. Paired English posts that may not be shown yet (draft, unpublished Vietnamese partner, a slug that cannot be routed, a page that owns the address) answer 404 on every address; a post that is not paired stays an ordinary post at its own address.
* **Language switcher.** A small navigation with VI and EN links, 44 px touch targets, keyboard and screen reader friendly. On a post it links to the translation, elsewhere to the home page of the other language, and it is left out where there is nothing to link to.
* **Search engines.** `hreflang` (vi, en, x-default) between the two posts of a pair and between the two home pages, a separate canonical per language, English breadcrumbs, and the English addresses in the sitemap (without an SEO plugin the sitemap of WordPress lists the English posts at their English address and leaves out the archives that only English posts fill, which would answer 404). Yoast SEO is supported through its documented filters and stays in charge of titles, canonicals and its single sitemap index.
* **English pages are English.** Inside `/en/` the locale is `en_US` (`<html lang="en-US">`, WordPress and theme strings in English), the logo and the search form of the theme lead to `/en/`, and a menu named `{menu-slug}-en` replaces the Vietnamese menu of the same location when it exists.
* **Caches stay apart.** The fragment cache of the Nguyen Ha Editorial theme gets a language suffix, and it and the page cache purge of the Nguyen Ha SEO Companion plugin are told when a pair changes. Both are optional.

**What it never does**

* It never edits, deletes, re-slugs or redirects a Vietnamese post, never touches the SEO fields of any post, and never writes to a post: its own data is a few options (the pairs, the English articles, categories and page pairs you registered, and the switch), the stored rewrite rules (stored when you switch it on or press the button, and taken out again when you switch it off or deactivate the plugin) and, when you press a button, one draft page.
* It never translates, publishes or schedules content, never calls an external service, never loads a script, and never adds `noindex`.
* It never replaces Polylang, WPML or another multilingual plugin and is not meant to run beside one.

== Installation ==

1. Upload the zip (Plugins > Add New > Upload Plugin) and activate it. **Nothing changes**: the plugin is switched off until an administrator switches it on (below).
2. Create the English posts as ordinary posts (draft first). Give each an ASCII slug (letters, digits, hyphens, for instance `motorbike-rental-in-hanoi`); a slug the plugin cannot route keeps the post hidden.
3. Pair each English post with its Vietnamese post, or register it as an independent English article, under Tools > VI/EN Links.
4. Switch English routing on (next section), then publish the English posts.

== Switching it on ==

Installing, updating or activating the plugin never changes a live site. An administrator switches the English side on, in one of two ways that are never mixed:

**1. The switch in WordPress (no file to edit).** Open Tools > VI/EN Links and press **Switch English routing on**. The plugin stores its rewrite rules in the same step and tells you at once whether it worked; if WordPress does not store them, nothing is changed. The decision is saved with the address of the site, so a copy of the database on another address (a staging copy, a new domain, http to https) stays **closed** until somebody switches it on there. On a **production** site (the environment type of WordPress is not `local`, `development` or `staging`; it is `production` unless `wp-config.php` says otherwise) the button also asks you to tick that you have a recent backup and have checked the English section on a staging copy.

**2. wp-config.php.** When `wp-config.php` defines `NH_VIEN_ENABLE_EN_ROUTES`, the constant decides alone and the switch is not shown: `define( 'NH_VIEN_ENABLE_EN_ROUTES', true );` opens the gate (on production also `define( 'NH_VIEN_ALLOW_PRODUCTION', true );`, set by the owner after the staging checks) and you press **Store the rewrite rules** once; any other value closes it. That is also the emergency brake: `define( 'NH_VIEN_ENABLE_EN_ROUTES', false );` switches the English side off at once, whatever the dashboard says, even when the administration cannot be opened. On production, `define( 'NH_VIEN_ALLOW_PRODUCTION', false );` does the same for the dashboard switch.

Until the rules are stored the plugin changes nothing: no URL, no query, no page, no tag. The screen says whether the English pages are live, who decided, and why not when they are not.

**Before production** (every item on a staging copy of the site, with the same theme, the same SEO plugin and the same permalink structure):

* A complete backup of the database and `wp-content` exists and was opened once.
* The Vietnamese pages are identical with the plugin switched on and off (compare the HTML of the home, a category, a post, the search and the feed). The plugin's own tests do this on a seeded site.
* No post already has the address `/en/` (a published post with the slug `en` makes the plugin refuse to switch on or store the rules, so that it is never shadowed). A published page at `/en/` becomes the English home (put `[nh_vien_posts]` in it); a page below `/en/` keeps its address and wins over an English post with the same slug.
* `/sitemap_index.xml` (Yoast SEO) or `/wp-sitemap.xml` lists the English posts at `/en/{slug}/`, no draft, and no second sitemap exists.
* One canonical and the right hreflang pair on a Vietnamese post, its English partner, both home pages and an English category; the English post has no ordinary-address copy that answers 200.
* The switcher is visible and usable on a phone, a tablet and a desktop, with the keyboard, in the theme that is live.
* The page cache and the CDN keep `/en/...` addresses apart (they are different URLs) and are purged after the pairs are made.
* Nothing answers `/en/...` that should not (drafts, unpaired posts, unknown slugs: all 404).

**Switching it off.** Press **Switch English routing off** (the rules of this plugin leave the stored rewrite rules, every other rule stays as it is, and `/en/...` is as it was before the plugin was switched on), or put the constant `NH_VIEN_ENABLE_EN_ROUTES` as `false` in `wp-config.php`. Deactivating the plugin does both by itself: it takes its own rules out of the stored rules and forgets the switch, so no `/en/...` address can be left answering with the Vietnamese home page and activating the plugin again starts closed. After that an old `/en/{slug}/` address answers 404, or WordPress's own guess sends it to the address the English post has without the plugin. The pairs and the other lists stay in the database (options `nh_vien_translation_pairs_v1`, `nh_vien_independent_en_posts_v1`, `nh_vien_english_categories_v1`, `nh_vien_page_pairs_v1`) so that switching on again needs no new work; delete them by hand if you want them gone.

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
* Constants: `NH_VIEN_ENABLE_EN_ROUTES` (decides alone when defined; `false` is the emergency brake), `NH_VIEN_ALLOW_PRODUCTION` (a production site: `true` vouches for it, anything else forbids the dashboard switch).

== Tests ==

The offline scripts need nothing but PHP and run on PHP 7.4 to 8.4 in the project's CI:

php plugins/nguyen-ha-vi-en/tests/smoke.php
php plugins/nguyen-ha-vi-en/tests/links.php
php plugins/nguyen-ha-vi-en/tests/routes.php
php plugins/nguyen-ha-vi-en/tests/gate.php
php plugins/nguyen-ha-vi-en/tests/gate-config.php
php plugins/nguyen-ha-vi-en/tests/boot.php
php plugins/nguyen-ha-vi-en/tests/query.php
php plugins/nguyen-ha-vi-en/tests/seo.php

The CI also installs the plugin from its zip on real WordPress sites (WordPress 7.1.3 and the oldest supported 6.3.5; the Nguyen Ha Editorial theme with and without Yoast SEO, and a default block theme) and runs `tests/site/run.php` over HTTP (routes, redirects, language lists, switcher, SEO, security, administration, the Vietnamese pages compared with and without the plugin, the switch of the dashboard on and off, deactivation) and the Playwright tests in `tests/e2e/` (switcher at phone, tablet and desktop widths, keyboard, axe). These files are not part of the zip.

== Known limitations ==

* One site per installation: WordPress multisite is not tested.
* Posts are paired, or registered as independent English articles; pages can be paired by ID (About, Prices, Contact); other post types are not translated by this plugin.
* There is no English feed (`/en/feed/` answers 404); the main feed stays Vietnamese.
* Without an SEO plugin the sitemap of WordPress does not list the English home or the English archives (WordPress gives plugins no way to add entries to it).
* The post counts that WordPress keeps for categories and tags, and the monthly Archives widget, count both languages.
* The logo and the search form follow the language through `home_url( '/' )`. A theme that builds those links another way (some block themes) keeps linking to the Vietnamese home.
* Only English posts with a plain ASCII slug can be routed. Menu items, widgets and text that a person typed by hand are not translated.
* The first matching rule wins: a real page at `/en/` or below it takes priority over the plugin's addresses, and a page at `/en/` has no page 2.

== Changelog ==

= 0.5.1 =
* A switch in Tools > VI/EN Links turns English routing on and off without editing wp-config.php: administrator and nonce only; it stores the rewrite rules in the same step and undoes the change when WordPress does not store them; switching off removes only this plugin's rules. The decision is tied to the address of the site (a database copied to another address stays closed), a production site needs a confirmation, and deactivating the plugin forgets the switch.
* wp-config.php stays the authority: a constant `NH_VIEN_ENABLE_EN_ROUTES` that is not `true` closes the English side whatever the dashboard says (the emergency brake), `NH_VIEN_ALLOW_PRODUCTION = false` forbids the dashboard switch on production, and a constant that is `true` keeps working as before.
* Faster: the pages below `/en/` that hold an address are looked up once per request instead of once per English post (a site with hundreds of English posts saved hundreds of queries on every page).
* "Store the rewrite rules" refuses to run without pretty permalinks.
* Tests: the offline scripts for the gate (`gate.php`, `gate-config.php`), the lookups of the pages below `/en/`, and a site suite that drives the switch over HTTP (security, on, off, production, address, constants, deactivation).

= 0.5.0 =
* Permit administrator-designated English articles without mandatory Vietnamese partners; no content or slug is rewritten.
* Permit explicitly designated empty parent/child English categories without changing or taking over Vietnamese categories.
* Link independently authored VI and EN pages by ID only when both serve equivalent visitor intents; unmatched English pages under /en/ remain valid.
* Keep production routes disabled until both explicit production gates and the administrator rewrite-rule action are completed.


= 0.4.0 =
* The English side: addresses for posts, home, categories, tags, pagination and search; one language per list; locale, menus and caches per language; redirect of the ordinary English post address; hreflang, canonical and sitemap entries; Yoast SEO integration; accessible switcher; administration status screen with the rewrite and English home page buttons.
* Off until the site opts in with a constant (and a second one on production); inert until the rules are stored; its own rewrite rules are removed when the plugin is deactivated.
