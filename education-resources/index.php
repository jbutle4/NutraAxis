<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/education-resources.php';

education_resources_require_read();

$activeSlug = 'education-resources';
$typeFilter = trim((string) ($_GET['type'] ?? ''));
$search = trim((string) ($_GET['q'] ?? ''));
$listFilters = [
    'type' => $typeFilter !== '' ? $typeFilter : null,
    'q'    => $search !== '' ? $search : null,
] + table_sort_state(EDUCATION_RESOURCES_LIST_SORT_COLUMNS, 'updated', 'desc', $_GET);
$rows = education_resources_list($listFilters);
$notice = $_GET['notice'] ?? null;

$pageTitle = 'Education Resources | NutraAxis Operations';
$pageDescription = 'Manage education PDFs and Vimeo video links for NutraAxis operations.';

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
          'title'      => 'Education Resources',
          'lead'       => 'Store Vimeo training links and PDF documents (uploaded to Azure Blob Storage) as clickable education resources.',
          'permission' => permission_label(education_resources_permission_value()),
      ]);
      if (education_resources_can_create()) {
          render_list_page_toolbar(
              '<a class="btn-primary" href="/education-resources/new.php">New resource</a>'
          );
      }
      ?>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">Education resource created.</div>
      <?php elseif ($notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">Education resource updated.</div>
      <?php elseif ($notice === 'deleted'): ?>
      <div class="admin-notice is-success" role="status">Education resource deleted.</div>
      <?php endif; ?>

      <form class="po-filter audit-filter page-list-filters" method="get" action="/education-resources/">
        <?php table_sort_hidden_inputs($listFilters, 'updated', 'desc'); ?>
        <div class="audit-filter-grid">
          <div>
            <label for="type">Type</label>
            <select class="form-input" id="type" name="type">
              <option value="">All types</option>
              <?php foreach (EDUCATION_RESOURCE_TYPES as $value => $label): ?>
              <option value="<?= htmlspecialchars($value) ?>" <?= $typeFilter === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="audit-filter-wide">
            <label for="q">Search</label>
            <input class="form-input" type="search" id="q" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Description, type, URL, or file name" />
          </div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Apply Filters</button>
          <a class="btn-secondary" href="/education-resources/">Clear</a>
        </div>
      </form>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <?php
            table_sort_render_head_row(
                EDUCATION_RESOURCES_LIST_SORT_COLUMNS,
                '/education-resources',
                $listFilters,
                ['type', 'q'],
                [],
                'updated',
                'desc',
                '',
                'Resource | Actions'
            );
            ?>
          </thead>
          <tbody>
            <?php if ($rows === []): ?>
            <tr><td colspan="5">No education resources found.</td></tr>
            <?php else: ?>
            <?php foreach ($rows as $row): ?>
            <?php
              $erid = (int) ($row['ERID'] ?? 0);
              $openHref = education_resources_open_href($row);
              $isExternal = ((string) ($row['Type'] ?? '')) === 'Video';
            ?>
            <tr>
              <td><?= htmlspecialchars((string) ($row['Description'] ?? '')) ?></td>
              <td><?= htmlspecialchars((string) ($row['Type'] ?? '')) ?></td>
              <td><?= htmlspecialchars(education_resources_format_datetime($row['CreateDate'] ?? null)) ?></td>
              <td>
                <?= htmlspecialchars(education_resources_format_datetime($row['UpdateDate'] ?? null)) ?>
                <?php if (!empty($row['UpdatedByName'])): ?>
                <span class="form-hint">by <?= htmlspecialchars((string) $row['UpdatedByName']) ?></span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($openHref !== '#'): ?>
                <a href="<?= htmlspecialchars($openHref) ?>"<?= $isExternal ? ' target="_blank" rel="noopener noreferrer"' : ' target="_blank"' ?>>
                  <?= $isExternal ? 'Open video' : 'Open PDF' ?>
                </a>
                <?php else: ?>
                —
                <?php endif; ?>
                <?php if (education_resources_can_update()): ?>
                · <a href="/education-resources/edit.php?id=<?= $erid ?>">Edit</a>
                <?php endif; ?>
                <?php if (education_resources_can_delete()): ?>
                · <a href="/education-resources/delete.php?id=<?= $erid ?>" onclick="return confirm('Delete this education resource?');">Delete</a>
                <?php endif; ?>
              </td>
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
