/**
 * VISUAL: a full-page screenshot sweep for review, with automatic layout probes on every page.
 *
 *   page type x {375, 768, 1440 (the three projects)} x {light, dark}  ->  __screenshots__/<project>/<page>-<scheme>.png
 *   interactive states (drawer, mega panel, search suggestions, focus rings, TOC, FAQ, hover)
 *                                                                         ->  __screenshots__/<project>/states/
 *   the widths where the primary navigation wraps (961-1100px)             ->  __screenshots__/breakpoints/
 *
 * The screenshots are for people (and are git-ignored); the probes make the sweep fail on defects a
 * machine can see: horizontal overflow, text cut off by its container, siblings overlapping each other,
 * stretched images, images that did not load.
 */
import fs from 'node:fs';
import path from 'node:path';
import { test, expect, requireRoute } from './helpers/fixtures.mjs';
import { PAGE_TYPES, slug } from './helpers/routes.mjs';
import { SCREENSHOT_DIR } from './helpers/env.mjs';
import { clippedText, distortedImages, gotoReady, loadAllImages, measureOverflow, note, openDrawer, openSearch, overlappingSiblings, typeInHeaderSearch } from './helpers/ui.mjs';

function shotPath(testInfo, ...parts) {
  const file = path.join(SCREENSHOT_DIR, testInfo.project.name, ...parts);
  fs.mkdirSync(path.dirname(file), { recursive: true });
  return file;
}

// ---------------------------------------------------------------------------------------------------
// Full-page sweep
// ---------------------------------------------------------------------------------------------------
for (const scheme of ['light', 'dark']) {
  test.describe(`screenshots, ${scheme}`, () => {
    test.use({ colorScheme: scheme });

    for (const type of PAGE_TYPES) {
      test(`${type.label}`, async ({ page, routes, guard }, testInfo) => {
        const url = requireRoute(test, routes, type.key);
        await gotoReady(page, url);
        if (type.status === 404) {
          guard.expectDocument404(page.url());
        }
        await loadAllImages(page);

        await page.screenshot({ path: shotPath(testInfo, `${slug(type.label)}-${scheme}.png`), fullPage: true, animations: 'disabled', caret: 'hide' });

        // What a machine can see.
        const overflow = await measureOverflow(page);
        expect(overflow.scrollWidth, `horizontal overflow: ${JSON.stringify(overflow.offenders)}`).toBeLessThanOrEqual(overflow.innerWidth);

        const { clipped, ellipsized } = await page.evaluate(clippedText);
        if (ellipsized.length > 0) {
          note(testInfo, 'ellipsis', ellipsized.join(' | '));
        }
        expect(clipped, 'text cut off by its container').toEqual([]);

        expect(await page.evaluate(overlappingSiblings), 'siblings overlapping each other').toEqual([]);
        expect(await page.evaluate(distortedImages), 'stretched or squashed images').toEqual([]);

        const brokenImages = await page.evaluate(() => [...document.images].filter((img) => img.naturalWidth === 0).map((img) => img.currentSrc || img.src));
        expect(brokenImages, 'images that did not load').toEqual([]);
      });
    }
  });
}

// ---------------------------------------------------------------------------------------------------
// Interactive states
// ---------------------------------------------------------------------------------------------------
for (const scheme of ['light', 'dark']) {
  test.describe(`state screenshots, ${scheme}`, () => {
    test.use({ colorScheme: scheme });

    test('mobile drawer open with a sub menu expanded @drawer', async ({ page, routes }, testInfo) => {
      await gotoReady(page, requireRoute(test, routes, 'home'));
      await openDrawer(page);
      await page.locator('#primary-navigation .submenu-toggle').first().click();
      await page.screenshot({ path: shotPath(testInfo, 'states', `drawer-open-${scheme}.png`), animations: 'disabled' });
      expect(await page.evaluate(overlappingSiblings), 'overlaps with the drawer open').toEqual([]);
    });

    test('desktop mega panel open @desktop-only', async ({ page, routes }, testInfo) => {
      await gotoReady(page, requireRoute(test, routes, 'home'));
      await page.locator('.nav-list > li > .submenu-toggle').first().click();
      await expect(page.locator('.nav-list .mega-panel').first()).toBeVisible();
      await page.screenshot({ path: shotPath(testInfo, 'states', `megapanel-${scheme}.png`), animations: 'disabled' });
    });

    test('header search with suggestions', async ({ page, routes }, testInfo) => {
      await gotoReady(page, requireRoute(test, routes, 'home'));
      await openSearch(page);
      await typeInHeaderSearch(page, 'scooter');
      await expect(page.locator('#header-search .search-suggest')).toBeVisible();
      await page.screenshot({ path: shotPath(testInfo, 'states', `search-suggestions-${scheme}.png`), animations: 'disabled' });
    });

    test('keyboard focus rings on the header and on a card', async ({ page, routes }, testInfo) => {
      await gotoReady(page, requireRoute(test, routes, 'home'));
      await page.keyboard.press('Tab'); // skip link
      await page.screenshot({ path: shotPath(testInfo, 'states', `focus-skip-link-${scheme}.png`), clip: { x: 0, y: 0, width: page.viewportSize().width, height: 160 }, animations: 'disabled' });
      await page.keyboard.press('Tab');
      await page.keyboard.press('Tab');
      await page.screenshot({ path: shotPath(testInfo, 'states', `focus-header-${scheme}.png`), clip: { x: 0, y: 0, width: page.viewportSize().width, height: 160 }, animations: 'disabled' });
      const card = page.locator('.card__title a').first();
      await card.scrollIntoViewIfNeeded();
      await card.focus();
      await page.screenshot({ path: shotPath(testInfo, 'states', `focus-card-${scheme}.png`), animations: 'disabled' });
    });

    test('article: contents expanded and an FAQ item opened', async ({ page, routes }, testInfo) => {
      await gotoReady(page, requireRoute(test, routes, 'singleRich'));
      if (!(await page.locator('.toc__details').evaluate((el) => el.open))) {
        await page.locator('.toc__summary').click();
      }
      await page.screenshot({ path: shotPath(testInfo, 'states', `article-top-toc-${scheme}.png`), animations: 'disabled' });
      await page.locator('.faq').scrollIntoViewIfNeeded();
      await page.locator('.faq__item').nth(1).locator('summary').click();
      await page.locator('.faq').screenshot({ path: shotPath(testInfo, 'states', `faq-open-${scheme}.png`), animations: 'disabled' });
    });

    test('card hover and the dark-mode toggle pressed @desktop-only', async ({ page, routes }, testInfo) => {
      await gotoReady(page, requireRoute(test, routes, 'home'));
      const card = page.locator('article.card--post').first();
      await card.scrollIntoViewIfNeeded();
      await card.hover();
      await page.waitForTimeout(400);
      await card.screenshot({ path: shotPath(testInfo, 'states', `card-hover-${scheme}.png`), animations: 'disabled' });
    });
  });
}

// ---------------------------------------------------------------------------------------------------
// Widths where the primary navigation changes mode or wraps
// ---------------------------------------------------------------------------------------------------
test('header at the widths around the drawer breakpoint (nav wrapping) @once', async ({ page, routes }, testInfo) => {
  const widths = [900, 961, 1024, 1100, 1180];
  for (const width of widths) {
    await page.setViewportSize({ width, height: 600 });
    await gotoReady(page, requireRoute(test, routes, 'home'));
    const file = path.join(SCREENSHOT_DIR, 'breakpoints', `home-header-${width}.png`);
    fs.mkdirSync(path.dirname(file), { recursive: true });
    await page.screenshot({ path: file, clip: { x: 0, y: 0, width, height: 190 }, animations: 'disabled' });
    const rows = await page.evaluate(() => new Set([...document.querySelectorAll('.nav-list > li')].filter((li) => li.getBoundingClientRect().width > 0).map((li) => Math.round(li.getBoundingClientRect().top))).size);
    note(testInfo, `nav@${width}`, `${rows} row(s) of navigation`);
  }
});
