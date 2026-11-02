# ACCS clinic catalog maintenance

**Status:** Design only — do not implement until Ops asks to execute.  
**Date:** 4 September 2026  
**Audience:** Future agents and Ops (Joe / Jeff)

This is the working design for keeping **clinic shared catalogs** in sync after Create Clinic Store. It records Adobe behavior, Jeff Vo’s 4 Sep 2026 notes, the Advanced Pricing model, and what we should automate later.

Related:

- Clinic provision sequence: [`docs/ACCS_Clinic_Provisioning_Changes_For_Review.md`](ACCS_Clinic_Provisioning_Changes_For_Review.md)
- Signup / ACCS IDs: [`docs/AGENT_HANDOFF_PROVIDER_SIGNUP.md`](AGENT_HANDOFF_PROVIDER_SIGNUP.md)
- Assign + MSRP helper today: `includes/provider-signup-accs-config.php` (`provider_signup_accs_config_assign_catalog_contents`, `provider_signup_accs_config_set_catalog_prices_to_msrp`)

---

## 1. Problem

Adobe B2B shared catalogs are **independent allowlists**. Create Clinic Store copies Default (General) (catalog **1**) into `SC-{CompanyName}` **once**. After that:

- New SKUs are **not** auto-added to clinic catalogs.
- New categories are **not** auto-added to clinic catalog trees.
- Clinic **custom / shared-catalog** prices are a **snapshot** (we write MSRP at assign time). Later MSRP/Wholesale edits do **not** rewrite those rows.

We need a maintenance path that is safe at “thousands of clinic catalogs” and does **not** key off the `SC-` name.

---

## 2. Two “catalogs” (do not confuse them)

| Layer | What it is | Auto-updates? |
|-------|------------|----------------|
| **Product catalog** (global SKU) | Name, description, images, status, Cost, attributes, inventory, Magento `price` / `msrp` | **Yes**, everywhere the SKU is already assigned |
| **Shared catalog** (`Default (General)` and each clinic `SC-…`) | Which SKUs/categories are allowed, plus optional custom/tier prices | **No** membership or custom-price cascade between catalogs |

Assigning a category to a shared catalog does **not** add that category’s products. Product assign and category assign are separate API/Admin actions.

Disabled products (`status !== 1`) should stay out of new assigns (already our provision rule).

---

## 3. What Jeff clarified (4 Sep 2026)

Jeff: new products are not auto-added, but the Admin solve is small:

> There is a dropdown to pick which Shared Catalogs the product is tied to. Click **Select All** and the new product is assigned to all clinic shared catalogs.

That is **membership only**. Same outcome as `POST /V1/sharedCatalog/{id}/assignProducts` on every selected catalog.

**Do not** build a Function App whose only job is to replace Select All.

Select All does **not**:

- Write Advanced Pricing (Practitioner wholesale or `SC-` custom prices)
- Update prices on SKUs already assigned
- Add a **new category node** to each clinic catalog’s structure
- Limit targets to “clinic catalogs we provisioned” (it is every catalog in the tenant unless merchandising unchecks)

Admin assign surfaces (Jeff / Adobe):

- Product edit → **Product in Shared Catalogs**
- Product grid mass action
- **Set Pricing and Structure** on a shared catalog
- REST `POST /V1/sharedCatalog/:id/assignProducts`

---

## 4. Pricing model (confirmed from ACCS Advanced Pricing)

Wholesale is **not** a shared-catalog field. It is Advanced Pricing on **customer groups**.

Example SKU (screenshot 4 Sep 2026):

| Website | Group or catalog | Qty | Price |
|---------|------------------|-----|--------|
| All Websites [USD] | Wholesale | 1 | Fixed **$20.79** |
| All Websites [USD] | Practitioner | 1 | Fixed **$20.79** |
| All Websites [USD] | Practitioner-… (Not Exempt) | 1 | Fixed **$20.79** |
| All Websites [USD] | `SC-TestClinic…` | 1 | Fixed **$41.58** |
| All Websites [USD] | `SC-Morgan…` | 1 | Fixed **$41.58** |
| All Websites [USD] | `SC-Capo…` | 1 | Fixed **$41.58** |
| Cost (global) | — | — | **$9.45** |

Rules:

- **Wholesale** = Practitioner-group Advanced Price (qty 1, Fixed). Keep Wholesale and Practitioner-Not Exempt in sync when those groups should match.
- **Clinic storefront via `SC-…`** = that shared catalog’s custom/tier row (today **MSRP**, here 2× wholesale).
- **Cost** is global only; not a sell price.
- Create Clinic Store already writes the **catalog** layer: tier price for the catalog’s customer group = product `msrp` if set, else Magento `price`. It does **not** maintain the Practitioner wholesale row.

API equivalent for catalog prices: `POST /V1/products/tier-prices` with `customer_group` = the **shared catalog’s group code**, `quantity` 1, `price_type` `fixed`, `website_id` 0. See `provider_signup_accs_config_set_catalog_prices_to_msrp()`.

If a catalog has **no** custom row, Magento may fall through to Practitioner/Wholesale group price. After provision, clinic catalogs **do** have custom rows — later Practitioner edits will **not** move clinic PDP sell price.

---

## 5. How to identify clinic catalogs

**Do not** use `SC-` as the allowlist. Stage already has `SC - CapoCommerce`, patient/test catalogs, `Sales`, `Default (General)`.

**Do not** add a custom attribute on the shared catalog object. Adobe `SharedCatalogInterface` is not merchant EAV; a catalog flag would need a custom ACCS module.

**Source of truth:** `dbo.ProviderSignupApplication.AccsSharedCatalogId` where the app is **Provisioned**, scoped by `AccsEnvironment` (`stage` / `production` / `dev`).

Safety filters:

- Shared catalog `type` = custom (not public Default (General)) unless merchandising explicitly includes public
- Optional Ops checkbox later: “include in catalog push”

`SC-` remains a **display** convention only.

Cross-check (not primary): company admin `patient_shared_catalog_id` (Jeff’s catalog link). Do not use `assignCompanies` on new clinic catalogs.

Tenants must not mix. Same per-env rule as signup (`provider_signup_accs_with_environment`).

---

## 6. Workflows

### 6.1 New product (descriptions, Cost, Wholesale, MSRP, qty)

**Admin (required)**

1. Create and enable the product; set Cost, MSRP, descriptions, categories.
2. Advanced Pricing: Practitioner (and Wholesale / Practitioner-Not Exempt) = **wholesale**, qty 1 Fixed.
3. **Product in Shared Catalogs** — Select All, or a subset if non-clinic catalogs must be excluded.
4. If guests should see it: include **Default (General)**.

**What auto-cascades after assign:** name, description, images, Cost, status, inventory.

**What does not:** clinic `SC-` custom price. Select All does not create the $41.58-style rows.

**Automation (optional v1, recommended if clinic PDP must be MSRP):** action `assign_price` — for each `AccsSharedCatalogId`, if SKU is assigned and has **no** catalog custom price (or was just assigned), set custom price = current **MSRP**. Do **not** overwrite an existing clinic-specific price unless action is `reprice`.

**Skip** a Function App that only calls `assignProducts` for every catalog.

### 6.2 Price change on an existing product

**Admin**

- Cost → save on the product (global).
- Wholesale → Advanced Pricing **Practitioner** (+ Wholesale / Practitioner-Not Exempt).
- Clinic sell price / MSRP on `SC-…` → **does not** follow Practitioner. Either edit each catalog row (not viable at thousands) or run **`reprice`**.

**Automation:** action `reprice` — explicit, never a sticky product flag.

- Allowlist = provisioned `AccsSharedCatalogId` for that ACCS env.
- For SKUs already on the catalog, rewrite custom/tier price to current **MSRP** (or Wholesale if Jeff later changes the clinic sell-price rule).
- Do not assign SKUs or categories.

### 6.3 New Magento category + assign existing products on Default (General)

**Admin**

1. Create the category; assign SKUs; optionally add the category to Default (General).

**What auto-cascades:** nothing on clinic **trees**. SKUs already on a clinic catalog stay assigned but will **not** appear under the new node until that category ID is assigned to that catalog.

**Automation:** action `assign_category`

1. `assignCategories` for **that category ID only** (not the full master tree) on each clinic catalog.
2. Membership: default **navigation only** (do not add SKUs that were never on the clinic catalog).
3. Onboard extra SKUs only if they were Select All’d / flagged for clinic push; then assign + MSRP if new.

---

## 7. What to automate vs leave in Admin

| Work | Owner | Notes |
|------|--------|--------|
| Create SKU, Cost, copy, categories, enable | ACCS Admin | |
| Practitioner / Wholesale group price | ACCS Admin Advanced Pricing | One row, not per clinic |
| First-time SKU membership on many catalogs | ACCS Admin **Select All** | Jeff; no replace-job |
| Clinic `SC-` custom price = MSRP after new assign | **Function App / Ops** | Select All leaves this gap |
| Later clinic MSRP/wholesale catalog reprice | **Function App / Ops** | `reprice` |
| New category node on all clinic catalogs | **Function App / Ops** | `assign_category` |
| Exclude test/Sales/patient catalogs | Ops allowlist | `AccsSharedCatalogId` |

---

## 8. Suggested v1 when we execute

Do **not** start with a product attribute “Shared Catalog update” as the only trigger. A boolean cannot hold pending/done/failed, assign vs reprice vs category, or Stage vs Prod. Merchandising leaving it on would rewrite prices.

**Queue (Ops DB or Function App log), not a sticky flag:**

| Field | Values |
|-------|--------|
| Action | `assign_price` / `reprice` / `assign_category` |
| SKU(s) and/or category ID | |
| Environment | `production` / `stage` / `dev` |
| Status | `pending` / `running` / `done` / `failed` |
| Results | per catalog id: ok / skip / error |

**Triggers (in order of safety):**

1. Ops button: “Set MSRP on clinic catalogs for SKU” / “Add category to clinic catalogs” / “Reprice SKU” with preview (N catalogs).
2. Optional later: scheduled drain of the queue.
3. Optional later: product-save webhook enqueue. Not for go-live week.

**Kernel (reuse, do not rewrite):**

- `POST /sharedCatalog/{id}/assignCategories`
- `POST /sharedCatalog/{id}/assignProducts` (Enabled SKUs only)
- `provider_signup_accs_config_set_catalog_prices_to_msrp()`
- `provider_signup_accs_with_environment()`

Per-SKU/per-category, not a full remaster of Default (General) onto every clinic.

**v1 out of scope:** overwrite negotiated clinic prices without `reprice`; delete/unassign because a SKU left Default (General); push to every shared catalog in the tenant; Affiliated Patient catalogs (unless Jeff says so). Disable on the product is enough to hide a SKU that is already assigned.

---

## 9. Open decisions (confirm with Jeff before build)

1. After Select All, must clinic PDP sell price be **MSRP** (provision behavior) or Magento/Practitioner **wholesale** until someone sets catalog prices?
2. May Select All include Default (General), Sales, Capo, templates, patient/test catalogs, or must merchandising uncheck those?
3. `reprice` writes **MSRP** or **Practitioner wholesale** onto `SC-` rows?
4. New category job: navigation only, or also onboard SKUs not yet on the clinic catalog?
5. Are **all** Provisioned `AccsSharedCatalogId` in scope, or only Practitioner (group 4) clinics?
6. Opt-out checkbox on the application for “exclude from catalog push”?

---

## 10. Implementation sketch (when approved)

1. SQL: job/queue table + optional `CatalogPushOptOut` on `ProviderSignupApplication`.
2. PHP or Functions job: resolve allowlist by env → run action → persist per-catalog results.
3. Ops UI: pick SKU/category, action, env, preview, run, show failures, retry.
4. Docs: merchandising SOP — Select All for membership; Practitioner row for wholesale; Ops job for clinic MSRP / category tree / reprice.
5. Do **not** add an ACCS shared-catalog “Clinic Catalog” attribute.

---

## 11. Agent notes

- Company Advanced Settings (address book off, custom shipping off, quotes on, PO off, payment/shipping use config) are set in `includes/provider-signup-accs.php`, not copied from Clinic_Template. Omit address-book flags only if the tenant lacks Company Address Book modules.
- Email-challenge start insert must inline TTL minutes (PDO/sqlsrv + `DATEADD`); see `provider_signup_request_email_challenge()`.
- Company admin `clinic_id` must equal current company ID (doctor dropdown).
- Dual QBO work is unrelated; do not mix into this job.
