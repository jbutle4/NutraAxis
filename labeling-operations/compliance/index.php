<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/labeling.php';

label_require_read();

$activeSlug = 'labeling-operations';
$activeLabelSection = 'compliance';
$subjectFilter = $_GET['subject'] ?? '';
$listFilters = [
    'subject' => $subjectFilter !== '' ? $subjectFilter : null,
] + table_sort_state(LABEL_COMPLIANCE_LIST_SORT_COLUMNS, 'date', 'desc', $_GET);
$reviews = label_list_compliance_reviews($listFilters);
$notice = $_GET['notice'] ?? null;

$pageTitle = label_page_title('Label Compliance Review');
$pageDescription = 'Log approvals and review activity for batch printing and label order production.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      $listToolbar = label_can_create() ? '<a class="btn-primary" href="/labeling-operations/compliance/new.php">Log Review</a>' : '';
      render_list_page_header([
          'back_href'  => '/labeling-operations/',
          'back_label' => 'Back to ' . label_module_title(),
          'category'   => 'Label Compliance Review',
          'title'      => 'Approvals & Review Log',
          'lead'       => 'Track compliance review outcomes for batch print orders, label order runs, white label production orders, and label templates.',
      ]);
      ?>

      <?php require dirname(__DIR__, 2) . '/includes/labeling-nav.php'; ?>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">Compliance review logged successfully.</div>
      <?php endif; ?>

      <form class="po-filter" method="get" action="/labeling-operations/compliance/">
        <?php table_sort_hidden_inputs($listFilters, 'date', 'desc'); ?>
        <label for="subject">Review subject</label>
        <select class="form-input" id="subject" name="subject" onchange="this.form.submit()">
          <option value="">All subjects</option>
          <?php foreach (LABEL_REVIEW_SUBJECTS as $key => $label): ?>
          <option value="<?= htmlspecialchars($key) ?>" <?= $subjectFilter === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
      </form>

      <?php render_list_page_toolbar($listToolbar !== '' ? $listToolbar : null); ?>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <?php table_sort_render_head_row(
                LABEL_COMPLIANCE_LIST_SORT_COLUMNS,
                '/labeling-operations/compliance',
                $listFilters,
                ['subject'],
                LABEL_COMPLIANCE_LIST_SORT_NUMERIC,
                'date',
                'desc',
                'date'
            ); ?>
          </thead>
          <tbody>
            <?php if ($reviews === []): ?>
            <tr><td colspan="6">No compliance reviews logged yet.</td></tr>
            <?php else: ?>
            <?php foreach ($reviews as $review): ?>
            <tr>
              <td><?= htmlspecialchars(label_format_datetime($review['ReviewDate'])) ?></td>
              <td><?= htmlspecialchars(label_review_subject_label($review['ReviewSubject'])) ?></td>
              <td><?= (int) $review['SubjectID'] ?></td>
              <td><?= htmlspecialchars($review['ReviewStatus']) ?></td>
              <td><?= htmlspecialchars($review['ReviewerName']) ?></td>
              <td><?= htmlspecialchars($review['Comments'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
