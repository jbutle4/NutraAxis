const { app } = require('@azure/functions');
const runner = require('../lib/runner');

// NCRONTAB in UTC (Flex Consumption has no WEBSITE_TIME_ZONE). Days 2–10: a month is frozen in the portal
// once its analytics are complete, usually the 2nd–5th; the job skips when no frozen report needs highlights.
app.timer('marketing-report-highlights', {
  schedule: process.env.MARKETING_REPORT_HIGHLIGHTS_SCHEDULE || '0 15 14 2-10 * *',
  handler: async (timer, context) => {
    const result = await runner.execute('report-highlights');
    context.log('report-highlights: ok=%s log_id=%s %s', result.ok, result.log_id, result.message || result.error);
    if (!result.ok) {
      throw new Error(String(result.error || result.message || 'report-highlights failed.'));
    }
  },
});
