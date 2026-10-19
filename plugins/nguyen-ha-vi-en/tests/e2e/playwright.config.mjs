/**
 * Browser tests of the VI-EN language switcher at three widths (phone, tablet, desktop).
 *
 *   NH_BASE_URL=http://127.0.0.1:8080 npx playwright test -c plugins/nguyen-ha-vi-en/tests/e2e/playwright.config.mjs
 *
 * The site is the one seeded by ../site/seed.php with the plugin switched on. Retries stay at 0 on purpose.
 */
import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: '.',
  testMatch: '*.spec.mjs',
  timeout: 60_000,
  expect: { timeout: 8_000 },
  fullyParallel: true,
  forbidOnly: Boolean(process.env.CI),
  retries: 0,
  workers: Math.max(1, Number(process.env.NH_E2E_WORKERS || 2)),
  reporter: [['list'], ...(process.env.NH_E2E_JUNIT ? [['junit', { outputFile: process.env.NH_E2E_JUNIT }]] : [])],
  use: {
    baseURL: (process.env.NH_BASE_URL || 'http://127.0.0.1:8080').replace(/\/+$/, ''),
    locale: 'en-US',
    timezoneId: 'Asia/Ho_Chi_Minh',
    colorScheme: 'light',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    actionTimeout: 10_000,
    navigationTimeout: 30_000,
  },
  projects: [
    { name: 'mobile', use: { viewport: { width: 375, height: 812 }, hasTouch: true, isMobile: true } },
    { name: 'tablet', use: { viewport: { width: 768, height: 1024 } } },
    { name: 'desktop', use: { viewport: { width: 1440, height: 900 } } },
  ],
});
