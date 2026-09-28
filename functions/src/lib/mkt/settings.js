const { sql } = require('../db-config');

async function loadSettings(pool) {
  const result = await pool.request().query('SELECT SettingKey, SettingValue FROM dbo.MktSetting');
  const settings = {};
  for (const row of result.recordset) {
    settings[row.SettingKey] = row.SettingValue;
  }
  return settings;
}

function settingNumber(settings, key, fallback) {
  const value = Number(settings[key]);
  return Number.isFinite(value) ? value : fallback;
}

function settingLines(settings, key) {
  return String(settings[key] || '')
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter(Boolean);
}

function settingJson(settings, key, fallback) {
  try {
    const decoded = JSON.parse(String(settings[key] || ''));
    return decoded && typeof decoded === 'object' ? decoded : fallback;
  } catch {
    return fallback;
  }
}

async function recordUsage(pool, usage) {
  await pool.request()
    .input('provider', sql.NVarChar(30), usage.provider)
    .input('operation', sql.NVarChar(100), usage.operation)
    .input('mode', sql.NVarChar(20), usage.mode || 'api')
    .input('prompt_key', sql.NVarChar(100), usage.promptKey || null)
    .input('prompt_version', sql.Int, usage.promptVersion ?? null)
    .input('model', sql.NVarChar(100), usage.model || null)
    .input('input_tokens', sql.Int, usage.inputTokens ?? null)
    .input('output_tokens', sql.Int, usage.outputTokens ?? null)
    .input('units', sql.Int, usage.units ?? null)
    .input('cost', sql.Decimal(12, 6), usage.costUsd ?? null)
    .input('ok', sql.Bit, usage.ok === false ? 0 : 1)
    .input('error', sql.NVarChar(1000), usage.error ? String(usage.error).slice(0, 1000) : null)
    .input('log_id', sql.Int, usage.processLogId ?? null)
    .input('ref_type', sql.NVarChar(40), usage.refType || null)
    .input('ref_id', sql.BigInt, usage.refId ?? null)
    .query(`
      INSERT INTO dbo.MktApiUsage (
        Provider, Operation, Mode, PromptKey, PromptVersion, Model,
        InputTokens, OutputTokens, Units, CostUsd, Ok, ErrorMessage,
        ProcessLogID, RefType, RefID
      )
      VALUES (
        @provider, @operation, @mode, @prompt_key, @prompt_version, @model,
        @input_tokens, @output_tokens, @units, @cost, @ok, @error,
        @log_id, @ref_type, @ref_id
      )
    `);
}

async function aiCostMonthToDate(pool) {
  const result = await pool.request().query(`
    SELECT COALESCE(SUM(CostUsd), 0) AS Cost
    FROM dbo.MktApiUsage
    WHERE Provider IN (N'anthropic', N'openai')
      AND CreatedAt >= DATEFROMPARTS(YEAR(SYSUTCDATETIME()), MONTH(SYSUTCDATETIME()), 1)
  `);
  return Number(result.recordset[0]?.Cost || 0);
}

module.exports = {
  loadSettings,
  settingNumber,
  settingLines,
  settingJson,
  recordUsage,
  aiCostMonthToDate,
};
