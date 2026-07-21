<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/bid-initiative.php';

bid_require_read();

$activeSlug = 'procurement-bids';
$hubBack = app_module_hub_back_link('procurement-bids');
$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');
$listFilters = [
    'status' => $statusFilter !== '' ? $statusFilter : null,
    'q'      => $search !== '' ? $search : null,
] + table_sort_state(BID_INITIATIVE_LIST_SORT_COLUMNS, 'modified', 'desc', $_GET);
$initiatives = bid_initiative_list($listFilters);
$notice = $_GET['notice'] ?? null;

$pageTitle = 'Initiatives & Bids | Procurement';
$pageDescription = 'Manage light RFPs and supplier bid estimates that do not become purchase orders.';

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
          <div class="section-label">Procurement</div>
          <h1>Initiatives & Bids</h1>
          <p class="page-lead">Create light RFPs, collect supplier estimates, and award a selected bid as a draft supplier invoice (no PO).</p>
          <p class="permission-note">Your access: <?= htmlspecialchars(permission_label(bid_permission_value())) ?></p>
        </div>
        <?php if (bid_can_create()): ?>
        <a class="btn-primary" href="/procurement-bids/new.php">New Initiative</a>
        <?php endif; ?>
      </div>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">Initiative created successfully.</div>
      <?php elseif ($notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">Initiative updated successfully.</div>
      <?php elseif ($notice === 'awarded'): ?>
      <div class="admin-notice is-success" role="status">Bid awarded. A draft/estimate supplier invoice was created.</div>
      <?php endif; ?>

      <form class="po-filter audit-filter" method="get" action="/procurement-bids/">
        <?php table_sort_hidden_inputs($listFilters, 'modified', 'desc'); ?>
        <div class="audit-filter-grid">
          <div>
            <label for="status">Status</label>
            <select class="form-input" id="status" name="status">
              <option value="">All statuses</option>
              <?php foreach (BID_INITIATIVE_STATUSES as $status): ?>
              <option value="<?= htmlspecialchars($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="audit-filter-wide">
            <label for="q">Search</label>
            <input class="form-input" type="search" id="q" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Number, title, or category" />
          </div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Apply Filters</button>
          <a class="btn-secondary" href="/procurement-bids/">Clear</a>
        </div>
      </form>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <?php table_sort_render_head_row(
                BID_INITIATIVE_LIST_SORT_COLUMNS,
                '/procurement-bids',
                $listFilters,
                ['status', 'q'],
                BID_INITIATIVE_LIST_SORT_NUMERIC,
                'modified',
                'desc',
                '',
                table_actions_header(bid_can_update() ? ['View', 'Edit'] : ['View'])
            ); ?>
          </thead>
          <tbody>
            <?php if ($initiatives === []): ?>
            <tr><td colspan="8">No initiatives match your filters.</td></tr>
            <?php else: ?>
            <?php foreach ($initiatives as $row): ?>
            <tr>
              <td><?= htmlspecialchars($row['InitiativeNumber']) ?></td>
              <td><?= htmlspecialchars($row['Title']) ?></td>
              <td><?= htmlspecialchars($row['Category'] ?? '—') ?></td>
              <td><span class="status-badge <?= bid_initiative_status_class((string) $row['Status']) ?>"><?= htmlspecialchars($row['Status']) ?></span></td>
              <td><?= $row['BudgetAmount'] !== null ? htmlspecialchars(accounting_format_money($row['BudgetAmount'])) : '—' ?></td>
              <td><?= htmlspecialchars(accounting_format_date($row['TargetAwardDate'] ?? null)) ?></td>
              <td><?= htmlspecialchars(admin_format_datetime($row['ModifiedDate'] ?? null)) ?></td>
              <?php
              table_view_edit_cell(
                  '/procurement-bids/view.php?id=' . (int) $row['InitiativeID'],
                  '/procurement-bids/edit.php?id=' . (int) $row['InitiativeID'],
                  bid_can_update()
              );
              ?>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
