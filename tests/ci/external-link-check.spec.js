// Rolle: Selbsttest der externen Link-Prüfung (external-link-check.mjs). Läuft
// mit `npm run qa:tooling` — prüft die Warn-Klassifizierung gegen einen
// Fake-RequestContext, ohne Netz, ohne Server, kein Test von Anwendungscode.
import { expect, test } from '@playwright/test';

import { checkExternalUrl, collectExternalLinkWarnings } from './external-link-check.mjs';

function contextWithStatus(status) {
  return { get: async () => ({ status: () => status }) };
}

function contextThatThrows(message) {
  return { get: async () => { throw new Error(message); } };
}

test.describe('Selbsttest: externe Link-Warnungen (gemockt, kein Server nötig)', () => {
  test('meldet HTTP-Fehlerstatus als Warnung', async () => {
    const warning = await checkExternalUrl(contextWithStatus(404), 'https://example.test/tot');

    expect(warning).toBe('https://example.test/tot: HTTP 404');
  });

  test('meldet einen Netzwerkfehler als Warnung, statt zu werfen', async () => {
    const warning = await checkExternalUrl(contextThatThrows('getaddrinfo ENOTFOUND'), 'https://example.test/');

    expect(warning).toBe('https://example.test/: getaddrinfo ENOTFOUND');
  });

  test('erreichbare Links erzeugen keine Warnung', async () => {
    const warning = await checkExternalUrl(contextWithStatus(200), 'https://example.test/');

    expect(warning).toBeNull();
  });

  test('sammelt nur die Warnungen der kaputten Links ein', async () => {
    const statusByUrl = new Map([
      ['https://example.test/ok', 200],
      ['https://example.test/tot', 410],
      ['https://example.test/umgezogen', 301]
    ]);
    const requestContext = { get: async url => ({ status: () => statusByUrl.get(url) }) };

    const warnings = await collectExternalLinkWarnings(requestContext, statusByUrl.keys());

    expect(warnings).toEqual(['https://example.test/tot: HTTP 410']);
  });
});
