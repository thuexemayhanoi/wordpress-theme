/**
 * BEHAVIOR: colour mode, table of contents, FAQ, tables, comment form, cards, focus indicators and the
 * safety of stored content.
 *
 * Colour mode (assets/src/js/color-mode.js + the inline script in <head>):
 *  - the toggle updates aria-pressed, data-theme on <html> and localStorage ("nh-editorial-color-mode");
 *  - the choice survives navigation and reload and beats the operating system setting;
 *  - NO FLASH: data-theme is set while the document is still being parsed (before <body> exists, hence
 *    before first paint) and never flips again by itself;
 *  - prefers-color-scheme is honoured when nothing is stored and followed live until the visitor chooses;
 *  - unusable storage (private mode) leaves a working page.
 *
 * TOC (assets/src/js/toc.js, template-parts/components/toc.php): open on wide screens, collapsed on small
 * ones; every link targets a heading id; activating a link brings the heading into view below the sticky
 * header, focuses it and marks it current; a pasted deep link lands correctly too.
 */
import { test, expect, requireRoute } from './helpers/fixtures.mjs';
import { attachJson, gotoReady, jumpTo, note, settleScroll, themeTraceInit } from './helpers/ui.mjs';

const KEY = 'nh-editorial-color-mode';
const toggle = (page) => page.locator('.mode-toggle');
const themeOf = (page) => page.evaluate(() => document.documentElement.getAttribute('data-theme'));
const storedOf = (page) => page.evaluate((key) => window.localStorage.getItem(key), KEY);
const bodyBackground = (page) => page.evaluate(() => getComputedStyle(document.body).backgroundColor);

// ---------------------------------------------------------------------------------------------------
// Colour mode
// ---------------------------------------------------------------------------------------------------
test.describe('colour mode', () => {
  test('the toggle updates aria-pressed, data-theme, localStorage and the real colours (pointer and keyboard)', async ({ page, routes }) => {
    await gotoReady(page, requireRoute(test, routes, 'home'));
    const button = toggle(page);

    expect(await themeOf(page), 'starts light with a light OS and nothing stored').toBe('light');
    await expect(button).toHaveAttribute('aria-pressed', 'false');
    expect(await storedOf(page), 'nothing is stored before the visitor chooses').toBeNull();
    expect(await bodyBackground(page)).toBe('rgb(255, 255, 255)');

    await button.click();
    expect(await themeOf(page)).toBe('dark');
    await expect(button).toHaveAttribute('aria-pressed', 'true');
    expect(await storedOf(page)).toBe('dark');
    expect(await bodyBackground(page), 'dark palette is really applied').toBe('rgb(11, 15, 23)');
    expect(await page.evaluate(() => getComputedStyle(document.documentElement).colorScheme), 'native controls follow').toBe('dark');

    await button.click();
    expect(await themeOf(page)).toBe('light');
    await expect(button).toHaveAttribute('aria-pressed', 'false');
    expect(await storedOf(page)).toBe('light');
    expect(await bodyBackground(page)).toBe('rgb(255, 255, 255)');

    // Keyboard: Enter and Space both operate it; the accessible name does not change with the state.
    await button.focus();
    await page.keyboard.press('Enter');
    expect(await themeOf(page), 'Enter toggles').toBe('dark');
    await page.keyboard.press('Space');
    expect(await themeOf(page), 'Space toggles').toBe('light');
    expect((await button.textContent()).trim(), 'the name stays "Dark mode"; aria-pressed carries the state').toBe('Dark mode');
  });

  test('the choice persists across navigation (link click), a new page load and a reload', async ({ page, routes }) => {
    await gotoReady(page, requireRoute(test, routes, 'singleRich'));
    await toggle(page).click();
    expect(await themeOf(page)).toBe('dark');

    // A real link click = a new document.
    await page.locator('.brand').first().click();
    await page.waitForURL(/\/$/);
    expect(await themeOf(page), 'still dark on the next page').toBe('dark');
    await expect(toggle(page)).toHaveAttribute('aria-pressed', 'true');

    await page.goto(routes.categoryHub);
    expect(await themeOf(page), 'still dark after a direct load').toBe('dark');
    await page.reload();
    expect(await themeOf(page), 'still dark after reload').toBe('dark');
    await expect(toggle(page)).toHaveAttribute('aria-pressed', 'true');

    await toggle(page).click();
    await page.reload();
    expect(await themeOf(page), 'an explicit light choice persists too').toBe('light');
    await expect(toggle(page)).toHaveAttribute('aria-pressed', 'false');
  });

  test('no flash of the wrong theme: data-theme is set during parsing, before <body> and before first paint', async ({ page, routes }, testInfo) => {
    // Store a choice first, then reload with the tracer installed (init scripts run on every navigation).
    await gotoReady(page, requireRoute(test, routes, 'home'));
    await toggle(page).click();
    expect(await storedOf(page)).toBe('dark');

    await page.addInitScript(themeTraceInit);
    await page.goto(routes.home, { waitUntil: 'load' });
    await page.waitForTimeout(250);
    const trace = await page.evaluate(() => window.__themeTrace);
    await attachJson(testInfo, 'theme-trace', trace);

    const sets = trace.filter((entry) => entry.why === 'mutation');
    expect(sets.length, 'data-theme is written exactly once during the load (no second flip)').toBe(1);
    expect(sets[0].theme).toBe('dark');
    expect(sets[0].readyState, 'written while the document is still parsing').toBe('loading');
    expect(sets[0].hasBody, 'written before <body> exists, so before anything is painted').toBe(false);

    const paints = trace.filter((entry) => entry.why === 'first-paint' || entry.why === 'first-contentful-paint');
    expect(paints.length, 'paint timing entries were recorded').toBeGreaterThan(0);
    for (const paint of paints) {
      expect(paint.theme, `theme at ${paint.why}`).toBe('dark');
      expect(sets[0].t, `data-theme set before ${paint.why}`).toBeLessThan(paint.t);
    }
    expect(await themeOf(page)).toBe('dark');
  });

  test.describe('dark operating system, nothing stored', () => {
    test.use({ colorScheme: 'dark' });

    test('dark is applied at parse time without flashing, aria-pressed is true, nothing is written to storage', async ({ page, routes }, testInfo) => {
      await page.addInitScript(themeTraceInit);
      await page.goto(requireRoute(test, routes, 'home'), { waitUntil: 'load' });
      await page.waitForTimeout(250);
      const trace = await page.evaluate(() => window.__themeTrace);
      await attachJson(testInfo, 'theme-trace-os-dark', trace);

      const sets = trace.filter((entry) => entry.why === 'mutation');
      expect(sets.length).toBe(1);
      expect(sets[0].theme).toBe('dark');
      expect(sets[0].hasBody).toBe(false);
      await expect(toggle(page)).toHaveAttribute('aria-pressed', 'true');
      expect(await storedOf(page), 'following the OS must not write a stored choice').toBeNull();
      expect(await bodyBackground(page)).toBe('rgb(11, 15, 23)');
    });

    test('an explicit light choice beats the dark OS and persists', async ({ page, routes }) => {
      await gotoReady(page, requireRoute(test, routes, 'home'));
      expect(await themeOf(page)).toBe('dark');
      await toggle(page).click();
      expect(await themeOf(page)).toBe('light');
      await page.reload();
      expect(await themeOf(page), 'the stored light choice wins over the OS after reload').toBe('light');
      await expect(toggle(page)).toHaveAttribute('aria-pressed', 'false');
    });
  });

  test('a stored dark choice beats a light OS; an invalid stored value is ignored', async ({ page, routes }) => {
    await gotoReady(page, requireRoute(test, routes, 'home'));
    await page.evaluate((key) => window.localStorage.setItem(key, 'dark'), KEY);
    await page.reload();
    expect(await themeOf(page)).toBe('dark');

    await page.evaluate((key) => window.localStorage.setItem(key, 'sepia'), KEY);
    await page.reload();
    expect(await themeOf(page), 'garbage in storage falls back to the OS preference (light)').toBe('light');
    await expect(toggle(page)).toHaveAttribute('aria-pressed', 'false');
  });

  test('it follows live OS changes until the visitor makes a choice, then stops following', async ({ page, routes }) => {
    await gotoReady(page, requireRoute(test, routes, 'home'));
    expect(await themeOf(page)).toBe('light');

    await page.emulateMedia({ colorScheme: 'dark' });
    await expect.poll(() => themeOf(page), { message: 'follows the OS to dark' }).toBe('dark');
    await expect(toggle(page)).toHaveAttribute('aria-pressed', 'true');
    await page.emulateMedia({ colorScheme: 'light' });
    await expect.poll(() => themeOf(page), { message: 'follows the OS back to light' }).toBe('light');

    await toggle(page).click(); // the visitor picks dark
    expect(await themeOf(page)).toBe('dark');
    await page.emulateMedia({ colorScheme: 'dark' });
    await page.emulateMedia({ colorScheme: 'light' });
    await page.waitForTimeout(250);
    expect(await themeOf(page), 'an explicit choice is not overridden by OS changes').toBe('dark');
  });

  test('unusable storage (private mode): the page still themes, toggles for this page, and does not throw', async ({ page, routes, guard }) => {
    await page.addInitScript(() => {
      Object.defineProperty(window, 'localStorage', {
        get() {
          throw new DOMException('The operation is insecure.', 'SecurityError');
        },
      });
    });
    await gotoReady(page, requireRoute(test, routes, 'home'));
    await expect(toggle(page)).toBeVisible();
    // With storage unavailable the inline script leaves data-theme unset and the CSS media query takes over.
    expect(await themeOf(page), 'data-theme stays unset when storage throws').toBeNull();
    expect(await bodyBackground(page), 'the light palette applies through prefers-color-scheme').toBe('rgb(255, 255, 255)');

    await toggle(page).click();
    expect(await themeOf(page), 'the toggle still works for this page').toBe('dark');
    await expect(toggle(page)).toHaveAttribute('aria-pressed', 'true');
    expect(await bodyBackground(page)).toBe('rgb(11, 15, 23)');
    guard.assertClean();
  });
});

// ---------------------------------------------------------------------------------------------------
// Table of contents
// ---------------------------------------------------------------------------------------------------
test.describe('table of contents', () => {
  const details = (page) => page.locator('.toc__details');
  const links = (page) => page.locator('.toc__list a');

  async function ensureOpen(page) {
    if (!(await details(page).evaluate((el) => el.open))) {
      await page.locator('.toc__summary').click();
    }
    await expect(details(page)).toHaveJSProperty('open', true);
  }

  async function landingOf(page, id) {
    return page.evaluate((target) => {
      const el = document.getElementById(target);
      const box = el.getBoundingClientRect();
      return {
        top: Math.round(box.top),
        bottom: Math.round(box.bottom),
        viewport: window.innerHeight,
        header: Math.round(document.querySelector('.site-header').getBoundingClientRect().bottom),
        focused: document.activeElement === el,
        hash: location.hash,
        current: [...document.querySelectorAll('.toc a[aria-current]')].map((a) => a.getAttribute('href')),
      };
    }, id);
  }

  test('it is open on wide screens and collapsed on small ones; click and keyboard toggle it', async ({ page, routes }) => {
    await gotoReady(page, requireRoute(test, routes, 'singleRich'));
    const wide = page.viewportSize().width >= 1100;
    await expect(details(page)).toHaveJSProperty('open', wide);

    const summary = page.locator('.toc__summary');
    await summary.click();
    await expect(details(page)).toHaveJSProperty('open', !wide);
    await summary.click();
    await expect(details(page)).toHaveJSProperty('open', wide);

    await summary.focus();
    await page.keyboard.press('Enter');
    await expect(details(page), 'Enter toggles').toHaveJSProperty('open', !wide);
    await page.keyboard.press('Space');
    await expect(details(page), 'Space toggles').toHaveJSProperty('open', wide);

    // The state follows the viewport when it is resized across the 1100px breakpoint.
    await page.setViewportSize({ width: wide ? 800 : 1300, height: 900 });
    await expect(details(page)).toHaveJSProperty('open', !wide);
  });

  test('every link targets an existing, unique heading id, and every H2/H3 of the article is listed', async ({ page, routes }) => {
    await gotoReady(page, requireRoute(test, routes, 'singleRich'));
    const data = await page.evaluate(() => {
      const hrefs = [...document.querySelectorAll('.toc__list a')].map((a) => decodeURIComponent(a.getAttribute('href').slice(1)));
      const headings = [...document.querySelectorAll('.entry-content h2[id], .entry-content h3[id]')].map((h) => h.id);
      const allIds = [...document.querySelectorAll('[id]')].map((el) => el.id);
      return {
        hrefs,
        headings,
        missing: hrefs.filter((id) => !document.getElementById(id)),
        duplicates: hrefs.filter((id) => allIds.filter((other) => other === id).length > 1),
      };
    });
    expect(data.hrefs.length, 'TOC entries').toBeGreaterThan(2);
    expect(data.missing, 'TOC links to ids that do not exist').toEqual([]);
    expect(data.duplicates, 'TOC ids that are not unique on the page').toEqual([]);
    expect(data.hrefs, 'the TOC lists exactly the H2/H3 headings of the body, in order').toEqual(data.headings);
  });

  test('activating a link shows the heading below the sticky header, focuses it and marks it current', async ({ page, routes }, testInfo) => {
    await gotoReady(page, requireRoute(test, routes, 'singleRich'));
    await ensureOpen(page);
    const count = await links(page).count();
    const offsets = [];

    for (const index of [0, Math.floor(count / 2), count - 1]) {
      await ensureOpen(page);
      const link = links(page).nth(index);
      const id = decodeURIComponent((await link.getAttribute('href')).slice(1));
      await link.click();
      await settleScroll(page);
      const landing = await landingOf(page, id);

      expect(landing.top, `#${id} must not hide under the sticky header (header bottom ${landing.header})`).toBeGreaterThanOrEqual(landing.header - 1);
      expect(landing.bottom, `#${id} is fully inside the viewport`).toBeLessThanOrEqual(landing.viewport);
      expect(landing.hash, 'the URL fragment follows').toBe(`#${id}`);
      expect(landing.focused, 'keyboard and screen reader users land on the heading').toBe(true);
      expect(landing.current, 'exactly the activated entry is marked current').toEqual([`#${id}`]);
      offsets.push(landing.top - landing.header);
    }
    note(testInfo, 'anchor-offset', `heading top sits ${offsets.join(', ')}px below the header bottom after a TOC jump (scroll-padding-top and scroll-margin-top both apply)`);
  });

  test.describe('with reduced motion (instant jumps)', () => {
    test.use({ reducedMotion: 'reduce' });

    test('indicator, focus and position are still right after a TOC jump', async ({ page, routes }) => {
      await gotoReady(page, requireRoute(test, routes, 'singleRich'));
      await ensureOpen(page);
      const link = links(page).nth(3);
      const id = decodeURIComponent((await link.getAttribute('href')).slice(1));
      await link.click();
      await settleScroll(page);
      const landing = await landingOf(page, id);
      expect(landing.top, 'heading below the header').toBeGreaterThanOrEqual(landing.header - 1);
      expect(landing.focused, 'heading focused').toBe(true);
      expect(landing.current, 'the current-section marker is right after an instant jump').toEqual([`#${id}`]);
    });
  });

  test('natural scrolling moves the current-section marker through the headings in order', async ({ page, routes }) => {
    await gotoReady(page, requireRoute(test, routes, 'singleRich'));
    const order = [];
    for (let step = 0; step < 60; step += 1) {
      await page.mouse.wheel(0, 140);
      await page.waitForTimeout(70);
      const current = await page.evaluate(() => {
        const marked = document.querySelector('.toc a[aria-current]');
        return marked ? marked.getAttribute('href') : null;
      });
      if (current && order[order.length - 1] !== current) {
        order.push(current);
      }
    }
    expect(order.length, 'the marker moved through several sections').toBeGreaterThan(2);
    const all = await page.evaluate(() => [...document.querySelectorAll('.toc__list a')].map((a) => a.getAttribute('href')));
    const positions = order.map((href) => all.indexOf(href));
    expect(positions, 'the marker only moves forward while scrolling down').toEqual([...positions].sort((a, b) => a - b));
  });

  test('a pasted deep link (new tab) lands on the heading, not under the header', async ({ context, page, routes }) => {
    const url = requireRoute(test, routes, 'singleRich');
    await gotoReady(page, url);
    const ids = await page.evaluate(() => [...document.querySelectorAll('.toc__list a')].map((a) => decodeURIComponent(a.getAttribute('href').slice(1))));
    const sample = [ids[0], ids[Math.floor(ids.length / 2)], ids[ids.length - 1]];

    for (const id of sample) {
      const tab = await context.newPage();
      await tab.goto(`${url}#${id}`, { waitUntil: 'load' });
      await settleScroll(tab);
      const landing = await landingOf(tab, id);
      expect(landing.top, `deep link #${id}: heading is not under the header`).toBeGreaterThanOrEqual(landing.header - 1);
      expect(landing.bottom, `deep link #${id}: heading is inside the viewport`).toBeLessThanOrEqual(landing.viewport);
      await tab.close();
    }
  });

  test('on desktop the table of contents is sticky and stays inside the viewport @desktop-only', async ({ page, routes }) => {
    await gotoReady(page, requireRoute(test, routes, 'singleRich'));
    await jumpTo(page, 1600);
    const facts = await page.evaluate(() => {
      const toc = document.querySelector('.toc').getBoundingClientRect();
      return { top: toc.top, bottom: toc.bottom, header: document.querySelector('.site-header').getBoundingClientRect().bottom, vh: window.innerHeight };
    });
    expect(facts.top, 'the TOC sticks just under the header').toBeGreaterThanOrEqual(facts.header - 1);
    expect(facts.top).toBeLessThan(facts.header + 60);
    expect(facts.bottom, 'and never extends below the viewport').toBeLessThanOrEqual(facts.vh);
  });
});

// ---------------------------------------------------------------------------------------------------
// FAQ
// ---------------------------------------------------------------------------------------------------
test.describe('FAQ', () => {
  test('the first question is open, the others closed; click, Enter and Space toggle; answers show only when open', async ({ page, routes }) => {
    await gotoReady(page, requireRoute(test, routes, 'singleRich'));
    const items = page.locator('.faq__item');
    const count = await items.count();
    expect(count).toBeGreaterThan(2);
    await expect(items.nth(0)).toHaveJSProperty('open', true);
    await expect(items.nth(1)).toHaveJSProperty('open', false);
    await expect(items.nth(1).locator('.faq__answer')).toBeHidden();

    const second = items.nth(1);
    const summary = second.locator('summary');
    await summary.click();
    await expect(second).toHaveJSProperty('open', true);
    await expect(second.locator('.faq__answer')).toBeVisible();
    await summary.click();
    await expect(second).toHaveJSProperty('open', false);

    await summary.focus();
    await page.keyboard.press('Enter');
    await expect(second, 'Enter opens').toHaveJSProperty('open', true);
    await page.keyboard.press('Space');
    await expect(second, 'Space closes').toHaveJSProperty('open', false);

    // Each summary carries its question as the visible (and accessible) text.
    for (let i = 0; i < count; i += 1) {
      const text = (await items.nth(i).locator('summary').innerText()).trim();
      const question = (await items.nth(i).locator('.faq__question').innerText()).trim();
      expect(text, `summary #${i} text equals its question`).toBe(question);
    }
  });

  test('links inside a closed answer are not tabbable; they become tabbable when it opens', async ({ page, routes }, testInfo) => {
    await gotoReady(page, requireRoute(test, routes, 'singleRich'));
    const item = page.locator('.faq__item:has(.faq__answer a)').first();
    if ((await item.count()) === 0) {
      note(testInfo, 'skipped-check', 'no FAQ answer on this site contains a link');
      return;
    }
    const summary = item.locator('summary');
    const link = item.locator('.faq__answer a').first();

    await expect(item).toHaveJSProperty('open', false);
    await summary.focus();
    await page.keyboard.press('Tab');
    await expect(link, 'a link in a closed answer must not receive focus').not.toBeFocused();

    await summary.focus();
    await page.keyboard.press('Enter');
    await expect(item).toHaveJSProperty('open', true);
    await page.keyboard.press('Tab');
    await expect(link, 'once open, Tab moves into the answer').toBeFocused();
  });
});

// ---------------------------------------------------------------------------------------------------
// Tables
// ---------------------------------------------------------------------------------------------------
test.describe('tables', () => {
  test('a table sits in a labelled, keyboard-focusable scroll region with a visible focus ring', async ({ page, routes }) => {
    await gotoReady(page, requireRoute(test, routes, 'singleRich'));
    const region = page.locator('.table-scroll').first();
    await expect(region).toHaveAttribute('role', 'region');
    await expect(region).toHaveAttribute('tabindex', '0');
    expect((await region.getAttribute('aria-label')) || '', 'accessible name of the region').not.toBe('');
    await expect(region.locator('table')).toHaveCount(1);
    expect(await region.evaluate((el) => getComputedStyle(el).overflowX)).toMatch(/auto|scroll/);

    // Reach it by keyboard like a user does.
    await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
    let reached = false;
    for (let i = 0; i < 160 && !reached; i += 1) {
      await page.keyboard.press('Tab');
      reached = await region.evaluate((el) => el === document.activeElement);
    }
    expect(reached, 'the table region is reachable with Tab').toBe(true);
    const ring = await region.evaluate((el) => {
      const cs = getComputedStyle(el);
      return { style: cs.outlineStyle, width: parseFloat(cs.outlineWidth), visible: el.matches(':focus-visible') };
    });
    expect(ring.visible, ':focus-visible matches after keyboard focus').toBe(true);
    expect(ring.style, 'outline style').not.toBe('none');
    expect(ring.width, 'outline width').toBeGreaterThanOrEqual(2);
  });

  test('a wide table scrolls inside its region (not the page) and responds to the arrow keys @mobile-only', async ({ page, routes }) => {
    await page.setViewportSize({ width: 320, height: 640 });
    await gotoReady(page, requireRoute(test, routes, 'singleRich'));
    const region = page.locator('.table-scroll').first();
    await region.scrollIntoViewIfNeeded();
    const facts = await region.evaluate((el) => ({ client: el.clientWidth, scroll: el.scrollWidth, page: document.documentElement.scrollWidth, inner: window.innerWidth }));
    expect(facts.page, 'the page itself does not scroll sideways').toBeLessThanOrEqual(facts.inner);
    if (facts.scroll > facts.client) {
      await region.focus();
      await page.keyboard.press('ArrowRight');
      await page.waitForTimeout(150);
      expect(await region.evaluate((el) => el.scrollLeft), 'ArrowRight scrolls the focused region').toBeGreaterThan(0);
    } else {
      note(test.info(), 'table', `at 320px the table fits its region (${facts.scroll} <= ${facts.client}); keyboard scrolling not exercised`);
    }
  });
});

// ---------------------------------------------------------------------------------------------------
// Comment form (read only: nothing is ever submitted)
// ---------------------------------------------------------------------------------------------------
test('comment form: every field has a label, required fields are marked, the submit control has a name', async ({ page, routes }) => {
  await gotoReady(page, requireRoute(test, routes, 'singleComments'));
  const fields = await page.evaluate(() =>
    [...document.querySelectorAll('#commentform input:not([type=hidden]):not([type=submit]), #commentform textarea')].map((el) => {
      const label = el.labels && el.labels[0] ? el.labels[0].textContent.trim() : '';
      return { id: el.id, type: el.type, label, required: el.required || el.getAttribute('aria-required') === 'true', autocomplete: el.getAttribute('autocomplete') };
    })
  );
  expect(fields.length, 'comment fields').toBeGreaterThan(2);
  for (const field of fields) {
    expect(field.label, `field #${field.id} has a <label>`).not.toBe('');
  }
  expect(fields.filter((f) => f.required).length, 'required fields are flagged to assistive technology').toBeGreaterThan(0);
  const submit = page.locator('#commentform [type=submit]');
  await expect(submit).toBeVisible();
  expect(((await submit.getAttribute('value')) || (await submit.textContent()) || '').trim(), 'submit control name').not.toBe('');
  await expect(page.locator('.comment-list li').first()).toBeVisible();
});

// ---------------------------------------------------------------------------------------------------
// Cards: one link per article for keyboard and screen reader users
// ---------------------------------------------------------------------------------------------------
for (const key of ['home', 'categoryHub', 'search']) {
  test(`${key}: each article card exposes a single link to its article in the Tab order`, async ({ page, routes }) => {
    await gotoReady(page, requireRoute(test, routes, key));
    const cards = await page.evaluate(() =>
      [...document.querySelectorAll('article.card')].map((card) => {
        const media = card.querySelector('a.card__media');
        const tabbable = [...card.querySelectorAll('a[href]')].filter((a) => a.tabIndex >= 0 && a.getAttribute('aria-hidden') !== 'true').map((a) => a.href);
        return {
          mediaHidden: media ? media.getAttribute('aria-hidden') === 'true' && media.getAttribute('tabindex') === '-1' : true,
          tabbable,
          duplicates: tabbable.filter((href, i) => tabbable.indexOf(href) !== i),
          title: card.querySelector('.card__title a')?.textContent.trim().slice(0, 40) || '',
        };
      })
    );
    expect(cards.length, 'cards on the page').toBeGreaterThan(3);
    expect(cards.filter((c) => !c.mediaHidden).map((c) => c.title), 'cards whose image link is not hidden from AT and the Tab order').toEqual([]);
    expect(cards.filter((c) => c.duplicates.length > 0).map((c) => c.title), 'cards with two tabbable links to the same URL').toEqual([]);
    expect(cards.filter((c) => !c.title).length, 'cards without a title link').toBe(0);
  });
}

// ---------------------------------------------------------------------------------------------------
// Focus indicators (WCAG 2.4.7, 1.4.11)
// ---------------------------------------------------------------------------------------------------
test('every kind of control shows a focus ring of at least 2px with 3:1 contrast against its surroundings', async ({ page, routes }, testInfo) => {
  await gotoReady(page, requireRoute(test, routes, 'home'));
  const targets = {
    'brand link': '.header-row .brand',
    'nav link': '.nav-list > li > a',
    'sub menu button': '.nav-list > li > .submenu-toggle',
    'search toggle': '.search-toggle',
    'dark mode toggle': '.mode-toggle',
    'menu toggle': '.nav-toggle',
    'hero search field': '.home-intro .search-field',
    'hero search button': '.home-intro .search-submit',
    'topic chip': '.home-intro .chip',
    'card title link': '.card__title a',
    'pagination link': '.pagination a.page-numbers',
    'call to action button': '.cta__button',
    'footer link': '.site-footer a',
  };

  const results = [];
  for (const [name, selector] of Object.entries(targets)) {
    const locator = page.locator(selector).first();
    if (!(await locator.isVisible())) {
      continue; // for example the menu toggle on desktop
    }
    await locator.scrollIntoViewIfNeeded();
    await page.keyboard.press('Tab'); // switch to keyboard modality so :focus-visible applies
    await locator.focus();
    const ring = await locator.evaluate((el) => {
      const cs = getComputedStyle(el);
      const parse = (css) => {
        const m = css.match(/rgba?\(([^)]+)\)/);
        const p = m[1].split(/[ ,/]+/).filter(Boolean).map(Number);
        return { r: p[0], g: p[1], b: p[2], a: p.length > 3 ? p[3] : 1 };
      };
      const lum = ({ r, g, b }) => {
        const c = (v) => ((v / 255) <= 0.03928 ? v / 255 / 12.92 : (((v / 255) + 0.055) / 1.055) ** 2.4);
        return 0.2126 * c(r) + 0.7152 * c(g) + 0.0722 * c(b);
      };
      // Background the ring is drawn on: the first opaque ancestor background of the parent (null on gradients).
      let bg = null;
      for (let node = el.parentElement; node; node = node.parentElement) {
        const style = getComputedStyle(node);
        if (style.backgroundImage !== 'none') {
          bg = null;
          break;
        }
        const color = parse(style.backgroundColor);
        if (color.a >= 0.99) {
          bg = color;
          break;
        }
      }
      const ringColor = parse(cs.outlineColor);
      let ratio = null;
      if (bg) {
        const [hi, lo] = lum(ringColor) >= lum(bg) ? [ringColor, bg] : [bg, ringColor];
        ratio = (lum(hi) + 0.05) / (lum(lo) + 0.05);
      }
      return { style: cs.outlineStyle, width: parseFloat(cs.outlineWidth), color: cs.outlineColor, ratio, focusVisible: el.matches(':focus-visible') };
    });
    results.push({ name, ...ring });
  }
  await attachJson(testInfo, 'focus-rings', results);

  expect(results.length, 'control kinds exercised').toBeGreaterThan(8);
  for (const ring of results) {
    expect(ring.focusVisible, `${ring.name}: :focus-visible matches after keyboard focus`).toBe(true);
    expect(ring.style, `${ring.name}: outline style`).not.toBe('none');
    expect(ring.width, `${ring.name}: outline width`).toBeGreaterThanOrEqual(2);
    if (ring.ratio !== null) {
      expect(ring.ratio, `${ring.name}: ring colour ${ring.color} vs its background`).toBeGreaterThanOrEqual(3);
    }
  }
});

// ---------------------------------------------------------------------------------------------------
// Stored content is escaped and sanitised (fixtures contain hostile probes on purpose)
// ---------------------------------------------------------------------------------------------------
test('hostile stored content does not run: FAQ script/onerror probes and a javascript: source URL', async ({ page, routes }) => {
  await gotoReady(page, requireRoute(test, routes, 'singleRich'));
  const facts = await page.evaluate(() => ({
    probe: window.__nh_xss,
    scriptsInFaq: document.querySelectorAll('.faq script, .sources script, .summary script').length,
    // Only <body>: the theme's own non-blocking stylesheet link in <head> carries an onload handler on purpose.
    onerror: [...document.body.querySelectorAll('*')].filter((el) => el.getAttributeNames().some((name) => name.startsWith('on'))).length,
    javascriptLinks: [...document.querySelectorAll('a[href]')].filter((a) => /^\s*javascript:/i.test(a.getAttribute('href'))).length,
  }));
  expect(facts.probe, 'window.__nh_xss must stay undefined').toBeUndefined();
  expect(facts.scriptsInFaq, '<script> inside FAQ/sources/summary').toBe(0);
  expect(facts.onerror, 'inline event handler attributes in the page body').toBe(0);
  expect(facts.javascriptLinks, 'javascript: links').toBe(0);
});
