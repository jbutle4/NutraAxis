# ACCS clinic provisioning — confirmed changes

**To:** Jeff Vo  
**From:** NutraAxis (Joe Butler)  
**Date:** 26 August 2026 (Jeff confirmed 27 August 2026)  
**Status:** Approved for implementation. Keep current step order. Only the two API changes below.

Jeff: all six questions are correct; question 4 was an endpoint clarification (`/assignProducts`, which we already use).

---

## Current automation (unchanged order)

We already create the company admin first, then the company, then catalog/content/roles.

### A. Create Clinic Store

1. Create or locate the company admin customer (`POST /V1/customers` or `GET /V1/customers/search`). Remember the customer ID.
2. `POST /V1/company/` with that customer as `super_user_id`. Remember the company ID.
3. `PUT /V1/company/{companyId}` to set sales representative and `customer_group_id = 4` (Practitioner). Practitioner is already ID 4 in Dev, Stage, and Production.
4. `POST /V1/company/setCustomAttributes` for `clinic-type`.

### B. Clinic configuration

5. `POST /V1/sharedCatalog` named `SC-{CompanyName}` (reuse if that name already exists).
6. `GET /V1/sharedCatalog/1/categories` (master catalog ID 1).
7. `POST /V1/sharedCatalog/{newId}/assignCategories`.
8. `GET /V1/sharedCatalog/1/products`.
9. `POST /V1/sharedCatalog/{newId}/assignProducts`.
10. `POST /V1/sharedCatalog/{newId}/assignCompanies` with the new company ID. **← we will stop doing this.**
11. `POST /V1/company/role` (or `PUT` if the role name already exists) for each template role, including **Affiliated Patients**. Roles are cloned from our `Clinic_Template` company: Default User, Owner, Company_Admin, Provider, Affiliated Patients.

---

## Proposed changes (minimal)

Keep the order above. Only these modifications:

| # | Change | Why |
|---|--------|-----|
| 1 | **Stop** `POST /V1/sharedCatalog/{id}/assignCompanies` (current step 10 / original Jeff step 9). | Jeff: do not use original step 9. Catalog is no longer joined to the company that way. |
| 2 | **Add** after the shared catalog exists: `PUT /V1/customers/{companyAdminId}` and set custom attribute `patient_shared_catalog_id` to the new shared catalog ID (inside `custom_attributes`). | Jeff new step 9. This is the new catalog link. Must run **after** catalog create so we have the ID. Placement after products (after current step 9) is fine. |
| 3 | **Keep** Practitioner group ID 4 on the company at create/`PUT` (current step 3). We will **not** move this to the end unless Jeff says the company must stay off Practitioner until the catalog is on the admin. | Already ID 4 in all environments. Moving it is an order change we would rather avoid. |

No other endpoint or sequence changes unless Jeff requires them below.

---

## Resulting sequence (if you agree)

1. Create/locate company admin — remember customer ID.  
2. `POST /V1/company/` — remember company ID.  
3. `PUT /V1/company/{id}` — sales rep + Practitioner group 4.  
4. Set `clinic-type` on the company.  
5. `POST /V1/sharedCatalog` as `SC-{CompanyName}`.  
6. Copy categories from master catalog 1 (`assignCategories`).  
7. Copy products from master catalog 1 (`assignProducts`).  
8. **`PUT /V1/customers/{adminId}`** — `patient_shared_catalog_id` = new catalog ID.  
9. Create/update company roles (including Affiliated Patients).  

**Removed:** `assignCompanies`.

---

## What we are intentionally not changing (unless you say otherwise)

- **Step order** of admin → company → catalog → categories → products → roles.  
- **Product assign path:** we keep `POST /V1/sharedCatalog/{id}/assignProducts` rather than switching to `POST /V1/sharedCatalog/{id}/products`.  
- **Catalog name prefix:** we keep `SC-` (no space). You used `SC – ` in the note; treat as the same convention unless the exact string matters.  
- **Role source:** we keep cloning the five roles from `Clinic_Template` via `POST`/`PUT /V1/company/role`.  
- **Admin customer group:** we still put the admin in Practitioner (group 4) when the customer is created or found.  
- **Master catalog ID 1** for category/product copy.

---

## Jeff’s answers (27 Aug 2026)

1. **`assignCompanies`** — Never call on new clinic catalogs. Existing clinics already assigned that way can stay as-is. **Correct.**
2. **`patient_shared_catalog_id`** — Company admin customer only; value is the shared catalog ID. Affiliated Patients / other customers are set later in ACCS, not by this automation. **Correct.**
3. **Practitioner group** — Setting `customer_group_id = 4` at company create is OK. Those two fields are independent. **Correct.**
4. **Products endpoint** — Keep `POST …/assignProducts`. The earlier `/products` path was a mistake. **No code change.**
5. **Roles timing** — After the catalog is OK. **Correct.**
6. **Catalog name** — `SC-{CompanyName}` is fine. **Correct.**

---

## Example payloads (new/changed calls only)

**Stop calling**

```http
POST /V1/sharedCatalog/{sharedCatalogId}/assignCompanies
```

**Add**

```http
PUT /V1/customers/{customerId}
```

```json
{
  "customer": {
    "id": 39,
    "email": "clinic-admin@example.com",
    "firstname": "Jane",
    "lastname": "Admin",
    "custom_attributes": [
      {
        "attribute_code": "patient_shared_catalog_id",
        "value": "6"
      }
    ]
  }
}
```

We will GET the customer first and merge `custom_attributes` so we do not wipe existing attributes (for example `phone_number`).
