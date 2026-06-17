<?php
require __DIR__ . '/includes/init.php';
require __DIR__ . '/includes/hub-cards.php';

$pageTitle = 'NutraAxis Operations Dashboard';
$pageDescription = 'NutraAxis Operations Dashboard — internal tools and resources for the NutraAxis team.';
$visibleFunctions = auth_filter_modules(app_functions());

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
          Your hub for inventory management, legal agreements, sales reporting, labeling, operations dashboard, accounting, support, and team resources.
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
          <a class="btn-primary" href="/po-management/approvals.php">Review Approvals</a>
        </div>
        <?php endif; ?>

        <?php
            $homeCards = array_map(static function (array $item): array {
                $item['href'] = auth_is_logged_in()
                    ? $item['href']
                    : auth_login_url($item['href']);

                return $item;
            }, $visibleFunctions);
            $homeSections = hub_cards_partition_uat($homeCards);
        ?>
        <div class="functions">
          <?php
            hub_render_function_card_grid($homeSections['production'], false);
            if ($homeSections['uat'] !== []) {
                echo '<h2 class="hub-uat-section-title">UAT / Test Systems</h2>';
                hub_render_function_card_grid($homeSections['uat'], false);
            }
          ?>
        </div>
      </div>
    </div>
  </main>
<?php
require __DIR__ . '/includes/footer.php';
