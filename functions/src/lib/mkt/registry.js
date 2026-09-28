const seoNoop = require('../jobs/seo-noop');
const researchHarvest = require('../jobs/research-harvest');
const researchAgent = require('../jobs/research-agent-discover');

const JOBS = {
  'seo-noop': {
    name: 'Marketing Chassis Check',
    run: () => seoNoop.run(),
    message: (r) => `Marketing chassis OK — ${r.settings ?? 0} settings loaded.`,
  },
  'research-harvest-due': {
    name: 'Content Harvester — Due Sources',
    run: (params) => researchHarvest.run(params),
    message: (r) => `${r.sources ?? 0} sources harvested — ${r.fetched ?? 0} fetched, ${r.inserted ?? 0} new, `
      + `${r.duplicates ?? 0} duplicates, ${r.failed ?? 0} failed`
      + (r.auto_paused ? `, ${r.auto_paused} auto-paused` : '') + '.',
  },
  'research-agent-discover': {
    name: 'AI Research Agent — Weekly Discovery',
    run: (params) => researchAgent.run(params),
    message: (r) => `${r.interests ?? 0} interests researched — ${r.candidates ?? 0} cited, ${r.verified ?? 0} verified, `
      + (r.unverified ? `${r.unverified} unverified (site blocks checks), ` : '')
      + `${r.rejected ?? 0} rejected, ${r.duplicates ?? 0} already known`
      + (r.failed ? `, ${r.failed} failed` : '') + ` (~$${Number(r.cost_usd || 0).toFixed(2)}).`,
  },
};

const REGISTRY = Object.fromEntries(
  Object.entries(JOBS).map(([code, job]) => [code, { code, name: job.name }])
);

function has(code) {
  return Object.prototype.hasOwnProperty.call(JOBS, code);
}

function invoke(code, params = {}) {
  return JOBS[code].run(params);
}

function buildResultMessage(code, result) {
  if (result.skipped && result.message) {
    return result.message;
  }
  return JOBS[code].message(result);
}

module.exports = {
  REGISTRY,
  has,
  invoke,
  buildResultMessage,
};
