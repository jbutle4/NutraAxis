const { app } = require('@azure/functions');
const runner = require('../lib/runner');

// NCRONTAB in UTC: Monday 11:00 UTC (06:00 CDT / 05:00 CST).
app.timer('marketing-research-agent', {
  schedule: process.env.MARKETING_RESEARCH_AGENT_SCHEDULE || '0 0 11 * * 1',
  handler: async (timer, context) => {
    const result = await runner.execute('research-agent-discover');
    context.log('Marketing research agent: ok=%s log_id=%s %s', result.ok, result.log_id, result.message || result.error);

    if (!result.ok) {
      throw new Error(String(result.error || result.message || 'Marketing research agent failed.'));
    }
  },
});
