/**
 * SEO PLUGIN COMPATIBILITY: the theme prints no SEO markup, so whatever the SEO plugin (or the companion
 * plugin) prints must be the only thing on the page, and must agree with what the theme shows.
 *
 * SKIPPED unless NH_SEO_PLUGIN is "yoast", "rankmath" or "none". CI installs the real plugin from
 * wordpress.org and sets the variable; a sandbox without access to wordpress.org simply skips this file.
 *
 *   yoast | rankmath   the plugin owns head markup and structured data
 *   none               no SEO plugin; the companion plugin (when active) may print its own graph
 *
 * For home, a single article, a category, search and 404 it asserts:
 *  1. at most one <link rel="canonical">, absolute and self-referential (present on indexable pages when a
 *     plugin is installed);
 *  2. at most one meta description, one og:title and one robots meta;
 *  3. JSON-LD: every script parses; with a plugin there is exactly ONE graph/script on indexable pages (home,
 *     article, category) and at most one on search and 404, where plugins differ about printing any; with
 *     "none" at most one; no @id declared twice; no Organization/WebSite/WebPage/BreadcrumbList/Article/
 *     Person/FAQPage family in two scripts, and none of the unique ones twice in a graph (a second Person is
 *     legitimate: author and site owner); FAQPage only when its questions are visible on the page; no
 *     AggregateRating, Review or Offer anywhere;
 *  4. the visible breadcrumb (nav.breadcrumbs) equals the BreadcrumbList names and URLs when one exists;
 *  5. the theme adds no microdata or RDFa of its own (no itemscope/itemprop/itemtype/vocab/typeof/property
 *     in <body>).
 *
 * The judgement itself is a pure function (helpers/seo-schema.mjs, judgeSeo) that is unit-tested with
 * synthetic Yoast-like and Rank Math-like graphs in seo-schema-selftest.spec.mjs; this file only collects
 * the facts from the page and reports every aspect, not just the first failure.
 */
import { test, expect, requireRoute } from './helpers/fixtures.mjs';
import { BASE_ORIGIN } from './helpers/env.mjs';
import { gotoReady } from './helpers/ui.mjs';
import { collectSeoFacts, judgeSeo } from './helpers/seo-schema.mjs';

const MODE = process.env.NH_SEO_PLUGIN;
const MODES = ['yoast', 'rankmath', 'none'];

/** Pages and whether search engines are meant to index them. */
const PAGES = [
  { key: 'home', indexable: true },
  { key: 'singleRich', indexable: true },
  { key: 'categoryHub', indexable: true },
  { key: 'search', indexable: false },
  { key: 'notFound', indexable: false, status: 404 },
];

test.describe(`SEO plugin compatibility (NH_SEO_PLUGIN=${MODE || 'unset'}) @once`, () => {
  test.skip(!MODES.includes(MODE), 'NH_SEO_PLUGIN is not set to yoast, rankmath or none: SEO plugin compatibility is not checked on this site');

  for (const { key, indexable, status } of PAGES) {
    test(`${key}: head tags, one JSON-LD graph, FAQ and breadcrumb parity, no theme microdata`, async ({ page, routes, guard }, testInfo) => {
      const url = requireRoute(test, routes, key);
      const response = await gotoReady(page, url);
      expect(response.status(), `HTTP status of ${url}`).toBe(status ?? 200);
      if (status === 404) {
        guard.expectDocument404(page.url());
      }

      const facts = await page.evaluate(collectSeoFacts);
      const { problems, analysis } = judgeSeo(facts, { mode: MODE, indexable, homeUrl: `${BASE_ORIGIN}/` });
      testInfo.annotations.push({
        type: 'seo-facts',
        description: `${analysis.documentCount} JSON-LD script(s), ${analysis.nodeCount} node(s); canonical ${facts.canonicals.length}; description ${facts.descriptions}; og:title ${facts.ogTitles}; robots ${facts.robots}; breadcrumb ${facts.breadcrumb.length} item(s)`,
      });

      expect.soft(problems.canonical, '1. canonical link').toEqual([]);
      expect.soft(problems.head, '2. description / og:title / robots meta tags').toEqual([]);
      expect.soft(problems.jsonld, '3. JSON-LD structured data').toEqual([]);
      expect.soft(problems.faq, '3. FAQPage only when the questions are visible').toEqual([]);
      expect.soft(problems.breadcrumb, '4. visible breadcrumb equals BreadcrumbList').toEqual([]);
      expect.soft(problems.microdata, '5. the theme adds no microdata/RDFa').toEqual([]);

      guard.assertClean();
    });
  }
});
