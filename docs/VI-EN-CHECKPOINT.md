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
| `feature/vi-en-complete-20261019` | **the complete plugin** (pieces B, C and D below), built on `67cadc2`; continue here |

## What exists (architecture in one page)

- **Pairs**: one option, `nh_vien_translation_pairs_v1` (Vietnamese post ID => English post ID), written only by an
  administrator with a nonce (Tools > VI/EN Links). A post is English iff it is the English side of a pair. An English
  post is *public* when both posts are published, its slug is routable (`[a-z0-9][a-z0-9_-]*`, not reserved) and no
  page lives at `en/{slug}`.
- **Gate** (`NH_VIEN_Routes::enabled()`): constant `NH_VIEN_ENABLE_EN_ROUTES === true` and a local/development/staging
  environment, or on production also `NH_VIEN_ALLOW_PRODUCTION === true`. **Live** = gate open and all eight rewrite
  rules present in the stored `rewrite_rules`; until an administrator presses "Store the rewrite rules" the plugin
  changes nothing. A stale stored rule with a closed gate answers 404; deactivating the plugin removes its own stored
  rules (`NH_VIEN_Routes::deactivate()`; without it every `/en/...` address answered 200 with the Vietnamese home).
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
  home, breadcrumbs, sitemap exclusions and extra entries); core sitemap arguments without an SEO plugin.
- **Admin** (`class-nh-vien-admin.php`): status table, store-rules button, draft English home page button, pair form
  and table; every action needs `manage_options` and a nonce; every value is escaped. Storing the rules is refused
  while the gate is open and a published *post* has the slug `en` (it would be shadowed by the English home).
- **Package**: `tools/package.sh` builds `dist/nguyen-ha-vi-en-<version>.zip` (the plugin's `.gitattributes` keeps
  `tests/` out); `tools/verify-package.sh` checks it, including independence from the theme and the companion plugin.

## Tests

| What | Where | How |
| --- | --- | --- |
| Offline scripts (PHP only) | `plugins/nguyen-ha-vi-en/tests/*.php` | `for t in plugins/nguyen-ha-vi-en/tests/*.php; do php "$t"; done`; CI runs them on PHP 7.4 to 8.4 |
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

## Next steps (owner's decision)

1. Review the branch; merge into `main` only when the owner says so.
2. Run the staging checklist, then the production procedure of [VI-EN-PLUGIN.md](VI-EN-PLUGIN.md) section 5.
3. After production, watch Search Console (coverage, hreflang, canonical) for a few weeks.
