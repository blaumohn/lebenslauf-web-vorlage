import { expect, test } from '@playwright/test';

import { crawlSite, summarizeBrokenPages } from './link-collector.mjs';

const ORIGIN = 'https://example.test';

async function mockSite(page, handler) {
  await page.route(`${ORIGIN}/**`, async route => {
    const url = new URL(route.request().url());
    const result = handler(url.pathname);
    if (!result) {
      await route.fulfill({ status: 404, body: 'not found' });
      return;
    }
    await route.fulfill({ status: 200, contentType: 'text/html', body: result });
  });
}

test('erkennt einen intern aufgelösten Link ohne Protokoll als kaputt', async ({ page }) => {
  await mockSite(page, path => {
    if (path === '/') {
      return '<a href="example.com/repo">repo</a>';
    }
    return null;
  });

  const { visited } = await crawlSite(page, ORIGIN);

  expect(visited.get('/example.com/repo')?.navigationStatus).toBe(404);
});

test('bricht bei unbegrenzt wachsenden Pfaden nach maxPages ab, statt zu hängen', async ({ page }) => {
  await mockSite(page, path => `<a href="loop/${path.replace(/^\//, '')}">next</a>`);

  const { truncated, visited } = await crawlSite(page, ORIGIN, { maxPages: 10 });

  expect(truncated).toBe(true);
  expect(visited.size).toBe(10);
});

test('meldet eine syntaktisch ungültige URL im Content statt sie stillschweigend zu verwerfen', async ({ page }) => {
  await mockSite(page, path => {
    if (path === '/') {
      return '<a href="http://[bad">kaputt</a>';
    }
    return null;
  });

  const { visited } = await crawlSite(page, ORIGIN);

  expect(visited.get('/')?.failures).toEqual([
    { url: 'http://[bad', reason: 'Ungültige URL im Content' }
  ]);
});

test('summarizeBrokenPages meldet einen kaputten Link genau wie links.spec.js ihn auswerten würde', async ({ page }) => {
  await mockSite(page, path => {
    if (path === '/') {
      return '<a href="example.com/repo">repo</a>';
    }
    return null;
  });

  const crawlResult = await crawlSite(page, ORIGIN);
  const brokenPages = summarizeBrokenPages(crawlResult);

  expect(brokenPages).toEqual(['/example.com/repo: HTTP 404']);
});

test('sammelt externe Links separat, ohne sie zu besuchen', async ({ page }) => {
  await mockSite(page, path => {
    if (path === '/') {
      return '<a href="https://github.com/example/repo">repo</a><a href="/kontakt">kontakt</a>';
    }
    if (path === '/kontakt') {
      return 'kontakt-seite';
    }
    return null;
  });

  const { visited, externalUrls } = await crawlSite(page, ORIGIN);

  expect([...visited.keys()].sort()).toEqual(['/', '/kontakt']);
  expect(externalUrls.has('https://github.com/example/repo')).toBe(true);
  expect(visited.get('/kontakt')?.navigationStatus).toBe(200);
});
