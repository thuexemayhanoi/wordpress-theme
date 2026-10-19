/**
 * The language switcher on a phone, a tablet and a desktop: it is on the page, inside the viewport, reachable with the
 * keyboard, big enough to touch, takes the visitor to the other language, and passes axe in the light and the dark
 * colour scheme. Without a horizontal scroll bar at any width.
 *
 * NH_E2E_THEME=editorial (the default) checks the whole page of the Nguyen Ha Editorial theme, where the switcher comes
 * right after the theme's skip link. With any other value (a theme the plugin knows nothing about) axe checks the
 * switcher itself, and the place of the skip link is left to the theme.
 */
import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const PAGES = [
  { name: 'Vietnamese post with a translation', url: '/thue-xe-may-ha-noi/', lang: 'vi', other: '/en/motorbike-rental-in-hanoi/', otherLang: 'en-US' },
  { name: 'English post', url: '/en/motorbike-rental-in-hanoi/', lang: 'en-US', other: '/thue-xe-may-ha-noi/', otherLang: 'vi' },
  { name: 'Vietnamese post without a translation', url: '/bai-chua-dich/', lang: 'vi', other: '/en/', otherLang: 'en-US' },
  { name: 'English category', url: '/en/category/travel-tips/', lang: 'en-US', other: '/', otherLang: 'vi' },
  { name: 'Vietnamese home', url: '/', lang: 'vi', other: '/en/', otherLang: 'en-US' },
];

const AXE_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

const OWN_THEME = (process.env.NH_E2E_THEME || 'editorial') === 'editorial';

// The name of the navigation is in the language of the page; the name of each link starts with its visible text.
const NAV_NAME = { vi: 'Ngôn ngữ', 'en-US': 'Language' };

for (const page of PAGES) {
  test.describe(page.name, () => {
    test('the switcher is on the page, inside the viewport and big enough to touch', async ({ page: tab }) => {
      await tab.goto(page.url);
      const nav = tab.locator('.nh-vien-switcher');
      await expect(nav).toHaveCount(1);
      await expect(nav).toBeVisible();
      await expect(nav).toHaveAttribute('aria-label', NAV_NAME[page.lang]);
      const viewport = tab.viewportSize();
      const box = await nav.boundingBox();
      expect(box.x).toBeGreaterThanOrEqual(0);
      expect(box.x + box.width).toBeLessThanOrEqual(viewport.width + 0.5);
      const links = nav.locator('a');
      await expect(links).toHaveCount(2);
      for (let i = 0; i < 2; i += 1) {
        const link = await links.nth(i).boundingBox();
        expect(link.width).toBeGreaterThanOrEqual(44);
        expect(link.height).toBeGreaterThanOrEqual(44);
      }
      const overflow = await tab.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
      expect(overflow, 'no horizontal scroll').toBeLessThanOrEqual(1);
      await expect(tab.locator('html')).toHaveAttribute('lang', page.lang);
    });

    test('each link is named by its visible text first (label in name) and says which language it is', async ({ page: tab }) => {
      await tab.goto(page.url);
      const names = await tab.locator('.nh-vien-switcher a').evaluateAll((links) => links.map((a) => ({ text: a.textContent.trim(), name: a.getAttribute('aria-label') })));
      expect(names).toEqual([
        { text: 'VI', name: 'VI: Tiếng Việt' },
        { text: 'EN', name: 'EN: English' },
      ]);
    });

    test('the keyboard reaches both links, shows where it is, and Enter switches the language', async ({ page: tab }) => {
      await tab.goto(page.url);
      const other = tab.locator(`.nh-vien-switcher a[hreflang="${page.otherLang === 'vi' ? 'vi' : 'en'}"]`);
      await other.focus();
      await expect(other).toBeFocused();
      const outline = await other.evaluate((el) => {
        const style = getComputedStyle(el);
        return { style: style.outlineStyle, width: parseFloat(style.outlineWidth) };
      });
      expect(outline.style).not.toBe('none');
      expect(outline.width).toBeGreaterThan(0);
      await Promise.all([tab.waitForURL((url) => url.pathname === page.other), tab.keyboard.press('Enter')]);
      await expect(tab.locator('html')).toHaveAttribute('lang', page.otherLang);
      await expect(tab.locator('.nh-vien-switcher a[aria-current="page"]')).toHaveCount(1);
    });

    test('the skip link is still the first stop of the keyboard; the switcher comes right after it', async ({ page: tab }) => {
      await tab.goto(page.url);
      test.skip(!OWN_THEME || (await tab.locator('.skip-link').count()) === 0, 'where the skip link goes is up to the theme');
      const before = await tab.evaluate(() => {
        const skip = document.querySelector('.skip-link');
        const bar = document.querySelector('.nh-vien-switcher');
        return Boolean(skip.compareDocumentPosition(bar) & Node.DOCUMENT_POSITION_FOLLOWING);
      });
      expect(before, 'the switcher is after the skip link in the document').toBe(true);
      await tab.keyboard.press('Tab');
      await expect(tab.locator('.skip-link')).toBeFocused();
      await tab.keyboard.press('Tab');
      await expect(tab.locator('.nh-vien-switcher a').first()).toBeFocused();
    });

    for (const scheme of ['light', 'dark']) {
      test(`axe finds no violation (${scheme} colour scheme)`, async ({ page: tab }) => {
        await tab.emulateMedia({ colorScheme: scheme });
        await tab.goto(page.url);
        await expect(tab.locator('.nh-vien-switcher')).toBeVisible();
        // The fixture has a post whose title is an <img> probe for escaping; WordPress and the theme print an administrator's
        // title as typed, so the probe shows up as a bare image in lists. It is not what this test is about.
        await tab.evaluate(() => document.querySelectorAll('img[src="x"]').forEach((img) => img.remove()));
        const axe = new AxeBuilder({ page: tab }).withTags(AXE_TAGS);
        const results = await (OWN_THEME ? axe : axe.include('.nh-vien-bar')).analyze();
        const report = results.violations.map((v) => `${v.id}: ${v.nodes.slice(0, 3).map((n) => n.target.join(' ')).join(' | ')}`);
        expect(report, report.join('\n')).toEqual([]);
      });
    }
  });
}

test('the switcher link of the current language is marked as the current page, once', async ({ page: tab }) => {
  await tab.goto('/en/motorbike-rental-in-hanoi/');
  await expect(tab.locator('.nh-vien-switcher a[aria-current="page"]')).toHaveCount(1);
  await expect(tab.locator('.nh-vien-switcher a[aria-current="page"]')).toHaveAttribute('lang', 'en');
});
