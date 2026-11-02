const { app } = require('@azure/functions');
const { timerSchedule } = require('../lib/timer-schedule');
const processRunner = require('../lib/process-runner');

app.timer('practitioner-tier-reclass', {
  schedule: timerSchedule('PRACTITIONER_TIER_RECLASS_SCHEDULE', '0 0 12 1 * *'),
  handler: async (_timer, context) => {
    context.log('Practitioner tier reclass: starting');

    const result = await processRunner.execute('practitioner-tier-reclass');
    context.log(
      'Practitioner tier reclass: ok=%s log_id=%s %s',
      result.ok,
      result.log_id,
      result.message || result.error
    );

    if (!result.ok) {
      throw new Error(String(result.error || result.message || 'Practitioner tier reclass failed.'));
    }
  },
});
