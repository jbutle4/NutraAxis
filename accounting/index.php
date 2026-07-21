<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/module-hub.php';
require dirname(__DIR__) . '/includes/accounting.php';

auth_require_module_read('accounting');

$activeSlug = 'accounting';
$hub = get_module('accounting');
if ($hub === null) {
    http_response_code(404);
    exit('Module hub not found.');
}

$areas = auth_filter_hub_submodules(app_hub_submodules('accounting'));
$notice = $_GET['notice'] ?? null;
$showConnectionBanner = accounting_can_read() || accounting_can_update();

if ($showConnectionBanner || $notice === 'connected' || $notice === 'disconnected' || $notice === 'config') {
    require dirname(__DIR__) . '/includes/quickbooks.php';
}

$noticeEnv = isset($_GET['env']) && function_exists('qbo_normalize_environment')
    ? qbo_normalize_environment($_GET['env'])
    : null;

$pageTitle = ($hub['title'] ?? 'Accounting') . ' | NutraAxis Operations';
$pageDescription = (string) ($hub['desc'] ?? '');

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php render_list_page_header([
          'back_href'  => '/',
          'back_label' => 'Back to Operations Home',
          'category'   => (string) ($hub['label'] ?? 'Administration'),
          'title'      => (string) ($hub['headline'] ?? $hub['title'] ?? 'Accounting'),
          'lead'       => (string) ($hub['lead'] ?? $hub['desc'] ?? ''),
      ]); ?>

      <?php if ($notice === 'connected'): ?>
      <div class="admin-notice is-success" role="status">QuickBooks <?= $noticeEnv ? htmlspecialchars(qbo_environment_label($noticeEnv)) . ' ' : '' ?>connected successfully.</div>
      <?php elseif ($notice === 'disconnected'): ?>
      <div class="admin-notice is-success" role="status">QuickBooks <?= $noticeEnv ? htmlspecialchars(qbo_environment_label($noticeEnv)) . ' ' : '' ?>disconnected.</div>
      <?php elseif ($notice === 'config'): ?>
      <div class="admin-notice is-error is-detail" role="alert">QuickBooks OAuth is not fully configured for <?= $noticeEnv ? htmlspecialchars(qbo_environment_label($noticeEnv)) : 'that' ?> environment. Check App Service client ID/secret and redirect URI settings.</div>
      <?php endif; ?>

      <?php if ($showConnectionBanner): ?>
      <?php require dirname(__DIR__) . '/includes/accounting-connection-dual-banner.php'; ?>
      <?php endif; ?>

      <?php if ($areas === []): ?>
      <div class="status-banner">
        <div>
          <strong>No applications assigned</strong>
          <p>Your role does not include access to any modules in this area. Contact a site administrator.</p>
        </div>
      </div>
      <?php else: ?>
      <?php hub_render_capability_cards($areas, 'capability-card capability-card-link', 'capability-grid capability-grid--six'); ?>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
