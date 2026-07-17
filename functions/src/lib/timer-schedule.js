/**
 * Resolve a timer NCRONTAB schedule from an app setting.
 * Format: {second} {minute} {hour} {day} {month} {day-of-week}
 * Uses WEBSITE_TIME_ZONE on Azure (e.g. America/Chicago).
 */
function timerSchedule(envKey, defaultSchedule) {
    const value = process.env[envKey];
    if (value !== undefined && String(value).trim() !== '') {
        return String(value).trim();
    }
    return defaultSchedule;
}

module.exports = {
    timerSchedule,
};
