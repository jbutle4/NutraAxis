<?php

require_once __DIR__ . '/../portal-manual.php';

/**
 * Administration User Manual — Accounting hub + Administration home cards.
 */

/** @return list<array<string, mixed>> */
function administration_manual_modules(): array
{
    $modules = [];
    $seen = [];

    foreach (app_accounting_submodules() as $child) {
        $slug = (string) ($child['slug'] ?? '');
        if ($slug === '' || isset($seen[$slug])) {
            continue;
        }
        $seen[$slug] = true;
        $modules[] = $child;
    }

    foreach (app_functions() as $fn) {
        if (($fn['group'] ?? '') !== 'admin') {
            continue;
        }
        $slug = (string) ($fn['slug'] ?? '');
        if ($slug === '' || isset($seen[$slug]) || $slug === 'administration-manual') {
            continue;
        }
        // Skip the Accounting hub card itself — its leaves are already listed.
        if ($slug === 'accounting') {
            continue;
        }
        $seen[$slug] = true;
        $modules[] = $fn;
    }

    return $modules;
}

function administration_manual_definition(): array
{
    return [
        'active_slug' => 'administration-manual',
        'category'    => 'Administration',
        'title'       => 'User Manual',
        'lead'        => 'Accounting, legal agreements, support, sales territories, and administration shortcuts — who does what and how to use each page.',
        'intro'       => 'Administration covers finance and control surfaces that sit beside Supply Chain: QuickBooks Online views, supplier invoices and payments, approval queues, legal agreements, Zendesk support, sales-rep territories, and the system performance placeholder. Day-to-day operational shortcuts (SharePoint, Process Log, provider signup) live under the **Operations** section on the home page — see that User Manual for those.',
        'golden_rules' => [
            '**Production vs UAT.** Accounting cards labeled **(UAT)** use QuickBooks Sandbox only. Never enter a real vendor bill on a UAT screen.',
            '**Supplier Invoices are the AP entry point.** Create bills and attachments in the portal, then sync to QuickBooks — don’t dual-key the same bill only in QBO unless Finance has agreed an exception.',
            '**Approvals before payment and QBO insert.** Use Approvals Queue / Approvals; dual control applies when configured.',
            '**Legal agreements are the contract system of record** for tracked renewals — keep renewal dates current.',
            '**Support tickets stay in Zendesk**; the portal Support module is the signed-in view into that queue.',
        ],
        'access_html' => 'Accounting pages need the Accounting permission. Legal, Support, Sales Reporting (territories), and Operations Dashboard each have their own columns. Missing cards mean missing Read on that module.',
        'roles' => [
            [
                'role'   => 'AP / accounting',
                'access' => 'Accounting (Create / Read / Update as assigned)',
                'does'   => 'Supplier invoices, invoice payments, QBO AP/AR/PO/supplier/COA views, bill sync, and approval follow-up.',
            ],
            [
                'role'   => 'Controller / approver',
                'access' => 'Accounting + approval rights',
                'does'   => 'Clears payment and QBO insert approvals; reviews open AP/AR.',
            ],
            [
                'role'   => 'Legal / admin',
                'access' => 'Legal Agreements',
                'does'   => 'Stores contracts and tracks renewal dates.',
            ],
            [
                'role'   => 'Support agent',
                'access' => 'Support',
                'does'   => 'Works Zendesk tickets from the portal Support module.',
            ],
            [
                'role'   => 'Sales ops admin',
                'access' => 'Sales Reporting',
                'does'   => 'Maintains sales-rep territory assignments used by Sales Rep Reporting.',
            ],
        ],
        'rhythm_intro' => 'AP is daily; legal renewals are calendar-driven; support is continuous.',
        'rhythm' => [
            ['when' => 'Daily', 'who' => 'AP', 'what' => 'Enter new **Supplier Invoices**, clear **Approvals Queue**, record **Invoice Payments**.', 'link' => ['supplier-invoices', '', null]],
            ['when' => 'Daily', 'who' => 'AP', 'what' => 'Scan **Accounts Payable** for aging; confirm QBO bill sync status.', 'link' => ['qbo-accounts-payable', '', null]],
            ['when' => 'Weekly', 'who' => 'Support lead', 'what' => 'Review open Zendesk volume via **Support**.', 'link' => ['support', '', null]],
            ['when' => 'Monthly', 'who' => 'Legal / admin', 'what' => 'Check **Legal Agreements** for renewals in the next 90 days.', 'link' => ['legal-agreements', '', null]],
            ['when' => 'When territories change', 'who' => 'Sales ops', 'what' => 'Update **Sales Rep Territory Assignment**.', 'link' => ['sales-rep-territory-assignment', '', null]],
        ],
        'workflows' => administration_manual_workflows(),
        'page_index' => portal_manual_modules_to_index(administration_manual_modules()),
        'page_guide' => administration_manual_pages(),
        'statuses' => [
            'Supplier invoice' => ['Draft', 'Submitted', 'Payment approval', 'QBO insert', 'Synced'],
            'Agreement' => ['Active', 'Expiring', 'Expired', 'Superseded'],
        ],
        'other_instructions' => [
            'Operations home card' => 'The **Operations Dashboard** card on Administration returns you to the home page Operations / IT sections — it is not a separate app.',
            'QBO connection' => 'Users with Accounting Update can connect or disconnect the QuickBooks company from Accounting settings (where exposed). Sandbox vs Production is environment-specific.',
            'Asking for access' => 'Site Admin → Roles: Accounting, Legal Agreements, Support, Sales Reporting, Operations Dashboard as needed.',
        ],
        'troubleshooting' => [
            ['Invoice stuck before QBO', 'Open Approvals Queue — payment or QBO insert may be pending or failed. Use recovery actions when shown.'],
            ['Wrong company in QuickBooks views', 'Confirm you are on Production vs (UAT) cards. Reconnect QBO if the wrong realm is linked.'],
            ['Support module empty', 'Confirm Zendesk access for your user and the Support permission column.'],
            ['Anything else', 'Contact the Administration / Finance owner or Joe Butler.'],
        ],
    ];
}

function administration_manual_workflows(): array
{
    return [
        'ap' => [
            'title'   => 'Vendor bill: invoice → approve → QuickBooks → pay',
            'summary' => 'Non-PO or PO-linked supplier invoices enter AP, get dual-control approvals, sync as QBO bills, then payment is recorded.',
            'cadence' => 'Daily AP.',
            'steps'   => [
                ['who' => 'AP / buyer', 'when' => 'When the vendor bill arrives', 'what' => 'Create **Supplier Invoice** with lines and attach the PDF.', 'link' => ['supplier-invoices', '', null]],
                ['who' => 'Approver', 'when' => 'When pending', 'what' => 'Clear payment and/or QBO insert from **Approvals Queue**.', 'link' => ['procurement-approvals', '', null]],
                ['who' => 'AP', 'when' => 'After insert', 'what' => 'Confirm the bill on **Accounts Payable** / **QBO Bill Sync**.', 'link' => ['qbo-accounts-payable', '', null]],
                ['who' => 'AP', 'when' => 'When paying', 'what' => 'Record **Invoice Payments** (or PO Payments for PO-tied spend).', 'link' => ['invoice-payments', '', null]],
            ],
        ],
        'qbo-views' => [
            'title'   => 'Review QuickBooks balances',
            'summary' => 'Read-only (and sync) views of the connected QBO company for AP, AR, POs, SKUs, suppliers, and chart of accounts.',
            'cadence' => 'As needed; week/month close.',
            'steps'   => [
                ['who' => 'AP', 'when' => 'Weekly', 'what' => '**Accounts Payable** aging and open bills.', 'link' => ['qbo-accounts-payable', '', null]],
                ['who' => 'AR', 'when' => 'Weekly', 'what' => '**Accounts Receivable** open invoices.', 'link' => ['qbo-accounts-receivable', '', null]],
                ['who' => 'AP', 'when' => 'As needed', 'what' => '**Purchase Orders**, **Suppliers**, **QBO SKU Master**, **Chart of Accounts**.', 'link' => ['qbo-purchase-orders', '', null]],
            ],
        ],
        'legal' => [
            'title'   => 'Track a contract renewal',
            'summary' => 'Keep agreements filed with owners and renewal dates so nothing lapses quietly.',
            'cadence' => 'On signature; monthly renewal scan.',
            'steps'   => [
                ['who' => 'Admin', 'when' => 'On signature', 'what' => 'Add the agreement on **Legal Agreements** with counterparty, dates, and file.', 'link' => ['legal-agreements', '', null]],
                ['who' => 'Admin', 'when' => 'Monthly', 'what' => 'Filter upcoming renewals; start renegotiation or notice before the deadline.', 'link' => ['legal-agreements', '', null]],
            ],
        ],
        'territory' => [
            'title'   => 'Update sales-rep territories',
            'summary' => 'State / zip / county assignments drive Sales Rep Reporting under Supply Chain.',
            'cadence' => 'When reps or geographies change.',
            'steps'   => [
                ['who' => 'Sales ops', 'when' => 'On change', 'what' => 'Edit assignments on **Sales Rep Territory Assignment**.', 'link' => ['sales-rep-territory-assignment', '', null]],
                ['who' => 'Sales ops', 'when' => 'After save', 'what' => 'Spot-check **Sales Rep Reporting** for a known order.', 'link' => null],
            ],
        ],
    ];
}

function administration_manual_pages(): array
{
    return [
        'supplier-invoices' => [
            'who'     => 'AP / buyers',
            'purpose' => 'Create vendor invoices, attach documents, prepare QuickBooks Bill sync.',
            'actions' => ['Create', 'Attach', 'Submit'],
        ],
        'invoice-payments' => [
            'who'     => 'AP',
            'purpose' => 'Record payments against supplier invoices not tied to a PO.',
            'actions' => ['Record payment'],
        ],
        'qbo-accounts-payable' => [
            'who'     => 'AP',
            'purpose' => 'Open bills and vendor balances from QuickBooks Production.',
            'actions' => ['Browse / filter'],
        ],
        'qbo-accounts-receivable' => [
            'who'     => 'AR / finance',
            'purpose' => 'Customer invoices and outstanding balances from QuickBooks Production.',
            'actions' => ['Browse / filter'],
        ],
        'qbo-purchase-orders' => [
            'who'     => 'AP / buyers',
            'purpose' => 'QuickBooks purchase orders and status.',
            'actions' => ['Browse'],
        ],
        'qbo-sku-master' => [
            'who'     => 'AP / inventory',
            'purpose' => 'QuickBooks inventory items — SKU, pricing, Qty on hand.',
            'actions' => ['Browse'],
        ],
        'qbo-suppliers' => [
            'who'     => 'AP',
            'purpose' => 'QuickBooks vendor directory and balances.',
            'actions' => ['Browse'],
        ],
        'qbo-chart-of-accounts' => [
            'who'     => 'Finance',
            'purpose' => 'General ledger accounts from QuickBooks.',
            'actions' => ['Browse'],
        ],
        'qbo-sync-bills' => [
            'who'     => 'AP',
            'purpose' => 'Link and import QuickBooks bills with Operations supplier invoices.',
            'actions' => ['Sync / link'],
        ],
        'procurement-approvals' => [
            'who'     => 'Approvers',
            'purpose' => 'Pending and completed PO and supplier invoice approvals.',
            'actions' => ['Approve', 'Return', 'Retry'],
        ],
        'approvals' => [
            'who'     => 'Approvers',
            'purpose' => 'Combined pending requests and history across PO, payment, and QBO insert.',
            'actions' => ['Review', 'Approve'],
        ],
        'legal-agreements' => [
            'who'     => 'Legal / admin',
            'purpose' => 'Store agreements and track renewal dates.',
            'actions' => ['Add', 'Update', 'Attach file'],
        ],
        'operations-dashboard' => [
            'who'     => 'Everyone with Operations access',
            'purpose' => 'Shortcut back to the home page Operations and IT & Ecommerce sections.',
            'actions' => ['Open home'],
        ],
        'sales-rep-territory-assignment' => [
            'who'     => 'Sales ops',
            'purpose' => 'State/zip/county assignments for Sales Rep Reporting.',
            'actions' => ['Edit assignments'],
        ],
        'support' => [
            'who'     => 'Support agents',
            'purpose' => 'View and work Zendesk tickets from the portal.',
            'actions' => ['Open tickets', 'Update'],
        ],
        'system-performance-dashboard' => [
            'who'     => 'IT / ops',
            'purpose' => 'Planned IT monitoring and Geckoboard views — not built yet.',
            'actions' => ['None yet'],
        ],
    ];
}
