/**
 * Page helpers shared by the specs. Everything here is read-only with respect to the site under test.
 *
 * Note on functions passed to page.evaluate(): Playwright serialises them, so they must not close over
 * module variables. Each one is self-contained.
 */
import { expect } from '@playwright/test';

/**
 * Navigate and wait until the page is really ready:
 *  - the load event has fired (the deferred theme script has run by then);
 *  - the non-critical stylesheet has been swapped in (it is loaded with media="print" + onload, so the
 *    footer, FAQ, print and motion rules only apply once the swap happened).
 * Do not use it on JavaScript-disabled pages: the swap needs JavaScript (there a <noscript> link applies).
 */
export async function gotoReady(page, target, { timeout = 15_000 } = {}) {
  const response = await page.goto(target, { waitUntil: 'load' });
  await waitForStyles(page, timeout);
  return response;
}

export async function waitForStyles(page, timeout = 15_000) {
  await page.waitForFunction(
    () => {
      const link = document.getElementById('nh-editorial-main-css');
      return !link || link.media === 'all' || link.media === '';
    },
    null,
    { timeout }
  );
}

/** Wait until window.scrollY stops changing (the theme uses smooth scrolling). Returns the final scrollY. */
export async function settleScroll(page, { quiet = 160, timeout = 5_000 } = {}) {
  const started = Date.now();
  let last = await page.evaluate(() => window.scrollY);
  let stableSince = Date.now();
  while (Date.now() - started < timeout) {
    await page.waitForTimeout(40);
    const now = await page.evaluate(() => window.scrollY);
    if (now !== last) {
      last = now;
      stableSince = Date.now();
    } else if (Date.now() - stableSince >= quiet) {
      break;
    }
  }
  return last;
}

/** Instantly scroll (bypassing the theme's smooth scrolling) and let layout settle. */
export async function jumpTo(page, y) {
  await page.evaluate((top) => window.scrollTo({ top, left: 0, behavior: 'instant' }), y);
  await page.waitForTimeout(60);
}

/** Bounding box of the sticky header. */
export async function headerRect(page) {
  return page.evaluate(() => {
    const r = document.querySelector('.site-header').getBoundingClientRect();
    return { top: r.top, bottom: r.bottom, height: r.height, left: r.left, right: r.right };
  });
}

/** True when the drawer navigation is in use (the menu button is displayed). */
export async function isDrawerMode(page) {
  return page.locator('.nav-toggle').isVisible();
}

export async function openDrawer(page) {
  await page.locator('.nav-toggle').click();
  await expect(page.locator('#primary-navigation')).toHaveClass(/is-open/);
}

export async function openSearch(page) {
  await page.locator('.search-toggle').click();
  await expect(page.locator('#header-search')).toBeVisible();
}

/** Type into the header search the way a person does (one key at a time) and wait for suggestions. */
export async function typeInHeaderSearch(page, text, { delay = 35 } = {}) {
  const input = page.locator('#header-search .search-field');
  await input.focus();
  await input.pressSequentially(text, { delay });
  return input;
}

/** Make every lazy image load: force eager loading and wait until each one has decoded. */
export async function loadAllImages(page, timeout = 15_000) {
  await page.evaluate(() => {
    document.querySelectorAll('img[loading="lazy"]').forEach((img) => {
      img.loading = 'eager';
    });
  });
  await page.waitForFunction(() => [...document.images].every((img) => img.complete), null, { timeout });
  await page.evaluate(() => Promise.all([...document.images].map((img) => img.decode().catch(() => undefined))));
}

/**
 * Horizontal overflow of the page plus the elements that cause it (ignoring boxes that sit inside a
 * container that scrolls or clips on purpose, such as the table region).
 */
export async function measureOverflow(page) {
  return page.evaluate(() => {
    const root = document.documentElement;
    const viewport = window.innerWidth;
    const describe = (el) => {
      const cls = typeof el.className === 'string' ? el.className.trim().split(/\s+/).slice(0, 2).join('.') : '';
      return `${el.tagName.toLowerCase()}${el.id ? '#' + el.id : ''}${cls ? '.' + cls : ''}`;
    };
    const insideClipper = (el) => {
      for (let parent = el.parentElement; parent && parent !== document.body; parent = parent.parentElement) {
        if (/(auto|scroll|hidden|clip)/.test(getComputedStyle(parent).overflowX)) {
          return true;
        }
      }
      return false;
    };
    const offenders = [];
    document.querySelectorAll('body *').forEach((el) => {
      const rect = el.getBoundingClientRect();
      if (rect.width <= 1 || rect.height <= 1) {
        return;
      }
      if ((rect.right > viewport + 1 || rect.left < -1) && !insideClipper(el)) {
        offenders.push({ element: describe(el), left: Math.round(rect.left), right: Math.round(rect.right), width: Math.round(rect.width) });
      }
    });
    return {
      scrollWidth: root.scrollWidth,
      clientWidth: root.clientWidth,
      innerWidth: viewport,
      overflowPx: root.scrollWidth - viewport,
      offenders: offenders.slice(0, 12),
    };
  });
}

/**
 * Facts about the focused element, evaluated inside the page. Returns null when nothing (or only the
 * document) has focus.
 *
 * "covered" counts how many of a 5x3 grid of points inside the visible part of the element are painted
 * over by something that is neither the element nor part of it (document.elementFromPoint decides what
 * is really on top). All points covered = entirely obscured (a WCAG 2.2 SC 2.4.11 failure); some =
 * partially obscured (allowed at AA, not at AAA 2.4.12).
 */
export function describeFocus() {
  const el = document.activeElement;
  if (!el || el === document.body || el === document.documentElement) {
    return null;
  }
  const header = document.querySelector('.site-header');
  const rect = el.getBoundingClientRect();
  const width = document.documentElement.clientWidth;
  const height = window.innerHeight;
  const visible = {
    left: Math.max(rect.left, 0),
    top: Math.max(rect.top, 0),
    right: Math.min(rect.right, width),
    bottom: Math.min(rect.bottom, height),
  };
  const w = visible.right - visible.left;
  const h = visible.bottom - visible.top;
  const inViewport = w > 0 && h > 0;

  const cls = typeof el.className === 'string' ? el.className.trim().split(/\s+/).slice(0, 2).join('.') : '';
  const text = (el.getAttribute('aria-label') || el.textContent || el.getAttribute('value') || '').trim().replace(/\s+/g, ' ').slice(0, 50);
  const path = [];
  for (let node = el; node && node.nodeType === 1 && node !== document.body; node = node.parentElement) {
    const siblings = node.parentElement ? [...node.parentElement.children].indexOf(node) : 0;
    path.unshift(`${node.tagName.toLowerCase()}[${siblings}]`);
  }

  let covered = 0;
  let samples = 0;
  let coveredBy = '';
  if (inViewport) {
    for (let ix = 0; ix < 5; ix += 1) {
      for (let iy = 0; iy < 3; iy += 1) {
        const x = visible.left + (w * (ix + 0.5)) / 5;
        const y = visible.top + (h * (iy + 0.5)) / 3;
        const top = document.elementFromPoint(x, y);
        samples += 1;
        if (top && !(el.contains(top) || top.contains(el))) {
          covered += 1;
          if (!coveredBy) {
            const topClass = typeof top.className === 'string' ? top.className.trim().split(/\s+/).slice(0, 2).join('.') : '';
            coveredBy = `${top.tagName.toLowerCase()}${top.id ? '#' + top.id : ''}${topClass ? '.' + topClass : ''}`;
          }
        }
      }
    }
  }
  return {
    key: path.join('>'),
    tag: el.tagName.toLowerCase(),
    label: `${el.tagName.toLowerCase()}${cls ? '.' + cls : ''} "${text}"`,
    inHeader: header ? header.contains(el) : false,
    inViewport,
    samples,
    covered,
    coveredBy,
    entirelyObscured: inViewport && samples > 0 && covered === samples,
    partiallyObscured: inViewport && covered > 0 && covered < samples,
    top: Math.round(rect.top),
    bottom: Math.round(rect.bottom),
  };
}

/**
 * Press Tab repeatedly from the top of the page and describe every stop (stops when focus wraps).
 *
 * The theme sets scroll-behavior: smooth, so right after a Tab the page is still animating towards the
 * focused element. WCAG 2.4.11 judges the position where focus ends up, so the animation is switched off
 * for the walk (instead of waiting ~250 ms per stop); the scroll target is the same either way.
 */
export async function focusWalk(page, { max = 160 } = {}) {
  await page.evaluate(() => {
    const style = document.createElement('style');
    style.textContent = 'html{scroll-behavior:auto !important}';
    document.head.appendChild(style);
    window.scrollTo({ top: 0, left: 0, behavior: 'instant' });
    if (document.activeElement && document.activeElement !== document.body) {
      document.activeElement.blur();
    }
  });
  const stops = [];
  const seen = new Set();
  for (let i = 0; i < max; i += 1) {
    await page.keyboard.press('Tab');
    const info = await page.evaluate(describeFocus);
    if (!info || seen.has(info.key)) {
      break;
    }
    seen.add(info.key);
    stops.push(info);
  }
  return stops;
}

/** Records every change of data-theme on <html> together with how far parsing had got. Install before navigation. */
export function themeTraceInit() {
  window.__themeTrace = [];
  const note = (why) => {
    window.__themeTrace.push({
      why,
      theme: document.documentElement.getAttribute('data-theme'),
      readyState: document.readyState,
      hasBody: Boolean(document.body),
      t: performance.now(),
    });
  };
  // Observe the document node: <html> may not exist yet when init scripts run.
  new MutationObserver(() => note('mutation')).observe(document, { attributes: true, subtree: true, attributeFilter: ['data-theme'] });
  document.addEventListener('DOMContentLoaded', () => note('DOMContentLoaded'));
  try {
    new PerformanceObserver((list) => {
      list.getEntries().forEach((entry) => {
        if (entry.name === 'first-paint' || entry.name === 'first-contentful-paint') {
          window.__themeTrace.push({
            why: entry.name,
            theme: document.documentElement.getAttribute('data-theme'),
            readyState: document.readyState,
            hasBody: Boolean(document.body),
            t: entry.startTime,
          });
        }
      });
    }).observe({ type: 'paint', buffered: true });
  } catch (error) {
    // Paint timing unavailable: the mutation records are still checked.
  }
}

/** Accumulates layout shifts without recent input (the CLS definition). Install before navigation. */
export function layoutShiftInit() {
  window.__cls = 0;
  window.__clsEntries = [];
  try {
    new PerformanceObserver((list) => {
      list.getEntries().forEach((entry) => {
        if (!entry.hadRecentInput) {
          window.__cls += entry.value;
          window.__clsEntries.push({
            value: Number(entry.value.toFixed(4)),
            at: Math.round(entry.startTime),
            nodes: (entry.sources || []).slice(0, 3).map((source) => {
              const node = source.node;
              if (!node) {
                return '?';
              }
              const cls = typeof node.className === 'string' ? node.className.trim().split(/\s+/)[0] : '';
              return `${node.nodeName.toLowerCase()}${cls ? '.' + cls : ''}`;
            }),
          });
        }
      });
    }).observe({ type: 'layout-shift', buffered: true });
  } catch (error) {
    window.__cls = -1;
  }
}

/** Attach structured data to the test report (shows up in the HTML report and in results.json). */
export async function attachJson(testInfo, name, data) {
  await testInfo.attach(name, { body: JSON.stringify(data, null, 2), contentType: 'application/json' });
}

/** Add a visible note to the report without failing the test (measurements, informational findings). */
export function note(testInfo, type, description) {
  testInfo.annotations.push({ type, description: String(description) });
}

/** A fresh browser context configured like the current project (viewport, touch, scale, locale, zone). */
export async function newProjectContext(browser, testInfo, overrides = {}) {
  const use = testInfo.project.use;
  return browser.newContext({
    baseURL: use.baseURL,
    viewport: use.viewport,
    hasTouch: use.hasTouch,
    isMobile: use.isMobile,
    deviceScaleFactor: use.deviceScaleFactor,
    locale: use.locale,
    timezoneId: use.timezoneId,
    colorScheme: use.colorScheme,
    ...(use.contextOptions || {}),
    ...overrides,
  });
}
