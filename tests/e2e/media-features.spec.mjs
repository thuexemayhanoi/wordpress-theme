/**
 * MEDIA FEATURES: how the theme behaves under user preferences and output media.
 *
 *  - prefers-reduced-motion: reduce -> no transition/animation longer than ~1ms anywhere (elements and
 *    pseudo-elements), scroll-behavior auto, no hover lift; the control run proves the check can fail;
 *  - forced-colors: active (Windows High Contrast) -> controls keep a border, focus rings stay visible,
 *    icons stay visible; screenshots for review;
 *  - print (assets/src/css/12-print-a11y.css) -> header/footer/navigation/TOC/CTA/related/comments are gone,
 *    the page is black on white (also when dark mode is on), external link URLs are printed, FAQ answers
 *    are printed even when collapsed (checked in the DOM and in a real PDF through pdftotext when installed);
 *  - text size 200%, text spacing (WCAG 1.4.12) and a landscape phone: nothing is lost, clipped or overflowing.
 */
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { test, expect, requireRoute } from './helpers/fixtures.mjs';
import { SCREENSHOT_DIR } from './helpers/env.mjs';
import { slug } from './helpers/routes.mjs';
import { attachJson, clippedText, gotoReady, jumpTo, measureOverflow, note, settleScroll } from './helpers/ui.mjs';

const hasPdftotext = spawnSync('pdftotext', ['-v'], { encoding: 'utf8' }).status !== null;

// ---------------------------------------------------------------------------------------------------
// Reduced motion
// ---------------------------------------------------------------------------------------------------
/** Longest transition or animation duration (ms) found on any element or pseudo-element, plus offenders. */
function motionAudit(limitMs) {
  const ms = (list) => list.split(',').map((part) => {
    const value = part.trim();
    return value.endsWith('ms') ? parseFloat(value) : parseFloat(value) * 1000;
  });
  const offenders = [];
  let longest = 0;
  let withTransition = 0;
  const check = (el, pseudo) => {
    const cs = getComputedStyle(el, pseudo);
    const transitions = cs.transitionProperty === 'none' ? [0] : ms(cs.transitionDuration).map((d, i) => d + (ms(cs.transitionDelay)[i % ms(cs.transitionDelay).length] || 0));
    const animations = cs.animationName === 'none' ? [0] : ms(cs.animationDuration);
    const max = Math.max(...transitions, ...animations);
    if (max > 0) {
      withTransition += 1;
    }
    longest = Math.max(longest, max);
    if (max > limitMs) {
      const cls = typeof el.className === 'string' ? el.className.trim().split(/\s+/)[0] : '';
      offenders.push(`${el.tagName.toLowerCase()}${cls ? '.' + cls : ''}${pseudo || ''} ${max}ms`);
    }
    if (cs.animationName !== 'none' && cs.animationIterationCount !== '1') {
      offenders.push(`${el.tagName.toLowerCase()}${pseudo || ''} animation repeats (${cs.animationIterationCount})`);
    }
  };
  document.querySelectorAll('*').forEach((el) => {
    check(el, null);
    check(el, '::before');
    check(el, '::after');
  });
  return { longest, withTransition, offenders: [...new Set(offenders)].slice(0, 15), scrollBehavior: getComputedStyle(document.documentElement).scrollBehavior };
}

test.describe('prefers-reduced-motion: reduce', () => {
  // reducedMotion / forcedColors are not first-class Playwright Test options: they go through contextOptions.
  test.use({ contextOptions: { reducedMotion: 'reduce' } });

  for (const key of ['home', 'singleRich', 'categoryHub']) {
    test(`${key}: no transition or animation longer than 1ms, scroll-behavior auto`, async ({ page, routes }) => {
      await gotoReady(page, requireRoute(test, routes, key));
      expect(await page.evaluate(() => window.matchMedia('(prefers-reduced-motion: reduce)').matches), 'reduced motion is really emulated').toBe(true);
      const audit = await page.evaluate(motionAudit, 1);
      expect(audit.scrollBehavior, 'html scroll-behavior').toBe('auto');
      expect(audit.offenders, `longest motion found: ${audit.longest}ms`).toEqual([]);
    });
  }

  test('hover does not move cards and TOC jumps are instant @desktop-only', async ({ page, routes }) => {
    await gotoReady(page, requireRoute(test, routes, 'home'));
    expect(await page.evaluate(() => window.matchMedia('(prefers-reduced-motion: reduce)').matches), 'reduced motion is really emulated').toBe(true);
    const card = page.locator('article.card--post').first();
    await card.scrollIntoViewIfNeeded();
    await card.hover();
    await page.waitForTimeout(150);
    expect(await card.evaluate((el) => getComputedStyle(el).transform), 'no hover lift').toBe('none');
    // (The image's hover scale is an instant state change once transitions are ~0ms, not an animation.)

    await gotoReady(page, requireRoute(test, routes, 'singleRich'));
    const link = page.locator('.toc__list a').nth(3);
    const before = await page.evaluate(() => window.scrollY);
    await link.click();
    await page.waitForTimeout(60); // a smooth scroll would still be travelling here
    const early = await page.evaluate(() => window.scrollY);
    const final = await settleScroll(page);
    expect(before, 'the page did move').toBeLessThan(final);
    expect(early, 'the jump was already complete 60ms after the click').toBe(final);
  });
});

test('control: with no preference the theme does animate (so the reduced-motion check can fail)', async ({ page, routes }) => {
  await gotoReady(page, requireRoute(test, routes, 'home'));
  const audit = await page.evaluate(motionAudit, 1);
  expect(audit.scrollBehavior).toBe('smooth');
  expect(audit.longest, 'some element has a real transition by default').toBeGreaterThan(100);
  expect(audit.offenders.length, 'and the audit reports them').toBeGreaterThan(0);
});

// ---------------------------------------------------------------------------------------------------
// Forced colors (Windows High Contrast)
// ---------------------------------------------------------------------------------------------------
test.describe('forced-colors: active', () => {
  test.use({ contextOptions: { forcedColors: 'active' } });

  test('controls keep a visible border, icons keep a visible colour and focus rings remain', async ({ page, routes }, testInfo) => {
    await gotoReady(page, requireRoute(test, routes, 'home'));
    expect(await page.evaluate(() => window.matchMedia('(forced-colors: active)').matches), 'forced colors really emulated').toBe(true);

    const controls = {
      'search toggle': '.search-toggle',
      'dark mode toggle': '.mode-toggle',
      'menu toggle': '.nav-toggle',
      'hero search button': '.home-intro .search-submit',
      'hero search field': '.home-intro .search-field',
      'topic chip': '.home-intro .chip',
      'card': 'article.card--post',
      'pagination link': '.pagination a.page-numbers',
      'current page': '.pagination .page-numbers.current',
      'call to action': '.cta__button',
      'brand mark': '.brand__mark',
    };
    const facts = [];
    for (const [name, selector] of Object.entries(controls)) {
      const locator = page.locator(selector).first();
      if (!(await locator.isVisible())) {
        continue;
      }
      const info = await locator.evaluate((el) => {
        const cs = getComputedStyle(el);
        const alpha = (css) => {
          const m = css.match(/rgba?\(([^)]+)\)/);
          if (!m) return 1;
          const p = m[1].split(/[ ,/]+/).filter(Boolean).map(Number);
          return p.length > 3 ? p[3] : 1;
        };
        return {
          borderStyle: cs.borderTopStyle,
          borderWidth: parseFloat(cs.borderTopWidth),
          borderAlpha: alpha(cs.borderTopColor),
          borderColor: cs.borderTopColor,
          forcedAdjust: cs.forcedColorAdjust,
        };
      });
      facts.push({ name, ...info });
    }
    await attachJson(testInfo, 'forced-colors-borders', facts);
    expect(facts.length, 'control kinds exercised').toBeGreaterThan(6);
    for (const fact of facts) {
      expect(fact.borderStyle, `${fact.name}: border style`).not.toBe('none');
      expect(fact.borderWidth, `${fact.name}: border width`).toBeGreaterThanOrEqual(1);
      expect(fact.borderAlpha, `${fact.name}: border colour (${fact.borderColor}) must not be transparent`).toBeGreaterThan(0);
    }

    // Icons draw with currentColor: it must differ from the button background (forced system colours).
    const icons = await page.evaluate(() =>
      [...document.querySelectorAll('.icon-btn:not([hidden])')]
        .filter((el) => el.getBoundingClientRect().width > 0)
        .map((el) => ({ name: el.className, color: getComputedStyle(el.querySelector('svg')).color, background: getComputedStyle(el).backgroundColor }))
    );
    expect(icons.length).toBeGreaterThan(1);
    for (const icon of icons) {
      expect(icon.color, `icon colour on ${icon.name}`).not.toBe(icon.background);
    }

    // Focus rings: keyboard focus must still produce a visible outline.
    for (const selector of ['.search-toggle', '.home-intro .search-submit', '.card__title a', '.home-intro .chip']) {
      await page.keyboard.press('Tab');
      await page.locator(selector).first().focus();
      const ring = await page.locator(selector).first().evaluate((el) => {
        const cs = getComputedStyle(el);
        return { style: cs.outlineStyle, width: parseFloat(cs.outlineWidth), color: cs.outlineColor, visible: el.matches(':focus-visible') };
      });
      expect(ring.visible, `${selector}: focus-visible`).toBe(true);
      expect(ring.style, `${selector}: outline style`).not.toBe('none');
      expect(ring.width, `${selector}: outline width`).toBeGreaterThanOrEqual(2);
    }
  });

  for (const key of ['home', 'singleRich']) {
    test(`screenshot of ${key} for review`, async ({ page, routes }, testInfo) => {
      await gotoReady(page, requireRoute(test, routes, key));
      const dir = path.join(SCREENSHOT_DIR, 'forced-colors');
      fs.mkdirSync(dir, { recursive: true });
      await page.keyboard.press('Tab');
      await page.keyboard.press('Tab');
      await page.screenshot({ path: path.join(dir, `${testInfo.project.name}-${slug(key)}.png`), animations: 'disabled' });
    });
  }
});

// ---------------------------------------------------------------------------------------------------
// Print
// ---------------------------------------------------------------------------------------------------
test.describe('print @desktop-only', () => {
  const HIDDEN_IN_PRINT = ['.site-header', '.site-footer', '.toc', '.cta', '.post-nav', '.related', '.widget-area', '.comments-area', '.pagination-wrap', '.skip-link', '.header-search'];

  test.beforeEach(async ({ page }) => {
    await page.setViewportSize({ width: 794, height: 1123 }); // A4 at 96 dpi
  });

  test('chrome is hidden, the page is black on white and images are not cropped (single article)', async ({ page, routes }) => {
    await gotoReady(page, requireRoute(test, routes, 'singleRich'));
    await page.emulateMedia({ media: 'print' });

    const state = await page.evaluate((selectors) => {
      const hidden = selectors.flatMap((selector) => [...document.querySelectorAll(selector)].map((el) => ({ selector, display: getComputedStyle(el).display })));
      const body = getComputedStyle(document.body);
      const img = document.querySelector('.article-media img');
      return {
        hidden,
        bodyBackground: body.backgroundColor,
        bodyColor: body.color,
        bodyFont: body.fontSize,
        mediaMaxHeight: img ? getComputedStyle(img).maxHeight : null,
        links: [...document.querySelectorAll('.prose a, .sources a')].map((a) => getComputedStyle(a).color),
        overflow: document.documentElement.scrollWidth - window.innerWidth,
      };
    }, HIDDEN_IN_PRINT);

    expect(state.hidden.length, 'print-hidden components present on the page').toBeGreaterThan(4);
    expect(state.hidden.filter((item) => item.display !== 'none').map((item) => item.selector), 'components still displayed in print').toEqual([]);
    expect(state.bodyBackground).toBe('rgb(255, 255, 255)');
    expect(state.bodyColor).toBe('rgb(0, 0, 0)');
    expect(state.bodyFont, '12pt body text').toBe('16px');
    expect(state.mediaMaxHeight, 'the featured image is not cropped on paper').toBe('none');
    expect(state.links.length, 'links in the article text, FAQ answers and sources').toBeGreaterThan(0);
    expect([...new Set(state.links)], 'links are plain black on paper').toEqual(['rgb(0, 0, 0)']);
    expect(state.overflow, 'nothing is wider than the A4 page').toBeLessThanOrEqual(0);
  });

  test('external link addresses are printed after the link text', async ({ page, routes }, testInfo) => {
    await gotoReady(page, requireRoute(test, routes, 'singleRich'));
    await page.emulateMedia({ media: 'print' });
    const links = await page.evaluate(() =>
      [...document.querySelectorAll('.prose a[href^="http"]')].map((a) => ({
        href: a.href,
        content: getComputedStyle(a, '::after').content,
        external: new URL(a.href).origin !== location.origin,
      }))
    );
    const external = links.filter((link) => link.external);
    expect(external.length, 'external links inside the article text').toBeGreaterThan(0);
    for (const link of external) {
      expect(link.content, `::after of ${link.href}`).toContain(link.href);
    }
    note(testInfo, 'print-links', `${external.length} external and ${links.length - external.length} same-site links in prose print their URL (the rule matches every absolute http(s) link)`);
  });

  test('every FAQ answer is printed, including the collapsed ones', async ({ page, routes }) => {
    await gotoReady(page, requireRoute(test, routes, 'singleRich'));
    await page.emulateMedia({ media: 'print' });
    const answers = await page.evaluate(() =>
      [...document.querySelectorAll('.faq__item')].map((item) => {
        const answer = item.querySelector('.faq__answer');
        return { open: item.open, text: answer.textContent.trim().slice(0, 40), rendered: answer.checkVisibility({ contentVisibilityAuto: true, visibilityProperty: true }) };
      })
    );
    expect(answers.length).toBeGreaterThan(2);
    expect(answers.filter((a) => !a.rendered).map((a) => a.text), 'FAQ answers that would not be printed (collapsed <details>)').toEqual([]);
  });

  test('a real PDF of the article contains all of it and none of the page chrome (needs pdftotext)', async ({ page, routes }, testInfo) => {
    test.skip(!hasPdftotext, 'pdftotext (poppler-utils) is not installed: the DOM-level print checks above still ran');
    await gotoReady(page, requireRoute(test, routes, 'singleRich'));
    const pdf = testInfo.outputPath('article.pdf');
    await page.emulateMedia({ media: 'print' });
    await page.pdf({ path: pdf, format: 'A4', printBackground: true });
    const text = spawnSync('pdftotext', ['-layout', pdf, '-'], { encoding: 'utf8' }).stdout;

    const facts = await page.evaluate(() => ({
      title: document.querySelector('h1').textContent.trim(),
      headings: [...document.querySelectorAll('.entry-content h2')].map((h) => h.textContent.trim()),
      takeaway: document.querySelector('.takeaways__list li').textContent.trim(),
      faq: [...document.querySelectorAll('.faq__item')].map((item) => ({
        question: item.querySelector('.faq__question').textContent.trim(),
        answer: item.querySelector('.faq__answer').textContent.trim().replace(/\s+/g, ' ').split(' ').slice(0, 6).join(' '),
      })),
      source: document.querySelector('.sources__list a').textContent.trim(),
    }));
    const flat = text.replace(/\s+/g, ' ');
    expect(flat, 'article title').toContain(facts.title);
    for (const heading of facts.headings) {
      expect(flat, `heading "${heading}"`).toContain(heading);
    }
    expect(flat, 'first key takeaway').toContain(facts.takeaway);
    expect(flat, 'first source').toContain(facts.source);
    const missingAnswers = facts.faq.filter((item) => !flat.includes(item.answer)).map((item) => item.question);
    expect(missingAnswers, 'FAQ questions whose answer is missing from the PDF').toEqual([]);

    for (const chrome of ['Skip to content', 'Toggle submenu', 'All rights reserved', 'Related articles', 'Message us']) {
      expect(flat, `"${chrome}" belongs to the screen, not to paper`).not.toContain(chrome);
    }
  });

  test('printing while dark mode is on still gives dark text on white paper', async ({ page, routes }) => {
    await gotoReady(page, requireRoute(test, routes, 'singleRich'));
    await page.locator('.mode-toggle').click();
    expect(await page.evaluate(() => document.documentElement.getAttribute('data-theme'))).toBe('dark');
    await page.emulateMedia({ media: 'print', colorScheme: 'dark' });

    const faint = await page.evaluate(() => {
      const lum = (c) => {
        const m = c.match(/rgba?\(([^)]+)\)/).slice(1)[0].split(/[ ,/]+/).map(Number);
        const ch = (v) => ((v / 255) <= 0.03928 ? v / 255 / 12.92 : (((v / 255) + 0.055) / 1.055) ** 2.4);
        return 0.2126 * ch(m[0]) + 0.7152 * ch(m[1]) + 0.0722 * ch(m[2]);
      };
      const bad = [];
      document.querySelectorAll('h1, h2, h3, .prose p, .prose li, .meta, .breadcrumbs, .summary__text, .takeaways li, .faq__question, .faq__answer, .sources li, .author-box').forEach((el) => {
        const box = el.getBoundingClientRect();
        if (getComputedStyle(el).display === 'none' || box.width === 0) return;
        const ratio = (1 + 0.05) / (lum(getComputedStyle(el).color) + 0.05); // text on pure white paper
        if (ratio < 4.5) bad.push(`${el.tagName.toLowerCase()}.${(el.className || '').toString().split(' ')[0]} ${getComputedStyle(el).color} (${ratio.toFixed(2)}:1 on white)`);
      });
      return [...new Set(bad)].slice(0, 12);
    });
    expect(faint, 'text that is too light for white paper when dark mode is active').toEqual([]);
  });

  test('a category listing prints without the controls (no search form, no pagination, no header)', async ({ page, routes }) => {
    await gotoReady(page, requireRoute(test, routes, 'categoryHub'));
    await page.emulateMedia({ media: 'print' });
    const shown = await page.evaluate(() =>
      ['.site-header', '.site-footer', '.pagination-wrap', '.skip-link'].filter((selector) => {
        const el = document.querySelector(selector);
        return el && getComputedStyle(el).display !== 'none';
      })
    );
    expect(shown).toEqual([]);
    await expect(page.locator('h1')).toBeVisible();
  });
});

// ---------------------------------------------------------------------------------------------------
// Text size, text spacing, landscape phone
// ---------------------------------------------------------------------------------------------------
test.describe('text size and spacing', () => {
  test('text at 200%: no overflow, header controls usable, nothing cut off (WCAG 1.4.4) @mobile-only', async ({ page, routes }, testInfo) => {
    for (const key of ['home', 'singleRich']) {
      for (const width of [375, 320]) {
        await page.setViewportSize({ width, height: 700 });
        await gotoReady(page, requireRoute(test, routes, key));
        await page.addStyleTag({ content: 'html{font-size:200% !important}' });
        await page.waitForTimeout(150);

        const overflow = await measureOverflow(page);
        expect(overflow.scrollWidth, `${key} @${width}px with 200% text: ${JSON.stringify(overflow.offenders)}`).toBeLessThanOrEqual(overflow.innerWidth);

        const header = await page.evaluate(() => {
          const rect = (selector) => {
            const el = document.querySelector(selector);
            const box = el.getBoundingClientRect();
            return { left: box.left, right: box.right, top: box.top, bottom: box.bottom, width: box.width, height: box.height };
          };
          return { header: rect('.site-header'), toggle: rect('.nav-toggle'), search: rect('.search-toggle'), brand: rect('.brand'), vh: window.innerHeight };
        });
        expect(header.toggle.right, 'menu button inside the viewport').toBeLessThanOrEqual(width);
        expect(header.search.left, 'search button inside the viewport').toBeGreaterThanOrEqual(0);
        expect(header.brand.right, 'brand does not run under the buttons').toBeLessThanOrEqual(header.search.left + 1);
        expect(header.header.height, `the sticky header must stay a small part of the screen (${Math.round(header.header.height)}px of ${header.vh}px)`).toBeLessThanOrEqual(header.vh * 0.35);

        await page.locator('.nav-toggle').click();
        await expect(page.locator('#primary-navigation')).toHaveClass(/is-open/);
        const drawer = await page.locator('#primary-navigation').boundingBox();
        expect(drawer.x + drawer.width, 'drawer inside the viewport').toBeLessThanOrEqual(width + 1);
        await page.locator('.nav-toggle').click();

        const { clipped, ellipsized } = await page.evaluate(clippedText);
        if (ellipsized.length > 0) {
          note(testInfo, 'ellipsis-200', `${key} @${width}px (by design, shortened with an ellipsis): ${ellipsized.join(' | ')}`);
        }
        expect(clipped, `cut-off text at 200% (${key} @${width}px)`).toEqual([]);
      }
    }
  });

  test('200% browser zoom on a phone (188px wide layout): reported, not asserted (below the 320px reflow baseline) @mobile-only', async ({ page, routes }, testInfo) => {
    await page.setViewportSize({ width: 188, height: 406 });
    await gotoReady(page, requireRoute(test, routes, 'home'));
    const overflow = await measureOverflow(page);
    note(testInfo, 'zoom-200', `home at a 188px layout width overflows by ${overflow.overflowPx}px ${overflow.offenders.map((o) => o.element).slice(0, 4).join(', ')}`);
    await expect(page.locator('h1')).toBeVisible();
    await expect(page.locator('.nav-toggle')).toBeVisible();
  });

  for (const key of ['home', 'singleRich', 'categoryHub']) {
    test(`${key}: text spacing overrides (WCAG 1.4.12) cause no overflow or cut-off text`, async ({ page, routes }, testInfo) => {
      await gotoReady(page, requireRoute(test, routes, key));
      await page.addStyleTag({
        content: '*{line-height:1.5 !important;letter-spacing:0.12em !important;word-spacing:0.16em !important} p{margin-bottom:2em !important}',
      });
      await page.waitForTimeout(150);
      const overflow = await measureOverflow(page);
      expect(overflow.scrollWidth, JSON.stringify(overflow.offenders)).toBeLessThanOrEqual(overflow.innerWidth);
      const { clipped, ellipsized } = await page.evaluate(clippedText);
      if (ellipsized.length > 0) {
        note(testInfo, 'ellipsis-spacing', ellipsized.join(' | '));
      }
      expect(clipped, 'cut-off text with the 1.4.12 spacing').toEqual([]);
    });
  }

  test('a phone in landscape (812x375): no overflow, a header that leaves room, a drawer that fits @mobile-only', async ({ page, routes }) => {
    await page.setViewportSize({ width: 812, height: 375 });
    await gotoReady(page, requireRoute(test, routes, 'singleRich'));
    const overflow = await measureOverflow(page);
    expect(overflow.scrollWidth).toBeLessThanOrEqual(overflow.innerWidth);

    const header = await page.locator('.site-header').boundingBox();
    expect(header.height, 'sticky header height in a 375px tall viewport').toBeLessThanOrEqual(375 * 0.25);

    await page.locator('.nav-toggle').click();
    const drawer = await page.locator('#primary-navigation').boundingBox();
    expect(drawer.y + drawer.height, 'drawer bottom edge inside the viewport').toBeLessThanOrEqual(375 + 1);
    // Every top-level link can be reached by scrolling inside the drawer.
    const last = page.locator('#primary-navigation .nav-list > li > a').last();
    await last.scrollIntoViewIfNeeded();
    await expect(last).toBeVisible();
    await jumpTo(page, 0);
  });
});
