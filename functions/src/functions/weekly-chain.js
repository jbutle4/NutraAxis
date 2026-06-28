const { app } = require('@azure/functions');
const { timerSchedule } = require('../lib/timer-schedule');
const processRunner = require('../lib/process-runner');

app.timer('weekly-chain', {
    schedule: timerSchedule('WEEKLY_CHAIN_SCHEDULE', '0 0 1 * * 0'),
    handler: async (timer, context) => {
        context.log('Weekly chain: starting monthly sales summary');

        const monthlyResult = await processRunner.execute('monthly-sales-summary');
        context.log(
            'Weekly chain: monthly ok=%s log_id=%s %s',
            monthlyResult.ok,
            monthlyResult.log_id,
            monthlyResult.message || monthlyResult.error
        );

        if (!monthlyResult.ok) {
            throw new Error(monthlyResult.error || monthlyResult.message || 'Monthly sales summary failed.');
        }

        context.log('Weekly chain: starting forecast plan');

        const planResult = await processRunner.execute('forecast-plan');
        context.log(
            'Weekly chain: forecast ok=%s log_id=%s %s',
            planResult.ok,
            planResult.log_id,
            planResult.message || planResult.error
        );

        if (!planResult.ok) {
            throw new Error(planResult.error || planResult.message || 'Forecast plan failed.');
        }
    },
});
