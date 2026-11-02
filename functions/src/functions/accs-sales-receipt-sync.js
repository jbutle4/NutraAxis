const { app } = require('@azure/functions');
const { timerSchedule } = require('../lib/timer-schedule');
const processRunner = require('../lib/process-runner');

app.timer('accs-sales-receipt-sync', {
    schedule: timerSchedule('ACCS_SALES_RECEIPT_SYNC_SCHEDULE', '0 30 3 * * *'),
    handler: async (_timer, context) => {
        context.log('ACCS sales receipt sync: starting');

        const result = await processRunner.execute('accs-sales-receipt-sync');
        context.log(
            'ACCS sales receipt sync: ok=%s log_id=%s %s',
            result.ok,
            result.log_id,
            result.message || result.error
        );

        if (!result.ok) {
            throw new Error(String(result.error || result.message || 'ACCS sales receipt sync failed.'));
        }
    },
});
