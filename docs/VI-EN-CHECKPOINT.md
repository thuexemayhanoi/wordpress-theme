# VI-EN native multilingual: checkpoint

Working notes so that any session can continue the VI-EN plugin (`plugins/nguyen-ha-vi-en/`) without redoing finished
work. The user-facing description (how it works, switching it on, production conditions, rollback) is
[VI-EN-PLUGIN.md](VI-EN-PLUGIN.md); the plugin's own `readme.txt` is the developer reference.

## Rules that stay in force

- The ~500 Vietnamese posts are never edited, deleted, re-slugged, redirected or re-canonicalised, and their SEO
  fields are never touched. The plugin writes to no post.
- No Polylang, WPML or any other multilingual plugin; no paid AI API; no automatic translation; no English post is
  published automatically.
- The plugin works on its own (no dependency on the theme or the companion plugin) and does not override Yoast SEO or
  add a second sitemap.
- Own branches only (`feature/...` so that Theme QA runs); no force-push; never push onto another session's branch;
  never merge into `main` or activate on production without the owner's decision.
- A step is not reported as finished while Theme QA has a red job.

## Branches

| Branch | Note |
| --- | --- |
| `main` | theme 2.0.1; no VI-EN code (`8b173d5`) |
| `feature/vi-en-core-step2-20261019` | step 2, inert scaffold (`81a4483`) |
| `feature/chat-vi-en-links-step3-20261019` | step 3, manual VI/EN pairs (`4037673`) |
| `feature/chat-vi-en-routes-step4-20261019` | step 4; fast-forwarded to the green CI fix `67cadc2` |
| `feature/vi-en-step4-ci-fix-20261019` | step 4 plus the CI fixes (`67cadc2`) |
| `feature/vi-en-complete-20261019` | the complete plugin 0.4.0 (pieces B, C and D below), built on `67cadc2`; merged through PR #4 |
| `main` (2026-10-20) | 0.5.0 (PR #5), the Editorial English home, menus and footer (PR #6), the Stage 3 inventory (PR #8) |
| `feature/vi-en-051-wordpress-toggle` (PR #9) | 0.5.1 first cut: the WordPress-only switch, green CI; superseded by the branch below, which contains it |
| `feature/vi-en-051-admin-toggle-rest-20261020` | **0.5.1 complete**: switch with preflight, smoke test, rollback and undo; emergency constant; REST API; publish guard; continue here |
| `feature/vi-en-dashboard-toggle-20261020` (PR #10) | another 0.5.1 attempt, red CI (PHP syntax), no tests; not used |

## What exists (architecture in one page)

- **Pairs**: one option, `nh_vien_translation_pairs_v1` (Vietnamese post ID => English post ID), written only by an
  administrator with a nonce (Tools > VI/EN Links). A post is English iff it is the English side of a pair. An English
  post is *public* when both posts are published, its slug is routable (`[a-z0-9][a-z0-9_-]*`, not reserved) and no
  page lives at `en/{slug}`.
- **Gate** (`NH_VIEN_Routes::enabled()`, decided by `source()`): the emergency constant `NH_VIEN_ENABLE_EN_ROUTES === false`
  wins over everything; then the setting an administrator saved (`nh_vien_routes_enabled_v1`, '1'/'0', autoloaded);
  then, without a setting, the constants of 0.4–0.5 (`NH_VIEN_ENABLE_EN_ROUTES === true` on local/development/staging,
  plus `NH_VIEN_ALLOW_PRODUCTION === true` on production). **Live** = gate open and all eight rewrite rules present in
  the stored `rewrite_rules`. A stale stored rule with a closed gate answers 404; deactivating the plugin removes its own
  stored rules (`NH_VIEN_Routes::deactivate()`; without it every `/en/...` address answered 200 with the Vietnamese home).
- **Switch** (`class-nh-vien-settings.php`, `NH_VIEN_Settings`): `enable( $confirmed )` = capability, emergency constant,
  confirmation on production, `NH_VIEN_Routes::preflight()` (permalinks, post at /en/, reserved pages below /en/, foreign
  rewrite rules, post types/taxonomies at /en/, home page, environment, SEO plugin), save the setting, `register()` +
  one `flush_rewrite_rules( false )`, verify `rules_stored()`, `smoke_test()` (loopback GET of `/en/?nh_vien_probe=`,
  judged by the `X-NH-VIEN-Lang: en` header the English side adds through `wp_headers`), `restore()` of the snapshot
  when the rules are not stored or the smoke test fails (200/404 without the marker); 3xx/401/403/429/5xx or a transport
  error are inconclusive and leave the switch on. `disable()` = setting '0' + `remove_stored_rules()` (array_diff_key,
  no regeneration). `undo()` restores `nh_vien_last_change_v1`. The Tools screen and the REST API both call this class.
- **REST** (`class-nh-vien-rest.php`, namespace `nh-vien/v1`, `manage_options`, registered whatever the gate says):
  `GET /status`, `POST /routes {enabled, confirm}`, `POST /routes/undo`, `POST|DELETE /categories`, `/posts`, `/pairs`,
  `/pages`. The registration handlers call `NH_VIEN_Links` with a nonce created in the request. Errors carry HTTP statuses.
- **Publish guard** (`NH_VIEN_Links::after_insert_post()` on `wp_after_insert_post`, gate open only): a post published
  with registered English categories only is registered as an independent English post; anything else is left alone.
- **Addresses** (`class-nh-vien-urls.php`): `post_link` makes `/en/{slug}/` the permalink of a public English post
  (canonical, sitemaps, theme lists); the native address 301-redirects there; hidden posts are 404 everywhere;
  `term_link` gives English archives inside English pages; the home address is rewritten to `/en/` only between the end
  of `wp_head` and `wp_footer` (so SEO plugins keep one site identity); the address of the REST search route carries
  `nh_vien_lang` inside the body; `url_to_postid` maps `/en/{slug}/` to `?p=ID` so that oEmbed and embeds find English posts.
- **Lists** (`class-nh-vien-query.php`): `pre_get_posts` scopes front-end lists (not admin, AJAX, cron, REST, XML-RPC,
  WP-CLI, singular queries, other post types, `nh_vien_scope=off`); previous/next, term lists, empty-archive 404s and
  the REST search suggestions follow the same rule.
- **Language** (`class-nh-vien-context.php`): set by the matched rule; the locale filter switches to `en_US` early
  from the address; the theme's fragment cache version gets the suffix `-en`.
- **Switcher** (`class-nh-vien-switcher.php`): after the editorial theme's skip link (action
  `nh_editorial_after_skip_link` + theme support `nguyen-ha-vi-en-switcher`, the only two changes in the theme), else
  at `wp_body_open`, else before the content of a single post, plus `[nh_vien_switcher]`.
- **SEO** (`class-nh-vien-seo.php`): hreflang for pairs and homes; own canonical for English lists only without an SEO
  plugin; Yoast through documented filters (canonical, og:url, dynamic permalinks, title of the virtual English
  home, breadcrumbs, sitemap: English posts that may not be shown are excluded, the entry of an English-only term moves to
  its English address, the English archive of a shared term follows it, the English home is added); core sitemap
  arguments without an SEO plugin (English-only terms are left out; the sitemap of WordPress cannot take extra entries).
- **Admin** (`class-nh-vien-admin.php`): status table, store-rules button, draft English home page button, pair form
  and table; every action needs `manage_options` and a nonce; every value is escaped. Storing the rules is refused
  while the gate is open and a published *post* has the slug `en` (it would be shadowed by the English home).
- **Package**: `tools/package.sh` builds `dist/nguyen-ha-vi-en-<version>.zip` (the plugin's `.gitattributes` keeps
  `tests/` out); `tools/verify-package.sh` checks it, including independence from the theme and the companion plugin.

## Tests

| What | Where | How |
| --- | --- | --- |
| Offline scripts (PHP only) | `plugins/nguyen-ha-vi-en/tests/*.php` (`settings.php`: the switch, smoke test, rollback, undo; `rest.php`: routes, permission, emergency constant, statuses) | `for t in plugins/nguyen-ha-vi-en/tests/*.php; do php "$t"; done`; CI runs them on PHP 7.4 to 8.4 |
| Real WordPress over HTTP | `tests/site/seed.php`, `tests/site/run.php` (suites `snapshot`, `compare`, `main`, `homepage`, `legacy`, `inert`, `deactivated`) | job `vi-en-site` of `.github/workflows/theme-qa.yml` (four variants: editorial + Yoast, editorial, Twenty Twenty-Five, WordPress 6.3.5); the plugin is installed from its zip |
| Browser | `tests/e2e/switcher.spec.mjs` | Playwright + axe at 375/768/1440 px, in the same job (`NH_E2E_THEME=other` for a theme the plugin does not know) |
| Packages | `tools/verify-package.sh dist` | job `static` |

A local lab is easy with `tests/bin/setup-site.sh` (MySQL) or any WordPress with SQLite: seed with
`NH_VIEN_SEED_STEP=content wp eval-file plugins/nguyen-ha-vi-en/tests/site/seed.php`, open the gate with
`wp config set NH_VIEN_ENABLE_EN_ROUTES true --raw`, `wp rewrite flush`, seed the pairs (`NH_VIEN_SEED_STEP=pairs`),
then run the suites in the order the workflow uses.

## What is verified, and what is not

Verified by the project's CI on the head of `feature/vi-en-complete-20261019` (see the QA report of its Theme QA run):
the offline scripts on PHP 7.4 to 8.4; the real-site suites and the browser tests listed above; PHPCS (WordPress,
WordPress-Docs, PHPCompatibilityWP 7.4+) with zero findings; the packages.

Not verified (do not claim otherwise):

- A staging site or the production site: none was available. The checklist in [VI-EN-PLUGIN.md](VI-EN-PLUGIN.md)
  section 4 is the work that remains, on a copy of the live site.
- The live Yoast SEO configuration; Rank Math, All in One SEO, SEOPress and The SEO Framework (detected, not tested).
- Multisite, browsers other than Chromium, real devices and screen readers, real page caches and CDNs.

## Known limitations

See `readme.txt` ("Known limitations") and [VI-EN-PLUGIN.md](VI-EN-PLUGIN.md) section 8: no English feed, mixed term
counts and monthly archives widget, themes that build the home link without `home_url( '/' )`, ASCII English slugs
only, a real page at `/en/` has no page 2.

## Next steps

1. Merge the 0.5.1 branch when Theme QA is green, take `dist/nguyen-ha-vi-en-0.5.1.zip` from the release artifact (or the
   GitHub Release), upload it in Plugins → Add New → Upload Plugin → "Replace current with uploaded" (the one manual step:
   WordPress cannot install a zip through the REST API).
2. Switch the routes on from Tools → VI/EN Links (confirmation box on production), or through
   `POST /wp-json/nh-vien/v1/routes {"enabled": true, "confirm": true}` from the wp-aiwriter workflow that holds the
   administrator application password. Read the preflight and the smoke test result; purge the page cache.
3. Register the English categories 46–56 (parents 46, 47, 48 first) and the page pairs 14↔1759, 19↔1760, 25↔1761 through
   the same REST API, then check /en/, the seven English pages, the category archives, canonical, hreflang and
   `/sitemap_index.xml` from the public side.
4. After production, watch Search Console (coverage, hreflang, canonical) for a few weeks.
