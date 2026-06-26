import { expect, test } from '@playwright/test';

const token = process.env.CI_TOKEN;
const profile = process.env.CI_PROFILE;
const lang = process.env.CONTENT_LANGS.split(',')[0];

if (!token) throw new Error('CI_TOKEN muss gesetzt sein');
if (!profile) throw new Error('CI_PROFILE muss gesetzt sein');

test.use({ locale: lang });

test('cv token-url zeigt private Kontaktdaten', async ({ page }) => {
  await page.goto(`/cv?token=${token}&profile=${profile}`);
  await expect(page.locator('section.section-experience')).toBeAttached();
  await expect(page.locator('.contact')).not.toContainText('Kontaktformular');
});
