const { sql, connectPool, getProductionDatabase } = require('../db-config');
const { loadSettings, settingLines } = require('../mkt/settings');
const { runPrompt, extractJson } = require('../mkt/ai');
const { oneLine } = require('../mkt/taxonomy');
const {
  sharedVars, approvedClaims, claimLines, evidenceLines, reviewCopy,
} = require('../mkt/campaign');

const BRIEF_PROMPT_KEY = 'content.brief';
const DRAFT_PROMPT_KEY = 'content.draft';
const REVISE_PROMPT_KEY = 'content.revise';
const CHECK_CHANNEL = 'Web article (long-form page on our site)';
const BRIEF_STAGES = new Set(['idea', 'brief']);
const META_TITLE_MAX = 60;
const META_DESCRIPTION_MAX = 155;
// Long-form evidence pieces name conditions and drug uses in context; flag terms send them to compliance without
// deducting points, so the score reflects the AI claims review.
const LONG_FORM_FLAG_PENALTY = 0;

function userIdFrom(params) {
  return Number(params.triggered_by_user_id || params.user_id || 0) || null;
}

function contentTypes(settings) {
  const types = new Map();
  for (const line of settingLines(settings, 'content.types')) {
    const [key, label, words, guidance] = line.split('|').map((part) => (part || '').trim());
    if (key) types.set(key, { key, label: label || key, words: Number(words) || 1200, guidance: guidance || '' });
  }
  return types;
}

async function loadContent(pool, contentId) {
  const result = await pool.request().input('id', sql.Int, contentId).query(`
    SELECT c.*, t.Title AS TopicTitle, t.Summary AS TopicSummary, t.WhyItMatters AS TopicWhy, t.Angle AS TopicAngle,
           t.AvoidNotes AS TopicAvoid, t.Status AS TopicStatus,
           p.Name AS ProductName, p.Headline AS ProductHeadline, p.Summary AS ProductSummary, p.Formula AS ProductFormula,
           p.SuggestedUse AS ProductSuggestedUse
    FROM dbo.MktContent c
    LEFT JOIN dbo.MktTopic t ON t.TopicID = c.TopicID
    LEFT JOIN dbo.MktProduct p ON p.ProductID = c.ProductID
    WHERE c.ContentID = @id
  `);
  return result.recordset[0] || null;
}

async function loadVersion(pool, versionId) {
  if (!versionId) return null;
  const result = await pool.request().input('id', sql.Int, versionId).query('SELECT * FROM dbo.MktContentVersion WHERE VersionID = @id');
  return result.recordset[0] || null;
}

async function contentContext(pool, settings, content) {
  const claims = await approvedClaims(pool, content.TopicID, content.ProductID);
  const types = contentTypes(settings);
  const type = types.get(content.ContentType) || { key: content.ContentType, label: content.ContentType, words: 1200, guidance: '' };
  return {
    audience: content.Audience,
    claims,
    claimLines: claimLines(claims),
    type,
    targetWords: Number(content.TargetWords) || type.words,
  };
}

function productText(content) {
  if (!content.ProductID) return '(none — educational piece; no product benefit claims beyond the approved list)';
  return [
    content.ProductName,
    content.ProductHeadline ? `Headline: ${oneLine(content.ProductHeadline, 300)}` : null,
    content.ProductSummary ? `Summary: ${oneLine(content.ProductSummary, 600)}` : null,
    content.ProductFormula ? `Formula: ${oneLine(content.ProductFormula, 600)}` : null,
    content.ProductSuggestedUse ? `Suggested use: ${oneLine(content.ProductSuggestedUse, 300)}` : null,
  ].filter(Boolean).join('\n');
}

function listLines(value, format = (entry) => oneLine(entry, 300)) {
  return (Array.isArray(value) ? value : []).map(format).filter(Boolean);
}

/** Editable Markdown rendering of the brief JSON — this text, not the JSON, drives the draft. */
function briefMarkdown(brief) {
  const sections = [];
  const add = (heading, lines) => {
    if (lines.length) sections.push(`## ${heading}\n${lines.join('\n')}`);
  };
  add('Working title', brief.working_title ? [oneLine(brief.working_title, 200)] : []);
  add('Search intent', brief.search_intent ? [oneLine(brief.search_intent, 500)] : []);
  add('Reader', brief.reader ? [oneLine(brief.reader, 500)] : []);
  add('Angle', brief.angle ? [oneLine(brief.angle, 600)] : []);
  add('Key takeaways', listLines(brief.key_takeaways).map((line) => `- ${line}`));
  add('Outline', (Array.isArray(brief.outline) ? brief.outline : []).filter((row) => row && row.heading).map((row) => [
    `### ${oneLine(row.heading, 200)}`,
    ...listLines(row.points).map((point) => `- ${point}`),
  ].join('\n')));
  add('Evidence to cite', listLines(brief.evidence_to_cite, (entry) => oneLine(entry, 400)).map((line) => `- ${line}`));
  add('Approved claims to use (ids)', listLines(brief.claim_ids, (entry) => String(Number(entry) || '')).length
    ? [listLines(brief.claim_ids, (entry) => String(Number(entry) || '')).join(', ')] : []);
  add('FAQs', (Array.isArray(brief.faqs) ? brief.faqs : []).filter((row) => row && row.question)
    .map((row) => `- **${oneLine(row.question, 200)}** ${oneLine(row.answer_notes || '', 300)}`.trim()));
  add('Internal links', listLines(brief.internal_links).map((line) => `- ${line}`));
  add('Call to action', brief.cta ? [oneLine(brief.cta, 300)] : []);
  add('Meta', [
    brief.meta_title ? `- Title: ${oneLine(brief.meta_title, 120)}` : null,
    brief.meta_description ? `- Description: ${oneLine(brief.meta_description, 300)}` : null,
  ].filter(Boolean));
  add('Avoid', listLines(brief.avoid).map((line) => `- ${line}`));
  return sections.join('\n\n');
}

/** Parse the plain-text draft format: header lines, a --- line, then the Markdown body. */
function parseArticle(text) {
  const cleaned = String(text || '').replace(/^\s*```[a-z]*\s*\n/i, '').replace(/\n```\s*$/, '');
  const lines = cleaned.split(/\r?\n/);
  const divider = lines.findIndex((line) => line.trim() === '---');
  if (divider < 0) return null;
  const header = {};
  for (const line of lines.slice(0, divider)) {
    const match = line.match(/^\s*([A-Z_]+)\s*:\s*(.*)$/);
    if (match) header[match[1]] = match[2].trim();
  }
  const body = lines.slice(divider + 1).join('\n').trim();
  if (!body || !header.TITLE) return null;
  const claimIds = String(header.CLAIM_IDS || '').split(/[\s,]+/).map(Number).filter((id) => Number.isInteger(id) && id > 0);
  return {
    title: oneLine(header.TITLE.replace(/^#+\s*/, ''), 300),
    metaTitle: header.META_TITLE ? oneLine(header.META_TITLE, 200) : null,
    metaDescription: header.META_DESCRIPTION ? oneLine(header.META_DESCRIPTION, 400) : null,
    claimIds,
    body,
  };
}

function wordCount(markdown) {
  const text = String(markdown || '').replace(/[#*_>`\-[\]()]/g, ' ');
  const words = text.match(/[A-Za-z0-9][A-Za-z0-9'’.%-]*/g);
  return words ? words.length : 0;
}

async function insertVersion(pool, content, article, context, source, note, processLogId, userId) {
  const allowed = new Set(context.claims.map((row) => row.ClaimID));
  const claimIds = [...new Set(article.claimIds.filter((id) => allowed.has(id)))];
  const result = await pool.request()
    .input('content', sql.Int, content.ContentID)
    .input('title', sql.NVarChar(300), article.title)
    .input('meta_title', sql.NVarChar(200), article.metaTitle)
    .input('meta_description', sql.NVarChar(400), article.metaDescription)
    .input('body', sql.NVarChar(sql.MAX), article.body)
    .input('words', sql.Int, wordCount(article.body))
    .input('source', sql.NVarChar(20), source)
    .input('note', sql.NVarChar(500), note ? oneLine(note, 500) : null)
    .input('claims', sql.NVarChar(500), claimIds.length ? JSON.stringify(claimIds) : null)
    .input('log_id', sql.Int, processLogId)
    .input('user', sql.Int, userId)
    .query(`
      DECLARE @next INT = (SELECT ISNULL(MAX(VersionNo), 0) + 1 FROM dbo.MktContentVersion WITH (UPDLOCK, HOLDLOCK) WHERE ContentID = @content);
      INSERT INTO dbo.MktContentVersion (ContentID, VersionNo, Title, MetaTitle, MetaDescription, Body, WordCount, Source, Note,
        ClaimIdsJson, GenerationLogID, CreatedBy)
      OUTPUT INSERTED.VersionID, INSERTED.VersionNo
      VALUES (@content, @next, @title, @meta_title, @meta_description, @body, @words, @source, @note, @claims, @log_id, @user);
    `);
  const { VersionID: versionId, VersionNo: versionNo } = result.recordset[0];
  await pool.request()
    .input('id', sql.Int, content.ContentID)
    .input('version', sql.Int, versionId)
    .input('user', sql.Int, userId)
    .query(`
      UPDATE dbo.MktContent
      SET CurrentVersionID = @version, Stage = N'draft', SubmittedVersionID = NULL, SubmittedBy = NULL, SubmittedAt = NULL,
          ComplianceStatus = CASE WHEN ComplianceStatus = N'changes_requested' THEN ComplianceStatus ELSE NULL END,
          ComplianceBy = CASE WHEN ComplianceStatus = N'changes_requested' THEN ComplianceBy ELSE NULL END,
          ComplianceAt = CASE WHEN ComplianceStatus = N'changes_requested' THEN ComplianceAt ELSE NULL END,
          EditorialStatus = CASE WHEN EditorialStatus = N'changes_requested' THEN EditorialStatus ELSE NULL END,
          EditorialBy = CASE WHEN EditorialStatus = N'changes_requested' THEN EditorialBy ELSE NULL END,
          EditorialAt = CASE WHEN EditorialStatus = N'changes_requested' THEN EditorialAt ELSE NULL END,
          UpdatedBy = @user, UpdatedAt = SYSUTCDATETIME()
      WHERE ContentID = @id
    `);
  return { versionId, versionNo };
}

/** Claims-check one version of a piece and store the result on the version row. */
async function checkVersion(pool, settings, content, version, context, processLogId) {
  const review = await reviewCopy(pool, settings, {
    refType: 'content',
    refId: content.ContentID,
    channel: CHECK_CHANNEL,
    flagPenalty: LONG_FORM_FLAG_PENALTY,
    title: version.Title,
    subject: version.MetaTitle ? `Meta title: ${version.MetaTitle}` : '',
    previewText: version.MetaDescription ? `Meta description: ${version.MetaDescription}` : '',
    body: version.Body,
  }, context, processLogId);
  const { result, score, needsCompliance } = review;
  const words = Number(version.WordCount) || wordCount(version.Body);
  result.length = { words, target: context.targetWords };
  if (!version.MetaTitle) result.issues.push('Meta title is missing.');
  else if (version.MetaTitle.length > META_TITLE_MAX) result.issues.push(`Meta title is ${version.MetaTitle.length} characters; keep it to ${META_TITLE_MAX}.`);
  if (!version.MetaDescription) result.issues.push('Meta description is missing.');
  else if (version.MetaDescription.length > META_DESCRIPTION_MAX) {
    result.issues.push(`Meta description is ${version.MetaDescription.length} characters; keep it to ${META_DESCRIPTION_MAX}.`);
  }
  if (words < context.targetWords * 0.6) result.issues.push(`Only ${words} words against a target of about ${context.targetWords}.`);
  if (/^#\s/m.test(version.Body)) result.issues.push('The body contains an H1 (#) heading; the page title is the only H1.');
  if (/https?:\/\//i.test(version.Body)) result.issues.push('The body contains a URL; confirm every link is real before publishing.');

  await pool.request()
    .input('id', sql.Int, version.VersionID)
    .input('score', sql.Decimal(4, 1), score)
    .input('json', sql.NVarChar(sql.MAX), JSON.stringify(result))
    .input('needs', sql.Bit, needsCompliance ? 1 : 0)
    .query(`
      UPDATE dbo.MktContentVersion
      SET ClaimsScore = @score, ClaimsCheckJson = @json, NeedsCompliance = @needs, ClaimsCheckedAt = SYSUTCDATETIME()
      WHERE VersionID = @id
    `);
  return { score, needsCompliance, costUsd: review.costUsd, issues: result.issues.length };
}

async function logReview(pool, contentId, versionId, decision, note, userId) {
  await pool.request()
    .input('content', sql.Int, contentId)
    .input('version', sql.Int, versionId)
    .input('decision', sql.NVarChar(30), decision)
    .input('note', sql.NVarChar(2000), note ? String(note).slice(0, 2000) : null)
    .input('user', sql.Int, userId)
    .query(`INSERT INTO dbo.MktContentReview (ContentID, VersionID, Gate, Decision, Note, UserID)
            VALUES (@content, @version, N'system', @decision, @note, @user)`);
}

function topicGuard(content) {
  if (content.TopicID && content.TopicStatus !== 'accepted') {
    return 'The linked topic is no longer accepted — accept it on the Topic Board first.';
  }
  return null;
}

function roundCost(value) {
  return Math.round(value * 10000) / 10000;
}

/** AI brief from the topic, keyword, evidence, product and approved claims. Replaces any unapproved brief. */
async function brief(params = {}) {
  const contentId = Number(params.content_id || 0);
  if (!contentId) return { ok: false, error: 'content_id is required.' };
  const processLogId = Number(params.log_id || 0) || null;
  const userId = userIdFrom(params);
  const pool = await connectPool(getProductionDatabase());

  try {
    const settings = await loadSettings(pool);
    const content = await loadContent(pool, contentId);
    if (!content) return { ok: false, error: `Content ${contentId} not found.` };
    if (!BRIEF_STAGES.has(content.Stage) || content.BriefApprovedAt) {
      return { ok: false, error: 'The brief can only be generated before it is approved (stage idea or brief).' };
    }
    const guard = topicGuard(content);
    if (guard) return { ok: false, error: guard };
    const context = await contentContext(pool, settings, content);

    const response = await runPrompt(pool, settings, {
      promptKey: BRIEF_PROMPT_KEY,
      operation: BRIEF_PROMPT_KEY,
      processLogId,
      refType: 'content',
      refId: contentId,
      vars: {
        ...sharedVars(settings, content.Audience),
        approved_claims: context.claimLines,
        content_type: context.type.label,
        type_guidance: context.type.guidance,
        target_words: context.targetWords,
        primary_keyword: content.PrimaryKeyword || '(none set — choose the natural search phrase for this topic)',
        secondary_keywords: content.SecondaryKeywords || '(none)',
        topic_title: content.TopicTitle || content.Title,
        topic_summary: content.TopicSummary || '',
        topic_why: content.TopicWhy || '',
        angle: content.TopicAngle || '(choose the most useful angle for the reader)',
        avoid: content.TopicAvoid || '(nothing beyond the compliance rules)',
        product: productText(content),
        notes: content.Notes || '(none)',
        evidence: await evidenceLines(pool, content.TopicID),
      },
    });
    const parsed = extractJson(response.text);
    if (!parsed || Array.isArray(parsed) || typeof parsed !== 'object' || !Array.isArray(parsed.outline)) {
      throw new Error(`Brief came back without an outline (stop: ${response.stopReason}).`);
    }
    const allowed = new Set(context.claims.map((row) => row.ClaimID));
    parsed.claim_ids = listLines(parsed.claim_ids, (entry) => Number(entry)).filter((id) => allowed.has(id));
    const markdown = briefMarkdown(parsed);

    await pool.request()
      .input('id', sql.Int, contentId)
      .input('json', sql.NVarChar(sql.MAX), JSON.stringify(parsed))
      .input('text', sql.NVarChar(sql.MAX), markdown)
      .input('log_id', sql.Int, processLogId)
      .input('user', sql.Int, userId)
      .query(`
        UPDATE dbo.MktContent
        SET BriefJson = @json, BriefText = @text, BriefBy = @user, BriefAt = SYSUTCDATETIME(), BriefLogID = @log_id,
            Stage = N'brief', UpdatedBy = @user, UpdatedAt = SYSUTCDATETIME()
        WHERE ContentID = @id
      `);
    await logReview(pool, contentId, null, 'ai_brief', `Brief generated (prompt v${response.promptVersion})`, userId);
    return {
      ok: true,
      error: null,
      content_id: contentId,
      sections: Array.isArray(parsed.outline) ? parsed.outline.length : 0,
      cost_usd: roundCost(response.costUsd),
      truncated: response.stopReason === 'max_tokens',
    };
  } finally {
    await pool.close();
  }
}

/** AI draft from the approved brief as a new version, then claims-checked. */
async function draft(params = {}) {
  const contentId = Number(params.content_id || 0);
  if (!contentId) return { ok: false, error: 'content_id is required.' };
  const processLogId = Number(params.log_id || 0) || null;
  const userId = userIdFrom(params);
  const pool = await connectPool(getProductionDatabase());

  try {
    const settings = await loadSettings(pool);
    const content = await loadContent(pool, contentId);
    if (!content) return { ok: false, error: `Content ${contentId} not found.` };
    if (!content.BriefApprovedAt || !String(content.BriefText || '').trim()) {
      return { ok: false, error: 'Approve the brief before drafting.' };
    }
    if (content.Stage !== 'brief' && content.Stage !== 'draft') {
      return { ok: false, error: 'A new AI draft is only available at the brief or draft stage.' };
    }
    const guard = topicGuard(content);
    if (guard) return { ok: false, error: guard };
    const context = await contentContext(pool, settings, content);

    const response = await runPrompt(pool, settings, {
      promptKey: DRAFT_PROMPT_KEY,
      operation: DRAFT_PROMPT_KEY,
      processLogId,
      refType: 'content',
      refId: contentId,
      vars: {
        ...sharedVars(settings, content.Audience),
        approved_claims: context.claimLines,
        content_type: context.type.label,
        target_words: context.targetWords,
        brief: content.BriefText,
        evidence: await evidenceLines(pool, content.TopicID),
      },
    });
    const article = parseArticle(response.text);
    if (!article) throw new Error(`Draft did not follow the TITLE / --- format (stop: ${response.stopReason}).`);
    const truncated = response.stopReason === 'max_tokens';
    const { versionId, versionNo } = await insertVersion(pool, content, article, context, 'ai_draft',
      truncated ? 'AI draft (reply hit the token limit — the ending may be cut off)' : 'AI draft from the approved brief', processLogId, userId);
    await logReview(pool, contentId, versionId, 'ai_drafted', `Version ${versionNo} drafted (prompt v${response.promptVersion})`, userId);

    const check = await checkVersion(pool, settings, content, await loadVersion(pool, versionId), context, processLogId);
    return {
      ok: true,
      error: null,
      content_id: contentId,
      version_no: versionNo,
      words: wordCount(article.body),
      score: check.score,
      needs_compliance: check.needsCompliance,
      cost_usd: roundCost(response.costUsd + check.costUsd),
      truncated,
    };
  } finally {
    await pool.close();
  }
}

/** Re-run the claims check on the current version. */
async function check(params = {}) {
  const contentId = Number(params.content_id || 0);
  if (!contentId) return { ok: false, error: 'content_id is required.' };
  const processLogId = Number(params.log_id || 0) || null;
  const pool = await connectPool(getProductionDatabase());

  try {
    const settings = await loadSettings(pool);
    const content = await loadContent(pool, contentId);
    if (!content) return { ok: false, error: `Content ${contentId} not found.` };
    const version = await loadVersion(pool, content.CurrentVersionID);
    if (!version) return { ok: false, error: 'There is no draft to check yet.' };
    const context = await contentContext(pool, settings, content);
    const result = await checkVersion(pool, settings, content, version, context, processLogId);
    return {
      ok: true,
      error: null,
      content_id: contentId,
      version_no: version.VersionNo,
      score: result.score,
      needs_compliance: result.needsCompliance,
      cost_usd: roundCost(result.costUsd),
    };
  } finally {
    await pool.close();
  }
}

function findingsText(version) {
  try {
    const result = JSON.parse(version.ClaimsCheckJson || 'null');
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

async function latestChangeRequest(pool, contentId) {
  const result = await pool.request().input('id', sql.Int, contentId).query(`
    SELECT TOP (1) Gate, Note FROM dbo.MktContentReview
    WHERE ContentID = @id AND Decision = N'changes_requested'
    ORDER BY ReviewID DESC
  `);
  const row = result.recordset[0];
  return row && row.Note ? `${row.Gate} reviewer: ${row.Note}` : null;
}

/** AI revision of the current version from an instruction and its findings; saved as a new version and re-checked. */
async function revise(params = {}) {
  const contentId = Number(params.content_id || 0);
  if (!contentId) return { ok: false, error: 'content_id is required.' };
  const instruction = String(params.instruction || '').trim().slice(0, 2000);
  const processLogId = Number(params.log_id || 0) || null;
  const userId = userIdFrom(params);
  const pool = await connectPool(getProductionDatabase());

  try {
    const settings = await loadSettings(pool);
    const content = await loadContent(pool, contentId);
    if (!content) return { ok: false, error: `Content ${contentId} not found.` };
    if (content.Stage !== 'draft') return { ok: false, error: 'Only a piece at the draft stage can be revised.' };
    const version = await loadVersion(pool, content.CurrentVersionID);
    if (!version) return { ok: false, error: 'There is no draft to revise yet.' };
    const context = await contentContext(pool, settings, content);
    const reviewerNote = await latestChangeRequest(pool, contentId);
    const findings = [findingsText(version), reviewerNote ? `- ${reviewerNote}` : null].filter(Boolean).join('\n');

    const response = await runPrompt(pool, settings, {
      promptKey: REVISE_PROMPT_KEY,
      operation: REVISE_PROMPT_KEY,
      processLogId,
      refType: 'content',
      refId: contentId,
      vars: {
        ...sharedVars(settings, content.Audience),
        approved_claims: context.claimLines,
        instruction: instruction || 'Fix every claims-check finding and reviewer note while keeping the piece.',
        findings,
        title: version.Title,
        meta_title: version.MetaTitle || '',
        meta_description: version.MetaDescription || '',
        body: version.Body,
      },
    });
    const article = parseArticle(response.text);
    if (!article) throw new Error(`Revision did not follow the TITLE / --- format (stop: ${response.stopReason}).`);
    if (response.stopReason === 'max_tokens') {
      throw new Error('Revision hit the token limit before finishing; no new version was saved.');
    }
    const note = instruction ? `AI revision: ${instruction}` : 'AI revision to fix findings';
    const { versionId, versionNo } = await insertVersion(pool, content, article, context, 'ai_revision', note, processLogId, userId);
    await logReview(pool, contentId, versionId, 'ai_revised', `Version ${versionNo}: ${note}`, userId);

    const result = await checkVersion(pool, settings, content, await loadVersion(pool, versionId), context, processLogId);
    return {
      ok: true,
      error: null,
      content_id: contentId,
      version_no: versionNo,
      score: result.score,
      needs_compliance: result.needsCompliance,
      cost_usd: roundCost(response.costUsd + result.costUsd),
    };
  } finally {
    await pool.close();
  }
}

module.exports = {
  brief, draft, check, revise, parseArticle, briefMarkdown, wordCount,
};
