<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/links.php';
require dirname(__DIR__) . '/includes/hub-cards.php';
require dirname(__DIR__) . '/includes/accs-test-order-client.php';

auth_require_module_read('operations-dashboard');

$accsTestOrderNotice = null;
$accsTestOrderError = null;

function operations_dashboard_normalize_link(array $link): array
{
    if (!isset($link['tier'])) {
        if (!empty($link['internal'])) {
            $link['tier'] = ENVIRONMENT_TIER_PRODUCTION;
        } elseif (preg_match('#zendesk\.com#i', (string) ($link['href'] ?? ''))) {
            $link['tier'] = ENVIRONMENT_TIER_PRODUCTION;
        } elseif (preg_match('#(sandbox\.admin\.commerce\.adobe\.com|admin\.commerce\.adobe\.com/UAEy|sandbox\.qbo\.intuit\.com|nutrasync-eds-staging|e1905796|nutraaxis_test|/nutraaxis_test/|/function-test/)#i', (string) ($link['href'] ?? ''))) {
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

function operations_dashboard_render_section_links(array $links, array $actionCards = []): void
{
    $filtered = [];
    foreach ($links as $link) {
        if (!empty($link['module']) && !auth_can_read_leaf_module((string) $link['module'])) {
            continue;
        }
        $filtered[] = operations_dashboard_normalize_link($link);
    }

    $sections = hub_cards_partition_uat($filtered);
    $actionSections = hub_cards_partition_uat($actionCards);

    if ($sections['production'] !== []) {
        echo '<div class="functions operations-dashboard-links">';
        hub_render_function_card_grid($sections['production'], false);
        echo '</div>';
    }

    if ($sections['uat'] !== [] || $actionSections['uat'] !== []) {
        echo '<h2 class="hub-uat-section-title">UAT / Test Systems</h2>';
        echo '<div class="functions operations-dashboard-links">';
        hub_render_function_card_grid($sections['uat'], false);
        operations_dashboard_render_action_cards($actionSections['uat']);
        echo '</div>';
    }

    if ($actionSections['production'] !== []) {
        echo '<div class="functions operations-dashboard-links">';
        operations_dashboard_render_action_cards($actionSections['production']);
        echo '</div>';
    }
}

function operations_dashboard_render_action_cards(array $cards): void
{
    foreach ($cards as $card) {
        if (($card['action'] ?? '') !== 'accs_test_orders') {
            continue;
        }

        $tierClass = hub_card_tier_class($card);
        echo '<div class="function-card operations-dashboard-action-card ' . htmlspecialchars($tierClass) . '">';

        if (!empty($card['icon']) && function_exists('icon_svg')) {
            echo '<div class="function-icon">' . icon_svg((string) $card['icon']) . '</div>';
        }

        echo '<h3>' . htmlspecialchars((string) ($card['title'] ?? '')) . '</h3>';
        echo '<p>' . htmlspecialchars((string) ($card['desc'] ?? '')) . '</p>';
        echo '<p class="operations-dashboard-action-note">Runs in the background — you can keep using the portal while orders are created.</p>';
        echo '<form id="accs-test-orders-form" method="post" action="/operations-dashboard/accs-test-orders-run.php" class="operations-dashboard-action-form">';
        echo '<input type="hidden" name="dashboard_action" value="accs_test_orders" />';
        echo '<button type="submit" class="btn-primary" id="accs-test-orders-submit">Create 5 test orders</button>';
        echo '</form>';
        echo '</div>';
    }
}

$activeSlug = 'operations-dashboard';
$canManageLinks = links_can_read();
$linkListFilters = ['status' => 'active'] + table_sort_state(LINKS_LIST_SORT_COLUMNS, 'category', 'asc', $_GET);
$indexLinks = $canManageLinks ? links_list($linkListFilters) : [];

$dashboardActionCards = [
    [
        'title'  => 'ACCS Test Order Creation',
        'desc'   => 'Create 5 ACCS Stage test orders (4 random catalog SKUs each) using the same customer, address, and payment as order 000000094.',
        'icon'   => 'chart',
        'tier'   => ENVIRONMENT_TIER_UAT,
        'action' => 'accs_test_orders',
    ],
];

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
                'title'    => 'Contacts List',
                'desc'     => 'Maintain business contacts and review supplier directory contact details.',
                'href'     => '/contacts-list/',
                'icon'     => 'supplier',
                'internal' => true,
                'module'   => 'contacts-list',
            ],
            [
                'title' => 'QuickBooks',
                'desc'  => 'QuickBooks Online accountant view for NutraAxis financials.',
                'href'  => 'https://qbo.intuit.com/app/my-accountant',
                'icon'  => 'accounting',
                'tier'  => ENVIRONMENT_TIER_PRODUCTION,
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
                'title'    => 'IT Product Backlog',
                'desc'     => 'Track IT product backlog items, status, due dates, and implementation notes.',
                'href'     => '/enhancement-log/',
                'icon'     => 'clipboard',
                'internal' => true,
            ],
            [
                'title'    => 'Travel & Expense',
                'desc'     => 'Submit expense reports with receipt PDFs and route reimbursements through T&E approval.',
                'href'     => '/travel-expense/',
                'icon'     => 'payment',
                'internal' => true,
                'module'   => 'travel-expense',
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
                'title' => 'Intuit Developer',
                'desc'  => 'Intuit Developer dashboard — manage QuickBooks API apps, credentials, and OAuth settings.',
                'href'  => 'https://developer.intuit.com/dashboard?id=9341457225981893&tab=apps',
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
                'title' => 'Prod DA',
                'desc'  => 'Document Authoring for NutraSync EDS production content.',
                'href'  => 'https://da.live/#/capocommerce/nutrasync-eds',
                'icon'  => 'document',
                'tier'  => ENVIRONMENT_TIER_PRODUCTION,
            ],
            [
                'title' => 'Prod DAM',
                'desc'  => 'Adobe Experience Manager DAM for NutraSync production digital assets.',
                'href'  => 'https://author-p180942-e1905687.adobeaemcloud.com/ui#/aem/assets.html/content/dam',
                'icon'  => 'catalog',
                'tier'  => ENVIRONMENT_TIER_PRODUCTION,
            ],
            [
                'title' => 'Jazz OMS',
                'desc'  => 'Cart.com Jazz Commerce order management — login as NutraSync_API_PROD.',
                'href'  => 'https://fbflurry.jazz-oms.com/account/login?next=/',
                'icon'  => 'inventory',
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
                'desc'  => 'UAT System — Document Authoring for NutraSync EDS staging content.',
                'href'  => 'https://da.live/#/capocommerce/nutrasync-eds-staging',
                'icon'  => 'document',
                'tier'  => ENVIRONMENT_TIER_UAT,
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
                'desc'  => 'UAT System — NutraAxis staging storefront on Adobe Edge Delivery Services.',
                'href'  => 'https://main--nutrasync-eds-staging--capocommerce.aem.live/',
                'icon'  => 'chart',
                'tier'  => ENVIRONMENT_TIER_UAT,
            ],
            [
                'title' => 'Jazz OMS',
                'desc'  => 'UAT System — Cart.com Jazz Commerce order management — login as NutraSync_API_UAT.',
                'href'  => 'https://fbflurry-uat01.jazz-oms.com/account/login?next=/',
                'icon'  => 'inventory',
                'tier'  => ENVIRONMENT_TIER_UAT,
            ],
            [
                'title' => 'QuickBooks Sandbox',
                'desc'  => 'UAT System — QuickBooks Online sandbox company for testing accounting integrations.',
                'href'  => 'https://sandbox.qbo.intuit.com/app/homepage',
                'icon'  => 'accounting',
                'tier'  => ENVIRONMENT_TIER_UAT,
            ],
            [
                'title' => 'NA Test Site',
                'desc'  => 'UAT System — NutraAxis site index, HTML previews, concept pages, and test renders.',
                'href'  => 'https://nutraaxisweb.azurewebsites.net/nutraaxis_test/',
                'icon'  => 'links',
                'tier'  => ENVIRONMENT_TIER_UAT,
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
      <?php render_list_page_header([
          'back_href'  => '/',
          'back_label' => 'Back to Operations Home',
          'category'   => 'Overview',
          'title'      => 'Operations Dashboard',
          'lead'       => 'Shortcuts to planning, documents, accounting, Adobe Commerce, and support tools.',
          'permission' => auth_module_permission_label('operations-dashboard'),
      ]); ?>

      <?php if ($accsTestOrderNotice !== null): ?>
      <div class="admin-notice is-success" role="status"><?= htmlspecialchars($accsTestOrderNotice) ?></div>
      <?php elseif ($accsTestOrderError !== null): ?>
      <div class="admin-notice is-error" role="alert"><?= htmlspecialchars($accsTestOrderError) ?></div>
      <?php endif; ?>
      <div id="accs-test-orders-banner"></div>

      <?php
      $listToolbar = $canManageLinks ? '<a class="btn-secondary" href="/links-index/">Manage Links</a>' : null;
      render_list_page_toolbar($listToolbar);
      ?>

      <?php foreach ($dashboardSections as $section): ?>
      <section class="operations-dashboard-section">
        <h2 class="operations-dashboard-section-title"><?= htmlspecialchars($section['title']) ?></h2>
        <?php
        $actionCards = ($section['title'] ?? '') === 'IT and eCommerce' ? $dashboardActionCards : [];
        operations_dashboard_render_section_links($section['links'], $actionCards);
        ?>
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
              <?php table_sort_render_head_row(
                  LINKS_LIST_SORT_COLUMNS,
                  '/operations-dashboard',
                  $linkListFilters,
                  [],
                  [],
                  'category',
                  'asc'
              ); ?>
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
  <script>
  (function () {
    const form = document.getElementById('accs-test-orders-form');
    const banner = document.getElementById('accs-test-orders-banner');
    if (!form || !banner) return;

    const button = document.getElementById('accs-test-orders-submit');
    const defaultLabel = button ? button.textContent : 'Create 5 test orders';

    function escapeHtml(value) {
      return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    }

    form.addEventListener('submit', async function (event) {
      event.preventDefault();

      if (button) {
        button.disabled = true;
        button.textContent = 'Starting…';
      }

      try {
        const response = await fetch(form.action, {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          },
          body: new URLSearchParams(new FormData(form)),
        });

        let data = {};
        try {
          data = await response.json();
        } catch (error) {
          data = { ok: false, message: 'Could not read the server response.' };
        }

        const message = data.message || data.error || 'Test order creation could not be started.';
        const cssClass = data.ok ? 'admin-notice is-success' : 'admin-notice is-error';
        const role = data.ok ? 'status' : 'alert';
        banner.innerHTML = '<div class="' + cssClass + '" role="' + role + '">' + escapeHtml(message) + '</div>';
        banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      } catch (error) {
        banner.innerHTML = '<div class="admin-notice is-error" role="alert">Could not reach the server to start test order creation.</div>';
      } finally {
        if (button) {
          button.disabled = false;
          button.textContent = defaultLabel;
        }
      }
    });
  })();
  </script>
<?php
require dirname(__DIR__) . '/includes/footer.php';
