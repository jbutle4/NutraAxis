# NutraAxis Operations — Agent working rules

Rules for Cursor agents and humans working on this repo. Production is Azure App Service **`nutraaxisweb`**. Git push does **not** update live; deploy is FTP/FTPS.

## Deploy-then-merge (mandatory)

After any FTP deploy of branch work to `nutraaxisweb`:

1. Merge (or cherry-pick) that work onto **`main`** in the **same session** (or immediately after).
2. Push `main` to origin.
3. Do not leave live ahead of `main`.

If you cannot merge in the same session, do not deploy — or deploy only after the merge is ready to land.

## Pre-merge conflict scan (required before every merge to `main`)

Before merging any branch or PR into `main`:

1. List open PRs: `gh pr list --state open`
2. List local/remote branches that are ahead of `main`
3. For the candidate branch **and** each other open PR branch, dry-run a conflict check, for example:

   ```bash
   git fetch origin main <branch>
   git merge-tree --write-tree origin/main origin/<branch>
   # or: git merge-tree $(git merge-base origin/main origin/<branch>) origin/main origin/<branch>
   ```

4. If another open branch/PR would be **heavily conflicted or overwritten** — especially shared files like `includes/app.php` and `includes/auth.php` — then either:
   - merge/rebase that work first,
   - port only the unique pieces onto `main`, or
   - pause and report

   Do **not** silent-overwrite shared hub/auth wiring.

5. Prefer **one active production feature branch** at a time. Merge often. Do not start a new session on a different branch while critical UI remains unmerged / live-only.

## Deploy notes

- Credentials: local `.vscode/sftp.json` (gitignored). Scripts: `npm run upload`, `node scripts/ftp-upload-files.js <paths…>`
- Host/user documented in `docs/SYSTEM_APPRECIATION.md` §7
- SQL migrations: `node scripts/run-sql-file.js sql/<file>` with local `.env` (`DB_*`)
- Do **not** deploy `.env`, `.vscode/`, `node_modules/`, or `Archive Sites/`

## Portal navigation & breadcrumbs (mandatory)

Home cards, left nav, hub pages, and “Back to …” links all come from **`includes/app.php`**. Do not invent parallel nav. When adding or moving a module, update every layer in the same change.

### Source of truth

| Surface | Registry in `includes/app.php` |
|--------|---------------------------------|
| Home Supply Chain / Admin cards | `$appFunctions` + `group` (`supply-chain` / `admin`) |
| Hub child cards (Procurement, Product Master, …) | Hub submodule arrays (`$procurementSubModules`, `$productMasterSubModules`, …) wired through `app_hub_submodules()` |
| Left nav | Same hubs/leaves via `auth_filter_modules(app_functions())` + `nav_children_for_parent()` |
| Module → parent hub | Child `slug` must appear in exactly one hub submodule list so `app_hub_for_module_slug()` / `app_module_hub_back_link()` resolve correctly |
| Permissions | `MODULE_PERMISSION_COLUMNS` in `includes/auth.php` (leaf slug → role column) |

### Breadcrumbs and section labels

- List pages for hub children **must** use `app_module_hub_back_link('<leaf-slug>')` for the back link (href + “Back to {Hub}” label). Prefer the pattern in `procurement-bids/index.php` / `procurement-approvals/index.php`.
- Do **not** hardcode `/inventory-management/` (or any other hub) on a page that lives under a different hub. Example bug: Supplier Management under Procurement with “Back to Inventory Management”.
- Section label / category text must match the parent hub (e.g. `Procurement`, not `Inventory`).
- Detail/edit pages may breadcrumb to the module list; the **module list** breadcrumbs to the hub.

### Checklist when adding or relocating a module

1. Add/move the leaf in the correct hub submodule array (and remove it from any old hub).
2. If it should be a top-level home card, add/update `$appFunctions` (and `group`).
3. Ensure `MODULE_PERMISSION_COLUMNS` has the slug.
4. Point list-page breadcrumb at `app_module_hub_back_link($activeSlug)` (or the leaf slug).
5. Align section-label / `render_list_page_header` category with the hub.
6. Confirm table headers match body columns one-for-one (sort column maps in the module include).
7. For Accounting URLs, use `accounting_path()` only for `/accounting/…` routes — top-level folders like `/procurement-approvals/` must not be rewritten under `/accounting/`.
8. Smoke the hub card, left nav entry, and back link after deploy.

### Related helpers

- `app_module_hub_back_link(string $moduleSlug): array` — `{ href, label }`
- `module_hub_render($hubSlug, $activeSlug)` — hub landing cards
- `render_list_page_header([...])` — shared list header (pass hub-aware `back_href` / `back_label` / `category`)

## Active handoffs

- Dual QBO + Accounting UAT: see **`docs/AGENT_HANDOFF_DUAL_QBO.md`** before touching QBO/Accounting. Prefer **Local** agent for the remaining SQL migration.

## Out of scope / leave alone unless asked

- Draft QBO inventory work (e.g. PR #17 / `jbutle4/cursor/qbo-inventory-cycle-dee4`) — WIP; do not merge by default
- Blind-merge of every local `cursor/*` tip
