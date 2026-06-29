<?php

require_once __DIR__ . '/auth.php';

function site_documentation_require_read(): void
{
    auth_require_module_read('site-documentation');
}

function site_documentation_data_profile_overview(): array
{
    return [
        [
            'title' => 'Production (default)',
            'body'  => 'Most portal pages use the production data profile. Azure SQL connects to nutraaxis (DB_NAME). Jazz OMS reads JAZZ_DOMAIN_PROD / JAZZ_*_PROD keys. Adobe Commerce reads ACCS production tenant (ADOBE_COMMERCE_PRODUCTION_ENVIRONMENT or production). QuickBooks uses QBO_CLIENT_ID_PROD when connected.',
        ],
        [
            'title' => 'UAT / test profile',
            'body'  => 'Pages under *-uat/ paths or jazz-asns-uat.php set data_profile_set(\'uat\') before loading shared templates. Jazz uses JAZZ_UAT_* (fallback JAZZ_*). Adobe Commerce uses stage/dev tenant (ADOBE_COMMERCE_UAT_ENVIRONMENT, default stage). Portal SQL is still nutraaxis — UAT pages only change external API targets, not the operations database.',
        ],
        [
            'title' => 'Background jobs',
            'body'  => 'Scheduled Azure Functions write to production SQL (nutraaxis) and call production ACCS/Jazz credentials configured on the Function App. Manual reruns from Process Log call Nutra-forecast-tool via NUTRA_FUNCTIONS_BASE_URL.',
        ],
    ];
}

function site_documentation_page_data_sources(): array
{
    return [
        ['path' => '/po-management/', 'title' => 'PO Management', 'tier' => 'production', 'sql' => 'nutraaxis', 'jazz' => '—', 'accs' => '—', 'notes' => 'Portal PO workflow data'],
        ['path' => '/po-receiving/', 'title' => 'PO Receiving', 'tier' => 'production', 'sql' => 'nutraaxis', 'jazz' => 'prod (transmit)', 'accs' => '—', 'notes' => 'ASN transmit uses production Jazz'],
        ['path' => '/po-payments/', 'title' => 'PO Payments', 'tier' => 'production', 'sql' => 'nutraaxis', 'jazz' => '—', 'accs' => '—', 'notes' => ''],
        ['path' => '/product-catalog/', 'title' => 'Product SKU Master', 'tier' => 'production', 'sql' => 'nutraaxis', 'jazz' => '—', 'accs' => '—', 'notes' => ''],
        ['path' => '/supplier-management/', 'title' => 'Supplier Management', 'tier' => 'production', 'sql' => 'nutraaxis', 'jazz' => '—', 'accs' => '—', 'notes' => ''],
        ['path' => '/delivery-scheduling-log/', 'title' => 'Delivery Schedule Log', 'tier' => 'production', 'sql' => 'nutraaxis', 'jazz' => 'prod (links)', 'accs' => '—', 'notes' => ''],
        ['path' => '/inventory-demand/', 'title' => 'Inventory Forecasting', 'tier' => 'production', 'sql' => 'nutraaxis', 'jazz' => '—', 'accs' => '—', 'notes' => 'Reads ForecastPlan, InventoryBalance tables'],
        ['path' => '/accs-inventory-reporting/', 'title' => 'ACCS Inventory Reporting', 'tier' => 'production', 'sql' => '—', 'jazz' => '—', 'accs' => 'production', 'notes' => 'Live ACCS MSI source items'],
        ['path' => '/accs-inventory-reporting-uat/', 'title' => 'ACCS Inventory (stage)', 'tier' => 'uat', 'sql' => '—', 'jazz' => '—', 'accs' => 'stage', 'notes' => 'UAT wrapper → shared template'],
        ['path' => '/inventory-balances/', 'title' => 'Inventory Balances', 'tier' => 'production', 'sql' => 'nutraaxis', 'jazz' => '—', 'accs' => '—', 'notes' => 'InvCurrentBalance + Facility IMS ledger (bootstrap from InventoryBalance snapshot)'],
        ['path' => '/inventory-reporting/', 'title' => 'Jazz Current Inventory', 'tier' => 'production', 'sql' => '—', 'jazz' => 'production', 'accs' => '—', 'notes' => 'Live Jazz OMS inventory API'],
        ['path' => '/inventory-reporting-uat/', 'title' => 'Jazz Current Inventory (UAT)', 'tier' => 'uat', 'sql' => '—', 'jazz' => 'UAT', 'accs' => '—', 'notes' => 'UAT wrapper → shared template'],
        ['path' => '/inventory-reconciliation/', 'title' => 'Inventory Reconciliation', 'tier' => 'production', 'sql' => '—', 'jazz' => 'production', 'accs' => 'production', 'notes' => 'Side-by-side Jazz + ACCS'],
        ['path' => '/inventory-reconciliation-uat/', 'title' => 'Inventory Reconciliation (UAT)', 'tier' => 'uat', 'sql' => '—', 'jazz' => 'UAT', 'accs' => 'stage', 'notes' => 'UAT wrapper → shared template'],
        ['path' => '/jazz-item-master/', 'title' => 'Jazz Item Master', 'tier' => 'production', 'sql' => '—', 'jazz' => 'production', 'accs' => '—', 'notes' => ''],
        ['path' => '/jazz-item-master-uat/', 'title' => 'Jazz Item Master (UAT)', 'tier' => 'uat', 'sql' => '—', 'jazz' => 'UAT', 'accs' => '—', 'notes' => 'UAT wrapper → shared template'],
        ['path' => '/po-receiving/jazz-asns.php', 'title' => 'Jazz ASNs', 'tier' => 'production', 'sql' => '—', 'jazz' => 'production', 'accs' => '—', 'notes' => 'Read-only Jazz ASN list'],
        ['path' => '/po-receiving/jazz-asns-uat.php', 'title' => 'Jazz ASNs (UAT)', 'tier' => 'uat', 'sql' => '—', 'jazz' => 'UAT', 'accs' => '—', 'notes' => 'UAT wrapper → jazz-asns.php'],
        ['path' => '/sales-reporting/accs-order-report/', 'title' => 'ACCS Order Report', 'tier' => 'production', 'sql' => '—', 'jazz' => '—', 'accs' => 'production', 'notes' => ''],
        ['path' => '/sales-reporting/jazz-order-report/', 'title' => 'Jazz Order Report', 'tier' => 'production', 'sql' => '—', 'jazz' => 'production', 'accs' => '—', 'notes' => 'Jazz OMS /api/v1/order/status'],
        ['path' => '/sales-reporting/accs-order-report-uat/', 'title' => 'ACCS Order Report (UAT)', 'tier' => 'uat', 'sql' => '—', 'jazz' => '—', 'accs' => 'stage', 'notes' => 'UAT wrapper → shared template'],
        ['path' => '/sales-reporting/jazz-order-report-uat/', 'title' => 'Jazz Order Report (UAT)', 'tier' => 'uat', 'sql' => '—', 'jazz' => 'uat', 'accs' => '—', 'notes' => 'UAT wrapper → shared template'],
        ['path' => '/sales-reporting/daily-sales-summary/', 'title' => 'Daily Sales Summary', 'tier' => 'production', 'sql' => 'nutraaxis', 'jazz' => '—', 'accs' => '—', 'notes' => 'DailySalesSummary table (prod ACCS rollup)'],
        ['path' => '/sales-reporting/monthly-sales-summary/', 'title' => 'Monthly Sales Summary', 'tier' => 'production', 'sql' => 'nutraaxis', 'jazz' => '—', 'accs' => '—', 'notes' => 'MonthlySalesSummary table'],
        ['path' => '/accounting/', 'title' => 'Accounting', 'tier' => 'production', 'sql' => 'nutraaxis (tokens)', 'jazz' => '—', 'accs' => '—', 'notes' => 'QuickBooks Online production (QBO_*_PROD)'],
        ['path' => '/labeling-operations/', 'title' => 'Custom Order Fulfillment', 'tier' => 'production', 'sql' => 'nutraaxis', 'jazz' => '—', 'accs' => '—', 'notes' => ''],
        ['path' => '/legal-agreements/', 'title' => 'Legal Agreements', 'tier' => 'production', 'sql' => 'nutraaxis', 'jazz' => '—', 'accs' => '—', 'notes' => ''],
        ['path' => '/support/', 'title' => 'Support', 'tier' => 'external', 'sql' => '—', 'jazz' => '—', 'accs' => '—', 'notes' => 'Zendesk API'],
        ['path' => '/process-log/', 'title' => 'Process Log', 'tier' => 'production', 'sql' => 'nutraaxis', 'jazz' => '—', 'accs' => '—', 'notes' => 'ProcessExecutionLog; reruns call Function App'],
        ['path' => '/site-admin/', 'title' => 'Site Administration', 'tier' => 'production', 'sql' => 'nutraaxis', 'jazz' => '—', 'accs' => '—', 'notes' => 'Users, roles, audit log'],
    ];
}

function site_documentation_azure_sql(): array
{
    return [
        'summary' => 'Operations portal and Azure Functions share Azure SQL Server nutraaxisdb01. The PHP App Service and scheduled jobs read/write nutraaxis only.',
        'databases' => [
            ['name' => 'nutraaxis', 'role' => 'Production', 'used_by' => 'PHP portal (DB_NAME), Azure Functions scheduled jobs, Process Log'],
        ],
        'connection' => [
            'Portal env keys' => 'DB_HOST or DB_SERVER, DB_NAME, DB_USER, DB_PASS (or DB_PASSWORD), DB_PORT',
            'Function App keys' => 'DB_SERVER, DB_NAME_PRODUCTION (or DB_NAME)',
            'Local dev' => '.env in repo root (never deployed)',
        ],
        'migrations' => 'Schema changes live in sql/ as numbered .sql files (e.g. 075_create_po_payment_attachment.sql). Apply to nutraaxis before deploying code that depends on new columns or tables.',
    ];
}

function site_documentation_function_apps(): array
{
    return [
        'apps' => [
            [
                'name' => 'Nutra-forecast-tool',
                'role' => 'Default Function App for scheduled jobs and Process Log reruns',
                'url_key' => 'NUTRA_FUNCTIONS_BASE_URL',
                'key_key' => 'NUTRA_FUNCTIONS_KEY',
            ],
            [
                'name' => 'Nutra-forecast-tool-prod',
                'role' => 'Production-only processes (e.g. accs-sales-order-sync)',
                'url_key' => 'NUTRA_FUNCTIONS_PROD_BASE_URL',
                'key_key' => 'NUTRA_FUNCTIONS_PROD_KEY',
            ],
        ],
        'functions' => [
            ['name' => 'daily-sales-summary', 'trigger' => 'Timer (daily 2:00 AM Central)', 'data' => 'Production ACCS orders → nutraaxis.DailySalesSummary'],
            ['name' => 'weekly-chain', 'trigger' => 'Timer (Sunday 1:00 AM Central)', 'data' => 'MonthlySalesSummary rollup + ForecastPlan refresh'],
            ['name' => 'jazz-inventory-snapshot', 'trigger' => 'Timer (Sunday noon Central)', 'data' => 'Production Jazz OMS → nutraaxis.InventoryBalance'],
            ['name' => 'process-retry', 'trigger' => 'Service Bus queue process-retry', 'data' => 'Retries failed ProcessExecutionLog jobs'],
            ['name' => 'process-execute', 'trigger' => 'HTTP POST (function key)', 'data' => 'Manual/portal-triggered job execution'],
            ['name' => 'accs-sales-order-sync', 'trigger' => 'Timer (every 2 hours)', 'data' => 'Production ACCS order detail sync — Nutra-forecast-tool-prod only'],
            ['name' => 'accs-order-webhook', 'trigger' => 'HTTP (anonymous + secret header)', 'data' => 'Production ACCS order events → fulfillment routing email'],
            ['name' => 'accs-employee-customer-create', 'trigger' => 'HTTP (function key)', 'data' => 'Creates ACCS customers from employee list'],
            ['name' => 'ping', 'trigger' => 'HTTP', 'data' => 'Health check'],
            ['name' => 'accs-jazz-order-test', 'trigger' => 'HTTP (function key)', 'data' => 'Integration test endpoint — not scheduled'],
        ],
        'source' => 'Function source is in the repo functions/ folder. Deploy separately from the PHP portal (not included in npm run upload).',
    ];
}

function site_documentation_service_bus(): array
{
    return [
        'summary' => 'Azure Service Bus schedules delayed retries when a background job fails. The process-retry function consumes messages from the process-retry queue.',
        'env_keys' => [
            'SERVICEBUS_CONNECTION_STRING' => 'Namespace connection string on the Function App',
            'SERVICEBUS_PROCESS_RETRY_QUEUE' => 'Queue name (default: process-retry)',
        ],
        'flow' => [
            'A timer or manual job fails after writing ProcessExecutionLog.',
            'process-runner schedules a Service Bus message with NextRetryAt (2, 4, 8 minute backoff).',
            'process-retry function dequeues the message and re-runs the job.',
            'After max attempts the job is marked Abandoned and may email alerts@nutraaxislabs.zendesk.com.',
        ],
        'message_type' => 'process-retry — body includes log_id, process_code, attempt_count',
    ];
}

function site_documentation_module_sections(): array
{
    global $appFunctions;

    $sections = [];

    foreach ($appFunctions as $module) {
        $entry = [
            'title'       => $module['title'],
            'description' => $module['desc'],
            'href'        => $module['href'],
            'children'    => [],
        ];

        if (function_exists('app_hub_slugs') && in_array($module['slug'], app_hub_slugs(), true)) {
            foreach (app_hub_submodules($module['slug']) as $child) {
                $childModule = get_module($child['slug']);
                $dataSource = site_documentation_data_source_note($child['href'], $child['tier'] ?? ENVIRONMENT_TIER_PRODUCTION);
                $entry['children'][] = [
                    'title'       => $child['title'],
                    'description' => $child['desc'],
                    'href'        => $child['href'],
                    'note'        => $childModule['headline'] ?? null,
                    'data_source' => $dataSource,
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
            ['title' => 'IT Product Backlog', 'description' => 'IT product backlog items with type, status, due dates, and notes.', 'href' => '/enhancement-log/'],
            ['title' => 'Links Index', 'description' => 'Curated shortcuts surfaced on the Operations Dashboard.', 'href' => '/links-index/'],
            ['title' => 'My Account', 'description' => 'View your profile and change your password.', 'href' => '/my-account/'],
            ['title' => 'Site Documentation', 'description' => 'This page — module reference and scheduled process guide.', 'href' => '/site-documentation/'],
        ],
    ];

    return $sections;
}

function site_documentation_data_source_note(string $href, string $tier = ENVIRONMENT_TIER_PRODUCTION): string
{
    $normalizedHref = parse_url($href, PHP_URL_PATH) ?: $href;

    foreach (site_documentation_page_data_sources() as $row) {
        $rowPath = rtrim($row['path'], '/');
        $checkPath = rtrim($normalizedHref, '/');
        if ($row['path'] === $href || $rowPath === $checkPath) {
            $parts = [];
            if (($row['sql'] ?? '') !== '' && $row['sql'] !== '—') {
                $parts[] = 'SQL: ' . $row['sql'];
            }
            if (($row['jazz'] ?? '') !== '' && $row['jazz'] !== '—') {
                $parts[] = 'Jazz: ' . $row['jazz'];
            }
            if (($row['accs'] ?? '') !== '' && $row['accs'] !== '—') {
                $parts[] = 'ACCS: ' . $row['accs'];
            }
            if ($parts === []) {
                $parts[] = 'Portal SQL: nutraaxis';
            }

            return implode(' · ', $parts);
        }
    }

    return environment_tier_normalize($tier) === ENVIRONMENT_TIER_UAT
        ? 'UAT profile — external APIs use test credentials'
        : 'Production profile — portal SQL nutraaxis';
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
            'writes_to' => 'InventoryBalance',
            'notes'     => 'Pulls live Jazz OMS inventory by SKU and facility.',
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
            'body'  => 'Operators with update access can rerun failed or abandoned jobs from Process Log. Reruns call Azure Function App process-execute (requires NUTRA_FUNCTIONS_BASE_URL and NUTRA_FUNCTIONS_KEY on this App Service). Function source lives in the repo functions/ folder; deploy to Nutra-forecast-tool separately from the PHP portal.',
        ],
    ];
}
