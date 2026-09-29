const { app } = require('@azure/functions');
const runner = require('../lib/runner');

// NCRONTAB in UTC (Flex Consumption has no WEBSITE_TIME_ZONE).
app.timer('marketing-harvest', {
  schedule: process.env.MARKETING_HARVEST_SCHEDULE || '0 15 * * * *',
  handler: async (timer, context) => {
    const result = await runner.execute('research-harvest-due');
    context.log('Marketing harvest: ok=%s log_id=%s %s', result.ok, result.log_id, result.message || result.error);

    if (!result.ok) {
      throw new Error(String(result.error || result.message || 'Marketing harvest failed.'));
    }
  },
});
