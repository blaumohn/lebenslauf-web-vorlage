const LINK_ATTRIBUTES = ['href', 'src', 'action', 'formaction', 'poster', 'data', 'cite'];

const IGNORED_PROTOCOLS = new Set(['data:', 'blob:', 'javascript:', 'mailto:', 'tel:']);

const DEFAULT_MAX_PAGES = 200;

export async function crawlSite(page, baseUrl, { maxPages = DEFAULT_MAX_PAGES } = {}) {
  const siteOrigin = new URL(baseUrl).origin;
  const visited = new Map();
  const externalUrls = new Set();
  const queue = ['/'];
  let truncated = false;

  while (queue.length > 0) {
    if (visited.size >= maxPages) {
      truncated = true;
      break;
    }

    const path = queue.shift();
    if (visited.has(path)) {
      continue;
    }

    const { navigationStatus, failures, discoveredUrls } = await visitPage(page, new URL(path, baseUrl).href);

    for (const url of discoveredUrls) {
      if (!belongsToSite(url, siteOrigin)) {
        externalUrls.add(url);
        continue;
      }
      const internalPath = toCrawlPath(url);
      if (!visited.has(internalPath)) {
        queue.push(internalPath);
      }
    }

    visited.set(path, { navigationStatus, failures });
  }

  return { visited, externalUrls, truncated };
}

export function summarizeBrokenPages({ visited, truncated }) {
  const brokenPages = [];

  for (const [path, pageResult] of visited) {
    brokenPages.push(...describePageFailures(path, pageResult));
  }

  const truncationMessage = describeTruncation(visited, truncated);
  if (truncationMessage) {
    brokenPages.push(truncationMessage);
  }

  return brokenPages;
}

function belongsToSite(url, siteOrigin) {
  return new URL(url).origin === siteOrigin;
}

// Nur Pfad + Query weiterverfolgen: der Fragment-Anteil (#…) adressiert keine
// eigene Seite, und absolute URLs würden den visited-Abgleich unterlaufen.
function toCrawlPath(url) {
  const { pathname, search } = new URL(url);
  return pathname + search;
}

async function visitPage(page, url) {
  const failures = [];
  const onRequestFailed = request => {
    failures.push({ url: request.url(), reason: request.failure()?.errorText ?? 'Netzwerkfehler' });
  };
  const onResponse = response => {
    if (response.url() === url) {
      return;
    }
    if (response.status() >= 400) {
      failures.push({ url: response.url(), reason: `HTTP ${response.status()}` });
    }
  };

  page.on('requestfailed', onRequestFailed);
  page.on('response', onResponse);

  try {
    const response = await page.goto(url, { waitUntil: 'load' });
    if (!response) {
      failures.push({ url, reason: 'keine Navigations-Response' });
    }
    // networkidle gilt bei Playwright als unzuverlässig für harte Wartebedingungen
    // (Polling/Analytics verhindern es u. U. dauerhaft) — hier nur als kurze,
    // folgenlose Kulanzfrist für spät nachgeladene Ressourcen.
    await page.waitForLoadState('networkidle', { timeout: 3000 }).catch(() => {});

    const { urls, invalid } = await collectPageUrls(page);
    for (const value of invalid) {
      failures.push({ url: value, reason: 'Ungültige URL im Content' });
    }

    return { navigationStatus: response?.status() ?? null, failures, discoveredUrls: urls };
  } finally {
    page.off('requestfailed', onRequestFailed);
    page.off('response', onResponse);
  }
}

async function collectPageUrls(page) {
  const urls = new Set();
  const invalid = new Set();

  for (const frame of page.frames()) {
    let result;
    try {
      result = await frame.evaluate(collectUrlsInDocument, LINK_ATTRIBUTES);
    } catch (error) {
      if (frame.isDetached()) {
        // Frame wurde während des Crawls entfernt (z. B. durch Skripte der Seite) —
        // dann gibt es nichts mehr einzusammeln. Alle anderen Fehler sind Bugs
        // in der Sammellogik und schlagen durch.
        continue;
      }
      throw error;
    }
    for (const url of result.urls) {
      if (isCheckableUrl(url)) {
        urls.add(url);
      }
    }
    for (const value of result.invalid) {
      invalid.add(value);
    }
  }

  return { urls: [...urls], invalid: [...invalid] };
}

// Wird von Playwright serialisiert und im Browser-Kontext der Seite ausgeführt:
// darf nichts aus diesem Modul referenzieren, alle Helfer müssen deshalb im
// Funktionskörper stehen (gehoisted, damit der Hauptfluss oben lesbar bleibt).
function collectUrlsInDocument(attributes) {
  const found = new Set();
  const invalid = new Set();

  collectAttributeUrls();
  collectMetaRefreshUrls();
  return { urls: [...found], invalid: [...invalid] };

  function collectAttributeUrls() {
    for (const element of document.querySelectorAll('*')) {
      for (const attribute of attributes) {
        add(element.getAttribute(attribute));
      }

      const srcset = element.getAttribute('srcset');
      if (srcset) {
        for (const candidate of parseSrcsetCandidates(srcset)) {
          add(candidate);
        }
      }
    }
  }

  function collectMetaRefreshUrls() {
    for (const meta of document.querySelectorAll('meta[http-equiv="refresh" i][content]')) {
      add(readMetaRefreshUrl(meta));
    }
  }

  function add(value) {
    if (!value) {
      return;
    }
    try {
      found.add(new URL(value, document.baseURI).href);
    } catch {
      invalid.add(value);
    }
  }

  function parseSrcsetCandidates(srcset) {
    return srcset.split(',').map(candidate => candidate.trim().split(/\s+/, 1)[0]);
  }

  function readMetaRefreshUrl(meta) {
    const match = meta.content.match(/(?:^|;)\s*url\s*=\s*(['"]?)(.*?)\1\s*$/i);
    return match?.[2] ?? null;
  }
}

function isCheckableUrl(urlString) {
  try {
    return !IGNORED_PROTOCOLS.has(new URL(urlString).protocol);
  } catch {
    return false;
  }
}

function isBrokenStatus(navigationStatus) {
  return navigationStatus !== null && navigationStatus >= 400;
}

function describePageFailures(path, { navigationStatus, failures }) {
  const messages = [];

  if (isBrokenStatus(navigationStatus)) {
    messages.push(`${path}: HTTP ${navigationStatus}`);
  }
  for (const failure of failures) {
    messages.push(`${path}: ${failure.url} — ${failure.reason}`);
  }

  return messages;
}

function describeTruncation(visited, truncated) {
  if (!truncated) {
    return null;
  }
  return `Crawl abgebrochen: mehr als ${visited.size} Seiten gefunden — möglicher Endlos-Link (z. B. relative Auflösung einer eigentlich absoluten URL)`;
}
