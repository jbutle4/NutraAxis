const { sql } = require('../db-config');
const { settingLines, settingNumber } = require('./settings');
const { runPrompt, extractJson } = require('./ai');
const { oneLine } = require('./taxonomy');

const CHECK_PROMPT_KEY = 'campaign.claims_check';
const STATEMENT_CLASSES = new Set(['approved', 'evidence', 'unapproved', 'disease']);
const CLAIM_CLASSES = new Set(['approved', 'unapproved', 'disease']);
// X shortens every link to 23 characters.
const X_LINK_CHARS = 23;
const MAX_EVIDENCE_ITEMS = 8;

/** @returns {Map<string, {key: string, label: string, medium: string, maxChars: number, guidance: string}>} */
function loadChannels(settings) {
  const channels = new Map();
  for (const line of settingLines(settings, 'campaign.channels')) {
    const [key, label, medium, maxChars, guidance] = line.split('|').map((part) => (part || '').trim());
    if (!key) continue;
    channels.set(key, {
      key,
      label: label || key,
      medium: medium === 'email' ? 'email' : 'social',
      maxChars: Number(maxChars) || 0,
      guidance: guidance || '',
    });
  }
  return channels;
}

function audienceText(settings, audience) {
  const lines = settingLines(settings, 'brand.audiences').map((line) => {
    const [key, label] = line.split('|');
    return { key: (key || '').trim(), label: (label || key || '').trim() };
  });
  const wanted = audience === 'both' ? lines : lines.filter((row) => row.key === audience);
  return (wanted.length ? wanted : lines).map((row) => `${row.key}: ${row.label}`).join('; ') || audience;
}

function sharedVars(settings, audience) {
  return {
    brand_name: settings['brand.name'] || 'NutraAxis',
    brand_voice: settings['brand.voice'] || '',
    audience: audienceText(settings, audience),
    rules: settingLines(settings, 'campaign.rules').map((rule) => `- ${rule}`).join('\n'),
    disclaimer: settings['claims.disclaimer'] || '',
  };
}

async function loadCampaign(pool, campaignId) {
  const result = await pool.request().input('id', sql.Int, campaignId).query(`
    SELECT c.*, t.Title AS TopicTitle, t.Summary AS TopicSummary, t.WhyItMatters AS TopicWhy, t.Angle AS TopicAngle,
           t.AvoidNotes AS TopicAvoid, t.Status AS TopicStatus
    FROM dbo.MktCampaign c
    LEFT JOIN dbo.MktTopic t ON t.TopicID = c.TopicID
    WHERE c.CampaignID = @id
  `);
  return result.recordset[0] || null;
}

/**
 * Approved claims linked to the topic, plus every approved claim of the product when one is given — the only
 * benefit wording generation and the check accept.
 */
async function approvedClaims(pool, topicId, productId = null) {
  if (!topicId && !productId) return [];
  const result = await pool.request()
    .input('topic', sql.Int, topicId || null)
    .input('product', sql.Int, productId || null)
    .query(`
      SELECT c.ClaimID, c.ClaimText, c.RequiresDisclaimer, p.Name AS ProductName
      FROM dbo.MktClaim c
      LEFT JOIN dbo.MktProduct p ON p.ProductID = c.ProductID
      WHERE c.Status = N'approved'
        AND (c.ClaimID IN (SELECT ClaimID FROM dbo.MktTopicClaim WHERE TopicID = @topic) OR c.ProductID = @product)
      ORDER BY p.Name, c.SortOrder, c.ClaimID
    `);
  return result.recordset;
}

function claimLines(claims) {
  if (claims.length === 0) {
    return '(none linked to this topic — make NO product or ingredient benefit claims; keep content educational and evidence-reporting only)';
  }
  return claims.map((row) => `${row.ClaimID} | ${row.ProductName || 'General'} | ${oneLine(row.ClaimText, 400)}${row.RequiresDisclaimer ? ' ‡' : ''}`).join('\n');
}

/** Evidence items for generation: the ones people marked, otherwise the topic's most relevant items. */
async function evidenceLines(pool, topicId) {
  if (!topicId) return '(none)';
  const result = await pool.request().input('topic', sql.Int, topicId).input('max', sql.Int, MAX_EVIDENCE_ITEMS).query(`
    SELECT TOP (@max) h.Title, h.Domain, h.AiSummary, h.StudyJson, COALESCE(h.PublishedAt, h.FetchedAt) AS ItemDate,
           COALESCE(s.Name, h.Domain) AS SourceName, ti.IsEvidence
    FROM dbo.MktTopicItem ti
    INNER JOIN dbo.MktHarvestedItem h ON h.ItemID = ti.ItemID
    LEFT JOIN dbo.MktSource s ON s.SourceID = h.SourceID
    WHERE ti.TopicID = @topic
    ORDER BY ti.IsEvidence DESC, h.RelevanceMax DESC, COALESCE(h.PublishedAt, h.FetchedAt) DESC
  `);
  if (result.recordset.length === 0) return '(none)';
  return result.recordset.map((row) => {
    const year = row.ItemDate ? new Date(row.ItemDate).getUTCFullYear() : 'undated';
    let study = '';
    try {
      const facts = JSON.parse(row.StudyJson || 'null');
      if (facts && typeof facts === 'object') {
        study = ` [study: ${['design', 'population', 'n', 'intervention', 'dose', 'duration', 'result']
          .filter((key) => facts[key] !== null && facts[key] !== undefined && facts[key] !== '')
          .map((key) => `${key} ${facts[key]}`).join('; ')}]`;
      }
    } catch {
      study = '';
    }
    return `- ${row.SourceName || row.Domain} (${year}): ${oneLine(row.Title, 200)} — ${oneLine(row.AiSummary || '', 300)}${study}`;
  }).join('\n');
}

function escapeRegex(value) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function flagTermHits(settings, text) {
  const haystack = String(text || '');
  return settingLines(settings, 'claims.flag_terms').filter((term) => new RegExp(`(^|[^a-z0-9])${escapeRegex(term)}(?=[^a-z0-9]|$)`, 'i').test(haystack));
}

function lengthCheck(asset, channel) {
  if (!channel || !channel.maxChars) return null;
  const hashtags = String(asset.Hashtags || '').trim();
  let text = `${asset.Body}${hashtags ? `\n${hashtags}` : ''}`;
  if (channel.key === 'x') text = text.replace(/\[LINK\]/g, 'x'.repeat(X_LINK_CHARS));
  return { chars: text.length, max: channel.maxChars };
}

/**
 * Claims review of any copy: deterministic flag terms + AI review against the approved claims. Each distinct flag
 * term costs copy.flagPenalty points (default 1) and always requires compliance review. Returns the stored check
 * result; callers add format issues and persist it.
 */
async function reviewCopy(pool, settings, copy, context, processLogId) {
  const response = await runPrompt(pool, settings, {
    promptKey: CHECK_PROMPT_KEY,
    operation: CHECK_PROMPT_KEY,
    processLogId,
    refType: copy.refType,
    refId: copy.refId,
    vars: {
      ...sharedVars(settings, context.audience),
      approved_claims: context.claimLines,
      channel: copy.channel,
      title: copy.title || '',
      subject: copy.subject || '',
      preview_text: copy.previewText || '',
      body: copy.body,
    },
  });
  const parsed = extractJson(response.text);
  if (!parsed || Array.isArray(parsed) || typeof parsed !== 'object' || !Number.isFinite(Number(parsed.score))) {
    throw new Error(`Claims check for ${copy.refType} ${copy.refId} returned no score (stop: ${response.stopReason}).`);
  }

  const hits = flagTermHits(settings, [copy.title, copy.subject, copy.previewText, copy.body].filter(Boolean).join('\n'));
  const statements = (Array.isArray(parsed.statements) ? parsed.statements : [])
    .filter((row) => row && STATEMENT_CLASSES.has(row.class))
    .map((row) => ({
      text: oneLine(row.text, 400),
      class: row.class,
      claim_id: Number(row.claim_id) || null,
      note: oneLine(row.note || '', 300),
    }));
  const aiScore = Math.min(10, Math.max(0, Number(parsed.score)));
  const flagPenalty = copy.flagPenalty ?? 1;
  const score = Math.max(0, Math.round((aiScore - hits.length * flagPenalty) * 10) / 10);
  const makesClaims = statements.some((row) => CLAIM_CLASSES.has(row.class));
  const needsCompliance = settings['review.compliance_mode'] === 'all'
    || hits.length > 0 || makesClaims || Boolean(parsed.references_efficacy) || Boolean(parsed.references_condition) || score < 10;
  return {
    result: {
      score,
      ai_score: aiScore,
      flag_terms: hits,
      flag_penalty: flagPenalty,
      statements,
      issues: (Array.isArray(parsed.issues) ? parsed.issues : []).map((issue) => oneLine(issue, 300)).filter(Boolean),
      length: null,
      references_efficacy: Boolean(parsed.references_efficacy),
      references_condition: Boolean(parsed.references_condition),
      disclaimer_ok: parsed.disclaimer_ok !== false,
      summary: oneLine(parsed.summary || '', 400),
      min_score: settingNumber(settings, 'claims.min_score', 7),
      model: response.model,
      prompt_version: response.promptVersion,
    },
    score,
    needsCompliance,
    costUsd: response.costUsd,
  };
}

/** Claims-check one asset. Writes the score only if the asset has not been edited meanwhile. */
async function checkAsset(pool, settings, asset, context, processLogId) {
  const channel = context.channels.get(asset.Channel);
  const review = await reviewCopy(pool, settings, {
    refType: 'asset',
    refId: asset.AssetID,
    channel: channel ? channel.label : asset.Channel,
    title: asset.Title,
    subject: asset.Subject,
    previewText: asset.PreviewText,
    body: asset.Body,
  }, context, processLogId);
  const { result, score, needsCompliance } = review;
  const length = lengthCheck(asset, channel);
  result.length = length;
  if (length && length.chars > length.max) result.issues.push(`Too long for ${channel.label}: ${length.chars} of ${length.max} characters.`);
  if (!/\[LINK\]/.test(asset.Body)) result.issues.push('The [LINK] placeholder is missing, so the tracked call-to-action link has nowhere to go.');

  await pool.request()
    .input('id', sql.Int, asset.AssetID)
    .input('version', sql.Int, asset.ContentVersion)
    .input('score', sql.Decimal(4, 1), score)
    .input('json', sql.NVarChar(sql.MAX), JSON.stringify(result))
    .input('needs', sql.Bit, needsCompliance ? 1 : 0)
    .query(`
      UPDATE dbo.MktAsset
      SET ClaimsScore = @score, ClaimsCheckJson = @json, ClaimsCheckedVersion = @version,
          ClaimsCheckedAt = SYSUTCDATETIME(), NeedsCompliance = @needs
      WHERE AssetID = @id AND ContentVersion = @version
    `);
  return { score, needsCompliance, costUsd: review.costUsd };
}

async function checkContext(pool, settings, campaign) {
  const claims = await approvedClaims(pool, campaign.TopicID);
  return {
    channels: loadChannels(settings),
    audience: campaign.Audience,
    claims,
    claimLines: claimLines(claims),
  };
}

/** Run fn over items with at most `limit` in flight. */
async function mapLimit(items, limit, fn) {
  const results = new Array(items.length);
  let next = 0;
  async function worker() {
    while (next < items.length) {
      const index = next;
      next += 1;
      results[index] = await fn(items[index], index);
    }
  }
  await Promise.all(Array.from({ length: Math.min(limit, items.length) }, worker));
  return results;
}

module.exports = {
  loadChannels,
  sharedVars,
  loadCampaign,
  approvedClaims,
  claimLines,
  evidenceLines,
  reviewCopy,
  checkAsset,
  checkContext,
  mapLimit,
};
