const { app } = require('@azure/functions');
const { timerSchedule } = require('../lib/timer-schedule');
const { invokePhpCron } = require('../lib/invoke-php-cron');

app.timer('forecast-plan', {
    // Sunday 1:30 AM Central by default (WEBSITE_TIME_ZONE). Override with FORECAST_PLAN_SCHEDULE.
    schedule: timerSchedule('FORECAST_PLAN_SCHEDULE', '0 30 1 * * 0'),
    handler: async (timer, context) => {
        await invokePhpCron(context, {
            productionKeys: ['FORECAST_PLAN_CRON_URL_PRODUCTION'],
            stagingKeys: ['FORECAST_PLAN_CRON_URL_STAGING'],
            label: 'Forecast plan',
        });
    },
});
