# VI-EN native multilingual: checkpoint

Working notes so that any session can continue the VI-EN plugin (`plugins/nguyen-ha-vi-en/`) without redoing finished
work. The user-facing description (how it works, switching it on, production conditions, rollback) is
[VI-EN-PLUGIN.md](VI-EN-PLUGIN.md); the plugin's own `readme.txt` is the developer reference. The facts about the
production WordPress database (draft English pages, empty English categories, business data) are in
[EN-STAGE3-WORDPRESS-INVENTORY.md](EN-STAGE3-WORDPRESS-INVENTORY.md); the safe setup order is in
[EN-HOME-SETUP.md](EN-HOME-SETUP.md).

## Rules that stay in force

- The ~500 Vietnamese posts are never edited, deleted, re-slugged, redirected or re-canonicalised, and their SEO
  fields are never touched. The plugin writes to no post, page or term.
- No Polylang, WPML or any other multilingual plugin; no paid AI API; no automatic translation; no English post is
  published automatically.
- The plugin works on its own (no dependency on the theme or the companion plugin) and does not override Yoast SEO or
  add a second sitemap.
- Own branches only (`feature/...` so that Theme QA runs); no force-push; never push onto another session's branch;
  never merge into `main` or activate on production without the owner's decision. Merging on GitHub deploys nothing:
  the website changes only when a zip is installed and the switch is pressed.
- The business data (address, phone, prices) of this blog (Nguyen Ha) is never mixed with the data of the other
  business (Nguyen Tu); prices and policies are never invented.
- A step is not reported as finished while Theme QA has a red job.

## Branches and pull requests

| Branch / PR | State |
| --- | --- |
| `main` | theme 2.0.1; plugin **0.5.0** (`4c38aba`): independent English posts, English category trees, page pairs, editorial English home, English menus and footer (PRs #4, #5, #6, #8) |
| `feature/vi-en-final-20261019` | **plugin 0.5.1: continue here.** One switch design, one lookup for the pages below `/en/`, the `dashboard` site suite, the fixes of two independent reviews (rewrite rules, pairs, menus, lists, addresses, cache), current docs. Supersedes PR #9 and PR #10 |
| PR #9 `feature/vi-en-051-wordpress-toggle`, PR #10 `feature/vi-en-dashboard-toggle-20261020` | two competing 0.5.1 switches, **superseded** (not merged, not deleted). As read from their code (not run): in #9 the option overrides the wp-config.php constant (no emergency brake) and opens a production site without confirmation; #10 flushes with the plugin's rules still registered in memory (so switching off appears to leave them stored), has no rollback, no confirmation on production and no binding to the address of the site; neither tests the button on a real site |
| PR #7 `feature/vi-en-stage2-home-ready-20261020` (draft) | an alternative of stage 2 that was never merged (PR #6 was), based on an older `main`; **superseded** |
| PR #3 `seo/home-services-20261018` | unrelated (four service links on the Vietnamese home); not touched |
| older `feature/...step2/3/4...` branches | merged long ago through PR #4; kept as history |

## What exists (architecture in one page)

- **Content** (`class-nh-vien-links.php`): three kinds of English content. *Pairs*: option `nh_vien_translation_pairs_v1`
  (Vietnamese post ID => English post ID, one to one). *Independent English articles*: option
  `nh_vien_independent_en_posts_v1`, registered while the post is still a draft or pending (a published post cannot be
  relabelled). *Pages*: pages below `/en/`, optionally paired with a Vietnamese page (`nh_vien_page_pairs_v1`). English
  category trees are declared in `nh_vien_english_categories_v1` (empty categories only, parents before children). A post
  is English iff it is the English side of a pair or registered; an English post is *public* when it is published (and
  its Vietnamese partner is, for a pair), its slug is routable (`[a-z0-9][a-z0-9_-]*`, not reserved) and no published page
  holds `en/{slug}`. Every option is written only by an administrator with a nonce (Tools > VI/EN Links). A new pair needs its
  English side to be a draft or pending post. The slugs of the public English posts come from **one query of two columns**
  (`ID`, `post_name`: never the content), cached until any post changes (`NH_VIEN_Links::published_rows()`), and the terms of
  those posts are read once (`en_populated_term_ids()`: the terms and parents of the public posts, which is what the English
  fallback menu lists; `en_term_ids()` adds the declared empty categories). Both term lookups opt out of the term filter
  (`nh_vien_scope=off`): core runs `wp_get_object_terms()` through `get_terms()`, and the filter would drop every term while the
  lookup it needs is still being made.
- **Gate** (`class-nh-vien-gate.php`, `NH_VIEN_Routes::enabled()` delegates to it). Two ways, never mixed. (1) wp-config.php
  defines `NH_VIEN_ENABLE_EN_ROUTES`: the constant decides alone (`true` opens, on production also with
  `NH_VIEN_ALLOW_PRODUCTION === true`; anything else closes: the emergency brake). (2) No constant: the switch in
  Tools > VI/EN Links (`NH_VIEN_Admin::toggle()` -> `NH_VIEN_Gate::switch_on()/switch_off()`), stored in the autoloaded
  option `nh_vien_gate_v1` = `on`, `site` (fingerprint of the `home` option: another address counts as off), `confirmed`
  (production only), `user`, `time`. A production site needs the confirmation, `NH_VIEN_ALLOW_PRODUCTION` that is not
  `true` forbids it. Switching on registers and stores the 8 rules and undoes everything when they are not stored;
  switching off removes only the plugin's own keys from `rewrite_rules` (no flush); deactivation does the same and
  forgets the option. **Live** = gate open and all eight rules stored; a closed gate with a stored rule answers 404
  (never the Vietnamese home page).
- **Requests** (`NH_VIEN_Routes::parse_request()`): a matched rule answers only when the English side is live (else 404, never the
  Vietnamese home); a **list** route keeps only the query variables of `LIST_VARS` (WordPress also reads `?p=`, `?page_id=`,
  `?author=` from the address, and the address wins over the rule); anything else below `/en/` that no rule of ours matched (a
  page two levels down, a 404) is made English through `NH_VIEN_Context::address_is_english()`; a post address that nothing holds
  is looked up in `_wp_old_slug` (exactly one public English post, slug free now) and redirected in `template_redirect`.
- **Addresses** (`class-nh-vien-urls.php`): `post_link` makes `/en/{slug}/` the permalink of a public English post
  (canonical, sitemaps, theme lists); the native address 301-redirects there; hidden posts are 404 everywhere;
  `term_link` gives English archives inside English pages; the home address is rewritten to `/en/` only between the end
  of `wp_head` and `wp_footer` (so SEO plugins keep one site identity); the address of the REST search route carries
  `nh_vien_lang` inside the body; `url_to_postid` maps `/en/{slug}/` to `?p=ID` so that oEmbed and embeds find English posts.
- **Pages below /en/** (`NH_VIEN_Routes::pages_below_en()`): one lookup per request (the page `en` and its published
  children, slug => ID) is the single answer for the public list, request resolution and the fallback menu. It replaced a
  lookup per English post (about 200 extra queries per page with 200 English posts).
- **Lists** (`class-nh-vien-query.php`): `pre_get_posts` scopes front-end lists (not admin, AJAX, cron, REST, XML-RPC,
  WP-CLI, singular queries, other post types, `nh_vien_scope=off`); previous/next, term lists, empty-archive 404s and
  the REST search suggestions follow the same rule. One function, `scoped_ids()`, merges `post__in` and `post__not_in` (WP_Query
  ignores `post__not_in` once `post__in` is set) and adds the English pages (`english_page_ids()`) for queries that may return
  pages; `post__in = [0]` stays empty.
- **Language** (`class-nh-vien-context.php`): set by the matched rule; the locale filter switches to `en_US` early
  from the address; the theme's fragment cache version gets the suffix `-en`.
- **Content helpers** (`class-nh-vien-content.php`): English menus (`primary-en`, `footer-en`; a location without one is marked
  and answered by the filter `pre_wp_nav_menu` with an English-only fallback menu: Home, the About/Prices/Contact pages and the
  top-level categories that have English posts; WordPress would otherwise pick the first menu that has items, the Vietnamese
  one), the editorial English home template, escaped English titles, `[nh_vien_posts]`, and no feed links on English pages.
- **Switcher** (`class-nh-vien-switcher.php`): after the editorial theme's skip link (action
  `nh_editorial_after_skip_link` + theme support `nguyen-ha-vi-en-switcher`), else at `wp_body_open`, else before the
  content of a single post, plus `[nh_vien_switcher]`.
- **SEO** (`class-nh-vien-seo.php`): hreflang for pairs, page pairs and homes; own canonical for English lists only without
  an SEO plugin; Yoast through documented filters (canonical, og:url, dynamic permalinks, title of the virtual English
  home, breadcrumbs, sitemap: English posts that may not be shown are excluded, the entry of an English-only term moves
  to its English address, the English archive of a shared term follows it, the English home is added); core sitemap
  arguments without an SEO plugin.
- **Admin** (`class-nh-vien-admin.php`): status table (who decides, environment, rules, live), the switch, store-rules
  button, draft English home page button, pair / independent article / category / page-pair forms; every action needs
  `manage_options` and a nonce; every value is escaped. Refuses to switch on or store the rules without pretty permalinks
  or while a published *post* has the slug `en`.
- **Cache** (`class-nh-vien-cache.php`): `nh_vien_pairs_changed` bumps the theme's fragment cache and queues the companion's page
  cache; the filter `nh_seo_purge_post_urls` adds the other side of a pair, the English home and the English archives of a post.
- **Package**: `tools/package.sh` builds `dist/nguyen-ha-vi-en-<version>.zip` (the plugin's `.gitattributes` keeps
  `tests/` out); `tools/verify-package.sh` checks it, including independence from the theme and the companion plugin.

## Tests

| What | Where | How |
| --- | --- | --- |
| Offline scripts (PHP only) | `plugins/nguyen-ha-vi-en/tests/*.php` (`smoke, links, routes, gate, gate-config, boot, query, content, cache, seo`; shared stubs in `tests/lib/db-stub.php`) | `for t in plugins/nguyen-ha-vi-en/tests/*.php; do php "$t"; done`; CI runs them on PHP 7.4 to 8.4. Mutation-checked by hand: break one important line in a copy of the plugin and the scripts must fail |
| Real WordPress over HTTP | `tests/site/seed.php`, `tests/site/run.php` (suites `snapshot`, `compare`, `main`, `homepage`, `legacy`, `inert`, `deactivated`, `dashboard`; lab result at the last change: 182, 10, 20, 10 and 188 checks) | job `vi-en-site` of `.github/workflows/theme-qa.yml` (four variants: editorial + Yoast, editorial, Twenty Twenty-Five, WordPress 6.3.5); the plugin is installed from its zip |
| Browser | `tests/e2e/switcher.spec.mjs` | Playwright + axe at 375/768/1440 px, in the same job (`NH_E2E_THEME=other` for a theme the plugin does not know) |
| Packages | `tools/verify-package.sh dist` | job `static` |

A local lab without MySQL: any WordPress with the SQLite integration plugin (the SQLite lab of this work used WordPress
7.1.3 cloned from its public mirror), the theme and plugins symlinked, `WPLANG` set with `wp config set`, then seed with
`NH_VIEN_SEED_STEP=content wp eval-file plugins/nguyen-ha-vi-en/tests/site/seed.php`, serve with `tests/bin/serve.sh`,
open the gate with `wp config set NH_VIEN_ENABLE_EN_ROUTES true --raw`, `wp rewrite flush`, seed the pairs
(`NH_VIEN_SEED_STEP=pairs`), then run the suites in the order the workflow uses; `dashboard` runs last, after the plugin is
activated again, with `--wp="wp --allow-root --path=..."`. Copy `tests/bin/mu-metrics.php` into `mu-plugins` and define
`SAVEQUERIES` to count queries per page.

## What is verified, and what is not

Verified (see the QA report of the Theme QA run of the branch): the offline scripts on PHP 7.4 to 8.4; the real-site suites
and the browser tests above; PHPCS (WordPress, WordPress-Docs, PHPCompatibilityWP 7.4+) with zero findings; the packages.

Lab tip: the PHP built-in server caches the resolution of a symlink; after pointing `wp-content/plugins/nguyen-ha-vi-en` at another
copy of the plugin, restart the server, or the old code keeps answering for a while (this skewed a first performance comparison).

Not verified (do not claim otherwise):

- A staging site or the production site: none was available. The checklist in [VI-EN-PLUGIN.md](VI-EN-PLUGIN.md)
  section 4 is the work that remains, on a copy of the live site.
- The live Yoast SEO configuration; Rank Math, All in One SEO, SEOPress and The SEO Framework (detected, not tested).
- Multisite, browsers other than Chromium, real devices and screen readers, real page caches and CDNs, hosting limits.
- Timings on the real hosting: the query counts of the lab are for comparing versions, not for predicting seconds.

## Known limitations

See `readme.txt` ("Known limitations") and [VI-EN-PLUGIN.md](VI-EN-PLUGIN.md) section 8: no English feed, mixed term
counts and monthly archives widget, themes that build the home link without `home_url( '/' )`, ASCII English slugs
only, a real page at `/en/` has no page 2, the dashboard decision closes when the address of the site changes, no date or author
archive in English, REST and embed not covered, rules left behind by an FTP deletion or a network-wide deactivation.

## Next steps (owner's decision)

1. Review `feature/vi-en-final-20261019`; merge it into `main` only when the owner says so, then close PR #9, #10 and #7.
2. Run the staging checklist, then the production procedure of [VI-EN-PLUGIN.md](VI-EN-PLUGIN.md) section 5 (backup,
   install the zip, register the English categories/pages/posts with the IDs of that environment, switch on with the
   confirmation, publish the approved English pages and articles in a planned window).
3. After production, watch Search Console (coverage, hreflang, canonical) for a few weeks.
