const { sql } = require('../db-config');
const { canonicalUrl, domainOf, sha256, contentHash, simhash, hamming } = require('./dedup');

const NEAR_DUPLICATE_BITS = 3;
const NEAR_DUPLICATE_WINDOW_DAYS = 30;

async function isKnownUrl(pool, url) {
  const result = await pool.request()
    .input('hash', sql.Binary(32), sha256(canonicalUrl(url)))
    .query('SELECT TOP 1 1 AS Known FROM dbo.MktHarvestedItem WHERE UrlHash = @hash');
  return result.recordset.length > 0;
}

async function loadRecentSimhashes(pool) {
  const result = await pool.request()
    .input('days', sql.Int, NEAR_DUPLICATE_WINDOW_DAYS)
    .query(`
      SELECT CAST(SimHash AS NVARCHAR(30)) AS SimHash
      FROM dbo.MktHarvestedItem
      WHERE SimHash IS NOT NULL AND FetchedAt >= DATEADD(DAY, -@days, SYSUTCDATETIME())
    `);
  return result.recordset.map((row) => row.SimHash);
}

/**
 * Insert one harvested item unless it duplicates an existing URL, content, or near-identical text.
 * @returns {Promise<'inserted'|'duplicate'>}
 */
async function insertItem(pool, item, recentSimhashes) {
  const canonical = canonicalUrl(item.url);
  const urlHash = sha256(canonical);
  const cHash = contentHash(item.title, item.body || item.summary);
  const sHash = simhash(`${item.title} ${item.body || item.summary || ''}`);

  if (cHash) {
    const existing = await pool.request()
      .input('hash', sql.Binary(32), cHash)
      .query('SELECT TOP 1 1 AS Found FROM dbo.MktHarvestedItem WHERE ContentHash = @hash');
    if (existing.recordset.length > 0) return 'duplicate';
  }
  if (sHash && recentSimhashes.some((other) => hamming(sHash, other) <= NEAR_DUPLICATE_BITS)) {
    return 'duplicate';
  }

  const result = await pool.request()
    .input('source_id', sql.Int, item.sourceId ?? null)
    .input('run_id', sql.BigInt, item.harvestRunId ?? null)
    .input('interest_id', sql.Int, item.interestId ?? null)
    .input('source_type', sql.NVarChar(30), item.sourceType)
    .input('url', sql.NVarChar(2000), String(item.url).slice(0, 2000))
    .input('canonical', sql.NVarChar(2000), canonical.slice(0, 2000))
    .input('url_hash', sql.Binary(32), urlHash)
    .input('content_hash', sql.Binary(32), cHash)
    .input('simhash', sql.BigInt, sHash)
    .input('domain', sql.NVarChar(255), domainOf(item.url))
    .input('title', sql.NVarChar(500), String(item.title).slice(0, 500))
    .input('summary', sql.NVarChar(4000), item.summary ? String(item.summary).slice(0, 4000) : null)
    .input('body', sql.NVarChar(sql.MAX), item.body ? String(item.body).slice(0, 60000) : null)
    .input('author', sql.NVarChar(300), item.author ? String(item.author).slice(0, 300) : null)
    .input('published', sql.DateTime2, item.publishedAt instanceof Date ? item.publishedAt : null)
    .input('status', sql.NVarChar(20), item.status || 'new')
    .input('metadata', sql.NVarChar(sql.MAX), item.metadata ? JSON.stringify(item.metadata) : null)
    .input('prompt_key', sql.NVarChar(100), item.promptKey || null)
    .input('prompt_version', sql.Int, item.promptVersion ?? null)
    .input('verification', sql.NVarChar(500), item.verificationNote ? String(item.verificationNote).slice(0, 500) : null)
    .query(`
      IF NOT EXISTS (SELECT 1 FROM dbo.MktHarvestedItem WHERE UrlHash = @url_hash)
      BEGIN
        INSERT INTO dbo.MktHarvestedItem (
          SourceID, HarvestRunID, InterestID, SourceType, Url, CanonicalUrl, UrlHash, ContentHash, SimHash, Domain,
          Title, Summary, BodyText, Author, PublishedAt, Status, MetadataJson, PromptKey, PromptVersion, VerificationNote
        )
        VALUES (
          @source_id, @run_id, @interest_id, @source_type, @url, @canonical, @url_hash, @content_hash, @simhash, @domain,
          @title, @summary, @body, @author, @published, @status, @metadata, @prompt_key, @prompt_version, @verification
        );
        SELECT 1 AS Inserted;
      END
      ELSE
        SELECT 0 AS Inserted;
    `);

  const inserted = Number(result.recordset[0]?.Inserted || 0) === 1;
  if (inserted && sHash) recentSimhashes.push(sHash);
  return inserted ? 'inserted' : 'duplicate';
}

async function startRun(pool, { sourceId = null, interestId = null, runType, processLogId = null }) {
  const result = await pool.request()
    .input('source_id', sql.Int, sourceId)
    .input('interest_id', sql.Int, interestId)
    .input('run_type', sql.NVarChar(30), runType)
    .input('log_id', sql.Int, processLogId)
    .query(`
      INSERT INTO dbo.MktHarvestRun (SourceID, InterestID, RunType, ProcessLogID)
      OUTPUT INSERTED.HarvestRunID
      VALUES (@source_id, @interest_id, @run_type, @log_id)
    `);
  return Number(result.recordset[0].HarvestRunID);
}

async function finishRun(pool, runId, { status, fetched = 0, inserted = 0, duplicates = 0, rejected = 0, error = null }) {
  await pool.request()
    .input('id', sql.BigInt, runId)
    .input('status', sql.NVarChar(20), status)
    .input('fetched', sql.Int, fetched)
    .input('inserted', sql.Int, inserted)
    .input('duplicates', sql.Int, duplicates)
    .input('rejected', sql.Int, rejected)
    .input('error', sql.NVarChar(1000), error ? String(error).slice(0, 1000) : null)
    .query(`
      UPDATE dbo.MktHarvestRun
      SET FinishedAt = SYSUTCDATETIME(), Status = @status, Fetched = @fetched, Inserted = @inserted,
          Duplicates = @duplicates, Rejected = @rejected, ErrorMessage = @error
      WHERE HarvestRunID = @id
    `);
}

module.exports = {
  isKnownUrl,
  loadRecentSimhashes,
  insertItem,
  startRun,
  finishRun,
};
