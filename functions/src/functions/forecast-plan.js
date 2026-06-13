const { app } = require('@azure/functions');
const { timerSchedule } = require('../lib/timer-schedule');

app.timer('forecast-plan', {
    // Sunday 1:30 AM Central by default (WEBSITE_TIME_ZONE). Override with FORECAST_PLAN_SCHEDULE.
    schedule: timerSchedule('FORECAST_PLAN_SCHEDULE', '0 30 1 * * 0'),
    handler: async (myTimer, context) => {
        const url = process.env.NUTRAAXIS_CRON_URL;
        const secret = process.env.CRON_SECRET;

        if (!url) {
            context.error('NUTRAAXIS_CRON_URL is not configured.');
            return;
        }

        if (!secret) {
            context.error('CRON_SECRET is not configured.');
            return;
        }

        context.log('Calling NutraAxis forecast plan cron: %s', url);

        const response = await fetch(url, {
            headers: { 'X-Cron-Secret': secret },
        });

        const body = await response.text();
        context.log('Forecast plan cron returned HTTP %s: %s', response.status, body);

        if (!response.ok) {
            throw new Error(`Forecast plan cron failed with HTTP ${response.status}: ${body}`);
        }
    },
});
