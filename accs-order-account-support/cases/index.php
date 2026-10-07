<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require_once dirname(__DIR__, 2) . '/includes/list-page-header.php';
require_once dirname(__DIR__, 2) . '/includes/accs-support-case.php';

auth_require_module_read('accs-order-account-support');

$activeSlug = 'accs-order-account-support';
$filters = [
    'status' => trim((string) ($_GET['status'] ?? '')),
    'q'      => trim((string) ($_GET['q'] ?? '')),
    'page'   => max(1, (int) ($_GET['page'] ?? 1)),
    'sort'   => (string) ($_GET['sort'] ?? 'updated'),
    'dir'    => (string) ($_GET['dir'] ?? 'desc'),
];
$list = accs_support_case_list($filters);
$rows = $list['rows'];
$total = (int) $list['total'];
$page = (int) $list['page'];
$perPage = (int) $list['per_page'];
$totalPages = max(1, (int) ceil($total / $perPage));
$queryKeys = ['status', 'q', 'page'];
$canCreate = auth_can_create('AccsOrderAccountSupport');

$pageTitle = 'ACCS Support Cases | NutraAxis Operations';
$pageDescription = 'Support cases for ACCS Production-to-Stage troubleshooting.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main page-main--fluid">
    <div class="container page-inner page-inner--full">
      <?php
      render_list_page_header([
          'back_href'  => '/accs-order-account-support/',
          'back_label' => 'Back to ACCS Order and Account Support',
          'category'   => 'IT & Ecommerce',
          'title'      => 'Support cases',
          'lead'       => 'Permanent record of ACCS support clones, comments, events, and Stage resources.',
          'permission' => auth_module_permission_label($activeSlug),
      ]);
      if ($canCreate) {
          render_list_page_toolbar(
              '<a class="btn-primary" href="/accs-order-account-support/clone/">Clone account to Stage</a>'
          );
      }
      ?>

      <form class="po-filter audit-filter page-list-filters" method="get" action="/accs-order-account-support/cases/">
        <?php table_sort_hidden_inputs($filters, 'updated', 'desc'); ?>
        <div class="audit-filter-grid">
          <div>
            <label for="status">Status</label>
            <select class="form-input" id="status" name="status">
              <option value="">All statuses</option>
              <?php foreach (ACCS_SUPPORT_CASE_STATUSES as $status): ?>
              <option value="<?= htmlspecialchars($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= htmlspecialchars(accs_support_case_status_label($status)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="audit-filter-wide">
            <label for="q">Search</label>
            <input class="form-input" type="search" id="q" name="q" value="<?= htmlspecialchars((string) ($filters['q'] ?? '')) ?>" placeholder="Case ID, ticket, company, Stage name" />
          </div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Filter</button>
          <a class="btn-secondary" href="/accs-order-account-support/cases/">Clear</a>
        </div>
      </form>

      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <?php
            table_sort_render_head_row(
                ACCS_SUPPORT_CASE_LIST_SORT_COLUMNS,
                '/accs-order-account-support/cases/',
                $filters,
                $queryKeys,
                ['id'],
                'updated',
                'desc',
                'updated',
                'Open'
            );
            ?>
          </thead>
          <tbody>
            <?php if ($rows === []): ?>
            <tr><td colspan="<?= count(ACCS_SUPPORT_CASE_LIST_SORT_COLUMNS) + 1 ?>">No support cases yet.</td></tr>
            <?php else: ?>
            <?php foreach ($rows as $row): ?>
            <tr>
              <td><a href="/accs-order-account-support/cases/view.php?id=<?= (int) $row['CaseID'] ?>">#<?= (int) $row['CaseID'] ?></a></td>
              <td><?= htmlspecialchars(accs_support_case_status_label((string) $row['Status'])) ?></td>
              <td>
                <?php if (!empty($row['ProdCompanyId'])): ?>
                  <?= htmlspecialchars((string) ($row['ProdCompanyName'] ?? '')) ?>
                  <span class="muted">(<?= (int) $row['ProdCompanyId'] ?>)</span>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
              <td>
                <?php if (!empty($row['StageCompanyId'])): ?>
                  <?= htmlspecialchars((string) ($row['StageCompanyName'] ?? '')) ?>
                  <span class="muted">(<?= (int) $row['StageCompanyId'] ?>)</span>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars((string) ($row['TicketRef'] ?? '—')) ?></td>
              <td><?= htmlspecialchars((string) ($row['UpdatedAt'] ?? '')) ?></td>
              <td><?= htmlspecialchars((string) ($row['CreatedAt'] ?? '')) ?></td>
              <td><a href="/accs-order-account-support/cases/view.php?id=<?= (int) $row['CaseID'] ?>">Open</a></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($totalPages > 1): ?>
      <nav class="pagination" aria-label="Cases pages">
        <?php if ($page > 1): ?>
        <a href="?<?= http_build_query(array_merge($filters, ['page' => $page - 1])) ?>">Previous</a>
        <?php endif; ?>
        <span>Page <?= $page ?> of <?= $totalPages ?> (<?= $total ?> cases)</span>
        <?php if ($page < $totalPages): ?>
        <a href="?<?= http_build_query(array_merge($filters, ['page' => $page + 1])) ?>">Next</a>
        <?php endif; ?>
      </nav>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
