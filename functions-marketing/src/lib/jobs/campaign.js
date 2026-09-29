const { sql, connectPool, getProductionDatabase } = require('../db-config');
const { loadSettings, settingNumber } = require('../mkt/settings');
const { runPrompt, extractJson, extractArrayObjects, BudgetExceededError } = require('../mkt/ai');
const { oneLine } = require('../mkt/taxonomy');
const {
  loadChannels, sharedVars, loadCampaign, evidenceLines, checkAsset, checkContext, mapLimit,
} = require('../mkt/campaign');

const GENERATE_PROMPT_KEY = 'campaign.generate';
const REVISE_PROMPT_KEY = 'campaign.revise_asset';
const EDITABLE_STATUSES = new Set(['draft', 'changes_requested']);
const CHECK_CONCURRENCY = 4;

function userIdFrom(params) {
  return Number(params.triggered_by_user_id || params.user_id || 0) || null;
}

function formatInstructions(campaign, channels) {
  const parts = Number(campaign.PartCount) || 1;
  if (campaign.Format === 'email') {
    const base = 'channel = "email". Each email needs 3 subject_variants (subject = the strongest), preview_text under 90 characters, and a plain-text body with short paragraphs.';
    return parts > 1
      ? `An email sequence of ${parts} emails sent ${campaign.CadenceDays || 3} days apart, each building on the last; sequence = email number. ${base}`
      : `One email; sequence = 1. ${base}`;
  }
  const channelNote = `Produce one asset per channel listed (${channels.map((row) => row.key).join(', ')}).`;
  if (campaign.Format === 'series') {
    return `A ${parts}-part social series posted ${campaign.CadenceDays || 2} days apart with a narrative arc: part 1 frames the question, `
      + `the middle parts each cover one evidence point, the final part sums up and invites action. ${channelNote} `
      + 'Every part must stand on its own for readers who missed the others; sequence = part number.';
  }
  return `A single post. ${channelNote} sequence = 1 for every asset.`;
}

function hashtagsText(value) {
  const list = (Array.isArray(value) ? value : String(value || '').split(/[\s,]+/))
    .map((tag) => String(tag || '').trim().replace(/^#?/, '#'))
    .filter((tag) => tag.length > 1);
  return list.length ? list.join(' ').slice(0, 500) : null;
}

function claimIdsText(value, allowed) {
  const ids = (Array.isArray(value) ? value : []).map(Number).filter((id) => allowed.has(id));
  return ids.length ? JSON.stringify([...new Set(ids)]) : null;
}

function subjectVariantsText(value) {
  const list = (Array.isArray(value) ? value : []).map((entry) => oneLine(entry, 200)).filter(Boolean).slice(0, 5);
  return list.length ? JSON.stringify(list) : null;
}

async function loadAssets(pool, where, input) {
  const request = pool.request();
  for (const [name, [type, value]] of Object.entries(input)) request.input(name, type, value);
  return (await request.query(`SELECT * FROM dbo.MktAsset WHERE ${where} ORDER BY SequenceNo, Channel`)).recordset;
}

async function checkAll(pool, settings, campaign, assets, processLogId) {
  const context = await checkContext(pool, settings, campaign);
  const minScore = settingNumber(settings, 'claims.min_score', 7);
  const totals = { checked: 0, passing: 0, needs_compliance: 0, check_failed: 0, cost_usd: 0, errors: [] };
  await mapLimit(assets, CHECK_CONCURRENCY, async (asset) => {
    try {
      const result = await checkAsset(pool, settings, asset, context, processLogId);
      totals.checked += 1;
      if (result.score >= minScore) totals.passing += 1;
      if (result.needsCompliance) totals.needs_compliance += 1;
      totals.cost_usd += result.costUsd;
    } catch (error) {
      totals.check_failed += 1;
      totals.errors.push(`Asset ${asset.AssetID}: ${error.message}`);
      if (error instanceof BudgetExceededError) throw error;
    }
  });
  return totals;
}

/**
 * Generate every asset of a campaign in one call, then claims-check each. Regeneration replaces the
 * assets only while all of them are still editable drafts.
 */
async function generate(params = {}) {
  const campaignId = Number(params.campaign_id || 0);
  if (!campaignId) return { ok: false, error: 'campaign_id is required.' };
  const processLogId = Number(params.log_id || 0) || null;
  const userId = userIdFrom(params);
  const pool = await connectPool(getProductionDatabase());

  try {
    const settings = await loadSettings(pool);
    const campaign = await loadCampaign(pool, campaignId);
    if (!campaign) return { ok: false, error: `Campaign ${campaignId} not found.` };
    if (campaign.TopicID && campaign.TopicStatus !== 'accepted') {
      return { ok: false, error: 'The campaign topic is no longer accepted — accept it on the Topic Board first.' };
    }
    const existing = await loadAssets(pool, 'CampaignID = @id', { id: [sql.Int, campaignId] });
    if (existing.some((asset) => !EDITABLE_STATUSES.has(asset.Status))) {
      return { ok: false, error: 'Some assets are already in review or approved; regenerate is only available while every asset is a draft.' };
    }

    const channelMap = loadChannels(settings);
    const channels = String(campaign.Channels).split(',').map((key) => channelMap.get(key.trim())).filter(Boolean);
    if (channels.length === 0) return { ok: false, error: 'The campaign has no valid channels.' };
    const context = await checkContext(pool, settings, campaign);

    const response = await runPrompt(pool, settings, {
      promptKey: GENERATE_PROMPT_KEY,
      operation: GENERATE_PROMPT_KEY,
      processLogId,
      refType: 'campaign',
      refId: campaignId,
      vars: {
        ...sharedVars(settings, campaign.Audience),
        approved_claims: context.claimLines,
        format_instructions: formatInstructions(campaign, channels),
        channels: channels.map((row) => `${row.key} | ${row.label} | ${row.guidance}`).join('\n'),
        cta_text: campaign.CtaText || 'Learn more',
        topic_title: campaign.TopicTitle || campaign.Name,
        topic_summary: campaign.TopicSummary || '',
        topic_why: campaign.TopicWhy || '',
        angle: campaign.TopicAngle || campaign.Brief || '',
        avoid: campaign.TopicAvoid || '(nothing beyond the compliance rules)',
        brief: campaign.Brief || '(none)',
        evidence: await evidenceLines(pool, campaign.TopicID),
      },
    });

    const parsed = extractJson(response.text);
    const list = parsed && !Array.isArray(parsed) && Array.isArray(parsed.assets)
      ? parsed.assets
      : extractArrayObjects(response.text, 'assets');
    const allowedChannels = new Set(channels.map((row) => row.key));
    const allowedClaims = new Set(context.claims.map((row) => row.ClaimID));
    const parts = Number(campaign.PartCount) || 1;
    const seen = new Set();
    const assets = [];
    for (const entry of list) {
      if (!entry || typeof entry !== 'object' || !String(entry.body || '').trim()) continue;
      const channel = String(entry.channel || '').trim().toLowerCase();
      const sequence = Math.min(parts, Math.max(1, Number(entry.sequence) || 1));
      if (!allowedChannels.has(channel) || seen.has(`${sequence}|${channel}`)) continue;
      seen.add(`${sequence}|${channel}`);
      assets.push({ ...entry, channel, sequence });
    }
    if (assets.length === 0) {
      throw new Error(`Generation returned no usable assets (stop: ${response.stopReason}).`);
    }

    await pool.request().input('id', sql.Int, campaignId)
      .query(`DELETE FROM dbo.MktAsset WHERE CampaignID = @id AND Status IN (N'draft', N'changes_requested')`);
    for (const entry of assets) {
      await pool.request()
        .input('campaign', sql.Int, campaignId)
        .input('channel', sql.NVarChar(30), entry.channel)
        .input('sequence', sql.Int, entry.sequence)
        .input('title', sql.NVarChar(300), entry.title ? oneLine(entry.title, 300) : null)
        .input('subject', sql.NVarChar(300), entry.subject ? oneLine(entry.subject, 300) : null)
        .input('variants', sql.NVarChar(2000), subjectVariantsText(entry.subject_variants))
        .input('preview', sql.NVarChar(300), entry.preview_text ? oneLine(entry.preview_text, 300) : null)
        .input('body', sql.NVarChar(sql.MAX), String(entry.body).trim())
        .input('hashtags', sql.NVarChar(500), hashtagsText(entry.hashtags))
        .input('cta', sql.NVarChar(200), entry.cta_text ? oneLine(entry.cta_text, 200) : campaign.CtaText)
        .input('media', sql.NVarChar(1000), entry.media_notes ? oneLine(entry.media_notes, 1000) : null)
        .input('claims', sql.NVarChar(500), claimIdsText(entry.claim_ids_used, allowedClaims))
        .input('user', sql.Int, userId)
        .query(`
          INSERT INTO dbo.MktAsset (CampaignID, Channel, SequenceNo, Title, Subject, SubjectVariantsJson, PreviewText, Body,
            Hashtags, CtaText, MediaNotes, ClaimIdsJson, CreatedBy, UpdatedBy)
          VALUES (@campaign, @channel, @sequence, @title, @subject, @variants, @preview, @body,
            @hashtags, @cta, @media, @claims, @user, @user)
        `);
    }
    await pool.request()
      .input('id', sql.Int, campaignId)
      .input('log_id', sql.Int, processLogId)
      .input('version', sql.Int, response.promptVersion)
      .input('user', sql.Int, userId)
      .query(`
        UPDATE dbo.MktCampaign
        SET Status = N'active', GeneratedAt = SYSUTCDATETIME(), GenerationLogID = @log_id, PromptVersion = @version,
            UpdatedBy = @user, UpdatedAt = SYSUTCDATETIME()
        WHERE CampaignID = @id
      `);

    const inserted = await loadAssets(pool, 'CampaignID = @id', { id: [sql.Int, campaignId] });
    const checks = await checkAll(pool, settings, campaign, inserted, processLogId);
    return {
      ok: true,
      error: null,
      campaign_id: campaignId,
      assets: inserted.length,
      ...checks,
      cost_usd: Math.round((response.costUsd + checks.cost_usd) * 10000) / 10000,
      truncated: response.stopReason === 'max_tokens',
      errors: checks.errors.slice(0, 10),
    };
  } finally {
    await pool.close();
  }
}

/** Re-run the claims check on one asset, or on every editable asset of a campaign whose content changed. */
async function check(params = {}) {
  const assetId = Number(params.asset_id || 0);
  const campaignId = Number(params.campaign_id || 0);
  if (!assetId && !campaignId) return { ok: false, error: 'asset_id or campaign_id is required.' };
  const processLogId = Number(params.log_id || 0) || null;
  const pool = await connectPool(getProductionDatabase());

  try {
    const settings = await loadSettings(pool);
    const assets = assetId
      ? await loadAssets(pool, 'AssetID = @id', { id: [sql.Int, assetId] })
      : await loadAssets(pool, `CampaignID = @id AND Status IN (N'draft', N'changes_requested')
          AND (ClaimsCheckedVersion IS NULL OR ClaimsCheckedVersion <> ContentVersion)`, { id: [sql.Int, campaignId] });
    if (assets.length === 0) {
      return { ok: true, error: null, skipped: true, message: 'Every asset is already checked at its current version.', checked: 0 };
    }
    const campaign = await loadCampaign(pool, assets[0].CampaignID);
    const totals = await checkAll(pool, settings, campaign, assets, processLogId);
    const allFailed = totals.check_failed > 0 && totals.checked === 0;
    return {
      ok: !allFailed,
      error: allFailed ? totals.errors[0] : null,
      ...totals,
      cost_usd: Math.round(totals.cost_usd * 10000) / 10000,
      errors: totals.errors.slice(0, 10),
    };
  } finally {
    await pool.close();
  }
}

function findingsText(asset) {
  try {
    const result = JSON.parse(asset.ClaimsCheckJson || 'null');
    if (!result) return '(not checked yet)';
    const lines = [
      ...result.statements.filter((row) => row.class === 'unapproved' || row.class === 'disease')
        .map((row) => `- ${row.class}: "${row.text}"${row.note ? ` — ${row.note}` : ''}`),
      ...result.issues.map((issue) => `- ${issue}`),
      ...(result.flag_terms || []).map((term) => `- flag term used: "${term}"`),
    ];
    return lines.length ? lines.join('\n') : '(no findings)';
  } catch {
    return '(not checked yet)';
  }
}

/** AI revision of one editable asset from an instruction and its claims-check findings; re-checks afterwards. */
async function revise(params = {}) {
  const assetId = Number(params.asset_id || 0);
  if (!assetId) return { ok: false, error: 'asset_id is required.' };
  const instruction = String(params.instruction || '').trim().slice(0, 2000);
  const processLogId = Number(params.log_id || 0) || null;
  const userId = userIdFrom(params);
  const pool = await connectPool(getProductionDatabase());

  try {
    const settings = await loadSettings(pool);
    const [asset] = await loadAssets(pool, 'AssetID = @id', { id: [sql.Int, assetId] });
    if (!asset) return { ok: false, error: `Asset ${assetId} not found.` };
    if (!EDITABLE_STATUSES.has(asset.Status)) {
      return { ok: false, error: 'Only draft assets or assets with changes requested can be revised.' };
    }
    const campaign = await loadCampaign(pool, asset.CampaignID);
    const context = await checkContext(pool, settings, campaign);
    const channel = context.channels.get(asset.Channel);

    const response = await runPrompt(pool, settings, {
      promptKey: REVISE_PROMPT_KEY,
      operation: REVISE_PROMPT_KEY,
      processLogId,
      refType: 'asset',
      refId: assetId,
      vars: {
        ...sharedVars(settings, campaign.Audience),
        approved_claims: context.claimLines,
        channel: channel ? channel.label : asset.Channel,
        channel_guidance: channel ? channel.guidance : '',
        instruction: instruction || 'Fix every claims-check finding while keeping the message.',
        findings: findingsText(asset),
        title: asset.Title || '',
        subject: asset.Subject || '',
        preview_text: asset.PreviewText || '',
        body: asset.Body,
      },
    });
    const parsed = extractJson(response.text);
    if (!parsed || Array.isArray(parsed) || !String(parsed.body || '').trim()) {
      throw new Error(`Revision returned no body (stop: ${response.stopReason}).`);
    }
    const allowedClaims = new Set(context.claims.map((row) => row.ClaimID));
    await pool.request()
      .input('id', sql.Int, assetId)
      .input('title', sql.NVarChar(300), parsed.title ? oneLine(parsed.title, 300) : asset.Title)
      .input('subject', sql.NVarChar(300), parsed.subject ? oneLine(parsed.subject, 300) : asset.Subject)
      .input('variants', sql.NVarChar(2000), subjectVariantsText(parsed.subject_variants) || asset.SubjectVariantsJson)
      .input('preview', sql.NVarChar(300), parsed.preview_text ? oneLine(parsed.preview_text, 300) : asset.PreviewText)
      .input('body', sql.NVarChar(sql.MAX), String(parsed.body).trim())
      .input('hashtags', sql.NVarChar(500), parsed.hashtags ? hashtagsText(parsed.hashtags) : asset.Hashtags)
      .input('claims', sql.NVarChar(500), claimIdsText(parsed.claim_ids_used, allowedClaims))
      .input('user', sql.Int, userId)
      .query(`
        UPDATE dbo.MktAsset
        SET Title = @title, Subject = @subject, SubjectVariantsJson = @variants, PreviewText = @preview, Body = @body,
            Hashtags = @hashtags, ClaimIdsJson = @claims, ContentVersion = ContentVersion + 1,
            UpdatedBy = @user, UpdatedAt = SYSUTCDATETIME()
        WHERE AssetID = @id
      `);
    await pool.request()
      .input('id', sql.Int, assetId)
      .input('note', sql.NVarChar(2000), instruction ? `AI revision: ${instruction}` : 'AI revision to fix claims-check findings')
      .input('user', sql.Int, userId)
      .query(`INSERT INTO dbo.MktAssetReview (AssetID, Gate, Decision, Note, UserID) VALUES (@id, N'system', N'ai_revised', @note, @user)`);

    const [updated] = await loadAssets(pool, 'AssetID = @id', { id: [sql.Int, assetId] });
    const result = await checkAsset(pool, settings, updated, context, processLogId);
    return {
      ok: true,
      error: null,
      asset_id: assetId,
      score: result.score,
      needs_compliance: result.needsCompliance,
      cost_usd: Math.round((response.costUsd + result.costUsd) * 10000) / 10000,
    };
  } finally {
    await pool.close();
  }
}

module.exports = { generate, check, revise, formatInstructions };
