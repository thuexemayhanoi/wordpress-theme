/**
 * Single place that reads the environment. Nothing in the suite hard-codes an absolute path or a host:
 * everything is derived from these values, so the same suite runs locally and in CI.
 *
 *   NH_BASE_URL          Site under test (default http://127.0.0.1:8071). No trailing slash needed.
 *   NH_E2E_WORKERS       Parallel workers (default 3; the PHP built-in server runs 4 workers).
 *   NH_E2E_ARTIFACTS     Output dir for traces, reports, routes.json (default tests/e2e/.artifacts).
 *   NH_E2E_SCREENSHOTS   Output dir for the visual sweep (default tests/e2e/__screenshots__).
 *   NH_SEARCH_TERM       A word that matches several fixture articles (default "scooter").
 *   NH_HIDDEN_TITLES     Comma separated titles of draft/private/scheduled fixtures that must never
 *                        appear on a public page (default: the titles tests/bin/seed-fixtures.php creates).
 *   NH_CRAWL_LIMIT       Max URLs the link crawler visits (default 300).
 *   NH_E2E_REQUIRE_ALL   "1" makes a page type that cannot be discovered a failure instead of a skip.
 */
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));

export const E2E_DIR = path.resolve(here, '..');
export const BASE_URL = (process.env.NH_BASE_URL || 'http://127.0.0.1:8071').replace(/\/+$/, '');
export const BASE_ORIGIN = new URL(BASE_URL).origin;
export const ARTIFACTS_DIR = path.resolve(process.env.NH_E2E_ARTIFACTS || path.join(E2E_DIR, '.artifacts'));
export const SCREENSHOT_DIR = path.resolve(process.env.NH_E2E_SCREENSHOTS || path.join(E2E_DIR, '__screenshots__'));
export const ROUTES_FILE = path.join(ARTIFACTS_DIR, 'routes.json');
export const SEARCH_TERM = process.env.NH_SEARCH_TERM || 'scooter';
export const NO_RESULT_TERM = 'zzqxjkvnohit';
export const CRAWL_LIMIT = Math.max(1, Number(process.env.NH_CRAWL_LIMIT || 300));
export const REQUIRE_ALL = process.env.NH_E2E_REQUIRE_ALL === '1';
// WordPress lists password-protected posts ("Protected: <title>") in its archives and widgets by default. The theme's
// own components never do (docs/DATA-CONTRACT.md), and the crawler fails on a link to one. Set this to 1 to accept
// WordPress' default: the links are then reported as an observation instead.
export const ALLOW_PROTECTED_LINKS = process.env.NH_ALLOW_PROTECTED_LINKS === '1';
export const HIDDEN_TITLES = (process.env.NH_HIDDEN_TITLES || 'Hidden draft article,Hidden private article,Hidden scheduled article')
  .split(',')
  .map((title) => title.trim())
  .filter(Boolean);

/** Viewport widths of the three Playwright projects (see playwright.config.mjs). */
export const VIEWPORTS = {
  mobile: { width: 375, height: 812 },
  tablet: { width: 768, height: 1024 },
  desktop: { width: 1440, height: 900 },
};

/** Narrowest width WCAG 2.2 SC 1.4.10 (Reflow) asks to support without two-dimensional scrolling. */
export const REFLOW_WIDTH = 320;

/** CSS breakpoints of the theme (assets/src/css): used to sweep both sides of each boundary. */
export const BREAKPOINT_WIDTHS = [320, 375, 414, 599, 600, 639, 640, 768, 899, 900, 960, 961, 1023, 1024, 1099, 1100, 1180, 1280, 1440];

/** Absolute URL for a site-relative path (or an absolute URL already pointing at the site). */
export function abs(urlOrPath) {
  if (/^https?:\/\//i.test(urlOrPath)) {
    return urlOrPath;
  }
  return BASE_URL + (urlOrPath.startsWith('/') ? '' : '/') + urlOrPath;
}
