const { sql, connectPool, getProductionDatabase } = require('../db-config');
const { loadSettings, settingNumber } = require('../mkt/settings');
const { runPrompt, extractJson } = require('../mkt/ai');
const { canonicalUrl, sha256, contentHash } = require('../mkt/dedup');
const { fetchText, fetchJson, sleep } = require('../mkt/http');
const { stripTags } = require('../mkt/html');
const { asArray } = require('../mkt/feeds');
const { requestIdentity, pubmedCommon, mapPubmedXml, mapTrialStudy, EUTILS_BASE } = require('../mkt/adapters');
const store = require('../mkt/store');
const { loadTaxonomy } = require('../mkt/taxonomy');
const {
  applyScore, scoreSystemVars, scoreUserVars, relevanceThreshold, interestWeights, PROMPT_KEY: SCORE_PROMPT_KEY,
} = require('./research-score');

const IDENTIFIER_ERROR = 'Enter a PubMed ID, DOI, or ClinicalTrials.gov number (NCT…).';
// NCBI allows 3 requests/second without an API key.
const NCBI_GAP_MS = 360;
const MAX_CANDIDATES = 5;
const MAX_CONSECUTIVE_ERRORS = 5;
// Stop taking new references well inside the ~230 s HTTP-trigger limit; the rest wait for the next run.
const MATCH_TIME_BUDGET_MS = 150 * 1000;
const CORPORATE_AUTHOR = /\b(collaboration|collaborative|trial|group|investigators|consortium|committee|study|studies)\b/i;

class LookupError extends Error {}

function userIdFrom(params) {
  return Number(params.triggered_by_user_id || params.user_id || 0) || null;
}

function roundCost(value) {
  return Math.round(value * 10000) / 10000;
}

/** Calls NCBI E-utilities at most once per NCBI_GAP_MS. */
function ncbiClient(ctx) {
  let lastAt = 0;
  return async (path, { json = true } = {}) => {
    const wait = lastAt + NCBI_GAP_MS - Date.now();
    if (wait > 0) await sleep(wait);
    const url = `${EUTILS_BASE}/${path}&${pubmedCommon(ctx)}`;
    const response = json
      ? await fetchJson(url, { userAgent: ctx.userAgent })
      : await fetchText(url, { userAgent: ctx.userAgent });
    lastAt = Date.now();
    return response;
  };
}

function parseIdentifier(raw) {
  const value = String(raw || '').trim();
  if (/^\d{1,9}$/.test(value) && Number(value) > 0) return { kind: 'pmid', value: String(Number(value)) };
  if (/^10\.\d{4,9}\/\S+$/.test(value)) return { kind: 'doi', value };
  if (/^NCT\d{8}$/i.test(value)) return { kind: 'nct', value: value.toUpperCase() };
  return null;
}

async function pmidForDoi(ncbi, doi) {
  const term = `"${doi.replace(/"/g, '')}"[doi]`;
  const response = await ncbi(`esearch.fcgi?db=pubmed&retmode=json&retmax=1&term=${encodeURIComponent(term)}`);
  if (!response.ok) throw new LookupError(`PubMed search failed (HTTP ${response.status || '—'}). Try again in a minute.`);
  return response.json?.esearchresult?.idlist?.[0] || null;
}

/** Public metadata and abstract for one paper or trial, mapped exactly as the harvester maps it. */
async function fetchPaper(ncbi, ctx, identifier) {
  if (identifier.kind === 'nct') {
    const response = await fetchJson(`https://clinicaltrials.gov/api/v2/studies/${identifier.value}?format=json`, { userAgent: ctx.userAgent });
    if (response.status === 404) throw new LookupError('ClinicalTrials.gov has no study with that number.');
    if (!response.ok) throw new LookupError(`ClinicalTrials.gov request failed (HTTP ${response.status || '—'}). Try again in a minute.`);
    const item = mapTrialStudy(response.json || {});
    if (!item.metadata.nct_id || !item.title) throw new LookupError('ClinicalTrials.gov has no study with that number.');
    return { ...item, sourceType: 'clinicaltrials' };
  }

  let pmid = identifier.value;
  if (identifier.kind === 'doi') {
    pmid = await pmidForDoi(ncbi, identifier.value);
    if (!pmid) throw new LookupError('That DOI is not in PubMed. Enter the PubMed ID instead, or add studies PubMed indexes.');
  }
  const response = await ncbi(`efetch.fcgi?db=pubmed&retmode=xml&id=${pmid}`, { json: false });
  if (!response.ok && (response.status === 0 || response.status >= 500 || response.status === 429)) {
    throw new LookupError(`PubMed fetch failed (HTTP ${response.status || '—'}). Try again in a minute.`);
  }
  const item = response.ok ? mapPubmedXml(response.text).find((entry) => entry.metadata.pmid === pmid) : null;
  if (!item) throw new LookupError('PubMed has no record with that ID.');
  return { ...item, sourceType: 'pubmed' };
}

async function itemByUrl(pool, url) {
  return (await pool.request().input('hash', sql.Binary(32), sha256(canonicalUrl(url)))
    .query('SELECT ItemID, Status, Title FROM dbo.MktHarvestedItem WHERE UrlHash = @hash')).recordset[0] || null;
}

/** The research item for this paper: existing by URL, else inserted, else the same content under another URL. */
async function resolveItem(pool, paper, userId) {
  const existing = await itemByUrl(pool, paper.url);
  if (existing) return { row: existing, inserted: false };

  const outcome = await store.insertItem(pool, { ...paper, status: 'new' }, []);
  if (outcome === 'inserted') {
    const row = await itemByUrl(pool, paper.url);
    if (row && userId) {
      await pool.request().input('id', sql.BigInt, row.ItemID).input('user', sql.Int, userId)
        .query('UPDATE dbo.MktHarvestedItem SET AddedByUserID = @user WHERE ItemID = @id');
    }
    if (row) return { row, inserted: true };
  }
  const hash = contentHash(paper.title, paper.body || paper.summary);
  const sameContent = hash
    ? (await pool.request().input('hash', sql.Binary(32), hash)
      .query('SELECT TOP 1 ItemID, Status, Title FROM dbo.MktHarvestedItem WHERE ContentHash = @hash ORDER BY ItemID')).recordset[0]
    : null;
  const row = sameContent || await itemByUrl(pool, paper.url);
  if (!row) throw new Error('The paper could not be saved as a research item.');
  return { row, inserted: false };
}

/** Score one never-scored item now with the batch prompt and vocabulary. A reply that will not parse leaves it for the batch. */
async function scoreItemNow(pool, settings, itemId, processLogId) {
  const row = (await pool.request().input('id', sql.BigInt, itemId).query(`
    SELECT h.ItemID, h.Title, h.Url, h.Summary, LEFT(h.BodyText, 6000) AS BodyText, h.SourceType, h.PublishedAt,
           COALESCE(s.Name, h.Domain) AS SourceName
    FROM dbo.MktHarvestedItem h
    LEFT JOIN dbo.MktSource s ON s.SourceID = h.SourceID
    WHERE h.ItemID = @id
  `)).recordset[0];
  const taxonomy = await loadTaxonomy(pool, settings);
  const maxChars = settingNumber(settings, 'research.score_text_chars', 1800);
  const response = await runPrompt(pool, settings, {
    promptKey: SCORE_PROMPT_KEY,
    operation: 'literature.score_item',
    processLogId,
    refType: 'item',
    refId: itemId,
    vars: { ...scoreSystemVars(settings, taxonomy), ...scoreUserVars(row, maxChars) },
  });
  const parsed = extractJson(response.text);
  if (!parsed || Array.isArray(parsed) || typeof parsed !== 'object') {
    return { scored: false, costUsd: response.costUsd || 0 };
  }
  const outcome = await applyScore(pool, itemId, parsed, taxonomy, relevanceThreshold(settings), await interestWeights(pool));
  return { scored: outcome !== 'skipped', costUsd: response.costUsd || 0 };
}

/** Add the item to the library, or reactivate its existing library row. */
async function upsertLibrarySource(pool, itemId, { addedFrom, takeaway, userId }) {
  const result = await pool.request()
    .input('item', sql.BigInt, itemId)
    .input('from', sql.NVarChar(10), addedFrom)
    .input('takeaway', sql.NVarChar(1000), takeaway)
    .input('user', sql.Int, userId)
    .query(`
      SET XACT_ABORT ON;
      BEGIN TRANSACTION;
      DECLARE @sid INT = (SELECT SourceID FROM dbo.MktLitSource WITH (UPDLOCK, HOLDLOCK) WHERE ItemID = @item);
      IF @sid IS NULL
      BEGIN
        INSERT INTO dbo.MktLitSource (ItemID, AddedFrom, Takeaway, CreatedBy, UpdatedBy)
        VALUES (@item, @from, @takeaway, @user, @user);
        SET @sid = CAST(SCOPE_IDENTITY() AS INT);
        DELETE FROM dbo.MktLitDismissed WHERE ItemID = @item;
        SELECT @sid AS SourceID, CAST(1 AS BIT) AS Created;
      END
      ELSE
      BEGIN
        UPDATE dbo.MktLitSource
        SET Status = N'active', Takeaway = COALESCE(Takeaway, @takeaway), UpdatedBy = @user, UpdatedAt = SYSUTCDATETIME()
        WHERE SourceID = @sid;
        SELECT @sid AS SourceID, CAST(0 AS BIT) AS Created;
      END
      COMMIT TRANSACTION;
    `);
  const row = result.recordset[0];
  return { sourceId: Number(row.SourceID), created: Boolean(row.Created) };
}

/**
 * literature-add-source: look up a PMID / DOI / NCT number, make sure it is a scored research item, add it to the
 * library, and optionally confirm it as the match for a flyer reference. Only public paper metadata reaches the AI.
 */
async function addSource(params = {}) {
  const identifier = parseIdentifier(params.identifier);
  const flyerRefId = Number(params.flyer_ref_id || 0) || null;
  const takeaway = String(params.takeaway || '').trim().slice(0, 1000) || null;
  const processLogId = Number(params.log_id || 0) || null;
  const userId = userIdFrom(params);
  if (!identifier) return { ok: false, error: IDENTIFIER_ERROR };

  const pool = await connectPool(getProductionDatabase());
  try {
    if (flyerRefId) {
      const ref = (await pool.request().input('id', sql.Int, flyerRefId)
        .query('SELECT MatchStatus FROM dbo.MktLitFlyerRef WHERE FlyerRefID = @id')).recordset[0];
      if (!ref) return { ok: false, error: 'Flyer reference not found.' };
      if (ref.MatchStatus === 'matched') return { ok: false, error: 'That flyer reference is already matched to a library source.' };
    }
    const settings = await loadSettings(pool);
    const ctx = requestIdentity(settings);

    let paper;
    try {
      paper = await fetchPaper(ncbiClient(ctx), ctx, identifier);
    } catch (error) {
      if (error instanceof LookupError) return { ok: false, error: error.message };
      throw error;
    }

    const { row: item } = await resolveItem(pool, paper, userId);
    const itemId = Number(item.ItemID);
    let scored = false;
    let scoreError = null;
    let costUsd = 0;
    if (item.Status === 'new') {
      try {
        const result = await scoreItemNow(pool, settings, itemId, processLogId);
        scored = result.scored;
        costUsd = result.costUsd;
      } catch (error) {
        scoreError = error.message;
      }
    }

    const { sourceId, created } = await upsertLibrarySource(pool, itemId, {
      addedFrom: flyerRefId ? 'flyer' : 'lookup',
      takeaway,
      userId,
    });
    if (flyerRefId) {
      await pool.request()
        .input('id', sql.Int, flyerRefId)
        .input('sid', sql.Int, sourceId)
        .input('user', sql.Int, userId)
        .query(`
          UPDATE dbo.MktLitFlyerRef SET MatchStatus = N'matched', SourceID = @sid, UpdatedBy = @user, UpdatedAt = SYSUTCDATETIME()
          WHERE FlyerRefID = @id
        `);
    }
    return {
      ok: true,
      source_id: sourceId,
      item_id: itemId,
      title: String(item.Title || paper.title),
      created,
      scored,
      score_attempted: item.Status === 'new',
      score_error: scoreError,
      flyer: Boolean(flyerRefId),
      cost_usd: roundCost(costUsd),
    };
  } finally {
    await pool.close();
  }
}

/**
 * Split a flyer reference like "Langsjoen PH, Langsjoen AM. Clin Pharmacol Drug Dev. 2014;3(1):13-17" into first-author
 * surname (or a group author), journal abbreviation, year, volume and first page. Null when it does not follow that shape.
 */
function parseReference(refText) {
  const value = String(refText || '').replace(/\s+/g, ' ').trim();
  const match = /^(.+?)\.\s+(.+?)\.\s+((?:18|19|20)\d{2})\b(.*)$/.exec(value);
  if (!match) return null;
  const [, authorsPart, journalPart, year, rest] = match;
  // A year outside parentheses means several citations run together; "(1985)" is part of e.g. "J Appl Physiol (1985)".
  if (/\b(?:18|19|20)\d{2}\b/.test(journalPart.replace(/\([^)]*\)/g, ''))) return null;
  const tail = /^\s*(?:[A-Za-z]{3,9}(?:\s+\d{1,2})?)?\s*(?:;\s*([A-Za-z0-9]+)\s*(?:\([^)]*\))?\s*(?:Suppl\.?\s*\d*\s*)?(?::\s*([A-Za-z]*\d+[A-Za-z0-9]*)[^;]*)?)?\.?\s*$/.exec(rest);
  if (!tail) return null;

  const firstAuthor = authorsPart.replace(/,?\s*et\.?\s+al\.?$/i, '').split(',')[0].trim();
  const surname = firstAuthor.replace(/\s+[A-Z]{1,4}$/, '').replace(/["[\]()]/g, '').trim();
  if (!surname) return null;
  const hasInitials = surname !== firstAuthor;
  return {
    surname,
    corporate: !hasInitials && CORPORATE_AUTHOR.test(surname) ? surname : null,
    journal: journalPart.replace(/["[\]]/g, '').trim(),
    year,
    volume: tail[1] || null,
    page: tail[1] && tail[2] ? tail[2] : null,
  };
}

/** Most specific first; the first to return 1–5 PubMed ids wins. */
function searchStrategies(ref) {
  const year = `${ref.year}[dp]`;
  const journal = `"${ref.journal}"[ta]`;
  const volumePage = ref.volume && ref.page ? ` AND ${ref.volume}[vi] AND ${ref.page}[pg]` : '';
  const terms = [];
  if (ref.corporate) {
    const group = `"${ref.corporate}"[cn]`;
    if (volumePage) terms.push(`${group} AND ${year} AND ${journal}${volumePage}`);
    terms.push(`${group} AND ${year} AND ${journal}`);
    if (volumePage) terms.push(`${journal} AND ${year}${volumePage}`);
    return terms;
  }
  const firstAuthor = `${ref.surname}[1au]`;
  if (volumePage) terms.push(`${firstAuthor} AND ${year} AND ${journal}${volumePage}`);
  terms.push(`${firstAuthor} AND ${year} AND ${journal}`);
  terms.push(`${ref.surname}[au] AND ${year} AND ${journal}`);
  // Flyers often shorten the journal (e.g. "Front Endocrinol" for "Front Endocrinol (Lausanne)").
  if (volumePage) terms.push(`${firstAuthor} AND ${year}${volumePage}`);
  return terms;
}

async function ncbiJson(ncbi, path) {
  const response = await ncbi(path);
  const json = response.json;
  if (!response.ok || !json || json.error || json.esearchresult?.ERROR) {
    throw new Error(`PubMed request failed (HTTP ${response.status || '—'}${response.error ? `: ${response.error}` : ''})`);
  }
  return json;
}

async function findPmids(ncbi, ref) {
  let ambiguous = null;
  for (const term of searchStrategies(ref)) {
    const json = await ncbiJson(ncbi, `esearch.fcgi?db=pubmed&retmode=json&retmax=${MAX_CANDIDATES}&term=${encodeURIComponent(term)}`);
    const count = Number(json.esearchresult?.count || 0);
    const ids = json.esearchresult?.idlist || [];
    if (ids.length && count <= MAX_CANDIDATES) return ids;
    if (count > MAX_CANDIDATES && !ambiguous) ambiguous = ids;
  }
  return ambiguous || [];
}

async function candidatesFor(ncbi, ids) {
  if (!ids.length) return [];
  const json = await ncbiJson(ncbi, `esummary.fcgi?db=pubmed&retmode=json&id=${ids.join(',')}`);
  const result = json.result || {};
  return asArray(result.uids).map((uid) => result[uid]).filter((doc) => doc && !doc.error).map((doc) => {
    const names = asArray(doc.authors).map((author) => author.name).filter(Boolean);
    return {
      pmid: String(doc.uid),
      title: stripTags(String(doc.title || '')),
      journal: doc.source || '',
      year: String(doc.pubdate || '').slice(0, 4),
      authors: names.slice(0, 3).join(', ') + (names.length > 3 ? ' et al' : ''),
      volume: doc.volume || '',
      pages: doc.pages || '',
      doi: asArray(doc.articleids).find((id) => id.idtype === 'doi')?.value || null,
    };
  });
}

/**
 * literature-flyer-match: look up unmatched flyer references in PubMed and store up to five candidate papers each.
 * Never confirms a match — a person picks the right candidate. No AI.
 */
async function matchFlyerRefs(params = {}) {
  const productId = Number(params.product_id || 0) || null;
  const flyerRefId = Number(params.flyer_ref_id || 0) || null;
  const max = Math.min(Math.max(Number(params.max || 0) || 60, 1), 200);
  const userId = userIdFrom(params);
  const started = Date.now();
  const pool = await connectPool(getProductionDatabase());
  const totals = { checked: 0, with_candidates: 0, no_match: 0, unparsed: 0, errors: 0 };
  try {
    const settings = await loadSettings(pool);
    const ncbi = ncbiClient(requestIdentity(settings));

    let rows;
    let remainingProductId = productId;
    if (flyerRefId) {
      rows = (await pool.request().input('id', sql.Int, flyerRefId)
        .query('SELECT FlyerRefID, ProductID, RefText, MatchStatus FROM dbo.MktLitFlyerRef WHERE FlyerRefID = @id')).recordset;
      if (!rows.length) return { ok: false, error: 'Flyer reference not found.' };
      if (rows[0].MatchStatus === 'matched') return { ok: false, error: 'That flyer reference is already matched to a library source.' };
      remainingProductId = rows[0].ProductID;
    } else {
      rows = (await pool.request().input('max', sql.Int, max).input('product', sql.Int, productId).query(`
        SELECT TOP (@max) FlyerRefID, ProductID, RefText, MatchStatus
        FROM dbo.MktLitFlyerRef
        WHERE IsStudy = 1 AND MatchStatus = N'unmatched' AND (@product IS NULL OR ProductID = @product)
        ORDER BY ProductID, RefNumber
      `)).recordset;
    }

    let consecutiveErrors = 0;
    for (const row of rows) {
      if (consecutiveErrors >= MAX_CONSECUTIVE_ERRORS || Date.now() - started > MATCH_TIME_BUDGET_MS) break;
      const ref = parseReference(row.RefText);
      let candidates = [];
      if (ref) {
        try {
          candidates = await candidatesFor(ncbi, await findPmids(ncbi, ref));
          consecutiveErrors = 0;
        } catch {
          totals.errors += 1;
          consecutiveErrors += 1;
          continue;
        }
      } else {
        totals.unparsed += 1;
      }
      await pool.request()
        .input('id', sql.Int, row.FlyerRefID)
        .input('json', sql.NVarChar(sql.MAX), JSON.stringify(candidates))
        .input('status', sql.NVarChar(12), candidates.length ? 'candidates' : 'no_match')
        .input('user', sql.Int, userId)
        .query(`
          UPDATE dbo.MktLitFlyerRef
          SET CandidatesJson = @json, MatchStatus = @status, CheckedAt = SYSUTCDATETIME(), UpdatedBy = @user, UpdatedAt = SYSUTCDATETIME()
          WHERE FlyerRefID = @id AND MatchStatus <> N'matched'
        `);
      totals.checked += 1;
      totals[candidates.length ? 'with_candidates' : 'no_match'] += 1;
    }

    const remaining = (await pool.request().input('product', sql.Int, remainingProductId).query(`
      SELECT COUNT(*) AS N FROM dbo.MktLitFlyerRef
      WHERE IsStudy = 1 AND MatchStatus = N'unmatched' AND (@product IS NULL OR ProductID = @product)
    `)).recordset[0].N;
    return { ok: true, ...totals, remaining: Number(remaining) };
  } finally {
    await pool.close();
  }
}

module.exports = { addSource, matchFlyerRefs };
