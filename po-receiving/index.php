<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/po-receiving.php';

por_require_read();

$activeSlug = 'po-receiving';
$hubBack = app_module_hub_back_link('po-receiving');
$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');
$listFilters = [
    'status' => $statusFilter !== '' ? $statusFilter : null,
    'q'      => $search !== '' ? $search : null,
] + table_sort_state(POR_LIST_SORT_COLUMNS, 'scheduled', 'desc', $_GET);
$receipts = por_list($listFilters);
$notice = $_GET['notice'] ?? null;

$pageTitle = 'PO Receiving | Inbound & Receiving';
$pageDescription = 'Advanced shipping notices and purchase order receiving.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="<?= htmlspecialchars($hubBack['href']) ?>">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        <?= htmlspecialchars($hubBack['label']) ?>
      </a>

      <div class="admin-header">
        <div>
          <div class="section-label">Supply Chain</div>
          <h1>PO Receiving</h1>
          <p class="page-lead">Schedule and record inbound shipments against purchase orders.</p>
          <p class="permission-note">Your access: <?= htmlspecialchars(permission_label(po_permission_value())) ?></p>
        </div>
        <div class="admin-actions">
          <a class="btn-secondary" href="/po-receiving/jazz-asns.php">Jazz ASNs</a>
          <?php if (por_can_create()): ?>
          <a class="btn-primary" href="/po-receiving/new.php">New Receipt</a>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">Receipt created successfully.</div>
      <?php elseif ($notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">Receipt updated successfully.</div>
      <?php elseif ($notice === 'deleted'): ?>
      <div class="admin-notice is-success" role="status">Receipt deleted successfully.</div>
      <?php elseif ($notice === 'attachment'): ?>
      <div class="admin-notice is-success" role="status">Attachment uploaded successfully.</div>
      <?php endif; ?>

      <form class="po-filter audit-filter" method="get" action="/po-receiving/">
        <?php table_sort_hidden_inputs($listFilters, 'scheduled', 'desc'); ?>
        <div class="audit-filter-grid">
          <div>
            <label for="status">Status</label>
            <select class="form-input" id="status" name="status">
              <option value="">All statuses</option>
              <?php foreach (POR_STATUSES as $status): ?>
              <option value="<?= htmlspecialchars($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="audit-filter-wide">
            <label for="q">Search</label>
            <input class="form-input" type="search" id="q" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="PO number, Jazz ASN, supplier, or delivery address" />
          </div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Apply Filters</button>
          <a class="btn-secondary" href="/po-receiving/">Clear</a>
        </div>
      </form>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <?php table_sort_render_head_row(
                POR_LIST_SORT_COLUMNS,
                '/po-receiving',
                $listFilters,
                ['status', 'q'],
                [],
                'scheduled',
                'desc',
                'scheduled',
                table_actions_header(por_can_update() ? ['View', 'Edit'] : ['View'])
            ); ?>
          </thead>
          <tbody>
            <?php if ($receipts === []): ?>
            <tr><td colspan="8">No receipts match your filters.</td></tr>
            <?php else: ?>
            <?php foreach ($receipts as $receipt): ?>
            <tr>
              <td><a class="btn-text" href="/po-management/view.php?id=<?= (int) $receipt['POID'] ?>"><?= htmlspecialchars($receipt['PONumber']) ?></a></td>
              <td><?= htmlspecialchars($receipt['JazzASN'] ?? '—') ?></td>
              <td><?= htmlspecialchars($receipt['SupplierName']) ?></td>
              <td><span class="status-badge <?= por_status_class($receipt['PORStatus']) ?>"><?= htmlspecialchars($receipt['PORStatus']) ?></span></td>
              <td><?= htmlspecialchars(por_format_scheduled($receipt['ScheduledReceiptDate'] ?? null, $receipt['ScheduledReceiptTime'] ?? null)) ?></td>
              <td><?= htmlspecialchars(por_format_date($receipt['ActualReceiptDate'] ?? null)) ?></td>
              <td><?= !empty($receipt['AppointmentMade']) ? 'Yes' : 'No' ?></td>
              <?php
              $actions = [
                  ['href' => '/po-receiving/view.php?id=' . (int) $receipt['PORID'], 'label' => 'View'],
              ];
              if (por_can_edit($receipt)) {
                  $actions[] = ['href' => '/po-receiving/edit.php?id=' . (int) $receipt['PORID'], 'label' => 'Edit'];
              }
              table_actions_cell($actions);
              ?>
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
