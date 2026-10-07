<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require_once dirname(__DIR__, 2) . '/includes/list-page-header.php';
require_once dirname(__DIR__, 2) . '/includes/accs-support-case.php';
auth_require_module_read('accs-order-account-support');

$caseId = (int) ($_GET['id'] ?? 0);
$case = accs_support_case_get($caseId);
if ($case === null) {
    http_response_code(404);
    echo 'Support case not found.';
    exit;
}

$canUpdate = auth_can_update('AccsOrderAccountSupport');
$error = null;
$notice = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canUpdate) {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'comment') {
        $result = accs_support_case_add_comment($caseId, (string) ($_POST['body'] ?? ''));
        if ($result['ok']) {
            header('Location: /accs-order-account-support/cases/view.php?id=' . $caseId . '&notice=commented');
            exit;
        }
        $error = $result['error'] ?? 'Unable to add comment.';
    } elseif ($action === 'close') {
        accs_support_case_update($caseId, [
            'status'    => 'closed',
            'closed_at' => gmdate('Y-m-d H:i:s'),
        ]);
        accs_support_case_add_event($caseId, 'status_changed', 'Case closed.');
        header('Location: /accs-order-account-support/cases/view.php?id=' . $caseId . '&notice=closed');
        exit;
    }
}

$case = accs_support_case_get($caseId);
$comments = accs_support_case_list_comments($caseId);
$events = accs_support_case_list_events($caseId);
$resources = accs_support_case_list_resources($caseId);
$cloneSummary = null;
if (!empty($case['CloneSummaryJson'])) {
    $decoded = json_decode((string) $case['CloneSummaryJson'], true);
    if (is_array($decoded)) {
        $cloneSummary = $decoded;
    }
}
$noticeKey = (string) ($_GET['notice'] ?? '');

$pageTitle = 'Support case #' . $caseId . ' | NutraAxis Operations';
$pageDescription = 'ACCS support case detail.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/accs-order-account-support/cases/',
          'back_label' => 'Back to Support cases',
          'category'   => 'IT & Ecommerce',
          'title'      => 'Support case #' . $caseId,
          'lead'       => (string) ($case['Subject'] ?? 'ACCS support case'),
          'permission' => auth_module_permission_label('accs-order-account-support'),
      ]);
      ?>

      <?php if ($noticeKey === 'commented'): ?>
      <div class="admin-notice is-success" role="status">Comment added.</div>
      <?php elseif ($noticeKey === 'closed'): ?>
      <div class="admin-notice is-success" role="status">Case closed.</div>
      <?php elseif ($noticeKey === 'cloned'): ?>
      <div class="admin-notice is-success" role="status">Stage clone completed.</div>
      <?php elseif ($noticeKey === 'purged'): ?>
      <div class="admin-notice is-success" role="status">Stage support account purged.</div>
      <?php endif; ?>
      <?php if ($error !== null): ?>
      <div class="admin-notice is-error" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <section class="detail-card">
        <h2>Case summary</h2>
        <dl class="detail-list detail-list-inline">
          <div><dt>Status</dt><dd><?= htmlspecialchars(accs_support_case_status_label((string) $case['Status'])) ?></dd></div>
          <div><dt>Ticket</dt><dd><?= htmlspecialchars((string) ($case['TicketRef'] ?? '—')) ?></dd></div>
          <div><dt>Prod company</dt><dd><?= htmlspecialchars((string) ($case['ProdCompanyName'] ?? '—')) ?> <?php if (!empty($case['ProdCompanyId'])): ?><span class="muted">#<?= (int) $case['ProdCompanyId'] ?></span><?php endif; ?></dd></div>
          <div><dt>Prod admin</dt><dd><?= htmlspecialchars((string) ($case['ProdAdminEmail'] ?? '—')) ?></dd></div>
          <div><dt>Prod catalog</dt><dd><?= !empty($case['ProdSharedCatalogId']) ? '#' . (int) $case['ProdSharedCatalogId'] : '—' ?></dd></div>
          <div><dt>Stage company</dt><dd><?= htmlspecialchars((string) ($case['StageCompanyName'] ?? '—')) ?> <?php if (!empty($case['StageCompanyId'])): ?><span class="muted">#<?= (int) $case['StageCompanyId'] ?></span><?php endif; ?></dd></div>
          <div><dt>Stage customer</dt><dd><?= !empty($case['StageCustomerId']) ? '#' . (int) $case['StageCustomerId'] : '—' ?></dd></div>
          <div><dt>Stage catalog</dt><dd><?= !empty($case['StageSharedCatalogId']) ? '#' . (int) $case['StageSharedCatalogId'] : '—' ?></dd></div>
          <div><dt>Created</dt><dd><?= htmlspecialchars((string) ($case['CreatedAt'] ?? '')) ?><?php if (!empty($case['CreatedByName'])): ?> by <?= htmlspecialchars((string) $case['CreatedByName']) ?><?php endif; ?></dd></div>
          <div><dt>Updated</dt><dd><?= htmlspecialchars((string) ($case['UpdatedAt'] ?? '')) ?></dd></div>
        </dl>
        <div class="form-actions" style="margin-top:1rem;">
          <?php if ($canUpdate && empty($case['StageCompanyId']) && !in_array((string) $case['Status'], ['purged', 'closed'], true)): ?>
          <a class="btn-primary" href="/accs-order-account-support/clone/?case_id=<?= $caseId ?>">Clone to Stage</a>
          <?php endif; ?>
          <?php if ($canUpdate && !empty($case['StageCompanyId']) && (string) $case['Status'] !== 'purged'): ?>
          <a class="btn-secondary" href="/accs-order-account-support/purge/?case_id=<?= $caseId ?>">Purge Stage account</a>
          <?php endif; ?>
          <?php if ($canUpdate && !in_array((string) $case['Status'], ['closed', 'purged'], true)): ?>
          <form method="post" style="display:inline;">
            <input type="hidden" name="action" value="close" />
            <button type="submit" class="btn-secondary">Close case</button>
          </form>
          <?php endif; ?>
        </div>
      </section>

      <?php if ($cloneSummary !== null): ?>
      <section class="detail-card">
        <h2>Clone summary</h2>
        <dl class="detail-list detail-list-inline">
          <div><dt>SKUs mirrored</dt><dd><?= (int) ($cloneSummary['sku_mirrored_count'] ?? 0) ?> / <?= (int) ($cloneSummary['sku_prod_count'] ?? 0) ?></dd></div>
          <div><dt>SKUs skipped</dt><dd><?= (int) ($cloneSummary['sku_skipped_count'] ?? 0) ?></dd></div>
          <div><dt>Categories</dt><dd><?= (int) ($cloneSummary['category_count'] ?? 0) ?></dd></div>
          <div><dt>Prices mirrored</dt><dd><?= (int) ($cloneSummary['price_mirrored_count'] ?? 0) ?></dd></div>
          <div><dt>Storefront</dt><dd><?php if (!empty($cloneSummary['storefront_url'])): ?><a href="<?= htmlspecialchars((string) $cloneSummary['storefront_url']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars((string) $cloneSummary['storefront_url']) ?></a><?php else: ?>—<?php endif; ?></dd></div>
        </dl>
        <?php if (!empty($cloneSummary['sku_skipped']) && is_array($cloneSummary['sku_skipped'])): ?>
        <p class="muted" style="margin-top:0.75rem;">Skipped SKUs (missing on Stage): <?= htmlspecialchars(implode(', ', array_map('strval', array_slice($cloneSummary['sku_skipped'], 0, 40)))) ?><?= count($cloneSummary['sku_skipped']) > 40 ? '…' : '' ?></p>
        <?php endif; ?>
      </section>
      <?php endif; ?>

      <section class="detail-card">
        <h2>Comments</h2>
        <?php if ($comments === []): ?>
        <p class="muted">No comments yet.</p>
        <?php else: ?>
        <ul class="comment-thread">
          <?php foreach ($comments as $comment): ?>
          <li>
            <div class="comment-meta">
              <strong><?= htmlspecialchars((string) ($comment['CreatedByName'] ?? 'User')) ?></strong>
              <span class="muted"><?= htmlspecialchars((string) ($comment['CreatedAt'] ?? '')) ?></span>
            </div>
            <div class="comment-body"><?= nl2br(htmlspecialchars((string) ($comment['Body'] ?? ''))) ?></div>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <?php if ($canUpdate): ?>
        <form class="admin-form" method="post" style="margin-top:1rem;">
          <input type="hidden" name="action" value="comment" />
          <div class="form-group form-group--stacked">
            <label for="body">Add comment</label>
            <textarea class="form-input" id="body" name="body" rows="4" required></textarea>
          </div>
          <button type="submit" class="btn-primary">Save comment</button>
        </form>
        <?php endif; ?>
      </section>

      <section class="detail-card">
        <h2>Events</h2>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr><th>When</th><th>Type</th><th>Message</th><th>By</th></tr>
            </thead>
            <tbody>
              <?php if ($events === []): ?>
              <tr><td colspan="4">No events.</td></tr>
              <?php else: ?>
              <?php foreach ($events as $event): ?>
              <tr>
                <td><?= htmlspecialchars((string) ($event['CreatedAt'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($event['EventType'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($event['Message'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($event['CreatedByName'] ?? '—')) ?></td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section class="detail-card">
        <h2>Stage resource ledger</h2>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr><th>When</th><th>Action</th><th>Type</th><th>Stage ID</th><th>Name</th><th>Prod source</th></tr>
            </thead>
            <tbody>
              <?php if ($resources === []): ?>
              <tr><td colspan="6">No Stage resources recorded.</td></tr>
              <?php else: ?>
              <?php foreach ($resources as $resource): ?>
              <tr>
                <td><?= htmlspecialchars((string) ($resource['CreatedAt'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($resource['Action'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($resource['ResourceType'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($resource['StageEntityId'] ?? '—')) ?></td>
                <td><?= htmlspecialchars((string) ($resource['StageEntityName'] ?? '—')) ?></td>
                <td><?= htmlspecialchars((string) ($resource['ProdSourceId'] ?? '—')) ?></td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
