const { app } = require('@azure/functions');
const { timerSchedule } = require('../lib/timer-schedule');
const processRunner = require('../lib/process-runner');

app.timer('inventory-sales-sync', {
    schedule: timerSchedule('INVENTORY_SALES_SYNC_SCHEDULE', '0 0 3 * * *'),
    handler: async (_timer, context) => {
        context.log('Inventory sales sync: starting');

        const result = await processRunner.execute('inventory-sales-sync');
        context.log(
            'Inventory sales sync: ok=%s log_id=%s %s',
            result.ok,
            result.log_id,
            result.message || result.error
        );

        if (!result.ok) {
            throw new Error(String(result.error || result.message || 'Inventory sales sync failed.'));
        }
    },
});
