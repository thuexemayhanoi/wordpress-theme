# Browser end-to-end suite (theme only)

Playwright tests that drive the **Nguyen Ha Editorial theme** in a real Chromium against a running
WordPress site. The companion plugin is out of scope (its behaviour is only observed through the page).

The suite is read-only: it never writes to the site (no comments are posted, nothing is created), so it can
run against any seeded copy. It never edits theme code; when it finds a defect the test fails and says where.

## Run it

```bash
# the site must be up (see "The site under test"); the default is http://127.0.0.1:8071
npm run test:e2e                              # everything: 3 viewport projects
npm run test:e2e -- --project=desktop         # one project (mobile 375x812, tablet 768x1024, desktop 1440x900)
npm run test:e2e -- navigation                # one spec, matched by file name
npm run test:e2e -- -g "skip link"            # by test title
npm run test:e2e -- --headed --workers=1      # watch it

node tests/e2e/summarize.mjs                  # table: spec / tests / pass / fail / skipped, then every failure
npx playwright show-report tests/e2e/.artifacts/report
```

Retries are **off** on purpose (`retries: 0`): a test that needs a retry is flaky and must be fixed, not hidden.
Default `workers` is 3 (the PHP built-in server used by `tests/bin/serve.sh` runs 4 workers on a 4 core machine).
A full run takes about 6 to 9 minutes on 4 shared cores, mostly the visual sweep, the axe matrix and the CLS runs.

### The site under test

| Variable | Default | Meaning |
|---|---|---|
| `NH_BASE_URL` | `http://127.0.0.1:8071` | Site to test. It must equal WordPress' own `siteurl`/`home`, otherwise WordPress redirects every request. |
| `NH_E2E_WORKERS` | `3` | Parallel workers. |
| `NH_E2E_ARTIFACTS` | `tests/e2e/.artifacts` | Traces, HTML/JSON reports, `routes.json`. Git-ignored. |
| `NH_E2E_SCREENSHOTS` | `tests/e2e/__screenshots__` | The visual sweep. Git-ignored, never commit it. |
| `NH_SEARCH_TERM` | `scooter` | A word that matches several fixture articles. |
| `NH_HIDDEN_TITLES` | the three titles `seed-fixtures.php` creates | Draft/private/scheduled titles that must never appear publicly. |
| `NH_CRAWL_LIMIT` | `300` | URL cap of the link crawler. |
| `NH_E2E_REQUIRE_ALL` | unset | `1` turns an undiscoverable page type from a skip into a failure. |
| `NH_SEO_PLUGIN` | unset | `yoast`, `rankmath` or `none` enables `seo-plugins.spec.mjs`; anything else skips it. |

Nothing is hard-coded to this machine: no absolute paths, every URL is discovered from the live DOM
(`helpers/discover.mjs`) starting at the home page, and written to `.artifacts/routes.json` by `global-setup.mjs`.
The setup prints every discovered URL and fails fast, with a message, when the site is down or a core page type
(home, rich article, category hub, search, 404) cannot be found.

Optional tool: `pdftotext` (poppler-utils). With it the print test also checks a real PDF; without it that one
test is skipped with a visible reason and the DOM-level print checks still run.

### CI outline (not run in the sandbox this was written in)

```yaml
- run: npm ci
- run: npx playwright install --with-deps chromium     # CI only; the browser is preinstalled in the dev sandbox
- run: tests/bin/setup-site.sh "$WP_DIR" "http://127.0.0.1:8080" wp_e2e   # provisions WordPress, activates theme + plugin
- run: wp --path="$WP_DIR" eval-file tests/bin/seed-fixtures.php           # the same fixtures as the dev site
- run: tests/bin/serve.sh "$WP_DIR" 8080 &
- run: NH_BASE_URL=http://127.0.0.1:8080 npm run test:e2e
- run: node tests/e2e/summarize.mjs --markdown >> "$GITHUB_STEP_SUMMARY"
# a second job, after: wp plugin install wordpress-seo --activate   (or seo-by-rank-math)
- run: NH_SEO_PLUGIN=yoast NH_BASE_URL=http://127.0.0.1:8080 npx playwright test -c tests/e2e/playwright.config.mjs seo-plugins
```

## Projects and tags

Three projects set the viewport. A test that only makes sense on one kind of layout carries a tag in its title
and is *filtered out* of the other projects (it does not show up as "skipped"):

| Tag | Runs on | Because |
|---|---|---|
| `@desktop-only` | desktop | hover, mega menu, wide TOC layout, print |
| `@drawer` | mobile, tablet | the menu button / drawer navigation (<= 960px) |
| `@touch` | mobile | needs a touch device |
| `@mobile-only` | mobile | sets its own viewport width (320, 188, landscape) |
| `@once` | desktop | does not depend on the viewport (crawler, breakpoint sweep, SEO) |
| `@no-tablet` | mobile, desktop | matrix tests (axe, contrast) |

## Specs

| File | What it guards |
|---|---|
| `smoke.spec.mjs` | Every page type: status (the 404 is a real 404), `<html lang>`, exactly one h1, zoomable viewport meta, one banner/main/contentinfo, skip-link target, unique ids, resolving `aria-*` references, theme assets wired; and (through the guard) no console error, no uncaught exception, no failed request, no 4xx/5xx subresource, no third-party request. |
| `layout.spec.mjs` | No horizontal overflow at 375/768/1440 and 320px (WCAG 1.4.10), hostile search phrases, a breakpoint sweep (header height vs the 84px scroll padding, overlaps), sticky header, WCAG 2.4.11 Focus Not Obscured (Tab walk with `elementFromPoint`), image size/dimensions/lazy-loading/LCP priority, CLS over three cold loads (asserted <= 0.1 only when the three values agree within 0.02). |
| `a11y.spec.mjs` | axe-core (wcag2a/2aa/21a/21aa/22aa + best-practice) on every page type in light and dark, dark through the toggle, and the drawer, mega panel, search suggestions, TOC and FAQ states. Plus a contrast audit that decides what axe leaves as "needs review" (gradients, overlaps, footer) from computed colours or rendered pixels. |
| `navigation.spec.mjs` | Skip link, header search panel (aria-expanded, focus, Esc, outside click, mutual exclusion with the nav), desktop mega menu (disclosure buttons, hover intent with a fake clock, keyboard, Esc, focus-out), mobile drawer (open/close, links not covered, sub menus, Esc, focus, scroll behaviour, resize), touch. |
| `behavior.spec.mjs` | Colour mode (aria-pressed, `data-theme`, storage, persistence, **no flash**: `data-theme` is set before `<body>` and before first paint, OS preference live, broken storage), TOC (state per viewport, ids, landing position, current marker, deep links, sticky), FAQ, table scroll region, comment form (read only), one link per card, focus rings (>= 2px, 3:1), hostile stored content. |
| `search.spec.mjs` | Suggestions (3 chars, debounce, count announced, keyboard, aborted/500/403/invalid/empty/slow/hostile answers), submitting, and the results page (phrase, count, `<mark>`, topic chips and filter, pagination, empty state, singular, Vietnamese, XSS probes, hidden content). |
| `no-js.spec.mjs` | JavaScript disabled: noscript stylesheet, header layout, dead JS-only buttons, sub menu reachability, sticky header not swallowing the screen, search forms, native TOC and FAQ, dark mode through CSS. Screenshots in `__screenshots__/nojs`. |
| `media-features.spec.mjs` | `prefers-reduced-motion` (no transition > 1ms on any element or pseudo-element), `forced-colors`, print (chrome hidden, black on white also in dark mode, link URLs, FAQ answers in a real PDF), 200% text, WCAG 1.4.12 text spacing, landscape phone. |
| `links.spec.mjs` | Breadth-first crawl (same origin, capped): status, redirects, empty/`#`/`javascript:` hrefs, nameless links, `target=_blank` rel, hidden titles, links to password-protected URLs, h1/title/lang, canonical, breadcrumb trail, pagination consistency, orphan articles. Prints a summary table. |
| `visual.spec.mjs` | Full-page screenshots page type x viewport x scheme into `__screenshots__/`, interactive states, nav-wrap widths, with probes for overflow, clipped text, overlapping siblings, stretched images. |
| `seo-plugins.spec.mjs` | Skipped unless `NH_SEO_PLUGIN` is set. Canonical, description/og:title/robots counts, one JSON-LD graph, no duplicated ids or entity families, FAQPage visibility, no AggregateRating/Review/Offer, BreadcrumbList vs `nav.breadcrumbs`, no theme microdata. |
| `seo-schema-selftest.spec.mjs` | Unit tests (no browser) of the structured data analysis with synthetic Yoast/Rank Math graphs. Always runs. |

Shared code is in `helpers/`: `env` (all configuration), `fixtures` (the **guard**), `routes` + `discover`
(page types), `ui` (focus walking, overflow and layout probes, trace helpers), `axe`, `contrast`, `seo-schema`.

## The guard

Every test runs under an automatic guard on its browser context. When the test body passed, the guard fails
the test if the page produced a console `error`, an uncaught exception, a failed request, a 4xx/5xx
subresource, a request to any host other than the site, or a theme init warning. Details:

* `net::ERR_ABORTED` is ignored (navigations and the search suggestions' own `AbortController` cancel
  requests by design); HTTP errors are checked separately.
* Gravatar (WordPress core avatars) is answered with a stub, everything else third-party is blocked and
  reported. The suite therefore does not depend on internet access.
* The 404 page logs one console error for its own status; a test opts in with `guard.expectDocument404(url)`.
* A test that breaks something on purpose declares it: `guard.allow({ failed, response, console })`.

## Things worth knowing when you extend it

* `reducedMotion` and `forcedColors` are **not** first-class Playwright Test options: use
  `test.use({ contextOptions: { reducedMotion: 'reduce' } })`, and assert `matchMedia(...)` so a silent no-op
  cannot pass.
* The theme uses `scroll-behavior: smooth`; geometry checks either use instant jumps (`jumpTo`) or wait for the
  scroll to settle (`settleScroll`). Do not read positions right after an anchor click.
* The non-critical stylesheet swaps in through `media="print"` + `onload`; `gotoReady` waits for it. Do not use
  it, or `page.waitForFunction`/`page.addStyleTag`, on JavaScript-disabled pages (they can hang): the no-JS spec
  polls from Node instead.
* New page type: add it to `PAGE_TYPES` (`helpers/routes.mjs`) and teach `helpers/discover.mjs` how to find it.
* axe exclusions live only in `helpers/axe.mjs` (`KNOWN_FALSE_POSITIVES`, empty) and need evidence.
* The `admin` user's profile "website" is set by wp-cli at install and points at a non-existent path on a
  seeded site. The crawler reports it ("broken-profile-link") but does not fail on it: it is data an author
  typed, not a theme link. Clearing it in `seed-fixtures.php` (`wp user update admin --user_url=`) removes the note.
