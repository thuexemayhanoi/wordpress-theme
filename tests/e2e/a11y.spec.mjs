/**
 * ACCESSIBILITY (axe-core): zero violations expected.
 *
 * Rule set: wcag2a, wcag2aa, wcag21a, wcag21aa, wcag22aa and axe best-practice (see helpers/axe.mjs).
 *
 *  1. every page type, light and dark, as set by the operating system (prefers-color-scheme), at the mobile
 *     and desktop viewports;
 *  2. dark reached through the toggle button (the stored-choice path) on the three main page types;
 *  3. interactive states that change the DOM: mobile drawer with a submenu open, desktop mega panel open,
 *     header search open with suggestions visible, TOC expanded, an FAQ item opened; each in light and dark;
 *  4. a contrast audit that decides what axe leaves as "needs review" (text over gradients, overlaps and
 *     the footer): flat backgrounds from computed colours, everything else measured on rendered pixels.
 *
 * Violations are never excluded to get green: a finding is inspected, then fixed in the theme or, if axe is
 * demonstrably wrong, listed with evidence in helpers/axe.mjs (KNOWN_FALSE_POSITIVES, empty today).
 * "Needs review" (incomplete) results are attached to the report for a human to judge.
 */
import { test, expect, requireRoute } from './helpers/fixtures.mjs';
import { PAGE_TYPES } from './helpers/routes.mjs';
import { formatViolations, runAxe, summarizeIncomplete } from './helpers/axe.mjs';
import { auditContrast, formatContrastFailures } from './helpers/contrast.mjs';
import { attachJson, gotoReady, openDrawer, openSearch, typeInHeaderSearch } from './helpers/ui.mjs';

/** Run axe and fail with a readable report; the incomplete list always goes to the report. */
async function expectNoViolations(page, testInfo, label) {
  const { violations, incomplete } = await runAxe(page);
  await attachJson(testInfo, `axe-incomplete-${label}`, summarizeIncomplete(incomplete));
  expect(violations, `${violations.length} axe violation(s) on ${page.url()} [${label}]\n${formatViolations(violations)}`).toEqual([]);
}

async function expectTheme(page, scheme) {
  expect(await page.evaluate(() => document.documentElement.getAttribute('data-theme')), 'data-theme on <html>').toBe(scheme);
}

// ---------------------------------------------------------------------------------------------------
// 1. Every page type, light and dark (OS preference)
// ---------------------------------------------------------------------------------------------------
for (const scheme of ['light', 'dark']) {
  test.describe(`axe: every page type, ${scheme}`, () => {
    test.use({ colorScheme: scheme });

    for (const type of PAGE_TYPES) {
      test(`${type.label}`, { tag: '@no-tablet' }, async ({ page, routes, guard }, testInfo) => {
        const url = requireRoute(test, routes, type.key);
        await gotoReady(page, url);
        if (type.status === 404) {
          guard.expectDocument404(page.url());
        }
        await expectTheme(page, scheme);
        await expectNoViolations(page, testInfo, `${type.key}-${scheme}`);
      });
    }
  });
}

// ---------------------------------------------------------------------------------------------------
// 2. Dark mode reached with the toggle (stored choice)
// ---------------------------------------------------------------------------------------------------
test.describe('axe: dark mode switched on with the toggle', () => {
  for (const key of ['home', 'singleRich', 'categoryHub']) {
    test(`${key}`, { tag: '@no-tablet' }, async ({ page, routes }, testInfo) => {
      const url = requireRoute(test, routes, key);
      await gotoReady(page, url);
      await expectTheme(page, 'light');
      await page.locator('.mode-toggle').click();
      await expectTheme(page, 'dark');
      await expect(page.locator('.mode-toggle')).toHaveAttribute('aria-pressed', 'true');
      await expectNoViolations(page, testInfo, `${key}-toggled-dark`);
    });
  }
});

// ---------------------------------------------------------------------------------------------------
// 3. Interactive states, light and dark
// ---------------------------------------------------------------------------------------------------
for (const scheme of ['light', 'dark']) {
  test.describe(`axe: interactive states, ${scheme}`, () => {
    test.use({ colorScheme: scheme });

    test('mobile drawer open with a submenu expanded @drawer', async ({ page, routes }, testInfo) => {
      await gotoReady(page, requireRoute(test, routes, 'home'));
      await openDrawer(page);
      const toggle = page.locator('#primary-navigation .submenu-toggle').first();
      await toggle.click();
      await expect(toggle).toHaveAttribute('aria-expanded', 'true');
      await expect(page.locator('#primary-navigation .mega-panel').first()).toBeVisible();
      await expectNoViolations(page, testInfo, `drawer-${scheme}`);
    });

    test('desktop mega panel open @desktop-only', async ({ page, routes }, testInfo) => {
      await gotoReady(page, requireRoute(test, routes, 'home'));
      const toggle = page.locator('.nav-list > li > .submenu-toggle').first();
      await toggle.click();
      await expect(toggle).toHaveAttribute('aria-expanded', 'true');
      await expect(page.locator('.nav-list .mega-panel').first()).toBeVisible();
      await expectNoViolations(page, testInfo, `megapanel-${scheme}`);
    });

    test('header search open with suggestions listed', async ({ page, routes }, testInfo) => {
      await gotoReady(page, requireRoute(test, routes, 'home'));
      await openSearch(page);
      await typeInHeaderSearch(page, 'scooter');
      await expect(page.locator('#header-search .search-suggest')).toBeVisible();
      await expect(page.locator('#header-search .search-suggest__list a').first()).toBeVisible();
      await expectNoViolations(page, testInfo, `search-suggestions-${scheme}`);
    });

    test('table of contents expanded', async ({ page, routes }, testInfo) => {
      await gotoReady(page, requireRoute(test, routes, 'singleRich'));
      const details = page.locator('.toc__details');
      if (!(await details.evaluate((el) => el.open))) {
        await page.locator('.toc__summary').click();
      }
      await expect(details).toHaveJSProperty('open', true);
      await expect(page.locator('.toc__list a').first()).toBeVisible();
      await expectNoViolations(page, testInfo, `toc-${scheme}`);
    });

    test('FAQ item opened', async ({ page, routes }, testInfo) => {
      await gotoReady(page, requireRoute(test, routes, 'singleRich'));
      const second = page.locator('.faq__item').nth(1);
      await second.locator('summary').click();
      await expect(second).toHaveJSProperty('open', true);
      await expect(second.locator('.faq__answer')).toBeVisible();
      await expectNoViolations(page, testInfo, `faq-${scheme}`);
    });
  });
}

// ---------------------------------------------------------------------------------------------------
// 4. Contrast audit (second opinion for what axe cannot decide)
// ---------------------------------------------------------------------------------------------------
async function expectContrast(page, label, root = 'body') {
  const { checked, failures, measuredOnPixels } = await auditContrast(page, { root });
  expect(checked, `text elements audited under ${root}`).toBeGreaterThan(3);
  expect(
    failures,
    `${failures.length} contrast failure(s) on ${page.url()} [${label}] (${checked} elements audited, ${measuredOnPixels} measured on rendered pixels)\n${formatContrastFailures(failures)}`
  ).toEqual([]);
}

for (const scheme of ['light', 'dark']) {
  test.describe(`contrast audit, ${scheme}`, () => {
    test.use({ colorScheme: scheme });

    for (const key of ['home', 'singleRich', 'singleComments', 'categoryHub', 'search', 'searchEmpty', 'notFound', 'author', 'pillar']) {
      test(`${key}`, { tag: '@no-tablet' }, async ({ page, routes, guard }) => {
        const url = requireRoute(test, routes, key);
        await gotoReady(page, url);
        if (key === 'notFound') {
          guard.expectDocument404(page.url());
        }
        await expectTheme(page, scheme);
        await expectContrast(page, `${key}-${scheme}`);
      });
    }

    test('header search suggestions', { tag: '@no-tablet' }, async ({ page, routes }) => {
      await gotoReady(page, requireRoute(test, routes, 'home'));
      await openSearch(page);
      await typeInHeaderSearch(page, 'scooter');
      await expect(page.locator('#header-search .search-suggest__list a').first()).toBeVisible();
      await expectContrast(page, `search-suggestions-${scheme}`, '#header-search');
    });
  });
}
