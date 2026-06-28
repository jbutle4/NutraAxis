const { app } = require('@azure/functions');
const { timerSchedule } = require('../lib/timer-schedule');
const processRunner = require('../lib/process-runner');

app.timer('accs-sales-order-sync', {
    schedule: timerSchedule('ACCS_SALES_ORDER_SYNC_SCHEDULE', '0 0 */2 * * *'),
    handler: async (timer, context) => {
        context.log('ACCS sales order sync: starting');

        const result = await processRunner.execute('accs-sales-order-sync');
        context.log(
            'ACCS sales order sync: ok=%s log_id=%s %s',
            result.ok,
            result.log_id,
            result.message || result.error
        );

        if (!result.ok) {
            throw new Error(String(result.error || result.message || 'ACCS sales order sync failed.'));
        }
    },
});
