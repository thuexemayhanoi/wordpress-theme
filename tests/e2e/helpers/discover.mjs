/**
 * Finds the URL of every page type by walking the real DOM of the site under test, so the suite keeps
 * working on a freshly seeded CI site (different ids, dates, slugs) and never depends on a hard-coded
 * permalink. It only reads: no request in this file changes anything on the site.
 *
 * The result is a map { pageTypeKey: "/site-relative/path/?query" } plus human readable notes about
 * anything that could not be found (those page types are skipped, or fail with NH_E2E_REQUIRE_ALL=1).
 */
import { chromium } from '@playwright/test';
import { BASE_ORIGIN, NO_RESULT_TERM, SEARCH_TERM, abs } from './env.mjs';

/** Characters that only occur in Vietnamese text: used to pick the Vietnamese hub without naming it. */
const VIETNAMESE = /[ạảãàáâậầấẩẫăắằặẳẵéèẻẽẹêếềểễệíìỉĩịóòỏõọôốồổỗộơớờởỡợúùủũụưứừửữựýỳỷỹỵđ]/i;

/** Site-relative path (with query) for a same-origin absolute URL, or null for anything else. */
export function toPath(href) {
  if (!href) {
    return null;
  }
  try {
    const url = new URL(href, BASE_ORIGIN);
    return url.origin === BASE_ORIGIN ? url.pathname + url.search : null;
  } catch {
    return null;
  }
}

/** Facts about one article/page URL, read from its DOM. */
async function inspect(page, path) {
  const response = await page.goto(abs(path), { waitUntil: 'domcontentloaded' });
  return page.evaluate((status) => {
    const first = (selector, attribute = 'href') => {
      const element = document.querySelector(selector);
      return element ? element[attribute] || element.getAttribute(attribute) : null;
    };
    return {
      status,
      hasToc: Boolean(document.querySelector('.toc')),
      hasFaq: Boolean(document.querySelector('.faq')),
      hasSummary: Boolean(document.querySelector('.summary')),
      hasSources: Boolean(document.querySelector('.sources')),
      hasTable: Boolean(document.querySelector('.table-scroll')),
      hasComments: Boolean(document.querySelector('.comment-list li')),
      hasPasswordForm: Boolean(document.querySelector('form.post-password-form')),
      pillar: first('.pillar-box__title a'),
      author: first('a[rel="author"]'),
      tag: first('.chips--tags a[rel="tag"]'),
    };
  }, response ? response.status() : 0);
}

/**
 * @returns {Promise<{routes: Record<string, string>, notes: string[]}>}
 */
export async function discoverRoutes() {
  const browser = await chromium.launch();
  const context = await browser.newContext();
  const page = await context.newPage();
  const routes = {};
  const notes = [];

  try {
    // ---------------------------------------------------------------- home + navigation
    await page.goto(abs('/'), { waitUntil: 'domcontentloaded' });
    routes.home = '/';

    const home = await page.evaluate(() => {
      const links = (selector) => [...document.querySelectorAll(selector)].map((a) => ({ href: a.href, text: a.textContent.trim() }));
      return {
        hubs: [...document.querySelectorAll('#primary-menu > li.menu-item-has-children')].map((li) => ({
          href: li.querySelector(':scope > a').href,
          children: [...li.querySelectorAll(':scope > .sub-menu a')].map((a) => a.href),
        })),
        topics: links('.home-intro .chips a, .footer-list a'),
        cards: [...document.querySelectorAll('article.card--post, article.card--hero, article.card--compact')].map((card) => ({
          href: card.querySelector('.card__title a')?.href || '',
          protected: card.classList.contains('post-password-required'),
          sticky: card.classList.contains('sticky'),
        })),
        next: document.querySelector('.pagination a.next')?.href || null,
        footerMenu: links('.footer-menu a'),
      };
    });

    const homeNext = toPath(home.next);
    routes.homePage2 = homeNext || '/page/2/';
    if (!homeNext) {
      notes.push('home page has no "next" pagination link: homePage2 falls back to /page/2/');
    }

    // ---------------------------------------------------------------- categories
    const hub = home.hubs[0];
    const hubTopic = hub ? hub.href : home.topics[0]?.href;
    if (hubTopic) {
      routes.categoryHub = toPath(hubTopic);
    } else {
      notes.push('no hub link found in the primary navigation or the topic chips');
    }
    if (hub && hub.children[0]) {
      routes.categoryChild = toPath(hub.children[0]);
    } else {
      notes.push('the first hub has no child category in the navigation: categoryChild not available');
    }
    const vi = home.topics.find((topic) => VIETNAMESE.test(topic.text));
    if (vi) {
      routes.categoryVi = toPath(vi.href);
    } else {
      notes.push('no Vietnamese topic found: categoryVi not available');
    }

    if (routes.categoryHub) {
      await page.goto(abs(routes.categoryHub), { waitUntil: 'domcontentloaded' });
      const next = await page.evaluate(() => document.querySelector('.pagination a.next')?.href || null);
      if (toPath(next)) {
        routes.categoryPage2 = toPath(next);
      } else {
        notes.push('the first hub fits on one page: categoryPage2 not available');
      }
    }

    // ---------------------------------------------------------------- articles
    const candidates = [];
    const seen = new Set();
    const pushCards = (cards) => {
      for (const card of cards) {
        const path = toPath(card.href);
        if (path && !seen.has(path)) {
          seen.add(path);
          candidates.push({ path, protected: card.protected, sticky: card.sticky });
        }
      }
    };
    // Sticky/featured first (they are the fully featured fixtures), then the latest list.
    pushCards([...home.cards].sort((a, b) => Number(b.sticky) - Number(a.sticky)));

    const protectedCard = home.cards.find((card) => card.protected);
    if (protectedCard) {
      routes.passwordPost = toPath(protectedCard.href);
    }
    if (!routes.passwordPost) {
      // A theme may keep protected posts out of its lists (it should: they leak their title there). The public
      // REST API still lists them, flagged with content.protected, so the page type stays reachable.
      try {
        const response = await page.request.get(abs('/wp-json/wp/v2/posts?per_page=100&_fields=link,content.protected'));
        if (response.ok()) {
          const posts = await response.json();
          const found = posts.find((post) => post.content && post.content.protected === true);
          if (found) {
            routes.passwordPost = toPath(String(found.link));
            notes.push('passwordPost found through the REST API (no list on the site links to it)');
          }
        }
      } catch {
        // REST disabled: passwordPost stays unavailable and its tests are skipped.
      }
    }

    let rich = null;
    let plain = null;
    for (const candidate of candidates.filter((c) => !c.protected).slice(0, 24)) {
      const facts = await inspect(page, candidate.path);
      if (facts.status !== 200 || facts.hasPasswordForm) {
        continue;
      }
      if (!rich && facts.hasToc && facts.hasFaq && facts.hasSummary) {
        rich = { ...candidate, facts };
      } else if (!plain && !facts.hasFaq && !facts.hasSummary) {
        plain = { ...candidate, facts };
      }
      if (rich && plain) {
        break;
      }
    }
    if (rich) {
      routes.singleRich = rich.path;
    } else {
      notes.push('no article with summary + TOC + FAQ found on the home page: singleRich not available');
    }
    if (plain) {
      routes.singlePlain = plain.path;
    } else {
      notes.push('no plain article found: singlePlain not available');
    }

    // The article that has comments: ask the public REST API (read-only) for the latest comment.
    try {
      const response = await page.request.get(abs('/wp-json/wp/v2/comments?per_page=1&_fields=link'));
      if (response.ok()) {
        const [comment] = await response.json();
        const path = comment ? toPath(String(comment.link).split('#')[0]) : null;
        if (path) {
          routes.singleComments = path;
        }
      }
    } catch {
      // REST disabled: handled by the note below.
    }
    if (!routes.singleComments) {
      notes.push('no article with comments found: singleComments not available');
    }

    // ---------------------------------------------------------------- pages and archives from the rich article
    if (rich) {
      const facts = rich.facts;
      if (facts.pillar) {
        routes.pillar = toPath(facts.pillar);
      }
      if (facts.author) {
        routes.author = toPath(facts.author);
      }
      if (facts.tag) {
        routes.tag = toPath(facts.tag);
      }
    }
    for (const [key, value] of [['pillar', routes.pillar], ['author', routes.author], ['tag', routes.tag]]) {
      if (!value) {
        notes.push(`${key} link not found on the rich article: ${key} not available`);
      }
    }
    const aboutLink = home.footerMenu.find((link) => toPath(link.href) && toPath(link.href) !== '/');
    if (aboutLink) {
      routes.page = toPath(aboutLink.href);
    } else {
      notes.push('no footer menu page link found: page not available');
    }

    // ---------------------------------------------------------------- fixed URLs
    routes.search = `/?s=${encodeURIComponent(SEARCH_TERM)}`;
    routes.searchEmpty = `/?s=${encodeURIComponent(NO_RESULT_TERM)}`;
    routes.notFound = '/nothing-here/';
  } finally {
    await browser.close();
  }

  return { routes, notes };
}
