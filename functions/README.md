# Azure Functions — nutra-forecast-tool

Timer and HTTP functions for NutraAxis scheduled jobs.

## Functions

| Function | Type | Purpose |
|----------|------|---------|
| `ping` | HTTP | Health check; used by PHP `/function-test/` |
| `daily-sales-summary` | Timer | Daily 2:00 AM Central — calls PHP `/cron/daily-sales-summary.php` |
| `forecast-plan` | Timer | Sunday 1:30 AM Central — calls PHP `/cron/weekly-demand.php` |
| `staging-db-sync` | Timer | Daily 2:30 AM Central — incremental prod → staging SQL sync |

## PHP cron migration (Function App triggers)

Timer functions call secured PHP endpoints on the App Service. The forecast math, sales rollup, and SQL work still run in PHP until those processes are ported to Node.

| Function | PHP endpoint | Production URL setting | Staging URL setting |
|----------|--------------|------------------------|---------------------|
| `daily-sales-summary` | `/cron/daily-sales-summary.php` | `DAILY_SALES_CRON_URL_PRODUCTION` | `DAILY_SALES_CRON_URL_STAGING` |
| `forecast-plan` | `/cron/weekly-demand.php` | `FORECAST_PLAN_CRON_URL_PRODUCTION` | `FORECAST_PLAN_CRON_URL_STAGING` |

Set **both** URLs once, then choose the active site with one switch:

| Setting | Values |
|---------|--------|
| `NUTRAAXIS_CRON_TARGET` | `production` (default) or `staging` |

**Do not deploy the `functions/` folder to the PHP web server.** Publish only to the Function App with `func azure functionapp publish`. The web server keeps the `/cron/*.php` endpoints; the Function App keeps the schedulers.

### Staging → production rollout (single Function App)

1. Set both prod and staging cron URLs in Function App Configuration.
2. Set `NUTRAAXIS_CRON_TARGET=staging` while testing.
3. Publish function code (`func publish`) — no URL changes needed between deploys.
4. Verify staging `ProcessExecutionLog` and target tables.
5. Set `NUTRAAXIS_CRON_TARGET=production` and restart the Function App.
6. Disable the matching WebJob on production after the Function timer is confirmed.

Keep staging App Service WebJobs disabled so production crons are not triggered twice.

## Staging database sync

`staging-db-sync` copies **new and changed** rows from production (`nutraaxis`) into staging (`nutraaxis_staging`).

- Uses primary keys plus watermark columns such as `ModifiedDate`, `GeneratedAt`, or `SnapshotDateTime`
- Inserts missing rows and updates rows whose column values changed
- Does **not** delete staging-only rows or perform a full refresh
- Tracks progress in `dbo.StagingSyncState` and `dbo.StagingSyncRun` on the staging database

Run the staging SQL migration first:

```bash
node scripts/run-sql-file.js sql/063_create_staging_sync_state.sql
```

Ensure `.env` points at `nutraaxis_staging` when running that migration, or override `DB_NAME` for the command.

Manual sync (from `functions/` with `local.settings.json` configured):

```bash
npm run sync-staging
```

Excluded tables by default: `PasswordResetToken`, `StagingSyncState`, `StagingSyncRun`. Override with `STAGING_SYNC_EXCLUDED_TABLES`.

## Timer schedules (App Settings)

Each scheduled function reads its cron expression from an app setting. Defaults apply when the setting is omitted.

| Function | App setting | Default | Meaning |
|----------|-------------|---------|---------|
| `daily-sales-summary` | `DAILY_SALES_SCHEDULE` | `0 0 2 * * *` | Daily 2:00 AM Central |
| `forecast-plan` | `FORECAST_PLAN_SCHEDULE` | `0 30 1 * * 0` | Sunday 1:30 AM Central |
| `staging-db-sync` | `STAGING_SYNC_SCHEDULE` | `0 30 2 * * *` | Daily 2:30 AM Central |

Set `WEBSITE_TIME_ZONE` to `America/Chicago` on the Function App. After changing a schedule in Azure Configuration, restart the Function App.

NCRONTAB format: `{second} {minute} {hour} {day} {month} {day-of-week}`

## Local development

### 1. Settings

Copy the example and add your cron secret (same value as PHP App Service `CRON_SECRET`):

```bash
cp local.settings.json.example local.settings.json
# Edit local.settings.json → set CRON_SECRET
```

### 2. Storage emulator (required for timer triggers locally)

Timer functions need `AzureWebJobsStorage`. Either:

- Install and run [Azurite](https://learn.microsoft.com/azure/storage/common/storage-use-azurite): `azurite --silent`
- Or paste your Function App’s storage connection string into `local.settings.json`

### 3. Run locally

Use Node 20 LTS (Azure Functions does not support Node 25 yet):

```bash
nvm use 20   # if using nvm
npm start    # func start — http://localhost:7071
```

Test ping:

```bash
curl "http://localhost:7071/api/ping?name=NutraAxis"
```

## Deploy to Azure

```bash
az login
func azure functionapp publish nutra-forecast-tool
```

## Azure App Settings (Function App → Configuration)

| Name | Value |
|------|--------|
| `WEBSITE_TIME_ZONE` | `America/Chicago` |
| `NUTRAAXIS_CRON_TARGET` | `production` or `staging` |
| `CRON_SECRET` | Same as PHP App Service |
| `DAILY_SALES_CRON_URL_PRODUCTION` | `https://nutraaxisweb.azurewebsites.net/cron/daily-sales-summary.php` |
| `DAILY_SALES_CRON_URL_STAGING` | `https://nutraaxisweb-staging.azurewebsites.net/cron/daily-sales-summary.php` |
| `DAILY_SALES_SCHEDULE` | `0 0 2 * * *` |
| `FORECAST_PLAN_CRON_URL_PRODUCTION` | `https://nutraaxisweb.azurewebsites.net/cron/weekly-demand.php` |
| `FORECAST_PLAN_CRON_URL_STAGING` | `https://nutraaxisweb-staging.azurewebsites.net/cron/weekly-demand.php` |
| `FORECAST_PLAN_SCHEDULE` | `0 30 1 * * 0` |
| `STAGING_SYNC_SCHEDULE` | `0 30 2 * * *` |
| `DB_SERVER` | `nutraaxisdb01.database.windows.net` |
| `DB_PORT` | `1433` |
| `DB_USER` | SQL login |
| `DB_PASSWORD` | SQL password |
| `DB_NAME_PRODUCTION` | `nutraaxis` |
| `DB_NAME_STAGING` | `nutraaxis_staging` |
| `STAGING_SYNC_OVERLAP_MINUTES` | `5` (optional) |
| `STAGING_SYNC_BATCH_SIZE` | `100` (optional) |
| `AzureWebJobsStorage` | (set at provision time) |

**Remove legacy settings** after adding the new ones: `NUTRAAXIS_CRON_URL`, `DAILY_SALES_CRON_URL`, `FORECAST_PLAN_CRON_URL`.

## PHP App Service settings (for `/function-test/`)

| Name | Value |
|------|--------|
| `AZURE_FUNCTION_APP_URL` | `https://nutra-forecast-tool-czaxf0eydta6aeeg.eastus2-01.azurewebsites.net` |
| `AZURE_FUNCTION_APP_KEY` | Function key from portal → Functions → ping → Function keys |
