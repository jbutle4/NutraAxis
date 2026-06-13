# Azure Functions — nutra-forecast-tool

Timer and HTTP functions for NutraAxis scheduled jobs.

## Functions

| Function | Type | Purpose |
|----------|------|---------|
| `ping` | HTTP | Health check; used by PHP `/function-test/` |
| `forecast-plan` | Timer | Sunday 1:30 AM Central — calls PHP `/cron/weekly-demand.php` |
| `staging-db-sync` | Timer | Daily 2:30 AM Central — incremental prod → staging SQL sync |

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
| `NUTRAAXIS_CRON_URL` | `https://nutraaxisweb.azurewebsites.net/cron/weekly-demand.php` |
| `CRON_SECRET` | Same as PHP App Service |
| `FORECAST_PLAN_SCHEDULE` | `0 30 1 * * 0` (optional — Sunday 1:30 AM) |
| `STAGING_SYNC_SCHEDULE` | `0 30 2 * * *` (optional — daily 2:30 AM) |
| `DB_SERVER` | `nutraaxisdb01.database.windows.net` |
| `DB_PORT` | `1433` |
| `DB_USER` | SQL login |
| `DB_PASSWORD` | SQL password |
| `DB_NAME_PRODUCTION` | `nutraaxis` |
| `DB_NAME_STAGING` | `nutraaxis_staging` |
| `STAGING_SYNC_OVERLAP_MINUTES` | `5` (optional) |
| `STAGING_SYNC_BATCH_SIZE` | `100` (optional) |
| `AzureWebJobsStorage` | (set at provision time) |

## PHP App Service settings (for `/function-test/`)

| Name | Value |
|------|--------|
| `AZURE_FUNCTION_APP_URL` | `https://nutra-forecast-tool-czaxf0eydta6aeeg.eastus2-01.azurewebsites.net` |
| `AZURE_FUNCTION_APP_KEY` | Function key from portal → Functions → ping → Function keys |
