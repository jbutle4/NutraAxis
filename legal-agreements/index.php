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
$contracts = legal_list_contracts($listFilters);
$notice = $_GET['notice'] ?? null;

$pageTitle = 'Contract Register | Legal Agreements';
$pageDescription = 'View and manage legal agreements and contracts.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      $listToolbar = legal_can_create() ? '<a class="btn-primary" href="/legal-agreements/new.php">New Contract</a>' : '';
      render_list_page_header([
          'back_href'  => '/',
          'back_label' => 'Back to Operations Home',
          'category'   => 'Legal',
          'title'      => 'Contract Register',
          'lead'       => 'Track agreements, counterparties, renewal dates, and contract status across NutraAxis operations.',
          'permission' => permission_label(legal_permission_value()),
      ]);
      ?>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">Contract created successfully.</div>
      <?php elseif ($notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">Contract updated successfully.</div>
      <?php elseif ($notice === 'deleted'): ?>
      <div class="admin-notice is-success" role="status">Contract deleted successfully.</div>
      <?php endif; ?>

      <form class="po-filter audit-filter page-list-filters" method="get" action="/legal-agreements/">
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

      <?php render_list_page_toolbar($listToolbar !== '' ? $listToolbar : null); ?>

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
