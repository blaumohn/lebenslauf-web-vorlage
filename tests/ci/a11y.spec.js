import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

import { A11yQa } from './a11y.mjs';

const accessibilityTestName =
  'has no automatically detectable accessibility violations';

for (const pageCase of A11yQa.pages()) {
  test.describe(pageCase.name, () => {
    test(accessibilityTestName, async ({ page }) => {
      await page.goto(pageCase.path);
      await expect(page.locator('body')).toBeVisible();

      const results = await new AxeBuilder({ page }).analyze();

      expect(results.violations).toEqual([]);
    });
  });
}

test('English CV declares the English document language', async ({ page }) => {
  await page.goto('/cv?lang=en');

  await expect(page.locator('html')).toHaveAttribute('lang', 'en');
});
