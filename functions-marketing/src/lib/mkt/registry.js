const seoNoop = require('../jobs/seo-noop');
const researchHarvest = require('../jobs/research-harvest');
const researchAgent = require('../jobs/research-agent-discover');
const researchScore = require('../jobs/research-score');
const researchCluster = require('../jobs/research-cluster');
const campaign = require('../jobs/campaign');
const content = require('../jobs/content');

function costText(r) {
  return ` (~$${Number(r.cost_usd || 0).toFixed(2)}).`;
}

function versionText(r) {
  return `version ${r.version_no} — claims score ${r.score}` + (r.needs_compliance ? ', needs compliance review' : '');
}

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
      + (r.truncated ? ' — reply hit the token limit; remaining items wait for the next run' : '')
      + ` (~$${Number(r.cost_usd || 0).toFixed(2)}).`,
  },
  'campaign-generate': {
    name: 'Campaign Studio — Generate Campaign',
    run: (params) => campaign.generate(params),
    message: (r) => `${r.assets ?? 0} assets generated — ${r.passing ?? 0} pass the claims check, `
      + `${r.needs_compliance ?? 0} need compliance review` + (r.check_failed ? `, ${r.check_failed} checks failed` : '')
      + (r.truncated ? ' (reply hit the token limit; some assets may be missing)' : '')
      + ` (~$${Number(r.cost_usd || 0).toFixed(2)}).`,
  },
  'campaign-claims-check': {
    name: 'Campaign Studio — Claims Check',
    run: (params) => campaign.check(params),
    message: (r) => `${r.checked ?? 0} assets checked — ${r.passing ?? 0} pass, ${r.needs_compliance ?? 0} need compliance review`
      + (r.check_failed ? `, ${r.check_failed} failed` : '') + ` (~$${Number(r.cost_usd || 0).toFixed(2)}).`,
  },
  'campaign-revise-asset': {
    name: 'Campaign Studio — AI Revise Asset',
    run: (params) => campaign.revise(params),
    message: (r) => `Asset ${r.asset_id} revised — claims score ${r.score}`
      + (r.needs_compliance ? ', needs compliance review' : '') + ` (~$${Number(r.cost_usd || 0).toFixed(2)}).`,
  },
  'content-brief': {
    name: 'Content Pipeline — AI Brief',
    run: (params) => content.brief(params),
    message: (r) => `Brief written for content ${r.content_id} — ${r.sections ?? 0} outline sections`
      + (r.truncated ? ' (reply hit the token limit; review the brief closely)' : '') + costText(r),
  },
  'content-draft': {
    name: 'Content Pipeline — AI Draft',
    run: (params) => content.draft(params),
    message: (r) => `Draft ${versionText(r)}, ${r.words ?? 0} words`
      + (r.truncated ? ' (reply hit the token limit; the ending may be cut off)' : '') + costText(r),
  },
  'content-claims-check': {
    name: 'Content Pipeline — Claims Check',
    run: (params) => content.check(params),
    message: (r) => `Checked ${versionText(r)}` + costText(r),
  },
  'content-revise': {
    name: 'Content Pipeline — AI Revise',
    run: (params) => content.revise(params),
    message: (r) => `Revised into ${versionText(r)}` + costText(r),
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
