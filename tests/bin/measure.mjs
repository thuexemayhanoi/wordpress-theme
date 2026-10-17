#!/usr/bin/env node
/**
 * Page cost report for a disposable test site that has tests/bin/mu-metrics.php installed.
 *
 * Usage: node tests/bin/measure.mjs <base-url> [runs] [--json]
 *
 * Every page is requested `runs` times (default 3). The first request fills the fragment cache (cold),
 * the best of the others is the warm figure. Numbers come from the server itself: query count and
 * database time from SAVEQUERIES, wall time from the start of the request, peak memory from PHP. They
 * describe the PHP built-in server on this machine, not production hosting, and are meant for comparing
 * pages and releases with each other.
 */

const base = (process.argv[2] || 'http://127.0.0.1:8072').replace(/\/$/, '');
const runs = Math.max(2, parseInt(process.argv[3] || '3', 10));
const asJson = process.argv.includes('--json');

/** Fetch a page without following redirects and read the metrics comment. */
async function hit(url) {
  const started = performance.now();
  const response = await fetch(url, { redirect: 'manual' });
  const body = await response.text();
  const total = performance.now() - started;
  const match = body.match(/<!-- nh-metrics (\{.*\}) -->/);
  return {
    status: response.status,
    location: response.headers.get('location'),
    bytes: Buffer.byteLength(body),
    total_ms: Math.round(total),
    metrics: match ? JSON.parse(match[1]) : null,
    body,
  };
}

/** Resolve a path to its canonical URL (one redirect at most). */
async function resolve(path) {
  const first = await hit(base + path);
  return first.status >= 300 && first.status < 400 && first.location ? first.location : base + path;
}

/** Pick pages from the home page and from known bulk fixture URLs. */
async function pages() {
  const home = await hit(base + '/');
  const hrefs = [...home.body.matchAll(/<a [^>]*href="([^"]+)"/g)].map((m) => m[1]).filter((h) => h.startsWith(base));
  const firstCard = hrefs.find((h) => /\/[a-z0-9-]+\/[a-z0-9-]+\/[a-z0-9-]+\/$/.test(h.replace(base, '')) && !h.includes('/category/') && !h.includes('/tag/') && !h.includes('/author/'));
  const farPost = await resolve('/?p=' + (parseInt(process.env.NH_MID_POST || '0', 10) || 0));

  const list = [
    ['home', '/'],
    ['home page 2', '/page/2/'],
    ['home page 50', '/page/50/', true],
    ['home last pages', '/page/' + (process.env.NH_LAST_PAGE || '400') + '/'],
    ['hub category', '/category/bulk-hub-1/'],
    ['hub category page 20', '/category/bulk-hub-1/page/20/', true],
    ['child category', '/category/bulk-hub-1/bulk-topic-1-1/'],
    ['child category page 5', '/category/bulk-hub-1/bulk-topic-1-1/page/5/', true],
    ['tag', '/tag/bulk-tag-1/'],
    ['author', '/author/bulk_author_1/'],
    ['author page 100', '/author/bulk_author_1/page/100/', true],
    ['year archive', '/' + new Date().getFullYear() + '/'],
    ['search common word', '/?s=scooter'],
    ['search two words', '/?s=xe+m%C3%A1y'],
    ['search page 5', '/?s=scooter&paged=5', true],
    ['search rare word', '/?s=zzzzqqqq'],
    ['404', '/nothing-here-at-all/'],
  ];
  if (firstCard) list.splice(1, 0, ['article (newest)', firstCard.replace(base, '')]);
  if (process.env.NH_MID_POST) list.splice(2, 0, ['article (mid archive)', farPost.replace(base, '')]);
  return list;
}

const rows = [];
for (const [label, path, deep] of await pages()) {
  const samples = [];
  for (let i = 0; i < runs; i++) samples.push(await hit(base + path));
  const cold = samples[0];
  const warm = samples.slice(1).reduce((best, s) => (s.total_ms < best.total_ms ? s : best));
  if (deep && warm.status === 404) {
    // A page number or archive that this site is too small to have is not a measurement.
    rows.push({ label, path, status: 404, absent: true });
    continue;
  }
  rows.push({
    label,
    path,
    status: warm.status,
    kb: Math.round(warm.bytes / 1024),
    cold_ms: cold.total_ms,
    warm_ms: warm.total_ms,
    cold_q: cold.metrics?.queries ?? null,
    warm_q: warm.metrics?.queries ?? null,
    warm_db_ms: warm.metrics?.db_ms ?? null,
    peak_mb: warm.metrics?.peak_mb ?? null,
    slowest: warm.metrics?.slowest?.[0] ?? null,
  });
}

if (asJson) {
  console.log(JSON.stringify(rows, null, 2));
} else {
  console.log('| page | status | KB | cold ms | warm ms | cold queries | warm queries | warm db ms | peak MB |');
  console.log('|---|---|---|---|---|---|---|---|---|');
  for (const r of rows) {
    if (r.absent) {
      console.log(`| ${r.label} | not present at this size | | | | | | | |`);
      continue;
    }
    console.log(`| ${r.label} | ${r.status} | ${r.kb} | ${r.cold_ms} | ${r.warm_ms} | ${r.cold_q} | ${r.warm_q} | ${r.warm_db_ms} | ${r.peak_mb} |`);
  }
  console.log('\nSlowest single query per page (warm):');
  for (const r of rows) {
    if (r.slowest && r.slowest.ms >= 5) console.log(`- ${r.label}: ${r.slowest.ms} ms  ${r.slowest.caller}\n    ${r.slowest.sql}`);
  }
}
