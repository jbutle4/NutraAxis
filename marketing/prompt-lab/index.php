<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing-prompts.php';

auth_require_module_read('research-prompt-lab');

$activeSlug = 'research-prompt-lab';
$baseHref = '/marketing/prompt-lab/';
$key = trim((string) ($_GET['key'] ?? ''));
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    marketing_require_admin();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'create') {
        $result = mkt_prompt_create_version($_POST);
        if ($result['ok']) {
            marketing_redirect($baseHref, ['key' => (string) $_POST['prompt_key'], 'notice' => 'Saved version ' . $result['version'] . '.']);
        }
        $error = $result['error'];
        $key = (string) ($_POST['prompt_key'] ?? $key);
    }
    if ($action === 'activate') {
        $ok = mkt_prompt_activate((string) ($_POST['prompt_key'] ?? ''), (int) ($_POST['version'] ?? 0));
        marketing_redirect($baseHref, ['key' => (string) ($_POST['prompt_key'] ?? '')] + ($ok ? ['notice' => 'Version activated.'] : ['error' => 'Could not activate that version.']));
    }
}

$versions = $key !== '' ? mkt_prompt_versions($key) : [];
$base = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : ($versions[0] ?? []);
$activeRow = null;
foreach ($versions as $row) {
    if (!empty($row['IsActive'])) {
        $activeRow = $row;
    }
}
$seed = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : ($activeRow ?? $base);
$value = static fn(string $postKey, string $rowKey, $default = '') => htmlspecialchars((string) ($seed[$postKey] ?? $seed[$rowKey] ?? $default));

$pageTitle = 'Prompt Lab | NutraAxis Operations';
$pageDescription = 'Versioned AI prompts for discovery, scoring, synthesis, and generation.';
$back = app_module_hub_back_link($activeSlug);

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => $key !== '' ? $baseHref : $back['href'],
          'back_label' => $key !== '' ? 'Back to Prompt Lab' : $back['label'],
          'category'   => 'Marketing & Research',
          'title'      => $key !== '' ? $key : 'Prompt Lab',
          'lead'       => 'Every AI call uses the active version of a prompt here. Saving creates a new version; older versions stay for comparison and rollback. Placeholders use {{name}}.',
          'permission' => auth_module_permission_label($activeSlug),
      ]);
      marketing_render_notice($_GET['notice'] ?? null, $_GET['error'] ?? $error);
      ?>

<?php if ($key === ''): ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Prompt</th><th>Active version</th><th>Provider / model</th><th>Latest version</th><th>Calls (30 days)</th><th>Last changed</th></tr></thead>
          <tbody>
            <?php foreach (mkt_prompt_keys() as $row): ?>
            <tr>
              <td><a class="table-name-link" href="<?= htmlspecialchars($baseHref) ?>?key=<?= rawurlencode((string) $row['PromptKey']) ?>"><?= htmlspecialchars((string) $row['PromptKey']) ?></a></td>
              <td><?= $row['ActiveVersion'] !== null ? 'v' . (int) $row['ActiveVersion'] : '<em>none</em>' ?></td>
              <td><?= htmlspecialchars(MKT_PROVIDERS[(string) $row['ActiveProvider']] ?? '—') ?> <span class="form-hint"><?= htmlspecialchars((string) ($row['ActiveModel'] ?? 'default model')) ?></span></td>
              <td>v<?= (int) $row['LatestVersion'] ?></td>
              <td><?= (int) $row['Calls30d'] ?></td>
              <td><?= htmlspecialchars(marketing_format_datetime($row['LastChanged'] ?? null)) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if (marketing_can_admin()): ?>
      <p><a class="btn-primary" href="<?= htmlspecialchars($baseHref) ?>?key=new">New prompt</a></p>
      <?php endif; ?>
<?php else: ?>
      <?php if ($versions !== []): ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Version</th><th>Provider / model</th><th>Temp / max tokens</th><th>Notes</th><th>Created</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($versions as $row): ?>
            <tr>
              <td>v<?= (int) $row['Version'] ?></td>
              <td><?= htmlspecialchars(MKT_PROVIDERS[(string) $row['Provider']] ?? (string) $row['Provider']) ?> <span class="form-hint"><?= htmlspecialchars((string) ($row['Model'] ?? 'default')) ?></span></td>
              <td><?= $row['Temperature'] !== null ? htmlspecialchars((string) $row['Temperature']) : 'default' ?> / <?= (int) $row['MaxTokens'] ?></td>
              <td><?= htmlspecialchars((string) ($row['Notes'] ?? '')) ?></td>
              <td><?= htmlspecialchars(marketing_format_datetime($row['CreatedAt'] ?? null)) ?><?= !empty($row['CreatedByName']) ? ' · ' . htmlspecialchars((string) $row['CreatedByName']) : '' ?></td>
              <td>
                <?php if (!empty($row['IsActive'])): ?>
                <?= mkt_render_badge('active') ?>
                <?php elseif (marketing_can_admin()): ?>
                <form method="post" class="table-action-form">
                  <input type="hidden" name="action" value="activate" />
                  <input type="hidden" name="prompt_key" value="<?= htmlspecialchars($key) ?>" />
                  <input type="hidden" name="version" value="<?= (int) $row['Version'] ?>" />
                  <button type="submit" class="btn-secondary">Activate</button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

      <h2 class="hub-section-title"><?= $versions === [] ? 'Create prompt' : 'New version (based on the active version)' ?></h2>
      <form class="admin-form" method="post" action="<?= htmlspecialchars($baseHref) ?>?key=<?= rawurlencode($key) ?>">
        <input type="hidden" name="action" value="create" />
        <div class="form-grid">
          <div class="form-group">
            <label for="prompt_key">Prompt key</label>
            <input class="form-input" id="prompt_key" name="prompt_key" required value="<?= htmlspecialchars($key === 'new' ? (string) ($_POST['prompt_key'] ?? '') : $key) ?>" <?= $versions !== [] ? 'readonly' : '' ?> placeholder="area.name" />
          </div>
          <div class="form-group">
            <label for="provider">Provider</label>
            <select class="form-input" id="provider" name="provider">
              <?php foreach (MKT_PROVIDERS as $p => $label): ?>
              <option value="<?= $p ?>" <?= ($seed['provider'] ?? $seed['Provider'] ?? 'anthropic') === $p ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="model">Model</label>
            <input class="form-input" id="model" name="model" value="<?= $value('model', 'Model') ?>" placeholder="blank = default for provider (Admin & Jobs → Settings)" />
          </div>
          <div class="form-group">
            <label for="temperature">Temperature</label>
            <input class="form-input" type="number" step="0.05" min="0" max="1" id="temperature" name="temperature" value="<?= $value('temperature', 'Temperature') ?>" />
          </div>
          <div class="form-group">
            <label for="max_tokens">Max output tokens</label>
            <input class="form-input" type="number" min="100" max="32000" id="max_tokens" name="max_tokens" value="<?= $value('max_tokens', 'MaxTokens', 2000) ?>" />
          </div>
          <div class="form-group form-grid-full">
            <label for="system_prompt">System prompt</label>
            <textarea class="form-input" id="system_prompt" name="system_prompt" rows="6"><?= $value('system_prompt', 'SystemPrompt') ?></textarea>
          </div>
          <div class="form-group form-grid-full">
            <label for="user_template">User template</label>
            <textarea class="form-input" id="user_template" name="user_template" rows="16" required><?= $value('user_template', 'UserTemplate') ?></textarea>
          </div>
          <div class="form-group form-grid-full">
            <label for="notes">Change notes</label>
            <input class="form-input" id="notes" name="notes" maxlength="1000" value="" placeholder="What changed and why" />
          </div>
          <div class="form-group form-group--stacked">
            <label><input type="checkbox" name="activate" value="1" /> Activate this version immediately</label>
          </div>
        </div>
        <?php if (marketing_can_admin()): ?>
        <div class="form-actions"><button type="submit" class="btn-primary">Save new version</button></div>
        <?php else: ?>
        <p class="form-hint">Editing prompts requires full Marketing & Research access.</p>
        <?php endif; ?>
      </form>
<?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
