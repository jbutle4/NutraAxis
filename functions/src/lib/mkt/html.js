const ENTITIES = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: ' ', '#39': "'", rsquo: '\u2019', lsquo: '\u2018', ldquo: '\u201c', rdquo: '\u201d', mdash: '\u2014', ndash: '\u2013', hellip: '\u2026' };

function decodeEntities(text) {
  return String(text || '').replace(/&(#x[0-9a-f]+|#\d+|[a-z0-9]+);/gi, (match, code) => {
    const lower = code.toLowerCase();
    if (lower.startsWith('#x')) return String.fromCodePoint(parseInt(lower.slice(2), 16));
    if (lower.startsWith('#')) return String.fromCodePoint(parseInt(lower.slice(1), 10));
    return ENTITIES[lower] ?? match;
  });
}

function stripTags(html) {
  return decodeEntities(String(html || '')
    .replace(/<(script|style|noscript|svg|iframe|form|nav|header|footer|aside)[\s\S]*?<\/\1>/gi, ' ')
    .replace(/<br\s*\/?>/gi, '\n')
    .replace(/<\/(p|div|li|h[1-6]|tr|section|article)>/gi, '\n')
    .replace(/<[^>]+>/g, ' '))
    .replace(/[ \t\f\v]+/g, ' ')
    .replace(/\s*\n\s*/g, '\n')
    .replace(/\n{3,}/g, '\n\n')
    .trim();
}

function metaContent(html, names) {
  for (const name of names) {
    const escaped = name.replace(/[.*+?^${}()|[\]\\:]/g, '\\$&');
    const patterns = [
      new RegExp(`<meta[^>]+(?:name|property|itemprop)=["']${escaped}["'][^>]*content=["']([^"']*)["']`, 'i'),
      new RegExp(`<meta[^>]+content=["']([^"']*)["'][^>]*(?:name|property|itemprop)=["']${escaped}["']`, 'i'),
    ];
    for (const pattern of patterns) {
      const match = html.match(pattern);
      if (match && match[1].trim() !== '') {
        return decodeEntities(match[1].trim());
      }
    }
  }
  return null;
}

function parseDate(value) {
  if (!value) return null;
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? null : date;
}

function mainHtml(html) {
  const article = html.match(/<article[\s\S]*?<\/article>/i);
  if (article) return article[0];
  const main = html.match(/<main[\s\S]*?<\/main>/i);
  if (main) return main[0];
  const body = html.match(/<body[\s\S]*?<\/body>/i);
  return body ? body[0] : html;
}

function extractArticle(html, pageUrl) {
  const titleTag = html.match(/<title[^>]*>([\s\S]*?)<\/title>/i);
  const title = metaContent(html, ['og:title', 'twitter:title'])
    || (titleTag ? decodeEntities(titleTag[1].replace(/\s+/g, ' ').trim()) : null);
  const description = metaContent(html, ['description', 'og:description', 'twitter:description']);
  const published = parseDate(metaContent(html, [
    'article:published_time', 'datePublished', 'pubdate', 'publish-date', 'date', 'dc.date', 'citation_publication_date',
  ]) || (html.match(/<time[^>]+datetime=["']([^"']+)["']/i) || [])[1]);
  const author = metaContent(html, ['author', 'article:author', 'citation_author']);
  const text = stripTags(mainHtml(html)).slice(0, 60000);

  return { url: pageUrl, title, description, publishedAt: published, author, text };
}

function extractLinks(html, baseUrl) {
  const links = new Set();
  const pattern = /<a\s[^>]*href=["']([^"'#]+)["']/gi;
  let match;
  while ((match = pattern.exec(html)) !== null) {
    try {
      const resolved = new URL(decodeEntities(match[1]), baseUrl);
      if (resolved.protocol === 'http:' || resolved.protocol === 'https:') {
        resolved.hash = '';
        links.add(resolved.toString());
      }
    } catch {
      // skip malformed href
    }
  }
  return [...links];
}

module.exports = {
  decodeEntities,
  stripTags,
  extractArticle,
  extractLinks,
  parseDate,
};
