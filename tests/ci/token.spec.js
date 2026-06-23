import { expect, test } from '@playwright/test';

const token = process.env.CI_TOKEN ?? '';
const profile = process.env.CI_PROFILE ?? '';
const lang = process.env.CONTENT_LANGS.split(',')[0];

test.use({ locale: lang });

test('cv token-url', async ({ page }) => {
  await page.goto(`/cv?token=${token}&profile=${profile}`);
  await expect(page.locator('section.section-experience')).toBeAttached();
});
