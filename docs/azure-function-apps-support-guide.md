# NutraAxis Azure Function Apps — Support Guide

**Document version:** 1.0  
**Date:** June 20, 2026  
**Apps covered:**

| App name | Host (example) | Role |
|----------|----------------|------|
| **Nutra-forecast-tool** | `nutra-forecast-tool-czaxf0eydta6aeeg.eastus2-01.azurewebsites.net` | Test / manual / Stage ACCS / Jazz UAT |
| **Nutra-forecast-tool-prod** | `nutra-forecast-tool-prod.azurewebsites.net` | Production scheduled jobs |

Both apps run the **same function code** from the `functions/` project. Behavior differs by **Azure App Settings** (environment, schedules, credentials).

**Timezone:** All cron schedules use `WEBSITE_TIME_ZONE=America/Chicago` unless noted.

---

## 1. Resilience, retries, and logging architecture

### 1.1 Process Execution Log (`dbo.ProcessExecutionLog`)

Most **batch/scheduled jobs** run through `process-runner.execute()`, which:

1. Inserts a row with status **Running**
2. Runs the job
3. On success → status **Success**, `ResultMessage`, `ResultJson`
4. On failure → status **Failed**, schedules retry via **Azure Service Bus** (`process-retry` queue)

**Retry policy:**

| Attempt | Delay before retry |
|---------|-------------------|
| 1 | 2 minutes |
| 2 | 4 minutes |
| 3 | 8 minutes |

After **MaxAttempts** (default **3**), status becomes **Abandoned** and an email is sent to `PROCESS_ALERT_EMAIL` (default: `alerts@nutraaxislabs.zendesk.com`).

Manual reruns from the NutraAxis **Process Log** UI call `POST /api/process-execute` on the Function App (prod jobs for `accs-sales-order-sync` route to **Nutra-forecast-tool-prod**).

### 1.2 What is NOT in ProcessExecutionLog

| Function | Logging instead |
|----------|-----------------|
| **staging-db-sync** | `dbo.StagingSyncRun` and `dbo.StagingSyncState` in **nutraaxis_test** |
| **ping** | Application Insights / Function logs only |
| **accs-order-webhook** | Application Insights / Function logs only |
| **accs-jazz-order-test** | HTTP JSON response only (test function) |

### 1.3 Receiver acknowledgement — do you need a separate function?

**Short answer:** For most current jobs, **no separate acknowledgement function is required** if the integration uses a **synchronous HTTP API** that returns success (2xx) when the payload is accepted.

| Integration | How completion is confirmed today | Separate ack function needed? |
|-------------|-----------------------------------|------------------------------|
| **ACCS / Adobe Commerce API** (orders, customers) | HTTP response + parsed JSON body; errors fail the job and retry | **No** — API response is the ack |
| **Jazz OMS order import** (test) | HTTP **201** on successful import | **No** for API acceptance; **Yes** only if Jazz later sends async status webhooks you must track |
| **Jazz inventory API** | HTTP 2xx + inventory rows returned | **No** |
| **SQL writes** (sync, summaries, snapshots) | Transaction commit / row counts in job result | **No** |
| **SMTP email** (order webhook) | `messageId` from mail server = accepted for delivery, **not** proof recipient read | **Optional** — only if you need delivery/read receipts or Jazz/ACCS-style callback |
| **ACCS inbound webhook** | Function returns **HTTP 200** to ACCS (ack to sender); internal email may still fail partially | **No** for ACCS retry semantics; monitor email errors in response JSON |

**When you WOULD add a separate acknowledgement function:**

- Downstream system accepts the request asynchronously and confirms later via webhook (e.g. “order shipped”, “payment settled”).
- You need idempotent tracking of external job IDs separate from Process Log.
- Receiver cannot return reliable sync success in the same HTTP call.

---

## 2. Current production schedules (Nutra-forecast-tool-prod)

| App setting | Current value | Effective schedule |
|-------------|---------------|-------------------|
| `ACCS_SALES_ORDER_SYNC_SCHEDULE` | `0 0 */2 * * *` | **Every 2 hours** |
| `STAGING_SYNC_SCHEDULE` | `0 0 3 * * *` | **Daily 3:00 AM Central** |
| `DAILY_SALES_SCHEDULE` | `0 0 0 31 2 *` | **Disabled** (invalid date — Feb 31) |
| `WEEKLY_CHAIN_SCHEDULE` | `0 0 0 31 2 *` | **Disabled** |
| `JAZZ_INVENTORY_SNAPSHOT_SCHEDULE` | `0 0 0 31 2 *` | **Disabled** |

## 3. Current test app schedules (Nutra-forecast-tool)

All timer schedules are set to **`0 0 0 1 1 2099`** (never run). Use **HTTP** triggers for manual testing.

---

## 4. Function reference

### 4.1 ping

| | |
|--|--|
| **Purpose** | Health check; verifies Function App is running |
| **Type** | HTTP GET/POST |
| **Input** | Optional query `name` or body text |
| **Output** | JSON: `{ ok, message, timestamp, environment }` |
| **Schedule** | None |
| **Process log** | **No** |
| **Resilience** | None; caller sees HTTP status |
| **Receiver ack** | N/A |

**Apps:** Both

---

### 4.2 process-execute

| | |
|--|--|
| **Purpose** | Manual execution and Process Log reruns from the portal |
| **Type** | HTTP POST (function key required) |
| **Input** | JSON: `{ "code": "<process_code>", "params": {} }` OR `{ "log_id": 123 }` for rerun |
| **Output** | JSON: `{ ok, log_id, message, error, ...job stats }` |
| **Schedule** | None (on demand) |
| **Process log** | **Yes** — creates/updates log for registered process codes |
| **Resilience** | Failed runs use standard retry/abandon flow |
| **Receiver ack** | N/A (orchestrator only) |

**Registered process codes:** `daily-sales-summary`, `monthly-sales-summary`, `forecast-plan`, `jazz-inventory-snapshot`, `accs-sales-order-sync`, `accs-employee-customer-create`, `staging-db-sync`

**Apps:** Both (prod-only jobs should target **Nutra-forecast-tool-prod** via portal routing for `accs-sales-order-sync`)

---

### 4.3 process-retry

| | |
|--|--|
| **Purpose** | Service Bus worker that reruns failed Process Log jobs when due |
| **Type** | Service Bus queue trigger (`process-retry`) |
| **Input** | Message: `{ type: "process-retry", log_id, process_code, attempt_count }` |
| **Output** | Updates existing `ProcessExecutionLog` row (success or next failure/retry) |
| **Schedule** | Event-driven (scheduled at `NextRetryAt` on failure) |
| **Process log** | **Yes** — updates existing row, does not create a new one |
| **Resilience** | Same retry/abandon rules as initial run |
| **Receiver ack** | N/A |

**Apps:** Both

---

### 4.4 daily-sales-summary

| | |
|--|--|
| **Purpose** | Summarize previous calendar day ACCS orders by SKU into `dbo.DailySalesSummary` |
| **Type** | Timer |
| **Input** | ACCS orders via Adobe Commerce API (`ADOBE_COMMERCE_ENVIRONMENT`); optional manual param `date` (YYYY-MM-DD) |
| **Output** | SQL inserts/updates in `DailySalesSummary`; Process Log message with order/SKU counts |
| **Schedule (default)** | Daily **2:00 AM Central** (`DAILY_SALES_SCHEDULE`) |
| **Schedule (prod app today)** | **Disabled** |
| **Process log** | **Yes** |
| **Resilience** | Retries + abandon email |
| **Receiver ack** | ACCS API HTTP success; SQL commit confirms local persistence |

**Apps:** Both (timer disabled on prod until schedule enabled)

---

### 4.5 weekly-chain

| | |
|--|--|
| **Purpose** | Orchestrator: runs **monthly-sales-summary** then **forecast-plan** sequentially |
| **Type** | Timer |
| **Input** | None (reads `DailySalesSummary` / forecast inputs from SQL) |
| **Output** | Two Process Log entries (one per child job); throws if either child fails |
| **Schedule (default)** | Sunday **1:00 AM Central** (`WEEKLY_CHAIN_SCHEDULE`) |
| **Schedule (prod app today)** | **Disabled** |
| **Process log** | **Yes** — via child `process-runner.execute()` calls (not a separate log for the chain itself) |
| **Resilience** | Each child job retries independently |
| **Receiver ack** | SQL-only downstream |

**Apps:** Both

---

### 4.6 monthly-sales-summary *(via weekly-chain or process-execute)*

| | |
|--|--|
| **Purpose** | Roll up `DailySalesSummary` into monthly SKU totals |
| **Input** | SQL `DailySalesSummary` |
| **Output** | SQL monthly summary tables; Process Log row |
| **Schedule** | Only via **weekly-chain** (no standalone timer) |
| **Process log** | **Yes** |

---

### 4.7 forecast-plan *(via weekly-chain or process-execute)*

| | |
|--|--|
| **Purpose** | Generate weighted moving average forecasts and inventory projections by SKU |
| **Input** | SQL sales/inventory data |
| **Output** | SQL `ForecastPlan` rows; Process Log row |
| **Schedule** | Only via **weekly-chain** |
| **Process log** | **Yes** |

---

### 4.8 jazz-inventory-snapshot

| | |
|--|--|
| **Purpose** | Pull Jazz OMS inventory by SKU/facility into `dbo.JazzInventorySnapshot` |
| **Type** | Timer |
| **Input** | Jazz OMS inventory API (`JAZZ_DOMAIN` / `JAZZ_*_PROD` on prod app) |
| **Output** | SQL snapshot rows; Process Log with insert count |
| **Schedule (default)** | Sunday **12:00 PM Central** |
| **Schedule (prod app today)** | **Disabled** |
| **Process log** | **Yes** |
| **Resilience** | Retries + abandon email |
| **Receiver ack** | Jazz API HTTP 2xx + row data returned |

**Apps:** Both

---

### 4.9 staging-db-sync

| | |
|--|--|
| **Purpose** | Incremental sync **nutraaxis** → **nutraaxis_test** (changed + new rows, all tables) |
| **Type** | Timer |
| **Input** | Production SQL; watermark columns per table |
| **Output** | Upserts in test DB; `StagingSyncRun` / `StagingSyncState` in **nutraaxis_test** |
| **Schedule (default)** | Daily **3:00 AM Central** |
| **Schedule (prod app today)** | **Active — 3:00 AM Central** |
| **Schedule (test app)** | **Disabled** |
| **Process log** | **No** — uses `StagingSyncRun` instead |
| **Resilience** | Timer throws on failure (no Service Bus retry for this job today); check `StagingSyncRun.Status` |
| **Receiver ack** | SQL commit per table |

**Apps:** Both (should run on **prod** only)

---

### 4.10 accs-sales-order-sync

| | |
|--|--|
| **Purpose** | Sync ACCS **production** orders to `dbo.AccsSalesOrderHeader` and `AccsSalesOrderDetail` |
| **Type** | Timer |
| **Input** | ACCS Production API (orders updated since watermark; open-order detail reconciliation batch 200) |
| **Output** | SQL header/detail rows; Process Log with fetched/inserted/updated counts |
| **Schedule (prod app)** | **Every 2 hours** |
| **Schedule (test app)** | **Disabled** (skips if env ≠ production) |
| **Process log** | **Yes** |
| **Resilience** | Retries + abandon email |
| **Receiver ack** | Read-only from ACCS; persistence confirmed by SQL write counts |

**Apps:** Both deployed; **must run on Nutra-forecast-tool-prod** only

---

### 4.11 accs-employee-customer-create

| | |
|--|--|
| **Purpose** | Provision ACCS customers from `dbo.EmployeeList` (`FirstEmail=1`) |
| **Type** | HTTP POST |
| **Input** | JSON params: `dry_run`, `retry_failed`, `include_existing`, `fix_groups_only` |
| **Output** | JSON + Process Log; updates `AccsStageCustomerId` or `AccsProdCustomerId` in SQL |
| **Schedule** | None (manual / on demand) |
| **Target env** | Test app: Stage (`ACCS_EMPLOYEE_CUSTOMER_TARGET_ENV=stage`); Prod app: **production** |
| **Process log** | **Yes** |
| **Resilience** | Retries on failure; per-row errors stored in SQL |
| **Receiver ack** | ACCS customer create/search API response + stored customer ID |

**Apps:** Both

---

### 4.12 accs-order-webhook

| | |
|--|--|
| **Purpose** | Receive ACCS order events; enrich line items with fulfillment; email Cart/CPPC suppliers |
| **Type** | HTTP GET (challenge) / POST (events), anonymous + secret header |
| **Input** | ACCS webhook JSON or Adobe I/O CloudEvents; header `x-nutraaxis-webhook-secret` |
| **Output** | HTTP 200 JSON to ACCS; SMTP emails per fulfillment route |
| **Schedule** | None (event-driven from ACCS) |
| **Process log** | **No** |
| **Resilience** | Returns 200 to ACCS even for non-order payloads; partial email failure returns 200 with `errors` if some sends succeed; all-fail → HTTP 500 (ACCS may retry) |
| **Receiver ack** | **HTTP 200 to ACCS = webhook acknowledgement**; email delivery is separate |

**Apps:** Both (typically registered against one ACCS webhook URL)

---

### 4.13 accs-jazz-order-test *(TEST ONLY)*

| | |
|--|--|
| **Purpose** | Fetch ACCS **Stage** order, map to Jazz **UAT** import payload, POST with incremented order number |
| **Type** | HTTP GET/POST |
| **Input** | `increment_id` or `entity_id`, `dry_run`, `test_seq`, `force_sku`, `cart_only` |
| **Output** | JSON with source order, mapped payload, Jazz HTTP status (201 = accepted) |
| **Schedule** | None |
| **Process log** | **No** |
| **Resilience** | Manual retry only; increment order number to avoid Jazz duplicate rejection |
| **Receiver ack** | Jazz **HTTP 201** confirms order import accepted |

**Apps:** Both (should only be used intentionally; not production fulfillment)

---

## 5. Process log coverage summary

| Function | Logs to ProcessExecutionLog? |
|----------|------------------------------|
| daily-sales-summary | Yes |
| weekly-chain (children) | Yes (monthly + forecast) |
| jazz-inventory-snapshot | Yes |
| accs-sales-order-sync | Yes |
| accs-employee-customer-create | Yes |
| staging-db-sync | **No** (StagingSyncRun) |
| process-execute | Yes (for registered codes) |
| process-retry | Updates existing log |
| ping | No |
| accs-order-webhook | No |
| accs-jazz-order-test | No |

---

## 6. Operational contacts and URLs

| Resource | URL |
|----------|-----|
| Process Log UI | https://nutraaxisweb.azurewebsites.net/process-log/ |
| Test Function App | https://nutra-forecast-tool-czaxf0eydta6aeeg.eastus2-01.azurewebsites.net |
| Prod Function App | https://nutra-forecast-tool-prod.azurewebsites.net |

---

## 7. Recommendations

1. **Enable prod timers** for daily sales, weekly chain, and Jazz inventory on **Nutra-forecast-tool-prod** when ready (currently disabled with `0 0 0 31 2 *`).
2. **Wire staging-db-sync to Process Log** (optional) if you want retries/abandon alerts consistent with other jobs.
3. **Do not add a separate acknowledgement function** unless a downstream system provides async callbacks; rely on synchronous API 2xx and SQL bookkeeping today.
4. **Monitor** `StagingSyncRun` for DB sync failures and `ProcessExecutionLog` for all other batch jobs.

---

*Generated from NutraAxis `functions/` source and Azure App Settings as of June 2026.*
