<?php

require_once __DIR__ . '/../portal-manual.php';
require_once __DIR__ . '/../operations-dashboard.php';

/**
 * Operations section User Manual (home Operations cards + external tools).
 */

function operations_manual_section_links(): array
{
    foreach (operations_dashboard_sections() as $section) {
        if (($section['key'] ?? '') === 'operations') {
            return array_values(array_filter(
                $section['links'] ?? [],
                static fn(array $link): bool => (($link['title'] ?? '') !== 'User Manual')
            ));
        }
    }

    return [];
}

function operations_manual_definition(): array
{
    $pageIndex = portal_manual_dashboard_links_to_index(operations_manual_section_links());

    return [
        'active_slug' => 'operations-manual',
        'category'    => 'Operations',
        'title'       => 'User Manual',
        'lead'        => 'How to use the Operations home section — internal portal tools and the external systems the team opens every day.',
        'intro'       => 'The **Operations** block on the home page mixes NutraAxis portal modules (provider signup, practitioner tiers, process log, education, contacts, documentation) with Microsoft 365, QuickBooks, Lucid, SurveyMonkey, Zendesk, and Cart.com help. Internal links stay in the portal; external links open in a new tab with your own login to that product.',
        'golden_rules' => [
            '**External tools use their own accounts.** SharePoint, Planner, QuickBooks, Zendesk, and the rest are not the portal password — use your org SSO or the account IT provisioned.',
            '**Process Log (Production) vs Process Log (UAT).** Production jobs touch live ACCS/forecasting; UAT is for stage/sandbox job runs only.',
            '**Provider Signup and Practitioner Tiers change customer setup.** Approve carefully; Stage vs Production is called out on the screens.',
            '**Site Documentation** is the page/job inventory; this User Manual is the how-to for the Operations section itself.',
            '**Other Links** is curated from Manage Links — prefer it over bookmark sprawl.',
        ],
        'access_html' => 'The Operations section appears when your role has Operations Dashboard (or related module) Read. Individual internal cards still check their own module permissions (e.g. Provider Account Review for signup).',
        'roles' => [
            [
                'role'   => 'Operations coordinator',
                'access' => 'Operations Dashboard + assigned modules',
                'does'   => 'Works Issues/Actions and Planner, files in Document Library, education resources, contacts, and follow-ups in Zendesk.',
            ],
            [
                'role'   => 'Provider onboarding',
                'access' => 'Provider Account Review',
                'does'   => 'Reviews provider signup applications, NPI/banking checks, ACCS company creation; manages practitioner tiers.',
            ],
            [
                'role'   => 'IT / systems',
                'access' => 'Operations Dashboard',
                'does'   => 'Runs Process Log jobs, maintains IT Product Backlog and Site Documentation, uses Function App ping on UAT as needed.',
            ],
        ],
        'rhythm_intro' => 'Most Operations work is daily stand-up style; onboarding and tiers are as applications arrive.',
        'rhythm' => [
            ['when' => 'Daily', 'who' => 'Ops coordinator', 'what' => 'Check **Issues and Actions** and **Planner** for due items.', 'link' => ['link-issues-and-actions', '', null]],
            ['when' => 'Daily', 'who' => 'Onboarding', 'what' => 'Clear **Provider Signup Management** queue.', 'link' => ['signup-review', '', null]],
            ['when' => 'Daily', 'who' => 'Support', 'what' => 'Work **ZenDesk** agent queue.', 'link' => ['link-zendesk', '', null]],
            ['when' => 'When jobs fail', 'who' => 'IT / ops', 'what' => 'Open **Process Log**, find the failed run, fix cause, rerun if safe.', 'link' => ['process-log', '', null]],
            ['when' => 'Monthly', 'who' => 'Onboarding', 'what' => 'Review **Practitioner Tiers** for revenue-based group updates.', 'link' => ['practitioner-tiers', '', null]],
        ],
        'workflows' => operations_manual_workflows(),
        'page_index' => $pageIndex,
        'page_guide' => operations_manual_pages(),
        'other_instructions' => [
            'Where is Administration?' => 'Accounting, legal, and Support portal modules are under the **Administration** home group (separate User Manual).',
            'Where is IT & Ecommerce?' => 'Azure, Adobe, ACCS admin, Jazz OMS, PayPal fraud, and stage links are under **IT & Ecommerce Management Systems** (separate User Manual).',
            'Asking for access' => 'Site Admin → Roles for Operations Dashboard, Provider Account Review, Education Resources, Contacts List, Links Index, etc.',
        ],
        'troubleshooting' => [
            ['External link asks for a different login', 'Expected — sign in with that product’s account or ask IT for access.'],
            ['Process Log 404 or empty', 'Confirm you used Production vs UAT card; check you have Operations Dashboard Read.'],
            ['Cannot approve a provider application', 'Need Provider Account Review Update; confirm NPI/banking validation messages on the application.'],
            ['Anything else', 'Use IT Product Backlog or contact Joe Butler.'],
        ],
    ];
}

function operations_manual_workflows(): array
{
    return [
        'signup' => [
            'title'   => 'Review a provider signup',
            'summary' => 'Applications land for ops review; validated apps can create ACCS companies.',
            'cadence' => 'As applications arrive.',
            'steps'   => [
                ['who' => 'Onboarding', 'when' => 'Daily', 'what' => 'Open **Provider Signup Management**, review pending applications.', 'link' => ['signup-review', '', null]],
                ['who' => 'Onboarding', 'when' => 'Same day', 'what' => 'Validate NPI and banking; approve or return with notes; create ACCS company when ready.', 'link' => ['signup-review', '', null]],
                ['who' => 'Onboarding', 'when' => 'After go-live', 'what' => 'Assign **Practitioner Tiers** when revenue thresholds apply.', 'link' => ['practitioner-tiers', '', null]],
            ],
        ],
        'jobs' => [
            'title'   => 'Investigate a failed scheduled job',
            'summary' => 'Process Log shows production job history; UAT log is for sandbox runs.',
            'cadence' => 'When alerts or imports fail.',
            'steps'   => [
                ['who' => 'IT / ops', 'when' => 'On failure', 'what' => 'Open **Process Log** (or UAT variant), find the run, read the error.', 'link' => ['process-log', '', null]],
                ['who' => 'IT / ops', 'when' => 'After fix', 'what' => 'Rerun from the module or Process Log when the UI offers it; confirm success.', 'link' => ['process-log', '', null]],
            ],
        ],
        'education' => [
            'title'   => 'Publish an education resource',
            'summary' => 'PDFs and Vimeo links for training appear on the public Education page when published.',
            'cadence' => 'As content is ready.',
            'steps'   => [
                ['who' => 'Ops / training', 'when' => 'When ready', 'what' => 'Add or replace files on **Education Resources**; set publish flags.', 'link' => ['education-resources', '', null]],
            ],
        ],
    ];
}

function operations_manual_pages(): array
{
    // Keys must match portal_manual_dashboard_links_to_index (module slug or link-title slug).
    return [
        'link-issues-and-actions' => [
            'who'     => 'Ops team',
            'purpose' => 'SharePoint list for open issues, action items, and follow-ups.',
            'analyze' => ['Keep owners and due dates current; close items when done so stand-up stays trustworthy.'],
            'actions' => ['Open in SharePoint', 'Edit list items there'],
        ],
        'link-planner' => [
            'who'     => 'Ops team',
            'purpose' => 'Microsoft Planner premium plan for operational tasks and schedules.',
            'actions' => ['Open Planner', 'Update task progress'],
        ],
        'link-document-library' => [
            'who'     => 'Everyone',
            'purpose' => 'SharePoint document library for team files.',
            'actions' => ['Upload / download in SharePoint'],
        ],
        'supplier-management' => [
            'who'     => 'Procurement / ops',
            'purpose' => 'Portal supplier profiles (also under Supply Chain Procurement).',
            'actions' => ['Maintain suppliers'],
        ],
        'contacts-list' => [
            'who'     => 'Ops / admin',
            'purpose' => 'Business contacts and supplier directory details.',
            'actions' => ['Add / edit contacts'],
        ],
        'education-resources' => [
            'who'     => 'Training / ops',
            'purpose' => 'Education PDFs and Vimeo links for the public Education page.',
            'actions' => ['Upload PDF', 'Add Vimeo link', 'Publish'],
        ],
        'link-quickbooks' => [
            'who'     => 'Finance',
            'purpose' => 'QuickBooks Online accountant view for NutraAxis financials.',
            'actions' => ['Open QBO in browser'],
        ],
        'link-lucid-chart' => [
            'who'     => 'Ops / IT',
            'purpose' => 'Lucid diagrams and process maps.',
            'actions' => ['Open Lucid'],
        ],
        'link-survey-monkey' => [
            'who'     => 'Ops / marketing',
            'purpose' => 'SurveyMonkey for team and customer feedback.',
            'actions' => ['Open SurveyMonkey'],
        ],
        'process-log' => [
            'who'     => 'IT / ops',
            'purpose' => 'Production scheduled job history and manual runs.',
            'analyze' => ['Failed runs show the error payload — fix root cause before rerunning.'],
            'actions' => ['Filter jobs', 'Open run detail', 'Rerun when offered'],
        ],
        'signup-review' => [
            'who'     => 'Provider onboarding',
            'purpose' => 'Review provider applications, validate NPI/banking, approve, create ACCS companies.',
            'actions' => ['Review', 'Approve / return', 'Provision'],
        ],
        'practitioner-tiers' => [
            'who'     => 'Provider onboarding',
            'purpose' => 'Clinic YTD ACCS revenue and Practitioner / Bronze–Platinum group assignment.',
            'actions' => ['Review revenue', 'Assign groups'],
        ],
        'enhancement-log' => [
            'who'     => 'IT',
            'purpose' => 'IT product backlog — status, due dates, implementation notes.',
            'actions' => ['Add item', 'Update status'],
        ],
        'site-documentation' => [
            'who'     => 'Everyone',
            'purpose' => 'Support reference for portal pages and scheduled processes.',
            'actions' => ['Browse / search'],
        ],
        'link-zendesk' => [
            'who'     => 'Support',
            'purpose' => 'Zendesk agent dashboard for NutraAxis Labs tickets.',
            'actions' => ['Open Zendesk'],
        ],
        'link-cart-com-help' => [
            'who'     => 'Ops / IT',
            'purpose' => 'Jazz Commerce (Cart.com) Jira Service Management help center.',
            'actions' => ['Open help center'],
        ],
        'links-index' => [
            'who'     => 'Everyone',
            'purpose' => 'Full Links Index maintained from Manage Links.',
            'actions' => ['Open shortcuts'],
        ],
    ];
}
