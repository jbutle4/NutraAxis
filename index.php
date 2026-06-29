<?php
require __DIR__ . '/includes/init.php';

$pageTitle = 'NutraAxis Operations Dashboard';
$pageDescription = 'NutraAxis Operations Dashboard — internal tools and resources for the NutraAxis team.';
$visibleFunctions = auth_filter_modules(app_functions());
$functionGroups = app_functions_grouped($visibleFunctions);

$pendingApprovalCount = 0;
if (auth_is_logged_in()) {
    require_once __DIR__ . '/includes/po.php';
    require_once __DIR__ . '/includes/po-approval.php';
    if (po_can_read_approval_queue()) {
        $pendingApprovalCount = po_count_pending_approvals();
    }
}

require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container">
      <div class="hero-inner page-inner">
        <div class="section-label">Internal Portal</div>
        <p class="page-lead">
          Supply chain workflows and administration tools for the NutraAxis team — from product master data through fulfillment, plus finance, legal, and operations support.
        </p>

        <?php if (!auth_is_logged_in()): ?>
        <div class="auth-prompt">
          <a class="btn-primary" href="<?= htmlspecialchars(auth_login_url()) ?>">Log In to Access Applications</a>
          <p class="auth-prompt-note">Application links require sign-in. Use your NutraAxis email and password.</p>
        </div>
        <?php else: ?>
        <p class="auth-welcome">Signed in as <strong><?= htmlspecialchars(auth_user()['UserName']) ?></strong> (<?= htmlspecialchars(auth_user()['RoleName']) ?>)</p>
        <?php endif; ?>

        <?php if (auth_is_logged_in() && $visibleFunctions === []): ?>
        <div class="status-banner">
          <div>
            <strong>No applications assigned</strong>
            <p>Your role does not include access to any Operations modules. Contact a site administrator.</p>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($pendingApprovalCount > 0): ?>
        <div class="status-banner status-banner-approval">
          <div>
            <strong><?= $pendingApprovalCount === 1 ? '1 purchase order needs' : $pendingApprovalCount . ' purchase orders need' ?> your approval</strong>
            <p>Email notifications may be unavailable. Use the approval queue to review and action submitted POs.</p>
          </div>
          <a class="btn-primary" href="/approvals/?status=pending">Review Approvals</a>
        </div>
        <?php endif; ?>

        <div class="portal-function-groups">
          <?php foreach ($functionGroups as $group): ?>
          <section class="portal-function-group portal-function-group--<?= htmlspecialchars($group['key']) ?>" aria-labelledby="portal-group-<?= htmlspecialchars($group['key']) ?>">
            <header class="portal-function-group__header">
              <h2 class="portal-function-group__title" id="portal-group-<?= htmlspecialchars($group['key']) ?>"><?= htmlspecialchars($group['title']) ?></h2>
              <p class="portal-function-group__desc"><?= htmlspecialchars($group['desc']) ?></p>
            </header>
            <div class="functions">
              <?php foreach ($group['modules'] as $item): ?>
              <?php
                $moduleHref = auth_is_logged_in()
                    ? $item['href']
                    : auth_login_url($item['href']);
              ?>
              <a class="function-card" href="<?= htmlspecialchars($moduleHref) ?>">
                <div class="function-icon"><?= icon_svg($item['icon']) ?></div>
                <h3><?= htmlspecialchars($item['title']) ?></h3>
                <p><?= htmlspecialchars($item['desc']) ?></p>
                <span class="function-link">
                  Open
                  <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M5 12h14M12 5l7 7-7 7"/>
                  </svg>
                </span>
              </a>
              <?php endforeach; ?>
            </div>
          </section>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </main>
<?php
require __DIR__ . '/includes/footer.php';
