# Marketing & Research — Operations Runbook

How to keep the Marketing & Research module (SEO Ops) running: schedules, alerts, reruns, keys, costs and fixes.
Build history and design live in [`docs/SEO_OPS_BUILD_SPEC.md`](../SEO_OPS_BUILD_SPEC.md).

## 1. Moving parts

| Piece | Where | Notes |
|---|---|---|
| Portal pages | `nutraaxisweb` App Service, `/marketing/…` | PHP. Deploys on merge to `main` (GitHub Action “Deploy to Azure App Service”). |
| Jobs | Function App **`nutraaxis-marketing-func`** (RG `NutraSync`, Flex Consumption, East US 2) | Node. Timers + the HTTP runner the portal calls. Published by hand — see §8. |
| Database | Azure SQL (same database as the portal) | Tables `Mkt*`; job runs in `dbo.ProcessExecutionLog`. |
| Secrets | Key Vault **`nutraaxis-mkt-kv`** | Referenced from Function App settings — see §6. |
| Portal → jobs | `NUTRA_FUNCTIONS_MARKETING_BASE_URL` / `NUTRA_FUNCTIONS_MARKETING_KEY` on `nutraaxisweb` | “Run now” buttons and on-demand actions call the Function App through these. |

Scope rules that always apply: only **nutraaxislabs.com** properties (Search Console, GA4, OpenRush imports refuse anything else — no Biote access); nothing writes to the website or ad platforms and nothing auto-posts; no customer PII goes to AI providers.

## 2. Schedules

Function timers run in **UTC**. Central Time is UTC−5 in summer (CDT) and UTC−6 in winter (CST), so every CT time below moves one hour earlier from November to March. The “Schedule” column on Admin & Jobs uses the summer times.

| Job code | Name | UTC | CDT / CST | Override setting |
|---|---|---|---|---|
| `research-harvest-due` | Content Harvester — Due Sources | hourly at :15 | same | `MARKETING_HARVEST_SCHEDULE` |
| `research-score-batch` | Topic Synthesis — Score Items (Batch) | hourly at :45 | same | `MARKETING_SCORE_SCHEDULE` |
| `engagement-triage` | Response Inbox — Triage | 00:10, 06:10, 12:10, 18:10 | 19:10 / 18:10 … | `MARKETING_ENGAGEMENT_TRIAGE_SCHEDULE` |
| `seo-gsc-ingest` | Search Console — Nightly Ingest | 09:30 daily | 04:30 / 03:30 | `MARKETING_GSC_SCHEDULE` |
| `seo-ga4-ingest` | GA4 — Nightly Ingest | 09:45 daily | 04:45 / 03:45 | `MARKETING_GA4_SCHEDULE` |
| `seo-crawl` | Page Inventory — Crawl Site | 10:00 Monday | 05:00 / 04:00 | `MARKETING_CRAWL_SCHEDULE` |
| `seo-verify-published` | Page Inventory — Verify Published Content | 10:20 daily | 05:20 / 04:20 | `MARKETING_VERIFY_SCHEDULE` |
| `engagement-score` | Engagement — Nightly Scores | 10:40 daily | 05:40 / 04:40 | `MARKETING_ENGAGEMENT_SCORE_SCHEDULE` |
| `seo-alerts` | Marketing Alerts — Daily Check | 11:00 daily | 06:00 / 05:00 | `MARKETING_ALERTS_SCHEDULE` |
| `research-agent-discover` | AI Research Agent — Weekly Discovery | 11:00 daily (each interest at most weekly) | 06:00 / 05:00 | `MARKETING_RESEARCH_AGENT_SCHEDULE` |
| `engagement-digest` | Engagement — Monday Digest | 12:00 Monday | 07:00 / 06:00 | `MARKETING_DIGEST_SCHEDULE` |
| `research-cluster-topics` | Topic Synthesis — Cluster Topics | 12:30 daily (when 8+ items newly scored) | 07:30 / 06:30 | `MARKETING_CLUSTER_SCHEDULE` |

On-demand jobs (started from their module, not a timer): `campaign-*` (Campaign Studio), `content-*` (Content Pipeline), `seo-openrush-import` and `seo-fix-spec` (Audit & Issues), `seo-issue-verify` (runs when an issue is marked fixed), `seo-noop` (chassis check).

To change a schedule, set the override app setting on the Function App to a six-field NCRONTAB expression (`sec min hour day month weekday`, UTC). Setting changes restart the app. Update the `schedule` label in `includes/marketing-jobs.php` to match.

## 3. Checking health

- **Admin & Jobs → Jobs** (`/marketing/admin/`): last run, status and result per job; the 50 most recent runs.
- **Process Log** (`/process-log/`): every run with parameters, messages and errors; filter by process code.
- **Audit & Issues → Alerts** (`/marketing/issues/?tab=alerts`): what the daily alert check found.
- **Admin & Jobs → AI & API Usage**: spend against budget, daily and monthly spend, cost by job and prompt, recent calls with errors.
- Function logs: Azure portal → `nutraaxis-marketing-func` → Application Insights → Logs, or **Monitor** on the individual function.

A run left in **Running** long after it started was cut off (Function timeout or restart) and never recorded a finish; check Application Insights for the cause. It doesn't block later runs. **Abandoned** means a run failed and used up its retries.

## 4. Alerts and how to respond

`seo-alerts` runs daily and on **Check now**. It opens an alert per problem, refreshes alerts that are still true, and resolves alerts whose cause has gone — you don't close them by hand. New alerts are emailed once, in one message, to the addresses in the `alerts.recipients` setting (blank = everyone with full Marketing access) and posted to `ALERTS_WEBHOOK_URL` if that Function App setting exists. **Acknowledge** means “seen, working on it”; it doesn't silence the underlying rule.

Turn rules on or off in Admin & Jobs → Settings → `alerts.rules` (one per line).

| Rule | Fires when | What to do |
|---|---|---|
| `job_failed` | The latest **scheduled** run of a marketing job in the last 7 days failed or was abandoned. | Open the linked Process Log entry and read the error. Common causes are in §9. Fix, then **Run now** on Admin & Jobs; the alert resolves at the next check once a scheduled run succeeds. |
| `traffic_drop` | GA4 sessions or Search Console clicks for the last 7 days are `alerts.traffic_drop_pct` (30%) below the weekly average of the 4 weeks before. High severity at twice the threshold. Needs 35 days of data and a baseline of at least `alerts.traffic_min_sessions` / `alerts.traffic_min_clicks`. | Check Performance for the channel or pages that fell. Rule out tracking first (GA4 tag, consent banner, site outage), then Search Console coverage / manual actions, then recent site changes. Log findings as a task. |
| `legacy_brand` | A live page still shows a name in `brand.legacy_terms` (e.g. NutraSync) outside `brand.legacy_allow_paths`. One alert per URL. | Open the issue, fix the copy in the storefront, **Mark fixed** (triggers a recrawl of those URLs). If the page is meant to mention it, add its path to `brand.legacy_allow_paths`. |
| `site_error` | A high-severity site issue (missing title, unreachable page, HTTP error, noindex) is new or open. | Open the issue; **Generate fix spec** for the developer; **Mark fixed** when done. If it's intentional (account or checkout pages), **Ignore** it with a reason. |
| `escalation_overdue` | An escalated Response Inbox item is past its compliance-review due time. | Compliance reviewer clears it in the Response Inbox. Adverse-event wording follows the escalation policy, not marketing judgement. |
| `ai_budget` | Month-to-date AI spend reaches `alerts.budget_warn_pct` (80%) of `ai.monthly_budget_usd`, or the month-end forecast passes the budget (medium). Reaching the budget is a separate high alert. | See §7. Decide whether to raise the budget or let AI work pause until the next UTC month. |

An alert email that fails leaves the alert un-notified with the error recorded; the next run retries it.

## 5. Rerunning jobs

- **Scheduled jobs:** Admin & Jobs → **Run now**. Runs are idempotent — rerunning an ingest re-reads the same days, a crawl re-crawls, harvest skips items it already has.
- **A specific failed run:** a Failed or Abandoned run can be rerun with its original parameters from the Process Log.
- **On-demand jobs:** repeat the action in the module (e.g. **Generate fix spec**, **Recheck pages now**, Campaign Studio **Generate**).
- **Search Console / GA4 gaps:** each nightly ingest reloads the last `gsc.refresh_days` (5) / `ga4.refresh_days` (4) days, so a missed night or two catches up on its own (Search Console finalizes data 2–3 days late). For a longer gap, temporarily raise the refresh setting, run the ingest, and set it back. An empty table loads `*.backfill_days` (90) of history.
- **Crawl:** Audit & Issues → **Crawl site now**, or Run now on `seo-crawl`. Issues that recur reopen their existing record; fixed ones resolve on the next crawl.

## 6. Keys and secrets

Secrets are in Key Vault **`nutraaxis-mkt-kv`** and the Function App reads them through Key Vault references. The Function App's system-assigned managed identity has **Get** and **List** on secrets (access-policy model — the team's Azure role is Contributor, which can't grant RBAC).

| Function App setting | Key Vault secret |
|---|---|
| `DB_PASSWORD` | `db-password` |
| `ANTHROPIC_API_KEY` | `anthropic-api-key` |
| `OPENAI_API_KEY` | `openai-api-key` |
| `GOOGLE_SA_JSON_B64` | `google-sa-json-b64` (base64 of the `nutraaxislabs-seo` service-account JSON key) |
| `SMTP_PASS` | `smtp-pass` |

Non-secret settings (`DB_SERVER`, `DB_USER`, `GSC_SITE_URL`, `GA4_PROPERTY_ID`, `SMTP_HOST` …) stay as plain app settings. The portal (`nutraaxisweb`) keeps its own copies of the database and SMTP credentials — rotating those means updating both places.

**Rotating a secret**

1. Create the new key at the provider (Anthropic console, OpenAI dashboard, Google Cloud service account, SMTP host, Azure SQL).
2. Store it without echoing it — write it to a private temp file and load from the file:
   ```bash
   az keyvault secret set --vault-name nutraaxis-mkt-kv --name anthropic-api-key --file /path/to/private-file --only-show-errors -o none
   rm -P /path/to/private-file
   ```
3. Restart the Function App so it picks up the new version immediately (otherwise it can take up to 24 hours):
   `az functionapp restart -g NutraSync -n nutraaxis-marketing-func`
4. Confirm every reference resolves:
   `az rest --method get --url "https://management.azure.com/subscriptions/<sub>/resourceGroups/NutraSync/providers/Microsoft.Web/sites/nutraaxis-marketing-func/config/configreferences/appsettings?api-version=2022-03-01" --only-show-errors` — each entry should show `"status": "Resolved"`.
5. Run `seo-noop`, then a job that uses the key (e.g. `seo-gsc-ingest` for Google, **Generate fix spec** for Anthropic), and check the result.
6. Revoke the old key at the provider.

Never paste keys into chat, tickets, shared folders or commits. The GoHighLevel token (`GHL_PIT`, deferred) follows the same pattern when it's added.

**If Key Vault is unreachable** (references show an error and jobs fail with auth errors): check the vault exists, the managed identity still has its access policy, and the secret isn't disabled or expired. As a last resort, temporarily put the value straight into the app setting, then switch back to the reference once the vault is fixed.

## 7. Budget and costs

- `ai.monthly_budget_usd` (Admin & Jobs → Settings) caps AI spend per **UTC** calendar month. Once month-to-date AI cost reaches it, **every** AI call is refused — scheduled and on demand — with “Monthly AI budget reached”. Non-AI work (harvesting, crawling, ingests) keeps running.
- Costs are estimates from token counts × `ai.pricing` (per-million-token rates by model; batch calls at half price; web searches at `web_search`). Update `ai.pricing` when provider prices change, then compare the dashboard with the provider invoice.
- **AI & API Usage** shows today, the last 7 days, a month-end forecast (month-to-date plus the 7-day daily rate for the days left), runway to the budget, and spend by day, month, job and prompt. The `ai_budget` alert uses the same forecast.
- Biggest costs are typically the batch scoring and research-agent jobs. To cut spend: pause harvest sources or interests, lower `research.score_batch_max_items` / `research.agent_max_results` / `research.cluster_max_items`, or switch a prompt to a cheaper model in Prompt Lab.
- Google APIs (Search Console, GA4) cost nothing and are logged with units only. OpenRush is used from Cursor and billed there, not through the portal.

## 8. Deploying

- **Portal:** merge to `main`; the GitHub Action deploys `nutraaxisweb`. Selective FTP (`node scripts/ftp-upload-files.js <paths…>`) is the emergency fallback — never a full-site upload from a branch. See `AGENTS.md`.
- **Function App:**
  ```bash
  cd functions-marketing
  npm ci
  func azure functionapp publish nutraaxis-marketing-func --javascript
  ```
  Takes about 2–3 minutes. Afterwards run `seo-noop` from Admin & Jobs. Only `nutraaxis-marketing-func` is published this way — don't publish the forecast Function Apps without approval.
- **SQL:** `node scripts/run-sql-file.js sql/<file>` with the local `.env`. Migrations are idempotent.

## 9. Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| “Monthly AI budget reached” | Budget hit for the UTC month. | §7 — raise `ai.monthly_budget_usd` or wait for the 1st. |
| Search Console / GA4 ingest: 403 or “permission” | The `nutraaxislabs-seo` service account lost access to the property, or the key was rotated/deleted. | In Search Console (`sc-domain:nutraaxislabs.com`) and GA4 (property `547490500`) confirm the service-account email is still a user. Rotate the key per §6 if needed. |
| Ingest “invalid_grant” / JWT errors | Service-account key deleted or `GOOGLE_SA_JSON_B64` malformed. | Create a new JSON key, base64 it, store per §6. |
| Every job fails with “Login failed” | DB password changed or Key Vault reference broken. | Check the reference status (§6 step 4); update `db-password`. |
| AI job: 401 / “invalid x-api-key” | Anthropic or OpenAI key revoked or expired. | Rotate per §6. |
| AI job: 429 / overloaded | Provider rate limit. | Usually clears on the next scheduled run; rerun later. |
| Alert emails not arriving | SMTP settings, `smtp-pass`, or recipients. | Alerts tab shows the notify error. Check `alerts.recipients`, the SMTP settings (copied from `nutraaxisweb`), and spam folders. |
| Crawl finds far fewer pages | Sitemap unreachable or `pages.exclude_patterns` too broad. | Check the crawl result text (“sitemap errors”) in the Process Log; review the setting. |
| OpenRush import refused | The audit is for another domain, or the JSON was cut off. | Only nutraaxislabs.com is accepted. Rerun `audit_site` and paste the whole reply. |
| Portal “Run now” errors before the job starts | `NUTRA_FUNCTIONS_MARKETING_*` settings on `nutraaxisweb` wrong, or the Function App is stopped. | Check the Function App is running and the base URL / key match. |

## 10. OpenRush audit import

1. In Cursor (or Claude) with the OpenRush tools connected, ask it to run `audit_site` for **nutraaxislabs.com**.
2. Copy the entire JSON reply.
3. Audit & Issues → **Import an OpenRush audit** → paste → **Import audit**.
4. OpenRush samples up to 20 pages. Only pages already active in the Page Inventory count; others are reported as skipped. OpenRush-owned checks (codes starting `or_`) resolve when a later import no longer finds them; the crawler's own checks are unaffected.
