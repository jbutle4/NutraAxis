const { sql } = require('../db-config');
const { settingNumber, settingJson, recordUsage, aiCostMonthToDate } = require('./settings');

const DEFAULT_WEB_SEARCH_USD = 0.01;
const REQUEST_TIMEOUT_MS = 5 * 60 * 1000;
const MAX_CONTINUATIONS = 4;

class BudgetExceededError extends Error {
  constructor(spent, budget) {
    super(`Monthly AI budget reached ($${spent.toFixed(2)} of $${budget.toFixed(2)}). Raise ai.monthly_budget_usd to continue.`);
    this.name = 'BudgetExceededError';
  }
}

async function loadPrompt(pool, promptKey) {
  const result = await pool.request()
    .input('key', sql.NVarChar(100), promptKey)
    .query('SELECT TOP 1 * FROM dbo.MktPrompt WHERE PromptKey = @key AND IsActive = 1');
  const prompt = result.recordset[0];
  if (!prompt) {
    throw new Error(`No active prompt "${promptKey}" — create or activate one in Prompt Lab.`);
  }
  return prompt;
}

/** Replace {{name}} placeholders; unknown placeholders render empty. */
function render(template, vars) {
  return String(template || '').replace(/\{\{\s*([a-z0-9_]+)\s*\}\}/gi, (_, name) => {
    const value = vars[name];
    if (value === undefined || value === null) return '';
    return Array.isArray(value) ? value.join(', ') : String(value);
  });
}

async function assertBudget(pool, settings) {
  const budget = settingNumber(settings, 'ai.monthly_budget_usd', 0);
  if (budget <= 0) return;
  const spent = await aiCostMonthToDate(pool);
  if (spent >= budget) throw new BudgetExceededError(spent, budget);
}

function estimateCost(settings, model, inputTokens, outputTokens, searches, batch = false) {
  const pricing = settingJson(settings, 'ai.pricing', {});
  const rates = pricing[model]
    || Object.entries(pricing).find(([name]) => model && model.startsWith(name))?.[1]
    || null;
  let cost = 0;
  if (rates) {
    cost += ((inputTokens || 0) * Number(rates.in || 0) + (outputTokens || 0) * Number(rates.out || 0)) / 1e6;
  }
  if (batch) cost *= 0.5;
  cost += (searches || 0) * Number(pricing.web_search ?? DEFAULT_WEB_SEARCH_USD);
  return Math.round(cost * 1e6) / 1e6;
}

async function postJson(url, headers, body) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS);
  try {
    const response = await fetch(url, {
      method: 'POST',
      headers: { 'content-type': 'application/json', ...headers },
      body: JSON.stringify(body),
      signal: controller.signal,
    });
    const text = await response.text();
    let json = null;
    try {
      json = JSON.parse(text);
    } catch {
      json = null;
    }
    if (!response.ok) {
      const message = json?.error?.message || text.slice(0, 300) || `HTTP ${response.status}`;
      throw new Error(`HTTP ${response.status}: ${message}`);
    }
    return json;
  } finally {
    clearTimeout(timer);
  }
}

/**
 * Newer models reject sampling parameters such as temperature. Retry once without it so a
 * prompt's saved temperature never breaks a model switch.
 */
async function postJsonTolerant(url, headers, body) {
  try {
    return await postJson(url, headers, body);
  } catch (error) {
    if (body.temperature === undefined || !/temperature/i.test(error.message)) throw error;
    delete body.temperature;
    return postJson(url, headers, body);
  }
}

async function callAnthropic({ model, system, user, temperature, maxTokens, webSearch, maxSearches }) {
  const apiKey = process.env.ANTHROPIC_API_KEY;
  if (!apiKey) throw new Error('ANTHROPIC_API_KEY is not configured.');

  const messages = [{ role: 'user', content: user }];
  const body = { model, max_tokens: maxTokens, messages };
  if (system) body.system = system;
  if (temperature !== null && temperature !== undefined) body.temperature = Number(temperature);
  if (webSearch) body.tools = [{ type: 'web_search_20250305', name: 'web_search', max_uses: maxSearches }];

  const totals = { inputTokens: 0, outputTokens: 0, searches: 0 };
  const texts = [];
  for (let turn = 0; turn <= MAX_CONTINUATIONS; turn += 1) {
    const json = await postJsonTolerant('https://api.anthropic.com/v1/messages', {
      'x-api-key': apiKey,
      'anthropic-version': '2023-06-01',
    }, body);
    totals.inputTokens += Number(json.usage?.input_tokens || 0)
      + Number(json.usage?.cache_read_input_tokens || 0)
      + Number(json.usage?.cache_creation_input_tokens || 0);
    totals.outputTokens += Number(json.usage?.output_tokens || 0);
    totals.searches += Number(json.usage?.server_tool_use?.web_search_requests || 0);
    for (const block of json.content || []) {
      if (block.type === 'text' && block.text) texts.push(block.text);
    }
    if (json.stop_reason !== 'pause_turn') {
      return { text: texts.join(''), stopReason: json.stop_reason, model: json.model || model, ...totals };
    }
    messages.push({ role: 'assistant', content: json.content });
  }
  return { text: texts.join(''), stopReason: 'pause_turn', model, ...totals };
}

function openAiSupportsTemperature(model) {
  return !/^(gpt-5|o\d)/i.test(model || '');
}

async function callOpenAi({ model, system, user, temperature, maxTokens, webSearch }) {
  const apiKey = process.env.OPENAI_API_KEY;
  if (!apiKey) throw new Error('OPENAI_API_KEY is not configured.');

  const body = { model, input: user, max_output_tokens: maxTokens };
  if (system) body.instructions = system;
  if (temperature !== null && temperature !== undefined && openAiSupportsTemperature(model)) {
    body.temperature = Number(temperature);
  }
  if (webSearch) body.tools = [{ type: 'web_search' }];

  const json = await postJsonTolerant('https://api.openai.com/v1/responses', { authorization: `Bearer ${apiKey}` }, body);
  const texts = [];
  let searches = 0;
  for (const item of json.output || []) {
    if (item.type === 'web_search_call') searches += 1;
    if (item.type === 'message') {
      for (const part of item.content || []) {
        if (part.type === 'output_text' && part.text) texts.push(part.text);
      }
    }
  }
  return {
    text: texts.join('\n'),
    stopReason: json.status === 'incomplete' ? (json.incomplete_details?.reason || 'incomplete') : 'end_turn',
    model: json.model || model,
    inputTokens: Number(json.usage?.input_tokens || 0),
    outputTokens: Number(json.usage?.output_tokens || 0),
    searches,
  };
}

/**
 * Run the active version of a prompt. Checks the monthly budget first and logs usage (success or failure).
 * @returns {Promise<{text: string, model: string, provider: string, promptVersion: number, costUsd: number,
 *   inputTokens: number, outputTokens: number, searches: number, stopReason: string}>}
 */
async function runPrompt(pool, settings, {
  promptKey, vars = {}, webSearch = false, maxSearches = 5, operation, processLogId = null, refType = null, refId = null,
}) {
  await assertBudget(pool, settings);
  const prompt = await loadPrompt(pool, promptKey);
  const provider = prompt.Provider === 'openai' ? 'openai' : 'anthropic';
  const model = prompt.Model || settings[`ai.${provider}.model`] || (provider === 'openai' ? 'gpt-5-mini' : 'claude-sonnet-4-5');
  const request = {
    model,
    system: render(prompt.SystemPrompt, vars),
    user: render(prompt.UserTemplate, vars),
    temperature: prompt.Temperature,
    maxTokens: Number(prompt.MaxTokens || 2000),
    webSearch,
    maxSearches,
  };
  const usageBase = {
    provider,
    operation: operation || promptKey,
    mode: webSearch ? 'agent' : 'realtime',
    promptKey,
    promptVersion: prompt.Version,
    model,
    processLogId,
    refType,
    refId,
  };

  try {
    const result = provider === 'openai' ? await callOpenAi(request) : await callAnthropic(request);
    const costUsd = estimateCost(settings, result.model, result.inputTokens, result.outputTokens, result.searches);
    await recordUsage(pool, {
      ...usageBase,
      model: result.model,
      inputTokens: result.inputTokens,
      outputTokens: result.outputTokens,
      units: result.searches,
      costUsd,
    });
    return { ...result, provider, promptVersion: prompt.Version, costUsd };
  } catch (error) {
    await recordUsage(pool, { ...usageBase, ok: false, error: error.message });
    throw error;
  }
}

/** Pull the first JSON array or object out of model text (tolerates ```json fences and prose). */
function extractJson(text) {
  const source = String(text || '');
  const fenced = source.match(/```(?:json)?\s*([\s\S]*?)```/i);
  const candidates = [fenced?.[1], source];
  for (const candidate of candidates) {
    if (!candidate) continue;
    const trimmed = candidate.trim();
    try {
      return JSON.parse(trimmed);
    } catch {
      // fall through to bracket scan
    }
    // Prose around the JSON can contain brackets (e.g. "[1]"); prefer the first balanced array of objects.
    let fallback = null;
    for (const open of ['[', '{']) {
      for (let start = trimmed.indexOf(open), tries = 0; start !== -1 && tries < 50;
        start = trimmed.indexOf(open, start + 1), tries += 1) {
        const end = matchingBracket(trimmed, start);
        if (end === -1) continue;
        try {
          const parsed = JSON.parse(trimmed.slice(start, end + 1));
          if (Array.isArray(parsed) && parsed.some((entry) => entry && typeof entry === 'object')) return parsed;
          if (!Array.isArray(parsed) && Array.isArray(parsed?.items)) return parsed;
          fallback = fallback ?? parsed;
        } catch {
          // not JSON at this position
        }
      }
    }
    if (fallback !== null) return fallback;
  }
  return null;
}

function matchingBracket(text, start) {
  const open = text[start];
  const close = open === '[' ? ']' : '}';
  let depth = 0;
  let inString = false;
  for (let i = start; i < text.length; i += 1) {
    const ch = text[i];
    if (inString) {
      if (ch === '\\') i += 1;
      else if (ch === '"') inString = false;
    } else if (ch === '"') {
      inString = true;
    } else if (ch === open) {
      depth += 1;
    } else if (ch === close) {
      depth -= 1;
      if (depth === 0) return i;
    }
  }
  return -1;
}

module.exports = {
  BudgetExceededError,
  loadPrompt,
  render,
  assertBudget,
  estimateCost,
  runPrompt,
  extractJson,
};
