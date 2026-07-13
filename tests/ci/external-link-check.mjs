import { chromium, request } from '@playwright/test';

import { A11yQa } from './a11y.mjs';
import { crawlSite } from './link-collector.mjs';

async function checkExternalUrl(requestContext, url) {
  try {
    const response = await requestContext.get(url, { timeout: 10_000, maxRedirects: 10 });
    return response.status() >= 400 ? `${url}: HTTP ${response.status()}` : null;
  } catch (error) {
    return `${url}: ${error instanceof Error ? error.message : String(error)}`;
  }
}

function reportWarnings(warnings, totalChecked) {
  if (warnings.length === 0) {
    console.log(`[external-link-check] Alle ${totalChecked} externen Links erreichbar.`);
    return;
  }
  console.warn('[external-link-check] Warnung: externe Links möglicherweise nicht erreichbar:');
  for (const warning of warnings) {
    console.warn(`  - ${warning}`);
  }
}

const baseUrl = process.env.PLAYWRIGHT_BASE_URL ?? process.argv[2];
if (!baseUrl) {
  throw new Error('Basis-URL fehlt (PLAYWRIGHT_BASE_URL oder Argument)');
}

const browser = await chromium.launch(A11yQa.launchOptions());
const page = await browser.newPage();
const { externalUrls } = await crawlSite(page, baseUrl);
await browser.close();

const requestContext = await request.newContext();
const warnings = [];
for (const url of externalUrls) {
  const warning = await checkExternalUrl(requestContext, url);
  if (warning) {
    warnings.push(warning);
  }
}
await requestContext.dispose();

reportWarnings(warnings, externalUrls.size);
