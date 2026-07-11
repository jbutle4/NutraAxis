<?php
require __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/hub-cards.php';
require_once __DIR__ . '/includes/operations-dashboard.php';

$pageTitle = 'NutraAxis Operations Dashboard';
$pageDescription = 'NutraAxis Operations Dashboard — internal tools and resources for the NutraAxis team.';
$visibleFunctions = array_values(array_filter(
    auth_filter_modules(app_functions()),
    fn(array $module): bool => ($module['slug'] ?? '') !== 'operations-dashboard'
));
$functionGroups = app_functions_grouped($visibleFunctions);
$dashboardGroups = [];

if (auth_is_logged_in() && auth_can_read_module('operations-dashboard')) {
    foreach (operations_dashboard_sections() as $section) {
        $links = [];
        foreach ($section['links'] ?? [] as $link) {
            $normalized = operations_dashboard_normalize_link($link);
            if (!operations_dashboard_link_visible($normalized)) {
                continue;
            }

            $links[] = $normalized;
        }

        if (($section['title'] ?? '') === 'IT & Ecommerce Management Systems') {
            foreach (operations_dashboard_action_cards() as $actionCard) {
                if (($actionCard['action'] ?? '') !== 'accs_test_orders') {
                    continue;
                }

                $links[] = operations_dashboard_normalize_link([
                    'title'    => (string) ($actionCard['title'] ?? 'ACCS Test Order Creation'),
                    'desc'     => (string) ($actionCard['desc'] ?? 'Open the test order creation tool.'),
                    'href'     => '/operations-dashboard/#accs-test-orders-form',
                    'icon'     => (string) ($actionCard['icon'] ?? 'chart'),
                    'internal' => true,
                    'tier'     => $actionCard['tier'] ?? ENVIRONMENT_TIER_UAT,
                ]);
            }
        }

        if ($links !== []) {
            $dashboardGroups[] = [
                'key'     => preg_replace('/[^a-z0-9]+/', '-', strtolower((string) ($section['title'] ?? 'dashboard'))),
                'title'   => (string) ($section['title'] ?? 'Operations Dashboard'),
                'desc'    => (string) (($section['title'] ?? '') === 'Operations'
                    ? 'Operational shortcuts, support tools, documentation, and curated team links.'
                    : 'Production and UAT systems for Adobe Commerce, QuickBooks, Jazz OMS, Azure, and ecommerce administration.'),
                'links'   => $links,
            ];
        }
    }
}

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

        <?php if (auth_is_logged_in() && $visibleFunctions === [] && $dashboardGroups === []): ?>
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
          <details class="portal-function-group portal-function-group--<?= htmlspecialchars($group['key']) ?>">
            <summary class="portal-function-group__summary" aria-controls="portal-group-panel-<?= htmlspecialchars($group['key']) ?>">
              <span class="portal-function-group__header">
                <span class="portal-function-group__title"><?= htmlspecialchars($group['title']) ?></span>
                <span class="portal-function-group__desc"><?= htmlspecialchars($group['desc']) ?></span>
              </span>
              <span class="portal-function-group__toggle" aria-hidden="true">Expand</span>
            </summary>
            <div class="portal-function-group__panel" id="portal-group-panel-<?= htmlspecialchars($group['key']) ?>">
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
            </div>
          </details>
          <?php endforeach; ?>

          <?php foreach ($dashboardGroups as $group): ?>
          <details class="portal-function-group portal-function-group--<?= htmlspecialchars($group['key']) ?>">
            <summary class="portal-function-group__summary" aria-controls="portal-group-panel-<?= htmlspecialchars($group['key']) ?>">
              <span class="portal-function-group__header">
                <span class="portal-function-group__title"><?= htmlspecialchars($group['title']) ?></span>
                <span class="portal-function-group__desc"><?= htmlspecialchars($group['desc']) ?></span>
              </span>
              <span class="portal-function-group__toggle" aria-hidden="true">Expand</span>
            </summary>
            <div class="portal-function-group__panel" id="portal-group-panel-<?= htmlspecialchars($group['key']) ?>">
              <div class="functions">
                <?php hub_render_function_card_grid($group['links'], false); ?>
              </div>
            </div>
          </details>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </main>
<?php
require __DIR__ . '/includes/footer.php';
