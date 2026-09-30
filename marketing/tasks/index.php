<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing-tasks.php';

auth_require_module_read('marketing-tasks');

$activeSlug = 'marketing-tasks';
$baseHref = '/marketing/tasks/';
$tabs = [
    'mine' => 'My tasks',
    'open' => 'All open',
    'done' => 'Closed',
    'new'  => 'New task',
];
$tab = array_key_exists((string) ($_GET['tab'] ?? ''), $tabs) ? (string) $_GET['tab'] : 'mine';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    marketing_require_update();
    $action = (string) ($_POST['action'] ?? '');
    $taskId = (int) ($_POST['task_id'] ?? 0);
    $returnTab = array_key_exists((string) ($_POST['tab'] ?? ''), $tabs) ? (string) $_POST['tab'] : 'mine';
    $result = match ($action) {
        'create'   => mkt_task_create($_POST),
        'complete' => mkt_task_set_status($taskId, 'done', (string) ($_POST['note'] ?? '')),
        'cancel'   => mkt_task_set_status($taskId, 'cancelled', (string) ($_POST['note'] ?? '')),
        'reopen'   => mkt_task_set_status($taskId, 'open'),
        'assign'   => mkt_task_assign($taskId, (int) ($_POST['assignee_user_id'] ?? 0)),
        default    => ['ok' => false, 'error' => 'Unknown action.'],
    };
    if ($result['ok']) {
        $notice = match ($action) {
            'create'   => 'Task created.',
            'complete' => 'Task done.',
            'cancel'   => 'Task cancelled.',
            'reopen'   => 'Task reopened.',
            default    => 'Task reassigned.',
        };
        marketing_redirect($baseHref, ['tab' => $action === 'create' ? 'open' : $returnTab, 'notice' => $notice]);
    }
    $error = $result['error'];
    if ($action === 'create') {
        $tab = 'new';
    }
}

$sync = mkt_tasks_sync();
$canUpdate = marketing_can_update();
$counts = mkt_task_counts();
$owners = mkt_content_owner_options();
$today = mkt_cal_now_local()->format('Y-m-d');
$role = (string) ($_GET['role'] ?? '');
$kind = (string) ($_GET['kind'] ?? '');

$pageTitle = 'Tasks | NutraAxis Operations';
$pageDescription = 'Marketing work queue: tasks the Content Pipeline, Campaign Studio and Publishing Calendar open and close, plus manual tasks.';
$back = app_module_hub_back_link($activeSlug);

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';

$tabLabels = $tabs;
$tabLabels['mine'] .= ' (' . $counts['mine'] . ')';
$tabLabels['open'] .= ' (' . $counts['open'] . ')';
if (!$canUpdate) {
    unset($tabLabels['new']);
}
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => $back['href'],
          'back_label' => $back['label'],
          'category'   => 'Marketing & Research',
          'title'      => 'Tasks',
          'lead'       => 'Automatic tasks open when a piece or campaign needs someone (a brief to approve, a review, a publish, a schedule, a GoHighLevel load) and close by themselves when the work is done. Add manual tasks for anything else.',
          'permission' => auth_module_permission_label($activeSlug),
      ]);
      marketing_render_notice($_GET['notice'] ?? null, $_GET['error'] ?? $error);
      marketing_render_tabs($baseHref, $tabLabels, $tab);
      ?>

      <div class="status-banner">
        <div>
          <strong>Queue</strong>
          <p>
            <?= $counts['open'] ?> open · <?= $counts['mine'] ?> for you ·
            <?= $counts['overdue'] > 0 ? '<span class="mkt-task-overdue">' . $counts['overdue'] . ' overdue</span>' : '0 overdue' ?>
            <?= $sync['opened'] + $sync['closed'] > 0 ? ' · just now: ' . $sync['opened'] . ' opened, ' . $sync['closed'] . ' closed automatically' : '' ?>
          </p>
          <p class="form-hint">"My tasks" shows tasks assigned to you plus unassigned tasks for the roles you hold (<?= htmlspecialchars(implode(', ', array_map(static fn(string $r): string => MKT_TASK_ROLES[$r], mkt_task_my_roles())) ?: 'none') ?>). Automatic tasks are due a set number of calendar days after they open (<code>tasks.sla_days</code>); a publish task uses the piece's due date if it has one, and a loading task the post's scheduled date. Tasks you create only have the due date you give them.</p>
        </div>
      </div>

      <?php if ($tab === 'new' && $canUpdate): ?>
        <form class="admin-form" method="post" action="<?= htmlspecialchars($baseHref) ?>">
          <input type="hidden" name="action" value="create" />
          <?php marketing_render_field_guide_link('task-new'); ?>
          <div class="form-grid">
            <div class="form-group form-grid-full"><label for="title">Task</label><input class="form-input" id="title" name="title" required maxlength="300" value="<?= htmlspecialchars((string) ($_POST['title'] ?? '')) ?>" /></div>
            <div class="form-group">
              <label for="assignee_user_id">Assignee</label>
              <select class="form-input" id="assignee_user_id" name="assignee_user_id">
                <option value="">Unassigned</option>
                <?php foreach ($owners as $row): ?>
                <option value="<?= (int) $row['UserID'] ?>" <?= (string) ($_POST['assignee_user_id'] ?? '') === (string) $row['UserID'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $row['UserName']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="assignee_role">Role</label>
              <select class="form-input" id="assignee_role" name="assignee_role">
                <option value="">Any</option>
                <?php foreach (MKT_TASK_ROLES as $key => $label): ?>
                <option value="<?= $key ?>" <?= (string) ($_POST['assignee_role'] ?? '') === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group"><label for="due_date">Due</label><input class="form-input" type="date" id="due_date" name="due_date" value="<?= htmlspecialchars((string) ($_POST['due_date'] ?? '')) ?>" /></div>
            <div class="form-group">
              <label for="priority">Priority</label>
              <select class="form-input" id="priority" name="priority">
                <?php foreach (MKT_TASK_PRIORITIES as $key => $label): ?>
                <option value="<?= $key ?>" <?= (string) ($_POST['priority'] ?? 'normal') === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group form-grid-full"><label for="href">Link</label><input class="form-input" id="href" name="href" maxlength="500" value="<?= htmlspecialchars((string) ($_POST['href'] ?? '')) ?>" placeholder="Optional — a portal path like /marketing/content/view.php?id=3, or a full URL" /></div>
            <div class="form-group form-grid-full"><label for="detail">Detail</label><textarea class="form-input" id="detail" name="detail" rows="3" maxlength="2000"><?= htmlspecialchars((string) ($_POST['detail'] ?? '')) ?></textarea></div>
          </div>
          <div class="form-actions"><button type="submit" class="btn-primary">Create task</button></div>
        </form>
      <?php else: ?>
        <?php
        $rows = mkt_tasks_list([
            'status' => $tab === 'done' ? '' : 'open',
            'scope'  => $tab === 'mine' ? 'mine' : '',
            'role'   => $role,
            'kind'   => $kind,
        ]);
        if ($tab === 'done') {
            $rows = array_values(array_filter($rows, static fn(array $r): bool => $r['Status'] !== 'open'));
        }
        ?>
        <form class="mkt-inline-form" method="get" action="<?= htmlspecialchars($baseHref) ?>" style="margin-bottom:0.75rem">
          <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>" />
          <select class="form-input" name="role">
            <option value="">All roles</option>
            <?php foreach (MKT_TASK_ROLES as $key => $label): ?>
            <option value="<?= $key ?>" <?= $role === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
          </select>
          <select class="form-input" name="kind">
            <option value="">Automatic and manual</option>
            <option value="auto" <?= $kind === 'auto' ? 'selected' : '' ?>>Automatic only</option>
            <option value="manual" <?= $kind === 'manual' ? 'selected' : '' ?>>Manual only</option>
          </select>
          <button type="submit" class="btn-secondary">Filter</button>
        </form>
        <?php if ($canUpdate) { marketing_render_field_guide_link('task-close'); } ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Task</th><th>Role</th><th>Assignee</th><th>Due</th><th><?= $tab === 'done' ? 'Closed' : 'Priority' ?></th><th>Source</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if ($rows === []): ?>
              <tr><td colspan="7"><?= $tab === 'done' ? 'No closed tasks.' : 'Nothing to do — the queue is clear.' ?></td></tr>
              <?php endif; ?>
              <?php foreach ($rows as $row): ?>
              <?php
              $due = $row['DueDate'] ? substr((string) $row['DueDate'], 0, 10) : null;
              $overdue = $row['Status'] === 'open' && $due !== null && $due < $today;
              $manual = $row['AutoKey'] === null;
              ?>
              <tr>
                <td>
                  <?php if (!empty($row['Href'])): ?>
                  <a href="<?= htmlspecialchars((string) $row['Href']) ?>"><strong><?= htmlspecialchars((string) $row['Title']) ?></strong></a>
                  <?php else: ?>
                  <strong><?= htmlspecialchars((string) $row['Title']) ?></strong>
                  <?php endif; ?>
                  <?php if (!empty($row['Detail'])): ?><div class="form-hint"><?= nl2br(htmlspecialchars((string) $row['Detail'])) ?></div><?php endif; ?>
                  <?php if (!empty($row['CompletionNote'])): ?><div class="form-hint">Closing note: <?= htmlspecialchars((string) $row['CompletionNote']) ?></div><?php endif; ?>
                </td>
                <td><?= htmlspecialchars(MKT_TASK_ROLES[(string) $row['AssigneeRole']] ?? '—') ?></td>
                <td>
                  <?php if ($canUpdate && $row['Status'] === 'open'): ?>
                  <form method="post" action="<?= htmlspecialchars($baseHref) ?>" class="mkt-inline-form">
                    <input type="hidden" name="action" value="assign" /><input type="hidden" name="task_id" value="<?= (int) $row['TaskID'] ?>" /><input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>" />
                    <select class="form-input" name="assignee_user_id" onchange="this.form.submit()" aria-label="Assignee">
                      <option value="">Unassigned</option>
                      <?php foreach ($owners as $owner): ?>
                      <option value="<?= (int) $owner['UserID'] ?>" <?= (int) $row['AssigneeUserID'] === (int) $owner['UserID'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $owner['UserName']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </form>
                  <?php else: ?>
                  <?= htmlspecialchars((string) ($row['AssigneeName'] ?? 'Unassigned')) ?>
                  <?php endif; ?>
                </td>
                <td<?= $overdue ? ' class="mkt-task-overdue"' : '' ?>><?= $due !== null ? htmlspecialchars(marketing_format_date($due)) . ($overdue ? ' (overdue)' : '') : '—' ?></td>
                <td>
                  <?php if ($tab === 'done'): ?>
                  <?= htmlspecialchars(MKT_TASK_STATUSES[(string) $row['Status']] ?? '') ?> <?= htmlspecialchars(marketing_format_datetime($row['CompletedAt'] ?? null)) ?><?= !empty($row['CompletedByName']) ? '<div class="form-hint">' . htmlspecialchars((string) $row['CompletedByName']) . '</div>' : '' ?>
                  <?php else: ?>
                  <?= $row['Priority'] === 'high' ? mkt_render_badge('failed', ['failed' => 'High']) : 'Normal' ?>
                  <?php endif; ?>
                </td>
                <td><?= $manual ? 'Manual' . (!empty($row['CreatedByName']) ? '<div class="form-hint">' . htmlspecialchars((string) $row['CreatedByName']) . '</div>' : '') : 'Automatic' ?></td>
                <td>
                  <?php if (!$manual): ?>
                  <span class="form-hint"><?= $row['Status'] === 'open' ? 'Closes when done' : '' ?></span>
                  <?php elseif ($canUpdate && $row['Status'] === 'open'): ?>
                  <form method="post" action="<?= htmlspecialchars($baseHref) ?>" class="mkt-inline-form">
                    <input type="hidden" name="task_id" value="<?= (int) $row['TaskID'] ?>" /><input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>" />
                    <input class="form-input" name="note" maxlength="500" placeholder="Note (optional)" aria-label="Closing note" style="min-width:7rem" />
                    <button type="submit" class="btn-secondary" name="action" value="complete">Done</button>
                    <button type="submit" class="btn-text" name="action" value="cancel">Cancel</button>
                  </form>
                  <?php elseif ($canUpdate): ?>
                  <form method="post" action="<?= htmlspecialchars($baseHref) ?>">
                    <input type="hidden" name="task_id" value="<?= (int) $row['TaskID'] ?>" /><input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>" />
                    <button type="submit" class="btn-text" name="action" value="reopen">Reopen</button>
                  </form>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
