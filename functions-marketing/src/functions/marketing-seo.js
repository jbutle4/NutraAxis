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
app.timer('marketing-gsc', {
  schedule: process.env.MARKETING_GSC_SCHEDULE || '0 30 9 * * *',
  handler: (timer, context) => runJob('seo-gsc-ingest', context),
});

app.timer('marketing-ga4', {
  schedule: process.env.MARKETING_GA4_SCHEDULE || '0 45 9 * * *',
  handler: (timer, context) => runJob('seo-ga4-ingest', context),
});

app.timer('marketing-crawl', {
  schedule: process.env.MARKETING_CRAWL_SCHEDULE || '0 0 10 * * 1',
  handler: (timer, context) => runJob('seo-crawl', context),
});

app.timer('marketing-verify-published', {
  schedule: process.env.MARKETING_VERIFY_SCHEDULE || '0 20 10 * * *',
  handler: (timer, context) => runJob('seo-verify-published', context),
});
