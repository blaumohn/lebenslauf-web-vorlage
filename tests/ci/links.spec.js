// Rolle: QA-Gate für interne Links. Läuft nur mit `qa:links`, braucht einen
// laufenden Dev-Server (lokal und am Deploy-Artefakt, siehe pipeline_lib.sh)
// und bricht die Pipeline bei Befunden.
import { expect, test } from '@playwright/test';

import { crawlSite, summarizeBrokenPages } from './link-collector.mjs';

test.describe('QA-Gate: interne Links (echte Site, braucht Dev-Server)', () => {
  test('alle internen Links, Ressourcen und Anfragen sind erreichbar', async ({ page, baseURL }) => {
    const crawlResult = await crawlSite(page, baseURL);
    const brokenPages = summarizeBrokenPages(crawlResult);

    expect(brokenPages, brokenPages.join('\n')).toEqual([]);
  });
});
