<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/process-runner.php';

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
    $registry = process_registry();

    return [
        array_merge($registry['daily-sales-summary'], [
            'schedule'    => 'Daily at 2:00 AM US Central',
            'webjob'      => 'daily-sales-summary',
            'writes_to'   => 'DailySalesSummary',
            'notes'       => 'Summarizes the previous calendar day of ACCS sales by SKU. Optional backfill: ?date=YYYY-MM-DD',
        ]),
        array_merge($registry['jazz-inventory-snapshot'], [
            'schedule'    => 'Every Sunday at 12:00 PM US Central',
            'webjob'      => 'jazz-inventory-snapshot',
            'writes_to'   => 'JazzInventorySnapshot',
            'notes'       => 'Pulls live Jazz OMS inventory by SKU and facility.',
        ]),
        array_merge($registry['monthly-sales-summary'], [
            'schedule'    => 'Every Sunday at 1:00 AM US Central (via weekly-chain)',
            'webjob'      => 'monthly-sales-summary',
            'cron_path'   => '/cron/weekly-chain.php',
            'writes_to'   => 'MonthlySalesSummary',
            'notes'       => 'Rolls up DailySalesSummary into monthly SKU totals. weekly-chain also runs the demand projection step.',
        ]),
        array_merge($registry['forecast-plan'], [
            'schedule'    => 'Every Sunday at 1:30 AM US Central',
            'webjob'      => 'forecast-plan',
            'cron_path'   => '/cron/weekly-demand.php',
            'writes_to'   => 'ForecastPlan',
            'notes'       => 'Weighted moving average demand projection by SKU. View results on Inventory Forecasting (/inventory-demand/).',
        ]),
        [
            'code'        => 'process-watcher',
            'name'        => 'Process Retry Watcher',
            'description' => 'Retries failed background jobs when NextRetryAt is due.',
            'cron_path'   => '/cron/process-watcher.php',
            'schedule'    => 'Every 5 minutes',
            'webjob'      => 'process-watcher',
            'writes_to'   => 'ProcessExecutionLog',
            'notes'       => 'Finds Failed rows with NextRetryAt <= now, reruns the process, and marks Success, Failed, or Abandoned.',
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
            'body'  => 'Failed jobs are retried automatically by the process watcher (every 5 minutes) with exponential backoff: 2, 4, and 8 minutes after attempts 1, 2, and 3. After max attempts (default 3), the job is marked Abandoned.',
        ],
        [
            'title' => 'Abandoned job alerts',
            'body'  => 'When SMTP relay is configured (SMTP_HOST, SMTP_USER, SMTP_PASS), abandoned jobs send an email to alerts@nutraaxislabs.zendesk.com so support can open a Zendesk ticket. Failed jobs awaiting retry do not alert.',
        ],
        [
            'title' => 'SMTP email',
            'body'  => 'Office 365 SMTP uses notifications@nutraaxislabs.com on smtp.office365.com:587 with TLS. Set MAIL_FROM to the same mailbox if needed. Test with /cron/test-mail.php or php scripts/test-smtp.php. Use ?diagnose=1&to=external@example.com&internal=jbutler@nfcllc.com to compare tenant-internal vs external RCPT responses.',
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
            'title' => 'Authentication',
            'body'  => 'Cron endpoints require the X-Cron-Secret header (or ?key=) matching the CRON_SECRET App Setting. WebJobs pass the header automatically.',
        ],
        [
            'title' => 'Manual execution',
            'body'  => 'Operators with update access can rerun failed or abandoned jobs from Process Log. Developers can also trigger jobs with curl or npm scripts (daily-sales-summary, monthly-sales-summary, inventory-plan, jazz-snapshot).',
        ],
    ];
}
