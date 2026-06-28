<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/support.php';
require dirname(__DIR__) . '/includes/zendesk.php';

support_require_read();

$ticketId = (int) ($_GET['id'] ?? 0);
$configError = zendesk_config_error();
$ticketResult = ['ok' => false, 'error' => $configError ?? 'Ticket not found.', 'ticket' => null, 'users' => [], 'comments' => []];

if ($configError === null && $ticketId > 0) {
    $ticketResult = zendesk_get_ticket($ticketId);
}

if ($configError !== null || !$ticketResult['ok'] || $ticketResult['ticket'] === null) {
    $activeSlug = 'support';
    $pageTitle = 'Ticket Not Found | Support';
    require dirname(__DIR__) . '/includes/head.php';
    require dirname(__DIR__) . '/includes/header.php';
    echo '<main class="page-main"><div class="container page-inner">';
    echo '<a class="breadcrumb" href="/support/"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>Back to Tickets</a>';
    echo '<div class="admin-notice is-error is-detail" role="alert">' . htmlspecialchars($ticketResult['error'] ?? 'Ticket not found.') . '</div>';
    echo '<div class="module-actions"><a class="btn-secondary" href="/support/">Back to Tickets</a></div>';
    echo '</div></main>';
    require dirname(__DIR__) . '/includes/footer.php';
    exit;
}

$ticket = $ticketResult['ticket'];
$users = $ticketResult['users'];
$comments = $ticketResult['comments'];
$requester = $users[(int) ($ticket['requester_id'] ?? 0)] ?? null;
$assignee = $users[(int) ($ticket['assignee_id'] ?? 0)] ?? null;
$canComment = support_can_comment_on_ticket($ticket, $requester);
$notice = $_GET['notice'] ?? null;

$activeSlug = 'support';
$pageTitle = 'Ticket #' . $ticketId . ' | Support';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      ob_start();
      if (support_can_update() && trim((string) env('ZENDESK_SUBDOMAIN', '')) !== ''):
      ?>
      <a class="btn-secondary" href="https://<?= htmlspecialchars(trim((string) env('ZENDESK_SUBDOMAIN', 'nutraaxislabs'))) ?>.zendesk.com/agent/tickets/<?= $ticketId ?>" target="_blank" rel="noopener noreferrer">Open in Zendesk</a>
      <?php endif;
      $pageToolbar = ob_get_clean();

      render_list_page_header([
          'back_href'  => '/support/',
          'back_label' => 'Back to Tickets',
          'category'   => 'Zendesk Ticket',
          'title'      => zendesk_ticket_subject($ticket),
          'lead'       => '<span class="status-badge ' . support_status_class((string) ($ticket['status'] ?? '')) . '">' . htmlspecialchars(support_status_label((string) ($ticket['status'] ?? ''))) . '</span> · ' . htmlspecialchars(support_priority_label((string) ($ticket['priority'] ?? 'normal'))) . ' · #' . $ticketId,
          'lead_html'  => true,
      ]);
      ?>

      <?php if ($notice === 'comment'): ?>
      <div class="admin-notice is-success" role="status">Reply posted successfully.</div>
      <?php elseif ($notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">Ticket updated successfully.</div>
      <?php endif; ?>

      <?php render_list_page_toolbar($pageToolbar); ?>

      <?php if (!support_is_agent()): ?>
      <div class="status-banner">
        <div>
          <strong>View-only access</strong>
          <p>You can read this ticket because it was submitted under <?= htmlspecialchars(support_user_email()) ?>. Replies and status changes require the Support role with Update permission.</p>
        </div>
      </div>
      <?php endif; ?>

      <div class="detail-grid">
        <section class="detail-card">
          <h2>Ticket Details</h2>
          <dl class="detail-list">
            <div><dt>Requester</dt><dd><?= htmlspecialchars(zendesk_user_label($requester)) ?></dd></div>
            <div><dt>Assignee</dt><dd><?= htmlspecialchars(zendesk_user_label($assignee)) ?></dd></div>
            <div><dt>Created</dt><dd><?= htmlspecialchars(zendesk_format_datetime($ticket['created_at'] ?? null)) ?></dd></div>
            <div><dt>Updated</dt><dd><?= htmlspecialchars(zendesk_format_datetime($ticket['updated_at'] ?? null)) ?></dd></div>
          </dl>
        </section>

        <?php if (support_can_update()): ?>
        <section class="detail-card">
          <h2>Update Ticket</h2>
          <form class="admin-form" method="post" action="/support/update.php">
            <input type="hidden" name="ticket_id" value="<?= $ticketId ?>" />
            <div class="form-grid">
              <div class="form-group">
                <label for="status">Status</label>
                <select class="form-input" id="status" name="status">
                  <?php foreach (SUPPORT_TICKET_STATUSES as $value => $label): ?>
                  <option value="<?= htmlspecialchars($value) ?>" <?= strtolower((string) ($ticket['status'] ?? '')) === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="priority">Priority</label>
                <select class="form-input" id="priority" name="priority">
                  <?php foreach (SUPPORT_TICKET_PRIORITIES as $value => $label): ?>
                  <option value="<?= htmlspecialchars($value) ?>" <?= strtolower((string) ($ticket['priority'] ?? 'normal')) === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <button type="submit" class="btn-secondary btn-small">Save Changes</button>
          </form>
        </section>
        <?php endif; ?>
      </div>

      <section class="detail-card supplier-po-report">
        <h2>Conversation</h2>
        <?php if ($comments === []): ?>
        <p class="page-lead">No comments yet.</p>
        <?php else: ?>
        <div class="support-thread">
          <?php foreach ($comments as $comment): ?>
          <?php
            $author = $users[(int) ($comment['author_id'] ?? 0)] ?? null;
            $isPublic = !empty($comment['public']);
            if (!support_can_update() && !$isPublic) {
                continue;
            }
          ?>
          <article class="support-comment<?= $isPublic ? '' : ' is-internal' ?>">
            <header class="support-comment-head">
              <strong><?= htmlspecialchars(zendesk_user_label($author)) ?></strong>
              <span><?= htmlspecialchars(zendesk_format_datetime($comment['created_at'] ?? null)) ?></span>
              <?php if (!$isPublic): ?>
              <span class="status-badge status-draft">Internal</span>
              <?php endif; ?>
            </header>
            <div class="support-comment-body"><?= zendesk_format_comment_body($comment) ?></div>
          </article>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </section>

      <?php if ($canComment): ?>
      <section class="detail-card">
        <h2><?= support_can_update() ? 'Add Reply' : 'Reply to Support' ?></h2>
        <form class="admin-form" method="post" action="/support/comment.php">
          <input type="hidden" name="ticket_id" value="<?= $ticketId ?>" />
          <div class="form-group form-group-wide">
            <label for="body">Message</label>
            <textarea class="form-input" id="body" name="body" rows="6" required></textarea>
            <p class="permission-note">Replies are posted through the Zendesk API account and include your Ops login name and timestamp for audit purposes.</p>
          </div>
          <?php if (support_can_update()): ?>
          <div class="form-grid">
            <div class="form-group">
              <label for="comment_status">Set status after reply</label>
              <select class="form-input" id="comment_status" name="status">
                <option value="">Keep current status</option>
                <?php foreach (SUPPORT_TICKET_STATUSES as $value => $label): ?>
                <option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="checkbox-label">
                <input type="checkbox" name="public" value="1" checked />
                Public reply (visible to requester)
              </label>
            </div>
          </div>
          <?php endif; ?>
          <button type="submit" class="btn-primary">Post Reply</button>
        </form>
      </section>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
