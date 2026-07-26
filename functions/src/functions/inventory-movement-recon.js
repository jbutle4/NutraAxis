const { app } = require('@azure/functions');
const { timerSchedule } = require('../lib/timer-schedule');
const processRunner = require('../lib/process-runner');

app.timer('inventory-movement-recon', {
    schedule: timerSchedule('INVENTORY_MOVEMENT_RECON_SCHEDULE', '0 0 4 * * *'),
    handler: async (_timer, context) => {
        context.log('Inventory movement recon: starting');

        const result = await processRunner.execute('inventory-movement-recon');
        context.log(
            'Inventory movement recon: ok=%s log_id=%s %s',
            result.ok,
            result.log_id,
            result.message || result.error
        );

        if (!result.ok) {
            throw new Error(String(result.error || result.message || 'Inventory movement recon failed.'));
        }
    },
});
