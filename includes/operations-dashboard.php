<?php

require_once __DIR__ . '/data-profile.php';

function operations_dashboard_sections(): array
{
    return [
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
                    'title'    => 'Provider Signup Management',
                    'desc'     => 'Review provider onboarding applications, validate NPI and banking, and approve ACCS provisioning.',
                    'href'     => '/provider-enrollment/',
                    'icon'     => 'clipboard',
                    'internal' => true,
                    'module'   => 'provider-enrollment',
                ],
                [
                    'title'    => 'IT Product Backlog',
                    'desc'     => 'Track IT product backlog items, status, due dates, and implementation notes.',
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
                [
                    'title'    => 'Other Links',
                    'desc'     => 'Open the full Links Index shortcut page currently maintained from Manage Links.',
                    'href'     => '/operations-dashboard/other-links.php',
                    'icon'     => 'links',
                    'internal' => true,
                    'module'   => 'links-index',
                ],
            ],
        ],
        [
            'title' => 'IT & Ecommerce Management Systems',
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
                    'title' => 'Adobe Development',
                    'desc'  => 'Adobe Experience Cloud home for NutraSync development resources.',
                    'href'  => 'https://experience.adobe.com/#/@nutrasync/home',
                    'icon'  => 'dashboard',
                    'tier'  => ENVIRONMENT_TIER_PRODUCTION,
                ],
                [
                    'title' => 'ACCS Prod Admin',
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
                    'title' => 'ACCS Stage Admin',
                    'desc'  => 'UAT System — Adobe Commerce as a Cloud Service admin for the stage tenant.',
                    'href'  => 'https://na1-sandbox.admin.commerce.adobe.com/UAEyTrirS4qBMAWYZa4uic',
                    'icon'  => 'dashboard',
                    'tier'  => ENVIRONMENT_TIER_UAT,
                ],
                [
                    'title' => 'Stage DA',
                    'desc'  => 'UAT System — Document Authoring for NutraSync EDS staging content.',
                    'href'  => 'https://da.live/#/capocommerce/nutrasync-eds-staging',
                    'icon'  => 'document',
                    'tier'  => ENVIRONMENT_TIER_UAT,
                ],
                [
                    'title' => 'Stage DAM',
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
}

function operations_dashboard_action_cards(): array
{
    return [
        [
            'title'  => 'ACCS Test Order Creation',
            'desc'   => 'Create 5 ACCS Stage test orders (4 random catalog SKUs each) using the same customer, address, and payment as order 000000094.',
            'icon'   => 'chart',
            'tier'   => ENVIRONMENT_TIER_UAT,
            'action' => 'accs_test_orders',
        ],
    ];
}

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

function operations_dashboard_link_visible(array $link): bool
{
    if (!empty($link['module']) && !auth_can_read_leaf_module((string) $link['module'])) {
        return false;
    }

    return trim((string) ($link['href'] ?? '')) !== '';
}

function operations_dashboard_nav_link_title(array $link, bool $inUatGroup = false): string
{
    $title = trim((string) ($link['title'] ?? ''));
    if ($title === '') {
        return 'Link';
    }

    if (!$inUatGroup
        && environment_tier_normalize($link['tier'] ?? ENVIRONMENT_TIER_PRODUCTION) === ENVIRONMENT_TIER_UAT
        && !str_contains(strtolower($title), 'uat')) {
        return $title . ' (UAT)';
    }

    return $title;
}

function operations_dashboard_nav_link_item(array $link, bool $inUatGroup = false): array
{
    $normalized = operations_dashboard_normalize_link($link);

    return [
        'title'    => operations_dashboard_nav_link_title($normalized, $inUatGroup),
        'href'     => (string) $normalized['href'],
        'external' => !empty($normalized['external']),
        'slug'     => 'operations-dashboard',
    ];
}

function operations_dashboard_nav_section_children(array $links): array
{
    $children = [];

    foreach ($links as $link) {
        $normalized = operations_dashboard_normalize_link($link);
        if (!operations_dashboard_link_visible($normalized)) {
            continue;
        }

        $children[] = operations_dashboard_nav_link_item($link);
    }

    return $children;
}

function operations_dashboard_nav_sections(): array
{
    $sections = [];

    foreach (operations_dashboard_sections() as $section) {
        $sectionLinks = $section['links'] ?? [];
        $children = operations_dashboard_nav_section_children($sectionLinks);

        if ($children !== []) {
            $sections[] = [
                'title'    => (string) ($section['title'] ?? ''),
                'slug'     => 'operations-dashboard',
                'children' => $children,
            ];
        }
    }

    return $sections;
}
