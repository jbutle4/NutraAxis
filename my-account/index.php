<?php
require dirname(__DIR__) . '/includes/init.php';

auth_require_my_account();

$user = auth_user();
$pageTitle = 'My Account | NutraAxis Operations';
$pageDescription = 'View your NutraAxis Operations account and role permissions.';

$permissionRows = [
    ['PO Management', auth_permission_value('POManagement')],
    ['PO Approval', auth_permission_value('POApproval')],
    ['Travel & Expense', auth_permission_value('TEManagement')],
    ['T&E Approval', auth_permission_value('TEApproval')],
    ['Jazz Current Inventory', auth_permission_value('InventoryReporting')],
    ['Sales Reporting', auth_permission_value('SalesReporting')],
    ['Inventory Forecasting', auth_permission_value('InventoryForecasting')],
    ['Labeling Operations', auth_permission_value('LabelingOperations')],
    ['Operations Dashboard', auth_permission_value('OperationsDashboard')],
    ['Legal Agreements & Contracts', auth_permission_value('LegalAgreements')],
    ['Product Catalog / SKU Master', auth_permission_value('ProductCatalog')],
    ['Links Index', auth_permission_value('LinksIndex')],
    ['Support', auth_permission_value('Support')],
    ['Accounting', auth_permission_value('Accounting')],
    ['User Administration', auth_permission_value('UserAdmin')],
    ['Role Administration', auth_permission_value('RoleAdmin')],
];

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Operations Home
      </a>

      <div class="page-hero">
        <div class="section-label">Account</div>
        <h1>My Account</h1>
        <p class="page-lead">Your profile and role-based access for NutraAxis Operations.</p>
      </div>

      <div class="account-grid">
        <div class="account-card">
          <h2>Profile</h2>
          <dl class="account-details">
            <div>
              <dt>Name</dt>
              <dd><?= htmlspecialchars($user['UserName']) ?></dd>
            </div>
            <div>
              <dt>Email</dt>
              <dd><?= htmlspecialchars($user['UserLogin']) ?></dd>
            </div>
            <div>
              <dt>Role</dt>
              <dd><?= htmlspecialchars($user['RoleName']) ?></dd>
            </div>
          </dl>
        </div>

        <div class="account-card">
          <h2>Permissions</h2>
          <p class="account-card-lead">Access levels assigned to your role (CRUD: Create, Read, Update, Delete).</p>
          <table class="permission-table">
            <thead>
              <tr>
                <th>Area</th>
                <th>Access</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($permissionRows as [$label, $value]): ?>
              <tr>
                <td><?= htmlspecialchars($label) ?></td>
                <td>
                  <span class="permission-badge <?= $value ? 'is-granted' : 'is-denied' ?>">
                    <?= htmlspecialchars(permission_label($value)) ?>
                  </span>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="module-actions">
        <a class="btn-secondary" href="/logout/">Log Out</a>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
