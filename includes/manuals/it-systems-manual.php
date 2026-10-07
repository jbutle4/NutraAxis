<?php

require_once __DIR__ . '/../portal-manual.php';
require_once __DIR__ . '/../operations-dashboard.php';

/**
 * IT & Ecommerce Management Systems User Manual.
 */

function it_systems_manual_section_links(): array
{
    foreach (operations_dashboard_sections() as $section) {
        if (($section['key'] ?? '') === 'it-systems') {
            return array_values(array_filter(
                $section['links'] ?? [],
                static fn(array $link): bool => (($link['title'] ?? '') !== 'User Manual')
            ));
        }
    }

    return [];
}

function it_systems_manual_definition(): array
{
    return [
        'active_slug' => 'it-systems-manual',
        'category'    => 'IT & Ecommerce',
        'title'       => 'User Manual',
        'lead'        => 'Production and UAT system links — Azure, Adobe Commerce, Jazz OMS, QuickBooks developer tools, PayPal fraud, and related admin consoles.',
        'intro'       => 'The **IT & Ecommerce Management Systems** block on the home page is a curated launchpad. Almost every card opens an external console (Azure, Adobe, Jazz, Intuit, PayPal, WordPress). Use **Production** links for live customer impact and **UAT / Stage** links only for testing. The portal does not store passwords for these systems — access is granted in each vendor’s admin.',
        'golden_rules' => [
            '**Never use a Production admin URL to “just test.”** Stage / UAT cards are labeled — ACCS Stage, Stage DA/DAM, Jazz UAT, QBO Sandbox, NA Test Site.',
            '**Least privilege.** Admin consoles can change live catalog, content, and orders. Prefer read-only roles when you only need to look.',
            '**ACCS Prod vs Stage.** Production tenant and stage tenant are different Adobe Commerce environments. Orders, customers, and catalogs do not share data.',
            '**Jazz OMS Prod vs UAT** use different hosts and API users (NutraSync_API_PROD vs NutraSync_API_UAT).',
            '**Function App ping** and other diagnostic tools are UAT-oriented — don’t treat a green ping as proof Production jobs are healthy (use Process Log under Operations).',
        ],
        'access_html' => 'This section is visible with Operations Dashboard Read. Each external system still requires its own user account from IT.',
        'roles' => [
            [
                'role'   => 'IT administrator',
                'access' => 'Azure, Adobe Admin Console, Intuit developer, Function App',
                'does'   => 'Manages cloud resources, app registrations, API keys, and connectivity tests.',
            ],
            [
                'role'   => 'Ecommerce / ACCS admin',
                'access' => 'ACCS Prod/Stage Admin, DA, DAM, storefront previews',
                'does'   => 'Catalog, content authoring, and storefront checks in the correct environment.',
            ],
            [
                'role'   => 'OMS operator',
                'access' => 'Jazz OMS Prod / UAT',
                'does'   => 'Order and inventory operations inside Jazz Commerce.',
            ],
            [
                'role'   => 'Payments risk',
                'access' => 'PayPal Fraud',
                'does'   => 'Monitors payment risk and fraud protection cases.',
            ],
        ],
        'rhythm_intro' => 'Most visits are on-demand. After deploys or incidents, verify Prod health; use Stage/UAT for rehearsals.',
        'rhythm' => [
            ['when' => 'After a portal/functions deploy', 'who' => 'IT', 'what' => 'Confirm **Azure Portal** app service / function health; spot-check **Process Log** under Operations.', 'link' => ['link-azure-portal', '', null]],
            ['when' => 'Before a catalog or content change', 'who' => 'Ecommerce', 'what' => 'Rehearse on **ACCS Stage Admin** / **Stage DA** / **Stage DAM**, then apply to Prod.', 'link' => ['link-accs-stage-admin', '', null]],
            ['when' => 'When payment risk spikes', 'who' => 'Payments', 'what' => 'Open **PayPal Fraud** and work the queue.', 'link' => ['link-paypal-fraud', '', null]],
            ['when' => 'When OMS issues arise', 'who' => 'OMS', 'what' => 'Use the matching **Jazz OMS** (Prod or UAT) host — never mix credentials.', 'link' => ['link-jazz-oms', '', null]],
        ],
        'workflows' => it_systems_manual_workflows(),
        'page_index' => portal_manual_dashboard_links_to_index(it_systems_manual_section_links()),
        'page_guide' => it_systems_manual_pages(),
        'other_instructions' => [
            'Provider Signup (UAT)' => 'The Stage signup URL tags applications for ACCS Stage provisioning — do not use it for real providers.',
            'NA Test Site' => 'Concept pages and HTML previews on the NutraAxis test path — not the live storefront.',
            'NutraSync WordPress' => 'Legacy/content WordPress admin; prefer Adobe DA/DAM for EDS storefront content unless you know the page still lives in WP.',
            'Asking for access' => 'Request vendor console access through IT (Azure AD groups, Adobe Admin Console product seats, Jazz users, Intuit developer, PayPal).',
        ],
        'troubleshooting' => [
            ['Wrong environment after login', 'Check the URL host (sandbox vs prod, stage vs live). Sign out and use the card from this section again.'],
            ['Access denied on Azure or Adobe', 'You need an entitlement in that tenant — ask IT; the portal cannot grant it.'],
            ['Jazz login fails', 'Confirm Prod vs UAT host and the correct API/service user; reset via Cart.com admin if needed.'],
            ['Anything else', 'Open IT Product Backlog under Operations or contact Joe Butler.'],
        ],
    ];
}

function it_systems_manual_workflows(): array
{
    return [
        'content' => [
            'title'   => 'Ship a storefront content change safely',
            'summary' => 'Author and preview on Stage, then promote to Production DA/DAM and ACCS Prod.',
            'cadence' => 'Per content release.',
            'steps'   => [
                ['who' => 'Content / ecommerce', 'when' => 'Start', 'what' => 'Edit on **Stage DA** / **Stage DAM**; preview **ACCS Staging** storefront.', 'link' => ['link-stage-da', '', null]],
                ['who' => 'Content / ecommerce', 'when' => 'After QA', 'what' => 'Apply the same change on **Prod DA** / **Prod DAM**; verify via **ACCS Prod Admin** / live site.', 'link' => ['link-prod-da', '', null]],
            ],
        ],
        'oms' => [
            'title'   => 'Work an OMS issue in the right Jazz',
            'summary' => 'Production order problems use Jazz Prod; test scripts use Jazz UAT.',
            'cadence' => 'On incident or test.',
            'steps'   => [
                ['who' => 'OMS', 'when' => 'Production issue', 'what' => 'Open the Production **Jazz OMS** card; use NutraSync_API_PROD credentials as documented.', 'link' => ['link-jazz-oms', '', null]],
                ['who' => 'OMS / IT', 'when' => 'Test only', 'what' => 'Open the UAT **Jazz OMS** card (fbflurry-uat01); never point Production integrations at UAT.', 'link' => ['link-jazz-oms-2', '', null]],
            ],
        ],
        'qbo-dev' => [
            'title'   => 'Manage QuickBooks API apps',
            'summary' => 'Intuit Developer workspaces hold OAuth apps and keys for portal QBO integrations.',
            'cadence' => 'When rotating keys or adding sandbox apps.',
            'steps'   => [
                ['who' => 'IT', 'when' => 'As needed', 'what' => 'Open **Intuit Development Registration** / **Intuit Developer** dashboard; update only the intended app (Prod vs Sandbox).', 'link' => ['link-intuit-developer', '', null]],
                ['who' => 'IT', 'when' => 'After key change', 'what' => 'Update Function App settings / portal secrets; test against **QuickBooks Sandbox** before Production.', 'link' => ['link-quickbooks-sandbox', '', null]],
            ],
        ],
    ];
}

function it_systems_manual_pages(): array
{
    return [
        'link-azure-portal' => [
            'who'     => 'IT',
            'purpose' => 'Microsoft Azure portal for NutraAxis cloud resources and app services.',
            'actions' => ['Open Azure', 'Check App Service / Function App'],
        ],
        'link-paypal-fraud' => [
            'who'     => 'Payments risk',
            'purpose' => 'PayPal fraud protection dashboard.',
            'actions' => ['Review cases'],
        ],
        'link-intuit-development-registration' => [
            'who'     => 'IT',
            'purpose' => 'Intuit Developer workspaces for QBO API apps and OAuth.',
            'actions' => ['Manage apps / keys'],
        ],
        'link-intuit-developer' => [
            'who'     => 'IT',
            'purpose' => 'Intuit Developer dashboard for the NutraAxis QBO apps.',
            'actions' => ['Manage credentials'],
        ],
        'link-adobe-admin-console' => [
            'who'     => 'IT / ecommerce admin',
            'purpose' => 'Adobe organization admin — users, products, licenses.',
            'actions' => ['Assign seats', 'Manage users'],
        ],
        'link-adobe-development' => [
            'who'     => 'IT / ecommerce',
            'purpose' => 'Adobe Experience Cloud home for NutraSync development.',
            'actions' => ['Open Experience Cloud'],
        ],
        'link-accs-prod-admin' => [
            'who'     => 'Ecommerce admin',
            'purpose' => 'Adobe Commerce Cloud admin for the production tenant.',
            'analyze' => ['Changes here affect live customers — prefer Stage first.'],
            'actions' => ['Admin catalog / config'],
        ],
        'accs-order-account-support' => [
            'who'     => 'IT / ecommerce support',
            'purpose' => 'Hub for guided ACCS order and clinic account support processes.',
            'screens' => ['Process cards for each published support workflow.'],
            'analyze' => ['Use Production ACCS / Jazz tools for live cases; Stage only for rehearsals.'],
            'actions' => ['Open a support process when listed'],
        ],
        'link-prod-da' => [
            'who'     => 'Content',
            'purpose' => 'Document Authoring for NutraSync EDS production content.',
            'actions' => ['Edit / publish content'],
        ],
        'link-prod-dam' => [
            'who'     => 'Content',
            'purpose' => 'AEM DAM for production digital assets.',
            'actions' => ['Upload / manage assets'],
        ],
        'link-jazz-oms' => [
            'who'     => 'OMS',
            'purpose' => 'Cart.com Jazz Commerce order management (Production).',
            'actions' => ['Login as documented Prod user'],
        ],
        'link-accs-stage-admin' => [
            'who'     => 'Ecommerce admin',
            'purpose' => 'ACCS admin for the stage tenant (UAT).',
            'actions' => ['Test admin changes'],
        ],
        'link-stage-da' => [
            'who'     => 'Content',
            'purpose' => 'Document Authoring for staging EDS content.',
            'actions' => ['Edit staging content'],
        ],
        'link-stage-dam' => [
            'who'     => 'Content',
            'purpose' => 'AEM DAM for staging assets.',
            'actions' => ['Upload staging assets'],
        ],
        'link-accs-staging' => [
            'who'     => 'QA / ecommerce',
            'purpose' => 'Staging storefront on Edge Delivery Services.',
            'actions' => ['Preview site'],
        ],
        'link-provider-signup-uat' => [
            'who'     => 'Onboarding / IT',
            'purpose' => 'Provider application tagged for ACCS Stage provisioning.',
            'actions' => ['Start stage application'],
        ],
        'link-jazz-oms-2' => [
            'who'     => 'OMS / IT',
            'purpose' => 'Jazz OMS UAT host.',
            'actions' => ['Login as UAT user'],
        ],
        'link-quickbooks-sandbox' => [
            'who'     => 'IT / accounting',
            'purpose' => 'QuickBooks Online sandbox company.',
            'actions' => ['Test accounting integrations'],
        ],
        'link-na-test-site' => [
            'who'     => 'IT / QA',
            'purpose' => 'NutraAxis test site index and concept pages.',
            'actions' => ['Open previews'],
        ],
        'function-test' => [
            'who'     => 'IT',
            'purpose' => 'Call the Azure Function App ping endpoint from the portal.',
            'actions' => ['Run ping'],
        ],
        'link-nutrasync-wordpress' => [
            'who'     => 'Content (legacy)',
            'purpose' => 'NutraSync WordPress admin login.',
            'actions' => ['WP admin'],
        ],
    ];
}
