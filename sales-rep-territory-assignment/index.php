<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/sales-rep-territory.php';
require dirname(__DIR__) . '/includes/admin.php';

sales_rep_territory_require_read();

$activeSlug = 'sales-rep-territory-assignment';
$filters = [
    'state' => strtoupper(trim($_GET['state'] ?? '')),
    'rep'   => trim($_GET['rep'] ?? ''),
    'q'     => trim($_GET['q'] ?? ''),
] + table_sort_state(SALES_REP_TERRITORY_SORT_COLUMNS, 'state', 'asc', $_GET);

$rows = [];
$dbError = null;
$reps = [];

try {
    $rows = sales_rep_territory_list($filters);
    $reps = sales_rep_territory_distinct_reps();
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

$notice = $_GET['notice'] ?? null;

$pageTitle = 'Sales Rep Territory Assignment | NutraAxis Operations';
$pageDescription = 'Maintain zip/county sales territory assignments for Sales Rep Reporting.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/',
          'back_label' => 'Back to Operations Home',
          'category'   => 'Operations',
          'title'      => 'Sales Rep Territory Assignment',
          'lead'       => 'Maintain State / Zip / County → sales rep assignments used by Sales Rep Reporting.',
          'permission' => permission_label(sales_rep_territory_permission_value()),
      ]);

      $toolbar = '';
      if (sales_rep_territory_can_create()) {
          $toolbar = '<a class="btn-primary" href="/sales-rep-territory-assignment/new.php">New assignment</a>';
      }
      render_list_page_toolbar($toolbar !== '' ? $toolbar : null);
      ?>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">Territory assignment created.</div>
      <?php elseif ($notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">Territory assignment updated.</div>
      <?php elseif ($notice === 'deleted'): ?>
      <div class="admin-notice is-success" role="status">Territory assignment deleted.</div>
      <?php endif; ?>

      <?php if ($dbError !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($dbError) ?></div>
      <?php else: ?>

      <form class="po-filter audit-filter" method="get" action="/sales-rep-territory-assignment/">
        <?php table_sort_hidden_inputs($filters, 'state', 'asc'); ?>
        <div class="audit-filter-grid">
          <div>
            <label for="state">State</label>
            <select class="form-input" id="state" name="state">
              <option value="">All states</option>
              <?php foreach (CONTACT_US_STATES as $code => $label): ?>
              <option value="<?= htmlspecialchars($code) ?>" <?= $filters['state'] === $code ? 'selected' : '' ?>>
                <?= htmlspecialchars($code . ' — ' . $label) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="rep">Rep</label>
            <select class="form-input" id="rep" name="rep">
              <option value="">All reps</option>
              <?php foreach ($reps as $rep): ?>
              <option value="<?= htmlspecialchars($rep) ?>" <?= $filters['rep'] === $rep ? 'selected' : '' ?>><?= htmlspecialchars($rep) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="audit-filter-wide">
            <label for="q">Search</label>
            <input class="form-input" type="search" id="q" name="q" value="<?= htmlspecialchars($filters['q']) ?>" placeholder="State, zip, county, or rep" />
          </div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Apply Filters</button>
          <a class="btn-secondary" href="/sales-rep-territory-assignment/">Clear</a>
        </div>
      </form>

      <div class="status-banner">
        <div>
          <strong><?= count($rows) ?> assignment<?= count($rows) === 1 ? '' : 's' ?></strong>
          <p>Exact zip matches take priority over statewide ZipCode = All in Sales Rep Reporting.</p>
        </div>
      </div>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <?php table_sort_render_head_row(
                SALES_REP_TERRITORY_SORT_COLUMNS,
                '/sales-rep-territory-assignment',
                $filters,
                ['state', 'rep', 'q'],
                [],
                'state',
                'asc',
                '',
                sales_rep_territory_can_update() || sales_rep_territory_can_delete() ? 'Actions' : ''
            ); ?>
          </thead>
          <tbody>
            <?php if ($rows === []): ?>
            <tr><td colspan="<?= (sales_rep_territory_can_update() || sales_rep_territory_can_delete()) ? 7 : 6 ?>">No territory assignments found.</td></tr>
            <?php else: ?>
            <?php foreach ($rows as $row): ?>
            <?php $id = (int) ($row['SalesTeamTerritoryAssignmentID'] ?? 0); ?>
            <tr>
              <td><?= htmlspecialchars((string) ($row['State'] ?? '')) ?></td>
              <td><?= htmlspecialchars((string) ($row['ZipCode'] ?? '')) ?></td>
              <td><?= htmlspecialchars((string) ($row['County'] ?? '')) ?></td>
              <td><?= htmlspecialchars((string) ($row['Rep'] ?? '')) ?></td>
              <td><?= htmlspecialchars((string) (($row['PreviousRepAssigned'] ?? '') !== '' ? $row['PreviousRepAssigned'] : '—')) ?></td>
              <td><?= !empty($row['DateModified']) ? htmlspecialchars(admin_format_datetime($row['DateModified'])) : '—' ?></td>
              <?php if (sales_rep_territory_can_update() || sales_rep_territory_can_delete()): ?>
              <td>
                <?php if (sales_rep_territory_can_update()): ?>
                <a href="/sales-rep-territory-assignment/edit.php?id=<?= $id ?>">Edit</a>
                <?php endif; ?>
                <?php if (sales_rep_territory_can_update() && sales_rep_territory_can_delete()): ?> · <?php endif; ?>
                <?php if (sales_rep_territory_can_delete()): ?>
                <a href="/sales-rep-territory-assignment/delete.php?id=<?= $id ?>" onclick="return confirm('Delete this territory assignment?');">Delete</a>
                <?php endif; ?>
              </td>
              <?php endif; ?>
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
