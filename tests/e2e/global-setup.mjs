/**
 * Runs once before the suite:
 *  1. fails fast, with an actionable message, when the site under test is not reachable;
 *  2. discovers the URL of every page type from the live DOM and stores it in routes.json;
 *  3. prints the discovered routes so a CI log shows exactly what was tested.
 */
import fs from 'node:fs';
import http from 'node:http';
import https from 'node:https';
import { ARTIFACTS_DIR, BASE_URL, REQUIRE_ALL, ROUTES_FILE, SCREENSHOT_DIR } from './helpers/env.mjs';
import { discoverRoutes } from './helpers/discover.mjs';
import { PAGE_TYPES } from './helpers/routes.mjs';

/**
 * HTTP status of a URL, without following redirects. node:http on purpose: the built-in fetch (undici) of some Node 22
 * releases throws an uncaught assertion (`assert(!this.paused)`) while it reads the answer of PHP's built-in server,
 * which ended this setup before a single test ran on GitHub Actions.
 */
function statusOf(url) {
  return new Promise((resolve, reject) => {
    const client = url.startsWith('https:') ? https : http;
    const request = client.get(url, { headers: { connection: 'close' }, timeout: 15_000 }, (response) => {
      response.resume();
      response.on('end', () => resolve(response.statusCode));
      response.on('error', reject);
    });
    request.on('timeout', () => request.destroy(new Error('no answer within 15 s')));
    request.on('error', reject);
  });
}

export default async function globalSetup() {
  try {
    const status = await statusOf(`${BASE_URL}/`);
    if (status !== 200) {
      throw new Error(`HTTP ${status}`);
    }
  } catch (error) {
    throw new Error(
      `The site under test is not reachable at ${BASE_URL} (${error.message}).\n` +
        'Start it first, for example:  tests/bin/serve.sh <wordpress dir> 8071 &\n' +
        'or point the suite at another site with NH_BASE_URL=http://host:port.'
    );
  }

  fs.mkdirSync(ARTIFACTS_DIR, { recursive: true });
  fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });

  const { routes, notes } = await discoverRoutes();
  fs.writeFileSync(ROUTES_FILE, JSON.stringify({ generatedAt: new Date().toISOString(), baseUrl: BASE_URL, routes, notes }, null, 2));

  console.log(`\n[e2e] site under test: ${BASE_URL}`);
  for (const type of PAGE_TYPES) {
    console.log(`[e2e]   ${type.key.padEnd(15)} ${routes[type.key] || 'NOT FOUND'}`);
  }
  for (const note of notes) {
    console.log(`[e2e]   note: ${note}`);
  }

  const missingCore = PAGE_TYPES.filter((type) => type.core && !routes[type.key]).map((type) => type.key);
  if (missingCore.length > 0) {
    throw new Error(`Core page types could not be discovered: ${missingCore.join(', ')}. Is the fixture site seeded (tests/bin/seed-fixtures.php)?`);
  }
  const missing = PAGE_TYPES.filter((type) => !routes[type.key]).map((type) => type.key);
  if (REQUIRE_ALL && missing.length > 0) {
    throw new Error(`NH_E2E_REQUIRE_ALL=1 and these page types are missing: ${missing.join(', ')}`);
  }
}
