const { app } = require('@azure/functions');
const { timerSchedule } = require('../lib/timer-schedule');
const { invokePhpCron } = require('../lib/invoke-php-cron');

app.timer('daily-sales-summary', {
    // Daily 2:00 AM Central by default (WEBSITE_TIME_ZONE). Override with DAILY_SALES_SCHEDULE.
    schedule: timerSchedule('DAILY_SALES_SCHEDULE', '0 0 2 * * *'),
    handler: async (timer, context) => {
        await invokePhpCron(context, {
            productionKeys: ['DAILY_SALES_CRON_URL_PRODUCTION'],
            stagingKeys: ['DAILY_SALES_CRON_URL_STAGING'],
            label: 'Daily sales summary',
        });
    },
});
