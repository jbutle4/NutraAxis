const { sql, connectPool, getProductionDatabase } = require('../db-config');
const { loadSettings, settingNumber } = require('../mkt/settings');
const { runPrompt, extractJson, BudgetExceededError } = require('../mkt/ai');
const { fetchText, sleep } = require('../mkt/http');
const { extractArticle, parseDate } = require('../mkt/html');
const store = require('../mkt/store');

const PROMPT_KEY = 'research.discover';
const RERUN_AFTER_DAYS = 6;
const MAX_RUN_MS = 9 * 60 * 1000;
const VERIFY_DELAY_MS = 750;
const TITLE_MATCH_RATIO = 0.6;
const ACCESS_BLOCKED_STATUSES = new Set([401, 403, 429, 503]);

async function dueInterests(pool, interestId) {
  const request = pool.request().input('days', sql.Int, RERUN_AFTER_DAYS);
  let where = `Status = N'active' AND AgentEnabled = 1
    AND (LastAgentRunAt IS NULL OR LastAgentRunAt <= DATEADD(DAY, -@days, SYSUTCDATETIME()))`;
  if (interestId) {
    request.input('interest_id', sql.Int, interestId);
    where = 'InterestID = @interest_id';
  }
  const result = await request.query(`
    SELECT InterestID, Name, Description, Audience, TherapeuticArea, ProductLine
    FROM dbo.MktInterest
    WHERE ${where}
    ORDER BY Priority DESC, COALESCE(LastAgentRunAt, '2000-01-01') ASC
  `);
  return result.recordset;
}

async function interestContext(pool, interestId) {
  const terms = await pool.request()
    .input('id', sql.Int, interestId)
    .query('SELECT TermType, Term FROM dbo.MktInterestTerm WHERE InterestID = @id ORDER BY TermID');
  const byType = { include: [], exclude: [], hashtag: [], query: [] };
  for (const row of terms.recordset) {
    (byType[row.TermType] || []).push(row.Term);
  }
  const domains = await pool.request()
    .input('id', sql.Int, interestId)
    .query(`
      SELECT TOP 15 h.Domain
      FROM dbo.MktHarvestedItem h
      WHERE h.Domain IS NOT NULL AND h.Status NOT IN (N'rejected', N'duplicate')
        AND (h.InterestID = @id OR h.SourceID IN (SELECT SourceID FROM dbo.MktInterestSource WHERE InterestID = @id))
      GROUP BY h.Domain
      ORDER BY COUNT(*) DESC
    `);
  return { terms: byType, knownDomains: domains.recordset.map((row) => row.Domain) };
}

function normalizeText(text) {
  return String(text || '')
    .toLowerCase()
    .replace(/[\u2018\u2019\u201c\u201d]/g, "'")
    .replace(/[^a-z0-9%.' ]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function titleMatches(claimedTitle, pageTitle, pageText) {
  const words = normalizeText(claimedTitle).split(' ').filter((word) => word.length > 3);
  if (words.length === 0) return false;
  const haystack = ` ${normalizeText(pageTitle)} ${normalizeText(pageText).slice(0, 20000)} `;
  const hits = words.filter((word) => haystack.includes(` ${word}`)).length;
  return hits / words.length >= TITLE_MATCH_RATIO;
}

/**
 * Fetch the cited page and confirm it exists and supports the claim (quote on page, or title match).
 * Sites that refuse automated clients outright cannot be checked either way; those stay in the queue
 * flagged unverified rather than being rejected as fabricated.
 * @returns {Promise<{verified: boolean, blocked: boolean, note: string, article: object|null}>}
 */
async function verifyCitation(candidate, userAgent) {
  const page = await fetchText(candidate.url, { userAgent, timeoutMs: 20000, accept: 'text/html,application/xhtml+xml,application/pdf;q=0.8,*/*;q=0.5' });
  if (!page.ok && ACCESS_BLOCKED_STATUSES.has(Number(page.status))) {
    return { verified: false, blocked: true, note: `UNVERIFIED — site blocks automated checks (HTTP ${page.status}); confirm manually before citing`, article: null };
  }
  if (!page.ok) {
    return { verified: false, blocked: false, note: `Page not reachable (${page.error || `HTTP ${page.status}`})`, article: null };
  }
  if (/application\/pdf/i.test(page.contentType || '')) {
    return { verified: true, blocked: false, note: 'PDF reachable; quote not machine-checked', article: null };
  }
  const article = extractArticle(page.text, page.url || candidate.url);
  const quote = normalizeText(candidate.quote);
  if (quote.length >= 12 && normalizeText(article.text).includes(quote)) {
    return { verified: true, blocked: false, note: 'Quote found on page', article };
  }
  if (titleMatches(candidate.title, article.title, article.text)) {
    return { verified: true, blocked: false, note: quote ? 'Title matched page; quote not found verbatim' : 'Title matched page', article };
  }
  return { verified: false, blocked: false, note: 'Neither the quoted text nor the title was found on the page', article };
}

function validCandidate(entry) {
  if (!entry || typeof entry !== 'object') return false;
  try {
    const url = new URL(String(entry.url || ''));
    return ['http:', 'https:'].includes(url.protocol) && String(entry.title || '').trim() !== '';
  } catch {
    return false;
  }
}

async function discoverForInterest(pool, settings, interest, { processLogId, userAgent, maxResults, lookbackDays, recentSimhashes }) {
  const context = await interestContext(pool, interest.InterestID);
  const runId = await store.startRun(pool, { interestId: interest.InterestID, runType: 'ai_research', processLogId });
  const counts = { fetched: 0, inserted: 0, duplicates: 0, rejected: 0 };
  let unverified = 0;
  let costUsd = 0;

  try {
    const response = await runPrompt(pool, settings, {
      promptKey: PROMPT_KEY,
      webSearch: true,
      maxSearches: Math.max(3, maxResults),
      operation: 'research.discover',
      processLogId,
      refType: 'interest',
      refId: interest.InterestID,
      vars: {
        brand_name: settings['brand.name'] || 'NutraAxis',
        interest_name: interest.Name,
        interest_description: [interest.Description, interest.TherapeuticArea, interest.ProductLine].filter(Boolean).join(' — '),
        interest_audience: interest.Audience || 'practitioners',
        include_terms: context.terms.include.join(', ') || '(none)',
        exclude_terms: context.terms.exclude.join(', ') || '(none)',
        queries: context.terms.query.join('; ') || '(use your judgement)',
        known_domains: context.knownDomains.join(', ') || '(none yet)',
        max_results: maxResults,
        lookback_days: lookbackDays,
      },
    });
    costUsd = response.costUsd;

    const parsed = extractJson(response.text);
    const list = Array.isArray(parsed) ? parsed : parsed?.items;
    if (!Array.isArray(list)) {
      throw new Error(`Model did not return a JSON array (stop: ${response.stopReason}).`);
    }
    const candidates = list.filter(validCandidate).slice(0, maxResults);
    counts.fetched = candidates.length;

    for (const candidate of candidates) {
      if (await store.isKnownUrl(pool, candidate.url)) {
        counts.duplicates += 1;
        continue;
      }
      const check = await verifyCitation(candidate, userAgent);
      const outcome = await store.insertItem(pool, {
        url: candidate.url,
        title: String(candidate.title).trim(),
        summary: candidate.summary || null,
        body: check.verified ? (check.article?.text || null) : null,
        author: check.article?.author || null,
        publishedAt: parseDate(candidate.published_date) || check.article?.publishedAt || null,
        sourceType: 'ai_research',
        interestId: interest.InterestID,
        harvestRunId: runId,
        status: check.verified || check.blocked ? 'new' : 'rejected',
        verificationNote: check.note,
        promptKey: PROMPT_KEY,
        promptVersion: response.promptVersion,
        metadata: {
          verified: check.verified,
          evidence_type: candidate.evidence_type || null,
          why_relevant: candidate.why_relevant || null,
          quote: candidate.quote || null,
          model: response.model,
        },
      }, recentSimhashes);
      if (outcome === 'duplicate') counts.duplicates += 1;
      else if (check.verified) counts.inserted += 1;
      else if (check.blocked) {
        counts.inserted += 1;
        unverified += 1;
      } else counts.rejected += 1;
      await sleep(VERIFY_DELAY_MS);
    }

    await store.finishRun(pool, runId, { status: 'success', ...counts });
    await pool.request()
      .input('id', sql.Int, interest.InterestID)
      .query('UPDATE dbo.MktInterest SET LastAgentRunAt = SYSUTCDATETIME() WHERE InterestID = @id');
    return { ...counts, unverified, costUsd };
  } catch (error) {
    await store.finishRun(pool, runId, { status: 'failed', ...counts, error: error.message });
    throw error;
  }
}

async function run(params = {}) {
  const interestId = Number(params.interest_id || params.interestId || 0) || null;
  const processLogId = Number(params.log_id || 0) || null;
  const pool = await connectPool(getProductionDatabase());
  const totals = {
    interests: 0, candidates: 0, verified: 0, unverified: 0, rejected: 0, duplicates: 0, failed: 0, cost_usd: 0, errors: [],
  };
  const started = Date.now();

  try {
    const settings = await loadSettings(pool);
    const options = {
      processLogId,
      userAgent: settings['harvest.user_agent'] || 'NutraAxisResearchBot/1.0',
      maxResults: settingNumber(settings, 'research.agent_max_results', 8),
      lookbackDays: settingNumber(settings, 'harvest.lookback_days', 30),
      recentSimhashes: await store.loadRecentSimhashes(pool),
    };
    const interests = await dueInterests(pool, interestId);
    if (interests.length === 0) {
      return { ok: true, error: null, skipped: true, message: 'No agent-enabled interests are due (each runs at most weekly).', ...totals };
    }

    for (const interest of interests) {
      if (Date.now() - started > MAX_RUN_MS) break;
      totals.interests += 1;
      try {
        const result = await discoverForInterest(pool, settings, interest, options);
        totals.candidates += result.fetched;
        totals.verified += result.inserted - result.unverified;
        totals.unverified += result.unverified;
        totals.rejected += result.rejected;
        totals.duplicates += result.duplicates;
        totals.cost_usd += result.costUsd;
      } catch (error) {
        totals.failed += 1;
        totals.errors.push(`${interest.Name}: ${error.message}`);
        if (error instanceof BudgetExceededError) break;
      }
    }

    totals.cost_usd = Math.round(totals.cost_usd * 10000) / 10000;
    const allFailed = totals.failed > 0 && totals.failed === totals.interests;
    return {
      ok: !allFailed,
      error: allFailed ? totals.errors[0] : null,
      ...totals,
      errors: totals.errors.slice(0, 20),
    };
  } finally {
    await pool.close();
  }
}

module.exports = { run, verifyCitation, titleMatches, normalizeText };
