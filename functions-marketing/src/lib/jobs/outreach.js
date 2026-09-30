const { sql, connectPool, getProductionDatabase } = require('../db-config');
const { loadSettings } = require('../mkt/settings');
const { runPrompt, extractJson } = require('../mkt/ai');
const { sha256 } = require('../mkt/dedup');
const pages = require('../mkt/pages');
const { sharedVars, claimLines, reviewCopy } = require('../mkt/campaign');

const PITCH_PROMPT_KEY = 'outreach.pitch';
const CLOSED_STATUSES = new Set(['won', 'declined']);

function userIdFrom(params) {
  return Number(params.triggered_by_user_id || params.user_id || 0) || null;
}

function roundCost(value) {
  return Math.round(value * 10000) / 10000;
}

/** The page being pitched: its crawled title when it is in the Page Inventory, else the URL itself. */
async function targetPage(pool, settings, targetUrl) {
  const url = String(targetUrl || '').trim();
  if (!url) return { url: settings['brand.site_url'] || '', title: 'our website' };
  const key = pages.pageKey(url);
  if (!key) return { url, title: url };
  const row = (await pool.request().input('hash', sql.Binary(32), sha256(key))
    .query('SELECT Title FROM dbo.MktPage WHERE PageKeyHash = @hash')).recordset[0];
  return { url, title: String(row?.Title || '').trim() || url };
}

/** outreach-draft-pitch: AI pitch email for one prospect, claims-checked. Contact details are never read or sent. */
async function draftPitch(params = {}) {
  const prospectId = Number(params.prospect_id || 0);
  const processLogId = Number(params.log_id || 0) || null;
  const userId = userIdFrom(params);
  if (!prospectId) return { ok: false, error: 'prospect_id is required.' };
  const pool = await connectPool(getProductionDatabase());
  try {
    const settings = await loadSettings(pool);
    const prospect = (await pool.request().input('id', sql.Int, prospectId)
      .query('SELECT ProspectID, Domain, Opportunity, Angle, TargetUrl, Status FROM dbo.MktProspect WHERE ProspectID = @id')).recordset[0];
    if (!prospect) return { ok: false, error: 'Prospect not found.' };
    if (CLOSED_STATUSES.has(prospect.Status)) return { ok: false, error: 'This prospect is closed.' };
    const target = await targetPage(pool, settings, prospect.TargetUrl);
    const { brand_name: brandName, brand_voice: brandVoice } = sharedVars(settings, 'both');

    const response = await runPrompt(pool, settings, {
      promptKey: PITCH_PROMPT_KEY,
      operation: PITCH_PROMPT_KEY,
      processLogId,
      refType: 'prospect',
      refId: prospectId,
      vars: {
        brand_name: brandName,
        brand_voice: brandVoice,
        prospect_domain: prospect.Domain,
        opportunity: prospect.Opportunity,
        angle: String(prospect.Angle || '').trim() || '(none given — use the opportunity)',
        target_title: target.title,
        target_url: target.url,
      },
    });
    const parsed = extractJson(response.text);
    const subject = typeof parsed?.subject === 'string' ? parsed.subject.trim() : '';
    const body = typeof parsed?.body === 'string' ? parsed.body.trim() : '';
    if (!subject || !body) throw new Error(`Pitch draft returned no subject or body (stop: ${response.stopReason}).`);

    const review = await reviewCopy(pool, settings, {
      refType: 'prospect',
      refId: prospectId,
      channel: 'Outreach email',
      title: '',
      subject,
      previewText: '',
      body,
    }, { audience: 'both', claimLines: claimLines([]) }, processLogId);
    const { result, score, needsCompliance } = review;

    await pool.request()
      .input('id', sql.Int, prospectId)
      .input('subject', sql.NVarChar(300), subject.slice(0, 300))
      .input('body', sql.NVarChar(sql.MAX), body)
      .input('score', sql.Decimal(4, 1), score)
      .input('json', sql.NVarChar(sql.MAX), JSON.stringify(result))
      .input('user', sql.Int, userId)
      .query(`
        UPDATE dbo.MktProspect
        SET PitchSubject = @subject, PitchBody = @body, PitchClaimsScore = @score, PitchCheckJson = @json,
            PitchDraftedAt = SYSUTCDATETIME(), UpdatedAt = SYSUTCDATETIME(), UpdatedBy = @user
        WHERE ProspectID = @id
      `);
    await pool.request()
      .input('id', sql.Int, prospectId)
      .input('note', sql.NVarChar(2000), `AI draft — claims score ${score}` + (needsCompliance ? ', flagged for compliance' : ''))
      .input('user', sql.Int, userId)
      .query(`INSERT INTO dbo.MktProspectEvent (ProspectID, EventType, Note, CreatedBy) VALUES (@id, N'drafted', @note, @user)`);
    return {
      ok: true,
      prospect_id: prospectId,
      domain: prospect.Domain,
      score,
      needs_compliance: needsCompliance,
      cost_usd: roundCost((response.costUsd || 0) + (review.costUsd || 0)),
    };
  } finally {
    await pool.close();
  }
}

module.exports = { draftPitch };
