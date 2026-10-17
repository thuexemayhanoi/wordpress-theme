/**
 * SMOKE: every page type answers with the right status and a sound document, and the browser sees no
 * errors while loading it.
 *
 * Per page type: HTTP status (the 404 page must really be a 404), <html lang>, exactly one h1, a title,
 * a zoomable viewport meta, one main/banner/contentinfo landmark, a working skip link target, unique ids,
 * aria references that resolve, and (through the automatic `guard` fixture and an explicit assertion)
 * no console error, no uncaught exception, no failed request, no 4xx/5xx subresource, no third-party
 * request.
 */
import { test, expect, requireRoute } from './helpers/fixtures.mjs';
import { PAGE_TYPES } from './helpers/routes.mjs';
import { gotoReady, note } from './helpers/ui.mjs';

for (const type of PAGE_TYPES) {
  test(`${type.label}: status, language, one h1, landmarks, clean console and network`, async ({ page, routes, guard }, testInfo) => {
    const url = requireRoute(test, routes, type.key);

    const response = await gotoReady(page, url);
    const expected = type.status ?? 200;
    expect(response.status(), `HTTP status of ${url}`).toBe(expected);
    if (expected === 404) {
      guard.expectDocument404(page.url());
    }

    // Everything below is a soft assertion: one run reports every problem of the page, and a defect early in
    // the list cannot hide a console error or a missing asset further down.

    // <html lang> is what screen readers and translators use to pick a language.
    const lang = await page.locator('html').getAttribute('lang');
    expect.soft(lang, '<html lang>').toMatch(/^[a-z]{2,3}(-[A-Za-z0-9]{2,8})*$/);

    // Exactly one h1, visible and not empty.
    const h1 = page.locator('h1');
    await expect.soft(h1, 'number of h1 elements').toHaveCount(1);
    const h1Text = (await h1.count()) > 0 ? (await h1.first().innerText()).trim() : '';
    expect.soft(h1Text.length, 'h1 text length (visible text)').toBeGreaterThan(0);

    expect.soft((await page.title()).trim().length, 'document title length').toBeGreaterThan(0);

    // The viewport meta must not disable zooming (WCAG 1.4.4).
    const viewport = (await page.locator('meta[name="viewport"]').getAttribute('content')) || '';
    expect.soft(viewport).toContain('width=device-width');
    expect.soft(viewport).not.toMatch(/user-scalable\s*=\s*(no|0)/i);
    expect.soft(viewport).not.toMatch(/maximum-scale\s*=\s*1(\.0)?\b/i);

    // Landmarks: one banner, one main, one contentinfo; every <nav> has a distinguishing name.
    await expect.soft(page.locator('main#primary')).toHaveCount(1);
    await expect.soft(page.locator('body > header#masthead')).toHaveCount(1);
    await expect.soft(page.locator('footer#colophon')).toHaveCount(1);
    const unnamedNavs = await page.locator('nav:not([aria-label]):not([aria-labelledby])').count();
    expect.soft(unnamedNavs, 'nav landmarks without an accessible name').toBe(0);

    // The skip link points at something that exists and can take focus (main has tabindex=-1).
    const skip = await page.evaluate(() => {
      const link = document.querySelector('a.skip-link');
      if (!link) {
        return { found: false };
      }
      const target = document.querySelector(link.getAttribute('href'));
      return { found: true, href: link.getAttribute('href'), targetExists: Boolean(target), targetTabindex: target ? target.getAttribute('tabindex') : null };
    });
    expect.soft(skip.found, 'skip link exists').toBe(true);
    expect.soft(skip.targetExists, `skip link target ${skip.href} exists`).toBe(true);
    expect.soft(skip.targetTabindex, 'skip link target is programmatically focusable').toBe('-1');

    // ids are unique and aria references resolve (a broken aria-controls is invisible to sighted users).
    const integrity = await page.evaluate(() => {
      const counts = new Map();
      document.querySelectorAll('[id]').forEach((el) => counts.set(el.id, (counts.get(el.id) || 0) + 1));
      const duplicates = [...counts].filter(([, n]) => n > 1).map(([id, n]) => `${id} x${n}`);
      const dangling = [];
      ['aria-controls', 'aria-labelledby', 'aria-describedby'].forEach((attribute) => {
        document.querySelectorAll(`[${attribute}]`).forEach((el) => {
          el
            .getAttribute(attribute)
            .split(/\s+/)
            .filter(Boolean)
            .forEach((id) => {
              if (!document.getElementById(id)) {
                dangling.push(`${attribute}="${id}" on ${el.tagName.toLowerCase()}`);
              }
            });
        });
      });
      return { duplicates, dangling };
    });
    expect.soft(integrity.duplicates, 'duplicate ids').toEqual([]);
    expect.soft(integrity.dangling, 'aria references to missing ids').toEqual([]);

    // The three theme assets are wired: inline critical CSS, deferred main CSS, one deferred script.
    await expect.soft(page.locator('style#nh-editorial-critical-inline-css')).toHaveCount(1);
    await expect.soft(page.locator('#nh-editorial-main-css')).toHaveCount(1);
    await expect.soft(page.locator('script#nh-editorial-js[src][defer]')).toHaveCount(1);

    if (guard.state.stubbed.length > 0) {
      note(testInfo, 'third-party', `Gravatar avatar requests (WordPress core default), answered with a stub: ${guard.state.stubbed.length}`);
    }
    guard.assertClean();
  });
}

test('theme assets are served with the right content types and are not empty @once', async ({ request }) => {
  const home = await (await request.get('/')).text();
  const css = home.match(/href="([^"]*assets\/css\/main\.min\.css[^"]*)"/)?.[1];
  const js = home.match(/src="([^"]*assets\/js\/theme\.min\.js[^"]*)"/)?.[1];
  expect(css, 'main stylesheet URL in the page').toBeTruthy();
  expect(js, 'theme script URL in the page').toBeTruthy();

  const cssResponse = await request.get(css);
  expect(cssResponse.status()).toBe(200);
  expect(cssResponse.headers()['content-type']).toContain('text/css');
  expect((await cssResponse.body()).length, 'main.min.css bytes').toBeGreaterThan(1_000);

  const jsResponse = await request.get(js);
  expect(jsResponse.status()).toBe(200);
  expect(jsResponse.headers()['content-type']).toMatch(/javascript/);
  expect((await jsResponse.body()).length, 'theme.min.js bytes').toBeGreaterThan(1_000);
});

test('a missing page is a real 404 (not a soft 404) and says so @once', async ({ request, routes }) => {
  const response = await request.get(routes.notFound, { maxRedirects: 0 });
  expect(response.status()).toBe(404);
  expect(await response.text()).toContain('Page not found');
});
