const { app } = require('@azure/functions');
const { timerSchedule } = require('../lib/timer-schedule');
const processRunner = require('../lib/process-runner');

app.timer('accs-jazz-tracking-sync', {
  schedule: timerSchedule('ACCS_JAZZ_TRACKING_SYNC_SCHEDULE', '0 0 */4 * * *'),
  handler: async (_timer, context) => {
    context.log('ACCS Jazz tracking sync: starting');

    const result = await processRunner.execute('accs-jazz-tracking-sync');
    context.log(
      'ACCS Jazz tracking sync: ok=%s log_id=%s %s',
      result.ok,
      result.log_id,
      result.message || result.error
    );

    if (!result.ok) {
      throw new Error(String(result.error || result.message || 'ACCS Jazz tracking sync failed.'));
    }
  },
});
