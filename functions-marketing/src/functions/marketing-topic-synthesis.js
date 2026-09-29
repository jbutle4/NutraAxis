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
app.timer('marketing-score', {
  schedule: process.env.MARKETING_SCORE_SCHEDULE || '0 45 * * * *',
  handler: (timer, context) => runJob('research-score-batch', context),
});

app.timer('marketing-cluster', {
  schedule: process.env.MARKETING_CLUSTER_SCHEDULE || '0 30 12 * * *',
  handler: (timer, context) => runJob('research-cluster-topics', context),
});
