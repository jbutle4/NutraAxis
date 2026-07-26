# QBO Inventory Cycle — UAT E2E Checklist

**Environment:** Sandbox / UAT only  
**Portal:** https://operations.nutraaxislabs.com  
**QBO realm:** `9341457230168529` (Sandbox Company US 7988)  
**Function App:** `Nutra-forecast-tool` (sandbox) — run jobs via Process Log, not prod  
**Related:** [QBO Inventory Cycle Runbook](./QBO_INVENTORY_CYCLE_RUNBOOK.md)

Use this checklist for a full end-to-end pass of inventory use cases while `QBO_ENVIRONMENT=sandbox`.  
Suggested golden SKUs: `NA-MT-004`, `NA-HR-006` (or a dedicated UAT pair).

---

## 0. Preconditions

- [ ] Branch deployed to **nutraaxisweb** and sandbox **Nutra-forecast-tool**
- [ ] Accounting → QuickBooks connected (sandbox)
- [ ] App settings on Web App (and sandbox Function App as needed):
  - `QBO_ENVIRONMENT=sandbox`
  - `QBO_INV_ADJUST_ACCOUNT_ID_SANDBOX=85`
  - `QBO_INV_ASSET_ACCOUNT_CART_SANDBOX=1150040000`
  - `QBO_INV_ASSET_ACCOUNT_WPC_SANDBOX=1150040001`
  - `QBO_INV_ASSET_ACCOUNT_CPPC_SANDBOX=1150040002`
- [ ] Inventory timers disabled on test Function App (`0 0 0 1 1 2099`) — use Process Log **Run**
- [ ] Process Log → **QuickBooks Chart of Accounts Sync** completed recently
- [ ] Do **not** run this checklist against `Nutra-forecast-tool-prod` until `*_PROD` account Ids are filled

**SQL — connection / realm**

```sql
SELECT TOP 1 RealmID, CompanyName, Environment, AccessTokenExpiresAt
FROM dbo.QBOConnection
WHERE Environment = N'sandbox'
ORDER BY ConnectionID DESC;
-- Expect RealmID = 9341457230168529
```

**SQL — sandbox asset accounts present**

```sql
SELECT QBO_AccountId, Name, FullyQualifiedName, Active
FROM dbo.QBO_COA
WHERE RealmID = N'9341457230168529'
  AND Name LIKE N'Inventory Asset%'
ORDER BY Name;
-- Expect Cart.com 1150040000, WPC WIP 1150040001, CPPC 1150040002 (names may be nested under Inventory Asset:)
```

---

## 1. SKU → Inventory @ 0

| | |
|--|--|
| **UI** | Product Catalog → SKU → QuickBooks inventory item / Convert to Inventory |
| **Also** | `/accounting/inventory.php` |

- [ ] Test SKUs are Type = Inventory in QBO
- [ ] QtyOnHand = 0 before ops posts (or note baseline)
- [ ] `SKUMaster.QBO_ItemID` / sync status = Synced

```sql
SELECT SKUCode, QBO_ItemID, QBO_SyncStatus, QBO_SyncedAt
FROM dbo.SKUMaster
WHERE SKUCode IN (N'NA-MT-004', N'NA-HR-006');
```

---

## 2. Receipt sync (+)

| | |
|--|--|
| **UI** | `/po-receiving/` — transmit / complete ASN for CART |
| **Job** | Process Log → **Inventory Receipt Sync** → Run |

- [ ] IMS CART OK increases by receipt qty
- [ ] QBO QtyOnHand increases by same qty
- [ ] Sync log DocNumber `NA-RCV-{por}-{line}` = Synced

```sql
-- IMS CART after receipt
SELECT SKUCode, FacilityCode, QtyOK, QtyQuarantine, QtyOnHold
FROM dbo.InvCurrentBalance
WHERE SKUCode IN (N'NA-MT-004', N'NA-HR-006')
  AND FacilityCode = N'CART';

SELECT DocNumber, SyncType, SKUCode, QtyChange, SyncStatus, QBO_TxnId, SyncError
FROM dbo.QBOInventorySyncLog
WHERE SyncType = N'Receipt'
ORDER BY CreateDate DESC;
```

---

## 3. Sales sync (−)

| | |
|--|--|
| **Data** | ACCS shipped/complete lines in `AccsSalesOrder*` (or apply `sql/123_seed_sandbox_sales_sync_smoke_order.sql`) |
| **Job** | Process Log → **Inventory Sales Sync** → Run |

- [ ] IMS facility qty decreases
- [ ] QBO QtyOnHand decreases
- [ ] DocNumber `NA-SAL-{header}-{detail}` = Synced
- [ ] Re-run skips already-Synced lines (no double IMS decrement)

```sql
SELECT DocNumber, SyncType, SKUCode, QtyChange, SyncStatus, QBO_TxnId, SyncError
FROM dbo.QBOInventorySyncLog
WHERE SyncType = N'Sale'
ORDER BY CreateDate DESC;
```

---

## 4. Adjustment (shrink / gain)

| | |
|--|--|
| **UI** | `/inventory-adjustments/` → New → Pending → **Approve & post** |
| **Also** | Reject path; **Retry QBO** if SyncStatus = Error |

- [ ] Approve posts IMS `AdjustmentLoss` / `AdjustmentGain`
- [ ] QBO InventoryAdjustment DocNumber `NA-ADJ-{id}` = Synced
- [ ] Reject leaves IMS and QBO unchanged
- [ ] Retry after Error does not double-post IMS

```sql
SELECT TOP 5 AdjustmentID, SKUCode, FacilityCode, Direction, Qty, Status, CreateDate
FROM dbo.InvAdjustment
ORDER BY AdjustmentID DESC;

SELECT DocNumber, SyncStatus, QBO_TxnId, SyncError
FROM dbo.QBOInventorySyncLog
WHERE SyncType = N'Adjustment'
ORDER BY CreateDate DESC;
```

---

## 5. Transfer + Journal Entry

| | |
|--|--|
| **UI** | `/inventory-transfers/` → New (e.g. CART → WPC_QUEUE) → Ship → Receive |
| **Retry** | Transfer view → **Retry QBO journal** if needed |

- [ ] IMS: from ↓, to ↑ (via TRANSIT if configured); status Received
- [ ] Company-wide QBO QtyOnHand **unchanged** for same SKU
- [ ] DocNumber `NA-XFER-{id}` = Synced (Journal Entry between Cart.com ↔ WPC/CPPC asset accounts)

```sql
SELECT TOP 5 TransferID, SKUCode, FromFacilityCode, ToFacilityCode,
       QtyRequested, QtyShipped, QtyReceived, TransferStatus
FROM dbo.InvTransfer
ORDER BY TransferID DESC;

SELECT DocNumber, SyncStatus, QBO_TxnId, SyncError, FacilityCode
FROM dbo.QBOInventorySyncLog
WHERE SyncType = N'TransferJE'
ORDER BY CreateDate DESC;

SELECT FacilityCode, QtyOK
FROM dbo.InvCurrentBalance
WHERE SKUCode = N'NA-MT-004'
  AND FacilityCode IN (N'CART', N'TRANSIT', N'WPC_QUEUE', N'WPC_WIP', N'CPPC')
ORDER BY FacilityCode;
```

---

## 6. Movement completeness recon (Layer 1)

| | |
|--|--|
| **Job** | Process Log → **Inventory Movement Completeness Recon** → Run |
| **UI** | `/inventory-movement-recon/` |

- [ ] Run Success
- [ ] Action-needed rows only for real gaps (missing IMS, QBO Error, approved-unposted adj)
- [ ] Clean golden-path window → 0 unexpected exceptions

```sql
SELECT TOP 5 ReconRunID, Status, ExceptionCount, CreateDate
FROM dbo.InventoryMovementReconRun
ORDER BY ReconRunID DESC;

SELECT TOP 50 LineType, SKUCode, DocNumber, Severity, RecommendedAction
FROM dbo.InventoryMovementReconLine
WHERE ReconRunID = (SELECT MAX(ReconRunID) FROM dbo.InventoryMovementReconRun)
ORDER BY Severity DESC, LineType, SKUCode;
```

---

## 7. Jazz vs IMS CART (Layer 2 mothership)

| | |
|--|--|
| **UI** | `/inventory-jazz-ims-recon/` — Jazz **UAT**, mismatches filter as needed |

- [ ] Jazz facility resolves via `Facility.ExternalReferenceCode` (CART ← `FBF09`)
- [ ] Rows compare Jazz `on_hand_quantity` vs IMS CART OK+Q+H
- [ ] Mismatches listed (expected before align if IMS was ops-only)

---

## 8. Jazz → IMS CART align

| | |
|--|--|
| **UI** | `/inventory-jazz-ims-align/` — dry run → type `ALIGN` → apply |

- [ ] Dry-run shows deltas; apply posts `JazzSyncReconcile` (IMS only)
- [ ] Negative Jazz OH clamped to target 0
- [ ] Post-align: Jazz vs IMS recon mismatches vs clamped targets = 0 (for Jazz-present SKUs)
- [ ] **QBO QtyOnHand unchanged** (do not bootstrap QBO from Jazz)

```sql
SELECT TOP 5 AlignRunID, Mode, Status, LinesPosted, CreateDate
FROM dbo.InventoryJazzImsAlignRun
ORDER BY AlignRunID DESC;

-- Spot-check CART vs prior QBO ops qty (QBO should still be ops-only)
SELECT b.SKUCode, b.QtyOK AS ImsCartOk
FROM dbo.InvCurrentBalance b
WHERE b.FacilityCode = N'CART'
  AND b.SKUCode IN (N'NA-MT-004', N'NA-HR-006');
```

---

## 9. IMS vs QBO financial recon (Layer 2)

| | |
|--|--|
| **UI** | `/inventory-qbo-recon/` — Show all / Mismatches only |

- [ ] All inventory SKUs match by Sku or `SKUMaster.QBO_ItemID` (0 missing on either side for catalog set)
- [ ] Large IMS − QBO deltas **expected** after step 8
- [ ] Ops focus: QBO qty ≈ receipt − sale ± adjustment only (e.g. MT-004 / HR-006)
- [ ] Banner / summary understood by reviewer — no force-match QBO to Jazz

```sql
-- Sync-log qty trail for golden SKUs
SELECT DocNumber, SyncType, SKUCode, QtyChange, SyncStatus, QBO_TxnId
FROM dbo.QBOInventorySyncLog
WHERE SKUCode IN (N'NA-MT-004', N'NA-HR-006')
  AND SyncStatus = N'Synced'
ORDER BY SKUCode, CreateDate;
```

---

## 10. Idempotency / failure recovery spot-checks

- [ ] Re-run Receipt Sync / Sales Sync → already Synced docs skipped
- [ ] Adjustment Retry QBO after Error → single IMS txn
- [ ] Transfer Retry QBO journal → single JE for `NA-XFER-{id}`
- [ ] Insufficient IMS qty on sale surfaces clearly (no silent negative if guarded)

---

## Pass criteria (UAT complete)

| Area | Pass |
|------|------|
| DocNumbers | At least one Synced each: `NA-RCV-*`, `NA-SAL-*`, `NA-ADJ-*`, `NA-XFER-*` |
| Idempotency | Second job runs do not double-post IMS/QBO |
| Jazz align | IMS CART matches Jazz; QBO unchanged |
| Recon | L1 clean for exercised window; L2 explains Jazz-aligned IMS vs ops QBO |
| Prod safety | No changes to `Nutra-forecast-tool-prod` inventory Ids until Monday COA Ids |

---

## Out of scope for this UAT pass

- Filling `*_PROD` account Ids / production cutover  
- Bootstrapping QBO QtyOnHand from Jazz  
- Enabling production inventory timers  

---

## Quick URL index

| Step | Path |
|------|------|
| Hub | `/inventory/` (Inventory Reporting) |
| Balances | `/inventory-balances/` |
| PO receiving | `/po-receiving/` |
| Process Log | `/process-log/` |
| Adjustments | `/inventory-adjustments/` |
| Transfers | `/inventory-transfers/` |
| Movement recon | `/inventory-movement-recon/` |
| Jazz vs IMS | `/inventory-jazz-ims-recon/` |
| Jazz → IMS align | `/inventory-jazz-ims-align/` |
| IMS vs QBO | `/inventory-qbo-recon/` |
| Accounting inventory | `/accounting/inventory.php` |
