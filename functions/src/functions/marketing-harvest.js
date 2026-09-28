const { app } = require('@azure/functions');
const { timerSchedule } = require('../lib/timer-schedule');
const processRunner = require('../lib/process-runner');

app.timer('marketing-harvest', {
    schedule: timerSchedule('MARKETING_HARVEST_SCHEDULE', '0 15 * * * *'),
    handler: async (timer, context) => {
        const result = await processRunner.execute('research-harvest-due');
        context.log('Marketing harvest: ok=%s log_id=%s %s', result.ok, result.log_id, result.message || result.error);

        if (!result.ok) {
            throw new Error(String(result.error || result.message || 'Marketing harvest failed.'));
        }
    },
});
