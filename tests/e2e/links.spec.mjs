/**
 * LINKS: a breadth-first crawl of the site's internal links, starting at the home page.
 *
 * Same origin only, capped at NH_CRAWL_LIMIT (default 300) URLs, and it skips /wp-admin, /wp-login,
 * /feed, /wp-json, search result URLs (?s=), comment reply links and other non-content endpoints. It
 * crawls with plain HTTP (no browser) and parses the markup, so it is fast and exercises exactly what a
 * crawler sees.
 *
 * Checked on the way (each in its own step, all reported together through soft assertions):
 *  1. every internal link answers 200, or redirects once to a 200 (the summary table counts both);
 *  2. link hygiene: no empty href, no "#"-only href, no javascript: href, no nameless link, target=_blank
 *     always has rel=noopener;
 *  3. nothing hidden is linked: no draft/private/scheduled title anywhere, no link to a password-protected URL;
 *  4. one h1, a title and <html lang> on every page; at most one canonical, absolute and self-referential;
 *  5. breadcrumb: Home > ... > current, links work, the current item is not a link, it matches the h1 and the
 *     topic chip of the article;
 *  6. pagination: page 2 exists exactly when page 1 links to it, differs from page 1, and an out-of-range
 *     page is a real 404; every published article is reachable (no orphans).
 *
 * Runs once (desktop project): it does not depend on the viewport.
 */
import { test, expect } from './helpers/fixtures.mjs';
import { ALLOW_PROTECTED_LINKS, BASE_ORIGIN, CRAWL_LIMIT, HIDDEN_TITLES, OWN_BREADCRUMB } from './helpers/env.mjs';

const SKIP_PATH = /^\/(wp-admin|wp-login\.php|wp-json|wp-content|wp-includes|xmlrpc\.php)(\/|$)|(^|\/)feed(\/|$)|\/trackback\/?$|\/embed\/?$|^\/comments\/feed/;
const CONCURRENCY = 4;
const MAX_REDIRECTS = 5;

const decode = (text) =>
  String(text)
    .replace(/&#0*38;|&amp;/gi, '&')
    .replace(/&#0*39;|&apos;/gi, "'")
    .replace(/&quot;|&#0*34;/gi, '"')
    .replace(/&lt;/gi, '<')
    .replace(/&gt;/gi, '>');

const stripTags = (html) => decode(html.replace(/<(script|style)\b[\s\S]*?<\/\1>/gi, ' ').replace(/<[^>]+>/g, ' ')).replace(/\s+/g, ' ').trim();

/** Attributes of a tag as a lower-cased map. */
function attributes(source) {
  const map = {};
  const pattern = /([a-zA-Z_:][-a-zA-Z0-9_:.]*)(?:\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s"'>]+)))?/g;
  let match;
  while ((match = pattern.exec(source))) {
    map[match[1].toLowerCase()] = decode(match[2] ?? match[3] ?? match[4] ?? '');
  }
  return map;
}

/** All <a> elements of a document. */
function anchors(html) {
  const found = [];
  const pattern = /<a\b([^>]*)>([\s\S]*?)<\/a>/gi;
  let match;
  while ((match = pattern.exec(html))) {
    found.push({ attrs: attributes(match[1]), inner: match[2], index: match.index });
  }
  return found;
}

/** Which component of the page a link sits in, judged from the nearest preceding container marker. */
const CONTEXTS = [
  ['<article class="card', 'article card'],
  ['wp-block-latest-posts', 'core Recent Posts widget'],
  ['wp-block-archives', 'core Archives widget'],
  ['wp-block-categories', 'core Categories widget'],
  ['wp-block-latest-comments', 'core Recent Comments widget'],
  ['class="social-links', 'profile link'],
  ['class="post-nav', 'previous/next'],
  ['class="pillar-box', 'topic box'],
  ['class="breadcrumbs', 'breadcrumb'],
  ['class="primary-nav', 'primary navigation'],
  ['class="footer-menu', 'footer menu'],
  ['class="chips', 'chips'],
  ['class="pagination', 'pagination'],
  ['class="site-footer', 'footer'],
];
function contextOf(html, index) {
  const head = html.slice(Math.max(0, index - 4000), index);
  let best = { at: -1, label: 'page content' };
  for (const [marker, label] of CONTEXTS) {
    const at = head.lastIndexOf(marker);
    if (at > best.at) {
      best = { at, label };
    }
  }
  return best.label;
}

/** A link is nameless when nothing a person or a screen reader could read is left in it. */
function hasName(anchor) {
  if (anchor.attrs['aria-hidden'] === 'true') {
    return true; // decorative duplicate (card image link), hidden from assistive technology on purpose
  }
  if ((anchor.attrs['aria-label'] || '').trim() || (anchor.attrs.title || '').trim()) {
    return true;
  }
  if (/<img\b[^>]*\balt=["'][^"']+["']/i.test(anchor.inner)) {
    return true;
  }
  return stripTags(anchor.inner).length > 0;
}

function normalize(href, base) {
  let url;
  try {
    url = new URL(href, base);
  } catch {
    return null;
  }
  if (url.origin !== BASE_ORIGIN) {
    return null;
  }
  url.hash = '';
  return url;
}

function shouldCrawl(url) {
  return !SKIP_PATH.test(url.pathname) && !url.searchParams.has('s') && !url.searchParams.has('replytocom') && !url.searchParams.has('p') && !url.searchParams.has('page_id');
}

/** GET with manual redirect handling: returns the chain and the final response data. */
async function fetchChain(request, start) {
  const hops = [];
  let current = start;
  for (let i = 0; i <= MAX_REDIRECTS; i += 1) {
    const response = await request.get(current, { maxRedirects: 0, failOnStatusCode: false, timeout: 30_000 });
    const status = response.status();
    if (status >= 300 && status < 400 && response.headers().location) {
      hops.push({ from: current, status, to: new URL(response.headers().location, current).href });
      current = hops[hops.length - 1].to;
      continue;
    }
    const type = response.headers()['content-type'] || '';
    const html = /html/i.test(type) ? await response.text() : '';
    return { hops, status, finalUrl: current, type, html };
  }
  return { hops, status: 310, finalUrl: current, type: '', html: '' };
}

test('crawl: internal links, hygiene, hidden content, canonicals, breadcrumbs, pagination @once', async ({ request }, testInfo) => {
  test.setTimeout(240_000);

  // ------------------------------------------------------------------------------------------ crawl
  const pages = new Map(); // final URL -> record
  const seenLinks = new Map(); // requested URL -> { referrers: Set, result }
  const external = new Map();
  let frontier = [new URL('/', BASE_ORIGIN).href];
  const queued = new Set(frontier);
  const hygiene = { empty: [], hashOnly: [], javascript: [], nameless: [], blankWithoutNoopener: [] };
  let capped = false;

  while (frontier.length > 0 && !capped) {
    const next = [];
    const level = frontier.slice();
    let cursor = 0;
    const worker = async () => {
      while (cursor < level.length) {
        const url = level[cursor];
        cursor += 1;
        const chain = await fetchChain(request, url);
        const record = { requested: url, ...chain };
        seenLinks.set(url, { ...(seenLinks.get(url) || { referrers: new Map() }), record });
        if (chain.status !== 200 || !chain.html) {
          continue;
        }
        pages.set(chain.finalUrl, record);
        const origin = chain.finalUrl;
        for (const anchor of anchors(chain.html)) {
          const href = anchor.attrs.href;
          if (href === undefined) {
            continue;
          }
          const label = `${new URL(origin).pathname} <a ${(anchor.attrs.class || '').split(' ')[0] || ''}> "${stripTags(anchor.inner).slice(0, 30)}"`;
          if (href.trim() === '') {
            hygiene.empty.push(label);
            continue;
          }
          if (href.trim() === '#') {
            hygiene.hashOnly.push(label);
            continue;
          }
          if (/^\s*javascript:/i.test(href)) {
            hygiene.javascript.push(label);
            continue;
          }
          if (/^(mailto|tel|sms):/i.test(href) || href.startsWith('#')) {
            continue;
          }
          if (!hasName(anchor)) {
            hygiene.nameless.push(`${label} -> ${href}`);
          }
          if ((anchor.attrs.target || '').toLowerCase() === '_blank' && !/\bnoopener\b/i.test(anchor.attrs.rel || '')) {
            hygiene.blankWithoutNoopener.push(`${label} -> ${href}`);
          }
          const target = normalize(href, origin);
          if (!target) {
            if (/^https?:/i.test(href)) {
              external.set(href, (external.get(href) || 0) + 1);
            }
            continue;
          }
          if (!shouldCrawl(target)) {
            continue;
          }
          const key = target.href;
          const entry = seenLinks.get(key) || { referrers: new Map() };
          const from = new URL(origin).pathname;
          const contexts = entry.referrers.get(from) || new Set();
          contexts.add(anchor.attrs.class?.includes('social-links__link') ? 'profile link' : contextOf(chain.html, anchor.index));
          entry.referrers.set(from, contexts);
          seenLinks.set(key, entry);
          if (!queued.has(key)) {
            if (queued.size >= CRAWL_LIMIT) {
              capped = true;
            } else {
              queued.add(key);
              next.push(key);
            }
          }
        }
      }
    };
    await Promise.all(Array.from({ length: CONCURRENCY }, worker));
    frontier = next;
  }

  const all = [...seenLinks.entries()].filter(([, entry]) => entry.record);
  const describeReferrers = (entry) => [...(entry.referrers || new Map())].slice(0, 6).map(([from, contexts]) => `${from} (${[...contexts].join(', ')})`);
  const allContexts = (entry) => new Set([...(entry.referrers || new Map()).values()].flatMap((set) => [...set]));
  const brokenAll = all.filter(([, entry]) => entry.record.status !== 200).map(([url, entry]) => ({ url, status: entry.record.status, referrers: describeReferrers(entry), onlyProfileLinks: [...allContexts(entry)].every((c) => c === 'profile link') }));
  // A profile link is a URL an author typed into their profile: the theme prints it, it cannot repair it.
  const broken = brokenAll.filter((item) => !item.onlyProfileLinks);
  const brokenProfileLinks = brokenAll.filter((item) => item.onlyProfileLinks);
  const redirected = all.filter(([, entry]) => entry.record.hops.length > 0).map(([url, entry]) => ({ url, hops: entry.record.hops.map((h) => `${h.status} -> ${h.to}`), referrers: describeReferrers(entry) }));

  const summary = {
    crawled: all.length,
    ok: all.length - brokenAll.length,
    broken: broken.length,
    brokenProfileLinks: brokenProfileLinks.length,
    redirected: redirected.length,
    externalLinks: external.size,
    htmlPages: pages.size,
    capped,
    limit: CRAWL_LIMIT,
  };
  console.log(`\n  Link crawl summary\n  +--------------------+--------+\n${Object.entries(summary)
    .map(([key, value]) => `  | ${key.padEnd(18)} | ${String(value).padStart(6)} |`)
    .join('\n')}\n  +--------------------+--------+`);
  await testInfo.attach('crawl-summary', { body: JSON.stringify({ summary, broken, brokenProfileLinks, redirected, external: [...external.keys()] }, null, 2), contentType: 'application/json' });

  expect(all.length, 'the crawl must reach more than the home page').toBeGreaterThan(10);

  // ------------------------------------------------------------------------------------------ 1. status
  await test.step('every internal link answers 200 (or a redirect that ends in 200)', async () => {
    expect.soft(broken, `${broken.length} broken internal link(s)`).toEqual([]);
    if (brokenProfileLinks.length > 0) {
      testInfo.annotations.push({
        type: 'broken-profile-link',
        description: `${brokenProfileLinks.map((item) => `${item.url} (${item.status})`).join(', ')}: a URL typed into an author profile (test data: the admin's "website" is set by wp-cli at install); not counted as a theme failure`,
      });
    }
    const badRedirects = redirected.filter((r) => r.hops.length > 1);
    expect.soft(badRedirects, 'redirect chains longer than one hop').toEqual([]);
    expect.soft(capped, `the crawl hit the ${CRAWL_LIMIT} URL cap: raise NH_CRAWL_LIMIT for a complete check`).toBe(false);
    if (redirected.length > 0) {
      testInfo.annotations.push({ type: 'redirected-links', description: `${redirected.length} internal link(s) redirect: ${redirected.slice(0, 3).map((r) => r.url).join(', ')}` });
    }
  });

  // ------------------------------------------------------------------------------------------ 2. hygiene
  await test.step('link hygiene: no empty, "#"-only or javascript: href; every link has a name; _blank has noopener', async () => {
    expect.soft(hygiene.empty, 'links with an empty href').toEqual([]);
    expect.soft(hygiene.hashOnly, 'links whose href is only "#"').toEqual([]);
    expect.soft(hygiene.javascript, 'links with a javascript: href').toEqual([]);
    expect.soft([...new Set(hygiene.nameless)].slice(0, 10), 'links without any readable name').toEqual([]);
    expect.soft([...new Set(hygiene.blankWithoutNoopener)].slice(0, 10), 'target=_blank links without rel=noopener').toEqual([]);
  });

  // ------------------------------------------------------------------------------------------ 3. hidden content
  await test.step('nothing hidden is exposed: no draft/private/scheduled title, no link to a password-protected URL', async () => {
    const leaks = [];
    const protectedTargets = new Map();
    for (const [url, record] of pages) {
      const text = stripTags(record.html);
      for (const title of HIDDEN_TITLES) {
        if (text.includes(title)) {
          leaks.push(`"${title}" appears on ${new URL(url).pathname}`);
        }
      }
      if (/class=["'][^"']*\bpost-password-form\b/.test(record.html)) {
        const entry = seenLinks.get(record.requested) || seenLinks.get(url);
        const contexts = [...allContexts(entry || {})];
        protectedTargets.set(url, { referrers: describeReferrers(entry || {}), fromTheme: contexts.some((context) => !context.startsWith('core ')) });
      }
    }
    expect.soft(leaks, 'hidden article titles found in public pages').toEqual([]);
    // A link from a core widget (Recent Posts, Archives...) is content the site owner placed in a widget area, not
    // theme output: it is reported, not failed. A link from the theme's own markup (cards, lists, navigation) fails.
    const describe = ([url, { referrers }]) => `${new URL(url).pathname} is password protected and linked from: ${referrers.join('; ')}`;
    const themeLinks = [...protectedTargets].filter(([, target]) => target.fromTheme).map(describe);
    const widgetLinks = [...protectedTargets].filter(([, target]) => !target.fromTheme).map(describe);
    if (widgetLinks.length > 0) {
      testInfo.annotations.push({ type: 'protected-links-in-core-widgets', description: widgetLinks.join(' | ') });
    }
    if (ALLOW_PROTECTED_LINKS) {
      if (themeLinks.length > 0) {
        testInfo.annotations.push({ type: 'protected-links', description: `accepted (NH_ALLOW_PROTECTED_LINKS=1): ${themeLinks.join(' | ')}` });
      }
    } else {
      expect.soft(themeLinks, 'theme output that links to password-protected URLs (WordPress lists them with a "Protected:" title by default; the theme\'s own components never do)').toEqual([]);
    }
  });

  // ------------------------------------------------------------------------------------------ 4. documents
  await test.step('every page has one h1, a title, <html lang>; canonical is single, absolute and self-referential', async () => {
    const problems = [];
    let withCanonical = 0;
    for (const [url, record] of pages) {
      const path = new URL(url).pathname + new URL(url).search;
      const h1 = (record.html.match(/<h1\b/gi) || []).length;
      if (h1 !== 1) problems.push(`${path}: ${h1} h1 elements`);
      if (!/<title>[^<]+<\/title>/i.test(record.html)) problems.push(`${path}: no <title>`);
      if (!/<html\b[^>]*\blang=["'][a-zA-Z-]+["']/i.test(record.html)) problems.push(`${path}: no <html lang>`);
      const canonicals = [...record.html.matchAll(/<link\b[^>]*\brel=["']canonical["'][^>]*>/gi)].map((m) => attributes(m[0].slice(5)).href);
      if (canonicals.length > 1) problems.push(`${path}: ${canonicals.length} canonical links`);
      if (canonicals.length === 1) {
        withCanonical += 1;
        const canonical = canonicals[0];
        if (!/^https?:\/\//i.test(canonical)) problems.push(`${path}: canonical is not absolute (${canonical})`);
        else if (canonical !== url) problems.push(`${path}: canonical (${canonical}) is not self-referential`);
      }
    }
    testInfo.annotations.push({ type: 'documents', description: `${pages.size} pages checked for h1/title/lang; ${withCanonical} carry a canonical link (WordPress prints it on singular pages; archives are the SEO plugin's job)` });
    expect.soft(problems.slice(0, 25), `${problems.length} document problem(s) across ${pages.size} pages`).toEqual([]);
  });

  // ------------------------------------------------------------------------------------------ 5. breadcrumbs
  await test.step('breadcrumb: Home > ... > current; links work; current is plain text and matches the h1', async () => {
    const problems = [];
    let checked = 0;
    for (const [url, record] of pages) {
      const block = record.html.match(/<nav class="breadcrumbs"[\s\S]*?<\/nav>/);
      if (!block) {
        continue;
      }
      checked += 1;
      const path = new URL(url).pathname + new URL(url).search;
      const items = [...block[0].matchAll(/<li class="breadcrumbs__item([^"]*)"[^>]*>([\s\S]*?)<\/li>/g)].map((m) => {
        const link = m[2].match(/<a\b([^>]*)>([\s\S]*?)<\/a>/);
        return { current: /breadcrumbs__item--current/.test(m[1]), href: link ? attributes(link[1]).href : null, name: stripTags(link ? link[2] : m[2]) };
      });
      if (items.length < 2) problems.push(`${path}: fewer than two items`);
      if (items[0] && (items[0].name !== 'Home' || normalize(items[0].href || '', url)?.href !== `${BASE_ORIGIN}/`)) problems.push(`${path}: first item is not "Home" linking to the home page`);
      const last = items[items.length - 1];
      if (last && (!last.current || last.href)) problems.push(`${path}: the last item must be the current page and not a link`);
      if (items.slice(0, -1).some((item) => !item.href)) problems.push(`${path}: an intermediate item is not a link`);
      if (new Set(items.map((item) => item.name)).size !== items.length) problems.push(`${path}: duplicate names in the trail`);
      for (const item of items.slice(0, -1)) {
        const target = item.href ? normalize(item.href, url) : null;
        const known = target ? seenLinks.get(target.href)?.record : null;
        if (target && known && known.status !== 200) problems.push(`${path}: breadcrumb link ${item.href} answers ${known.status}`);
      }
      // With Yoast SEO the trail is Yoast's own list, so its wording and its categories are the plugin's settings:
      // seo-plugins.spec.mjs compares the page with the BreadcrumbList instead.
      if (!OWN_BREADCRUMB) {
        continue;
      }
      // The visible current item names the page: same text as the h1 (not for search, whose h1 is a sentence).
      const h1 = stripTags((record.html.match(/<h1\b[^>]*>([\s\S]*?)<\/h1>/) || [])[1] || '');
      const isProtected = /class=["'][^"']*\bpost-password-form\b/.test(record.html); // core prefixes "Protected:" to the h1
      if (!/[?&]s=/.test(url) && !isProtected && last && last.name !== h1) problems.push(`${path}: current breadcrumb "${last.name}" differs from the h1 "${h1}"`);
      // On an article the last link of the trail is the primary category, which is also the accent chip.
      const chip = record.html.match(/<a class="chip chip--accent" href="([^"]+)"/);
      if (chip && items.length >= 3 && items[items.length - 2].href !== decode(chip[1])) problems.push(`${path}: the topic chip and the last breadcrumb link point to different categories`);
    }
    testInfo.annotations.push({ type: 'breadcrumbs', description: `${checked} breadcrumb trails checked` });
    expect.soft(checked, 'pages with a breadcrumb').toBeGreaterThan(5);
    expect.soft(problems.slice(0, 25), `${problems.length} breadcrumb problem(s) in ${checked} breadcrumbs`).toEqual([]);
  });

  // ------------------------------------------------------------------------------------------ 6. pagination and reachability
  await test.step('pagination: page 2 exists exactly when page 1 links to it, differs from page 1; out-of-range is a 404', async () => {
    const listingRoots = ['/', ...[...pages.keys()].map((u) => new URL(u).pathname).filter((p) => /^\/category\/[^/]+\/$/.test(p))];
    const problems = [];
    let withPagination = 0;
    for (const root of [...new Set(listingRoots)]) {
      const one = pages.get(new URL(root, BASE_ORIGIN).href);
      if (!one) continue;
      const page2Url = new URL(`${root.replace(/\/$/, '')}/page/2/`, BASE_ORIGIN).href;
      const two = await fetchChain(request, page2Url);
      const nav = /<nav class="navigation pagination"/.test(one.html);
      const links2 = anchors(one.html).some((a) => a.attrs.href && a.attrs.href.includes('/page/2/'));
      if (two.status === 200) {
        withPagination += 1;
        if (!nav || !links2) problems.push(`${root}: page 2 exists but page 1 has no pagination link to it`);
        // The article cards of page 2 must not repeat the "latest" list of page 1.
        const cards = (html) => [...html.matchAll(/<article class="card card--post[^"]*"[\s\S]*?<h[23] class="card__title">\s*<a href="([^"]+)"/g)].map((m) => decode(m[1]));
        const latestOnOne = cards(one.html.includes('section--latest') ? one.html.slice(one.html.indexOf('section--latest')) : one.html);
        const repeated = cards(two.html).filter((href) => latestOnOne.includes(href));
        if (repeated.length > 0) problems.push(`${root}: page 2 repeats ${repeated.length} article(s) of page 1 (${repeated[0]})`);
        if (cards(two.html).length === 0) problems.push(`${root}: page 2 lists no articles`);
        // Out of range must be a real 404, not an empty 200.
        const numbers = [...one.html.matchAll(/\/page\/(\d+)\//g)].map((m) => Number(m[1]));
        const far = new URL(`${root.replace(/\/$/, '')}/page/${Math.max(...numbers, 2) + 50}/`, BASE_ORIGIN).href;
        const out = await fetchChain(request, far);
        if (out.status !== 404) problems.push(`${root}: an out-of-range page answers ${out.status} instead of 404`);
      } else if (nav) {
        problems.push(`${root}: page 1 shows pagination but page 2 answers ${two.status}`);
      }
    }
    testInfo.annotations.push({ type: 'pagination', description: `${withPagination} paginating listing(s) checked out of ${new Set(listingRoots).size} (home and category hubs)` });
    expect.soft(withPagination, 'listings that paginate (home and the big hubs)').toBeGreaterThan(0);
    expect.soft(problems, 'pagination problems').toEqual([]);
  });

  await test.step('every published article is reachable from the home page by following links (no orphans)', async () => {
    const response = await request.get('/wp-json/wp/v2/posts?per_page=100&_fields=link,content.protected');
    if (!response.ok()) {
      testInfo.annotations.push({ type: 'orphans', description: 'REST API unavailable: orphan check skipped' });
      return;
    }
    // Password-protected posts are left out on purpose: a site may (and, for the title's sake, should) not link to them.
    const posts = (await response.json()).filter((post) => !(post.content && post.content.protected === true)).map((post) => post.link);
    const reached = new Set([...pages.keys()]);
    const orphans = posts.filter((link) => !reached.has(link));
    testInfo.annotations.push({ type: 'orphans', description: `${posts.length} published articles from the REST API compared with ${pages.size} crawled pages` });
    expect.soft(posts.length, 'published articles known to the REST API').toBeGreaterThan(5);
    expect.soft(capped ? [] : orphans, `${orphans.length} published article(s) not reachable by links`).toEqual([]);
  });
});
