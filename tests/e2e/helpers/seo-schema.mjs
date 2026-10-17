/**
 * Pure helpers (no browser) to judge the structured data a page prints. They are used by
 * seo-plugins.spec.mjs against real Yoast SEO / Rank Math pages, and unit-tested with synthetic graphs in
 * seo-schema-selftest.spec.mjs so that a mistake in the analysis cannot waste a CI run.
 *
 * Vocabulary:
 *  - a "document" is one <script type="application/ld+json"> element;
 *  - a "node" is an object with an @type, found at the top level, inside @graph, or nested;
 *  - a "group" is a family of types that mean the same thing for duplicate detection (Article/BlogPosting/...).
 */

/** Type families checked for duplicates. A node can belong to several (["WebPage","FAQPage"]). */
export const GROUPS = {
  Organization: ['Organization', 'Corporation', 'LocalBusiness', 'NGO', 'OnlineBusiness'],
  WebSite: ['WebSite'],
  WebPage: ['WebPage', 'CollectionPage', 'ItemPage', 'SearchResultsPage', 'AboutPage', 'ContactPage', 'ProfilePage', 'CheckoutPage'],
  BreadcrumbList: ['BreadcrumbList'],
  Article: ['Article', 'BlogPosting', 'NewsArticle', 'TechArticle', 'Report', 'ScholarlyArticle'],
  Person: ['Person'],
  FAQPage: ['FAQPage'],
};

/** Groups that must appear at most once inside a single graph (a second Person is legitimate: author vs. site owner). */
export const SINGLE_PER_GRAPH = ['Organization', 'WebSite', 'WebPage', 'BreadcrumbList', 'Article', 'FAQPage'];

/** Types and properties that mean fabricated trust signals on a site that has no real reviews or offers. */
export const FORBIDDEN_TYPES = ['AggregateRating', 'Review', 'Offer', 'AggregateOffer', 'Rating'];
export const FORBIDDEN_PROPERTIES = ['aggregateRating', 'review', 'reviews', 'offers'];

const asArray = (value) => (Array.isArray(value) ? value : value === undefined || value === null ? [] : [value]);

export const typesOf = (node) => asArray(node && node['@type']).map(String);

export function groupsOf(node) {
  const types = typesOf(node);
  return Object.entries(GROUPS)
    .filter(([, members]) => types.some((type) => members.includes(type)))
    .map(([group]) => group);
}

/** Every object that has an @type, anywhere in a parsed JSON-LD value. */
export function collectNodes(value, found = []) {
  if (Array.isArray(value)) {
    value.forEach((item) => collectNodes(item, found));
  } else if (value && typeof value === 'object') {
    if (value['@type'] !== undefined) {
      found.push(value);
    }
    Object.values(value).forEach((child) => collectNodes(child, found));
  }
  return found;
}

/** Parse the text of every ld+json script. Invalid JSON is reported, never thrown. */
export function parseDocuments(scriptTexts) {
  return scriptTexts.map((text, index) => {
    try {
      const data = JSON.parse(text);
      const graph = data && typeof data === 'object' && !Array.isArray(data) && Array.isArray(data['@graph']);
      return { index, data, isGraph: graph, nodes: collectNodes(data), error: null };
    } catch (error) {
      return { index, data: null, isGraph: false, nodes: [], error: String(error.message).slice(0, 120) };
    }
  });
}

/** Types the companion plugin may print in a script of its own, next to the graph of the SEO plugin. */
export const EXTRA_ONLY_TYPES = ['FAQPage', 'LocalBusiness'];

/** The nodes declared at the top of a document: the document itself, or the members of its @graph. */
function topLevelNodes(doc) {
  const { data } = doc;
  if (Array.isArray(data)) {
    return data.filter((item) => item && typeof item === 'object');
  }
  if (data && typeof data === 'object') {
    return doc.isGraph ? data['@graph'].filter((item) => item && typeof item === 'object') : [data];
  }
  return [];
}

/**
 * True for a script that holds nothing except FAQPage / LocalBusiness nodes: the separate block the companion plugin
 * prints beside an SEO plugin's graph (it never edits that graph). Anything else is a graph of its own.
 */
export function isExtraDocument(doc) {
  if (doc.error) {
    return false;
  }
  const top = topLevelNodes(doc);
  return top.length > 0 && top.every((node) => typesOf(node).length > 0 && typesOf(node).every((type) => EXTRA_ONLY_TYPES.includes(type)));
}

/** Normalise text for "is it visible on the page" comparisons. */
export const squash = (text) => String(text).replace(/\s+/g, ' ').trim().toLowerCase();

/** Look for a forbidden type or property anywhere in the value. */
export function findForbidden(value, path = '$', found = []) {
  if (Array.isArray(value)) {
    value.forEach((item, i) => findForbidden(item, `${path}[${i}]`, found));
  } else if (value && typeof value === 'object') {
    const bad = typesOf(value).filter((type) => FORBIDDEN_TYPES.includes(type));
    if (bad.length > 0) {
      found.push(`${path}: @type ${bad.join('/')}`);
    }
    for (const [key, child] of Object.entries(value)) {
      if (FORBIDDEN_PROPERTIES.includes(key)) {
        found.push(`${path}.${key}`);
      }
      findForbidden(child, `${path}.${key}`, found);
    }
  }
  return found;
}

/**
 * Full analysis of the documents of one page.
 * @returns {{
 *   errors: string[], documentCount: number, graphCount: number, nodeCount: number,
 *   duplicateIds: string[], crossDocumentDuplicates: string[], withinGraphDuplicates: string[],
 *   forbidden: string[], groups: Record<string, number>
 * }}
 */
export function analyze(documents) {
  const errors = documents.filter((doc) => doc.error).map((doc) => `script #${doc.index + 1}: invalid JSON (${doc.error})`);
  const good = documents.filter((doc) => !doc.error);

  // @id may only be declared once on the page. Pure references ({"@id": "..."} with nothing else) are not declarations.
  const declared = new Map();
  for (const doc of good) {
    for (const node of doc.nodes) {
      const id = node['@id'];
      if (typeof id === 'string' && typesOf(node).length > 0) {
        declared.set(id, (declared.get(id) || 0) + 1);
      }
    }
  }
  const duplicateIds = [...declared].filter(([, n]) => n > 1).map(([id, n]) => `${id} declared ${n} times`);

  // The same family in two different scripts = two plugins describing the same thing.
  const seenIn = new Map();
  good.forEach((doc) => {
    const groups = new Set(doc.nodes.flatMap(groupsOf));
    groups.forEach((group) => seenIn.set(group, [...(seenIn.get(group) || []), doc.index + 1]));
  });
  const crossDocumentDuplicates = [...seenIn].filter(([, scripts]) => scripts.length > 1).map(([group, scripts]) => `${group} appears in scripts #${scripts.join(' and #')}`);

  // Inside one graph, a family that must be unique appears more than once.
  const withinGraphDuplicates = [];
  for (const doc of good) {
    const counts = new Map();
    for (const node of doc.nodes) {
      groupsOf(node).forEach((group) => counts.set(group, (counts.get(group) || 0) + 1));
    }
    for (const group of SINGLE_PER_GRAPH) {
      // A node that is only a reference to another node has no @type of its own, so it is never counted here.
      if ((counts.get(group) || 0) > 1) {
        withinGraphDuplicates.push(`script #${doc.index + 1}: ${group} appears ${counts.get(group)} times in one graph`);
      }
    }
  }

  const groups = {};
  good.flatMap((doc) => doc.nodes).forEach((node) => groupsOf(node).forEach((group) => (groups[group] = (groups[group] || 0) + 1)));

  return {
    errors,
    documentCount: documents.length,
    graphCount: good.filter((doc) => doc.isGraph).length,
    nodeCount: good.reduce((sum, doc) => sum + doc.nodes.length, 0),
    duplicateIds,
    crossDocumentDuplicates,
    withinGraphDuplicates,
    forbidden: good.flatMap((doc) => findForbidden(doc.data, `script #${doc.index + 1}`)),
    groups,
  };
}

/** Index of every declared node by @id (used to resolve {"@id": ...} references). */
function indexById(documents) {
  const map = new Map();
  for (const doc of documents.filter((d) => !d.error)) {
    for (const node of doc.nodes) {
      if (typeof node['@id'] === 'string' && typesOf(node).length > 0) {
        map.set(node['@id'], node);
      }
    }
  }
  return map;
}

/** The text of every FAQ question the structured data declares (mainEntity may hold nodes or @id references). */
export function faqQuestions(documents) {
  const byId = indexById(documents);
  const questions = [];
  for (const doc of documents.filter((d) => !d.error)) {
    for (const node of doc.nodes.filter((n) => groupsOf(n).includes('FAQPage'))) {
      for (const entry of asArray(node.mainEntity)) {
        const resolved = entry && entry['@id'] && !entry.name && byId.has(entry['@id']) ? byId.get(entry['@id']) : entry;
        if (resolved && typeof resolved.name === 'string') {
          questions.push(resolved.name);
        }
      }
    }
  }
  return questions;
}

/** Which declared FAQ questions are not visible in the page text. */
export function invisibleFaqQuestions(documents, visibleText) {
  const text = squash(visibleText);
  return faqQuestions(documents).filter((question) => !text.includes(squash(question)));
}

/** The BreadcrumbList items (name and URL) of the first list found, or null. */
export function breadcrumbItems(documents) {
  const byId = indexById(documents);
  for (const doc of documents.filter((d) => !d.error)) {
    const list = doc.nodes.find((node) => groupsOf(node).includes('BreadcrumbList'));
    if (!list) {
      continue;
    }
    const elements = asArray(list.itemListElement)
      .map((element) => (element && element['@id'] && !element.name && !element.item && byId.has(element['@id']) ? byId.get(element['@id']) : element))
      .filter(Boolean)
      .sort((a, b) => Number(a.position) - Number(b.position));
    return elements.map((element) => {
      const item = element.item;
      const resolved = item && typeof item === 'object' ? item : null;
      const url = typeof item === 'string' ? item : resolved ? resolved['@id'] || resolved.url || null : null;
      return { name: String(element.name ?? (resolved && resolved.name) ?? ''), url, position: element.position };
    });
  }
  return null;
}

/**
 * Compare the visible breadcrumb ([{name, href|null}]) with the BreadcrumbList items ([{name, url|null}]).
 * The current page is plain text on screen, while a list may or may not give it a URL: when it does, it
 * must be the page's own URL. A page with no visible breadcrumb (home, 404) may only carry a one item list
 * naming the home page.
 * @returns {string[]} the differences, empty when they agree
 */
export function compareBreadcrumbs(visible, schema, { pageUrl, homeUrl }) {
  const problems = [];
  const same = (a, b) => a.replace(/\/$/, '') === b.replace(/\/$/, '');
  if (visible.length === 0) {
    if (schema.length > 1) {
      problems.push(`the page has no visible breadcrumb but BreadcrumbList declares ${schema.length} items (${schema.map((i) => i.name).join(' > ')})`);
    } else if (schema.length === 1 && schema[0].url && !same(schema[0].url, homeUrl)) {
      problems.push(`the page has no visible breadcrumb and the one-item BreadcrumbList does not point at the home page (${schema[0].url})`);
    }
    return problems;
  }
  if (visible.length !== schema.length) {
    problems.push(`${visible.length} visible item(s) (${visible.map((i) => i.name).join(' > ')}) but ${schema.length} in BreadcrumbList (${schema.map((i) => i.name).join(' > ')})`);
  }
  const count = Math.min(visible.length, schema.length);
  for (let i = 0; i < count; i += 1) {
    const v = visible[i];
    const s = schema[i];
    if (squash(v.name) !== squash(s.name)) {
      problems.push(`item ${i + 1}: visible name "${v.name}" differs from BreadcrumbList name "${s.name}"`);
    }
    const isCurrent = i === visible.length - 1;
    if (!isCurrent) {
      if (!v.href || !s.url || !same(v.href, s.url)) {
        problems.push(`item ${i + 1}: visible URL ${v.href} differs from BreadcrumbList URL ${s.url}`);
      }
    } else if (s.url && pageUrl && !same(s.url, pageUrl)) {
      problems.push(`current item: BreadcrumbList URL ${s.url} is not the page's own URL ${pageUrl}`);
    }
  }
  return problems;
}

/** Runs IN THE PAGE (pass it to page.evaluate): everything judgeSeo needs, as plain data. */
export function collectSeoFacts() {
  return {
    canonicals: [...document.querySelectorAll('link[rel~="canonical"]')].map((el) => el.getAttribute('href')),
    descriptions: document.querySelectorAll('meta[name="description" i]').length,
    ogTitles: document.querySelectorAll('meta[property="og:title" i]').length,
    robots: document.querySelectorAll('meta[name="robots" i]').length,
    scripts: [...document.querySelectorAll('script[type="application/ld+json" i]')].map((el) => el.textContent),
    breadcrumb: [...document.querySelectorAll('nav.breadcrumbs li')].map((li) => {
      const link = li.querySelector('a');
      return { name: li.textContent.trim().replace(/\s+/g, ' '), href: link ? link.href : null };
    }),
    bodyText: document.body.innerText,
    microdata: [...document.body.querySelectorAll('[itemscope], [itemprop], [itemtype], [vocab], [typeof], [property]')].map(
      (el) => `${el.tagName.toLowerCase()}${el.className ? '.' + String(el.className).split(' ')[0] : ''}`
    ),
    pageUrl: location.href.split('#')[0],
  };
}

/**
 * Judge everything the SEO spec checks about one page, from plain data (no browser needed).
 *
 * @param {object} facts   canonicals[], descriptions, ogTitles, robots, scripts[] (ld+json texts),
 *                         breadcrumb[] ({name, href}), bodyText, microdata[], pageUrl
 * @param {object} options mode: 'yoast' | 'rankmath' | 'none'; indexable: boolean; homeUrl: string
 * @returns {{problems: Record<string, string[]>, analysis: ReturnType<typeof analyze>}}
 */
export function judgeSeo(facts, { mode, indexable, homeUrl }) {
  const withPlugin = mode === 'yoast' || mode === 'rankmath';
  const trim = (url) => String(url).replace(/\/$/, '');
  const problems = { canonical: [], head: [], jsonld: [], faq: [], breadcrumb: [], microdata: [] };

  // 1. canonical: at most one, absolute, self-referential; required on indexable pages once a plugin is installed.
  if (facts.canonicals.length > 1) {
    problems.canonical.push(`${facts.canonicals.length} canonical links: ${JSON.stringify(facts.canonicals)}`);
  } else if (facts.canonicals.length === 1) {
    const [canonical] = facts.canonicals;
    if (!/^https?:\/\//i.test(canonical)) {
      problems.canonical.push(`canonical is not absolute: ${canonical}`);
    } else if (trim(canonical) !== trim(facts.pageUrl)) {
      problems.canonical.push(`canonical ${canonical} is not self-referential (page is ${facts.pageUrl})`);
    }
  } else if (indexable && withPlugin) {
    problems.canonical.push(`${mode} printed no canonical on an indexable page`);
  }

  // 2. one meta description, one og:title, one robots
  for (const [label, count] of [
    ['meta description', facts.descriptions],
    ['og:title', facts.ogTitles],
    ['robots meta', facts.robots],
  ]) {
    if (count > 1) {
      problems.head.push(`${count} ${label} tags`);
    }
  }

  // 3. JSON-LD
  const documents = parseDocuments(facts.scripts);
  const analysis = analyze(documents);
  problems.jsonld.push(...analysis.errors);
  if (withPlugin && indexable) {
    // The SEO plugin prints ONE graph. The companion plugin may add ONE more script of its own, but only one that
    // holds nothing except FAQPage / LocalBusiness nodes; the checks below report it when that block repeats
    // something the graph already describes. What matters is that nothing is described twice.
    const graphs = documents.filter((doc) => !doc.error && !isExtraDocument(doc));
    const extras = documents.filter(isExtraDocument);
    if (graphs.length !== 1) {
      problems.jsonld.push(`${mode} must print exactly one JSON-LD graph on an indexable page, found ${graphs.length}${extras.length ? ` (plus ${extras.length} FAQPage/LocalBusiness script(s))` : ''}`);
    }
    if (extras.length > 1) {
      problems.jsonld.push(`${extras.length} extra FAQPage/LocalBusiness scripts (at most one allowed)`);
    }
  } else if (analysis.documentCount > 1) {
    problems.jsonld.push(`${analysis.documentCount} JSON-LD scripts (at most one allowed here)`);
  }
  problems.jsonld.push(...analysis.duplicateIds, ...analysis.crossDocumentDuplicates, ...analysis.withinGraphDuplicates);
  problems.jsonld.push(...analysis.forbidden.map((item) => `forbidden AggregateRating/Review/Offer: ${item}`));
  problems.faq.push(...invisibleFaqQuestions(documents, facts.bodyText).map((question) => `FAQPage declares "${question}" which is not visible on the page`));

  // 4. breadcrumb parity
  const trail = breadcrumbItems(documents);
  if (trail) {
    problems.breadcrumb.push(...compareBreadcrumbs(facts.breadcrumb, trail, { pageUrl: facts.pageUrl, homeUrl }));
  }

  // 5. the theme adds no microdata or RDFa
  if (facts.microdata.length > 0) {
    problems.microdata.push(`microdata/RDFa attributes in the theme markup: ${facts.microdata.slice(0, 8).join(', ')}`);
  }

  return { problems, analysis };
}
