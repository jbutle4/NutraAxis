<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/links.php';
require dirname(__DIR__) . '/includes/hub-cards.php';

auth_require_module_read('operations-dashboard');

function operations_dashboard_normalize_link(array $link): array
{
    if (!isset($link['tier'])) {
        if (!empty($link['internal'])) {
            $link['tier'] = ENVIRONMENT_TIER_PRODUCTION;
        } elseif (preg_match('#zendesk\.com#i', (string) ($link['href'] ?? ''))) {
            $link['tier'] = ENVIRONMENT_TIER_PRODUCTION;
        } elseif (preg_match('#(admin\.commerce\.adobe\.com/UAEy|nutrasync-eds-staging|nutraaxis_test|/nutraaxis_test/)#i', (string) ($link['href'] ?? ''))) {
            $link['tier'] = ENVIRONMENT_TIER_UAT;
        } elseif (str_starts_with((string) ($link['href'] ?? ''), 'http')) {
            $link['tier'] = ENVIRONMENT_TIER_EXTERNAL;
        } else {
            $link['tier'] = ENVIRONMENT_TIER_PRODUCTION;
        }
    }

    $link['external'] = empty($link['internal']) && str_starts_with((string) ($link['href'] ?? ''), 'http');

    return $link;
}

function operations_dashboard_render_section_links(array $links): void
{
    $links = array_map('operations_dashboard_normalize_link', $links);
    $sections = hub_cards_partition_uat($links);

    if ($sections['production'] !== []) {
        echo '<div class="functions operations-dashboard-links">';
        hub_render_function_card_grid($sections['production'], false);
        echo '</div>';
    }

    if ($sections['uat'] !== []) {
        echo '<h2 class="hub-uat-section-title">UAT / Test Systems</h2>';
        echo '<div class="functions operations-dashboard-links">';
        hub_render_function_card_grid($sections['uat'], false);
        echo '</div>';
    }
}

$activeSlug = 'operations-dashboard';
$canManageLinks = links_can_read();
$indexLinks = $canManageLinks ? links_list(['status' => 'active']) : [];

$dashboardSections = [
    [
        'title' => 'Operations',
        'links' => [
            [
                'title' => 'Issues and Actions',
                'desc'  => 'SharePoint tracker for open issues, action items, and team follow-ups.',
                'href'  => 'https://nationalfinancial.sharepoint.com/sites/NutraCollaboration/Lists/Issue%20%20Actions%20tracker/AllItems.aspx',
                'icon'  => 'clipboard',
            ],
            [
                'title' => 'Planner',
                'desc'  => 'Microsoft Planner premium plan for NutraAxis operational tasks and schedules.',
                'href'  => 'https://planner.cloud.microsoft/webui/premiumplan/c2a63499-8702-4dee-8431-1efef82b8879/org/0b986e71-e763-f011-8ee3-7ced8d213739?tid=60392fb7-51ea-497a-8a08-0ec0265a97c7',
                'icon'  => 'dashboard',
            ],
            [
                'title' => 'Document Library',
                'desc'  => 'SharePoint document library for NutraAxis team files and shared resources.',
                'href'  => 'https://nationalfinancial.sharepoint.com/sites/NutraCollaboration/Shared%20Documents/Forms/AllItems.aspx?FolderCTID=0x012000DB37491E1D32BA4294FB292CA499F67A&id=%2Fsites%2FNutraCollaboration%2FShared%20Documents%2FGeneral',
                'icon'  => 'document',
            ],
            [
                'title'    => 'Supplier Management',
                'desc'     => 'Maintain supplier profiles, contacts, CMO relationships, and procurement records.',
                'href'     => '/supplier-management/',
                'icon'     => 'supplier',
                'internal' => true,
            ],
            [
                'title' => 'QuickBooks',
                'desc'  => 'QuickBooks Online accountant view for NutraAxis financials.',
                'href'  => 'https://qbo.intuit.com/app/my-accountant',
                'icon'  => 'accounting',
            ],
            [
                'title' => 'Lucid Chart',
                'desc'  => 'Lucid visual collaboration — recent diagrams and documents.',
                'href'  => 'https://lucid.app/documents#/home?folder_id=recent',
                'icon'  => 'chart',
            ],
            [
                'title' => 'Survey Monkey',
                'desc'  => 'SurveyMonkey home — create surveys, view responses, and manage team feedback.',
                'href'  => 'https://www.surveymonkey.com/home/',
                'icon'  => 'clipboard',
            ],
            [
                'title'    => 'Process Log',
                'desc'     => 'Scheduled job history for sales summaries, inventory snapshots, and demand runs.',
                'href'     => '/process-log/',
                'icon'     => 'clipboard',
                'internal' => true,
            ],
            [
                'title'    => 'Enhancement Log',
                'desc'     => 'Track portal enhancement requests, status, due dates, and implementation notes.',
                'href'     => '/enhancement-log/',
                'icon'     => 'clipboard',
                'internal' => true,
            ],
            [
                'title'    => 'Site Documentation',
                'desc'     => 'Support reference for all portal pages, modules, and scheduled background processes.',
                'href'     => '/site-documentation/',
                'icon'     => 'document',
                'internal' => true,
            ],
            [
                'title' => 'ZenDesk',
                'desc'  => 'NutraAxis Labs Zendesk agent dashboard for support tickets.',
                'href'  => 'https://nutraaxislabs.zendesk.com/auth/v3/signin?return_to=https%3A%2F%2Fnutraaxislabs.zendesk.com%2Fagent%2Fdashboard&role=agent',
                'icon'  => 'support',
            ],
            [
                'title' => 'Cart.com Help',
                'desc'  => 'Jazz Commerce (Cart.com) Jira Service Management help center.',
                'href'  => 'https://jazzcommerce.atlassian.net/servicedesk/customer/user/login?destination=portals%3FatlOrigin%3DeyJwIjoiYWRtaW4iLCJpIjoiNjIxMDJmMGE1NDRjNDA1YThlZDNmZGM5YzFkMWI3ZjEifQ%253D%253D%26cloudId%3D3ba837e5-4cdf-4f76-a13c-027e0dcebb93%26isEligibleForUserSurvey%3Dtrue',
                'icon'  => 'support',
            ],
        ],
    ],
    [
        'title' => 'IT and eCommerce',
        'links' => [
            [
                'title' => 'Azure Portal',
                'desc'  => 'Microsoft Azure portal for NutraAxis cloud resources and app services.',
                'href'  => 'https://portal.azure.com/auth/login/',
                'icon'  => 'dashboard',
            ],
            [
                'title' => 'PayPal Fraud',
                'desc'  => 'PayPal fraud protection dashboard for monitoring and managing payment risk.',
                'href'  => 'https://www.paypal.com/fraud-protection',
                'icon'  => 'payment',
            ],
            [
                'title' => 'Intuit Development Registration',
                'desc'  => 'Intuit Developer workspaces for QuickBooks API apps, keys, and OAuth configuration.',
                'href'  => 'https://developer.intuit.com/workspaces',
                'icon'  => 'accounting',
            ],
            [
                'title' => 'Adobe Admin Console',
                'desc'  => 'Adobe organization admin console for NutraAxis production users, products, and licenses.',
                'href'  => 'https://adminconsole.adobe.com/E73F22FB6913B1350A495C34@AdobeOrg/overview',
                'icon'  => 'dashboard',
                'tier'  => ENVIRONMENT_TIER_PRODUCTION,
            ],
            [
                'title' => 'ACCS Admin',
                'desc'  => 'Adobe Commerce as a Cloud Service admin for the production tenant.',
                'href'  => 'https://na1.admin.commerce.adobe.com/VLuKe3eeTwf1D5oxmLBfcr',
                'icon'  => 'dashboard',
                'tier'  => ENVIRONMENT_TIER_PRODUCTION,
            ],
            [
                'title' => 'ACCS Admin',
                'desc'  => 'UAT System — Adobe Commerce as a Cloud Service admin for the stage tenant.',
                'href'  => 'https://na1-sandbox.admin.commerce.adobe.com/UAEyTrirS4qBMAWYZa4uic',
                'icon'  => 'dashboard',
                'tier'  => ENVIRONMENT_TIER_UAT,
            ],
            [
                'title' => 'ACCS Authoring',
                'desc'  => 'Document Authoring for NutraSync EDS staging content.',
                'href'  => 'https://da.live/#/capocommerce/nutrasync-eds-staging',
                'icon'  => 'document',
            ],
            [
                'title' => 'ACCS Asset Management',
                'desc'  => 'Adobe Experience Manager DAM for NutraSync production digital assets.',
                'href'  => 'https://author-p180942-e1905687.adobeaemcloud.com/ui#/aem/assets.html/content/dam',
                'icon'  => 'catalog',
                'tier'  => ENVIRONMENT_TIER_PRODUCTION,
            ],
            [
                'title' => 'ACCS Asset Management',
                'desc'  => 'UAT System — Adobe Experience Manager DAM for NutraSync staging digital assets.',
                'href'  => 'https://author-p180942-e1905796.adobeaemcloud.com/ui#/aem/assets.html/content/dam',
                'icon'  => 'catalog',
                'tier'  => ENVIRONMENT_TIER_UAT,
            ],
            [
                'title' => 'ACCS Staging',
                'desc'  => 'NutraAxis staging storefront on Adobe Edge Delivery Services.',
                'href'  => 'https://main--nutrasync-eds-staging--capocommerce.aem.live/',
                'icon'  => 'chart',
            ],
            [
                'title' => 'NA Test Site',
                'desc'  => 'NutraAxis site index — HTML previews, concept pages, and test renders.',
                'href'  => 'https://nutraaxisweb.azurewebsites.net/nutraaxis_test/',
                'icon'  => 'links',
            ],
            [
                'title'    => 'Function App ping test',
                'desc'     => 'Diagnostic tool to call the Azure Function App ping endpoint from the portal and verify connectivity.',
                'href'     => '/function-test/',
                'icon'     => 'dashboard',
                'internal' => true,
                'tier'     => ENVIRONMENT_TIER_UAT,
            ],
            [
                'title' => 'NutraSync Wordpress',
                'desc'  => 'NutraSync WordPress admin login for content and site management.',
                'href'  => 'https://nutrasync.com/wp-login.php',
                'icon'  => 'document',
            ],
        ],
    ],
];

$pageTitle = 'Operations Dashboard | NutraAxis Operations';
$pageDescription = 'Shortcuts to Microsoft 365, accounting, commerce, support, and operational tools.';

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
        <div class="page-hero-head">
          <div class="module-icon"><?= icon_svg('dashboard', 28) ?></div>
          <?php if ($canManageLinks): ?>
          <a class="btn-secondary" href="/links-index/">Manage Links</a>
          <?php endif; ?>
        </div>
        <div class="section-label">Overview</div>
        <h1>Operations Dashboard</h1>
        <p class="page-lead">Shortcuts to planning, documents, accounting, Adobe Commerce, and support tools.</p>
        <p class="permission-note">Your access: <?= htmlspecialchars(auth_module_permission_label('operations-dashboard')) ?></p>
      </div>

      <?php foreach ($dashboardSections as $section): ?>
      <section class="operations-dashboard-section">
        <h2 class="operations-dashboard-section-title"><?= htmlspecialchars($section['title']) ?></h2>
        <?php operations_dashboard_render_section_links($section['links']); ?>
      </section>
      <?php endforeach; ?>

      <?php if ($canManageLinks): ?>
      <section class="operations-dashboard-section operations-dashboard-other-links">
        <h2 class="operations-dashboard-section-title">Other Links</h2>
        <p class="page-lead">Active shortcuts from the Links Index. Use Manage Links to add, edit, or remove entries.</p>

        <?php if ($indexLinks === []): ?>
        <div class="status-banner">
          <div>
            <strong>No active links</strong>
            <p>Add links from the Links Index to show them here.</p>
          </div>
          <?php if (links_can_create()): ?>
          <a class="btn-primary" href="/links-index/new.php">New Link</a>
          <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Name (click to open link)</th>
                <th>Category</th>
                <th>Status</th>
                <th>Registration</th>
                <th>Description</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($indexLinks as $indexLink):
                  $externalHref = links_external_url((string) $indexLink['LinkURL']);
                  $description = trim((string) ($indexLink['LinkDescription'] ?? ''));
              ?>
              <tr>
                <td><a href="<?= htmlspecialchars($externalHref) ?>" <?= links_external_name_attrs() ?>><?= htmlspecialchars((string) $indexLink['LinkName']) ?></a></td>
                <td><?= htmlspecialchars((string) $indexLink['LinkCategory']) ?></td>
                <td><span class="status-badge <?= links_status_class((string) $indexLink['LinkStatus']) ?>"><?= htmlspecialchars(links_status_label((string) $indexLink['LinkStatus'])) ?></span></td>
                <td><?= !empty($indexLink['UserRegistrationRequired']) ? 'Yes' : 'No' ?></td>
                <td><?= $description !== '' ? htmlspecialchars($description) : '—' ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </section>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
