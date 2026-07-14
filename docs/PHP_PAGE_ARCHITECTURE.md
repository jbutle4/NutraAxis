# NutraAxis PHP page architecture

Guide for building portal pages without the usual include/auth/404 footguns. Cursor also loads `.cursor/rules/php-page-architecture.mdc` when editing PHP.

## Stack

| Layer | Location | Role |
|-------|----------|------|
| Entry pages | Feature folders (`po-management/`, …) | One `.php` per URL |
| Bootstrap | `includes/init.php` | Session, auth refresh, shared UI helpers |
| Chrome | `head.php` / `header.php` / `footer.php` / `nav.php` | HTML shell + `operations.css` |
| Domain logic | `includes/<domain>.php` | Queries, validates, permissions |
| Hub renderer | `includes/module-hub.php` | Shared supply-chain hub landings |
| Config / DB | `env.php` → `database.php` → `db()` | Single PDO factory |

There is **no** front controller or router.

## Add a leaf module (checklist)

1. Create `/your-module/index.php` (and `new.php` / `edit.php` / `view.php` as needed).
2. `require` `init.php` at the correct `dirname` depth.
3. Add `includes/your-module.php` with `*_require_read|create|update|delete` and list/get/save helpers.
4. Register slug in `MODULE_PERMISSION_COLUMNS` (`includes/auth.php`).
5. Register card/href in `includes/app.php` (hub submodule array and/or `$appFunctions`).
6. Set `$pageTitle`, `$activeSlug`; render `head` → `header` → `<main>` → `footer`.
7. Prefer `render_list_page_header()` over hand-rolled breadcrumbs.
8. Use Post/Redirect/Get with `?notice=created|updated|…` for success flash.

## Add a hub

```php
<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/module-hub.php';

module_hub_render('your-hub-slug', 'your-hub-slug');
```

Hub must appear in `app_hub_slugs()`, `$appFunctions`, `$modulePages`, and `app_hub_submodules()`.

## Include path depth

| Page location | Bootstrap require |
|---------------|-------------------|
| `/po-management/index.php` | `dirname(__DIR__) . '/includes/init.php'` |
| `/sales-reporting/accs-order-report/index.php` | `dirname(__DIR__, 2) . '/includes/init.php'` |
| `/labeling-operations/templates/index.php` | `dirname(__DIR__, 2) . '/includes/init.php'` |

Wrong depth → fatal on first `require`.

## Shared vs do-not-copy

**Shared (use these):**

- `init.php`, `db()`, `auth_*` / domain `*_require_*`
- `head` / `header` / `footer`
- `render_list_page_header()`, `table_sort_*`, `table_actions_*`, `form_actions_*`
- `file-upload-dropzone-field.php` for upload UI (COA, product enrichment, PO, catalog, legal, TE, PO receiving, bids, provider signup)
- `attachment-storage.php` / `file-storage.php` for Azure Blob save/read/download (not per-page LOB copies)
- `module_hub_render()` for hub cards

**Upload storage standard:**

- All uploaded **documents** go to Azure Blob via `attachment_storage_*`. SQL stores metadata + `BlobPath` only (not `FileData` LOBs).
- Excel/CSV **import** temp files may stay local for parsing; do not treat them as long-term document storage.
- Encryption layers:
  1. **Platform:** Azure Storage encryption at rest (always on; optional CMK via Key Vault).
  2. **Application (sensitive docs):** `attachment_storage_save(..., ['encrypt' => true])` → AES-GCM via `file-crypto.php` before upload. Set `IsEncrypted = 1` on the row. Download through `attachment_storage_resolve_content()` so decryption is shared.
- Keys: `FILE_CRYPTO_ENCRYPTION_KEY` (preferred) or legacy `PROVIDER_SIGNUP_ENCRYPTION_KEY` (same key chain for tax/ACH + provider docs).
- Provider signup reseller certificates use encrypted blob (`domain` = `provider-signup`). Apply `sql/116_provider_signup_attachment_blob.sql`.
- Do **not** invent a seventh upload/storage helper — extend `file-upload-dropzone-field.php`, `attachment-storage.php`, and `file-crypto.php`.

**Still often cloned (be careful):**

- Thin `upload-attachment.php` / `attachment.php` endpoints per domain (they should call domain helpers that use `attachment_storage_*`)
- Hand-rolled `<table class="admin-table">` markup
- Spreadsheet **import** pages may keep a plain file input (not the attachment dropzone)

## Unfinished features

If a slug is registered in `app.php` before pages exist:

1. Add it to `app_nav_hidden_module_slugs()` so home/nav/hubs do not 404, **or**
2. Ship a hub/leaf page in the same change.

Optional feature includes (e.g. unified `approval.php` from another branch) must not hard-`require` from shared nav on `main`. Gate with `is_file()` (see `includes/accounting-nav.php`).

## Production vs UAT / sandbox

Hub cards show separate **Production** and **UAT** rows. Each integration must follow the **page data profile**, not a single site-wide toggle.

| Integration | Production page | UAT twin |
|-------------|-----------------|----------|
| Adobe Commerce | Forces `production` tenant/host | Forces `stage` (or `ADOBE_COMMERCE_UAT_ENVIRONMENT`) |
| Jazz OMS | `jazz_oms_use_environment('production')` + `JAZZ_*_PROD` (fallback `JAZZ_*`) | `…('uat')` + `JAZZ_UAT_*` (fallback `JAZZ_*`) |
| QuickBooks | `QBO_ENVIRONMENT=production` + reconnect if mismatch | Sandbox is a separate OAuth company (one connection in DB) |

Pattern:

1. Production leaf loads `includes/page-data-profile.php`.
2. UAT stub sets `$dataProfile = 'uat'` then requires the production leaf.
3. Internal links use `data_profile_page_path()` so sort/detail URLs stay on the same twin.
4. Do **not** flip `ADOBE_COMMERCE_ENVIRONMENT` in Azure hoping Production cards will move — they ignore that default when the profile is production.

CLI / scripts that need stage must call `data_profile_set('uat')` before Adobe helpers.

## Anti-patterns that cause runtime errors

| Mistake | Symptom |
|---------|---------|
| Forgot `init.php` | Undefined `auth_*` / chrome fatals |
| Wrong `dirname` depth | Failed opening required `includes/…` |
| Slug missing from `MODULE_PERMISSION_COLUMNS` | Always access denied for leaf |
| Hub href without folder | 404 from home card |
| Hard-require WIP include | Accounting / UAT fatals across many pages |
| Session write after init without reopen | Login/state bugs |
| UAT stub without `page-data-profile` on leaf | Banner/env clients stay on production |
| Relying on site-wide Adobe env alone | Production cards still showed stage data |

## Related docs

- `docs/DEVELOPMENT_LOG.md` — historical change log
- `docs/ROLE_PERMISSIONS_REVIEW.md` — IAM / CRUD letters
