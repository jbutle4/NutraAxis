<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/enhancement-log.php';

enhancement_log_require_read();

$activeSlug = 'enhancement-log';
$statusFilter = trim($_GET['status'] ?? '');
$typeFilter = trim($_GET['enh_type'] ?? '');
$productFilter = trim($_GET['it_product'] ?? '');
$search = trim($_GET['q'] ?? '');
$listFilters = [
    'status'     => $statusFilter !== '' ? $statusFilter : null,
    'enh_type'   => $typeFilter !== '' ? $typeFilter : null,
    'it_product' => $productFilter !== '' ? $productFilter : null,
    'q'          => $search !== '' ? $search : null,
] + table_sort_state(ENHANCEMENT_LOG_LIST_SORT_COLUMNS, 'request_date', 'desc', $_GET);
$entries = enhancement_log_list($listFilters);
$notice = $_GET['notice'] ?? null;

$pageTitle = 'IT Product Backlog | NutraAxis Operations';
$pageDescription = 'Track IT product backlog items, status, and due dates.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner page-inner--wide">
      <?php
      $listToolbar = enhancement_log_can_create() ? '<a class="btn-primary" href="/enhancement-log/new.php">New Backlog Item</a>' : '';
      render_list_page_header([
          'back_href'  => '/operations-dashboard/',
          'back_label' => 'Back to Operations Dashboard',
          'category'   => 'Operations',
          'title'      => 'IT Product Backlog',
          'lead'       => 'Track backlog items for ACCS, QBO, the Operations Portal, integrations, and other IT products.',
          'permission' => auth_module_permission_label('enhancement-log'),
      ]);
      ?>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">Backlog item created successfully.</div>
      <?php elseif ($notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">Backlog item updated successfully.</div>
      <?php endif; ?>

      <form class="po-filter audit-filter page-list-filters" method="get" action="/enhancement-log/">
        <?php table_sort_hidden_inputs($listFilters, 'request_date', 'desc'); ?>
        <div class="audit-filter-grid">
          <div>
            <label for="status">Status</label>
            <select class="form-input" id="status" name="status">
              <option value="">All statuses</option>
              <?php foreach (ENHANCEMENT_LOG_STATUSES as $status): ?>
              <option value="<?= htmlspecialchars($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>>
                <?= htmlspecialchars(enhancement_log_status_label($status)) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="enh_type">Type</label>
            <select class="form-input" id="enh_type" name="enh_type">
              <option value="">All types</option>
              <?php foreach (ENHANCEMENT_LOG_TYPES as $type): ?>
              <option value="<?= htmlspecialchars($type) ?>" <?= $typeFilter === $type ? 'selected' : '' ?>>
                <?= htmlspecialchars($type) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="it_product">IT product</label>
            <select class="form-input" id="it_product" name="it_product">
              <option value="">All products</option>
              <?php foreach (ENHANCEMENT_LOG_IT_PRODUCTS as $product): ?>
              <option value="<?= htmlspecialchars($product) ?>" <?= $productFilter === $product ? 'selected' : '' ?>>
                <?= htmlspecialchars($product) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="audit-filter-wide">
            <label for="q">Search</label>
            <input class="form-input" type="search" id="q" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Title, description, requester, type, product, or notes" />
          </div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Apply Filters</button>
          <a class="btn-secondary" href="/enhancement-log/">Clear</a>
        </div>
      </form>

      <?php render_list_page_toolbar($listToolbar !== '' ? $listToolbar : null); ?>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <?php table_sort_render_head_row(
                ENHANCEMENT_LOG_LIST_SORT_COLUMNS,
                '/enhancement-log',
                $listFilters,
                ['status', 'enh_type', 'it_product', 'q'],
                ['id'],
                'request_date',
                'desc',
                'request_date',
                table_actions_header(enhancement_log_can_update() ? ['View', 'Edit'] : ['View'])
            ); ?>
          </thead>
          <tbody>
            <?php if ($entries === []): ?>
            <tr><td colspan="11">No backlog items match your filters.</td></tr>
            <?php else: ?>
            <?php foreach ($entries as $entry): ?>
            <tr>
              <td><?= (int) $entry['EnhancementLogID'] ?></td>
              <td><?= htmlspecialchars((string) $entry['EnhancementTitle']) ?></td>
              <td><?= htmlspecialchars((string) ($entry['EnhType'] ?? '—')) ?></td>
              <td><?= htmlspecialchars((string) ($entry['ITProduct'] ?? '—')) ?></td>
              <td><?= htmlspecialchars((string) ($entry['Priority'] ?? '—')) ?></td>
              <td><?= htmlspecialchars((string) ($entry['Impact'] ?? '—')) ?></td>
              <td><?= htmlspecialchars((string) ($entry['RequestedBy'] ?? '—')) ?></td>
              <td><?= htmlspecialchars(enhancement_log_format_date((string) ($entry['RequestDate'] ?? ''))) ?></td>
              <td>
                <span class="status-badge <?= enhancement_log_status_class((string) $entry['RequestStatus']) ?>">
                  <?= htmlspecialchars(enhancement_log_status_label((string) $entry['RequestStatus'])) ?>
                </span>
              </td>
              <td><?= htmlspecialchars(enhancement_log_format_date((string) ($entry['ReqDueDate'] ?? ''))) ?></td>
              <?php table_view_edit_cell(
                  '/enhancement-log/view.php?id=' . (int) $entry['EnhancementLogID'],
                  '/enhancement-log/edit.php?id=' . (int) $entry['EnhancementLogID'],
                  enhancement_log_can_update()
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
