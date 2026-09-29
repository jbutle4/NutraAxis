const { XMLParser } = require('fast-xml-parser');
const { stripTags, decodeEntities, parseDate } = require('./html');

const parser = new XMLParser({
  ignoreAttributes: false,
  attributeNamePrefix: '@_',
  textNodeName: '#text',
  processEntities: false,
  htmlEntities: false,
});

function asArray(value) {
  if (value === undefined || value === null) return [];
  return Array.isArray(value) ? value : [value];
}

function text(value) {
  if (value === undefined || value === null) return '';
  if (typeof value === 'string' || typeof value === 'number') return decodeEntities(String(value)).trim();
  if (typeof value === 'object' && '#text' in value) return decodeEntities(String(value['#text'])).trim();
  return '';
}

function atomLink(links) {
  const list = asArray(links);
  const alternate = list.find((l) => typeof l === 'object' && (!l['@_rel'] || l['@_rel'] === 'alternate'));
  const chosen = alternate || list[0];
  if (!chosen) return '';
  return typeof chosen === 'string' ? chosen : String(chosen['@_href'] || '');
}

function parseFeed(xml) {
  const doc = parser.parse(xml);

  if (doc.rss?.channel || doc['rdf:RDF']) {
    const channel = doc.rss?.channel || doc['rdf:RDF'];
    const items = asArray(channel.item || doc['rdf:RDF']?.item);
    return items.map((item) => {
      const body = text(item['content:encoded']) || text(item.description);
      return {
        url: text(item.link) || (item.guid && String(item.guid['@_isPermaLink']) !== 'false' ? text(item.guid) : ''),
        title: stripTags(text(item.title)),
        summary: stripTags(text(item.description)).slice(0, 4000),
        body: stripTags(body),
        author: text(item['dc:creator']) || text(item.author) || null,
        publishedAt: parseDate(text(item.pubDate) || text(item['dc:date'])),
        sourceName: text(item.source) || null,
      };
    }).filter((item) => item.url && item.title);
  }

  if (doc.feed) {
    return asArray(doc.feed.entry).map((entry) => {
      const summary = text(entry.summary) || text(entry['media:group']?.['media:description']);
      const content = text(entry.content) || summary;
      return {
        url: atomLink(entry.link),
        title: stripTags(text(entry.title)),
        summary: stripTags(summary).slice(0, 4000),
        body: stripTags(content),
        author: text(asArray(entry.author)[0]?.name) || null,
        publishedAt: parseDate(text(entry.published) || text(entry.updated)),
        sourceName: null,
      };
    }).filter((item) => item.url && item.title);
  }

  throw new Error('Not an RSS, Atom, or RDF feed.');
}

module.exports = { parseFeed, asArray, text, parser };
