<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/jazz-order-report.php';

jazz_order_report_require_read();

$activeSlug = 'jazz-orders';
$filters = jazz_order_report_filters_from_query($_GET);
$configError = jazz_oms_config_error();
$listResult = $configError === null
    ? jazz_oms_list_orders($filters)
    : ['ok' => true, 'error' => null, 'rows' => []];
$rows = $listResult['rows'] ?? [];
$columns = jazz_order_report_columns($rows);

$pageTitle = 'Jazz Orders | Supply Chain Management';
$pageDescription = 'Fulfillment order queue and status from Jazz OMS.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner page-inner--wide">
      <a class="breadcrumb" href="/inventory-management/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Supply Chain Management
      </a>

      <div class="admin-header">
        <div>
          <div class="section-label">Supply Chain</div>
          <h1>Jazz Orders</h1>
          <p class="page-lead">Order queue and fulfillment status from Jazz OMS production APIs.</p>
          <p class="permission-note">Your access: <?= htmlspecialchars(permission_label(inventory_reporting_permission_value())) ?></p>
        </div>
      </div>

      <form class="po-filter audit-filter" method="get" action="/jazz-orders/">
        <div class="audit-filter-grid">
          <div>
            <label for="status">Status</label>
            <select class="form-input" id="status" name="status">
              <option value="">All statuses</option>
              <?php foreach (JAZZ_ORDER_STATUSES as $status): ?>
              <option value="<?= htmlspecialchars($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="order_number">Order number</label>
            <input class="form-input" type="search" id="order_number" name="order_number" value="<?= htmlspecialchars($filters['order_number'] ?? '') ?>" placeholder="e.g. ORD1234" />
          </div>
          <div>
            <label for="po_number">PO number</label>
            <input class="form-input" type="search" id="po_number" name="po_number" value="<?= htmlspecialchars($filters['po_number'] ?? '') ?>" />
          </div>
          <div>
            <label for="start_date">Start date</label>
            <input class="form-input" type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($filters['start_date'] ?? '') ?>" />
          </div>
          <div>
            <label for="end_date">End date</label>
            <input class="form-input" type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($filters['end_date'] ?? '') ?>" />
          </div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Apply Filters</button>
          <?php if ($filters !== []): ?>
          <a class="btn-secondary" href="/jazz-orders/">Clear</a>
          <?php endif; ?>
        </div>
      </form>

      <?php if ($configError !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($configError) ?></div>
      <?php elseif (!$listResult['ok']): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($listResult['error']) ?></div>
      <?php else: ?>
      <div class="status-banner">
        <div>
          <strong>Jazz OMS connected</strong>
          <p>
            <?= count($rows) ?> order<?= count($rows) === 1 ? '' : 's' ?>
            loaded from <?= htmlspecialchars(jazz_oms_base_url() . jazz_oms_order_endpoint()) ?>
            · tenant <?= htmlspecialchars(jazz_oms_tenant_code()) ?>
            <?php if (!empty($filters['status'])): ?>
            · status <?= htmlspecialchars($filters['status']) ?>
            <?php endif; ?>
          </p>
        </div>
      </div>

      <div class="admin-table-wrap production-status-table-wrap">
        <table class="admin-table production-status-table">
          <thead>
            <tr>
              <?php foreach ($columns as $column): ?>
              <th><?= htmlspecialchars(jazz_oms_field_label($column)) ?></th>
              <?php endforeach; ?>
              <th>View</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($rows === []): ?>
            <tr><td colspan="<?= max(2, count($columns) + 1) ?>">No orders returned from Jazz OMS<?= $filters !== [] ? ' for the selected filters' : '' ?>.</td></tr>
            <?php else: ?>
            <?php foreach ($rows as $row): ?>
            <tr>
              <?php foreach ($columns as $column): ?>
              <td>
                <?php if ($column === 'status'): ?>
                <span class="status-badge <?= jazz_order_report_status_class((string) ($row[$column] ?? '')) ?>"><?= htmlspecialchars(jazz_oms_format_cell($row[$column] ?? null)) ?></span>
                <?php elseif ($column === 'order_number' && trim((string) ($row[$column] ?? '')) !== ''): ?>
                <a class="btn-text" href="<?= htmlspecialchars(jazz_order_report_detail_href((string) $row[$column])) ?>"><?= htmlspecialchars((string) $row[$column]) ?></a>
                <?php else: ?>
                <?= htmlspecialchars(jazz_oms_format_cell($row[$column] ?? null)) ?>
                <?php endif; ?>
              </td>
              <?php endforeach; ?>
              <?php table_actions_cell([
                  ['href' => jazz_order_report_detail_href((string) ($row['order_number'] ?? '')), 'label' => 'View'],
              ]); ?>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
