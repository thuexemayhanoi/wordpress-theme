/**
 * Browser end-to-end suite for the Nguyen Ha Editorial THEME (the companion plugin is out of scope).
 *
 * Run: npm run test:e2e   (= playwright test -c tests/e2e/playwright.config.mjs)
 * See tests/e2e/README.md for env vars, CI usage and what each spec guards.
 *
 * Design rules of this config:
 *  - retries are 0 on purpose: a test that needs a retry is a flaky test, and a flaky test must be fixed,
 *    not hidden;
 *  - traces and screenshots are kept only for failures and only in git-ignored folders;
 *  - three viewport projects. Tests that only make sense on one kind of layout carry a tag and are
 *    filtered per project (they are not reported as "skipped", they simply do not exist for that project):
 *      @desktop-only  needs the desktop navigation (hover, mega menu, wide TOC layout)   -> desktop
 *      @drawer        needs the mobile/tablet drawer navigation (<= 960px)               -> mobile, tablet
 *      @touch         needs a touch device                                               -> mobile
 *      @mobile-only   runs once and sets its own viewport width                          -> mobile
 *      @once          viewport independent, runs once                                    -> desktop
 *      @no-tablet     matrix tests that cover mobile and desktop only                    -> mobile, desktop
 */
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig } from '@playwright/test';
import { ARTIFACTS_DIR, BASE_URL, VIEWPORTS } from './helpers/env.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const workers = Math.max(1, Number(process.env.NH_E2E_WORKERS || 3));
// 0 by default. A CI job may set NH_E2E_RETRIES=1 to find out which tests are flaky: Playwright then reports
// them as "flaky" and tests/e2e/summarize.mjs still exits 1 for them.
const retries = Math.max(0, Number(process.env.NH_E2E_RETRIES || 0));

export default defineConfig({
  // Specs live next to this config (tests/e2e/*.spec.mjs); shared code is in tests/e2e/helpers.
  testDir: here,
  testMatch: '*.spec.mjs',
  outputDir: path.join(ARTIFACTS_DIR, 'test-results'),
  globalSetup: path.join(here, 'global-setup.mjs'),

  timeout: 60_000,
  expect: { timeout: 8_000 },
  fullyParallel: true,
  forbidOnly: Boolean(process.env.CI),
  retries,
  workers,

  reporter: [
    ['list'],
    ['html', { open: 'never', outputFolder: path.join(ARTIFACTS_DIR, 'report') }],
    ['json', { outputFile: path.join(ARTIFACTS_DIR, 'results.json') }],
  ],

  use: {
    baseURL: BASE_URL,
    locale: 'en-US',
    // Fixed zone: post dates ("October 14, 2026") must not depend on the machine running the suite.
    timezoneId: 'Asia/Ho_Chi_Minh',
    colorScheme: 'light',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'off',
    actionTimeout: 10_000,
    navigationTimeout: 30_000,
  },

  projects: [
    {
      name: 'mobile',
      use: { viewport: VIEWPORTS.mobile, hasTouch: true, isMobile: true, deviceScaleFactor: 1 },
      grepInvert: /@desktop-only|@once/,
    },
    {
      name: 'tablet',
      use: { viewport: VIEWPORTS.tablet, deviceScaleFactor: 1 },
      grepInvert: /@desktop-only|@once|@mobile-only|@touch|@no-tablet/,
    },
    {
      name: 'desktop',
      use: { viewport: VIEWPORTS.desktop, deviceScaleFactor: 1 },
      grepInvert: /@drawer|@touch|@mobile-only/,
    },
  ],
});
