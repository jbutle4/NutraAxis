const seoNoop = require('../jobs/seo-noop');
const researchHarvest = require('../jobs/research-harvest');
const researchAgent = require('../jobs/research-agent-discover');
const researchScore = require('../jobs/research-score');
const researchCluster = require('../jobs/research-cluster');

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
  'research-score-batch': {
    name: 'Topic Synthesis — Score Items (Batch)',
    run: (params) => researchScore.run(params),
    message: (r) => [
      r.batches_collected ? `${r.batches_collected} batch collected: ${r.scored ?? 0} scored, ${r.discarded ?? 0} discarded`
        + (r.errored ? `, ${r.errored} returned to queue` : '') + ` (~$${Number(r.cost_usd || 0).toFixed(2)})` : null,
      r.submitted_items ? `${r.submitted_items} items submitted for scoring` : null,
      `${r.waiting ?? 0} waiting`,
    ].filter(Boolean).join('; ') + '.',
  },
  'research-cluster-topics': {
    name: 'Topic Synthesis — Cluster Topics',
    run: (params) => researchCluster.run(params),
    message: (r) => `${r.items ?? 0} items considered — ${r.topics_created ?? 0} new topics, ${r.topics_extended ?? 0} extended, `
      + `${r.items_assigned ?? 0} items grouped` + (r.emerging ? `, ${r.emerging} emerging-interest suggestions` : '')
      + ` (~$${Number(r.cost_usd || 0).toFixed(2)}).`,
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
