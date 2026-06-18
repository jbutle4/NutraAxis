<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/legal.php';

legal_require_read();

$activeSlug = 'legal-agreements';
$statusFilter = $_GET['status'] ?? '';
$typeFilter = $_GET['type'] ?? '';
$search = trim($_GET['q'] ?? '');
$listFilters = [
    'status' => $statusFilter !== '' ? $statusFilter : null,
    'type'   => $typeFilter !== '' ? $typeFilter : null,
    'q'      => $search !== '' ? $search : null,
] + table_sort_state(LEGAL_LIST_SORT_COLUMNS, 'contract_id', 'asc', $_GET);
$pageError = null;
try {
    $contracts = legal_list_contracts($listFilters);
} catch (Throwable $e) {
    error_log('legal-agreements list: ' . $e->getMessage());
    $contracts = [];
    $pageError = 'Unable to load contract data from SQL Server.';
    if (auth_can_access_site_admin()) {
        $pageError .= ' ' . $e->getMessage();
    }
}
$notice = $_GET['notice'] ?? null;

$pageTitle = 'Contract Register | Legal Agreements';
$pageDescription = 'View and manage legal agreements and contracts.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Operations Home
      </a>

      <div class="admin-header">
        <div>
          <div class="section-label">Legal</div>
          <h1>Contract Register</h1>
          <p class="page-lead">Track agreements, counterparties, renewal dates, and contract status across NutraAxis operations.</p>
          <p class="permission-note">Your access: <?= htmlspecialchars(permission_label(legal_permission_value())) ?></p>
        </div>
        <?php if (legal_can_create()): ?>
        <a class="btn-primary" href="/legal-agreements/new.php">New Contract</a>
        <?php endif; ?>
      </div>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">Contract created successfully.</div>
      <?php elseif ($notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">Contract updated successfully.</div>
      <?php elseif ($notice === 'deleted'): ?>
      <div class="admin-notice is-success" role="status">Contract deleted successfully.</div>
      <?php endif; ?>

      <?php if ($pageError !== null): ?>
      <div class="admin-notice is-error" role="alert"><?= htmlspecialchars($pageError) ?></div>
      <?php endif; ?>

      <form class="po-filter audit-filter" method="get" action="/legal-agreements.php">
        <?php table_sort_hidden_inputs($listFilters, 'contract_id', 'asc'); ?>
        <div class="audit-filter-grid">
          <div>
            <label for="status">Status</label>
            <select class="form-input" id="status" name="status">
              <option value="">All statuses</option>
              <?php foreach (LEGAL_CONTRACT_STATUSES as $status): ?>
              <option value="<?= htmlspecialchars($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="type">Contract type</label>
            <select class="form-input" id="type" name="type">
              <option value="">All types</option>
              <?php foreach (LEGAL_CONTRACT_TYPES as $type): ?>
              <option value="<?= htmlspecialchars($type) ?>" <?= $typeFilter === $type ? 'selected' : '' ?>><?= htmlspecialchars($type) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="audit-filter-wide">
            <label for="q">Search</label>
            <input class="form-input" type="search" id="q" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Contract ID, name, counterparty, or supplier" />
          </div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Apply Filters</button>
          <a class="btn-secondary" href="/legal-agreements/">Clear</a>
        </div>
      </form>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <?php table_sort_render_head_row(
                LEGAL_LIST_SORT_COLUMNS,
                '/legal-agreements',
                $listFilters,
                ['status', 'type', 'q'],
                LEGAL_LIST_SORT_NUMERIC,
                'contract_id',
                'asc',
                '',
                table_actions_header(legal_can_update() ? ['View', 'Edit'] : ['View'])
            ); ?>
          </thead>
          <tbody>
            <?php if ($contracts === []): ?>
            <tr><td colspan="8">No contracts match your filters.</td></tr>
            <?php else: ?>
            <?php foreach ($contracts as $contract): ?>
            <tr>
              <td><?= htmlspecialchars($contract['ContractNumber']) ?></td>
              <td><?= htmlspecialchars($contract['ContractName']) ?></td>
              <td><?= htmlspecialchars($contract['ContractType']) ?></td>
              <td><?= htmlspecialchars($contract['Counterparty']) ?></td>
              <td><span class="status-badge <?= legal_status_class($contract['ContractStatus']) ?>"><?= htmlspecialchars($contract['ContractStatus']) ?></span></td>
              <td><?= htmlspecialchars(legal_format_expiration($contract)) ?><?= !empty($contract['AutoRenewal']) ? ' · Auto-renew' : '' ?></td>
              <td><?= htmlspecialchars(legal_format_money($contract['AnnualValue'])) ?></td>
              <?php table_view_edit_cell(
                  '/legal-agreements/view.php?id=' . (int) $contract['ContractID'],
                  '/legal-agreements/edit.php?id=' . (int) $contract['ContractID'],
                  legal_can_update()
              ); ?>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
