---
title: QBO accounts for ACCS sales receipts
subtitle: Tax accrual, shipping, and discounts
author: NutraAxis Operations / Systems
date: 20 August 2026
---

**To:** Accounting  
**Company:** NutraAxis Health Solutions Inc. (QuickBooks Online Plus)  
**Re:** Chart of accounts setup for ACCS sales receipts — tax collected as a liability (Avalara remittance)

---

## 1. Policy (already agreed)

| System | Role |
|--------|------|
| **Avalara** | Tax calculation, nexus, exemptions, **filing and remittance**. QBO sales tax / Automated Sales Tax stays **OFF**. |
| **ACCS (store)** | Source of truth for order **grand total**, line discounts, shipping charged, and **tax collected** (including **$0** for B2B tax-exempt). |
| **QBO** | Books only. Mirror the receipt. Accrue tax collected as a **liability**. Do not recalculate tax. |

B2B exempt orders: ACCS tax = $0 → QBO posts **no tax**. No QBO exempt-customer setup is required for the integration.

---

## 2. End-to-end transaction model

### A. Customer order (Sales Receipt in QBO)

Cash is collected (or lands in Undeposited Funds). The receipt splits as follows:

| Component | QBO treatment | P&L vs balance sheet |
|-----------|----------------|----------------------|
| Product at **list price** | Inventory item → **product revenue** | Income |
| **Line / order discount** | Native QBO discount (now enabled) → **Discounts given** (contra-revenue) | Income (reduces revenue) |
| **Shipping charged to customer** | Shipping charge → **Shipping Income** | Income |
| **Tax collected** (Avalara/ACCS amount) | Pass-through line → **liability** (new account, see below) | **Balance sheet only** |
| Inventory qty | Inventory item on the receipt | Dr COGS / Cr Inventory Asset (QBO inventory) |

**Simplified journal (illustrative)**

Example: list $100, discount $20, shipping $8, tax collected $7.20, customer pays **$95.20**.

| Account | Debit | Credit |
|---------|------:|-------:|
| Undeposited Funds (11100) | 95.20 | |
| Discounts given | 20.00 | |
| Product revenue | | 100.00 |
| Shipping Income | | 8.00 |
| **Sales tax collected (liability) — TO CREATE** | | **7.20** |

Net revenue recognized = $88 product after discount + $8 shipping = **$96**. Tax **$7.20** is **not** revenue.

If B2B exempt: tax line omitted; customer pays $88 + $8 = $96; **no liability**.

### B. Bank deposit

When the payment is deposited:

| Account | Debit | Credit |
|---------|------:|-------:|
| Operating bank | 95.20 | |
| Undeposited Funds (11100) | | 95.20 |

Liability is **unchanged**.

### C. Remittance to Avalara (clears the accrual)

When Avalara is paid for tax that was collected:

| Account | Debit | Credit |
|---------|------:|-------:|
| **Sales tax collected (liability)** | 7.20 | |
| Operating bank (or AP if billed first) | | 7.20 |

The liability **cancels out**. Matching collections vs remittance = **no P&L**.

### D. Avalara variance only (this is when 64700 is used)

If Avalara’s bill does not equal tax collected (rounding, true-up, under/over-collection):

- If Avalara **greater than** collected: debit **64700 Sales Tax (Expense)**, credit cash/AP (or credit liability if already accrued short).
- If Avalara **less than** collected: debit liability, credit **64700** (or other income) for the difference; remainder of liability cleared vs cash.

**64700 is residual tax expense, not the collection account.**

---

## 3. Accounts we have vs what we need

### Need — tax (gap)

| Need | Type | Why | Status |
|------|------|-----|--------|
| **Sales Tax Collected** (or Avalara Tax Clearing) | **Other Current Liability** (suggest 2xxxx, e.g. 25200) | Accrue tax on the receipt; reverse when remitting to Avalara | **MISSING** |

Without this account, collected tax cannot accrue and clear. It will hit P&L incorrectly.

### Have — related, but not the collection account

| Number / name | QBO Id | Type | Use | Do **not** use for |
|---------------|--------|------|-----|-------------------|
| **64700 Sales Tax** | 1150040045 | **Expense** (`TaxesPaid`) | Avalara **variance** / tax expense after remittance | Customer tax on Sales Receipts |
| 25100 Accrued Expenses | 1150040002 | Other Current Liability | Other accruals | Mixing with Avalara tax (keep separate) |
| Clinical Commission Payable | 1150040027 | Other Current Liability | Commissions | Tax |
| Due to FHM | 1150040041 | Other Current Liability | Intercompany | Tax |

### Have — sales receipt (OK to use; some remaps needed)

| Account | QBO Id | Type | Role on the sale |
|---------|--------|------|------------------|
| 11100 Undeposited Funds | 1150040023 | Other Current Asset | Default cash on Sales Receipt until deposited |
| Product revenue – Standard | 1150040030 | Income | Preferred product revenue (if SKUs point here) |
| Sales / Sales of Product Income | 14 / 15 | Income | Current SKU income on many items |
| **Discounts given** | 21 | Income (contra) | Native line/order discounts |
| **Shipping Income** | 22 | Income | Shipping charged to customer |
| Shipping Expense – Cart.com / CPPC | 1150040028 / 29 | COGS | **Outbound freight cost**, not customer shipping |
| Cost of Goods Sold | 16 | COGS | Inventory sold |
| Inventory / Inventory Asset – Cart.com, etc. | 12000 / 17 / facility assets | Asset | Qty on Sales Receipt |

### Products/services that must be remapped

| Item | Id | Mapped today | Should map to |
|------|----|----------------|---------------|
| **Sales Tax** (Service) | **43** | Income **Sales** (14) — **wrong** (books tax as revenue) | **New liability** Sales Tax Collected |
| **Shipping** (Service) | **42** | Income **Sales** (14) — **wrong** | **Shipping Income** (22) |

QBO Sales form **Discount** and **Shipping** are already **ON**. QBO **Sales tax** stays **OFF**.

---

## 4. Ask of accounting (setup)

1. **Create** an Other Current Liability account, e.g. **25200 Sales Tax Collected – Avalara**.
   - Not 64700.
   - Not Accrued Expenses (keep that for other accruals).

2. **Edit item “Sales Tax” (id 43)** so it posts to that **liability**.  
   If QBO will not let a Service item post to a liability, create the item/account combo QBO allows and send back the **item id**.

3. **Edit item “Shipping” (id 42)** so its income account is **Shipping Income** (id 22).

4. Confirm **64700** remains **expense** for Avalara true-up only.

5. Reply with:
   - New liability **account number + QBO account id**
   - Confirmed **Sales Tax item id** (43 or replacement)
   - Confirmation that Shipping item 42 → account 22

After that, systems will post ACCS tax as a pass-through accrual and leave Avalara as the only tax engine.

---

## 5. What systems will not do

- Turn on QBO sales tax or Automated Sales Tax
- Calculate rates, nexus, or exemptions in QBO
- File or remit from QBO
- Post $0-tax (exempt) orders with a tax line

---

## Summary

The full cycle is: **collect → accrue liability → deposit cash → remit to Avalara → liability clears**. Account **64700** is used only if Avalara’s bill does not equal tax collected.
