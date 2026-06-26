import { defineConfig, devices } from '@playwright/test';

import { A11yQa } from './tests/ci/a11y.mjs';

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8080';
if (!process.env.PLAYWRIGHT_BASE_URL) {
  console.warn(`[playwright.config] baseURL: ${baseURL} (Fallback — PLAYWRIGHT_BASE_URL nicht gesetzt)`);
}
const webServer = process.env.PLAYWRIGHT_BASE_URL
  ? undefined
  : {
      command: 'node tests/ci/a11y.web-server.mjs',
      url: baseURL,
      reuseExistingServer: !process.env.CI,
      timeout: 10_000
    };

export default defineConfig({
  testDir: '.',
  timeout: 30_000,
  expect: {
    timeout: 5_000
  },
  use: {
    baseURL,
    trace: 'on-first-retry',
    launchOptions: A11yQa.launchOptions()
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] }
    }
  ],
  webServer
});
