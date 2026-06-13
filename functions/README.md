# Azure Functions — nutra-forecast-tool

All scheduled job **logic** runs here in Node.js. The PHP App Service serves the web UI only.

## Functions

| Function | Type | Status |
|----------|------|--------|
| `ping` | HTTP | Health check |
| `process-execute` | HTTP | Manual job execution and Process Log reruns |
| `daily-sales-summary` | Timer | **Live** — Adobe Commerce orders + SQL in Node |
| `weekly-chain` | Timer | **Live** — monthly rollup + forecast plan in Node |
| `jazz-inventory-snapshot` | Timer | **Live** — Jazz OMS inventory + SQL in Node |
| `process-retry` | Service Bus | **Live** — event-driven retries for failed ProcessExecutionLog rows |
| `staging-db-sync` | Timer | **Live** — incremental prod → staging SQL sync |

Forecast plan runs only via `weekly-chain` (Sunday 1:00 AM). There is no standalone forecast timer.

## Architecture

```
Timer function (Node)
  → process-runner.execute(code)
    → process-log (SQL)
    → jobs/<name>.js (business logic)

On failure (process-log.finishFailure)
  → schedule Service Bus message (process-retry queue) at NextRetryAt
  → process-retry function runs job when message is due
  → on exhaustion: email alert to Zendesk

Manual rerun (Process Log UI)
  → PHP POST /process-log/rerun.php
  → POST /api/process-execute on this Function App
```

No HTTP calls to PHP cron endpoints. Scheduled job cron scripts have been removed from the PHP app; WebJobs live under `App_Data/Disabled_jobs/`.

Shared messaging lives in `src/lib/service-bus.js`. Add new queues and triggers as other async workflows are introduced.

## Timer schedules

Set `WEBSITE_TIME_ZONE=America/Chicago`. Override schedules via app settings:

| Function | App setting | Default |
|----------|-------------|---------|
| `weekly-chain` | `WEEKLY_CHAIN_SCHEDULE` | `0 0 1 * * 0` (Sun 1:00 AM) |
| `daily-sales-summary` | `DAILY_SALES_SCHEDULE` | `0 0 2 * * *` (daily 2:00 AM) |
| `staging-db-sync` | `STAGING_SYNC_SCHEDULE` | `0 30 2 * * *` (daily 2:30 AM) |
| `jazz-inventory-snapshot` | `JAZZ_INVENTORY_SNAPSHOT_SCHEDULE` | `0 0 12 * * 0` (Sun 12:00 PM) |

## Local development

```bash
cp local.settings.json.example local.settings.json
# Set DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME_PRODUCTION, SERVICEBUS_CONNECTION_STRING
npm start
```

## Deploy

```bash
func azure functionapp publish Nutra-forecast-tool
```

## Azure App Settings

| Name | Value |
|------|--------|
| `WEBSITE_TIME_ZONE` | `America/Chicago` |
| `DB_SERVER` | SQL server |
| `DB_USER` / `DB_PASSWORD` | SQL credentials |
| `DB_NAME_PRODUCTION` | `nutraaxis` |
| `DB_NAME_STAGING` | `nutraaxis_staging` |
| Schedule settings | See table above |
| `AzureWebJobsStorage` | Set at provision time |

### PHP App Service (manual reruns from Process Log)

| Name | Value |
|------|--------|
| `NUTRA_FUNCTIONS_BASE_URL` | `https://nutra-forecast-tool-….azurewebsites.net` |
| `NUTRA_FUNCTIONS_KEY` | Function key for `process-execute` |

### Service Bus (`sb-forecast-tool`)

| Name | Value |
|------|--------|
| `SERVICEBUS_CONNECTION_STRING` | Namespace connection string |
| `SERVICEBUS_PROCESS_RETRY_QUEUE` | Optional — default `process-retry` |

When a process fails, `process-log` schedules a delayed message on the `process-retry` queue. The `process-retry` function invokes the job at `NextRetryAt` (2 / 4 / 8 minute backoff, max 3 attempts).

To backfill retries after migration off the old timer watcher:

```bash
node scripts/backfill-process-retries.js
```

### Adobe Commerce (daily sales summary)

| Name | Value |
|------|--------|
| `ADOBE_COMMERCE_ENVIRONMENT` | `stage`, `dev`, or `production` |
| `ADOBE_COMMERCE_CLIENT_ID` | IMS client ID |
| `ADOBE_COMMERCE_CLIENT_SECRET` | IMS client secret |
| `ADOBE_COMMERCE_PRODUCTION` | Tenant ID when environment is production |
| `ADOBE_COMMERCE_STAGE` | Tenant ID when environment is stage |
| `ADOBE_COMMERCE_DEV` | Tenant ID when environment is dev |
| `ADOBE_COMMERCE_ORDERS_PAGE_SIZE` | Optional — default `100` |

### Jazz OMS (inventory snapshot)

| Name | Value |
|------|--------|
| `JAZZ_DOMAIN` | Jazz subdomain (e.g. `fbflurry-uat01`) |
| `JAZZ_USERNAME` | API username |
| `JAZZ_PASSWORD` | API password |
| `JAZZ_TENANT_CODE` | Tenant code |
| `JAZZ_BASE_URL` | Optional full base URL override |
| `JAZZ_PAGE_SIZE` | Optional — default `100` |
| `JAZZ_INVENTORY_SNAPSHOT_SCHEDULE` | Optional — default `0 0 12 * * 0` |

### Process alerts (retry abandonment)

When a process exhausts automatic retries (status `Abandoned`), an email is sent to `alerts@nutraaxislabs.zendesk.com` (override with `PROCESS_ALERT_EMAIL`). Copy SMTP settings from the PHP App Service:

| Name | Value |
|------|--------|
| `SMTP_HOST` | e.g. `smtp.office365.com` |
| `SMTP_PORT` | `587` |
| `SMTP_USER` | SMTP mailbox |
| `SMTP_PASS` | SMTP password |
| `SMTP_ENCRYPTION` | `tls` |
| `MAIL_FROM_NAME` | Optional — default `NutraAxis Operations` |
| `MAIL_REPLY_TO` | Optional — default `nutrateam@nfcllc.com` |
| `PROCESS_ALERT_EMAIL` | Optional — default `alerts@nutraaxislabs.zendesk.com` |
| `SITE_URL` | Optional — link in alert body |
