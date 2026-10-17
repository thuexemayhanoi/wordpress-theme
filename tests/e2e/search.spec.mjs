/**
 * SEARCH: live suggestions in the header panel and the results page.
 *
 * Suggestions (assets/src/js/search.js, searchform.php): from 3 characters, debounced 250 ms, one request
 * to the core REST route /wp/v2/search (5 items), a list of links plus "See all results", a polite
 * live region that announces the count, ArrowDown/ArrowUp move focus through the links, Enter follows one,
 * Escape dismisses. A failing, aborted, slow, empty, malformed or hostile response must leave the plain
 * form fully usable. The suggestions are a list of links announced through a live region, not an ARIA
 * combobox/listbox: that is what is asserted (and it is what axe accepts).
 *
 * Results page (search.php): phrase, count (role=status), highlighted <mark>, matching-topic chips, topic
 * filter, pagination, empty state; and no phrase may ever execute.
 */
import { test, expect, requireRoute } from './helpers/fixtures.mjs';
import { BASE_ORIGIN, HIDDEN_TITLES, NO_RESULT_TERM, SEARCH_TERM } from './helpers/env.mjs';
import { gotoReady, measureOverflow, note, openSearch, typeInHeaderSearch } from './helpers/ui.mjs';

const REST = '**/wp-json/wp/v2/search*';
const suggestBox = (page) => page.locator('#header-search .search-suggest');
const suggestLinks = (page) => page.locator('#header-search .search-suggest__list a');
const statusOf = (page) => page.locator('#header-search .search-suggest__status');
const PROBE = '"><img src=x onerror=window.__xss=1>';

/** Open the header search on the home page. */
async function startHeaderSearch(page, routes) {
  await gotoReady(page, requireRoute(test, routes, 'home'));
  await openSearch(page);
}

// ---------------------------------------------------------------------------------------------------
// Suggestions
// ---------------------------------------------------------------------------------------------------
test.describe('suggestions', () => {
  test('typing shows a debounced list of article links plus "See all results", and announces the count', async ({ page, routes }) => {
    const requests = [];
    page.on('request', (request) => {
      if (request.url().includes('/wp-json/wp/v2/search')) {
        requests.push(request.url());
      }
    });
    await startHeaderSearch(page, routes);

    // Fewer than 3 characters: nothing is requested and nothing is shown.
    await typeInHeaderSearch(page, 'sc');
    await page.waitForTimeout(500);
    expect(requests.length, 'no request below 3 characters').toBe(0);
    await expect(suggestBox(page)).toBeHidden();

    await page.locator('#header-search .search-field').fill('');
    await typeInHeaderSearch(page, SEARCH_TERM, { delay: 35 });
    await expect(suggestBox(page)).toBeVisible();

    // Debounce: one request for the whole word (five would mean one per character).
    expect(requests.length, `requests for "${SEARCH_TERM}" typed at 35ms per key: ${requests.join(' | ')}`).toBeGreaterThanOrEqual(1);
    expect(requests.length).toBeLessThanOrEqual(2);
    expect(requests[requests.length - 1]).toContain(`search=${encodeURIComponent(SEARCH_TERM)}`);
    expect(requests[requests.length - 1]).toContain('per_page=5');

    const links = suggestLinks(page);
    const count = await links.count();
    expect(count, 'article suggestions plus the "see all" link').toBeGreaterThanOrEqual(2);
    expect(count, 'at most five suggestions plus "see all"').toBeLessThanOrEqual(6);

    const hrefs = await links.evaluateAll((els) => els.map((a) => a.href));
    for (const href of hrefs) {
      expect(href, 'suggestion links stay on the site').toMatch(/^https?:\/\//);
    }
    const last = links.last();
    await expect(last, 'the last item is the "see all" link').toHaveText('See all results');
    expect(await last.getAttribute('href')).toContain(`s=${encodeURIComponent(SEARCH_TERM)}`);

    // The live region announces how many suggestions are available.
    const status = statusOf(page);
    await expect(status).toHaveAttribute('role', 'status');
    await expect(status).toHaveAttribute('aria-live', 'polite');
    await expect(status).toHaveText(new RegExp(`^${count - 1} suggestions available$`));

    // Nothing paints over the suggestions and they stay inside the viewport horizontally.
    const viewport = page.viewportSize();
    for (let i = 0; i < count; i += 1) {
      const facts = await links.nth(i).evaluate((el) => {
        const r = el.getBoundingClientRect();
        const top = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2);
        return { left: r.left, right: r.right, onTop: top === el || el.contains(top) };
      });
      expect(facts.onTop, `suggestion #${i} is not covered`).toBe(true);
      expect(facts.left).toBeGreaterThanOrEqual(0);
      expect(facts.right).toBeLessThanOrEqual(viewport.width + 1);
    }
  });

  test('ArrowDown/ArrowUp move focus through the links, Tab reaches them, Escape dismisses, Enter follows a suggestion', async ({ page, routes }, testInfo) => {
    await startHeaderSearch(page, routes);
    const input = page.locator('#header-search .search-field');
    await typeInHeaderSearch(page, SEARCH_TERM);
    await expect(suggestBox(page)).toBeVisible();
    const links = suggestLinks(page);
    const total = await links.count();

    await page.keyboard.press('ArrowDown');
    await expect(links.nth(0), 'ArrowDown from the field focuses the first suggestion').toBeFocused();
    await page.keyboard.press('ArrowDown');
    await expect(links.nth(1)).toBeFocused();
    await page.keyboard.press('ArrowUp');
    await expect(links.nth(0)).toBeFocused();
    await page.keyboard.press('ArrowUp');
    await expect(input, 'ArrowUp from the first suggestion returns to the field').toBeFocused();

    for (let i = 0; i < total + 2; i += 1) {
      await page.keyboard.press('ArrowDown');
    }
    await expect(links.nth(total - 1), 'ArrowDown stops at the last item').toBeFocused();

    // Tab from the field also walks the suggestions (field -> submit -> links).
    await input.focus();
    await page.keyboard.press('Tab');
    await expect(page.locator('#header-search .search-submit')).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(links.nth(0), 'Tab reaches the first suggestion after the submit button').toBeFocused();

    // Escape: the suggestions disappear; the panel closes and focus goes back to the toggle.
    await page.keyboard.press('Escape');
    await expect(suggestBox(page)).toBeHidden();
    await expect(page.locator('#header-search')).toBeHidden();
    await expect(page.locator('.search-toggle')).toBeFocused();
    note(testInfo, 'escape', 'one Escape dismisses the suggestions AND closes the whole search panel (the key event also reaches the panel handler)');

    // Enter on a suggestion follows its link. The field keeps its text when the panel closes: clear it first.
    await openSearch(page);
    await input.fill('');
    await typeInHeaderSearch(page, SEARCH_TERM);
    await expect(suggestBox(page)).toBeVisible();
    await page.keyboard.press('ArrowDown');
    const first = links.nth(0);
    const href = await first.getAttribute('href');
    await Promise.all([page.waitForURL(href), page.keyboard.press('Enter')]);
    expect(page.url()).toBe(href);
  });

  test('"See all results" opens the normal results page for the phrase', async ({ page, routes }) => {
    await startHeaderSearch(page, routes);
    await typeInHeaderSearch(page, SEARCH_TERM);
    await expect(suggestBox(page)).toBeVisible();
    await Promise.all([page.waitForURL(/[?&]s=/), suggestLinks(page).last().click()]);
    expect(new URL(page.url()).searchParams.get('s')).toBe(SEARCH_TERM);
    await expect(page.locator('h1')).toContainText(`“${SEARCH_TERM}”`);
  });

  test('a click outside dismisses the suggestions; clearing the field hides them and clears the announcement', async ({ page, routes }) => {
    await startHeaderSearch(page, routes);
    const input = page.locator('#header-search .search-field');
    await typeInHeaderSearch(page, SEARCH_TERM);
    await expect(suggestBox(page)).toBeVisible();

    await input.fill('');
    await expect(suggestBox(page), 'emptying the field hides the list').toBeHidden();
    await expect(statusOf(page), 'and clears the live region').toHaveText('');

    await typeInHeaderSearch(page, SEARCH_TERM);
    await expect(suggestBox(page)).toBeVisible();
    await page.locator('main').click({ position: { x: 4, y: 300 } });
    await expect(suggestBox(page)).toBeHidden();
  });

  test('the singular case is announced with correct grammar', async ({ page, routes }) => {
    await startHeaderSearch(page, routes);
    await typeInHeaderSearch(page, 'Insurance');
    await expect(suggestBox(page)).toBeVisible();
    expect(await suggestLinks(page).count(), 'one article plus "see all"').toBe(2);
    // "1 suggestions available" would read badly in a screen reader.
    await expect(statusOf(page)).toHaveText(/^1 suggestion available$/);
  });

  test.describe('when the REST route misbehaves the plain form keeps working', () => {
    const cases = {
      'the request is aborted': (route) => route.abort(),
      'the server answers 500': (route) => route.fulfill({ status: 500, contentType: 'application/json', body: '{"code":"internal","message":"boom"}' }),
      'the server answers 200 with invalid JSON': (route) => route.fulfill({ status: 200, contentType: 'application/json', body: '<<not json>>' }),
      'the server answers 403': (route) => route.fulfill({ status: 403, contentType: 'application/json', body: '{"code":"rest_forbidden"}' }),
    };
    for (const [label, handler] of Object.entries(cases)) {
      test(label, async ({ page, routes, guard }) => {
        // These failures are the point of the test: declare them, nothing else is silenced.
        guard.allow({ failed: /wp-json\/wp\/v2\/search/, response: /wp-json\/wp\/v2\/search/, console: /Failed to load resource/ });
        await page.route(REST, handler);
        await startHeaderSearch(page, routes);
        const input = page.locator('#header-search .search-field');
        await typeInHeaderSearch(page, SEARCH_TERM);
        await page.waitForTimeout(700);

        await expect(suggestBox(page), 'no suggestion box is shown').toBeHidden();
        await expect(page.locator('#header-search'), 'the panel stays open').toBeVisible();
        await expect(input).toHaveValue(SEARCH_TERM);
        expect(await page.evaluate(() => document.querySelector('.search-suggest__list').children.length), 'no stale items').toBe(0);

        // Submitting still goes to the ordinary results page.
        await Promise.all([page.waitForURL(/[?&]s=/), page.keyboard.press('Enter')]);
        expect(new URL(page.url()).searchParams.get('s')).toBe(SEARCH_TERM);
        await expect(page.locator('h1')).toContainText(`“${SEARCH_TERM}”`);
        await expect(page.locator('article.card').first()).toBeVisible();
      });
    }
  });

  test('an empty answer shows no box and announces "No suggestions"; non-http URLs in the answer are dropped', async ({ page, routes }) => {
    let body = '[]';
    await page.route(REST, (route) => route.fulfill({ status: 200, contentType: 'application/json', body }));
    await startHeaderSearch(page, routes);

    await typeInHeaderSearch(page, SEARCH_TERM);
    await expect(statusOf(page)).toHaveText('No suggestions');
    await expect(suggestBox(page)).toBeHidden();

    body = JSON.stringify([{ id: 9, title: 'Bad link', url: 'javascript:window.__xss=1' }]);
    await page.locator('#header-search .search-field').fill('');
    await typeInHeaderSearch(page, 'different');
    await expect(statusOf(page)).toHaveText('No suggestions');
    await expect(suggestBox(page), 'a javascript: URL is never turned into a link').toBeHidden();
    expect(await page.evaluate(() => window.__xss)).toBeUndefined();
  });

  test('a slow answer for an older phrase never overwrites the list for the newer phrase', async ({ page, routes }) => {
    await page.route(REST, async (route) => {
      const phrase = new URL(route.request().url()).searchParams.get('search');
      if (phrase === 'scoo') {
        await new Promise((resolve) => setTimeout(resolve, 900));
        try {
          await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify([{ id: 1, title: 'STALE ANSWER FOR scoo', url: `${BASE_ORIGIN}/stale/` }]) });
        } catch {
          // The page already aborted this request (AbortController): exactly what the theme is meant to do.
        }
        return;
      }
      await route.continue();
    });
    await startHeaderSearch(page, routes);
    await typeInHeaderSearch(page, 'scoo');
    await page.waitForTimeout(450); // the debounce fired, the slow request is in flight
    await typeInHeaderSearch(page, 'ter');
    await expect(suggestBox(page)).toBeVisible();
    await page.waitForTimeout(1200); // give the stale answer time to arrive
    await expect(suggestLinks(page).filter({ hasText: 'STALE ANSWER' }), 'the stale answer is discarded').toHaveCount(0);
    await expect(suggestLinks(page).first()).not.toHaveText('See all results');
  });

  test('titles from the API are shown as text: entities decode, markup stays inert', async ({ page, routes }) => {
    await page.route(REST, (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify([{ id: 1, title: 'Tom &amp; Jerry &#8211; &lt;b&gt;bold&lt;/b&gt; &lt;img src=x onerror=window.__xss=2&gt;', url: `${BASE_ORIGIN}/x/` }]),
      })
    );
    await startHeaderSearch(page, routes);
    await typeInHeaderSearch(page, SEARCH_TERM);
    await expect(suggestBox(page)).toBeVisible();
    const first = suggestLinks(page).first();
    await expect(first).toHaveText('Tom & Jerry – <b>bold</b> <img src=x onerror=window.__xss=2>');
    expect(await suggestBox(page).locator('b, img').count(), 'no markup was created from the title').toBe(0);
    expect(await page.evaluate(() => window.__xss)).toBeUndefined();
  });

  test('typing the XSS probe into the box does not execute and sends it URL-encoded', async ({ page, routes }) => {
    const requests = [];
    page.on('request', (request) => {
      if (request.url().includes('/wp-json/wp/v2/search')) {
        requests.push(request.url());
      }
    });
    await startHeaderSearch(page, routes);
    await typeInHeaderSearch(page, PROBE);
    await page.waitForTimeout(700);

    expect(await page.evaluate(() => window.__xss), 'the probe must not execute').toBeUndefined();
    expect(await page.locator('img[src="x"]').count(), 'no element was created from the probe').toBe(0);
    expect(requests.length).toBeGreaterThanOrEqual(1);
    expect(requests[requests.length - 1], 'the phrase is percent-encoded').toContain(encodeURIComponent(PROBE));
    expect(await page.locator('#header-search .search-field').inputValue()).toBe(PROBE);
  });
});

// ---------------------------------------------------------------------------------------------------
// Submitting
// ---------------------------------------------------------------------------------------------------
test.describe('submitting a search', () => {
  test('the header form and the home hero form both reach the results page (button and Enter)', async ({ page, routes }) => {
    await gotoReady(page, requireRoute(test, routes, 'home'));

    // Hero form: Enter.
    const hero = page.locator('.home-intro .search-field');
    await hero.fill('helmet');
    await Promise.all([page.waitForURL(/[?&]s=helmet/), hero.press('Enter')]);
    await expect(page.locator('h1')).toContainText('“helmet”');

    // Header form: submit button.
    await openSearch(page);
    const field = page.locator('#header-search .search-field');
    await expect(field, 'the results page repeats the current phrase in the header form').toHaveValue('helmet');
    await field.fill('licence');
    await Promise.all([page.waitForURL(/[?&]s=licence/), page.locator('#header-search .search-submit').click()]);
    await expect(page.locator('h1')).toContainText('“licence”');
  });

  test('leading and trailing spaces are ignored; special characters are not double-encoded', async ({ page }) => {
    await gotoReady(page, `/?s=${encodeURIComponent('  scooter  ')}`);
    await expect(page.locator('h1')).toHaveText('Results for “scooter”');

    await gotoReady(page, `/?s=${encodeURIComponent('Tom & Jerry <3 "quotes"')}`);
    await expect(page.locator('h1')).toHaveText('Results for “Tom & Jerry <3 "quotes"”');
    await expect(page.locator('.archive-header .search-field')).toHaveValue('Tom & Jerry <3 "quotes"');
    const crumb = await page.locator('.breadcrumbs__item--current').innerText();
    expect(crumb, 'no double-encoded entities in the breadcrumb').toBe('Search results for: Tom & Jerry <3 "quotes"');
  });
});

// ---------------------------------------------------------------------------------------------------
// Results page
// ---------------------------------------------------------------------------------------------------
test.describe('results page', () => {
  test('phrase, count, breadcrumb, highlighted matches and topic chips', async ({ page, routes }) => {
    await gotoReady(page, requireRoute(test, routes, 'search'));

    await expect(page.locator('h1')).toHaveText(`Results for “${SEARCH_TERM}”`);
    const meta = page.locator('.archive-meta');
    await expect(meta).toHaveAttribute('role', 'status');
    const metaText = (await meta.innerText()).trim();
    expect(metaText).toMatch(/^\d+ results?$/);
    const total = Number(metaText.split(' ')[0]);
    expect(total).toBeGreaterThan(0);

    const crumbs = await page.locator('.breadcrumbs__item').allInnerTexts();
    expect(crumbs.map((c) => c.trim())).toEqual(['Home', `Search results for: ${SEARCH_TERM}`]);

    // Highlighting: <mark> wraps the phrase words inside titles and excerpts, nowhere else.
    const marks = await page.locator('article.card mark').allInnerTexts();
    expect(marks.length, 'highlighted matches').toBeGreaterThan(0);
    for (const text of marks) {
      expect(text.toLowerCase(), 'a mark only wraps a word of the phrase').toBe(SEARCH_TERM.toLowerCase());
    }
    expect(await page.locator('article.card .card__title mark').count(), 'titles are highlighted too').toBeGreaterThan(0);
    expect(await page.locator('main mark').count(), 'every mark lives inside a result card').toBe(await page.locator('article.card mark').count());

    // Matching topics (categories whose name contains the phrase).
    const matching = page.locator('.archive-label', { hasText: 'Matching topics' });
    await expect(matching).toBeVisible();
    const topics = await matching.locator('xpath=following-sibling::ul[1]//a').allInnerTexts();
    expect(topics.map((t) => t.toLowerCase()).some((t) => t.includes(SEARCH_TERM.toLowerCase())), 'a topic named like the phrase is offered').toBe(true);
  });

  test('topic filter: "All topics" is current, a topic chip narrows the results and becomes current', async ({ page, routes }) => {
    await gotoReady(page, requireRoute(test, routes, 'search'));
    const filter = page.locator('ul[aria-labelledby="search-filter-label"]');
    await expect(filter).toBeVisible();
    const all = filter.locator('a.chip').first();
    await expect(all).toHaveText('All topics');
    await expect(all).toHaveAttribute('aria-current', 'true');
    await expect(all).toHaveClass(/is-active/);

    const totalBefore = Number((await page.locator('.archive-meta').innerText()).split(' ')[0]);
    const chip = filter.locator('a.chip').nth(1);
    const name = (await chip.innerText()).trim();
    await Promise.all([page.waitForURL(/[?&]cat=\d+/), chip.click()]);
    const url = new URL(page.url());
    expect(url.searchParams.get('s'), 'the phrase is kept').toBe(SEARCH_TERM);
    const totalAfter = Number((await page.locator('.archive-meta').innerText()).split(' ')[0]);
    expect(totalAfter, `"${name}" must not widen the results`).toBeLessThanOrEqual(totalBefore);

    const active = page.locator('ul[aria-labelledby="search-filter-label"] a.chip.is-active');
    await expect(active, 'exactly one chip is active').toHaveCount(1);
    await expect(active).toHaveText(name);
    await expect(active).toHaveAttribute('aria-current', 'true');
  });

  test('a multi-word phrase survives the topic filter links intact (no double encoding)', async ({ page }) => {
    const phrase = 'first scooter';
    await gotoReady(page, `/?s=${encodeURIComponent(phrase)}`);
    const filter = page.locator('ul[aria-labelledby="search-filter-label"]');
    test.skip((await filter.count()) === 0, 'no topic filter is offered for this phrase on this site');
    const hrefs = await filter.locator('a.chip').evaluateAll((els) => els.map((a) => a.getAttribute('href')));
    for (const href of hrefs) {
      expect(new URL(href, 'http://x/').searchParams.get('s'), `filter link ${href}`).toBe(phrase);
    }
    await Promise.all([page.waitForURL(/[?&]cat=\d+/), filter.locator('a.chip').nth(1).click()]);
    await expect(page.locator('h1')).toHaveText(`Results for “${phrase}”`);
  });

  test('pagination: next/previous keep the phrase, page 2 shows different articles', async ({ page, routes }) => {
    // A word every fixture article contains, so there is more than one page of results.
    await gotoReady(page, '/?s=article');
    const total = Number((await page.locator('.archive-meta').innerText()).split(' ')[0]);
    const perPage = await page.locator('article.card').count();
    test.skip(total <= perPage, `only ${total} results for "article": nothing to paginate on this site`);

    const pagination = page.locator('nav.pagination');
    await expect(pagination).toBeVisible();
    await expect(pagination).toHaveAttribute('aria-label', 'Pagination');
    await expect(pagination.locator('[aria-current="page"]')).toHaveText(/1/);
    const next = pagination.locator('a.next');
    const nextHref = await next.getAttribute('href');
    expect(nextHref, 'the next link keeps the phrase').toContain('s=article');

    const firstPageTitles = await page.locator('article.card .card__title').allInnerTexts();
    await Promise.all([page.waitForURL(/\/page\/2\//), next.click()]);
    await expect(page.locator('h1')).toHaveText('Results for “article”');
    await expect(pagination.locator('[aria-current="page"]')).toHaveText(/2/);
    const secondPageTitles = await page.locator('article.card .card__title').allInnerTexts();
    expect(secondPageTitles.length).toBeGreaterThan(0);
    expect(secondPageTitles.filter((title) => firstPageTitles.includes(title)), 'page 2 repeats nothing from page 1').toEqual([]);

    const prev = pagination.locator('a.prev');
    await expect(prev, 'a previous link exists on page 2').toBeVisible();
    expect(await prev.getAttribute('href')).toContain('s=article');
    // Every pagination link has an accessible name (its text includes screen-reader text).
    for (const link of await pagination.locator('a').all()) {
      expect(((await link.textContent()) || '').trim().length, 'pagination link name').toBeGreaterThan(0);
    }
  });

  test('no results: the phrase, "0 results", an explanation, popular topics, and a way forward', async ({ page, routes }) => {
    await gotoReady(page, requireRoute(test, routes, 'searchEmpty'));
    await expect(page.locator('h1')).toHaveText(`Results for “${NO_RESULT_TERM}”`);
    await expect(page.locator('.archive-meta')).toHaveText('0 results');
    const empty = page.locator('.empty-state');
    await expect(empty).toBeVisible();
    await expect(empty.locator('.empty-state__title')).toHaveText('No matching articles');
    await expect(empty.locator('.empty-state__text')).not.toBeEmpty();
    expect(await empty.locator('.chips a').count(), 'popular topics to continue with').toBeGreaterThan(0);
    await expect(page.locator('nav.pagination')).toHaveCount(0);
    await expect(page.locator('.archive-header .search-field'), 'the refine form keeps the phrase').toHaveValue(NO_RESULT_TERM);

    await Promise.all([page.waitForURL(/\/category\//), empty.locator('.chips a').first().click()]);
    await expect(page.locator('h1')).toBeVisible();
  });

  test('a single result says "1 result"', async ({ page }) => {
    await gotoReady(page, '/?s=Insurance');
    await expect(page.locator('.archive-meta')).toHaveText('1 result');
  });

  test('Vietnamese phrases work: diacritics are kept in the heading and highlighted in the results', async ({ page }) => {
    await gotoReady(page, `/?s=${encodeURIComponent('phố cổ')}`);
    await expect(page.locator('h1')).toHaveText('Results for “phố cổ”');
    const count = Number((await page.locator('.archive-meta').innerText()).split(' ')[0]);
    expect(count, 'Vietnamese articles are found').toBeGreaterThan(0);
    const marks = (await page.locator('article.card mark').allInnerTexts()).map((t) => t.toLowerCase());
    expect(marks.length).toBeGreaterThan(0);
    for (const mark of marks) {
      expect(['phố', 'cổ']).toContain(mark);
    }
  });

  test('the XSS probe as a phrase is shown as text everywhere and never executes', async ({ page }) => {
    await gotoReady(page, `/?s=${encodeURIComponent(PROBE)}`);
    await page.waitForTimeout(300);
    expect(await page.evaluate(() => window.__xss), 'window.__xss must stay undefined').toBeUndefined();
    expect(await page.locator('img[src="x"]').count(), 'no <img src=x> was injected').toBe(0);
    await expect(page.locator('h1')).toHaveText(`Results for “${PROBE}”`);
    await expect(page.locator('.archive-header .search-field')).toHaveValue(PROBE);
    const html = await page.content();
    expect(html, 'the raw probe must be escaped in the markup').not.toContain('<img src=x onerror=window.__xss=1>');
    const overflow = await measureOverflow(page);
    expect(overflow.scrollWidth).toBeLessThanOrEqual(overflow.innerWidth);
  });

  test('draft, private and scheduled articles never appear in results or suggestions', async ({ page, request }) => {
    for (const title of HIDDEN_TITLES) {
      await gotoReady(page, `/?s=${encodeURIComponent(title)}`);
      // The phrase itself is echoed in the heading, so look at the result cards only.
      const cardTitles = await page.locator('article.card .card__title').allInnerTexts();
      expect(cardTitles.filter((text) => text.includes(title)), `"${title}" must not be listed publicly`).toEqual([]);
    }
    const response = await request.get(`/wp-json/wp/v2/search?search=${encodeURIComponent('Hidden')}&per_page=20`);
    expect(response.status()).toBe(200);
    const items = await response.json();
    const leaked = items.filter((item) => HIDDEN_TITLES.some((title) => String(item.title).includes(title)));
    expect(leaked, 'hidden posts in the REST search used by the suggestions').toEqual([]);
  });

  test('an empty phrase does not break the page (observation)', async ({ page }, testInfo) => {
    const response = await page.goto('/?s=');
    expect(response.status()).toBe(200);
    await page.waitForLoadState('load');
    const facts = await page.evaluate(() => ({
      h1: document.querySelector('h1').textContent.trim(),
      meta: document.querySelector('.archive-meta')?.textContent.trim(),
      cards: document.querySelectorAll('article.card').length,
    }));
    note(testInfo, 'empty-phrase', `/?s= renders h1 "${facts.h1}", meta "${facts.meta}", ${facts.cards} cards`);
    await expect(page.locator('h1')).toBeVisible();
  });
});
