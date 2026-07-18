# QBO Inventory Cycle — Sandbox Cutover Runbook

**Status:** Active  
**Environment:** QBO Sandbox / UAT only  
**Related:** [IMS Inventory Architecture](./IMS_INVENTORY_ARCHITECTURE.md), Accounting Ops v1.4

## Goal

Stand up company-wide QuickBooks Inventory quantity tracking at **QtyOnHand = 0**, with SQL IMS as the location ledger, and daily Function App jobs that post InventoryAdjustments for receipts and sales.

## Prerequisites

1. Sandbox company supports Inventory items (Plus/Advanced).
2. Create sandbox COA accounts:
   - Inventory Asset — Cart.com
   - Inventory Asset — WPC WIP
   - Inventory Asset — CPPC
   - An Expense (or COGS adjustment) account for InventoryAdjustment `AdjustAccountRef`
3. Portal + Function App: `QBO_ENVIRONMENT=sandbox`, connected to realm `9341457230168529`.
4. **Deploy this branch** before Process Log can show Inventory Receipt / Sales Sync:
   - App Service (PHP portal) — registers the processes and Process Log **Run** controls
   - Function App **`Nutra-forecast-tool`** (sandbox) — hosts `inventory-receipt-sync` and `inventory-sales-sync`
   - Until both are published, Process Log history will only show older jobs (e.g. QBO Chart of Accounts Sync, ACCS Sales Order Sync). Inventory Receipt Sync will not appear in **Registered Processes** or the history table.
5. Run SQL migrations in order:
   - `sql/067_create_accs_sales_order_tables.sql` (if not already applied)
   - `sql/117_create_qbo_coa.sql`
   - `sql/118_alter_sku_master_qbo_item_fields.sql`
   - `sql/119_create_ims_tables.sql`
   - `sql/120_alter_facility_integration_flags.sql`
   - `sql/121_seed_wpc_facilities_and_inventory_sync_log.sql`
   - `sql/122_create_inventory_movement_recon.sql`
   - `sql/123_seed_sandbox_sales_sync_smoke_order.sql` (optional — only if `AccsSalesOrder*` is empty for sales smoke)
   - `sql/124_alter_qbo_inventory_sync_log_adjustment_type.sql`
6. Run **QuickBooks Chart of Accounts Sync** (`qbo-coa-sync` — general ledger, not Certificate of Analysis) so Product Catalog account pickers populate.
7. Set Function App settings:
   - `QBO_INV_ADJUST_ACCOUNT_ID`
   - `QBO_INV_ASSET_ACCOUNT_CART` / `_WPC` / `_CPPC` (for transfer JEs)
   - `DB_NAME_INVENTORY_SYNC` (usually staging/test DB)

## Phase checklist

### 1. SKU → Inventory @ 0

1. On each Active SKU, set Income / Expense (COGS) / Asset account refs under **QuickBooks inventory item**.
2. Use **Convert to Inventory** (single) or **Convert All to Inventory** (bulk).
3. Confirm `/accounting/inventory.php` shows Type=Inventory and Qty on hand = 0.
4. Confirm Product Catalog `QBO_ItemID` / sync status = Synced.

### 2. IMS facilities

1. Open `/inventory-balances/` — facilities CART, CPPC, WLO, WPC_QUEUE, WPC_WIP, TRANSIT should exist.
2. PO receiving destination validates CART-only for supplier receipts.

### 3. Receipt sync (+)

1. Transmit a test ASN from `/po-receiving/`.
2. When Jazz ASN status is received/complete (or leave status null for sandbox force-path), open Process Log → **Registered Processes** → **Run** on **Inventory Receipt Sync** (or use **Run process** at the top, or wait for the 2:30 AM timer).
3. Expect: IMS CART qty increases; QBO QtyOnHand increases; `QBOInventorySyncLog` DocNumber `NA-RCV-{por}-{line}`.
4. If Run fails with `Unknown process code`, the Function App publish is missing — redeploy `functions/` to **Nutra-forecast-tool**.

### 4. Sales sync (−)

1. Ensure Accs sales order sync has shipped/complete lines in `AccsSalesOrder*`.
   - If the ACCS table is empty in sandbox, apply `sql/123_seed_sandbox_sales_sync_smoke_order.sql`
     (stage order `NA-SMOKE-SAL-001`, qty 2 of `NA-MT-004` / `NA-HR-006` against CART IMS from the ASN sim).
2. Run **Inventory Sales Sync**.
3. Expect: IMS facility qty decreases; QBO QtyOnHand decreases; log DocNumber `NA-SAL-{header}-{detail}`.
4. SalesReceipt financial insert must **not** attach Inventory ItemRefs (uses NonInventory or `QBO_SANDBOX_FALLBACK_ITEM_ID`).
5. Re-run is safe: Synced `QBOInventorySyncLog` rows skip; IMS `Sale` txn is not double-posted for the same header.

### 5. Transfers

1. Create transfer at `/inventory-transfers/` (CART → CPPC/WLO/WPC_*).
2. Ship (and receive if routed via TRANSIT).
3. Same-SKU company QtyOnHand unchanged; optional Journal Entry when asset account env vars are set.

### 5b. Adjustments (shrink / gain)

1. Open `/inventory-adjustments/` → **New adjustment**.
2. Choose SKU, facility, bucket, direction (Shrink − / Gain +), qty, and reason (e.g. `DAMAGE`).
3. Create as **Pending**, then **Approve & post**.
4. Expect: IMS `AdjustmentLoss`/`AdjustmentGain`; QBO InventoryAdjustment DocNumber `NA-ADJ-{id}`; sync-log Synced.
5. Reject leaves IMS/QBO untouched. QBO Error rows can **Retry QBO post** without double-posting IMS.

### 6. Reconciliation

1. **Movement completeness (Layer 1):** Run Process Log → **Inventory Movement Completeness Recon**, then open `/inventory-movement-recon/`.
2. Investigate Action-severity rows (missing IMS posts, QBO Error sync-log, approved-unposted adjustments).
3. **Jazz vs IMS CART (Layer 2 mothership):** Open `/inventory-jazz-ims-recon/` — Jazz `on_hand_quantity` at CART aliases (e.g. `FBF09`) vs IMS CART OK+quarantine+on hold.
4. **IMS vs QBO (Layer 2 financial):** Open `/inventory-qbo-recon/` for IMS company total vs QBO QtyOnHand.
5. Investigate mismatches before any production cutover.

## Production cutover (later — out of scope for sandbox build)

1. Set `QBO_ENVIRONMENT=production` and reconnect production realm.
2. Re-run Inventory conversion against live company.
3. Point Function App production timers + account Ids at production COA.
4. Do **not** bootstrap QBO qty from Jazz; keep opening QtyOnHand = 0 and let receipt/sales jobs accumulate.

## Failure recovery

| Symptom | Action |
|---------|--------|
| Sync blockers on SKU | Set three account refs; ensure QuickBooks Chart of Accounts Sync ran |
| Adjustment fails “item not inventory” | Run Convert to Inventory |
| Duplicate DocNumber | Already logged — safe no-op |
| Insufficient IMS qty on sale | Post opening/receipt first or investigate Jazz vs IMS |
| SalesReceipt errors on Inventory SKU | Set `QBO_SANDBOX_FALLBACK_ITEM_ID` NonInventory item |
