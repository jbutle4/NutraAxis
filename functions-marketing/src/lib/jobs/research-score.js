const { sql, connectPool, getProductionDatabase } = require('../db-config');
const { loadSettings, settingNumber, settingLines, recordUsage } = require('../mkt/settings');
const {
  loadPrompt, render, assertBudget, estimateCost, extractJson,
  submitAnthropicBatch, getAnthropicBatch, fetchAnthropicBatchResults,
} = require('../mkt/ai');
const { loadTaxonomy, matchVocabulary } = require('../mkt/taxonomy');

const PROMPT_KEY = 'research.score_item';
const PURPOSE = 'score_items';
const EVIDENCE_TYPES = new Set(['peer_reviewed', 'regulatory', 'news', 'competitor', 'opinion']);
const STALE_WAIT_HOURS = 12;
const BATCH_GIVE_UP_HOURS = 30;
const ID_CHUNK = 500;
// Anthropic bills cache writes at 1.25x and cache reads at 0.1x the base input rate.
const CACHE_WRITE_FACTOR = 1.25;
const CACHE_READ_FACTOR = 0.1;

function customId(itemId) {
  return `item-${itemId}`;
}

function itemIdFromCustomId(value) {
  const match = /^item-(\d+)$/.exec(String(value || ''));
  return match ? Number(match[1]) : null;
}

async function forChunks(ids, fn) {
  for (let i = 0; i < ids.length; i += ID_CHUNK) {
    const chunk = ids.slice(i, i + ID_CHUNK).map(Number).filter(Number.isInteger);
    if (chunk.length) await fn(chunk.join(','));
  }
}

async function releaseItems(pool, batchId, itemIds = null) {
  if (itemIds === null) {
    await pool.request().input('batch', sql.Int, batchId).query(`
      UPDATE dbo.MktHarvestedItem SET ScoreBatchID = NULL WHERE ScoreBatchID = @batch AND Status = N'new'
    `);
    return;
  }
  await forChunks(itemIds, (list) => pool.request().input('batch', sql.Int, batchId).query(`
    UPDATE dbo.MktHarvestedItem SET ScoreBatchID = NULL
    WHERE ScoreBatchID = @batch AND Status = N'new' AND ItemID IN (${list})
  `));
}

function itemText(row, maxChars) {
  const parts = [row.Summary, row.BodyText].map((part) => String(part || '').replace(/\s+/g, ' ').trim()).filter(Boolean);
  let text = parts[0] || '';
  if (parts[1] && !text.includes(parts[1].slice(0, 80))) text = `${text}\n${parts[1]}`.trim();
  return text.slice(0, maxChars) || '(no text — score from the title)';
}

function clamp01(value) {
  const number = Number(value);
  if (!Number.isFinite(number)) return null;
  return Math.round(Math.min(1, Math.max(0, number)) * 1000) / 1000;
}

/**
 * Validate one model reply against the taxonomy and write it: per-interest scores, tags, study facts,
 * and scored/discarded status. Items people already actioned (no longer "new") are left alone.
 */
async function applyScore(pool, itemId, parsed, taxonomy, threshold) {
  const scores = [];
  for (const [key, value] of Object.entries(parsed.scores || {})) {
    const interestId = Number(key);
    const relevance = clamp01(value);
    if (taxonomy.interestIds.has(interestId) && relevance !== null && relevance > 0) scores.push([interestId, relevance]);
  }
  scores.sort((a, b) => b[1] - a[1]);
  const max = scores.length ? scores[0][1] : 0;
  const evidenceType = EVIDENCE_TYPES.has(parsed.evidence_type) ? parsed.evidence_type : null;
  const areas = matchVocabulary(parsed.therapeutic_areas, taxonomy.areas);
  const products = matchVocabulary(parsed.products, taxonomy.products.map((row) => row.Name));
  const keywords = (Array.isArray(parsed.keywords) ? parsed.keywords : [])
    .map((keyword) => String(keyword || '').trim()).filter(Boolean).slice(0, 5);
  const study = evidenceType === 'peer_reviewed' && parsed.study && typeof parsed.study === 'object' ? parsed.study : null;
  const status = max >= threshold ? 'scored' : 'discarded';

  const request = pool.request()
    .input('item', sql.BigInt, itemId)
    .input('max', sql.Decimal(4, 3), max)
    .input('primary', sql.Int, scores.length ? scores[0][0] : null)
    .input('area', sql.NVarChar(100), areas[0] || null)
    .input('evidence', sql.NVarChar(30), evidenceType)
    .input('summary', sql.NVarChar(1000), parsed.summary ? String(parsed.summary).slice(0, 1000) : null)
    .input('tags', sql.NVarChar(sql.MAX), JSON.stringify({ areas, products, keywords }))
    .input('study', sql.NVarChar(sql.MAX), study ? JSON.stringify(study) : null)
    .input('status', sql.NVarChar(20), status);
  const values = scores.map(([interestId, relevance]) => `(@item, ${interestId}, ${relevance})`).join(', ');
  const result = await request.query(`
    SET XACT_ABORT ON;
    BEGIN TRANSACTION;
    UPDATE dbo.MktHarvestedItem
    SET RelevanceMax = @max, PrimaryInterestID = @primary, TherapeuticArea = @area, EvidenceType = @evidence,
        AiSummary = @summary, TagsJson = @tags, StudyJson = @study, ScoredAt = SYSUTCDATETIME(), Status = @status
    WHERE ItemID = @item AND Status = N'new';
    IF @@ROWCOUNT = 1
    BEGIN
      DELETE FROM dbo.MktItemScore WHERE ItemID = @item;
      ${values ? `INSERT INTO dbo.MktItemScore (ItemID, InterestID, Relevance) VALUES ${values};` : ''}
    END
    COMMIT TRANSACTION;
  `);
  return result.rowsAffected[0] === 1 ? status : 'skipped';
}

async function collectBatch(pool, settings, batch, taxonomy, processLogId) {
  const counts = { scored: 0, discarded: 0, errored: 0, unparsed: 0 };
  const status = await getAnthropicBatch(batch.ExternalBatchID);
  if (status.processing_status !== 'ended') {
    const ageHours = (Date.now() - new Date(batch.SubmittedAt).getTime()) / 3600000;
    if (ageHours > BATCH_GIVE_UP_HOURS) {
      await releaseItems(pool, batch.BatchID);
      await pool.request().input('id', sql.Int, batch.BatchID).input('ext', sql.NVarChar(30), status.processing_status).query(`
        UPDATE dbo.MktAiBatch SET Status = N'failed', ExternalStatus = @ext, CollectedAt = SYSUTCDATETIME(),
          ErrorMessage = N'Batch did not finish in time; items returned to the queue.'
        WHERE BatchID = @id
      `);
      return { ...counts, ended: true, costUsd: 0 };
    }
    await pool.request().input('id', sql.Int, batch.BatchID).input('ext', sql.NVarChar(30), status.processing_status)
      .query('UPDATE dbo.MktAiBatch SET ExternalStatus = @ext WHERE BatchID = @id');
    return { ...counts, ended: false, costUsd: 0 };
  }

  const threshold = settingNumber(settings, 'research.relevance_threshold', 0.6);
  const results = await fetchAnthropicBatchResults(status.results_url);
  const tokens = { input: 0, output: 0, billedInput: 0 };
  const retry = [];
  let firstError = null;

  for (const line of results) {
    const itemId = itemIdFromCustomId(line.custom_id);
    if (!itemId) continue;
    if (line.result?.type !== 'succeeded') {
      counts.errored += 1;
      retry.push(itemId);
      firstError = firstError || line.result?.error?.error?.message || line.result?.type || 'errored';
      continue;
    }
    const message = line.result.message || {};
    const usage = message.usage || {};
    const cacheWrite = Number(usage.cache_creation_input_tokens || 0);
    const cacheRead = Number(usage.cache_read_input_tokens || 0);
    tokens.input += Number(usage.input_tokens || 0) + cacheWrite + cacheRead;
    tokens.billedInput += Number(usage.input_tokens || 0) + cacheWrite * CACHE_WRITE_FACTOR + cacheRead * CACHE_READ_FACTOR;
    tokens.output += Number(usage.output_tokens || 0);

    const text = (message.content || []).filter((block) => block.type === 'text').map((block) => block.text).join('');
    const parsed = extractJson(text);
    if (!parsed || Array.isArray(parsed) || typeof parsed !== 'object') {
      counts.unparsed += 1;
      retry.push(itemId);
      continue;
    }
    const outcome = await applyScore(pool, itemId, parsed, taxonomy, threshold);
    if (outcome === 'scored') counts.scored += 1;
    else if (outcome === 'discarded') counts.discarded += 1;
  }

  await releaseItems(pool, batch.BatchID, retry);
  const costUsd = estimateCost(settings, batch.Model, Math.round(tokens.billedInput), tokens.output, 0, true);
  await recordUsage(pool, {
    provider: 'anthropic',
    operation: PROMPT_KEY,
    mode: 'batch',
    promptKey: PROMPT_KEY,
    promptVersion: batch.PromptVersion,
    model: batch.Model,
    inputTokens: tokens.input,
    outputTokens: tokens.output,
    units: counts.scored + counts.discarded,
    costUsd,
    processLogId,
    refType: 'ai_batch',
    refId: batch.BatchID,
  });
  await pool.request()
    .input('id', sql.Int, batch.BatchID)
    .input('ext', sql.NVarChar(30), status.processing_status)
    .input('ok', sql.Int, counts.scored + counts.discarded)
    .input('err', sql.Int, counts.errored + counts.unparsed)
    .input('in', sql.BigInt, tokens.input)
    .input('out', sql.BigInt, tokens.output)
    .input('cost', sql.Decimal(12, 6), costUsd)
    .input('error', sql.NVarChar(1000), firstError ? String(firstError).slice(0, 1000) : null)
    .query(`
      UPDATE dbo.MktAiBatch
      SET Status = N'collected', ExternalStatus = @ext, SucceededCount = @ok, ErroredCount = @err,
          InputTokens = @in, OutputTokens = @out, CostUsd = @cost, ErrorMessage = @error, CollectedAt = SYSUTCDATETIME()
      WHERE BatchID = @id
    `);
  return { ...counts, ended: true, costUsd };
}

async function pendingItems(pool) {
  const result = await pool.request().query(`
    SELECT COUNT(*) AS Waiting, MIN(FetchedAt) AS Oldest
    FROM dbo.MktHarvestedItem
    WHERE Status = N'new' AND ScoreBatchID IS NULL
  `);
  return result.recordset[0];
}

async function submitBatch(pool, settings, taxonomy, processLogId, maxItemsOverride = null) {
  await assertBudget(pool, settings);
  const prompt = await loadPrompt(pool, PROMPT_KEY);
  if (prompt.Provider !== 'anthropic') {
    throw new Error(`${PROMPT_KEY} must use the anthropic provider (batch scoring runs on the Message Batches API).`);
  }
  const model = prompt.Model || settings['ai.anthropic.model'] || 'claude-haiku-4-5';
  const maxItems = maxItemsOverride || settingNumber(settings, 'research.score_batch_max_items', 1000);
  const maxChars = settingNumber(settings, 'research.score_text_chars', 1800);

  const items = (await pool.request().input('max', sql.Int, maxItems).query(`
    SELECT TOP (@max) h.ItemID, h.Title, h.Url, h.Summary, LEFT(h.BodyText, 6000) AS BodyText, h.SourceType,
           h.PublishedAt, COALESCE(s.Name, h.Domain) AS SourceName
    FROM dbo.MktHarvestedItem h
    LEFT JOIN dbo.MktSource s ON s.SourceID = h.SourceID
    WHERE h.Status = N'new' AND h.ScoreBatchID IS NULL
    ORDER BY h.FetchedAt DESC
  `)).recordset;
  if (items.length === 0) return null;

  const system = render(prompt.SystemPrompt, {
    brand_name: settings['brand.name'] || 'NutraAxis',
    interests: taxonomy.interestLines,
    therapeutic_areas: taxonomy.areas.join('\n'),
    products: taxonomy.productLines,
    competitors: settingLines(settings, 'brand.competitors').join(', ') || '(none listed)',
  });
  const requests = items.map((row) => {
    const params = {
      model,
      max_tokens: Number(prompt.MaxTokens || 700),
      system: [{ type: 'text', text: system, cache_control: { type: 'ephemeral' } }],
      messages: [{
        role: 'user',
        content: render(prompt.UserTemplate, {
          source: row.SourceName || 'unknown',
          source_type: row.SourceType,
          published: row.PublishedAt ? new Date(row.PublishedAt).toISOString().slice(0, 10) : 'unknown',
          title: row.Title,
          url: row.Url,
          text: itemText(row, maxChars),
        }),
      }],
    };
    if (prompt.Temperature !== null && prompt.Temperature !== undefined) params.temperature = Number(prompt.Temperature);
    return { custom_id: customId(row.ItemID), params };
  });

  const inserted = await pool.request()
    .input('prompt_version', sql.Int, prompt.Version)
    .input('model', sql.NVarChar(100), model)
    .input('count', sql.Int, items.length)
    .input('log_id', sql.Int, processLogId)
    .query(`
      INSERT INTO dbo.MktAiBatch (Provider, Purpose, PromptKey, PromptVersion, Model, ItemCount, ProcessLogID)
      OUTPUT INSERTED.BatchID
      VALUES (N'anthropic', N'${PURPOSE}', N'${PROMPT_KEY}', @prompt_version, @model, @count, @log_id)
    `);
  const batchId = inserted.recordset[0].BatchID;
  const itemIds = items.map((row) => row.ItemID);
  await forChunks(itemIds, (list) => pool.request().input('batch', sql.Int, batchId).query(`
    UPDATE dbo.MktHarvestedItem SET ScoreBatchID = @batch WHERE ItemID IN (${list}) AND Status = N'new'
  `));

  try {
    const response = await submitAnthropicBatch(requests);
    await pool.request()
      .input('id', sql.Int, batchId)
      .input('ext_id', sql.NVarChar(100), response.id)
      .input('ext', sql.NVarChar(30), response.processing_status || null)
      .query('UPDATE dbo.MktAiBatch SET ExternalBatchID = @ext_id, ExternalStatus = @ext WHERE BatchID = @id');
    return { batchId, items: items.length, model };
  } catch (error) {
    await releaseItems(pool, batchId);
    await pool.request()
      .input('id', sql.Int, batchId)
      .input('error', sql.NVarChar(1000), String(error.message).slice(0, 1000))
      .query(`UPDATE dbo.MktAiBatch SET Status = N'failed', ErrorMessage = @error, CollectedAt = SYSUTCDATETIME() WHERE BatchID = @id`);
    throw error;
  }
}

/**
 * Hourly: collect any finished scoring batches, then submit waiting items as a new batch once enough
 * have queued (or the oldest has waited long enough). params.force submits whatever is waiting;
 * params.max_items caps the batch size for a trial run; params.collect_only skips submission.
 */
async function run(params = {}) {
  const processLogId = Number(params.log_id || 0) || null;
  const force = params.force === true || params.force === 1 || params.force === '1';
  const maxItems = Number(params.max_items || 0) || null;
  const collectOnly = params.collect_only === true || params.collect_only === '1';
  const pool = await connectPool(getProductionDatabase());
  const totals = {
    batches_collected: 0, batches_open: 0, scored: 0, discarded: 0, errored: 0,
    submitted_items: 0, batch_id: null, waiting: 0, cost_usd: 0,
  };

  try {
    const settings = await loadSettings(pool);
    const taxonomy = await loadTaxonomy(pool, settings);

    const orphaned = (await pool.request().input('purpose', sql.NVarChar(50), PURPOSE).query(`
      UPDATE dbo.MktAiBatch
      SET Status = N'failed', CollectedAt = SYSUTCDATETIME(), ErrorMessage = N'Submission never completed; items returned to the queue.'
      OUTPUT INSERTED.BatchID
      WHERE Status = N'submitted' AND Purpose = @purpose AND ExternalBatchID IS NULL
        AND SubmittedAt < DATEADD(HOUR, -1, SYSUTCDATETIME())
    `)).recordset;
    for (const row of orphaned) await releaseItems(pool, row.BatchID);

    const open = (await pool.request().input('purpose', sql.NVarChar(50), PURPOSE).query(`
      SELECT BatchID, ExternalBatchID, PromptVersion, Model, SubmittedAt
      FROM dbo.MktAiBatch
      WHERE Status = N'submitted' AND Purpose = @purpose AND ExternalBatchID IS NOT NULL
      ORDER BY BatchID
    `)).recordset;
    for (const batch of open) {
      const result = await collectBatch(pool, settings, batch, taxonomy, processLogId);
      if (!result.ended) {
        totals.batches_open += 1;
        continue;
      }
      totals.batches_collected += 1;
      totals.scored += result.scored;
      totals.discarded += result.discarded;
      totals.errored += result.errored + result.unparsed;
      totals.cost_usd += result.costUsd;
    }

    const pending = await pendingItems(pool);
    const waiting = Number(pending.Waiting || 0);
    const minItems = settingNumber(settings, 'research.score_batch_min_items', 25);
    const oldestHours = pending.Oldest ? (Date.now() - new Date(pending.Oldest).getTime()) / 3600000 : 0;
    const due = !collectOnly && waiting > 0 && (force || waiting >= minItems || oldestHours >= STALE_WAIT_HOURS);
    if (due) {
      const submitted = await submitBatch(pool, settings, taxonomy, processLogId, maxItems);
      if (submitted) {
        totals.submitted_items = submitted.items;
        totals.batch_id = submitted.batchId;
        totals.batches_open += 1;
      }
    }
    totals.waiting = (await pendingItems(pool)).Waiting;
    totals.cost_usd = Math.round(totals.cost_usd * 10000) / 10000;

    if (totals.batches_collected === 0 && totals.submitted_items === 0) {
      return {
        ok: true,
        error: null,
        skipped: true,
        message: totals.batches_open
          ? `Scoring batch still processing; ${totals.waiting} items waiting.`
          : `Nothing to score yet (${totals.waiting} waiting; submits at ${minItems} or after ${STALE_WAIT_HOURS}h).`,
        ...totals,
      };
    }
    return { ok: true, error: null, ...totals };
  } finally {
    await pool.close();
  }
}

module.exports = { run, applyScore, itemText };
