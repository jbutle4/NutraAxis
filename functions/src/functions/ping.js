const { app } = require('@azure/functions');

app.http('ping', {
    methods: ['GET', 'POST'],
    authLevel: 'function',
    handler: async (request, context) => {
        const name =
            request.query.get('name')
            || (await request.text())
            || 'NutraAxis';

        const payload = {
            ok: true,
            message: `Hello, ${name}!`,
            timestamp: new Date().toISOString(),
            environment: process.env.WEBSITE_SITE_NAME || 'local',
        };

        context.log('ping responded for name=%s', name);

        return {
            status: 200,
            headers: { 'Content-Type': 'application/json' },
            jsonBody: payload,
        };
    },
});
