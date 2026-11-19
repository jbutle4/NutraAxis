<?php

require dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/list-page-header.php';
require_once dirname(__DIR__) . '/includes/status-update-portal.php';
require_once dirname(__DIR__) . '/includes/status-update-services.php';

status_update_require_read();

$activeSlug = 'weekly-na-status-update';
$repo = new StatusUpdateRepository();
$draftService = new StatusUpdateDraftService();
$templateService = new StatusUpdateTemplateService();
$publishService = new StatusUpdatePublishService();
$claude = new StatusUpdateClaudeClient();

$author = status_update_author_name();
$statusDate = status_update_parse_date($_GET['date'] ?? '') ?? status_update_default_week_date();
$week = $repo->weekByDate($statusDate);
$template = $repo->latestTemplate();
$error = isset($_GET['error']) ? (string) $_GET['error'] : null;
$notice = isset($_GET['notice']) ? (string) $_GET['notice'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (!status_update_verify_csrf((string) ($_POST['csrf'] ?? ''))) {
        header('Location: /weekly-na-status-update/?date=' . rawurlencode($statusDate) . '&error=' . rawurlencode('Invalid request token. Refresh and try again.'), true, 302);
        exit;
    }

    $redirect = '/weekly-na-status-update/?date=' . rawurlencode($statusDate);

    if ($action === 'open_week' && status_update_can_contribute()) {
        $result = $draftService->openWeek($statusDate, $author);
        header('Location: ' . $redirect . ($result['ok'] ? '&notice=opened' : '&error=' . rawurlencode((string) $result['error'])), true, 302);
        exit;
    }

    if ($action === 'template_upload' && status_update_can_manage_template()) {
        $file = $_FILES['template_file'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            header('Location: ' . $redirect . '&error=' . rawurlencode('Template upload failed.'), true, 302);
            exit;
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        $validation = $templateService->validateUpload($tmp);
        if (!$validation['ok']) {
            header('Location: ' . $redirect . '&error=' . rawurlencode((string) $validation['error']), true, 302);
            exit;
        }
        auth_start_session();
        $storedPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'status-template-' . bin2hex(random_bytes(8)) . '.pptx';
        if (!copy($tmp, $storedPath)) {
            header('Location: ' . $redirect . '&error=' . rawurlencode('Could not stage template upload.'), true, 302);
            exit;
        }
        if (!empty($_SESSION['status_template_pending']['path']) && is_string($_SESSION['status_template_pending']['path'])) {
            $old = (string) $_SESSION['status_template_pending']['path'];
            if (is_file($old)) {
                @unlink($old);
            }
        }
        $_SESSION['status_template_pending'] = [
            'path' => $storedPath,
            'summary' => $validation['summary'],
            'use_next' => !empty($_POST['use_for_next_week']),
            'stored_at' => time(),
        ];
        header('Location: ' . $redirect . '&step=template_confirm', true, 302);
        exit;
    }

    if ($action === 'template_confirm' && status_update_can_manage_template()) {
        auth_start_session();
        $pending = $_SESSION['status_template_pending'] ?? null;
        if (!is_array($pending) || empty($pending['path']) || !is_readable((string) $pending['path'])) {
            header('Location: ' . $redirect . '&error=' . rawurlencode('Pending template expired. Upload again.'), true, 302);
            exit;
        }
        $pendingPath = (string) $pending['path'];
        $result = $templateService->confirmStore($pendingPath, $author, !empty($pending['use_next']));
        if (is_file($pendingPath)) {
            @unlink($pendingPath);
        }
        unset($_SESSION['status_template_pending']);
        header('Location: ' . $redirect . ($result['ok'] ? '&notice=template_saved' : '&error=' . rawurlencode((string) $result['error'])), true, 302);
        exit;
    }

    if ($week === null) {
        header('Location: ' . $redirect . '&error=' . rawurlencode('Open the week before making changes.'), true, 302);
        exit;
    }

    if ($action === 'chat_send' && status_update_can_contribute()) {
        $notes = trim((string) ($_POST['notes'] ?? ''));
        if ($notes === '') {
            header('Location: ' . $redirect . '&error=' . rawurlencode('Enter your status notes first.'), true, 302);
            exit;
        }
        $repo->addChatMessage((int) $week['id'], $author, 'user', $notes);
        $draft = $draftService->buildDraftValues($week);
        if (!$draft['ok']) {
            header('Location: ' . $redirect . '&error=' . rawurlencode((string) $draft['error']), true, 302);
            exit;
        }
        $thread = [];
        foreach ($repo->chatThread((int) $week['id'], $author, 20) as $row) {
            $thread[] = [
                'role'    => (string) $row['role'],
                'content' => (string) $row['content'],
            ];
        }
        $messages = [];
        foreach ($thread as $row) {
            $content = (string) $row['content'];
            if ($row['role'] === 'user') {
                $content = "<notes>\n" . $content . "\n</notes>";
            }
            $messages[] = ['role' => (string) $row['role'], 'content' => $content];
        }
        $ai = $claude->propose($messages, $draft['map']);
        if (!$ai['ok']) {
            header('Location: ' . $redirect . '&error=' . rawurlencode((string) $ai['error']), true, 302);
            exit;
        }
        $assistantBody = json_encode([
            'text'       => $ai['text'],
            'proposals'  => $ai['proposals'],
        ], JSON_UNESCAPED_UNICODE);
        $repo->addChatMessage((int) $week['id'], $author, 'assistant', is_string($assistantBody) ? $assistantBody : '');
        header('Location: ' . $redirect . '&notice=chat', true, 302);
        exit;
    }

    if ($action === 'accept_change' && status_update_can_contribute()) {
        $field = trim((string) ($_POST['field'] ?? ''));
        $expected = (int) ($_POST['latest_change_id'] ?? 0);
        $newJson = (string) ($_POST['new_value_json'] ?? '[]');
        $newValue = json_decode($newJson, true);
        if (!is_array($newValue)) {
            $newValue = [];
        }
        $result = $draftService->acceptChange($week, $field, $newValue, $expected, 'ai', null, $author);
        header('Location: ' . $redirect . ($result['ok'] ? '&notice=accepted' : '&error=' . rawurlencode((string) $result['error'])), true, 302);
        exit;
    }

    if ($action === 'publish' && status_update_can_publish()) {
        $result = $publishService->publish($week, $author);
        header('Location: ' . $redirect . ($result['ok'] ? '&notice=published' : '&error=' . rawurlencode((string) $result['error'])), true, 302);
        exit;
    }
}

// JSON poll endpoint
if (isset($_GET['action']) && $_GET['action'] === 'changes') {
    header('Content-Type: application/json; charset=utf-8');
    if ($week === null) {
        echo json_encode(['changes' => []]);
        exit;
    }
    $since = (int) ($_GET['since'] ?? 0);
    $out = [];
    foreach ($repo->changesForWeek((int) $week['id']) as $change) {
        if ((int) $change['id'] <= $since) {
            continue;
        }
        $out[] = [
            'id'        => (int) $change['id'],
            'field_key' => (string) $change['field_key'],
            'author'    => (string) $change['author'],
            'created_at'=> (string) $change['created_at'],
        ];
    }
    echo json_encode(['changes' => $out]);
    exit;
}

auth_start_session();
$templatePending = is_array($_SESSION['status_template_pending'] ?? null) ? $_SESSION['status_template_pending'] : null;

$draftState = $week !== null ? $draftService->buildDraftValues($week) : null;
$chat = $week !== null ? $repo->chatThread((int) $week['id'], $author, 12) : [];
$pollSince = 0;
if ($week !== null) {
    foreach ($repo->changesForWeek((int) $week['id']) as $changeRow) {
        $pollSince = max($pollSince, (int) $changeRow['id']);
    }
}
$csrf = status_update_csrf_token();

$pageTitle = 'Weekly NA Status Update | NutraAxis Operations';
require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/',
          'back_label' => 'Back to Operations',
          'category'   => 'Operations',
          'title'      => 'Weekly NA Status Update',
          'lead'       => 'Collaborative weekly status deck — Claude proposes field updates; accepted changes apply to the shared draft for publishing.',
      ]);
      ?>

      <?php if ($error): ?>
      <div class="admin-notice is-error" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if ($notice === 'opened'): ?>
      <div class="admin-notice is-success" role="status">Week opened for <?= htmlspecialchars($statusDate) ?>.</div>
      <?php elseif ($notice === 'template_saved'): ?>
      <div class="admin-notice is-success" role="status">Template saved.</div>
      <?php elseif ($notice === 'accepted'): ?>
      <div class="admin-notice is-success" role="status">Change accepted into the shared draft.</div>
      <?php elseif ($notice === 'published'): ?>
      <div class="admin-notice is-success" role="status">Week published. The deck is now read-only.</div>
      <?php elseif ($notice === 'chat'): ?>
      <div class="admin-notice is-success" role="status">Claude replied with proposals — review below.</div>
      <?php endif; ?>

      <section class="admin-card" style="margin-bottom:1.25rem;">
        <p><strong>Status date:</strong> <?= htmlspecialchars($statusDate) ?>
          <?php if ($week !== null): ?>
          · <strong>State:</strong> <?= htmlspecialchars((string) $week['state']) ?>
          <?php endif; ?>
        </p>
        <?php if ($week === null && $template === null): ?>
        <p>No template is loaded yet. Template admins must upload the master .pptx before contributors can work.</p>
        <?php elseif ($week === null): ?>
        <form method="post" class="inline-form">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>" />
          <input type="hidden" name="action" value="open_week" />
          <button type="submit" class="btn-primary">Open week</button>
        </form>
        <?php elseif (($week['state'] ?? '') === 'draft' && status_update_can_publish()): ?>
        <form method="post" class="inline-form" onsubmit="return confirm('Publish this week? The deck will lock.');">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>" />
          <input type="hidden" name="action" value="publish" />
          <button type="submit" class="btn-primary">Publish week</button>
        </form>
        <a class="btn-secondary" href="/weekly-na-status-update/download.php?date=<?= rawurlencode($statusDate) ?>">Download draft .pptx</a>
        <?php elseif (!empty($week['final_blob'])): ?>
        <a class="btn-secondary" href="/weekly-na-status-update/download.php?date=<?= rawurlencode($statusDate) ?>&amp;final=1">Download final .pptx</a>
        <?php endif; ?>
      </section>

      <?php if (status_update_can_manage_template()): ?>
      <section class="admin-card" style="margin-bottom:1.25rem;">
        <h2 class="h4">Template</h2>
        <?php if ($template !== null): ?>
        <p>Current template: <?= (int) $template['field_count'] ?> fields · uploaded <?= htmlspecialchars((string) $template['uploaded_at']) ?> by <?= htmlspecialchars((string) $template['uploaded_by']) ?>.</p>
        <?php endif; ?>
        <?php if (($_GET['step'] ?? '') === 'template_confirm' && $templatePending !== null): ?>
        <?php $summary = $templatePending['summary'] ?? []; ?>
        <p>Validation: <?= (int) ($summary['field_count'] ?? 0) ?> editable fields on <?= (int) ($summary['slides'] ?? 0) ?> slides.</p>
        <?php if (!empty($summary['problems'])): ?>
        <ul><?php foreach ($summary['problems'] as $problem): ?><li><?= htmlspecialchars((string) $problem) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
        <form method="post" class="admin-form">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>" />
          <input type="hidden" name="action" value="template_confirm" />
          <label class="form-group form-group--stacked">
            <input type="checkbox" name="use_for_next_week" value="1" <?= empty($template) ? 'checked disabled' : '' ?> />
            Start the next week from this template (structural change path)
          </label>
          <button type="submit" class="btn-primary">Confirm template</button>
        </form>
        <?php else: ?>
        <form method="post" enctype="multipart/form-data" class="admin-form">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>" />
          <input type="hidden" name="action" value="template_upload" />
          <div class="form-group">
            <label for="template_file">Upload .pptx template</label>
            <input type="file" id="template_file" name="template_file" accept=".pptx" required />
          </div>
          <label class="form-group form-group--stacked">
            <input type="checkbox" name="use_for_next_week" value="1" />
            Start the next week from this template after confirm
          </label>
          <button type="submit" class="btn-secondary">Validate upload</button>
        </form>
        <?php endif; ?>
      </section>
      <?php endif; ?>

      <?php if ($week !== null && ($week['state'] ?? '') === 'draft' && status_update_can_contribute() && $draftState !== null && $draftState['ok']): ?>
      <div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">
        <section class="admin-card">
          <h2 class="h4">Claude</h2>
          <?php foreach ($chat as $msg): ?>
          <div class="admin-card" style="margin-bottom:0.75rem;padding:0.75rem;">
            <strong><?= htmlspecialchars((string) $msg['role']) ?></strong>
            <?php if ((string) $msg['role'] === 'assistant'): ?>
              <?php
                $payload = json_decode((string) $msg['content'], true);
                if (is_array($payload)):
              ?>
              <?php if (!empty($payload['text'])): ?><p><?= nl2br(htmlspecialchars((string) $payload['text'])) ?></p><?php endif; ?>
              <?php if (!empty($payload['proposals']['changes']) && is_array($payload['proposals']['changes'])): ?>
              <table class="admin-table">
                <thead><tr><th>Field</th><th>Proposed</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($payload['proposals']['changes'] as $change): ?>
                  <?php
                    $field = (string) ($change['field'] ?? '');
                    $proposed = $change['new_value'] ?? [];
                    if (!is_array($proposed)) {
                        $proposed = [];
                    }
                    $latestId = $repo->latestChangeIdForField((int) $week['id'], $field);
                  ?>
                  <tr>
                    <td><?= htmlspecialchars($field) ?></td>
                    <td><?= htmlspecialchars(implode(' / ', array_map('strval', $proposed))) ?></td>
                    <td>
                      <form method="post" class="inline-form">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>" />
                        <input type="hidden" name="action" value="accept_change" />
                        <input type="hidden" name="field" value="<?= htmlspecialchars($field) ?>" />
                        <input type="hidden" name="latest_change_id" value="<?= (int) $latestId ?>" />
                        <input type="hidden" name="new_value_json" value="<?= htmlspecialchars(json_encode(array_values($proposed), JSON_UNESCAPED_UNICODE)) ?>" />
                        <button type="submit" class="btn-text">Accept</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
              <?php endif; ?>
              <?php else: ?>
              <p><?= nl2br(htmlspecialchars((string) $msg['content'])) ?></p>
              <?php endif; ?>
            <?php else: ?>
            <p><?= nl2br(htmlspecialchars((string) $msg['content'])) ?></p>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
          <form method="post" class="admin-form">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>" />
            <input type="hidden" name="action" value="chat_send" />
            <label for="notes">Your status notes</label>
            <textarea id="notes" name="notes" rows="6" class="form-input" placeholder="What changed this week? Claude will map notes to deck fields."></textarea>
            <button type="submit" class="btn-primary" style="margin-top:0.5rem;">Send to Claude</button>
          </form>
        </section>

        <section class="admin-card">
          <h2 class="h4">Shared draft</h2>
          <p class="text-muted"><?= count($draftState['map']) ?> editable fields · poll refresh every 20s.</p>
          <div id="status-draft-fields" style="max-height:32rem;overflow:auto;">
            <?php foreach ($draftState['map'] as $fieldKey => $lines): ?>
              <?php if (!is_array($lines)) { continue; } ?>
              <div class="admin-card" style="margin-bottom:0.5rem;padding:0.5rem;" data-field="<?= htmlspecialchars($fieldKey) ?>">
                <strong><?= htmlspecialchars($fieldKey) ?></strong>
                <div><?= htmlspecialchars(implode(' ¶ ', $lines)) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </section>
      </div>
      <script>
      (function () {
        let since = <?= (int) $pollSince ?>;
        const date = <?= json_encode($statusDate) ?>;
        setInterval(function () {
          fetch('/weekly-na-status-update/?action=changes&since=' + encodeURIComponent(since) + '&date=' + encodeURIComponent(date), {credentials: 'same-origin'})
            .then(function (r) { return r.json(); })
            .then(function (data) {
              if (!data || !data.changes) return;
              data.changes.forEach(function (row) {
                since = Math.max(since, row.id);
                const el = document.querySelector('[data-field="' + CSS.escape(row.field_key) + '"]');
                if (el) {
                  el.setAttribute('title', 'Updated by ' + row.author);
                }
              });
            })
            .catch(function () {});
        }, 20000);
      })();
      </script>
      <?php endif; ?>

      <section class="admin-card" style="margin-top:1.25rem;">
        <h2 class="h4">How to use</h2>
        <p>See the <a href="/operations/manual/#weekly-na-status-update">Operations User Manual</a> for the full workflow (template upload, structural changes, Claude, publish).</p>
      </section>
    </div>
  </main>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>