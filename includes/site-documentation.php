<?php

require_once __DIR__ . '/auth.php';

function site_documentation_require_read(): void
{
    auth_require_module_read('site-documentation');
}

function site_documentation_module_sections(): array
{
    global $appFunctions, $inventorySubModules;

    $sections = [];

    foreach ($appFunctions as $module) {
        $entry = [
            'title'       => $module['title'],
            'description' => $module['desc'],
            'href'        => $module['href'],
            'children'    => [],
        ];

        if ($module['slug'] === 'inventory-management') {
            foreach ($inventorySubModules as $child) {
                $childModule = get_module($child['slug']);
                $entry['children'][] = [
                    'title'       => $child['title'],
                    'description' => $child['desc'],
                    'href'        => $child['href'],
                    'note'        => $childModule['headline'] ?? null,
                ];
            }
        }

        $sections[] = $entry;
    }

    $sections[] = [
        'title'       => 'Site Administration',
        'description' => 'User, role, and audit tools for site administrators.',
        'href'        => '/site-admin/',
        'children'    => [
            ['title' => 'Users', 'description' => 'Create and manage NutraAxis Operations user accounts.', 'href' => '/site-admin/users/'],
            ['title' => 'Roles', 'description' => 'Define module permissions and approval rights by role.', 'href' => '/site-admin/roles/'],
            ['title' => 'Audit Log', 'description' => 'Review data changes and roll back when needed.', 'href' => '/site-admin/audit-log/'],
        ],
    ];

    $sections[] = [
        'title'       => 'Operations Utilities',
        'description' => 'Internal support pages for monitoring and reference.',
        'href'        => null,
        'children'    => [
            ['title' => 'Process Log', 'description' => 'Execution history for scheduled background jobs, with manual rerun for failed runs.', 'href' => '/process-log/'],
            ['title' => 'Enhancement Log', 'description' => 'Portal enhancement requests with status, due dates, and notes.', 'href' => '/enhancement-log/'],
            ['title' => 'Links Index', 'description' => 'Curated shortcuts surfaced on the Operations Dashboard.', 'href' => '/links-index/'],
            ['title' => 'My Account', 'description' => 'View your profile and change your password.', 'href' => '/my-account/'],
            ['title' => 'Site Documentation', 'description' => 'This page — module reference and scheduled process guide.', 'href' => '/site-documentation/'],
        ],
    ];

    return $sections;
}

function site_documentation_scheduled_processes(): array
{
    require_once __DIR__ . '/process-runner.php';

    $registry = process_registry();

    return [
        array_merge($registry['daily-sales-summary'], [
            'writes_to' => 'DailySalesSummary',
            'notes'     => 'Summarizes the previous calendar day of ACCS sales by SKU.',
        ]),
        array_merge($registry['monthly-sales-summary'], [
            'writes_to' => 'MonthlySalesSummary',
            'notes'     => 'First step of weekly-chain: rolls up DailySalesSummary into monthly SKU totals.',
        ]),
        array_merge($registry['forecast-plan'], [
            'writes_to' => 'ForecastPlan',
            'notes'     => 'Second step of weekly-chain: weighted moving average demand projection. View on Inventory Forecasting (/inventory-demand/).',
        ]),
        array_merge($registry['jazz-inventory-snapshot'], [
            'writes_to' => 'JazzInventorySnapshot',
            'notes'     => 'Pulls live Jazz OMS inventory by SKU and facility.',
        ]),
        array_merge($registry['staging-db-sync'], [
            'writes_to' => 'Staging database tables',
            'notes'     => 'Incremental sync from production SQL to staging SQL.',
        ]),
        [
            'code'          => 'process-retry',
            'name'          => 'Process Retry Worker',
            'description'   => 'Event-driven retries for failed background jobs.',
            'function_name' => 'process-retry',
            'schedule'      => 'On demand (Azure Service Bus)',
            'writes_to'     => 'ProcessExecutionLog',
            'notes'         => 'Scheduled when a job fails; runs at NextRetryAt with 2, 4, and 8 minute backoff.',
        ],
    ];
}

function site_documentation_process_monitoring(): array
{
    return [
        [
            'title' => 'Process Log',
            'body'  => 'Every scheduled and manual job run is recorded in ProcessExecutionLog with start/finish time, attempt count, next retry time, status, and result message. Open Process Log from the Operations Dashboard to review history or rerun failed or abandoned jobs.',
        ],
        [
            'title' => 'Automatic retries',
            'body'  => 'Failed jobs schedule a Service Bus retry message with exponential backoff: 2, 4, and 8 minutes after attempts 1, 2, and 3. The process-retry function runs the job when the message is due. After max attempts (default 3), the job is marked Abandoned.',
        ],
        [
            'title' => 'Abandoned job alerts',
            'body'  => 'When SMTP relay is configured on the Function App (SMTP_HOST, SMTP_USER, SMTP_PASS), abandoned jobs send an email to alerts@nutraaxislabs.zendesk.com so support can open a Zendesk ticket. Failed jobs awaiting retry do not alert.',
        ],
        [
            'title' => 'SMTP email',
            'body'  => 'Office 365 SMTP uses notifications@nutraaxislabs.com on smtp.office365.com:587 with TLS. Background job alerts are sent from the Azure Function App. The PHP portal still sends PO and scheduling mail. Test with /cron/test-mail.php or php scripts/test-smtp.php.',
        ],
        [
            'title' => 'External email delivery',
            'body'  => 'If only @nfcllc.com addresses receive mail, Microsoft 365 is treating that domain as internal in the tenant. Check Exchange mail flow rules, Outbound spam policies, and mailbox external-send permissions for notifications@nutraaxislabs.com. Until fixed, use @nfcllc.com addresses for PO approvers.',
        ],
        [
            'title' => 'Alert subscriptions',
            'body'  => 'Outbound email recipients are configured per user in AlertSubscription (To or Cc per alert). Site Admin → Users → Edit User lists only active subscriptions with add/remove and address-type controls. Alert names: process-abandoned, po-approval-request, po-status-update, po-viewed-by-approver.',
        ],
        [
            'title' => 'Reply-To address',
            'body'  => 'All app mail uses MAIL_REPLY_TO (default nutrateam@nfcllc.com) for the Reply-To header.',
        ],
        [
            'title' => 'Background job platform',
            'body'  => 'Scheduled jobs run on Azure Function App Nutra-forecast-tool (timer triggers and Service Bus). The PHP App Service hosts the web UI only. Scheduled job cron endpoints have been removed; WebJobs are retired under App_Data/Disabled_jobs/.',
        ],
        [
            'title' => 'Manual execution',
            'body'  => 'Operators with update access can rerun failed or abandoned jobs from Process Log. Reruns call Azure Function App process-execute (requires NUTRA_FUNCTIONS_BASE_URL and NUTRA_FUNCTIONS_KEY on this App Service). Developers can also use npm scripts under functions/ or POST to /api/process-execute on the Function App.',
        ],
    ];
}
