const { app } = require('@azure/functions');
const { timerSchedule } = require('../lib/timer-schedule');
const processRunner = require('../lib/process-runner');

app.timer('supplier-invoice-ap-recon', {
    schedule: timerSchedule('SUPPLIER_INVOICE_AP_RECON_SCHEDULE', '0 0 5 * * *'),
    handler: async (_timer, context) => {
        context.log('Supplier invoice AP recon: starting');

        const result = await processRunner.execute('supplier-invoice-ap-recon');
        context.log(
            'Supplier invoice AP recon: ok=%s log_id=%s %s',
            result.ok,
            result.log_id,
            result.message || result.error
        );

        if (!result.ok) {
            throw new Error(String(result.error || result.message || 'Supplier invoice AP recon failed.'));
        }
    },
});
