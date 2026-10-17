/**
 * NAVIGATION & KEYBOARD: skip link, header search panel, desktop mega menu, mobile drawer.
 *
 * Contract under test (assets/src/js/navigation.js, search.js, header.php):
 *  - skip link is the first Tab stop, becomes visible and moves focus to #primary;
 *  - the search toggle is a real button: aria-expanded mirrors the panel, opening moves focus to the input,
 *    Esc closes and returns focus to the toggle, a click outside closes, the nav and the search never
 *    stay open together;
 *  - desktop mega menu: a disclosure button next to each parent link (the link stays a link), aria-expanded
 *    mirrors the hidden panel, one panel at a time, hover opens after an intent delay and closes when the
 *    pointer leaves, keyboard: Enter/Space opens, Tab enters the panel, Esc closes and returns focus, tabbing
 *    out closes;
 *  - mobile/tablet drawer (<= 960px): the menu button opens/closes it, every link is reachable and not
 *    covered, sub menus expand independently, Esc closes and returns focus, keyboard activation moves focus
 *    into the menu, a click outside closes, the page stays attached and usable while it is open.
 */
import { test, expect, requireRoute } from './helpers/fixtures.mjs';
import { attachJson, describeFocus, gotoReady, headerRect, isDrawerMode, jumpTo, note, openDrawer, openSearch } from './helpers/ui.mjs';

const nav = (page) => page.locator('#primary-navigation');
const searchToggle = (page) => page.locator('.search-toggle');
const searchPanel = (page) => page.locator('#header-search');
const searchInput = (page) => page.locator('#header-search .search-field');

// ---------------------------------------------------------------------------------------------------
// Skip link
// ---------------------------------------------------------------------------------------------------
for (const key of ['home', 'singleRich', 'categoryHub', 'notFound']) {
  test(`skip link (${key}): first Tab stop, visible when focused, moves focus to #primary`, async ({ page, routes, guard }) => {
    const url = requireRoute(test, routes, key);
    await gotoReady(page, url);
    if (key === 'notFound') {
      guard.expectDocument404(page.url());
    }

    await page.keyboard.press('Tab');
    const skip = page.locator('a.skip-link');
    await expect(skip, 'the skip link is the first Tab stop').toBeFocused();

    // Visible and not covered while focused.
    const facts = await skip.evaluate((el) => {
      const box = el.getBoundingClientRect();
      const top = document.elementFromPoint(box.left + box.width / 2, box.top + box.height / 2);
      return { left: box.left, top: box.top, width: box.width, height: box.height, onTop: top === el || el.contains(top), outline: getComputedStyle(el).outlineStyle };
    });
    expect(facts.width, 'skip link width').toBeGreaterThan(60);
    expect(facts.height, 'skip link height').toBeGreaterThan(24);
    expect(facts.left, 'skip link inside the viewport (left)').toBeGreaterThanOrEqual(0);
    expect(facts.top, 'skip link inside the viewport (top)').toBeGreaterThanOrEqual(0);
    expect(facts.onTop, 'nothing paints over the focused skip link').toBe(true);

    await page.keyboard.press('Enter');
    await expect(page.locator('#primary'), 'focus moves to <main id="primary">').toBeFocused();
    expect(page.url()).toMatch(/#primary$/);

    // The next Tab stays inside main (it does not jump back to the header).
    await page.keyboard.press('Tab');
    const insideMain = await page.evaluate(() => document.querySelector('#primary').contains(document.activeElement));
    expect(insideMain, 'the Tab after the skip link lands inside main').toBe(true);
  });
}

// ---------------------------------------------------------------------------------------------------
// Header search panel
// ---------------------------------------------------------------------------------------------------
test.describe('header search panel', () => {
  test('toggle: aria-expanded mirrors the panel, focus moves to the input, second click closes', async ({ page, routes }) => {
    await gotoReady(page, requireRoute(test, routes, 'home'));
    const toggle = searchToggle(page);
    await expect(toggle).toHaveAttribute('aria-controls', 'header-search');
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(searchPanel(page)).toBeHidden();

    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await expect(searchPanel(page)).toBeVisible();
    await expect(searchInput(page), 'focus moves to the search input').toBeFocused();

    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(searchPanel(page)).toBeHidden();
  });

  test('keyboard: Enter and Space open it, Escape closes it and returns focus to the toggle', async ({ page, routes }) => {
    await gotoReady(page, requireRoute(test, routes, 'home'));
    const toggle = searchToggle(page);

    for (const key of ['Enter', 'Space']) {
      await toggle.focus();
      await page.keyboard.press(key);
      await expect(searchPanel(page), `${key} opens the panel`).toBeVisible();
      await expect(searchInput(page)).toBeFocused();
      await page.keyboard.press('Escape');
      await expect(searchPanel(page), 'Escape closes the panel').toBeHidden();
      await expect(toggle, 'focus returns to the toggle').toBeFocused();
      await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    }
  });

  test('a click outside closes the panel; the form is labelled and reachable in order', async ({ page, routes }) => {
    await gotoReady(page, requireRoute(test, routes, 'home'));
    await openSearch(page);

    // Accessible name of the field comes from its label, and Tab goes input -> submit.
    await expect(page.getByRole('searchbox', { name: 'Search for:' }).first()).toBeVisible();
    await page.keyboard.press('Tab');
    await expect(page.locator('#header-search .search-submit')).toBeFocused();

    await page.locator('main').click({ position: { x: 4, y: 260 } });
    await expect(searchPanel(page)).toBeHidden();
    await expect(searchToggle(page)).toHaveAttribute('aria-expanded', 'false');
  });

  test('opening the search closes the navigation, and opening the navigation closes the search', async ({ page, routes }) => {
    await gotoReady(page, requireRoute(test, routes, 'home'));
    const drawer = await isDrawerMode(page);

    if (drawer) {
      await openDrawer(page);
      await openSearch(page);
      await expect(nav(page), 'the drawer closes when the search opens').not.toHaveClass(/is-open/);
      await expect(page.locator('.nav-toggle')).toHaveAttribute('aria-expanded', 'false');

      await page.locator('.nav-toggle').click();
      await expect(nav(page)).toHaveClass(/is-open/);
      await expect(searchPanel(page), 'the search closes when the drawer opens').toBeHidden();
      await expect(searchToggle(page)).toHaveAttribute('aria-expanded', 'false');
    } else {
      const button = page.locator('.nav-list > li > .submenu-toggle').first();
      await button.click();
      await expect(button).toHaveAttribute('aria-expanded', 'true');
      await searchToggle(page).click();
      await expect(searchPanel(page)).toBeVisible();
      await expect(button, 'the mega panel closes when the search opens').toHaveAttribute('aria-expanded', 'false');

      await button.click();
      await expect(button).toHaveAttribute('aria-expanded', 'true');
      await expect(searchPanel(page), 'the search closes when a mega panel opens').toBeHidden();
    }
  });
});

// ---------------------------------------------------------------------------------------------------
// Desktop mega menu
// ---------------------------------------------------------------------------------------------------
test.describe('desktop mega menu @desktop-only', () => {
  const items = (page) => page.locator('.nav-list > li.menu-item-has-children');
  const buttonOf = (item) => item.locator(':scope > .submenu-toggle');
  const panelOf = (item) => item.locator(':scope > .sub-menu');

  test('every parent has a link and a disclosure button; panels start hidden and are wired with aria-controls', async ({ page, routes }) => {
    await gotoReady(page, requireRoute(test, routes, 'home'));
    const count = await items(page).count();
    expect(count, 'menu items with children').toBeGreaterThan(0);

    for (let i = 0; i < count; i += 1) {
      const item = items(page).nth(i);
      const button = buttonOf(item);
      const panel = panelOf(item);
      const link = item.locator(':scope > a');
      expect(await link.getAttribute('href'), `parent link #${i} stays a real link`).toMatch(/^https?:\/\//);
      await expect(button).toHaveAttribute('aria-expanded', 'false');
      await expect(panel).toBeHidden();
      const panelId = await panel.getAttribute('id');
      expect(await button.getAttribute('aria-controls'), 'aria-controls points at the panel').toBe(panelId);
      expect(await panel.locator('a').count(), 'the panel lists links').toBeGreaterThan(0);
      // The button's accessible name says what it toggles.
      expect(((await button.innerText()) || (await button.textContent()) || '').trim(), 'button name').toMatch(/submenu/i);
    }
  });

  test('click toggles aria-expanded and the panel; the panel spans the header; opening one closes the other', async ({ page, routes }) => {
    await gotoReady(page, requireRoute(test, routes, 'home'));
    const first = items(page).nth(0);
    const second = items(page).nth(1);

    await buttonOf(first).click();
    await expect(buttonOf(first)).toHaveAttribute('aria-expanded', 'true');
    await expect(panelOf(first)).toBeVisible();

    const header = await headerRect(page);
    const box = await panelOf(first).boundingBox();
    expect(Math.abs(box.y - header.bottom), 'the panel sits right under the header').toBeLessThan(3);
    expect(box.width, 'the panel spans the page width').toBeGreaterThan(page.viewportSize().width * 0.95);
    for (const link of await panelOf(first).locator('a').all()) {
      await expect(link).toBeVisible();
    }

    await buttonOf(second).click();
    await expect(buttonOf(second)).toHaveAttribute('aria-expanded', 'true');
    await expect(panelOf(second)).toBeVisible();
    await expect(buttonOf(first), 'only one panel stays open').toHaveAttribute('aria-expanded', 'false');
    await expect(panelOf(first)).toBeHidden();

    await buttonOf(second).click();
    await expect(buttonOf(second)).toHaveAttribute('aria-expanded', 'false');
    await expect(panelOf(second)).toBeHidden();
  });

  test('hover opens a panel, keeps it open over the panel, closes it when the pointer leaves', async ({ page, routes }) => {
    await gotoReady(page, requireRoute(test, routes, 'home'));
    const item = items(page).first();
    const panel = panelOf(item);

    await item.hover();
    await expect(panel, 'hover opens the panel').toBeVisible({ timeout: 1500 });
    await expect(buttonOf(item)).toHaveAttribute('aria-expanded', 'true');

    // Moving onto the panel itself must not close it.
    await panel.locator('a').first().hover();
    await page.waitForTimeout(500);
    await expect(panel, 'the panel stays open while the pointer is on it').toBeVisible();

    await page.mouse.move(700, 620);
    await expect(panel, 'the panel closes after the pointer leaves').toBeHidden({ timeout: 1500 });
    await expect(buttonOf(item)).toHaveAttribute('aria-expanded', 'false');
  });

  test('hover has an intent delay: nothing opens before ~90ms, and a pointer that left in time opens nothing', async ({ page, routes }) => {
    await gotoReady(page, requireRoute(test, routes, 'home'));
    const item = items(page).first();
    const panel = panelOf(item);

    // A fake clock makes the delay deterministic instead of racing real time. It is installed after the page
    // loaded; the theme reads window.setTimeout when the pointer event fires, so it uses the fake one.
    await page.clock.install();
    await page.clock.pauseAt(Date.now() + 10_000);

    await item.hover();
    await page.clock.runFor(40);
    await expect(panel, 'not open after 40ms').toBeHidden();
    await page.clock.runFor(120);
    await expect(panel, 'open once the intent delay has elapsed').toBeVisible();

    // Leave and come back within the grace period: the panel must survive (the close timer is cancelled).
    await page.mouse.move(700, 620);
    await page.clock.runFor(100);
    await item.hover();
    await page.clock.runFor(400);
    await expect(panel).toBeVisible();

    // Leave for good: closes after ~220ms, not before.
    await page.mouse.move(700, 620);
    await page.clock.runFor(100);
    await expect(panel, 'still open 100ms after leaving').toBeVisible();
    await page.clock.runFor(300);
    await expect(panel, 'closed after the grace period').toBeHidden();
  });

  test('keyboard: Enter/Space open, Tab enters the panel, Escape closes and returns focus, tabbing out closes', async ({ page, routes }) => {
    await gotoReady(page, requireRoute(test, routes, 'home'));
    const item = items(page).first();
    const button = buttonOf(item);
    const panel = panelOf(item);
    const inside = () => page.evaluate(() => Boolean(document.activeElement && document.activeElement.closest('.nav-list > li.menu-item-has-children:first-child .sub-menu')));

    for (const key of ['Enter', 'Space']) {
      await button.focus();
      await page.keyboard.press(key);
      await expect(panel, `${key} opens the panel`).toBeVisible();
      await expect(button).toHaveAttribute('aria-expanded', 'true');

      await page.keyboard.press('Tab');
      expect(await inside(), 'Tab moves focus into the open panel').toBe(true);

      await page.keyboard.press('Escape');
      await expect(panel, 'Escape closes the panel').toBeHidden();
      await expect(button, 'focus returns to the disclosure button').toBeFocused();
      await expect(button).toHaveAttribute('aria-expanded', 'false');
    }

    // Tab out of the last link of the panel: the panel closes behind the focus.
    await button.focus();
    await page.keyboard.press('Enter');
    await expect(panel).toBeVisible();
    const links = await panel.locator('a').count();
    for (let i = 0; i < links + 1; i += 1) {
      await page.keyboard.press('Tab');
    }
    expect(await inside(), 'focus has left the item').toBe(false);
    await expect(panel, 'the panel closes when focus leaves the item').toBeHidden();
    await expect(button).toHaveAttribute('aria-expanded', 'false');
  });

  test('a panel opened by hover closes on Escape from the parent link; a click outside closes a clicked-open panel', async ({ page, routes }) => {
    await gotoReady(page, requireRoute(test, routes, 'home'));
    const item = items(page).first();
    const panel = panelOf(item);

    // Opened by the pointer, focus is NOT inside the item: Escape must still close it (focus is moved to
    // the parent link first, as a keyboard user who just hovered would do).
    await item.hover();
    await expect(panel).toBeVisible({ timeout: 1500 });
    await item.locator(':scope > a').focus();
    await page.keyboard.press('Escape');
    await expect(panel, 'Escape on the parent link closes the panel').toBeHidden();
    await expect(buttonOf(item), 'and returns focus to the button').toBeFocused();

    // The pointer is still over the item (no new hover timer is pending): open by click, close by clicking away.
    await buttonOf(item).click();
    await expect(panel).toBeVisible();
    await page.locator('main').click({ position: { x: 4, y: 300 } });
    await expect(panel, 'a click outside closes the panel').toBeHidden();
  });

  test('the mega panel never makes the page scroll sideways and fits the viewport height', async ({ page, routes }) => {
    await gotoReady(page, requireRoute(test, routes, 'home'));
    await buttonOf(items(page).first()).click();
    const facts = await page.evaluate(() => {
      const panel = document.querySelector('.nav-list > li.menu-item-has-children .sub-menu:not([hidden])');
      const box = panel.getBoundingClientRect();
      return { scrollWidth: document.documentElement.scrollWidth, innerWidth: window.innerWidth, bottom: box.bottom, innerHeight: window.innerHeight };
    });
    expect(facts.scrollWidth).toBeLessThanOrEqual(facts.innerWidth);
    expect(facts.bottom, 'panel bottom edge inside the viewport').toBeLessThanOrEqual(facts.innerHeight);
  });
});

// ---------------------------------------------------------------------------------------------------
// Mobile / tablet drawer
// ---------------------------------------------------------------------------------------------------
test.describe('drawer navigation @drawer', () => {
  test('the menu button is shown, the drawer starts closed and is wired with aria-controls', async ({ page, routes }) => {
    await gotoReady(page, requireRoute(test, routes, 'home'));
    const toggle = page.locator('.nav-toggle');
    await expect(toggle).toBeVisible();
    await expect(toggle).toHaveAttribute('aria-controls', 'primary-navigation');
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(nav(page)).toBeHidden();
    // The accessible name of the button.
    expect((await toggle.textContent()).trim()).toBe('Menu');
  });

  test('opens and closes with the button; the icon swaps; every link is visible, inside the viewport and not covered', async ({ page, routes }) => {
    await gotoReady(page, requireRoute(test, routes, 'home'));
    const toggle = page.locator('.nav-toggle');

    await toggle.click();
    await expect(nav(page)).toHaveClass(/is-open/);
    await expect(nav(page)).toBeVisible();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await expect(toggle.locator('.icon--close'), 'the close icon is shown while open').toBeVisible();
    await expect(toggle.locator('.icon--open')).toBeHidden();

    const box = await nav(page).boundingBox();
    const viewport = page.viewportSize();
    expect(box.x, 'drawer left edge').toBeGreaterThanOrEqual(0);
    expect(box.x + box.width, 'drawer right edge').toBeLessThanOrEqual(viewport.width + 1);
    expect(box.y + box.height, 'drawer bottom edge').toBeLessThanOrEqual(viewport.height + 1);

    const topLinks = nav(page).locator(':scope .nav-list > li > a');
    const count = await topLinks.count();
    expect(count).toBeGreaterThan(0);
    for (let i = 0; i < count; i += 1) {
      const link = topLinks.nth(i);
      await expect(link).toBeVisible();
      const hit = await link.evaluate((el) => {
        const r = el.getBoundingClientRect();
        const top = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2);
        return Boolean(top && (top === el || el.contains(top)));
      });
      expect(hit, `link #${i} is hit-testable (nothing covers it)`).toBe(true);
    }

    await toggle.click();
    await expect(nav(page)).not.toHaveClass(/is-open/);
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(nav(page)).toBeHidden();
  });

  test('sub menus expand and collapse independently; their links are visible and reachable', async ({ page, routes }) => {
    await gotoReady(page, requireRoute(test, routes, 'home'));
    await openDrawer(page);
    const buttons = nav(page).locator('.submenu-toggle');
    const count = await buttons.count();
    expect(count, 'sub menu buttons').toBeGreaterThan(1);

    for (let i = 0; i < count; i += 1) {
      const button = buttons.nth(i);
      const panel = nav(page).locator('.sub-menu').nth(i);
      await button.click();
      await expect(button).toHaveAttribute('aria-expanded', 'true');
      await expect(panel).toBeVisible();
    }
    // Opening the next one did not close the previous ones: all are open at once.
    for (let i = 0; i < count; i += 1) {
      await expect(buttons.nth(i), `sub menu #${i} stays open`).toHaveAttribute('aria-expanded', 'true');
    }
    // The drawer never grows past the viewport: it scrolls inside itself.
    const fit = await nav(page).evaluate((el) => ({ bottom: el.getBoundingClientRect().bottom, vh: window.innerHeight, scrollable: el.scrollHeight > el.clientHeight }));
    expect(fit.bottom, 'drawer bottom edge with everything expanded').toBeLessThanOrEqual(fit.vh + 1);

    await buttons.nth(0).click();
    await expect(buttons.nth(0)).toHaveAttribute('aria-expanded', 'false');
    await expect(nav(page).locator('.sub-menu').nth(0)).toBeHidden();
    note(test.info(), 'drawer', `with every sub menu open the drawer ${fit.scrollable ? 'scrolls inside itself' : 'fits without scrolling'}`);
  });

  test('keyboard: Enter on the button moves focus into the menu, Escape closes the panel first, then the drawer', async ({ page, routes }) => {
    await gotoReady(page, requireRoute(test, routes, 'home'));
    const toggle = page.locator('.nav-toggle');

    await toggle.focus();
    await page.keyboard.press('Enter');
    await expect(nav(page)).toHaveClass(/is-open/);
    expect(
      await page.evaluate(() => Boolean(document.activeElement && document.activeElement.closest('#primary-navigation'))),
      'keyboard activation moves focus into the opened menu'
    ).toBe(true);

    // Open a sub menu with the keyboard and escape out of it: the panel closes, the drawer stays.
    const button = nav(page).locator('.submenu-toggle').first();
    await button.focus();
    await page.keyboard.press('Enter');
    await expect(button).toHaveAttribute('aria-expanded', 'true');
    await page.keyboard.press('Escape');
    await expect(button, 'the first Escape closes the sub menu only').toHaveAttribute('aria-expanded', 'false');
    await expect(nav(page)).toHaveClass(/is-open/);
    await expect(button).toBeFocused();

    await page.keyboard.press('Escape');
    await expect(nav(page), 'the second Escape closes the drawer').not.toHaveClass(/is-open/);
    await expect(toggle, 'focus returns to the menu button').toBeFocused();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
  });

  test('pointer activation does not steal focus; a click outside closes the drawer; a link click navigates', async ({ page, routes }) => {
    await gotoReady(page, requireRoute(test, routes, 'home'));
    const toggle = page.locator('.nav-toggle');

    await toggle.click();
    await expect(nav(page)).toHaveClass(/is-open/);
    await expect(toggle, 'a pointer click leaves focus on the button').toBeFocused();

    await page.locator('main').click({ position: { x: 4, y: 400 } });
    await expect(nav(page), 'a click outside closes the drawer').not.toHaveClass(/is-open/);

    await openDrawer(page);
    const target = nav(page).locator(':scope .nav-list > li > a').first();
    const href = await target.getAttribute('href');
    await Promise.all([page.waitForURL(href), target.click()]);
    expect(page.url()).toBe(href);
  });

  test('the page stays usable under the open drawer: header and drawer stay attached while scrolling, scrolling still works after closing', async ({ page, routes }, testInfo) => {
    await gotoReady(page, requireRoute(test, routes, 'singleRich'));
    await openDrawer(page);
    await jumpTo(page, 700);

    const header = await headerRect(page);
    expect(Math.abs(header.top), 'sticky header stays at the top').toBeLessThan(1.5);
    await expect(nav(page), 'the drawer is still open after scrolling').toHaveClass(/is-open/);
    const drawerBox = await nav(page).boundingBox();
    expect(Math.abs(drawerBox.y - header.bottom), 'the drawer stays attached to the header').toBeLessThan(2);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    expect(overflow, 'no horizontal shift while the drawer is open').toBeLessThanOrEqual(0);

    const bodyOverflow = await page.evaluate(() => getComputedStyle(document.body).overflow + '/' + getComputedStyle(document.documentElement).overflow);
    note(testInfo, 'scroll-lock', `no body scroll lock is applied while the drawer is open (overflow ${bodyOverflow}); the page keeps scrolling beneath it`);

    await page.locator('.nav-toggle').click();
    await expect(nav(page)).not.toHaveClass(/is-open/);
    await jumpTo(page, 1400);
    expect(await page.evaluate(() => window.scrollY), 'the page still scrolls after the drawer closed').toBeGreaterThan(1000);
  });

  test('resizing to the desktop breakpoint resets the drawer and back again closes it', async ({ page, routes }) => {
    await gotoReady(page, requireRoute(test, routes, 'home'));
    const original = page.viewportSize();
    await openDrawer(page);

    await page.setViewportSize({ width: 1280, height: 800 });
    await expect(page.locator('.nav-toggle')).toBeHidden();
    await expect(nav(page), 'the navigation is inline on desktop').toBeVisible();
    await expect(page.locator('.nav-toggle')).toHaveAttribute('aria-expanded', 'false');

    await page.setViewportSize(original);
    await expect(page.locator('.nav-toggle')).toBeVisible();
    await expect(nav(page), 'back to the drawer layout it starts closed').toBeHidden();
  });

  test('with the drawer open, Tab can leave it: where focus lands (observation)', async ({ page, routes }, testInfo) => {
    await gotoReady(page, requireRoute(test, routes, 'home'));
    await openDrawer(page);
    const stops = [];
    for (let i = 0; i < 30; i += 1) {
      await page.keyboard.press('Tab');
      const info = await page.evaluate(describeFocus);
      if (!info) {
        break;
      }
      stops.push(info);
      if (!info.inHeader && !(await page.evaluate(() => Boolean(document.activeElement.closest('#primary-navigation'))))) {
        break;
      }
    }
    const escaped = stops.find((stop) => !stop.inHeader);
    await attachJson(testInfo, 'drawer-tab-stops', stops);
    if (escaped) {
      note(
        testInfo,
        'drawer-focus',
        `Tab moved on into the page ("${escaped.label}") while the drawer is still open; ` +
          (escaped.entirelyObscured ? `that element is entirely covered by ${escaped.coveredBy} (dismissible with Escape, so allowed by WCAG 2.4.11, but closing the drawer when focus leaves would be better).` : 'it is visible.')
      );
    }
    // The drawer must always remain dismissible from wherever focus is.
    await page.keyboard.press('Escape');
    await expect(nav(page)).not.toHaveClass(/is-open/);
  });
});

// ---------------------------------------------------------------------------------------------------
// Touch
// ---------------------------------------------------------------------------------------------------
test.describe('touch @touch', () => {
  test('the device really is touch-first, so hover behaviour is off and taps drive the drawer and sub menus', async ({ page, routes }) => {
    await gotoReady(page, requireRoute(test, routes, 'home'));
    expect(await page.evaluate(() => window.matchMedia('(hover: hover)').matches), 'hover capability of the emulated device').toBe(false);

    await page.locator('.nav-toggle').tap();
    await expect(nav(page)).toHaveClass(/is-open/);
    const button = nav(page).locator('.submenu-toggle').first();
    await button.tap();
    await expect(button).toHaveAttribute('aria-expanded', 'true');
    await expect(nav(page).locator('.sub-menu').first()).toBeVisible();
    await button.tap();
    await expect(button).toHaveAttribute('aria-expanded', 'false');

    // Tap targets are at least 44 CSS px (WCAG 2.5.5 AAA, the platform guideline) for the header controls.
    for (const selector of ['.nav-toggle', '.search-toggle', '.mode-toggle']) {
      const box = await page.locator(selector).boundingBox();
      expect(box.width, `${selector} width`).toBeGreaterThanOrEqual(44);
      expect(box.height, `${selector} height`).toBeGreaterThanOrEqual(44);
    }
    const sub = await button.boundingBox();
    expect(sub.width).toBeGreaterThanOrEqual(44);
    expect(sub.height).toBeGreaterThanOrEqual(44);
  });
});
