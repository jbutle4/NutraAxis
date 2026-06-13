const { resolveCronUrl } = require('./cron-url');

/**
 * Call a secured NutraAxis PHP cron endpoint from a timer function.
 */
async function invokePhpCron(context, options) {
    const {
        productionKeys,
        stagingKeys,
        label,
        secretEnvKey = 'CRON_SECRET',
    } = options;

    const resolved = resolveCronUrl({ productionKeys, stagingKeys });
    const secret = process.env[secretEnvKey];

    if (!resolved.url) {
        context.error(
            '%s: no cron URL configured for target "%s". Expected one of: %s',
            label,
            resolved.target,
            (resolved.target === 'staging' ? stagingKeys : productionKeys).join(', ')
        );
        return;
    }

    if (!secret) {
        context.error('%s: %s is not configured.', label, secretEnvKey);
        return;
    }

    context.log(
        '%s: target=%s via %s → %s',
        label,
        resolved.target,
        resolved.envKey,
        resolved.url
    );

    const response = await fetch(resolved.url, {
        headers: { 'X-Cron-Secret': secret },
    });

    const body = await response.text();
    context.log('%s: returned HTTP %s: %s', label, response.status, body);

    if (!response.ok) {
        throw new Error(`${label} failed with HTTP ${response.status}: ${body}`);
    }

    return body;
}

module.exports = {
    invokePhpCron,
};
