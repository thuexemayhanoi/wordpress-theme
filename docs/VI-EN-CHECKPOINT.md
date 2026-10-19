# VI-EN native multilingual: checkpoint

Working notes so that any session can continue the VI-EN plugin (`plugins/nguyen-ha-vi-en/`) without redoing
finished work. Development only: nothing here is authorised for production, and nothing is merged into `main`
without the owner's decision.

## Rules that stay in force

- The ~500 Vietnamese posts are never edited, deleted, re-slugged, redirected or re-canonicalised, and their SEO
  fields are never touched.
- No Polylang, WPML or any other multilingual plugin; no paid AI API; no automatic translation; no English post is
  published automatically.
- The plugin works on its own (no dependency on the theme) and does not override Yoast SEO or add a second sitemap.
- Own branches only (`feature/...` so that Theme QA runs); no force-push; never push onto another session's branch.
- A step is not reported as finished while Theme QA has a red job.

## Branches and commits

| Branch | Head | Note |
| --- | --- | --- |
| `main` | `8b173d5` | theme 2.0.1; no VI-EN code |
| `feature/vi-en-core-step2-20261019` | `81a4483` | step 2, inert scaffold |
| `feature/chat-vi-en-links-step3-20261019` | `4037673` | step 3, manual VI/EN pairs |
| `feature/chat-vi-en-routes-step4-20261019` | `44ed3d5` | step 4 by another session; Theme QA run 39268633324 is red (three PHPUnit jobs and the release gate) |
| `feature/vi-en-step4-ci-fix-20261019` | `0a4cf13` plus this note | step 4 plus the fixes below; `0a4cf13` is a direct child of `44ed3d5`, so it fast-forwards onto the step-4 branch |

## Why step 4 was red, and what fixed it

1. **PHPCS** (`Static checks, build and packages`): WPCS findings in the plugin and its CLI test scripts. Production
   code was brought to zero findings; `plugins/nguyen-ha-vi-en/tests/*` is excluded from PHPCS in `phpcs.xml.dist`
   (standalone CLI scripts with WordPress stubs, not runtime code).
2. **A corrupted file**: a scripted text replacement expanded `$'` in a JavaScript replacement string and spliced a
   second copy of `class-nh-vien-routes.php` into itself (PHP parse error on line 18). Restored in `44ed3d5`.
3. **PHPUnit theme suites and the PHP 7.4 job**: `NH_Test_Theme_Source::
   test_no_php_file_of_the_repository_runs_when_requested_directly` listed `tests/links.php`, `routes.php` and
   `smoke.php` as files that would run when requested over the web. Fixed by the CLI-only guard
   (`if ( 'cli' !== PHP_SAPI ) { exit; }`) at the top of every test script.
4. **A functional bug**: `NH_VIEN_Plugin::boot()` registered the `/en/{slug}/` rewrite rule on the front end only.
   A flush from Settings > Permalinks (an admin request) therefore left the rule out of the stored rules. The rule is
   now registered on every request when the preview is enabled; `tests/boot.php` pins this.
5. **No CI coverage**: the plugin's offline tests did not run in CI. They now run in the PHP 7.4 to 8.4 matrix.

## What is verified, and what is not

Verified: Theme QA run 39269384059 on `0a4cf13` has 20 of 20 jobs green: PHPCS (WordPress, WordPress-Docs,
PHPCompatibilityWP 7.4+) with zero findings on the whole repository; PHP syntax and the four offline scripts in
`plugins/nguyen-ha-vi-en/tests/` on PHP 7.4, 8.0, 8.1, 8.2, 8.3 and 8.4; PHPUnit on WordPress 7.1.3 and latest and on
PHP 7.4; browser tests at three widths; the Yoast, Rank Math and no-plugin comparisons; the release gate. The plugin
is not activated in any of those sites, so they show only that the existing theme and companion plugin are unaffected.

Not verified yet (do not claim otherwise):

- Behaviour inside a real WordPress: rewrite flush from the admin and from WP-CLI, the real 404 and redirect
  behaviour of `/en/{slug}/`, interaction with a real `/en/` page. The offline scripts use WordPress stubs.
- Yoast SEO: canonical, hreflang and sitemap are not touched yet.
- A staging site: none was available to these sessions.

Known limitation of the preview route (to be solved in piece C, with the duplicate-content work): for a verified
`/en/{slug}/` request the plugin switches core's canonical redirect off completely, so `/en/{slug}` without the
trailing slash, or on another host or scheme, answers 200 instead of redirecting to the one canonical address. The
native address of an English post also still answers 200.

## Next pieces (only after the owner types DUYỆT)

- B: English URLs beyond single posts (home, categories, pagination, search), language switcher on those pages,
  breadcrumbs and internal links per language.
- C: Yoast: `/sitemap_index.xml` entries for valid English URLs, separate canonical, two-way hreflang, the
  native URL of English posts (duplicate content), language-aware caching.
- D: WordPress integration tests (own PHPUnit suite and bootstrap), Yoast tests, browser tests at three widths,
  XSS/CSRF checks, an independent plugin ZIP, staging checklist, comparison with the base branch.
