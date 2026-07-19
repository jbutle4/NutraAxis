/**
 * One-time helper: schedule Service Bus retries for failed ProcessExecutionLog
 * rows that still have NextRetryAt set (e.g. after migrating off the timer watcher).
 *
 * Usage: node scripts/backfill-process-retries.js
 */
require('../src/lib/db-config');

const processLog = require('../src/lib/process-log');
const serviceBus = require('../src/lib/service-bus');

async function main() {
  if (!serviceBus.isConfigured()) {
    throw new Error('SERVICEBUS_CONNECTION_STRING is not configured.');
  }

  const candidates = await processLog.listRetryCandidates();
  let scheduled = 0;
  let skipped = 0;

  for (const candidate of candidates) {
    const logId = Number(candidate.ProcessExecutionLogID || 0);
    const processCode = String(candidate.ProcessCode || '').trim();
    const attemptCount = Number(candidate.AttemptCount || 0);
    const nextRetryAt = String(candidate.NextRetryAt || '').trim();

    if (logId <= 0 || processCode === '' || nextRetryAt === '') {
      skipped += 1;
      continue;
    }

    const scheduledAt = new Date(nextRetryAt.includes('T') ? nextRetryAt : `${nextRetryAt}Z`);
    const enqueueAt = scheduledAt.getTime() <= Date.now() ? new Date() : scheduledAt;

    await serviceBus.scheduleProcessRetry(logId, processCode, attemptCount, enqueueAt);
    scheduled += 1;
    console.log(`Scheduled retry log_id=${logId} code=${processCode} at=${enqueueAt.toISOString()}`);
  }

  await serviceBus.closeClient();

  console.log(JSON.stringify({
    found: candidates.length,
    scheduled,
    skipped,
  }, null, 2));
}

main().catch((error) => {
  console.error(error.message);
  process.exit(1);
});
