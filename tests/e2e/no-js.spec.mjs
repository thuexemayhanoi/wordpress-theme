/**
 * NO JAVASCRIPT: the site must stay usable when the script does not run (blocked, failed, disabled).
 *
 * Contract (assets/src/js/navigation.js, inc/assets.php):
 *  - the non-critical stylesheet arrives through the <noscript> link (the media="print" swap needs JS);
 *  - every top-level link works; the head <noscript> style is supposed to expose the mega panels and the
 *    search panel and to hide the controls that only make sense with JavaScript (search toggle, menu
 *    button, sub menu chevrons, colour mode button) ("without JavaScript the collapsed menus and the search
 *    panel must stay reachable");
 *  - the sticky header must not swallow the screen or intercept clicks meant for the content;
 *  - search forms submit natively; the TOC and the FAQ are native <details>, so they work;
 *  - dark mode follows the operating system through CSS alone.
 *
 * Screenshots of the JS-less rendering go to tests/e2e/__screenshots__/nojs (git-ignored) for review.
 */
import path from 'node:path';
import fs from 'node:fs';
import { test as base, expect, installGuard, requireRoute } from './helpers/fixtures.mjs';
import { PAGE_TYPES, slug } from './helpers/routes.mjs';
import { SCREENSHOT_DIR } from './helpers/env.mjs';
import { measureOverflow, newProjectContext, note } from './helpers/ui.mjs';

/**
 * Wait until the eagerly loaded images are complete. Polled from Node on purpose: page.waitForFunction()
 * schedules its polling with page timers, which may never fire when scripting is disabled, whereas
 * page.evaluate() runs through the protocol and works.
 */
async function settleImages(page, { lazyToo = false, timeout = 8_000 } = {}) {
  if (lazyToo) {
    await page.evaluate(() => document.querySelectorAll('img[loading="lazy"]').forEach((img) => { img.loading = 'eager'; }));
  }
  const started = Date.now();
  while (Date.now() - started < timeout) {
    const done = await page.evaluate(() => [...document.images].filter((img) => img.loading !== 'lazy').every((img) => img.complete));
    if (done) {
      return;
    }
    await page.waitForTimeout(100);
  }
}

/**
 * Click an element the way a visitor does: scroll it into the part of the screen the sticky header leaves free,
 * then click. Playwright's own scrolling centres the element, which on a phone without JavaScript can be
 * underneath a header that fills most of the screen. That defect has its own test ("a sticky header never
 * takes more than a third of the screen"); it must not make every later test wait for a click timeout.
 */
async function clickClearOfHeader(page, locator) {
  await locator.evaluate((el) => {
    const header = document.querySelector('.site-header');
    const clear = (header ? header.getBoundingClientRect().bottom : 0) + 24;
    window.scrollBy({ top: el.getBoundingClientRect().top - clear, behavior: 'instant' });
  });
  await locator.click({ timeout: 5_000 });
}

/**
 * `openWithoutJs(url, overrides)` opens a page in a context with JavaScript switched off. Contexts are
 * closed, and the console/network guard is asserted, when the test ends (only if it otherwise passed).
 */
const test = base.extend({
  openWithoutJs: async ({ browser }, use, testInfo) => {
    const opened = [];
    await use(async (url, overrides = {}) => {
      const context = await newProjectContext(browser, testInfo, { javaScriptEnabled: false, ...overrides });
      const guard = await installGuard(context);
      const page = await context.newPage();
      const response = await page.goto(url, { waitUntil: 'load' });
      await settleImages(page);
      opened.push({ context, guard });
      return { context, page, guard, response };
    });
    const passed = testInfo.status === 'passed';
    for (const { context, guard } of opened) {
      if (passed) {
        guard.assertClean();
      }
      await context.close();
    }
  },
});

for (const type of PAGE_TYPES) {
  test(`${type.label}: loads, is styled by the <noscript> stylesheet and does not overflow`, async ({ openWithoutJs, routes }) => {
    const url = requireRoute(test, routes, type.key);
    const { page, guard, response } = await openWithoutJs(url);

    expect(response.status()).toBe(type.status ?? 200);
    if (type.status === 404) {
      guard.expectDocument404(page.url());
    }
    await expect(page.locator('h1')).toHaveCount(1);
    await expect(page.locator('h1')).toBeVisible();

    // The below-the-fold stylesheet normally swaps in with JS; without JS the <noscript> link must apply it.
    const styled = await page.evaluate(() => ({
      footerBackground: getComputedStyle(document.querySelector('.site-footer')).backgroundColor,
      link: document.getElementById('nh-editorial-main-css')?.media,
    }));
    expect(styled.link, 'the print-media link cannot swap itself without JS').toBe('print');
    expect(styled.footerBackground, 'footer styles come from main.min.css via <noscript>').toBe('rgb(11, 18, 32)');

    const overflow = await measureOverflow(page);
    expect(overflow.scrollWidth, `scrollWidth ${overflow.scrollWidth} vs ${overflow.innerWidth}; ${JSON.stringify(overflow.offenders)}`).toBeLessThanOrEqual(overflow.innerWidth);
  });
}

test.describe('navigation without JavaScript', () => {
  test('the header layout does not break: the brand name is not cut off and the nav is not squeezed beside it', async ({ openWithoutJs, routes }, testInfo) => {
    const { page } = await openWithoutJs(requireRoute(test, routes, 'home'));
    const facts = await page.evaluate(() => {
      const box = (selector) => {
        const el = document.querySelector(selector);
        if (!el || getComputedStyle(el).display === 'none') {
          return null;
        }
        const r = el.getBoundingClientRect();
        return { left: r.left, right: r.right, top: r.top, bottom: r.bottom, width: r.width, height: r.height };
      };
      const name = document.querySelector('.brand__name');
      return {
        brand: box('.header-row .brand'),
        nav: box('.header-row .primary-nav'),
        header: box('.site-header'),
        brandTruncated: name.scrollWidth > name.clientWidth + 1,
        viewport: window.innerWidth,
      };
    });
    expect.soft(facts.brandTruncated, 'the site name is cut off with an ellipsis').toBe(false);
    if (facts.nav) {
      const besideBrand = facts.nav.top < facts.brand.bottom - 2;
      expect.soft(besideBrand && facts.nav.width < 200, `the nav is squeezed into a ${Math.round(facts.nav.width)}px column beside the brand`).toBe(false);
      const overlapX = Math.min(facts.brand.right, facts.nav.right) - Math.max(facts.brand.left, facts.nav.left);
      const overlapY = Math.min(facts.brand.bottom, facts.nav.bottom) - Math.max(facts.brand.top, facts.nav.top);
      expect.soft(overlapX > 1 && overlapY > 1, 'brand and nav overlap').toBe(false);
    }
    note(testInfo, 'nojs-header', `header ${Math.round(facts.header.height)}px tall at ${facts.viewport}px wide`);
  });

  test('a sticky header never takes more than a third of the screen or intercepts clicks meant for the content', async ({ openWithoutJs, routes }, testInfo) => {
    const { page } = await openWithoutJs(requireRoute(test, routes, 'singleRich'));
    const facts = await page.evaluate(() => {
      const header = document.querySelector('.site-header');
      const style = getComputedStyle(header);
      return { sticky: style.position === 'sticky' || style.position === 'fixed', height: header.getBoundingClientRect().height, viewport: window.innerHeight };
    });
    note(testInfo, 'nojs-sticky-header', `${facts.sticky ? 'sticky' : 'not sticky'} header is ${Math.round(facts.height)}px of a ${facts.viewport}px viewport (${Math.round((facts.height / facts.viewport) * 100)}%)`);
    if (facts.sticky) {
      expect.soft(facts.height, `the sticky header covers ${Math.round((facts.height / facts.viewport) * 100)}% of the viewport`).toBeLessThanOrEqual(facts.viewport / 3);
    }

    // Behavioural proof: scroll a heading of the article to the middle of the screen and make sure what is
    // under the pointer there is the page content, not the header.
    const hit = await page.evaluate(() => {
      const heading = document.querySelector('.entry-content h2');
      heading.scrollIntoView({ block: 'center', behavior: 'instant' });
      const box = heading.getBoundingClientRect();
      const top = document.elementFromPoint(box.left + 10, box.top + box.height / 2);
      return { onHeading: Boolean(top && (top === heading || heading.contains(top))), coveredBy: top ? top.closest('.site-header') ? 'the header' : top.tagName : 'nothing' };
    });
    expect.soft(hit.onHeading, `an article heading scrolled to the screen centre is covered by ${hit.coveredBy}`).toBe(true);
  });

  test('top-level links are all visible and usable', async ({ openWithoutJs, routes }) => {
    const { page } = await openWithoutJs(requireRoute(test, routes, 'home'));
    const links = page.locator('.nav-list > li > a');
    const count = await links.count();
    expect(count).toBeGreaterThan(0);
    for (let i = 0; i < count; i += 1) {
      await expect(links.nth(i), `top-level link #${i}`).toBeVisible();
    }
  });

  test('the sub menu links are reachable (the head <noscript> style is meant to expose the panels)', async ({ openWithoutJs, routes }) => {
    const { page } = await openWithoutJs(requireRoute(test, routes, 'home'));
    const links = page.locator('.sub-menu a');
    const count = await links.count();
    expect(count, 'sub menu links in the markup').toBeGreaterThan(0);
    const hidden = [];
    for (let i = 0; i < count; i += 1) {
      if (!(await links.nth(i).isVisible())) {
        hidden.push((await links.nth(i).textContent()).trim());
      }
    }
    expect(hidden, `${hidden.length} of ${count} sub menu links stay display:none without JavaScript`).toEqual([]);
  });

  test('controls that need JavaScript are not shown (search toggle, menu button, sub menu chevrons, colour mode button)', async ({ openWithoutJs, routes }) => {
    const { page } = await openWithoutJs(requireRoute(test, routes, 'home'));
    const dead = await page.evaluate(() =>
      ['.search-toggle', '.nav-toggle', '.mode-toggle', '.submenu-toggle'].flatMap((selector) =>
        [...document.querySelectorAll(selector)]
          .filter((el) => {
            const r = el.getBoundingClientRect();
            return getComputedStyle(el).display !== 'none' && r.width > 0 && r.height > 0;
          })
          .map((el) => `${selector} (${(el.textContent || '').trim().slice(0, 30)})`)
      )
    );
    expect(dead, 'visible buttons that do nothing without JavaScript').toEqual([]);
  });

  test('a search form is available and submits on pages without a hero form', async ({ openWithoutJs, routes }) => {
    for (const key of ['singleRich', 'categoryHub']) {
      const { page } = await openWithoutJs(requireRoute(test, routes, key));
      const fields = page.locator('form[role="search"] input[type="search"]');
      let visible = null;
      for (const field of await fields.all()) {
        if (await field.isVisible()) {
          visible = field;
          break;
        }
      }
      expect.soft(visible, `a visible search field on ${key} without JavaScript (the header panel is hidden)`).not.toBeNull();
      if (!visible) {
        continue; // reported above; the other page type is still checked
      }
      await visible.fill('helmet');
      await Promise.all([page.waitForURL(/[?&]s=helmet/), visible.press('Enter')]);
      await expect(page.locator('h1')).toContainText('“helmet”');
    }
  });

  test('the hero, 404 and results forms submit natively', async ({ openWithoutJs, routes }) => {
    for (const key of ['home', 'notFound', 'search']) {
      const { page, guard } = await openWithoutJs(requireRoute(test, routes, key));
      if (key === 'notFound') {
        guard.expectDocument404(page.url());
      }
      const field = page.locator('main form[role="search"] input[type="search"], .archive-header form[role="search"] input[type="search"]').first();
      await expect(field).toBeVisible();
      await field.fill('licence');
      await Promise.all([page.waitForURL(/[?&]s=licence/), field.press('Enter')]);
      await expect(page.locator('h1')).toContainText('“licence”');
    }
  });
});

test.describe('content components without JavaScript', () => {
  test('the table of contents is native <details>: it opens, and its links land below the sticky header', async ({ openWithoutJs, routes }, testInfo) => {
    const { page } = await openWithoutJs(requireRoute(test, routes, 'singleRich'));
    const details = page.locator('.toc__details');
    const wide = page.viewportSize().width >= 1100;
    // JS opens the contents on wide screens; without it they start collapsed everywhere (progressive enhancement).
    expect(await details.evaluate((el) => el.open)).toBe(false);
    if (wide) {
      note(testInfo, 'toc', 'on a wide screen the contents start collapsed without JavaScript (JS opens them at >= 1100px)');
    }
    await clickClearOfHeader(page, page.locator('.toc__summary'));
    await expect(details).toHaveJSProperty('open', true);

    const link = page.locator('.toc__list a').nth(2);
    const id = decodeURIComponent((await link.getAttribute('href')).slice(1));
    await clickClearOfHeader(page, link);
    await page.waitForTimeout(700); // smooth scrolling is CSS-only
    const landing = await page.evaluate((target) => {
      const el = document.getElementById(target);
      return { top: el.getBoundingClientRect().top, header: document.querySelector('.site-header').getBoundingClientRect().bottom, hash: location.hash };
    }, id);
    expect(landing.hash).toBe(`#${id}`);
    expect(landing.top, 'the heading is not hidden under the sticky header').toBeGreaterThanOrEqual(landing.header - 1);
  });

  test('the FAQ works natively: first answer open, the others open on click', async ({ openWithoutJs, routes }) => {
    const { page } = await openWithoutJs(requireRoute(test, routes, 'singleRich'));
    const items = page.locator('.faq__item');
    await expect(items.nth(0)).toHaveJSProperty('open', true);
    await expect(items.nth(0).locator('.faq__answer')).toBeVisible();
    await expect(items.nth(1).locator('.faq__answer')).toBeHidden();
    await clickClearOfHeader(page, items.nth(1).locator('summary'));
    await expect(items.nth(1).locator('.faq__answer')).toBeVisible();
  });

  test('tables, images and the author box render; the article text is complete', async ({ openWithoutJs, routes }) => {
    const { page } = await openWithoutJs(requireRoute(test, routes, 'singleRich'));
    await expect(page.locator('.table-scroll table').first()).toBeVisible();
    await expect(page.locator('.article-media img')).toBeVisible();
    await expect(page.locator('.summary')).toBeVisible();
    await expect(page.locator('.sources')).toBeVisible();
    expect(await page.locator('.entry-content h2').count()).toBeGreaterThan(2);
  });

  test('dark mode applies through CSS alone (no data-theme attribute is ever set)', async ({ openWithoutJs, routes }) => {
    const { page } = await openWithoutJs(requireRoute(test, routes, 'home'), { colorScheme: 'dark' });
    expect(await page.evaluate(() => document.documentElement.getAttribute('data-theme'))).toBeNull();
    expect(await page.evaluate(() => getComputedStyle(document.body).backgroundColor)).toBe('rgb(11, 15, 23)');
  });
});

test.describe('screenshots for review', () => {
  for (const key of ['home', 'singleRich', 'categoryHub', 'search']) {
    test(`${key} without JavaScript`, async ({ openWithoutJs, routes }, testInfo) => {
      const { page } = await openWithoutJs(requireRoute(test, routes, key));
      await settleImages(page, { lazyToo: true, timeout: 15_000 });
      const dir = path.join(SCREENSHOT_DIR, 'nojs');
      fs.mkdirSync(dir, { recursive: true });
      await page.screenshot({ path: path.join(dir, `${testInfo.project.name}-${slug(key)}.png`), fullPage: true, animations: 'disabled' });
    });
  }
});
