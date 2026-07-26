const { app } = require('@azure/functions');
const { timerSchedule } = require('../lib/timer-schedule');
const processRunner = require('../lib/process-runner');

app.timer('daily-sales-summary', {
    schedule: timerSchedule('DAILY_SALES_SCHEDULE', '0 0 2 * * *'),
    handler: async (timer, context) => {
        context.log('Daily sales summary: starting');

        const result = await processRunner.execute('daily-sales-summary');
        context.log(
            'Daily sales summary: ok=%s log_id=%s %s',
            result.ok,
            result.log_id,
            result.message || result.error
        );

        if (!result.ok) {
            throw new Error(String(result.error || result.message || 'Daily sales summary failed.'));
        }
    },
});
