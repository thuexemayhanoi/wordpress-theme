/**
 * SELF-TEST of the structured data analysis used by seo-plugins.spec.mjs.
 *
 * The real Yoast SEO and Rank Math plugins can only run in CI (this sandbox cannot reach wordpress.org),
 * so the logic that judges their output is proven here with synthetic graphs shaped like theirs, including
 * deliberately broken ones. No browser, no site: it always runs and takes milliseconds.
 */
import { test, expect } from '@playwright/test';
import {
  analyze,
  breadcrumbItems,
  compareBreadcrumbs,
  faqQuestions,
  invisibleFaqQuestions,
  judgeSeo,
  parseDocuments,
} from './helpers/seo-schema.mjs';

const SITE = 'https://example.test';
const doc = (...objects) => parseDocuments(objects.map((o) => (typeof o === 'string' ? o : JSON.stringify(o))));

/** A graph shaped like Yoast SEO's output for a post. */
const yoastPost = () => ({
  '@context': 'https://schema.org',
  '@graph': [
    { '@type': 'Article', '@id': `${SITE}/p/#article`, isPartOf: { '@id': `${SITE}/p/#webpage` }, author: { '@id': `${SITE}/#/schema/person/1` }, headline: 'Title', publisher: { '@id': `${SITE}/#organization` } },
    { '@type': 'WebPage', '@id': `${SITE}/p/#webpage`, url: `${SITE}/p/`, breadcrumb: { '@id': `${SITE}/p/#breadcrumb` } },
    {
      '@type': 'BreadcrumbList',
      '@id': `${SITE}/p/#breadcrumb`,
      itemListElement: [
        { '@type': 'ListItem', position: 1, name: 'Home', item: `${SITE}/` },
        { '@type': 'ListItem', position: 2, name: 'Scooters', item: `${SITE}/category/scooters/` },
        { '@type': 'ListItem', position: 3, name: 'Title' },
      ],
    },
    { '@type': 'WebSite', '@id': `${SITE}/#website`, url: `${SITE}/` },
    { '@type': 'Organization', '@id': `${SITE}/#organization`, name: 'Example' },
    { '@type': 'Person', '@id': `${SITE}/#/schema/person/1`, name: 'Jane' },
    { '@type': 'ImageObject', '@id': `${SITE}/p/#primaryimage`, url: `${SITE}/i.jpg` },
  ],
});

/** A graph shaped like Rank Math's: BlogPosting, the site owner as Person+Organization, WebPage with a breadcrumb. */
const rankMathPost = () => ({
  '@context': 'https://schema.org',
  '@graph': [
    { '@type': ['Person', 'Organization'], '@id': `${SITE}/#person`, name: 'Example' },
    { '@type': 'WebSite', '@id': `${SITE}/#website`, url: `${SITE}/`, publisher: { '@id': `${SITE}/#person` } },
    { '@type': 'WebPage', '@id': `${SITE}/p/#webpage`, url: `${SITE}/p/` },
    { '@type': 'Person', '@id': `${SITE}/author/jane/`, name: 'Jane' },
    { '@type': 'BlogPosting', '@id': `${SITE}/p/#richSnippet`, headline: 'Title', author: { '@id': `${SITE}/author/jane/` }, isPartOf: { '@id': `${SITE}/p/#webpage` } },
    { '@type': 'BreadcrumbList', '@id': `${SITE}/p/#breadcrumb`, itemListElement: [{ '@type': 'ListItem', position: 1, item: { '@id': `${SITE}/`, name: 'Home' } }] },
  ],
});

test.describe('structured data analysis @once', () => {
  test('a Yoast-shaped graph in one script is clean', () => {
    const result = analyze(doc(yoastPost()));
    expect(result.errors).toEqual([]);
    expect(result.documentCount).toBe(1);
    expect(result.graphCount).toBe(1);
    expect(result.duplicateIds).toEqual([]);
    expect(result.crossDocumentDuplicates).toEqual([]);
    expect(result.withinGraphDuplicates).toEqual([]);
    expect(result.forbidden).toEqual([]);
    expect(result.groups).toMatchObject({ Article: 1, WebPage: 1, BreadcrumbList: 1, WebSite: 1, Organization: 1, Person: 1 });
  });

  test('a Rank Math-shaped graph is clean (a Person that is also an Organization counts once per family)', () => {
    const result = analyze(doc(rankMathPost()));
    expect(result.duplicateIds).toEqual([]);
    expect(result.crossDocumentDuplicates).toEqual([]);
    expect(result.withinGraphDuplicates).toEqual([]);
    expect(result.groups.Person).toBe(2); // site owner and author: legitimate
    expect(result.groups.Organization).toBe(1);
  });

  test('two scripts that both describe an Organization or a WebPage are reported', () => {
    const a = { '@context': 'https://schema.org', '@graph': [{ '@type': 'Organization', '@id': `${SITE}/#o1` }, { '@type': 'WebPage', '@id': `${SITE}/#w1` }] };
    const b = { '@context': 'https://schema.org', '@type': 'Organization', '@id': `${SITE}/#o2` };
    const result = analyze(doc(a, b));
    expect(result.documentCount).toBe(2);
    expect(result.crossDocumentDuplicates).toEqual(['Organization appears in scripts #1 and #2']);
  });

  test('the same @id declared twice is reported, a pure reference is not a declaration', () => {
    const graph = {
      '@graph': [
        { '@type': 'WebSite', '@id': `${SITE}/#website` },
        { '@type': 'WebPage', '@id': `${SITE}/#website` },
        { '@type': 'Article', isPartOf: { '@id': `${SITE}/#website` } },
      ],
    };
    expect(analyze(doc(graph)).duplicateIds).toEqual([`${SITE}/#website declared 2 times`]);
  });

  test('two WebPage or two Organization nodes in one graph are reported, two Person nodes are not', () => {
    const graph = {
      '@graph': [
        { '@type': 'WebPage', '@id': '#a' },
        { '@type': 'CollectionPage', '@id': '#b' },
        { '@type': 'Person', '@id': '#p1' },
        { '@type': 'Person', '@id': '#p2' },
        { '@type': 'Organization', '@id': '#o1' },
        { '@type': 'LocalBusiness', '@id': '#o2' },
      ],
    };
    expect(analyze(doc(graph)).withinGraphDuplicates).toEqual(['script #1: Organization appears 2 times in one graph', 'script #1: WebPage appears 2 times in one graph']);
  });

  test('fabricated trust signals are found wherever they hide', () => {
    const graph = {
      '@graph': [
        { '@type': 'Article', headline: 'T', aggregateRating: { '@type': 'AggregateRating', ratingValue: 5, reviewCount: 99 } },
        { '@type': 'WebPage', review: [{ '@type': 'Review', reviewBody: 'great' }] },
        { '@type': 'Product', offers: { '@type': 'Offer', price: '1' } },
      ],
    };
    const found = analyze(doc(graph)).forbidden.join('\n');
    expect(found).toContain('aggregateRating');
    expect(found).toContain('@type AggregateRating');
    expect(found).toContain('review');
    expect(found).toContain('@type Review');
    expect(found).toContain('offers');
    expect(found).toContain('@type Offer');
    expect(analyze(doc(yoastPost())).forbidden).toEqual([]);
  });

  test('invalid JSON is reported, not thrown', () => {
    const result = analyze(doc('{"@type": "WebPage",', yoastPost()));
    expect(result.errors).toHaveLength(1);
    expect(result.errors[0]).toContain('script #1: invalid JSON');
    expect(result.documentCount).toBe(2);
  });

  test('FAQ questions are read from nodes and from @id references and checked against the visible text', () => {
    const inline = {
      '@graph': [
        {
          '@type': 'FAQPage',
          mainEntity: [
            { '@type': 'Question', name: 'Can I rent a scooter?', acceptedAnswer: { '@type': 'Answer', text: 'Yes' } },
            { '@type': 'Question', name: 'Is a helmet included?', acceptedAnswer: { '@type': 'Answer', text: 'Ask' } },
          ],
        },
      ],
    };
    expect(faqQuestions(doc(inline))).toEqual(['Can I rent a scooter?', 'Is a helmet included?']);
    expect(invisibleFaqQuestions(doc(inline), 'Frequently asked   questions\nCan I rent a scooter?\nIs a HELMET included?')).toEqual([]);
    expect(invisibleFaqQuestions(doc(inline), 'Can I rent a scooter?')).toEqual(['Is a helmet included?']);

    const referenced = {
      '@graph': [
        { '@type': 'FAQPage', '@id': '#faq', mainEntity: [{ '@id': '#q1' }] },
        { '@type': 'Question', '@id': '#q1', name: 'Referenced question?' },
      ],
    };
    expect(faqQuestions(doc(referenced))).toEqual(['Referenced question?']);
    expect(invisibleFaqQuestions(doc(yoastPost()), 'anything')).toEqual([]); // no FAQPage, nothing to check
  });

  test('BreadcrumbList items are read in position order and resolve item objects', () => {
    expect(breadcrumbItems(doc(yoastPost()))).toEqual([
      { name: 'Home', url: `${SITE}/`, position: 1 },
      { name: 'Scooters', url: `${SITE}/category/scooters/`, position: 2 },
      { name: 'Title', url: null, position: 3 },
    ]);
    expect(breadcrumbItems(doc(rankMathPost()))).toEqual([{ name: 'Home', url: `${SITE}/`, position: 1 }]);
    expect(breadcrumbItems(doc({ '@type': 'WebPage' }))).toBeNull();
    const shuffled = { '@type': 'BreadcrumbList', itemListElement: [{ position: 2, name: 'B', item: '/b/' }, { position: 1, name: 'A', item: '/a/' }] };
    expect(breadcrumbItems(doc(shuffled)).map((i) => i.name)).toEqual(['A', 'B']);
  });

  test('breadcrumb comparison accepts a matching trail and names every difference', () => {
    const page = `${SITE}/p/`;
    const visible = [
      { name: 'Home', href: `${SITE}/` },
      { name: 'Scooters', href: `${SITE}/category/scooters/` },
      { name: 'Title', href: null },
    ];
    const schema = breadcrumbItems(doc(yoastPost()));
    const options = { pageUrl: page, homeUrl: `${SITE}/` };
    expect(compareBreadcrumbs(visible, schema, options)).toEqual([]);

    // a current item that also carries the page's own URL is fine, trailing slashes do not matter
    const withUrl = schema.map((item, i) => (i === 2 ? { ...item, url: `${SITE}/p` } : item));
    expect(compareBreadcrumbs(visible, withUrl, options)).toEqual([]);

    expect(compareBreadcrumbs(visible, schema.map((i, n) => (n === 1 ? { ...i, name: 'Bikes' } : i)), options).join('\n')).toContain('visible name "Scooters" differs from BreadcrumbList name "Bikes"');
    expect(compareBreadcrumbs(visible, schema.map((i, n) => (n === 1 ? { ...i, url: `${SITE}/elsewhere/` } : i)), options).join('\n')).toContain('visible URL');
    expect(compareBreadcrumbs(visible, schema.slice(0, 2), options).join('\n')).toContain('3 visible item(s)');
    expect(compareBreadcrumbs(visible, schema.map((i, n) => (n === 2 ? { ...i, url: `${SITE}/other/` } : i)), options).join('\n')).toContain("not the page's own URL");
  });

  test.describe('the per-page judgement', () => {
    const goodFacts = (overrides = {}) => ({
      canonicals: [`${SITE}/p/`],
      descriptions: 1,
      ogTitles: 1,
      robots: 1,
      scripts: [JSON.stringify(yoastPost())],
      breadcrumb: [
        { name: 'Home', href: `${SITE}/` },
        { name: 'Scooters', href: `${SITE}/category/scooters/` },
        { name: 'Title', href: null },
      ],
      bodyText: 'Title and some text',
      microdata: [],
      pageUrl: `${SITE}/p/`,
      ...overrides,
    });
    const judge = (facts, options = {}) => judgeSeo(facts, { mode: 'yoast', indexable: true, homeUrl: `${SITE}/`, ...options }).problems;
    const allClear = { canonical: [], head: [], jsonld: [], faq: [], breadcrumb: [], microdata: [] };

    test('a correct Yoast or Rank Math page has no problems', () => {
      expect(judge(goodFacts())).toEqual(allClear);
      // Rank Math-shaped graph on the home page: no visible breadcrumb, a one item list naming Home
      expect(judge(goodFacts({ scripts: [JSON.stringify(rankMathPost())], breadcrumb: [], pageUrl: `${SITE}/`, canonicals: [`${SITE}/`] }), { mode: 'rankmath' })).toEqual(allClear);
      // a single script that is not a @graph is acceptable ("or exactly one JSON-LD script")
      expect(judge(goodFacts({ scripts: [JSON.stringify({ '@context': 'https://schema.org', '@type': 'WebPage', '@id': `${SITE}/p/#webpage` })], breadcrumb: [] })).jsonld).toEqual([]);
    });

    test('canonical: required on indexable pages with a plugin, never duplicated, always absolute and self-referential', () => {
      expect(judge(goodFacts({ canonicals: [] })).canonical).toHaveLength(1);
      expect(judge(goodFacts({ canonicals: [] }), { mode: 'none' }).canonical).toEqual([]);
      expect(judge(goodFacts({ canonicals: [] }), { indexable: false }).canonical).toEqual([]);
      expect(judge(goodFacts({ canonicals: [`${SITE}/p/`, `${SITE}/p/`] })).canonical[0]).toContain('2 canonical links');
      expect(judge(goodFacts({ canonicals: ['/p/'] })).canonical[0]).toContain('not absolute');
      expect(judge(goodFacts({ canonicals: [`${SITE}/other/`] })).canonical[0]).toContain('not self-referential');
      expect(judge(goodFacts({ canonicals: [`${SITE}/p`] })).canonical, 'a trailing slash difference is not a mismatch').toEqual([]);
    });

    test('head tags: one description, one og:title, one robots', () => {
      expect(judge(goodFacts({ descriptions: 0, ogTitles: 0, robots: 0 })).head).toEqual([]);
      expect(judge(goodFacts({ descriptions: 2 })).head).toEqual(['2 meta description tags']);
      expect(judge(goodFacts({ ogTitles: 2 })).head).toEqual(['2 og:title tags']);
      expect(judge(goodFacts({ robots: 3 })).head).toEqual(['3 robots meta tags']);
    });

    test('JSON-LD count: exactly one graph with a plugin on indexable pages, at most one script elsewhere', () => {
      expect(judge(goodFacts({ scripts: [] })).jsonld[0]).toContain('exactly one JSON-LD graph');
      expect(judge(goodFacts({ scripts: [JSON.stringify(yoastPost()), JSON.stringify({ '@type': 'Thing', '@id': '#x' })] })).jsonld.join(' ')).toContain('found 2');
      expect(judge(goodFacts({ scripts: [] }), { indexable: false }).jsonld, 'a plugin may print nothing on search/404').toEqual([]);
      expect(judge(goodFacts({ scripts: [JSON.stringify(yoastPost())] }), { indexable: false }).jsonld).toEqual([]);
      expect(judge(goodFacts({ scripts: [JSON.stringify(yoastPost()), JSON.stringify({ '@type': 'Thing', '@id': '#x' })] }), { indexable: false }).jsonld[0]).toContain('at most one');
      expect(judge(goodFacts({ scripts: [] }), { mode: 'none' }).jsonld).toEqual([]);
      expect(judge(goodFacts({ scripts: [JSON.stringify({ '@type': 'Thing', '@id': '#a' }), JSON.stringify({ '@type': 'Thing', '@id': '#b' })] }), { mode: 'none' }).jsonld[0]).toContain('at most one');
    });

    test('the companion plugin may add one script of its own that holds only FAQPage / LocalBusiness', () => {
      const faqBlock = (id = `${SITE}/p/#faq`) => ({ '@context': 'https://schema.org', '@type': 'FAQPage', '@id': id, mainEntity: [{ '@type': 'Question', name: 'Is a helmet included?' }, { '@type': 'Question', name: 'Can I pay by card?' }] });
      const visible = 'Is a helmet included? Can I pay by card?';

      // The SEO plugin's graph plus a separate FAQPage block is the design: nothing is described twice.
      expect(judge(goodFacts({ scripts: [JSON.stringify(yoastPost()), JSON.stringify(faqBlock())], bodyText: visible }))).toEqual(allClear);
      // The same block as a @graph holding only a FAQPage is the same thing.
      expect(judge(goodFacts({ scripts: [JSON.stringify(yoastPost()), JSON.stringify({ '@context': 'https://schema.org', '@graph': [faqBlock()] })], bodyText: visible })).jsonld).toEqual([]);

      // A second script that describes anything else is a second graph.
      const secondGraph = JSON.stringify({ '@context': 'https://schema.org', '@graph': [{ '@type': 'FAQPage', '@id': '#f' }, { '@type': 'Organization', '@id': '#o' }] });
      expect(judge(goodFacts({ scripts: [JSON.stringify(yoastPost()), secondGraph] })).jsonld.join(' ')).toContain('found 2');
      // Two extra blocks are one too many, and a FAQPage the graph already holds must not be printed again.
      expect(judge(goodFacts({ scripts: [JSON.stringify(yoastPost()), JSON.stringify(faqBlock('#a')), JSON.stringify(faqBlock('#b'))], bodyText: visible })).jsonld.join(' ')).toContain('at most one allowed');
      const graphWithFaq = yoastPost();
      graphWithFaq['@graph'].push({ '@type': 'FAQPage', '@id': `${SITE}/p/#faq-in-graph`, mainEntity: [{ '@type': 'Question', name: 'Is a helmet included?' }] });
      expect(judge(goodFacts({ scripts: [JSON.stringify(graphWithFaq), JSON.stringify(faqBlock())], bodyText: visible })).jsonld.join(' ')).toContain('FAQPage appears in scripts #1 and #2');
      // Without an SEO plugin the companion plugin prints one graph, and a second script is still one too many.
      expect(judge(goodFacts({ scripts: [JSON.stringify(yoastPost()), JSON.stringify(faqBlock())], bodyText: visible }), { mode: 'none' }).jsonld[0]).toContain('at most one');
      // On a page that is not indexable nothing extra is expected either.
      expect(judge(goodFacts({ scripts: [JSON.stringify(yoastPost()), JSON.stringify(faqBlock())], bodyText: visible }), { indexable: false }).jsonld[0]).toContain('at most one');
    });

    test('JSON-LD content: duplicates, forbidden trust signals and broken JSON are reported', () => {
      const twoOrganizations = [JSON.stringify({ '@graph': [{ '@type': 'Organization', '@id': '#a' }] }), JSON.stringify({ '@type': 'Organization', '@id': '#b' })];
      expect(judge(goodFacts({ scripts: twoOrganizations }), { mode: 'none' }).jsonld.join(' ')).toContain('Organization appears in scripts #1 and #2');
      const withRating = yoastPost();
      withRating['@graph'][0].aggregateRating = { '@type': 'AggregateRating', ratingValue: 5 };
      expect(judge(goodFacts({ scripts: [JSON.stringify(withRating)] })).jsonld.join(' ')).toContain('forbidden AggregateRating/Review/Offer');
      expect(judge(goodFacts({ scripts: ['{broken'] })).jsonld.join(' ')).toContain('invalid JSON');
    });

    test('FAQPage must be visible; the breadcrumb must agree; the theme must print no microdata', () => {
      const withFaq = yoastPost();
      withFaq['@graph'].push({ '@type': 'FAQPage', '@id': `${SITE}/p/#faq`, mainEntity: [{ '@type': 'Question', name: 'Is a helmet included?' }] });
      withFaq['@graph'][1]['@type'] = 'WebPage';
      expect(judge(goodFacts({ scripts: [JSON.stringify(withFaq)], bodyText: 'Frequently asked questions. Is a helmet included? Ask.' })).faq).toEqual([]);
      expect(judge(goodFacts({ scripts: [JSON.stringify(withFaq)], bodyText: 'No FAQ on this page.' })).faq[0]).toContain('Is a helmet included?');

      expect(judge(goodFacts({ breadcrumb: [{ name: 'Home', href: `${SITE}/` }, { name: 'Bikes', href: `${SITE}/category/scooters/` }, { name: 'Title', href: null }] })).breadcrumb[0]).toContain('differs');
      expect(judge(goodFacts({ microdata: ['div.card', 'span'] })).microdata[0]).toContain('div.card');
    });
  });

  test('a page without a visible breadcrumb may only declare a one item list for the home page', () => {
    const options = { pageUrl: `${SITE}/`, homeUrl: `${SITE}/` };
    expect(compareBreadcrumbs([], [{ name: 'Home', url: `${SITE}/` }], options)).toEqual([]);
    expect(compareBreadcrumbs([], [], options)).toEqual([]);
    expect(compareBreadcrumbs([], [{ name: 'Home', url: `${SITE}/` }, { name: '404', url: null }], options).join('\n')).toContain('no visible breadcrumb');
    expect(compareBreadcrumbs([], [{ name: 'Somewhere', url: `${SITE}/x/` }], options).join('\n')).toContain('does not point at the home page');
  });
});
