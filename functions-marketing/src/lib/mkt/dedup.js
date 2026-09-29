const crypto = require('crypto');

const TRACKING_PARAMS = /^(utm_[a-z]+|fbclid|gclid|mc_cid|mc_eid|ref|ref_src|igshid|cmpid|ncid)$/i;

function canonicalUrl(raw) {
  try {
    const url = new URL(String(raw).trim());
    url.hash = '';
    url.hostname = url.hostname.toLowerCase().replace(/^www\./, '');
    for (const key of [...url.searchParams.keys()]) {
      if (TRACKING_PARAMS.test(key)) {
        url.searchParams.delete(key);
      }
    }
    url.searchParams.sort();
    let text = url.toString();
    if (url.pathname !== '/' && text.endsWith('/') && !url.search) {
      text = text.slice(0, -1);
    }
    return text;
  } catch {
    return String(raw).trim();
  }
}

function domainOf(raw) {
  try {
    return new URL(raw).hostname.toLowerCase().replace(/^www\./, '');
  } catch {
    return null;
  }
}

function sha256(text) {
  return crypto.createHash('sha256').update(String(text), 'utf8').digest();
}

function normalizeForHash(text) {
  return String(text || '')
    .toLowerCase()
    .replace(/<[^>]+>/g, ' ')
    .replace(/[^a-z0-9\s]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function contentHash(title, body) {
  const normalized = normalizeForHash(`${title} ${String(body || '').slice(0, 2000)}`);
  return normalized.length < 20 ? null : sha256(normalized);
}

function fnv1a64(text) {
  let hash = 0xcbf29ce484222325n;
  for (let i = 0; i < text.length; i++) {
    hash ^= BigInt(text.charCodeAt(i));
    hash = (hash * 0x100000001b3n) & 0xffffffffffffffffn;
  }
  return hash;
}

/**
 * 64-bit simhash over word 3-shingles; returned as a signed BIGINT-compatible string.
 */
function simhash(text) {
  const words = normalizeForHash(text).split(' ').filter((w) => w.length > 2);
  if (words.length < 8) {
    return null;
  }
  const weights = new Array(64).fill(0);
  for (let i = 0; i + 2 < words.length; i++) {
    const hash = fnv1a64(`${words[i]} ${words[i + 1]} ${words[i + 2]}`);
    for (let bit = 0; bit < 64; bit++) {
      weights[bit] += (hash >> BigInt(bit)) & 1n ? 1 : -1;
    }
  }
  let value = 0n;
  for (let bit = 0; bit < 64; bit++) {
    if (weights[bit] > 0) {
      value |= 1n << BigInt(bit);
    }
  }
  return BigInt.asIntN(64, value).toString();
}

function hamming(a, b) {
  let x = BigInt.asUintN(64, BigInt(a)) ^ BigInt.asUintN(64, BigInt(b));
  let count = 0;
  while (x) {
    count += Number(x & 1n);
    x >>= 1n;
  }
  return count;
}

module.exports = {
  canonicalUrl,
  domainOf,
  sha256,
  contentHash,
  simhash,
  hamming,
};
