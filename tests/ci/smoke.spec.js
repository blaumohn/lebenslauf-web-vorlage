import { expect, test } from '@playwright/test';

const langs = (process.env.CONTENT_LANGS || 'de')
  .split(',').map(l => l.trim()).filter(Boolean);

const pages = ['/', '/cv', '/contact'];

for (const lang of langs) {
  test.describe(`Smoke [${lang}]`, () => {
    test.use({ locale: lang });

    test('home', async ({ page }) => {
      await page.goto('/');
      await expect(page.locator('a[href*="/cv"]')).toBeVisible();
    });

    test('cv', async ({ page }) => {
      await page.goto('/cv');
      await expect(page.locator('section.section-experience')).toBeAttached();
    });

    test('contact', async ({ page }) => {
      await page.goto('/contact');
      await expect(page.locator('form')).toBeVisible();
    });

    for (const path of pages) {
      test(`headers ${path}`, async ({ page }) => {
        const response = await page.goto(path);
        expect(response.headers()['x-content-type-options']).toBe('nosniff');
        expect(response.headers()['content-type']).toContain('text/html');
      });
    }
  });
}
