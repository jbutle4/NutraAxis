<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/sales-rep-territory.php';
require dirname(__DIR__) . '/includes/admin.php';

sales_rep_territory_require_update();

$assignmentId = (int) ($_GET['id'] ?? 0);
$assignment = $assignmentId > 0 ? sales_rep_territory_get($assignmentId) : null;

if ($assignment === null) {
    header('Location: /sales-rep-territory-assignment/', true, 302);
    exit;
}

$activeSlug = 'sales-rep-territory-assignment';
$error = null;
$form = sales_rep_territory_to_form($assignment);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = sales_rep_territory_from_input($_POST);
    $result = sales_rep_territory_save($_POST, $assignmentId);

    if ($result['ok']) {
        header('Location: /sales-rep-territory-assignment/?notice=updated', true, 302);
        exit;
    }

    $error = $result['error'];
}

$pageTitle = 'Edit Territory Assignment | Sales Rep Territory Assignment';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/sales-rep-territory-assignment/',
          'back_label' => 'Back to Sales Rep Territory Assignment',
          'category'   => 'Operations',
          'title'      => 'Edit Territory Assignment',
          'lead'       => trim((string) ($assignment['State'] ?? '')) . ' / '
              . trim((string) ($assignment['ZipCode'] ?? '')) . ' / '
              . trim((string) ($assignment['County'] ?? '')),
      ]);
      ?>

      <?php if (!empty($assignment['PreviousRepAssigned'])): ?>
      <div class="status-banner">
        <div>
          <strong>Previous rep</strong>
          <p><?= htmlspecialchars((string) $assignment['PreviousRepAssigned']) ?>
            · Modified <?= htmlspecialchars(admin_format_datetime($assignment['DateModified'] ?? null)) ?></p>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php
        $formAction = '/sales-rep-territory-assignment/edit.php?id=' . $assignmentId;
        $isEdit = true;
        require dirname(__DIR__) . '/includes/sales-rep-territory-form.php';
      ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
