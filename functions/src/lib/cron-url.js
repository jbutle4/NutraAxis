/**
 * Resolve which NutraAxis PHP site cron timers should call.
 *
 * Set both URLs in App Settings, then flip one switch:
 *   NUTRAAXIS_CRON_TARGET=production | staging
 */

function cronTarget() {
    const value = (process.env.NUTRAAXIS_CRON_TARGET || 'production').trim().toLowerCase();

    return value === 'staging' ? 'staging' : 'production';
}

function firstEnvValue(keys) {
    for (const key of keys) {
        const value = process.env[key];
        if (value !== undefined && String(value).trim() !== '') {
            return { key, url: String(value).trim() };
        }
    }

    return null;
}

function resolveCronUrl(options) {
    const {
        productionKeys = [],
        stagingKeys = [],
    } = options;

    const target = cronTarget();
    const keys = target === 'staging' ? stagingKeys : productionKeys;
    const match = firstEnvValue(keys);

    if (!match) {
        return {
            target,
            url: null,
            envKey: keys[0] || null,
        };
    }

    return {
        target,
        url: match.url,
        envKey: match.key,
    };
}

module.exports = {
    cronTarget,
    resolveCronUrl,
};
