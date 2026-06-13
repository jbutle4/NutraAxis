const { app } = require('@azure/functions');
const { runStagingDatabaseSync } = require('../lib/staging-sync');
const { timerSchedule } = require('../lib/timer-schedule');

app.timer('staging-db-sync', {
    // Daily 2:30 AM Central by default (WEBSITE_TIME_ZONE). Override with STAGING_SYNC_SCHEDULE.
    schedule: timerSchedule('STAGING_SYNC_SCHEDULE', '0 30 2 * * *'),
    handler: async (timer, context) => {
        context.log('Starting scheduled staging database sync.');

        const summary = await runStagingDatabaseSync((message, ...args) => {
            context.log(message, ...args);
        });

        context.log('Staging database sync summary: %s', JSON.stringify(summary));
    },
});
