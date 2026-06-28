<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/labeling.php';

label_require_create();

$activeSlug = 'labeling-operations';
$activeLabelSection = 'compliance';
$error = null;
$form = [
    'review_subject' => 'BatchPrintOrder',
    'subject_id'     => '',
    'review_status'  => 'Pending',
    'reviewer_name'  => auth_user()['UserName'] ?? '',
    'comments'       => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = array_merge($form, $_POST);
    $result = label_save_compliance_review($_POST);
    if ($result['ok']) {
        header('Location: /labeling-operations/compliance/?notice=created', true, 302);
        exit;
    }
    $error = $result['error'];
}

$pageTitle = label_page_title('Log Compliance Review');

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/labeling-operations/compliance/',
          'back_label' => 'Back to Label Compliance Review',
          'category'   => 'Label Compliance Review',
          'title'      => 'Log Review',
          'lead'       => 'Record an approval or review outcome for batch printing or label order production.',
      ]);
      ?>

      <?php require dirname(__DIR__, 2) . '/includes/labeling-nav.php'; ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form class="admin-form" method="post" action="/labeling-operations/compliance/new.php">
        <div class="form-grid">
          <div class="form-group">
            <label for="review_subject">Review Subject</label>
            <select class="form-input" id="review_subject" name="review_subject">
              <?php foreach (LABEL_REVIEW_SUBJECTS as $key => $label): ?>
              <option value="<?= htmlspecialchars($key) ?>" <?= $form['review_subject'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="subject_id">Record ID</label>
            <input class="form-input" type="number" id="subject_id" name="subject_id" value="<?= htmlspecialchars($form['subject_id']) ?>" min="1" required />
          </div>
          <div class="form-group">
            <label for="review_status">Review Status</label>
            <select class="form-input" id="review_status" name="review_status">
              <?php foreach (LABEL_REVIEW_STATUSES as $status): ?>
              <option value="<?= htmlspecialchars($status) ?>" <?= $form['review_status'] === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="reviewer_name">Reviewer</label>
            <input class="form-input" type="text" id="reviewer_name" name="reviewer_name" value="<?= htmlspecialchars($form['reviewer_name']) ?>" required />
          </div>
          <div class="form-group form-grid-full">
            <label for="comments">Comments</label>
            <textarea class="form-input" id="comments" name="comments" rows="4"><?= htmlspecialchars($form['comments']) ?></textarea>
          </div>
        </div>
        <div class="module-actions">
          <button type="submit" class="btn-primary">Save Review</button>
          <a class="btn-secondary" href="/labeling-operations/compliance/">Cancel</a>
        </div>
      </form>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
