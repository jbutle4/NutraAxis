const { fetchText, fetchJson, sleep } = require('./http');
const { parseFeed, asArray, text, parser } = require('./feeds');
const { extractArticle, extractLinks, stripTags, parseDate } = require('./html');

function config(source) {
  try {
    const decoded = JSON.parse(source.ConfigJson || '{}');
    return decoded && typeof decoded === 'object' ? decoded : {};
  } catch {
    return {};
  }
}

function isoDaysAgo(days) {
  return new Date(Date.now() - days * 86400000).toISOString().slice(0, 10);
}

async function fetchFeed(url, ctx) {
  const response = await fetchText(url, {
    userAgent: ctx.userAgent,
    accept: 'application/rss+xml, application/atom+xml, application/xml, text/xml;q=0.9, */*;q=0.5',
  });
  if (!response.ok) {
    throw new Error(`Feed fetch failed (HTTP ${response.status || '—'}${response.error ? `: ${response.error}` : ''})`);
  }
  return parseFeed(response.text).slice(0, ctx.maxItems);
}

async function rss(source, ctx) {
  if (!source.Url) throw new Error('Feed URL is required.');
  return { items: await fetchFeed(source.Url, ctx) };
}

async function googleNews(source, ctx) {
  const query = String(source.Query || '').trim();
  if (!query) throw new Error('Search query is required.');
  const url = `https://news.google.com/rss/search?q=${encodeURIComponent(`${query} when:7d`)}&hl=en-US&gl=US&ceid=US:en`;
  const items = await fetchFeed(url, ctx);
  return {
    items: items.map((item) => ({
      ...item,
      title: item.sourceName ? item.title.replace(new RegExp(`\\s+-\\s+${item.sourceName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`), '') : item.title,
      metadata: item.sourceName ? { publisher: item.sourceName } : undefined,
    })),
  };
}

async function pubmed(source, ctx) {
  const query = String(source.Query || '').trim();
  if (!query) throw new Error('PubMed search term is required.');
  const base = 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils';
  const common = `tool=nutraaxis-harvester&email=${encodeURIComponent(ctx.contactEmail)}`;
  const search = await fetchJson(
    `${base}/esearch.fcgi?db=pubmed&retmode=json&sort=pub+date&datetype=edat&reldate=${ctx.lookbackDays}&retmax=${ctx.maxItems}&term=${encodeURIComponent(query)}&${common}`,
    { userAgent: ctx.userAgent }
  );
  if (!search.ok) throw new Error(`PubMed search failed (HTTP ${search.status})`);
  const ids = search.json?.esearchresult?.idlist || [];
  if (ids.length === 0) return { items: [] };

  await sleep(400);
  const fetched = await fetchText(`${base}/efetch.fcgi?db=pubmed&retmode=xml&id=${ids.join(',')}&${common}`, { userAgent: ctx.userAgent });
  if (!fetched.ok) throw new Error(`PubMed fetch failed (HTTP ${fetched.status})`);
  const doc = parser.parse(fetched.text);

  const items = asArray(doc.PubmedArticleSet?.PubmedArticle).map((entry) => {
    const citation = entry.MedlineCitation || {};
    const article = citation.Article || {};
    const pmid = text(citation.PMID);
    const abstract = asArray(article.Abstract?.AbstractText)
      .map((part) => {
        const label = typeof part === 'object' ? part['@_Label'] : null;
        const body = typeof part === 'object' ? stripTags(text(part) || JSON.stringify(part['#text'] ?? '')) : stripTags(String(part));
        return label ? `${label}: ${body}` : body;
      })
      .join('\n');
    const pubDate = article.Journal?.JournalIssue?.PubDate || {};
    const dateText = [text(pubDate.Year), text(pubDate.Month) || 'Jan', text(pubDate.Day) || '1'].join(' ');
    const authors = asArray(article.AuthorList?.Author)
      .map((a) => [text(a.ForeName), text(a.LastName)].filter(Boolean).join(' '))
      .filter(Boolean);
    const doi = asArray(entry.PubmedData?.ArticleIdList?.ArticleId).find((id) => id['@_IdType'] === 'doi');
    return {
      url: `https://pubmed.ncbi.nlm.nih.gov/${pmid}/`,
      title: stripTags(text(article.ArticleTitle) || (typeof article.ArticleTitle === 'object' ? JSON.stringify(article.ArticleTitle) : '')),
      summary: abstract.slice(0, 4000),
      body: abstract,
      author: authors.slice(0, 6).join(', ') || null,
      publishedAt: parseDate(dateText),
      metadata: {
        pmid,
        journal: text(article.Journal?.Title),
        doi: doi ? text(doi) : null,
        publication_types: asArray(article.PublicationTypeList?.PublicationType).map(text),
      },
    };
  }).filter((item) => item.title);

  return { items };
}

async function clinicalTrials(source, ctx) {
  const query = String(source.Query || '').trim();
  if (!query) throw new Error('ClinicalTrials.gov search term is required.');
  const since = isoDaysAgo(ctx.lookbackDays);
  const url = 'https://clinicaltrials.gov/api/v2/studies?format=json'
    + `&query.term=${encodeURIComponent(query)}`
    + `&filter.advanced=${encodeURIComponent(`AREA[LastUpdatePostDate]RANGE[${since},MAX]`)}`
    + `&sort=${encodeURIComponent('LastUpdatePostDate:desc')}&pageSize=${ctx.maxItems}`;
  const response = await fetchJson(url, { userAgent: ctx.userAgent });
  if (!response.ok) throw new Error(`ClinicalTrials.gov request failed (HTTP ${response.status})`);

  const items = asArray(response.json?.studies).map((study) => {
    const p = study.protocolSection || {};
    const id = p.identificationModule?.nctId;
    const summary = p.descriptionModule?.briefSummary || '';
    return {
      url: `https://clinicaltrials.gov/study/${id}`,
      title: p.identificationModule?.briefTitle || p.identificationModule?.officialTitle || id,
      summary: summary.slice(0, 4000),
      body: [summary, p.descriptionModule?.detailedDescription || ''].join('\n\n').trim(),
      author: p.sponsorCollaboratorsModule?.leadSponsor?.name || null,
      publishedAt: parseDate(p.statusModule?.lastUpdatePostDateStruct?.date || p.statusModule?.studyFirstPostDateStruct?.date),
      metadata: {
        nct_id: id,
        status: p.statusModule?.overallStatus || null,
        phase: asArray(p.designModule?.phases).join(', ') || null,
        conditions: asArray(p.conditionsModule?.conditions),
        interventions: asArray(p.armsInterventionsModule?.interventions).map((i) => i.name).filter(Boolean),
      },
    };
  }).filter((item) => item.url && item.title);

  return { items };
}

function robotsAllows(robotsText, path, agentToken) {
  let applies = false;
  let matchedSpecific = false;
  const rules = [];
  for (const rawLine of String(robotsText || '').split(/\r?\n/)) {
    const line = rawLine.replace(/#.*/, '').trim();
    const [field, ...rest] = line.split(':');
    const value = rest.join(':').trim();
    if (!field) continue;
    const key = field.trim().toLowerCase();
    if (key === 'user-agent') {
      const ua = value.toLowerCase();
      if (ua === agentToken) {
        if (!matchedSpecific) rules.length = 0;
        matchedSpecific = true;
        applies = true;
      } else {
        applies = ua === '*' && !matchedSpecific;
      }
    } else if (applies && (key === 'disallow' || key === 'allow') && value !== '') {
      rules.push({ allow: key === 'allow', path: value });
    }
  }
  let best = null;
  for (const rule of rules) {
    const anchored = rule.path.endsWith('$');
    const body = (anchored ? rule.path.slice(0, -1) : rule.path)
      .split('*')
      .map((part) => part.replace(/[.+?^${}()|[\]\\]/g, '\\$&'))
      .join('.*');
    const matches = new RegExp(`^${body}${anchored ? '$' : ''}`).test(path);
    if (matches && (!best || rule.path.length > best.len)) {
      best = { allow: rule.allow, len: rule.path.length };
    }
  }
  return best ? best.allow : true;
}

async function crawl(source, ctx) {
  if (!source.Url) throw new Error('Listing page URL is required.');
  const cfg = config(source);
  const listing = new URL(source.Url);
  const agentToken = 'nutraaxisresearchbot';

  const robots = await fetchText(`${listing.origin}/robots.txt`, { userAgent: ctx.userAgent, timeoutMs: 10000 });
  const robotsText = robots.ok ? robots.text : '';
  if (!robotsAllows(robotsText, listing.pathname, agentToken)) {
    throw new Error('robots.txt disallows the listing page for our crawler.');
  }

  const page = await fetchText(source.Url, { userAgent: ctx.userAgent, accept: 'text/html' });
  if (!page.ok) throw new Error(`Listing page fetch failed (HTTP ${page.status || '—'}${page.error ? `: ${page.error}` : ''})`);

  let pattern = null;
  if (cfg.link_pattern) {
    try {
      pattern = new RegExp(cfg.link_pattern, 'i');
    } catch {
      throw new Error('Link pattern is not a valid regular expression.');
    }
  }
  const listingPath = listing.pathname.replace(/\/$/, '');
  const candidates = extractLinks(page.text, page.url).filter((link) => {
    const url = new URL(link);
    if (url.hostname.replace(/^www\./, '') !== listing.hostname.replace(/^www\./, '')) return false;
    if (/\.(pdf|jpe?g|png|gif|svg|zip|mp4|mp3)$/i.test(url.pathname)) return false;
    if (pattern) return pattern.test(link);
    const path = url.pathname.replace(/\/$/, '');
    return path.startsWith(`${listingPath}/`) && path.split('/').length > listingPath.split('/').length;
  });

  const maxPages = Math.min(Number(cfg.max_pages) || 10, ctx.maxItems);
  const fresh = [];
  for (const link of candidates) {
    if (fresh.length >= maxPages) break;
    if (!(await ctx.isKnownUrl(link))) fresh.push(link);
  }

  const items = [];
  for (const link of fresh) {
    if (!robotsAllows(robotsText, new URL(link).pathname, agentToken)) continue;
    await sleep(ctx.crawlDelayMs);
    const article = await fetchText(link, { userAgent: ctx.userAgent, accept: 'text/html' });
    if (!article.ok || !/html/i.test(article.contentType)) continue;
    const extracted = extractArticle(article.text, article.url);
    if (!extracted.title) continue;
    items.push({
      url: article.url,
      title: extracted.title,
      summary: (extracted.description || extracted.text.slice(0, 500)).slice(0, 4000),
      body: extracted.text,
      author: extracted.author,
      publishedAt: extracted.publishedAt,
    });
  }

  return { items, candidates: candidates.length };
}

const ADAPTERS = {
  rss,
  reddit: rss,
  youtube: rss,
  google_news: googleNews,
  pubmed,
  clinicaltrials: clinicalTrials,
  crawl,
};

async function harvest(source, ctx) {
  const adapter = ADAPTERS[source.SourceType];
  if (!adapter) throw new Error(`No harvester for source type ${source.SourceType}.`);
  return adapter(source, ctx);
}

module.exports = { harvest, robotsAllows };
