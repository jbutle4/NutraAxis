const { app } = require('@azure/functions');
const { timerSchedule } = require('../lib/timer-schedule');
const processRunner = require('../lib/process-runner');

app.timer('qbo-coa-sync', {
    schedule: timerSchedule('QBO_COA_SYNC_SCHEDULE', '0 0 18 * * 5'),
    handler: async (timer, context) => {
        context.log('QBO chart of accounts sync: starting');

        const result = await processRunner.execute('qbo-coa-sync');
        context.log(
            'QBO chart of accounts sync: ok=%s log_id=%s %s',
            result.ok,
            result.log_id,
            result.message || result.error
        );

        if (!result.ok) {
            throw new Error(String(result.error || result.message || 'QBO chart of accounts sync failed.'));
        }
    },
});
