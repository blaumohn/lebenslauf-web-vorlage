import { expect, test } from '@playwright/test';

import { crawlSite, summarizeBrokenPages } from './link-collector.mjs';

test('alle internen Links, Ressourcen und Anfragen sind erreichbar', async ({ page, baseURL }) => {
  const crawlResult = await crawlSite(page, baseURL);
  const brokenPages = summarizeBrokenPages(crawlResult);

  expect(brokenPages, brokenPages.join('\n')).toEqual([]);
});
