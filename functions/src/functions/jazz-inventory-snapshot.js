const { app } = require('@azure/functions');
const { timerSchedule } = require('../lib/timer-schedule');
const processRunner = require('../lib/process-runner');

app.timer('jazz-inventory-snapshot', {
    schedule: timerSchedule('JAZZ_INVENTORY_SNAPSHOT_SCHEDULE', '0 0 12 * * 0'),
    handler: async (timer, context) => {
        context.log('Jazz inventory snapshot: starting');

        const result = await processRunner.execute('jazz-inventory-snapshot');
        context.log(
            'Jazz inventory snapshot: ok=%s log_id=%s %s',
            result.ok,
            result.log_id,
            result.message || result.error
        );

        if (!result.ok) {
            throw new Error(String(result.error || result.message || 'Jazz inventory snapshot failed.'));
        }
    },
});
