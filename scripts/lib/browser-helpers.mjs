import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

export const normalize = (value) => value
  .normalize('NFD')
  .replace(/[\u0300-\u036f]/g, '')
  .replace(/\s+/g, ' ')
  .trim();

const rgb = (value) => (value.match(/[\d.]+/g) || []).slice(0, 3).map(Number);

const luminance = (value) => rgb(value).map((channel) => {
  const normalized = channel / 255;

  return normalized <= 0.03928 ? normalized / 12.92 : ((normalized + 0.055) / 1.055) ** 2.4;
}).reduce((total, channel, index) => total + channel * [0.2126, 0.7152, 0.0722][index], 0);

export const contrast = (foreground, background) => {
  const values = [luminance(foreground), luminance(background)].sort((a, b) => b - a);

  return (values[0] + 0.05) / (values[1] + 0.05);
};

export const createEvidenceDirectory = (relativePath) => {
  const evidenceDir = path.resolve('.local/evidence', relativePath);
  fs.mkdirSync(evidenceDir, { recursive: true });

  return evidenceDir;
};

export const withBaseUrl = (url, baseUrl) => {
  const destination = new URL(url, baseUrl);
  const base = new URL(baseUrl);

  return `${base.origin}${destination.pathname}${destination.search}${destination.hash}`;
};

export const canonicalHostHeader = (url, baseUrl = url) => ({
  Host: new URL(baseUrl).host,
});

export const routeCanonicalNavigation = async (page, baseUrl) => {
  const base = new URL(baseUrl);
  const canonicalHost = process.env.PETSHOP_CANONICAL_HOST || base.host;

  await page.route('**/*', async (route) => {
    const request = route.request();
    const url = new URL(request.url());
    const isCanonicalLocalUrl = ['localhost', '127.0.0.1'].includes(url.hostname) && ['', '8888'].includes(url.port);
    const isBaseHostWithCanonicalPort = url.hostname === base.hostname && url.origin !== base.origin;

    if (url.origin === base.origin || isCanonicalLocalUrl || isBaseHostWithCanonicalPort) {
      const headers = request.headers();
      delete headers.host;
      let response;
      try {
        response = await route.fetch({
          maxRedirects: 0,
          // Recover only transport resets for safe reads, never repeat a write.
          maxRetries: ['GET', 'HEAD'].includes(request.method()) ? 2 : 0,
          url: withBaseUrl(url, baseUrl),
          headers: { ...headers, Host: canonicalHost },
        });
      } catch (error) {
        // Playwright's full call log contains cookie headers. Keep diagnostic
        // errors useful without copying session credentials into gate output.
        throw new Error(`${request.method()} ${url.pathname}: ${String(error.message).split('\n')[0]}`);
      }
      // The Docker proxy maps canonical localhost assets/API to wordpress:80.
      // Preserve the browser origin across that test-only alias mapping.
      const proxyHeaders = { ...response.headers() };
      const requestOrigin = request.headers().origin;
      if (requestOrigin && [base.origin, `http://${canonicalHost}`].includes(requestOrigin)) {
        proxyHeaders['access-control-allow-origin'] = requestOrigin;
        proxyHeaders['access-control-allow-credentials'] = 'true';
        proxyHeaders['access-control-allow-methods'] = 'GET, POST, PUT, PATCH, DELETE, OPTIONS';
        proxyHeaders['access-control-allow-headers'] = request.headers()['access-control-request-headers'] || 'Content-Type, Nonce, X-WP-Nonce';
        proxyHeaders['access-control-expose-headers'] = 'Nonce, Cart-Token';
      }
      const location = response.headers().location;
      if (location) {
        const destination = new URL(location, baseUrl);
        const destinationIsCanonicalLocalUrl = ['localhost', '127.0.0.1'].includes(destination.hostname)
          && ['', '8888'].includes(destination.port);
        const targetsBaseHost = destination.hostname === base.hostname;
        if (destinationIsCanonicalLocalUrl || targetsBaseHost) {
          await route.fulfill({
            response,
            headers: { ...proxyHeaders, location: withBaseUrl(destination, baseUrl) },
          });
          return;
        }
      }
      await route.fulfill({ response, headers: proxyHeaders });
      return;
    }

    await route.continue();
  });
};

export const launchBrowser = () => chromium.launch({ headless: true });
