# Agent handoff — Marketing & Research

Last updated: **Sep 30, 2026** (end of session). Everything below is merged to `main` and live on `nutraaxisweb`; the marketing Function App (`nutraaxis-marketing-func`) is published from `main`.

## Where things stand

- Worktree: `/Users/jbutle4/Sites/nutraaxis-mkt` on `main` at `85cb812`, clean. `.env` is a symlink to the main repo.
- Main repo `/Users/jbutle4/Sites/nutraaxis` holds the user's **own uncommitted work** (branch `jbutle4/cursor/supplier-invoice-ap-workflow`, plus edits to `assets/css/operations.css`, `includes/process-functions-client.php`, QBO files and more). Leave it alone; do not commit it. Avoid changing `operations.css` from marketing work — use a module stylesheet (see `marketing-docs.css`, `marketing-manual.css`).
- Open PRs: only [#28](https://github.com/jbutle4/NutraAxis/pull/28) (Process Log dropdown) — not ours; conflict-scan against it before merging.
- Latest SQL migration: `sql/168_create_marketing_reports.sql` (applied). Next number: **169**.

## Shipped (recent PRs)

| PR | What |
|----|------|
| #52–#54 | Blog publishing, process map in the User Manual |
| #55–#56 | Field reference (what to enter on every form) and help-text accuracy |
| #57 | Rank Tracker and Backlinks & Outreach (18 disavow domains listed in the portal only — not uploaded to Google) |
| #58 | Literature & Intelligence (library, flyer references, claims coverage, regulatory watch) |
| #59 | **Output Generator** and **monthly Reports** |
| #60 | User Manual field tables widened (no hidden columns) |

### Output Generator (`/marketing/output-generator/`, slug `research-output`)
- Word (.docx, native OOXML via ZipArchive) and print/PDF documents from current data: product evidence pack, claim evidence summary, topic brief, library bibliography, article for the site author.
- Code: `includes/marketing-docs.php` (document model, HTML + Word renderers, output log), `includes/marketing-outputs.php` (builders), `marketing/output-generator/{index,document}.php`, `assets/css/marketing-docs.css`.
- "Recently generated" log (`MktOutputLog`). Shortcuts on Claims Matrix product, topic view, content view, Literature library.

### Reports (`/marketing/reports/`, slug `marketing-reports`)
- Monthly report, calendar month in Central time vs prior month (current month vs same days last month).
- Closed months freeze into `MktReport` from the 2nd once GSC and GA4 cover the month end (setting `reports.freeze_after_days`, default 5th, at the latest). Freezing runs in PHP on task sync / Reports page load.
- AI highlights: Function job `report-highlights` (prompt `reports.monthly_highlights`, timer days 2–10 at 14:15 UTC, and on demand). Aggregate figures only.
- Task "Review the … marketing report" (coordinator) opens on the 2nd; closes on **Mark reviewed**.
- Code: `includes/marketing-reports.php`, `marketing/reports/{index,view}.php`, `functions-marketing/src/lib/jobs/report.js`, `functions-marketing/src/functions/marketing-reports.js`.
- July and August 2026 are frozen with highlights.

## What remains

### To build
1. **Original Research** (`/marketing/original-research/`) — still a placeholder (the manual shows it as "Not built yet"). Scope not yet agreed with the user; start with a preview + AskQuestion as for Literature and Reports.

### Deferred (needs something from the user first)
2. **GoHighLevel stats ingest** — needs the GHL token set in App Settings (Key Vault reference). Never paste the token in chat.
3. **GoHighLevel push of approved posts** — needs GHL write scopes.
4. **Paid X / LinkedIn harvesting** — needs paid API access.
5. **Attribution join** (GHL contacts ↔ GA4) — depends on 2; no contact PII may be persisted or sent to AI.
6. Adobe — out of scope.

### People tasks (in the portal, not code)
7. **Confirm flyer reference matches** — 162 PubMed candidates waiting, 0 confirmed (Literature & Intelligence → Flyer references). Evidence packs stay thin ("Not matched to a paper yet") until this is done.
8. **6 references with no PubMed match** — enter PMID/DOI by hand: AndroAxis #18, #19; MagRenew #10 (StatPearls); MultiAxis #16, #18; ProbioAxis #4.
9. **AdrenaAxis** has no reference list on its flyer — add one to the product if it exists.
10. **Library is empty** — add sources from "To review" or by PMID/DOI.
11. **Review the August 2026 report** — the review task shows as overdue (due Sep 7) because August closed before Reports existed. Press **Mark reviewed** on `/marketing/reports/?month=2026-08`.

### Watch next
12. **Oct 2–10:** first scheduled run of `report-highlights`; September should freeze ~Oct 3–5 once GSC data reaches Sep 30, then the "Review the September 2026 marketing report" task opens. Check the Process Log for the job and the highlights for accuracy.
13. "Write highlights now" / "Refresh snapshot" call the Function synchronously; on a cold start the portal client can time out after 20 s (seen once in testing) — retry.

### Known, left as is
- `curl_close()` deprecation notice on PHP 8.5 in `includes/process-functions-client.php` — pre-existing; the user has uncommitted edits to that file.
- Local FreeTDS/dblib turns some non-Windows-1252 characters into "-"/"=" when writing from a local script; production (sqlsrv) is fine. Snapshot JSON is ASCII-escaped for this reason.

## Standing rules (from the user)
- No Biote access; ingest refuses non-nutraaxislabs.com properties (Biote as a competitor name only). Competitors: names, domains, positions only.
- Secrets only in App Service / Function App settings; never print, paste or commit them. The zshrc `ANTHROPIC_API_KEY` is a personal key — don't bill it.
- No PII to AI providers; outreach contact details never go to AI; only public paper data or aggregate numbers.
- Nothing auto-posts except **Publish to blog** clicked by a person on approved content; outreach emails are never sent from the portal; never write to the website or ad platforms.
- Claims gate is informational only. Don't upload the disavow file to Google.
- Don't email Josh or Madison with tests. Don't change production firewall rules. Don't publish the forecast Function Apps without approval (`nutraaxis-marketing-func` is fine).
- Deploy: merge to `main` → GitHub Actions deploys. Before merging: `php scripts/audit-portal-nav.php`, conflict scan vs `main` and open PRs, minimal edits to `includes/app.php` / `includes/auth.php`.

## Useful commands
- Publish Function App: `cd functions-marketing && func azure functionapp publish nutraaxis-marketing-func --javascript`
- Run a marketing job from a local PHP script (never print the key): `KEY=$(az functionapp keys list -g NutraSync -n nutraaxis-marketing-func --query functionKeys.default -o tsv) && NUTRA_FUNCTIONS_MARKETING_BASE_URL=https://nutraaxis-marketing-func.azurewebsites.net NUTRA_FUNCTIONS_MARKETING_KEY="$KEY" php script.php`
- Apply a migration: `node scripts/run-sql-file.js sql/<file>`
- Watch a deploy: `gh run list --workflow "Deploy to Azure App Service" --commit <sha>` then `gh run watch <id> --exit-status`
