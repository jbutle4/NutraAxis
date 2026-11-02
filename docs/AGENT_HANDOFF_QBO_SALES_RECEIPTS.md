# QBO Sales Receipt Cutover — Agent Handoff

**Status:** Paused — waiting on accounting to update production QBO sales form settings and pass-through tax item mapping.  
**Production app:** `nutraaxisweb` (FTP deploy) · **Function app:** `Nutra-forecast-tool-prod`  
**Last validated:** 2026-08-16 — QBO sales receipt total **$831.20** = ACCS grand total (29 cutover orders).

---

## Goal

Move production sales revenue from **InventoryAdjustments** to **Sales Receipts with Inventory items** (revenue + qty in one QBO transaction). IMS/`inventory-sales-sync` owns quantity adjustments only in production. ACCS remains source of truth for order totals, discounts, shipping, and tax collected.

---

## What was completed

### Cutover (one-time)

- **29** sale `InventoryAdjustment` rows reversed (`SaleReversal`, doc `NA-SAL-REV-{headerId}`) with audit `PrivateNote`.
- **29** original `Sale` sync rows marked `Superseded`.
- **29** Sales Receipts posted for cutover orders (headers ~119–150).
- `inventory-sales-sync` → **IMS-only** in production (no QBO adjustments on sale).
- SQL migration applied: `sql/145_alter_qbo_inventory_sync_log_sale_receipt.sql` (`SaleReceipt`, `SaleReversal`, `Superseded`).

### Mapper and payload fixes

**`functions/src/lib/qbo-order-mapper.js`**

- Line mapping uses **`RowTotal`**, **`DiscountAmount`**, **`OriginalPrice`/`UnitPrice`**, not `UnitPrice × Qty` alone.
- Header charges: **`HeaderTaxAmount`**, **`HeaderShippingAmount`**, **`HeaderDiscountAmount`** (SQL aliases to avoid JOIN `NaN`).
- `mapOrderCharges` reconciles merchandise, line discounts, order-level discount, shipping, tax → **grand total**.

**`functions/src/lib/qbo-sales-receipt.js`**

- Production uses **Inventory** items via `resolveInventoryItemId`.
- `voidAndDeleteSalesReceipt()` — sparse update `PrivateNote`, then QBO delete.
- Auto-create **Shipping** and **Sales Tax** service items when missing (interim; see target architecture below).
- Interim posting patterns used during cutover (to be removed after QBO settings + accounting mapping):
  - Net unit pricing (discounts embedded in rate).
  - Tax as **Sales Tax** service line (not QBO sales tax module).

### Jobs and scripts

| File | Purpose |
|------|---------|
| `functions/src/lib/jobs/accs-sales-receipt-sync.js` | Daily production sales receipt sync |
| `functions/src/lib/jobs/qbo-sales-cutover.js` | One-time cutover (done) |
| `functions/src/lib/jobs/qbo-sales-receipt-repost.js` | Void + repost with corrected mapper |
| `functions/src/lib/qbo-sales-sync-log.js` | Sync log helpers |
| `scripts/run-qbo-sales-receipt-repost.js` | Local repost runner |
| `scripts/validate-qbo-accs-revenue.js` | ACCS vs QBO total validation |
| `scripts/query-cutover-status.js` | Sync log status |

- Process codes registered: `accs-sales-receipt-sync`, `qbo-sales-receipt-repost` (portal + function app).
- Timer: `accs-sales-receipt-sync` daily ~3:30 AM on `Nutra-forecast-tool-prod`.

### Void and repost (live)

Two repost passes after mapper fixes:

1. **First pass** — fixed line amounts and shipping item; exposed tax not applying via `TxnTaxDetail`.
2. **Second pass** — tax as service line; **28/29** reposted in job; header **119** failed once (inactive item) but QBO receipt was correct; sync log updated to txn **149**.

**Final validation (`validate-qbo-accs-revenue.js`):**

| Metric | Value |
|--------|-------|
| Orders | 29 |
| ACCS grand total | $831.20 |
| QBO receipt total | $831.20 |
| Delta | $0.00 |
| Mismatches | 0 |

**Sync log:** 29 `SaleReceipt` / `Synced`, 29 `SaleReversal` / `Synced`, 0 pending receipts.

---

## Production QBO configuration gaps (accounting action)

Queried production preferences 2026-08-17:

| Setting | Current | Needed for clean integration |
|---------|---------|------------------------------|
| `AllowDiscount` | **false** | **true** — native line discounts for margin visibility |
| `AllowShipping` | **false** | **true** (or agreed service-item policy) |
| `UsingSalesTax` | **false** | **Keep false** — Avalara owns tax (see below) |
| Tax rates | **0** | Not required in QBO if Avalara files |

**Do not enable QBO sales tax / AST for filing** — tax reporting and filing are via **Avalara**. QBO should only mirror **tax collected** from ACCS (pass-through), including **$0** for B2B exempt orders.

### Agreed target architecture

```
ACCS → integration → QBO Sales Receipts (revenue, discounts, shipping, pass-through tax)
ACCS / Avalara → Avalara (rates, nexus, exempt, liability by state/entity, filing)
IMS + inventory-sales-sync → QBO qty via inventory items on receipts (production)
```

**Accounting before next integration pass:**

1. Enable **Discount** (and **Shipping** if using native field) on sales forms.
2. Create/map **pass-through tax** item → **liability or clearing account** (not product sales income). Provide `QBO_SALES_TAX_ITEM_ID_PROD` (and shipping item id if used).
3. Confirm B2B exempt flow: ACCS `TaxAmount = 0` → no tax on QBO receipt (no QBO tax engine).
4. SKU COA: Income + COGS + Inventory Asset on all sellable inventory SKUs.

---

## Integration work when resuming (after settings)

1. Remove workarounds in `qbo-sales-receipt.js`:
   - Netted unit prices → **catalog rate + line `DiscountAmt`** (or `DiscountLineDetail` only if discounts stay off).
   - Tax service line → **pass-through to accounting’s liability/clearing item** (exact ACCS `HeaderTaxAmount`).
   - No `TxnTaxDetail`, no rate lookup, no AST.
2. Use native **shipping** if `AllowShipping` enabled.
3. FTP-deploy updated function libs + portal process registry if not live.
4. **Repost** cutover receipts once (or spot-check) and re-run `validate-qbo-accs-revenue.js`.
5. Merge to `main` per `AGENTS.md` deploy-then-merge rules.

**Local changes not yet deployed:** see `git status` on `main` — mapper, sales receipt, jobs, SQL 145, scripts (uncommitted / unpushed).

---

## Key file paths

| Path | Role |
|------|------|
| `functions/src/lib/qbo-order-mapper.js` | ACCS → Sales Receipt mapping |
| `functions/src/lib/qbo-sales-receipt.js` | QBO create/void/delete |
| `functions/src/lib/qbo-sales-sync-log.js` | Sync log |
| `functions/src/lib/jobs/accs-sales-receipt-sync.js` | Daily job |
| `functions/src/lib/jobs/qbo-sales-receipt-repost.js` | Void + repost |
| `functions/src/lib/jobs/inventory-sales-sync.js` | IMS-only sales in prod |
| `sql/145_alter_qbo_inventory_sync_log_sale_receipt.sql` | Sync type migration |

---

## Cutover order scope

- **29** orders, `AccsSalesOrderHeaderID` ~119–150 (not every integer; e.g. 121–123 exist but may be outside cutover set).
- ACCS breakdown (approx.): merchandise **$523.63**, tax **$250**, shipping **$57.57**, grand **$831.20**.

---

## Errors resolved during work

| Issue | Resolution |
|-------|------------|
| `Amount ≠ UnitPrice × Qty` | Net/gross line amount rules; QBO validation |
| `DiscountAmt` ignored | `AllowDiscount` off in QBO; net pricing workaround |
| No Shipping item | Auto-create Service item `"Shipping"` |
| Tax not in total | `TxnTaxDetail` ineffective; interim Sales Tax service line |
| Historical txn date | `useTodayTxnDate` when `SaleReversal` exists |
| Header tax `NaN` on JOIN | SQL aliases `HeaderTaxAmount`, etc. |
| Duplicate customer name | Display name suffix with email/increment id |

---

## Resume checklist

- [ ] Accounting confirms QBO settings + tax/shipping item ids
- [ ] Integration updated per target architecture (no Avalara duplication in QBO)
- [ ] Deploy function app + portal process files
- [ ] Repost or validate 29 receipts → $831.20
- [ ] Merge `main`, push origin
