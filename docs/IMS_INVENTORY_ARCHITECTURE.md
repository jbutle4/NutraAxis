# NutraAxis IMS — Inventory Architecture

**Status:** Active  
**Last updated:** 2026-06-17  
**Related:** [Order Fulfillment Architecture](./ORDER_FULFILLMENT_ARCHITECTURE.md)

---

## Hub-and-spoke topology

Cart.com (Jazz OMS) is the **mothership** for all physical inventory. Supplier purchase orders and inbound ASNs land at **CART** only. Downstream locations (**CPPC**, **White Label Operations**) are replenished by **facility transfer from CART**, not by direct PO receipt.

```
Suppliers → PO / ASN → CART (mothership, Jazz)
                         ├─ transfer → CPPC
                         ├─ transfer → WLO
                         └─ sale ship → ACCS (Cart fulfillment lines)
CPPC / WLO → local IMS (sales, adjustments) — no Jazz sync
```

| Facility | Role | Receives POs | Integration | Ledger posting |
|----------|------|--------------|-------------|----------------|
| **CART** | Mothership / 3PL (`CART_COM`) | Yes | Jazz | After Jazz confirmation (ASN receive → `inventory-receipt-sync`) |
| **CPPC** | Spoke | No | Local | Immediate on portal workflow / sales sync |
| **WLO** | Spoke (White Label Ops) | No | Local | Immediate on portal workflow |
| **WPC_QUEUE** | WPC awaiting processing | No | Local | Transfer stage (SQL); QBO G/L via JE when configured |
| **WPC_WIP** | WPC work in progress | No | Local | Transfer stage (SQL); QBO G/L via JE when configured |
| **TRANSIT** | In-flight bucket | No | Local | Cleared when spoke receives |

See also [QBO Inventory Cycle Runbook](./QBO_INVENTORY_CYCLE_RUNBOOK.md) for sandbox cutover steps.

**Balance recon:** `/inventory-jazz-ims-recon/` compares live Jazz on-hand (facility → CART via `ExternalReferenceCode`) with IMS CART OK + quarantine + on hold.

**CART align:** `/inventory-jazz-ims-align/` posts `JazzSyncReconcile` to bring IMS CART in line with Jazz on-hand (IMS only; QBO stays on receipt/sales/adjustment accumulation).

---

## Facility flags (`dbo.Facility`)

| Column | Purpose |
|--------|---------|
| `IsMothership` | Primary physical hub (Cart.com). Only one active mothership. |
| `ReceivesPurchaseOrders` | Allowed destination for PO receiving / supplier ASNs. Only CART. |
| `IntegrationMode` | `Jazz` — external execution and confirmation required. `Local` — NutraAxis IMS is definitive. |
| `ExternalReferenceCode` | Optional Jazz warehouse code sent on ASN transmit when it differs from `FacilityCode`. |

Migration: `sql/097_alter_facility_integration_flags.sql`

---

## Validation rules (enforced in PHP)

### Purchase order receiving

- Destination facility must resolve to a row with `ReceivesPurchaseOrders = 1` (today: **CART** only).
- Attempts to receive at **CPPC**, **WLO**, or **TRANSIT** are rejected with guidance to use a transfer from Cart.com.
- Unmapped free-text Jazz warehouse codes are still allowed when they do not match a known non-PO facility row (legacy ASN compatibility).

Implementation: `facility_validate_po_receipt_destination()` in `includes/facility.php`, called from `por_save()`.

### Facility transfers

- **Spoke replenishment:** `FromFacility` must be the mothership (`IsMothership = 1`); destination must be a spoke or **TRANSIT**.
- **In-transit completion:** `TRANSIT → CPPC/WLO` is allowed; `TRANSIT → CART` is not.
- **No spoke-to-spoke supply:** CPPC and WLO cannot replenish each other.
- **No PO destinations as transfer targets from spokes:** inventory cannot be pushed into CART from a spoke via transfer.

Implementation: `facility_validate_transfer()` in `includes/facility.php`; used by `facility_insert_transfer()` for programmatic/API use ahead of a transfer UI.

---

## Transaction posting (target state)

| Event | Facility | Post when |
|-------|----------|-----------|
| Supplier receipt | CART | Jazz ASN confirmed received |
| Transfer ship | CART | Jazz confirms ship (TBD with Cart.com) |
| Transfer receive | CPPC / WLO | Portal receive confirmed (local) |
| Transfer in transit | TRANSIT | On ship from CART; clear on spoke receive |
| Sale | CART | Jazz / ACCS ship confirm |
| Sale | CPPC / WLO | Portal / ACCS line fulfillment |
| Adjustment | CART | Jazz import or controlled manual |
| Adjustment | CPPC / WLO | Approved portal adjustment |

---

## Open integration items

- [ ] Jazz adjustment event import → `InvTransaction` (`JazzSyncReconcile`)
- [ ] ASN confirmed received → `InvTransaction` (`POReceipt`)
- [ ] Cart.com transfer confirmation object (ship order vs ASN pair) for CART → spoke
- [ ] Transfer UI in Operations portal (validation layer is in place)
