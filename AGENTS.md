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

## Active handoffs

- Dual QBO + Accounting UAT: see **`docs/AGENT_HANDOFF_DUAL_QBO.md`** before touching QBO/Accounting. Prefer **Local** agent for the remaining SQL migration.

## Out of scope / leave alone unless asked

- Draft QBO inventory work (e.g. PR #17 / `jbutle4/cursor/qbo-inventory-cycle-dee4`) — WIP; do not merge by default
- Blind-merge of every local `cursor/*` tip
