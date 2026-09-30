const seoNoop = require('../jobs/seo-noop');
const researchHarvest = require('../jobs/research-harvest');
const researchAgent = require('../jobs/research-agent-discover');
const researchScore = require('../jobs/research-score');
const researchCluster = require('../jobs/research-cluster');
const campaign = require('../jobs/campaign');
const content = require('../jobs/content');
const seoPages = require('../jobs/seo-pages');
const seoAnalytics = require('../jobs/seo-analytics');
const engagement = require('../jobs/engagement');
const seoIssues = require('../jobs/seo-issues');
const seoAlerts = require('../jobs/seo-alerts');
const outreach = require('../jobs/outreach');

function costText(r) {
  return ` (~$${Number(r.cost_usd || 0).toFixed(2)}).`;
}

function auditText(a) {
  return `Issues: ${a.new_issues} new, ${a.new_urls} URLs newly affected, ${a.resolved_urls} URLs resolved, ${a.verified} verified, `
    + `${a.reopened} reopened${a.still_open ? `, ${a.still_open} still present after a fix` : ''}.`;
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
  'seo-crawl': {
    name: 'Page Inventory — Crawl Site',
    run: (params) => seoPages.crawl(params),
    message: (r) => (r.mode === 'full'
      ? `${r.in_sitemap ?? 0} sitemap URLs (${r.new_pages ?? 0} new, ${r.excluded ?? 0} excluded), ${r.content_pages ?? 0} published content pages; `
        + (r.sitemap_errors?.length ? `${r.sitemap_errors.length} sitemap errors; ` : '')
      : '')
      + `${r.crawled ?? 0} pages crawled — ${r.healthy ?? 0} OK, ${r.errors ?? 0} errors, ${r.changed ?? 0} changed, ${r.with_issues ?? 0} with issues`
      + (r.skipped_time ? `, ${r.skipped_time} left for the next run` : '') + '.'
      + (r.audit ? ` ${auditText(r.audit)}` : ''),
  },
  'seo-verify-published': {
    name: 'Page Inventory — Verify Published Content',
    run: (params) => seoPages.verifyPublished(params),
    message: (r) => `${r.checked ?? 0} published pieces checked — ${r.live ?? 0} live, ${r.problems ?? 0} failed`
      + (r.off_site ? `, ${r.off_site} not on the site domain` : '') + '.',
  },
  'seo-gsc-ingest': {
    name: 'Search Console — Nightly Ingest',
    run: (params) => seoAnalytics.gscIngest(params),
    message: (r) => `${r.start} to ${r.end}${r.backfill ? ' (backfill)' : ''}: ${r.days ?? 0} days, ${r.clicks ?? 0} clicks, `
      + `${r.impressions ?? 0} impressions; ${r.page_rows ?? 0} page rows, ${r.query_rows ?? 0} query rows`
      + (r.new_pages ? `, ${r.new_pages} new pages found in search` : '') + '.',
  },
  'seo-ga4-ingest': {
    name: 'GA4 — Nightly Ingest',
    run: (params) => seoAnalytics.ga4Ingest(params),
    message: (r) => `${r.start} to ${r.end}${r.backfill ? ' (backfill)' : ''}: ${r.days ?? 0} days, ${r.sessions ?? 0} sessions; `
      + `${r.landing_rows ?? 0} landing rows, ${r.utm_rows ?? 0} tagged rows — ${r.asset_sessions ?? 0} sessions on ${r.assets_with_sessions ?? 0} campaign assets.`,
  },
  'engagement-score': {
    name: 'Engagement — Nightly Scores',
    run: (params) => engagement.score(params),
    message: (r) => (r.assets || r.content
      ? `Scored ${r.assets} assets and ${r.content} content pieces → ${r.campaigns} campaigns, ${r.topics} topics, ${r.interests} interests`
        + (r.weighted_interests ? `; ${r.weights_changed} interest weights moved.` : '; not enough scores yet to weight interests.')
      : 'Nothing to score yet — no posted assets or published content.'),
  },
  'engagement-triage': {
    name: 'Response Inbox — Triage',
    run: (params) => engagement.triage(params),
    message: (r) => `${r.triaged ?? 0} responses triaged`
      + (r.escalated ? `, ${r.escalated} escalated to compliance` : '')
      + (r.failed ? `, ${r.failed} failed (will retry)` : '') + costText(r),
  },
  'engagement-digest': {
    name: 'Engagement — Monday Digest',
    run: (params) => engagement.digest(params),
    message: (r) => (r.sparse
      ? `Week ${r.period_start} – ${r.period_end}: not enough data yet; no tasks opened.`
      : `Week ${r.period_start} – ${r.period_end}: digest ready, ${r.tasks} tasks opened`
        + (r.dropped ? ` (${r.dropped} recommendations dropped — no traceable evidence)` : '') + costText(r)),
  },
  'seo-openrush-import': {
    name: 'Site Issues — Import OpenRush Audit',
    run: (params) => seoIssues.importOpenRush(params),
    message: (r) => `OpenRush audit imported for ${r.pages} pages (${r.audit.skipped} skipped) — ${r.audit.findings} findings. ${auditText(r.audit)}`,
  },
  'seo-issue-verify': {
    name: 'Site Issues — Verify Fix',
    run: (params) => seoIssues.verify(params),
    message: (r) => `Rechecked ${r.crawled} page${r.crawled === 1 ? '' : 's'} for “${r.title}” — now ${r.status}`
      + (r.open_urls ? `, still on ${r.open_urls} URL${r.open_urls === 1 ? '' : 's'}` : '') + '.',
  },
  'seo-fix-spec': {
    name: 'Site Issues — AI Fix Spec',
    run: (params) => seoIssues.fixSpec(params),
    message: (r) => `Fix spec written for “${r.title}” from ${r.urls} URL${r.urls === 1 ? '' : 's'}` + costText(r),
  },
  'outreach-draft-pitch': {
    name: 'Backlinks & Outreach — AI Pitch Draft',
    run: (params) => outreach.draftPitch(params),
    message: (r) => `Pitch drafted for ${r.domain} — claims score ${r.score}`
      + (r.needs_compliance ? ', flagged for compliance review' : '') + costText(r),
  },
  'seo-alerts': {
    name: 'Marketing Alerts — Daily Check',
    run: (params) => seoAlerts.run(params, Object.fromEntries(Object.entries(JOBS).map(([code, job]) => [code, job.name]))),
    message: (r) => `${r.rules} rules checked — ${r.firing} firing: ${r.new} new, ${r.kept} continuing, ${r.resolved} resolved`
      + (r.notify ? (r.notify.error ? `; notification failed: ${r.notify.error}` : r.notify.notified ? `; ${r.notify.notified} sent` : '') : '; notification skipped')
      + '.',
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
