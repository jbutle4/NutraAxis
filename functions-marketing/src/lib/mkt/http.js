const DEFAULT_TIMEOUT_MS = 20000;
const MAX_BYTES = 3 * 1024 * 1024;

async function fetchText(url, { userAgent, timeoutMs = DEFAULT_TIMEOUT_MS, accept = '*/*', headers = {} } = {}) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);
  try {
    const response = await fetch(url, {
      redirect: 'follow',
      signal: controller.signal,
      headers: {
        'User-Agent': userAgent || 'NutraAxisResearchBot/1.0',
        Accept: accept,
        ...headers,
      },
    });
    const buffer = Buffer.from(await response.arrayBuffer());
    return {
      ok: response.ok,
      status: response.status,
      url: response.url || url,
      contentType: response.headers.get('content-type') || '',
      text: buffer.subarray(0, MAX_BYTES).toString('utf8'),
    };
  } catch (error) {
    return {
      ok: false,
      status: 0,
      url,
      contentType: '',
      text: '',
      error: error.name === 'AbortError' ? `Timed out after ${timeoutMs} ms` : error.message,
    };
  } finally {
    clearTimeout(timer);
  }
}

async function fetchJson(url, options = {}) {
  const result = await fetchText(url, { ...options, accept: 'application/json' });
  if (!result.ok) {
    return { ...result, json: null };
  }
  try {
    return { ...result, json: JSON.parse(result.text) };
  } catch {
    return { ...result, ok: false, json: null, error: 'Invalid JSON response' };
  }
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

module.exports = { fetchText, fetchJson, sleep };
