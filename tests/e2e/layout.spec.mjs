/**
 * LAYOUT: nothing overflows, the sticky header behaves, focus is never hidden behind it, images reserve
 * their space.
 *
 *  - no horizontal overflow on every page type at the project viewport (375 / 768 / 1440) and, for the
 *    mobile project, at 320 px (WCAG 2.2 SC 1.4.10 Reflow), including a very long unbroken search phrase;
 *  - a sweep over both sides of every CSS breakpoint of the theme: overflow, header never taller than the
 *    scroll padding the CSS reserves for it (84 px), no overlapping header parts;
 *  - the sticky header stays at the top and visible while scrolling;
 *  - WCAG 2.2 SC 2.4.11 Focus Not Obscured: tabbing through a page never lands on an element that is
 *    entirely painted over by the sticky header (or anything else);
 *  - images: rendered size, declared width/height, a single prioritised (LCP) image, everything else lazy;
 *  - CLS measured on three cold loads of home / single / category; asserted (<= 0.1) only when the three
 *    values agree within 0.02, otherwise reported as unstable (the rule the owner set).
 */
import { test, expect, requireRoute } from './helpers/fixtures.mjs';
import { PAGE_TYPES } from './helpers/routes.mjs';
import { BREAKPOINT_WIDTHS, REFLOW_WIDTH } from './helpers/env.mjs';
import {
  attachJson,
  focusWalk,
  gotoReady,
  headerRect,
  jumpTo,
  layoutShiftInit,
  loadAllImages,
  measureOverflow,
  newProjectContext,
  note,
  waitForStyles,
} from './helpers/ui.mjs';

/** Height the CSS reserves above anchored/focused content: --header-h (68) + 16, see 01-base.css. */
const SCROLL_PADDING_TOP = 84;

const describeOverflow = (o) => `scrollWidth ${o.scrollWidth} > innerWidth ${o.innerWidth}; offenders: ${JSON.stringify(o.offenders)}`;

// ---------------------------------------------------------------------------------------------------
// Horizontal overflow
// ---------------------------------------------------------------------------------------------------
for (const type of PAGE_TYPES) {
  test(`${type.label}: no horizontal overflow at the project viewport`, async ({ page, routes, guard }) => {
    const url = requireRoute(test, routes, type.key);
    await gotoReady(page, url);
    if (type.status === 404) {
      guard.expectDocument404(page.url());
    }
    await loadAllImages(page);
    const overflow = await measureOverflow(page);
    expect(overflow.scrollWidth, describeOverflow(overflow)).toBeLessThanOrEqual(overflow.innerWidth);
  });
}

for (const type of PAGE_TYPES) {
  test(`${type.label}: reflows at ${REFLOW_WIDTH}px without horizontal scrolling @mobile-only`, async ({ page, routes, guard }) => {
    const url = requireRoute(test, routes, type.key);
    await page.setViewportSize({ width: REFLOW_WIDTH, height: 640 });
    await gotoReady(page, url);
    if (type.status === 404) {
      guard.expectDocument404(page.url());
    }
    await loadAllImages(page);
    const overflow = await measureOverflow(page);
    expect(overflow.innerWidth).toBe(REFLOW_WIDTH);
    expect(overflow.scrollWidth, describeOverflow(overflow)).toBeLessThanOrEqual(overflow.innerWidth);
  });
}

test.describe('hostile search phrases do not break the layout', () => {
  const phrases = {
    'one unbroken 120 character word': 'a'.repeat(120),
    'a 300 character sentence': 'motorbike rental price '.repeat(14).trim(),
    'markup and quotes': `"><img src=x onerror=window.__xss=1> <b>bold</b> & more`,
  };
  for (const [label, phrase] of Object.entries(phrases)) {
    test(`search for ${label}`, async ({ page, guard }) => {
      await gotoReady(page, `/?s=${encodeURIComponent(phrase)}`);
      await loadAllImages(page);
      const overflow = await measureOverflow(page);
      expect(overflow.scrollWidth, describeOverflow(overflow)).toBeLessThanOrEqual(overflow.innerWidth);
      expect(await page.evaluate(() => window.__xss), 'the phrase must not execute').toBeUndefined();
      guard.assertClean();
    });
  }
});

test.describe('breakpoint sweep @once', () => {
  const pages = ['home', 'singleRich', 'categoryHub', 'search'];
  for (const key of pages) {
    test(`${key}: both sides of every breakpoint (overflow, header height, header overlap)`, async ({ page, routes }, testInfo) => {
      const url = requireRoute(test, routes, key);
      await page.setViewportSize({ width: 1440, height: 800 });
      await gotoReady(page, url);
      await loadAllImages(page);

      const problems = [];
      const table = [];
      for (const width of BREAKPOINT_WIDTHS) {
        await page.setViewportSize({ width, height: 800 });
        await page.waitForTimeout(120);

        const overflow = await measureOverflow(page);
        const header = await page.evaluate(() => {
          const rect = (selector) => {
            const el = document.querySelector(selector);
            if (!el) {
              return null;
            }
            const box = el.getBoundingClientRect();
            return getComputedStyle(el).display === 'none' || box.width === 0 ? null : { left: box.left, right: box.right, top: box.top, bottom: box.bottom };
          };
          const items = [...document.querySelectorAll('.nav-list > li')].filter((li) => li.getBoundingClientRect().width > 0);
          const rows = new Set(items.map((li) => Math.round(li.getBoundingClientRect().top)));
          return {
            height: document.querySelector('.site-header').getBoundingClientRect().height,
            brand: rect('.header-row .brand'),
            nav: rect('.header-row .primary-nav'),
            actions: rect('.header-row .header-actions'),
            navRows: rows.size,
          };
        });

        const parts = Object.entries({ brand: header.brand, nav: header.nav, actions: header.actions }).filter(([, box]) => box);
        for (let i = 0; i < parts.length; i += 1) {
          for (let j = i + 1; j < parts.length; j += 1) {
            const a = parts[i][1];
            const b = parts[j][1];
            const overlapX = Math.min(a.right, b.right) - Math.max(a.left, b.left);
            const overlapY = Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top);
            if (overlapX > 1 && overlapY > 1) {
              problems.push(`${width}px: header parts "${parts[i][0]}" and "${parts[j][0]}" overlap by ${Math.round(overlapX)}x${Math.round(overlapY)}px`);
            }
          }
        }
        if (overflow.scrollWidth > overflow.innerWidth) {
          problems.push(`${width}px: horizontal overflow ${overflow.overflowPx}px (${overflow.offenders.map((o) => o.element).join(', ')})`);
        }
        if (header.height > SCROLL_PADDING_TOP) {
          problems.push(
            `${width}px: sticky header is ${Math.round(header.height)}px tall (nav on ${header.navRows} row(s)) but the CSS only reserves ${SCROLL_PADDING_TOP}px ` +
              '(scroll-padding-top / scroll-margin-top): anchored and focused content is hidden under it'
          );
        }
        table.push({ width, headerHeight: Math.round(header.height), navRows: header.navRows, overflowPx: overflow.overflowPx });
      }
      await attachJson(testInfo, 'breakpoint-table', table);
      // Not a failure (the header still fits the 84px reserve) but worth seeing in every report: the nav
      // list wraps onto more than one row at some desktop widths (a visual quality matter, see visual spec).
      const wrapped = table.filter((row) => row.navRows > 1).map((row) => row.width);
      if (wrapped.length > 0) {
        note(testInfo, 'nav-wraps', `primary navigation wraps to 2+ rows at widths: ${wrapped.join(', ')}px`);
      }
      expect(problems, problems.join('\n')).toEqual([]);
    });
  }
});

// ---------------------------------------------------------------------------------------------------
// Sticky header
// ---------------------------------------------------------------------------------------------------
test('the header is sticky: it stays at the top and on top while scrolling', async ({ page, routes }) => {
  const url = requireRoute(test, routes, 'singleRich');
  await gotoReady(page, url);

  const header = page.locator('.site-header');
  await expect(header).not.toHaveClass(/is-scrolled/);
  expect(await header.evaluate((el) => getComputedStyle(el).position)).toBe('sticky');

  const total = await page.evaluate(() => document.documentElement.scrollHeight - window.innerHeight);
  expect(total, 'the page must be long enough to scroll').toBeGreaterThan(1500);

  for (const y of [400, 1500, Math.floor(total / 2), total]) {
    await jumpTo(page, y);
    const rect = await headerRect(page);
    expect(Math.abs(rect.top), `header top at scrollY=${y}`).toBeLessThan(1.5);
    const onTop = await page.evaluate(() => {
      const el = document.elementFromPoint(window.innerWidth / 2, 10);
      return Boolean(el && el.closest('.site-header'));
    });
    expect(onTop, `the header paints above the content at scrollY=${y}`).toBe(true);
    await expect(header).toHaveClass(/is-scrolled/);
  }

  await jumpTo(page, 0);
  await expect(header).not.toHaveClass(/is-scrolled/);
});

// ---------------------------------------------------------------------------------------------------
// WCAG 2.2 SC 2.4.11 Focus Not Obscured (Minimum)
// ---------------------------------------------------------------------------------------------------
for (const key of ['home', 'singleRich', 'categoryHub', 'singleComments', 'search']) {
  test(`${key}: keyboard focus is never entirely hidden behind the sticky header`, async ({ page, routes }, testInfo) => {
    const url = requireRoute(test, routes, key);
    await gotoReady(page, url);

    const stops = await focusWalk(page, { max: 200 });
    expect(stops.length, 'Tab stops found (a vacuous walk proves nothing)').toBeGreaterThan(10);

    const obscured = stops.filter((stop) => stop.entirelyObscured);
    const partial = stops.filter((stop) => stop.partiallyObscured);
    const offscreen = stops.filter((stop) => !stop.inViewport);
    await attachJson(testInfo, 'focus-walk', stops);
    note(testInfo, 'focus', `${stops.length} tab stops; ${partial.length} partially covered; ${offscreen.length} outside the viewport`);

    expect(
      obscured.map((stop) => `${stop.label} is covered by ${stop.coveredBy}`),
      'focused elements entirely covered by other content'
    ).toEqual([]);
    expect(
      offscreen.map((stop) => stop.label),
      'focused elements that are not inside the viewport (focus indicator not visible)'
    ).toEqual([]);
  });
}

// ---------------------------------------------------------------------------------------------------
// Images
// ---------------------------------------------------------------------------------------------------
for (const key of ['home', 'singleRich', 'categoryHub', 'singleComments']) {
  test(`${key}: images render, reserve their space, and only one is prioritised`, async ({ page, routes }) => {
    const url = requireRoute(test, routes, key);
    await gotoReady(page, url);

    // Read the loading hints first: loadAllImages() flips lazy images to eager.
    const hints = await page.evaluate(() =>
      [...document.images].map((img) => ({
        src: img.currentSrc || img.src,
        loading: img.getAttribute('loading'),
        fetchpriority: img.getAttribute('fetchpriority'),
        width: img.getAttribute('width'),
        height: img.getAttribute('height'),
        alt: img.getAttribute('alt'),
        decoding: img.getAttribute('decoding'),
        // Post thumbnails are printed by the theme (nh_editorial_thumbnail); avatars come from WordPress core.
        themeThumbnail: img.classList.contains('wp-post-image'),
      }))
    );
    await loadAllImages(page);
    const rendered = await page.evaluate(() =>
      [...document.images].map((img) => {
        const box = img.getBoundingClientRect();
        return { src: img.currentSrc || img.src, naturalWidth: img.naturalWidth, width: Math.round(box.width), height: Math.round(box.height) };
      })
    );

    expect(hints.length, 'the page should contain images').toBeGreaterThan(0);

    const broken = rendered.filter((img) => img.naturalWidth === 0 || img.width === 0 || img.height === 0);
    expect(broken, 'images that failed to load or have no rendered size').toEqual([]);

    const noAlt = hints.filter((img) => img.alt === null);
    expect(noAlt.map((img) => img.src), 'images without an alt attribute').toEqual([]);

    const noDimensions = hints.filter((img) => !(Number(img.width) > 0 && Number(img.height) > 0));
    expect(noDimensions.map((img) => img.src), 'images without width/height attributes (they cause layout shift)').toEqual([]);

    const prioritised = hints.filter((img) => img.fetchpriority === 'high');
    expect(prioritised.length, 'images with fetchpriority=high (exactly one LCP candidate at most)').toBeLessThanOrEqual(1);
    for (const img of prioritised) {
      expect(img.loading, 'the prioritised image must not be lazy').not.toBe('lazy');
    }
    const eagerOthers = hints.filter((img) => img.fetchpriority !== 'high' && img.loading !== 'lazy');
    expect(eagerOthers.map((img) => img.src), 'non-LCP images that are not lazy').toEqual([]);
    // Only the theme's own thumbnails: core avatars carry no decoding hint and are tiny.
    const syncDecoding = hints.filter((img) => img.themeThumbnail && img.decoding !== 'async');
    expect(syncDecoding.map((img) => img.src), 'theme thumbnails without decoding="async"').toEqual([]);
  });
}

// ---------------------------------------------------------------------------------------------------
// Cumulative Layout Shift
// ---------------------------------------------------------------------------------------------------
for (const key of ['home', 'singleRich', 'categoryHub']) {
  test(`${key}: layout shift (CLS) over three cold loads`, async ({ browser, routes }, testInfo) => {
    const url = requireRoute(test, routes, key);
    const runs = [];

    for (let run = 0; run < 3; run += 1) {
      // A new context per run = a cold cache, the conservative case (late CSS and images shift more).
      const context = await newProjectContext(browser, testInfo);
      const page = await context.newPage();
      await page.addInitScript(layoutShiftInit);
      await page.goto(url, { waitUntil: 'load' });
      await waitForStyles(page);
      await page.waitForTimeout(500);

      // Read the whole page the way a visitor does, so lazy images and below-the-fold content are included.
      const total = await page.evaluate(() => document.documentElement.scrollHeight);
      const step = Math.max(200, Math.floor((page.viewportSize()?.height || 800) * 0.8));
      for (let y = 0; y < total; y += step) {
        await jumpTo(page, y);
        await page.waitForTimeout(90);
      }
      await jumpTo(page, 0);
      await page.waitForTimeout(500);

      runs.push(await page.evaluate(() => ({ cls: window.__cls, entries: window.__clsEntries })));
      await context.close();
    }

    const values = runs.map((r) => Number(r.cls.toFixed(4)));
    const spread = Math.max(...values) - Math.min(...values);
    const stable = spread <= 0.02;
    await attachJson(testInfo, 'cls-runs', runs);
    note(testInfo, 'CLS', `${key} [${testInfo.project.name}] runs=${JSON.stringify(values)} spread=${spread.toFixed(4)} stable=${stable}`);

    expect(values.every((v) => v >= 0), 'the layout-shift observer must be supported').toBe(true);
    if (stable) {
      expect(Math.max(...values), `CLS ${JSON.stringify(values)}; shifted nodes: ${JSON.stringify(runs[0].entries)}`).toBeLessThanOrEqual(0.1);
    } else {
      note(testInfo, 'CLS-unstable', `Not asserted: the three runs differ by more than 0.02 (${JSON.stringify(values)}).`);
    }
  });
}
