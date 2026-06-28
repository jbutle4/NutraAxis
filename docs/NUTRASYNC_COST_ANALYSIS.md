# NutraSync Cost Analysis (June 2026)

**Scope:** Resource group `NutraSync`  
**Analysis date:** June 27, 2026  
**Data source:** Azure Cost Management API (resource group scope)

---

## Executive summary

June NutraSync spend (~**$467** MTD) is dominated by **SQL Database (~$421, 90%)**, not App Service or Function Apps. Deleting databases did **not** increase costs — but **deleted databases still appear on the June bill** for the days they ran before removal.

The mid-June **slope change** on the cost chart reflects:

1. **Multiple SQL databases** (`labresults`, `nutraaxis_staging`, `nutraaxis_test`, `nutraaxis`) all billing through mid-June.
2. **Higher daily SQL burn** (~$5/day early June → ~$23/day after June 10) while 3–4 databases were still online.
3. **Smaller increases** from App Service (~$18 → ~$29 in the second half) when staging slot / plan usage grew — **not** the primary driver.

**Going forward (July+):** With only `nutraaxis` remaining on serverless `GP_S_Gen5_1`, SQL should drop sharply (estimate **~$100–180/mo** depending on auto-pause vs always-on from timers/sync jobs).

---

## 1. Cost by service (full month vs split)

| Service | Jun 1–9 | Jun 10–26 | **MTD total** |
|---------|---------|-----------|---------------|
| **SQL Database** | $45.43 | **$391.18** | **$421.10** |
| Azure App Service | $17.59 | $29.05 | $44.90 |
| Storage | — | $0.29 | $0.29 |
| Service Bus | — | $0.28 | $0.28 |
| Functions | — | $0.17 | $0.17 |
| Bandwidth | $0.00 | $0.01 | $0.01 |
| **Total (approx.)** | ~$63 | ~$421 | **~$467** |

**Conclusion:** The jump after ~June 10 is **overwhelmingly SQL**, not Function Apps or Service Bus.

---

## 2. SQL cost by database (MTD, including deleted)

| Resource (database) | MTD cost | Status today |
|---------------------|----------|--------------|
| **labresults** | **$214.62** | **Deleted** — largest line item |
| **nutraaxis** | $103.91 | **Online** (production) |
| **nutraaxis_staging** | **$96.07** | **Deleted** |
| **nutraaxis_test** | $6.50 | **Deleted** |
| nutraaxis_dev | $0.00 | Deleted / never billed |

Only **`nutraaxis`** and system **`master`** remain on server `nutraaxisdb01`.

**Why deleted DBs still show on the bill:** Azure bills through the end of the day the resource existed in the calendar month. Deleting mid-June stops **future** charges but not **past** usage in June.

**User-reported deletions (lab results + test):** Match `labresults` ($215) + `nutraaxis_test` ($6.50). Also deleted: **`nutraaxis_staging`** ($96) — worth confirming that was intentional.

---

## 3. Current SQL configuration (production)

| Setting | Value |
|---------|--------|
| Database | `nutraaxis` |
| Tier | General Purpose **Serverless** Gen5 (`GP_S_Gen5_1`) |
| Min capacity | 0.5 vCore |
| Auto-pause delay | 60 minutes |
| Backup redundancy | Geo |

**Risk:** Function App timers (every 2 hours ACCS sync, nightly jobs) + Operations portal traffic may **prevent auto-pause**, keeping compute billed most of the day.

**Optimization options (SQL-only):**

- Monitor July daily SQL after deletions; if still high, compare **provisioned GP Gen5 1 vCore** vs serverless for always-on workload.
- If geo backup not required, switch to **Local** redundancy for backup storage savings.
- Shorten auto-pause delay (minimum 60 min) only if acceptable cold-start latency on first DB hit.

---

## 4. App Service plan review (`NutraAxis-ASP`)

| Setting | Value |
|---------|--------|
| SKU | **Premium v4 P0v4** (Linux) |
| Apps | `nutraaxisweb` |
| Deployment slot | **staging** (same plan) |
| Always On | **true** |
| Runtime | PHP 8.5 |
| MTD cost | **~$44.90** (~10% of NutraSync) |

### Is Premium required?

| Feature | Premium P0v4 | Basic B1 | Notes |
|---------|--------------|----------|-------|
| Deployment slots | Yes | No | **Staging slot requires Standard or higher** |
| Always On | Yes | Yes (paid tiers) | Currently enabled |
| Custom domains / TLS | Yes | Yes | Already configured |
| Future marketing apps on same plan | Yes | Limited | P0v4 has headroom for 2+ more apps at $0 incremental plan cost |

**Recommendation:**

- **Keep Premium for now** if the **staging slot** is actively used for pre-production FTP deploys.
- **Downgrade to Basic B1** (~$13/mo) only if you **remove the staging slot** and accept manual deploy testing — saves ~$30–130/mo depending on region.
- Adding marketing/promotions as **additional App Services on `NutraAxis-ASP`** adds **$0 plan cost** until you scale up/out.

---

## 5. Flex Consumption plans (Function Apps)

| Plan | Function App | MTD cost |
|------|--------------|----------|
| `ASP-NutraSync-a272` | `Nutra-forecast-tool` | ~$0.02 |
| `ASP-NutraSync-9594` | `Nutra-forecast-tool-prod` | ~$0.15 |
| **Functions total** | | **~$0.17** |

### Can the two Flex plans be merged?

**Yes, technically** — Flex Consumption supports **multiple function apps on one plan**. Azure Portal: Function App → **Change plan** → select existing `ASP-NutraSync-a272` (or consolidate under one new FC plan).

**Should you merge?**

- **Savings in June: negligible** (< $1 MTD).
- **Benefits:** simpler ops, one plan to manage.
- **Risks:** test and prod share plan-level limits; noisy test workloads could affect prod (usually minor at current volume).

**Recommendation:** Merge when convenient for housekeeping; **not a priority for cost reduction**.

---

## 6. Other NutraSync resources (mid-June additions)

Created ~June 12–17 (same week as DB cleanup):

| Resource | MTD cost | Notes |
|----------|----------|-------|
| `sb-forecast-tool` (Service Bus Standard) | $0.28 | ~$10/mo base going forward |
| `stforecasttool` (Storage) | $0.29 | Minimal |
| `appi-forecast-tool` (App Insights) | (in Functions/monitor) | Usage-based |
| `logic-forecast-scheduler` | Minimal | |

These are **not** the cause of the $400+ jump.

---

## 7. Right-size recommendation (5–6 users, ~41 MB data)

**Problem:** General Purpose **Serverless Gen5** is priced for variable production load with auto-pause. Your workload prevents pause:

- Function App **ACCS sales order sync every 2 hours** hits `nutraaxis`
- **Staging DB sync daily at 3 AM** (currently targets deleted `nutraaxis_test` — disable or fix)
- Portal use by 5–6 users

You pay **Gen5 serverless rates 24/7** while getting no benefit from pause. **Data size (41 MB) does not reduce the bill.**

### Recommended tier: **Standard S1** (~$30/month)

| Tier | Est. $/month | Notes |
|------|--------------|-------|
| **Standard S1** (20 DTU) | **~$30** | **Recommended** — 5–6 users + periodic batch jobs |
| Standard S0 (10 DTU) | ~$15 | Budget option; watch ACCS sync duration |
| Standard S2 (50 DTU) | ~$75 | If S1 sync jobs run slow or timeout |
| GP Serverless Gen5 (current) | ~$100–180 | Poor fit when timers keep DB awake |
| Basic | ~$5 | Too small for Function App batch + 10 min query timeouts |
| GP Gen5 1 vCore provisioned | ~$150 | Only if DTU tiers timeout on heavy sync |

### Apply in Azure Portal

SQL database `nutraaxis` → **Compute + storage** → **Standard** → **S1** → save.

Or CLI (brief reconnect; run off-peak):

```bash
az sql db update -g NutraSync -s nutraaxisdb01 -n nutraaxis \
  --edition Standard --service-objective S1 \
  --backup-storage-redundancy Local
```

`Local` backup redundancy is fine for a single-region ops DB and saves vs Geo.

### Also fix

1. **Disable staging DB sync** on prod Function App until a staging DB exists again — `STAGING_SYNC_SCHEDULE` is `0 0 3 * * *` but `DB_NAME_STAGING=nutraaxis_test` was deleted.
2. **Watch July daily SQL cost** after tier change — target **~$1/day** on S1 vs ~$3–6/day on serverless.

---

## 8. Action checklist

- [x] Verify cost by service — SQL dominates; App Service secondary
- [x] Verify deleted DBs still on June invoice — `labresults`, `nutraaxis_staging`, `nutraaxis_test`
- [ ] **Downgrade `nutraaxis` to Standard S1** (see section 7)
- [x] **Disable staging-db-sync** — removed (nutraaxis_test deleted)
- [ ] **Watch July SQL daily** after tier change
- [ ] Decide: keep Premium + staging slot vs downgrade and drop slot
- [ ] Optional: merge Flex Consumption plans (ops cleanup, not cost)

---

## 9. Answers to original questions

| Question | Answer |
|----------|--------|
| Did deleting databases *cause* the spike? | **No.** Deletion stops future billing; June still includes pre-deletion days. |
| Why did total cost jump mid-June? | **SQL from 3–4 databases** running at serverless rates; **`labresults` alone = $215 MTD**. |
| Is SQL still expensive going forward? | **Moderate** — only `nutraaxis` remains; expect **much lower** July bill vs June. |
| Are Function Apps / Premium ASP the problem? | **No** for June spike (~$45 App Service + ~$0.17 Functions vs ~$421 SQL). |
