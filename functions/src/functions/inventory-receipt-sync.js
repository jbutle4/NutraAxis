const { app } = require('@azure/functions');
const { timerSchedule } = require('../lib/timer-schedule');
const processRunner = require('../lib/process-runner');

app.timer('inventory-receipt-sync', {
    schedule: timerSchedule('INVENTORY_RECEIPT_SYNC_SCHEDULE', '0 30 2 * * *'),
    handler: async (_timer, context) => {
        context.log('Inventory receipt sync: starting');

        const result = await processRunner.execute('inventory-receipt-sync');
        context.log(
            'Inventory receipt sync: ok=%s log_id=%s %s',
            result.ok,
            result.log_id,
            result.message || result.error
        );

        if (!result.ok) {
            throw new Error(String(result.error || result.message || 'Inventory receipt sync failed.'));
        }
    },
});
