/**
 * Playwright fixtures shared by every spec.
 *
 * `guard` (automatic): watches the whole browser context while a test runs and, when the test body has
 * passed, fails it if the page produced any of:
 *   - a console message of type "error" (the 404 document logs one for itself: opt in with
 *     guard.expectDocument404(url));
 *   - an uncaught exception (pageerror);
 *   - a failed network request (ERR_ABORTED is ignored on purpose: navigations and the search
 *     suggestions' own AbortController cancel requests by design; HTTP errors are checked separately);
 *   - a subresource answered with 4xx/5xx (the main document status is asserted by the test itself);
 *   - a request to any host other than the site and Gravatar (the theme promises no third-party requests);
 *   - a console warning printed by the theme's own init wrapper ("Nguyen Ha Editorial:").
 * Third-party hosts are never really contacted: Gravatar (WordPress core avatars) is answered with a
 * 1x1 GIF, everything else is blocked and reported. That keeps the suite deterministic and offline-safe.
 *
 * A test that breaks something on purpose (aborting the REST route, say) declares it with
 * guard.allow({ failed, response, console }); nothing is silenced globally.
 */
import { test as base, expect } from '@playwright/test';
import { BASE_ORIGIN } from './env.mjs';
import { loadRoutes } from './routes.mjs';

/** 1x1 transparent GIF. */
const PIXEL_GIF = Buffer.from('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', 'base64');

/** Hosts the theme is expected to contact: WordPress core avatars only. */
const STUBBED_THIRD_PARTY = [/(^|\.)gravatar\.com$/i];

const escapeRegExp = (text) => text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

const matchesAny = (patterns, value) => patterns.some((pattern) => (pattern instanceof RegExp ? pattern.test(value) : value.includes(pattern)));

/**
 * Attach the guard to a browser context. Use the automatic `guard` fixture for the built-in context;
 * call this directly for contexts a test creates itself (for example with JavaScript disabled).
 */
export async function installGuard(context) {
  const state = {
    consoleErrors: [],
    consoleWarnings: [],
    pageErrors: [],
    failedRequests: [],
    badResponses: [],
    thirdParty: [],
    stubbed: [],
    aborted: [],
  };
  const allowed = { console: [], failed: [], response: [], thirdParty: [] };
  const blockedUrls = new Set();

  context.on('console', (message) => {
    const type = message.type();
    const record = { text: message.text(), url: message.location().url };
    if (type === 'error') {
      state.consoleErrors.push(record);
    } else if (type === 'warning') {
      state.consoleWarnings.push(record);
    }
  });

  context.on('weberror', (webError) => {
    state.pageErrors.push(String(webError.error()?.stack || webError.error()));
  });

  context.on('requestfailed', (request) => {
    const url = request.url();
    const failure = request.failure()?.errorText || 'unknown failure';
    if (blockedUrls.has(url)) {
      return; // already reported as a third-party request
    }
    if (failure === 'net::ERR_ABORTED') {
      state.aborted.push({ url, type: request.resourceType() });
      return;
    }
    state.failedRequests.push({ url, failure, type: request.resourceType() });
  });

  context.on('response', (response) => {
    const status = response.status();
    const request = response.request();
    if (status >= 400 && request.resourceType() !== 'document') {
      state.badResponses.push({ url: response.url(), status, type: request.resourceType() });
    }
  });

  await context.route(
    (url) => (url.protocol === 'http:' || url.protocol === 'https:') && url.origin !== BASE_ORIGIN,
    async (route) => {
      const url = new URL(route.request().url());
      if (STUBBED_THIRD_PARTY.some((pattern) => pattern.test(url.hostname))) {
        state.stubbed.push(url.href);
        await route.fulfill({ status: 200, contentType: 'image/gif', body: PIXEL_GIF });
        return;
      }
      blockedUrls.add(url.href);
      state.thirdParty.push(url.href);
      await route.abort('blockedbyclient');
    }
  );

  const guard = {
    state,
    /** Declare an expected problem: { console, failed, response, thirdParty } each a RegExp/string or array of them. */
    allow(rules) {
      for (const [kind, value] of Object.entries(rules)) {
        allowed[kind].push(...(Array.isArray(value) ? value : [value]));
      }
    },
    /** The 404 document makes the browser log one console error for its own status: allow exactly that. */
    expectDocument404(url) {
      const absolute = new URL(url, BASE_ORIGIN).href;
      allowed.console.push(new RegExp(`status of 404.*@ ${escapeRegExp(absolute)}$`));
    },
    problems() {
      const found = [];
      for (const message of state.consoleErrors) {
        if (!matchesAny(allowed.console, `${message.text} @ ${message.url}`) && !blockedUrls.has(message.url)) {
          found.push(`console.error: ${message.text}${message.url ? ` (${message.url})` : ''}`);
        }
      }
      for (const message of state.consoleWarnings) {
        if (/Nguyen Ha Editorial:/.test(message.text)) {
          found.push(`theme init warning: ${message.text}`);
        }
      }
      for (const error of state.pageErrors) {
        found.push(`uncaught exception: ${error}`);
      }
      for (const failed of state.failedRequests) {
        if (!matchesAny(allowed.failed, failed.url)) {
          found.push(`request failed (${failed.failure}, ${failed.type}): ${failed.url}`);
        }
      }
      for (const bad of state.badResponses) {
        if (!matchesAny(allowed.response, bad.url)) {
          found.push(`HTTP ${bad.status} (${bad.type}): ${bad.url}`);
        }
      }
      for (const url of state.thirdParty) {
        if (!matchesAny(allowed.thirdParty, url)) {
          found.push(`unexpected third-party request: ${url}`);
        }
      }
      return found;
    },
    assertClean() {
      const found = guard.problems();
      expect(found, `The page produced ${found.length} problem(s):\n  - ${found.join('\n  - ')}`).toEqual([]);
    },
  };
  return guard;
}

export const test = base.extend({
  /** Discovered URLs of every page type (see helpers/discover.mjs). */
  routes: [
    async ({}, use) => {
      await use(await loadRoutes());
    },
    { scope: 'worker' },
  ],

  guard: [
    async ({ context }, use, testInfo) => {
      const guard = await installGuard(context);
      await use(guard);
      // Only judge a test that otherwise passed: a failing test already reports its own cause.
      if (testInfo.status === 'passed') {
        guard.assertClean();
      }
    },
    { auto: true },
  ],
});

export { expect };

/** Skip with an explicit reason when a page type could not be discovered on this site. */
export function requireRoute(testApi, routes, key) {
  const url = routes[key];
  testApi.skip(!url, `page type "${key}" was not found on this site (see the notes printed by global setup)`);
  return url;
}
