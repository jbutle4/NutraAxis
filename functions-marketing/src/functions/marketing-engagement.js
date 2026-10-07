const { app } = require('@azure/functions');
const runner = require('../lib/runner');

async function runJob(code, context) {
  const result = await runner.execute(code);
  context.log('%s: ok=%s log_id=%s %s', code, result.ok, result.log_id, result.message || result.error);
  if (!result.ok) {
    throw new Error(String(result.error || result.message || `${code} failed.`));
  }
}

// NCRONTAB in UTC (Flex Consumption has no WEBSITE_TIME_ZONE).
app.timer('marketing-engagement-score', {
  schedule: process.env.MARKETING_ENGAGEMENT_SCORE_SCHEDULE || '0 40 10 * * *',
  handler: (timer, context) => runJob('engagement-score', context),
});

// Responses are triaged when entered; this retries any that failed.
app.timer('marketing-engagement-triage', {
  schedule: process.env.MARKETING_ENGAGEMENT_TRIAGE_SCHEDULE || '0 10 */6 * * *',
  handler: (timer, context) => runJob('engagement-triage', context),
});

app.timer('marketing-digest', {
  schedule: process.env.MARKETING_DIGEST_SCHEDULE || '0 0 12 * * 1',
  handler: (timer, context) => runJob('engagement-digest', context),
});
