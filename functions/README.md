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
| `qbo-coa-sync` | Timer | **Live** — QuickBooks chart of accounts → dbo.QBO_COA |
| `accs-sales-order-sync` | Timer | **Live** — ACCS production orders → dbo.AccsSalesOrderHeader + AccsSalesOrderDetail |
| `accs-employee-customer-create` | HTTP | **Live** — create ACCS Stage customers from dbo.EmployeeList (`FirstEmail=1`) |
| `accs-jazz-order-test` | HTTP | **TEST** — fetch ACCS Stage order, map to Jazz UAT import payload, submit with incremented order number |

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
| `qbo-coa-sync` | `QBO_COA_SYNC_SCHEDULE` | `0 0 18 * * 5` (Fri 6:00 PM) |
| `accs-sales-order-sync` | `ACCS_SALES_ORDER_SYNC_SCHEDULE` | `0 0 */2 * * *` (every 2 hours) |
| `jazz-inventory-snapshot` | `JAZZ_INVENTORY_SNAPSHOT_SCHEDULE` | `0 0 12 * * 0` (Sun 12:00 PM) |

## Local development

```bash
cp local.settings.json.example local.settings.json
# Set DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME_PRODUCTION, SERVICEBUS_CONNECTION_STRING
npm start
```

## Deploy

**Test / Stage app** (`Nutra-forecast-tool`) — manual HTTP test functions, Jazz UAT test endpoints:

```bash
func azure functionapp publish Nutra-forecast-tool
```

**Production app** (`Nutra-forecast-tool-prod`) — production ACCS order sync, production employee provisioning:

```bash
func azure functionapp publish Nutra-forecast-tool-prod
```

On **Nutra-forecast-tool** (test), all timer schedules are disabled (`0 0 0 1 1 2099`). Use HTTP triggers (`process-execute`, test functions) for manual runs on the test app; production timers run on **Nutra-forecast-tool-prod**.

| Name | Test app value |
|------|----------------|
| `DAILY_SALES_SCHEDULE` | `0 0 0 1 1 2099` |
| `WEEKLY_CHAIN_SCHEDULE` | `0 0 0 1 1 2099` |
| `JAZZ_INVENTORY_SNAPSHOT_SCHEDULE` | `0 0 0 1 1 2099` |
| `QBO_COA_SYNC_SCHEDULE` | `0 0 0 1 1 2099` |
| `ACCS_SALES_ORDER_SYNC_SCHEDULE` | `0 0 0 1 1 2099` |

Process Log reruns for `accs-sales-order-sync` route to the prod Function App via `NUTRA_FUNCTIONS_PROD_BASE_URL` / `NUTRA_FUNCTIONS_PROD_KEY` on the PHP App Service.

Production app settings for ACCS order sync:

| Name | Value |
|------|--------|
| `ADOBE_COMMERCE_ENVIRONMENT` | `production` |
| `ADOBE_COMMERCE_CLIENT_ID` | Production IMS client ID |
| `ADOBE_COMMERCE_CLIENT_SECRET` | Production IMS client secret |
| `ADOBE_COMMERCE_PRODUCTION` | Production ACCS tenant ID |
| `ACCS_SALES_ORDER_SYNC_SCHEDULE` | `0 0 */2 * * *` (every 2 hours; uses `WEBSITE_TIME_ZONE`) |
| `ACCS_SALES_ORDER_DETAIL_RECONCILE_BATCH` | `200` (open orders re-checked each run; set `0` to disable) |

Manual run from Process Log or HTTP:

```bash
curl -X POST "https://nutra-forecast-tool-prod.azurewebsites.net/api/process-execute?code=<function-key>" \
  -H "Content-Type: application/json" \
  -d '{"process_code":"accs-sales-order-sync"}'
```

Force a lookback resync (ignores watermark, uses lookback window):

```json
{"process_code":"accs-sales-order-sync","params":{"force":true}}
```

### Header + detail sync design

Line items are synced **inside** `accs-sales-order-sync`, not as a separate timer:

1. **Phase 1 — header watermark** — fetches orders with `updated_at >= watermark`, upserts header + detail lines when the header changed or the order is new.
2. **Phase 1b — detail-only on fetched orders** — when a fetched order’s header is unchanged but line fingerprints differ (qty shipped, item `updated_at`, etc.), only `AccsSalesOrderDetail` is replaced.
3. **Phase 2 — open-order reconciliation** — re-fetches the oldest-synced open orders (`processing`, `pending`, etc.) via `GET /V1/orders/{id}` because ACCS can change line items without bumping the order header `updated_at`, so those orders would never appear in the list filter.

Set `ACCS_SALES_ORDER_DETAIL_RECONCILE_BATCH=0` to skip phase 2.

### ACCS Stage employee customers

`accs-employee-customer-create` reads `dbo.EmployeeList` where `FirstEmail = 1`, maps `Group1` to ACCS customer group IDs, and creates customers in **ACCS Stage** via `POST /V1/customers`.

| EmployeeList.Group1 | Stage ACCS group_id | ACCS group name |
|---------------------|---------------------|-----------------|
| `Employee` / `employee` | 9 | Employee |
| `Sales` / `sales` | 11 | Sales |

Stage group IDs differ from production (production uses 8/9). Override with `ACCS_EMPLOYEE_CUSTOMER_GROUP_MAP` if needed.

New accounts are created **inactive and locked** by default so employees cannot sign in until you activate them in ACCS admin:

| Setting | Default | Effect |
|---------|---------|--------|
| `ACCS_EMPLOYEE_CUSTOMER_START_INACTIVE` | `true` | Sets `is_active = 0` |
| `ACCS_EMPLOYEE_CUSTOMER_START_LOCKED` | `true` | Sets `lock_expires` far in the future |

Manual run:

```bash
curl -X POST "https://nutra-forecast-tool-prod.azurewebsites.net/api/process-execute?code=<function-key>" \
  -H "Content-Type: application/json" \
  -d '{"code":"accs-employee-customer-create","params":{"dry_run":true}}'
```

Or dedicated endpoint:

```bash
curl -X POST "https://nutra-forecast-tool-prod.azurewebsites.net/api/accs-employee-customer-create?code=<function-key>" \
  -H "Content-Type: application/json" \
  -d '{"params":{"dry_run":false}}'
```

Successful creates store `AccsStageCustomerId` on the employee row so reruns skip already-provisioned accounts.

## Azure App Settings

| Name | Value |
|------|--------|
| `WEBSITE_TIME_ZONE` | `America/Chicago` |
| `DB_SERVER` | SQL server |
| `DB_USER` / `DB_PASSWORD` | SQL credentials |
| `DB_NAME_PRODUCTION` | `nutraaxis` |
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

### ACCS → Jazz UAT order test (`accs-jazz-order-test`)

**TEST ONLY.** Always uses ACCS **Stage** as the order source and Jazz **UAT** credentials (`JAZZ_UAT_*`, never `JAZZ_*_PROD`). Each run increments the order number (`{increment_id}-TEST-{seq|timestamp}`) so Jazz does not reject duplicates.

| Name | Value |
|------|--------|
| `JAZZ_UAT_DOMAIN` | Jazz UAT subdomain (e.g. `fbflurry-uat01`); falls back to `JAZZ_DOMAIN` |
| `JAZZ_UAT_USERNAME` / `JAZZ_UAT_PASSWORD` | Jazz UAT API credentials; fall back to `JAZZ_USERNAME` / `JAZZ_PASSWORD` |
| `JAZZ_UAT_BASE_URL` | Optional full UAT base URL override |
| `JAZZ_TENANT_CODE` | Jazz tenant code |
| `JAZZ_ORDER_IMPORT_ENDPOINT` | Optional — default `/api/v1/order/import` |
| `JAZZ_ORDER_SOURCE_CODE` | Optional — default `WEB_2026` |
| `ADOBE_COMMERCE_CLIENT_ID` / `ADOBE_COMMERCE_CLIENT_SECRET` | ACCS IMS credentials |
| `ADOBE_COMMERCE_STAGE` | ACCS Stage tenant ID |
| `ACCS_JAZZ_ORDER_TEST_SECRET` | Optional shared secret (`x-nutraaxis-test-secret` header) |

Local example:

```bash
curl -s "http://localhost:7071/api/accs-jazz-order-test?code=LOCAL&increment_id=000000001&dry_run=true&cart_only=false"
```

Deployed on **Nutra-forecast-tool** (test app):

```bash
curl -s "https://nutra-forecast-tool-czaxf0eydta6aeeg.eastus2-01.azurewebsites.net/api/accs-jazz-order-test?code=<function-key>&increment_id=000000001&dry_run=true&cart_only=false"
```

Params: `entity_id` or `increment_id`, `dry_run`, `test_suffix`, `test_seq`, `cart_only` (default `true`), `force_sku` (override line SKUs for UAT).

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
