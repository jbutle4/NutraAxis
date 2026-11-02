const { app } = require('@azure/functions');
const { timerSchedule } = require('../lib/timer-schedule');
const processRunner = require('../lib/process-runner');

// Monday 8:00 AM US Central (WEBSITE_TIME_ZONE=America/Chicago on the Function App).
app.timer('inventory-balances-weekly', {
    schedule: timerSchedule('INVENTORY_BALANCES_WEEKLY_SCHEDULE', '0 0 8 * * 1'),
    handler: async (_timer, context) => {
        context.log('Inventory balances weekly: starting');

        const result = await processRunner.execute('inventory-balances-weekly');
        context.log(
            'Inventory balances weekly: ok=%s log_id=%s %s',
            result.ok,
            result.log_id,
            result.message || result.error
        );

        if (!result.ok) {
            throw new Error(String(result.error || result.message || 'Inventory balances weekly failed.'));
        }
    },
});
