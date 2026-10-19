=== Nguyen Ha VI-EN Core ===
Contributors: thuexemayhanoi
Tags: multilingual, vietnamese, english, hreflang, seo
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.5.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Native Vietnamese and English sections for one WordPress site: paired posts, /en/ addresses, a language switcher, hreflang and Yoast SEO aware canonicals and sitemaps. It does nothing until an administrator switches it on under Tools > VI/EN Links.

== Description ==

The Vietnamese site stays exactly where it is. The English side is a second set of ordinary WordPress posts and pages that live under `/en/`: posts paired by hand with the Vietnamese post they translate, or independent English posts in English categories, and pages below the English home. There is no automatic translation, no external service and no English post is ever published by the plugin.

**What it does, once an administrator has switched it on**

* **Paired posts.** An administrator pairs an English post with a Vietnamese one, by post ID, under Tools > VI/EN Links. A pair is one to one and works in both directions. Each post keeps its own title, content, slug, excerpt and SEO fields.
* **Independent English posts and categories.** A draft can be registered as an English post without a Vietnamese partner; an empty category (whose parent is English too) can be reserved for English. A post published with English categories only is registered as an English post by itself, so that it never shows up in a Vietnamese list; a post with any other category stays Vietnamese.
* **English addresses.** A public English post is served at `/en/{slug}/`. The English home is `/en/` (with `/en/page/2/`, ...), categories and tags are `/en/category/{slug}/` and `/en/tag/{slug}/`, and the search is `/en/?s=...`. WordPress finds an English post by that address like any other post, so oEmbed, embeds and link cards work for it.
* **One language per list.** Vietnamese lists (home, categories, tags, archives, search, widgets and previous/next links of the theme) never show English posts, English lists never show Vietnamese ones. A language that has nothing in an archive answers 404 there. The suggestions under a search box follow too: inside the body of a page, the address of the REST search route (`/wp-json/wp/v2/search`) carries `nh_vien_lang=vi|en`, and a request that carries it is answered with that language only; any other REST client sees both languages.
* **No duplicate content.** The ordinary address of an English post (`/{slug}/`) redirects with a 301 to `/en/{slug}/`, and the English post has one canonical address. Paired English posts that may not be shown yet (draft, unpublished Vietnamese partner, a slug that cannot be routed, a page that owns the address) answer 404 on every address; a post that is not paired stays an ordinary post at its own address.
* **Language switcher.** A small navigation with VI and EN links, 44 px touch targets, keyboard and screen reader friendly. On a post it links to the translation, elsewhere to the home page of the other language, and it is left out where there is nothing to link to.
* **Search engines.** `hreflang` (vi, en, x-default) between the two posts of a pair and between the two home pages, a separate canonical per language, English breadcrumbs, and the English addresses in the sitemap (without an SEO plugin the sitemap of WordPress lists the English posts at their English address and leaves out the archives that only English posts fill, which would answer 404). Yoast SEO is supported through its documented filters and stays in charge of titles, canonicals and its single sitemap index.
* **English pages are English.** Inside `/en/` the locale is `en_US` (`<html lang="en-US">`, WordPress and theme strings in English), the logo and the search form of the theme lead to `/en/`, and a menu named `{menu-slug}-en` replaces the Vietnamese menu of the same location when it exists.
* **Caches stay apart.** The fragment cache of the Nguyen Ha Editorial theme gets a language suffix, and it and the page cache purge of the Nguyen Ha SEO Companion plugin are told when a pair changes. A switch of the English side purges the whole page cache of LiteSpeed Cache, WP Rocket, W3 Total Cache or WP Super Cache when one of them is active, and fires `nh_vien_purge_all` for the others. All of this is optional.

**What it never does**

* It never edits, deletes, re-slugs or redirects a Vietnamese post, never touches the SEO fields of any post, and never writes to a post: its own data is a handful of options (the switch, the pairs, the English posts and categories, the last switch), the stored rewrite rules (when the switch is used, and on deactivation, to take its own rules out) and, when you press a button, one draft page.
* It never translates, publishes or schedules content, never calls an external service, never loads a script, and never adds `noindex`.
* It never replaces Polylang, WPML or another multilingual plugin and is not meant to run beside one.

== Installation ==

1. Upload the zip (Plugins > Add New > Upload Plugin) and activate it; to update, upload the new zip and choose "Replace current with uploaded". **Nothing changes**: the plugin is switched off until an administrator switches it on (below).
2. Create the English posts as ordinary posts (draft first). Give each an ASCII slug (letters, digits, hyphens, for instance `motorbike-rental-in-hanoi`); a slug the plugin cannot route keeps the post hidden.
3. Pair each English post with its Vietnamese post, or register it as an independent English post, under Tools > VI/EN Links; register the English categories there before assigning them.
4. Switch the English routes on (next section), publish the English posts.

== Switching it on ==

Everything happens under **Tools > VI/EN Links**, by an administrator with a security nonce; no wp-config.php line, no WPCode snippet and no hosting access is needed.

1. The screen runs a **preflight** and lists its checks: pretty permalinks, no published post at `/en/`, no published page at a reserved address below `/en/` (page, category, tag, feed, ...), no other rewrite rule, post type or taxonomy claiming `/en/`, whether a published page at `/en/` exists (it is the English home), the environment type and the SEO plugin. A conflict blocks the switch and is explained; a note does not block.
2. Press **Switch the English routes on**. On a production site the confirmation box (a fresh backup exists and the English pages were checked) must be ticked. The plugin saves the setting (option `nh_vien_routes_enabled_v1`, autoloaded), registers its rewrite rules and stores them once (the only flush), then asks the server for `/en/`: the **smoke test**. Every answer of the English side carries the header `X-NH-VIEN-Lang: en`. If the rules cannot be stored, or `/en/` is answered without that header (a page cache or another rule answered instead), the previous state is restored and the screen says why. A request the server cannot make itself (a firewall, a redirect, a timeout) leaves the switch on and asks you to open `/en/` in a browser.
3. **Undo the last switch** goes back to the state before the last switch: the setting and the rewrite rules.
4. **Switch the English routes off** saves the setting and takes the plugin's own rules out of the stored rewrite rules without touching any other rule or regenerating them. `/en/...` is then answered by WordPress as if the plugin were not there: published pages below `/en/` keep their WordPress addresses, English posts are not shown anywhere, and the Vietnamese site is as it was. Deactivating the plugin also takes its rules out.

**Older setups.** A site that was switched on with the constants of versions 0.4 and 0.5 (`NH_VIEN_ENABLE_EN_ROUTES`, and `NH_VIEN_ALLOW_PRODUCTION` on production) keeps working until the buttons are used: a saved setting wins over the constants. **Emergency switch:** `define( 'NH_VIEN_ENABLE_EN_ROUTES', false );` in wp-config.php keeps the English side off whatever the setting says, for a site whose administration cannot be reached; the Tools screen says so and refuses the switch until the line is removed.

**Before production** (on a staging copy with the same theme, the same SEO plugin and the same permalink structure, or with great care on the live site after a backup):

* A complete backup of the database and `wp-content` exists and was opened once.
* The Vietnamese pages are identical with the plugin switched on and off (compare the HTML of the home, a category, a post, the search and the feed). The plugin's own tests do this on a seeded site.
* The preflight of the Tools screen shows no conflict. A published page at `/en/` becomes the English home (the Nguyen Ha Editorial theme renders it with its English home template; other themes show the page, put `[nh_vien_posts]` in it); a page below `/en/` keeps its address and wins over an English post with the same slug.
* `/sitemap_index.xml` (Yoast SEO) or `/wp-sitemap.xml` lists the English posts at `/en/{slug}/`, no draft, and no second sitemap exists.
* One canonical and the right hreflang pair on a Vietnamese post, its English partner, both home pages and an English category; the English post has no ordinary-address copy that answers 200.
* The switcher is visible and usable on a phone, a tablet and a desktop, with the keyboard, in the theme that is live.
* The page cache and the CDN keep `/en/...` addresses apart (they are different URLs) and are purged after the switch and after the pairs are made.
* Nothing answers `/en/...` that should not (drafts, unpaired posts, unknown slugs: all 404).

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

== REST API ==

The same actions are available to administrators through the REST API (application passwords work), so that a release pipeline can do what the Tools screen does, with the same checks. Every route is below `/wp-json/nh-vien/v1/` and needs `manage_options`; the routes exist whether the switch is on or off, because they are how it is switched on without hosting access.

* `GET /status`: the state of the English side (switch, its source, environment, stored rules, live, English home, pairs, English categories and posts, preflight, last switch).
* `POST /routes` with `{"enabled": true, "confirm": true}` or `{"enabled": false}`: the switch, with the preflight, the smoke test and the rollback of the Tools screen; `POST /routes/undo` goes back to the state before the last switch.
* `POST /categories` with `{"id": 46}` and `DELETE /categories/46`: register or unregister an English category (empty, parent registered first).
* `POST /posts` with `{"id": N}` and `DELETE /posts/N`: register a draft or pending post as an independent English post before publishing it, or unregister it.
* `POST /pairs` with `{"vi": N, "en": M}` and `DELETE /pairs/N`; `POST /pages` with `{"vi": N, "en": M}` and `DELETE /pages/N`: pairs of posts and of pages.

A refusal answers with the error code of the Tools screen and an HTTP status: 400 invalid input, 403 forbidden, 404 not found, 409 conflict (the preflight, a category with posts, a post already paired, the emergency constant).

== Hooks for developers ==

* Filter `nh_vien_switcher_auto_placement` (bool, default true): return false to place the switcher yourself with the shortcode.
* Filter `nh_vien_seo_plugin_active` (bool): tell the plugin whether an SEO plugin already prints canonical addresses.
* Action `nh_vien_pairs_changed( $vi_id, $en_id )`: fired when a pair is made or removed (with zero IDs when the rules are stored again). Purge your own caches here.
* Theme support `nguyen-ha-vi-en-switcher` together with the action `nh_editorial_after_skip_link`: how a theme places the switcher after its skip link.
* Query variable `nh_vien_scope=off` on a `WP_Query`/`get_posts()` call: a list that must see both languages (a sitemap, an administration tool).
* Request parameter `nh_vien_lang=vi|en` on the REST search route: the language of the suggestions (the plugin adds it to the route's address inside the pages it prints).
* Response header `X-NH-VIEN-Lang: en` on every answer of the English side (404 included): for caches, monitors and the smoke test.
* Options: `nh_vien_routes_enabled_v1` ('1' or '0', the switch; absent on a site that still uses the constants), `nh_vien_last_change_v1` (what the last switch replaced).
* Constants of older versions, honoured while no setting is saved: `NH_VIEN_ENABLE_EN_ROUTES` (true), `NH_VIEN_ALLOW_PRODUCTION` (true, on production). `NH_VIEN_ENABLE_EN_ROUTES` defined as false is the emergency switch and wins over the setting.

== Tests ==

The offline scripts need nothing but PHP and run on PHP 7.4 to 8.4 in the project's CI:

php plugins/nguyen-ha-vi-en/tests/smoke.php
php plugins/nguyen-ha-vi-en/tests/links.php
php plugins/nguyen-ha-vi-en/tests/routes.php
php plugins/nguyen-ha-vi-en/tests/boot.php
php plugins/nguyen-ha-vi-en/tests/query.php
php plugins/nguyen-ha-vi-en/tests/seo.php
php plugins/nguyen-ha-vi-en/tests/settings.php
php plugins/nguyen-ha-vi-en/tests/rest.php

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
* The switch of the English side moves into WordPress: Tools > VI/EN Links (administrators, nonce) switches the routes on and off, stores the setting with the Options API and needs no wp-config.php line. A saved setting wins over the constants of 0.4 and 0.5, which keep working until the buttons are used; `NH_VIEN_ENABLE_EN_ROUTES` defined as false is the emergency switch.
* Switching on runs a preflight (permalinks, a post at /en/, reserved addresses below /en/, foreign rewrite rules, post types and taxonomies at /en/, the English home, environment, SEO plugin), stores the rewrite rules once, then asks the server for /en/ and goes back to the previous state when the rules cannot be stored or /en/ is not answered in English. A production site asks for a confirmation. Switching off takes only the plugin's own rules out of the stored rules. "Undo the last switch" restores the state before it.
* Every answer of the English side carries the header `X-NH-VIEN-Lang: en`.
* REST API below /wp-json/nh-vien/v1/ for administrators: status, the switch, undo, and the registrations of English categories, English posts, post pairs and page pairs, with the checks of the Tools screen.
* A post published with English categories only is registered as an English post by itself.

= 0.5.0 =
* Permit administrator-designated English articles without mandatory Vietnamese partners; no content or slug is rewritten.
* Permit explicitly designated empty parent/child English categories without changing or taking over Vietnamese categories.
* Link independently authored VI and EN pages by ID only when both serve equivalent visitor intents; unmatched English pages under /en/ remain valid.
* Keep production routes disabled until both explicit production gates and the administrator rewrite-rule action are completed.


= 0.4.0 =
* The English side: addresses for posts, home, categories, tags, pagination and search; one language per list; locale, menus and caches per language; redirect of the ordinary English post address; hreflang, canonical and sitemap entries; Yoast SEO integration; accessible switcher; administration status screen with the rewrite and English home page buttons.
* Off until the site opts in with a constant (and a second one on production); inert until the rules are stored; its own rewrite rules are removed when the plugin is deactivated.
