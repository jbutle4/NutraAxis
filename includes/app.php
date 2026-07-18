<?php

require_once __DIR__ . '/data-profile.php';

function icon_svg(string $name, int $size = 24): string
{
    $icons = [
        'clipboard' => '<path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 12h6M9 16h6"/>',
        'boxes'     => '<path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><path d="M3.27 6.96L12 12.01l8.73-5.05M12 22.08V12"/>',
        'chart'     => '<path d="M3 3v18h18"/><path d="M7 16l4-4 4 4 5-6"/>',
        'trend'     => '<path d="M23 6l-9.5 9.5-5-5L1 18"/><path d="M17 6h6v6"/>',
        'tag'       => '<path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><circle cx="7" cy="7" r="1.5"/>',
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        'document'  => '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>',
        'catalog'   => '<path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><path d="M3 6h18M16 10a4 4 0 01-8 0"/>',
        'links'     => '<path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/>',
        'inventory' => '<path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><path d="M3.27 6.96L12 12.01l8.73-5.05M12 22.08V12"/>',
        'supplier'  => '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>',
        'payment'   => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M12 9.5v5M9.5 12h5"/>',
        'calendar'  => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'support'   => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="4"/><path d="M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>',
        'accounting'=> '<path d="M4 2h16a2 2 0 012 2v16a2 2 0 01-2 2H4a2 2 0 01-2-2V4a2 2 0 012-2z"/><path d="M8 6h8M8 10h8M8 14h5"/>',
    ];

    $body = $icons[$name] ?? $icons['dashboard'];
    return '<svg width="' . $size . '" height="' . $size . '" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">' . $body . '</svg>';
}

$productMasterSubModules = [
    [
        'slug'  => 'product-catalog',
        'title' => 'Product SKU Master',
        'desc'  => 'Maintain the master product catalog and SKU reference data.',
        'href'  => '/product-catalog/',
        'icon'  => 'catalog',
        'tier'  => 'production',
        'sort'  => 10,
    ],
    [
        'slug'  => 'product-enrichment',
        'title' => 'Product Page Enrichment',
        'desc'  => 'Manage PDP HTML and information sheet PDFs for nutraaxislabs.com product pages.',
        'href'  => '/product-enrichment/',
        'icon'  => 'document',
        'tier'  => 'production',
        'sort'  => 15,
    ],
    [
        'slug'  => 'jazz-item-master',
        'title' => 'Jazz Item Master',
        'desc'  => 'Production SKU and item reference data synced from Jazz OMS.',
        'href'  => '/jazz-item-master/',
        'icon'  => 'catalog',
        'tier'  => 'production',
        'sort'  => 20,
    ],
    [
        'slug'  => 'jazz-item-master-uat',
        'title' => 'Jazz Item Master (UAT)',
        'desc'  => 'UAT System — SKU and item reference data synced from Jazz OMS.',
        'href'  => '/jazz-item-master-uat/',
        'icon'  => 'catalog',
        'tier'  => 'uat',
        'sort'  => 210,
    ],
    [
        'slug'  => 'qbo-sku-master',
        'title' => 'QBO Product Master',
        'desc'  => 'QuickBooks inventory items — SKU, pricing, quantity on hand, and NutraAxis link.',
        'href'  => '/accounting/inventory.php',
        'icon'  => 'accounting',
        'tier'  => 'production',
        'sort'  => 30,
    ],
];

$inventoryManagementSubModules = [
    [
        'slug'  => 'product-catalog',
        'title' => 'SKU Master',
        'desc'  => 'Create and maintain NutraAxis SKUs, attributes, pricing, and QuickBooks sync.',
        'href'  => '/product-catalog/',
        'icon'  => 'catalog',
        'tier'  => 'production',
        'sort'  => 1,
    ],
    [
        'slug'  => 'inventory-balances',
        'title' => 'Inventory Balances',
        'desc'  => 'Live operational stock by SKU and facility from the IMS ledger.',
        'href'  => '/inventory-balances/',
        'icon'  => 'inventory',
        'tier'  => 'production',
        'sort'  => 5,
    ],
    [
        'slug'  => 'inventory-transfers',
        'title' => 'Facility Transfers',
        'desc'  => 'Move stock between Cart.com, CPPC, White Label, and transit.',
        'href'  => '/inventory-transfers/',
        'icon'  => 'inventory',
        'tier'  => 'production',
        'sort'  => 6,
    ],
    [
        'slug'  => 'inventory-adjustments',
        'title' => 'Inventory Adjustments',
        'desc'  => 'Approve shrink/gain adjustments to IMS and QuickBooks Qty on hand.',
        'href'  => '/inventory-adjustments/',
        'icon'  => 'inventory',
        'tier'  => 'production',
        'sort'  => 7,
    ],
    [
        'slug'  => 'inventory-jazz-ims-recon',
        'title' => 'Jazz vs IMS CART',
        'desc'  => 'Compare Jazz mothership on-hand with IMS CART ledger balances.',
        'href'  => '/inventory-jazz-ims-recon/',
        'icon'  => 'boxes',
        'tier'  => 'production',
        'sort'  => 8,
    ],
    [
        'slug'  => 'inventory-qbo-recon',
        'title' => 'QBO Inventory Reconciliation',
        'desc'  => 'Compare IMS location totals with QuickBooks Qty on hand.',
        'href'  => '/inventory-qbo-recon/',
        'icon'  => 'accounting',
        'tier'  => 'production',
        'sort'  => 9,
    ],
    [
        'slug'  => 'inventory-movement-recon',
        'title' => 'Movement Completeness',
        'desc'  => 'Find receipts, sales, transfers, and adjustments missing IMS or QBO posts.',
        'href'  => '/inventory-movement-recon/',
        'icon'  => 'inventory',
        'tier'  => 'production',
        'sort'  => 10,
    ],
    [
        'slug'  => 'qbo-inventory',
        'title' => 'QBO Inventory',
        'desc'  => 'QuickBooks Online quantity on hand by SKU — financial inventory view.',
        'href'  => '/accounting/inventory.php',
        'icon'  => 'accounting',
        'tier'  => 'production',
        'sort'  => 8,
    ],
    [
        'slug'  => 'inventory-reporting',
        'title' => 'Jazz Current Inventory',
        'desc'  => 'Jazz OMS production stock levels by SKU and facility.',
        'href'  => '/inventory-reporting/',
        'icon'  => 'boxes',
        'tier'  => 'production',
        'sort'  => 10,
    ],
    [
        'slug'  => 'accs-inventory-reporting',
        'title' => 'ACCS Inventory Reporting',
        'desc'  => 'Production Adobe Commerce (ACCS) inventory by SKU and source.',
        'href'  => '/accs-inventory-reporting/',
        'icon'  => 'chart',
        'tier'  => 'production',
        'sort'  => 20,
    ],
    [
        'slug'  => 'inventory-reconciliation',
        'title' => 'Inventory Reconciliation',
        'desc'  => 'Compare production Jazz OMS and ACCS inventory levels for the same SKU.',
        'href'  => '/inventory-reconciliation/',
        'icon'  => 'trend',
        'tier'  => 'production',
        'sort'  => 30,
    ],
    [
        'slug'  => 'inventory-forecasting',
        'title' => 'Inventory Forecasting',
        'desc'  => 'Project demand and plan replenishment with confidence.',
        'href'  => '/inventory-demand/',
        'icon'  => 'trend',
        'tier'  => 'production',
        'sort'  => 40,
    ],
    [
        'slug'  => 'accs-inventory-reporting-uat',
        'title' => 'ACCS Inventory Reporting (stage)',
        'desc'  => 'UAT System — Adobe Commerce (ACCS) inventory by SKU and source.',
        'href'  => '/accs-inventory-reporting-uat/',
        'icon'  => 'chart',
        'tier'  => 'uat',
        'sort'  => 210,
    ],
    [
        'slug'  => 'inventory-reporting-uat',
        'title' => 'Jazz Current Inventory (UAT)',
        'desc'  => 'UAT System — Jazz OMS stock levels by SKU and facility.',
        'href'  => '/inventory-reporting-uat/',
        'icon'  => 'boxes',
        'tier'  => 'uat',
        'sort'  => 220,
    ],
    [
        'slug'  => 'inventory-reconciliation-uat',
        'title' => 'Inventory Reconciliation (UAT)',
        'desc'  => 'UAT System — Compare Jazz OMS and ACCS inventory levels for the same SKU.',
        'href'  => '/inventory-reconciliation-uat/',
        'icon'  => 'trend',
        'tier'  => 'uat',
        'sort'  => 230,
    ],
];

$procurementSubModules = [
    [
        'slug'  => 'po-management',
        'title' => 'PO Management',
        'desc'  => 'Create, approve, and track purchase orders across suppliers.',
        'href'  => '/po-management/',
        'icon'  => 'clipboard',
        'tier'  => 'production',
        'sort'  => 10,
    ],
    [
        'slug'  => 'supplier-management',
        'title' => 'Supplier Management',
        'desc'  => 'Maintain supplier profiles, contacts, and procurement relationships.',
        'href'  => '/supplier-management/',
        'icon'  => 'supplier',
        'tier'  => 'production',
        'sort'  => 20,
    ],
    [
        'slug'  => 'po-payments',
        'title' => 'Supplier Payments',
        'desc'  => 'Submit and track payment requests against purchase orders.',
        'href'  => '/po-payments/',
        'icon'  => 'payment',
        'tier'  => 'production',
        'sort'  => 30,
    ],
    [
        'slug'  => 'qbo-purchase-orders',
        'title' => 'QBO Purchase Orders',
        'desc'  => 'QuickBooks Online purchase orders and open PO status.',
        'href'  => '/accounting/pos.php',
        'icon'  => 'accounting',
        'tier'  => 'production',
        'sort'  => 40,
    ],
    [
        'slug'  => 'qbo-suppliers',
        'title' => 'QBO Suppliers',
        'desc'  => 'QuickBooks Online vendor directory and supplier balances.',
        'href'  => '/accounting/suppliers.php',
        'icon'  => 'accounting',
        'tier'  => 'production',
        'sort'  => 50,
    ],
];

$inboundReceivingSubModules = [
    [
        'slug'  => 'po-receiving',
        'title' => 'PO Receiving',
        'desc'  => 'Advanced shipping notices for inbound shipments, expected receipts, and PO receiving.',
        'href'  => '/po-receiving/',
        'icon'  => 'boxes',
        'tier'  => 'production',
        'sort'  => 10,
    ],
    [
        'slug'  => 'delivery-scheduling-log',
        'title' => 'Delivery Schedule Log',
        'desc'  => 'Track inbound delivery appointments and scheduling updates.',
        'href'  => '/delivery-scheduling-log/',
        'icon'  => 'calendar',
        'tier'  => 'production',
        'sort'  => 20,
    ],
    [
        'slug'  => 'jazz-asns',
        'title' => 'Jazz ASNs',
        'desc'  => 'Browse production advanced shipping notices synced from Jazz OMS.',
        'href'  => '/po-receiving/jazz-asns.php',
        'icon'  => 'document',
        'tier'  => 'production',
        'sort'  => 30,
    ],
    [
        'slug'  => 'jazz-asns-uat',
        'title' => 'Jazz ASNs (UAT)',
        'desc'  => 'UAT System — Browse advanced shipping notices synced from Jazz OMS.',
        'href'  => '/po-receiving/jazz-asns-uat.php',
        'icon'  => 'document',
        'tier'  => 'uat',
        'sort'  => 210,
    ],
];

/** @deprecated Legacy variable — submodules are grouped by hub in app.php. */
$inventorySubModules = [];

$salesReportingSubModules = [
    [
        'slug'  => 'accs-order-report',
        'title' => 'ACCS Order Report',
        'desc'  => 'Browse and search Adobe Commerce (ACCS) production orders and order detail.',
        'href'  => '/sales-reporting/accs-order-report/',
        'icon'  => 'clipboard',
        'tier'  => 'production',
    ],
    [
        'slug'  => 'jazz-order-report',
        'title' => 'Jazz Order Report',
        'desc'  => 'Browse and search Jazz OMS production orders and line-item detail.',
        'href'  => '/sales-reporting/jazz-order-report/',
        'icon'  => 'clipboard',
        'tier'  => 'production',
    ],
    [
        'slug'  => 'sales-daily-summary',
        'title' => 'Daily Sales Summary',
        'desc'  => 'Daily SKU quantity totals rolled up from ACCS orders.',
        'href'  => '/sales-reporting/daily-sales-summary/',
        'icon'  => 'chart',
        'tier'  => 'production',
    ],
    [
        'slug'  => 'sales-monthly-summary',
        'title' => 'Monthly Sales Summary',
        'desc'  => 'Monthly SKU quantity totals materialized from daily sales.',
        'href'  => '/sales-reporting/monthly-sales-summary/',
        'icon'  => 'trend',
        'tier'  => 'production',
    ],
    [
        'slug'  => 'accs-order-report-uat',
        'title' => 'ACCS Order Report',
        'desc'  => 'UAT System Browse and search Adobe Commerce (ACCS) stage orders and order detail.',
        'href'  => '/sales-reporting/accs-order-report-uat/',
        'icon'  => 'clipboard',
        'tier'  => 'uat',
    ],
    [
        'slug'  => 'jazz-order-report-uat',
        'title' => 'Jazz Order Report',
        'desc'  => 'UAT System Browse and search Jazz OMS test orders and line-item detail.',
        'href'  => '/sales-reporting/jazz-order-report-uat/',
        'icon'  => 'clipboard',
        'tier'  => 'uat',
    ],
];

$appFunctions = [
    [
        'slug'  => 'product-master',
        'title' => 'Product Master',
        'desc'  => 'SKU catalog, attributes, and Jazz item reference data.',
        'href'  => '/product-master/',
        'icon'  => 'catalog',
        'group' => 'supply-chain',
    ],
    [
        'slug'  => 'inventory-management',
        'title' => 'Inventory Management',
        'desc'  => 'Stock visibility, reconciliation, and demand forecasting across facilities.',
        'href'  => '/inventory-management/',
        'icon'  => 'inventory',
        'group' => 'supply-chain',
    ],
    [
        'slug'  => 'procurement',
        'title' => 'Procurement',
        'desc'  => 'Purchase orders, suppliers, and PO payment tracking.',
        'href'  => '/procurement/',
        'icon'  => 'clipboard',
        'group' => 'supply-chain',
    ],
    [
        'slug'  => 'inbound-receiving',
        'title' => 'Inbound & Receiving',
        'desc'  => 'PO receipts, delivery scheduling, and advanced shipping notices.',
        'href'  => '/inbound-receiving/',
        'icon'  => 'boxes',
        'group' => 'supply-chain',
    ],
    [
        'slug'  => 'sales-reporting',
        'title' => 'Sales & Order Reporting',
        'desc'  => 'ACCS and Jazz order lookup, daily sales, and monthly sales summaries.',
        'href'  => '/sales-reporting/',
        'icon'  => 'chart',
        'group' => 'supply-chain',
    ],
    [
        'slug'  => 'labeling-operations',
        'title' => 'Custom Order Fulfillment Operations',
        'desc'  => 'Manage label templates, batches, and compliance workflows.',
        'href'  => '/labeling-operations/',
        'icon'  => 'tag',
        'group' => 'supply-chain',
    ],
    [
        'slug'  => 'coa-management',
        'title' => 'Manage our COAs',
        'desc'  => 'Upload Certificates of Analysis PDFs and metadata for the public nutraaxislabs.com COA table.',
        'href'  => '/coa-management/',
        'icon'  => 'document',
        'group' => 'supply-chain',
    ],
    [
        'slug'  => 'accounting',
        'title' => 'QuickBooks Online',
        'desc'  => 'QuickBooks Online views plus supplier invoices and invoice payments for AP, AR, POs, inventory, suppliers, and chart of accounts.',
        'href'  => '/accounting/',
        'icon'  => 'accounting',
        'group' => 'admin',
    ],
    [
        'slug'  => 'legal-agreements',
        'title' => 'Legal Agreements & Contracts',
        'desc'  => 'Store and track legal agreements, contracts, and renewal dates.',
        'href'  => '/legal-agreements/',
        'icon'  => 'document',
        'group' => 'admin',
    ],
    [
        'slug'  => 'operations-dashboard',
        'title' => 'Operations Dashboard',
        'desc'  => 'Shortcuts to Microsoft 365, QuickBooks, Adobe Commerce, and support tools.',
        'href'  => '/operations-dashboard/',
        'icon'  => 'dashboard',
        'group' => 'admin',
    ],
    [
        'slug'  => 'support',
        'title' => 'Support',
        'desc'  => 'View Zendesk tickets, create requests, and manage support conversations.',
        'href'  => '/support/',
        'icon'  => 'support',
        'group' => 'admin',
    ],
    [
        'slug'  => 'system-performance-dashboard',
        'title' => 'System Performance Dashboard',
        'desc'  => 'IT system monitoring, Geckoboard dashboards, and Zendesk totals — coming soon.',
        'href'  => '/system-performance-dashboard/',
        'icon'  => 'trend',
        'group' => 'admin',
    ],
];

$accountLinks = [
    ['title' => 'Site Admin', 'href' => '/site-admin/'],
    ['title' => 'My Account', 'href' => '/my-account/'],
    ['title' => 'Log Out',   'href' => '/logout/', 'class' => 'nav-logout'],
];

$modulePages = [
    'product-master' => [
        'label'       => 'Master Data',
        'headline'    => 'Product Master',
        'lead'        => 'Define and maintain NutraAxis SKU master data and Jazz OMS item references.',
        'capabilities' => [
            ['title' => 'Product SKU Master', 'desc' => 'Canonical SKU codes, attributes, pricing, and QuickBooks sync settings.'],
            ['title' => 'Jazz Item Master', 'desc' => 'Read-only Jazz OMS item reference for reconciliation.'],
            ['title' => 'QBO Product Master', 'desc' => 'QuickBooks inventory items — SKU, pricing, quantity on hand, and NutraAxis link.'],
        ],
    ],
    'inventory-management' => [
        'label'       => 'Inventory',
        'headline'    => 'Inventory Management',
        'lead'        => 'Monitor stock across systems, reconcile Jazz and ACCS, and plan replenishment.',
        'capabilities' => [
            ['title' => 'SKU Master', 'desc' => 'Create and maintain NutraAxis SKUs and catalog data.'],
            ['title' => 'Inventory Balances', 'desc' => 'Live IMS ledger balances by SKU, facility, and status bucket.'],
            ['title' => 'QBO Inventory', 'desc' => 'QuickBooks Online quantity on hand by SKU.'],
            ['title' => 'Jazz Current Inventory', 'desc' => 'Jazz OMS stock on hand by SKU and facility.'],
            ['title' => 'ACCS Inventory Reporting', 'desc' => 'Adobe Commerce inventory by SKU and source.'],
            ['title' => 'Inventory Reconciliation', 'desc' => 'Side-by-side Jazz vs ACCS quantity compare.'],
            ['title' => 'Inventory Forecasting', 'desc' => 'Demand projections and replenishment planning.'],
        ],
    ],
    'procurement' => [
        'label'       => 'Procurement',
        'headline'    => 'Procurement',
        'lead'        => 'Source materials and services — purchase orders, supplier records, and PO payments.',
        'capabilities' => [
            ['title' => 'PO Management', 'desc' => 'Create, approve, and track purchase orders.'],
            ['title' => 'Supplier Management', 'desc' => 'Supplier profiles, contacts, and QuickBooks vendor links.'],
            ['title' => 'PO Payments', 'desc' => 'Submit and track payment requests against purchase orders.'],
            ['title' => 'QBO Purchase Orders', 'desc' => 'QuickBooks Online purchase orders and status.'],
            ['title' => 'QBO Suppliers', 'desc' => 'QuickBooks Online vendor directory and balances.'],
        ],
    ],
    'inbound-receiving' => [
        'label'       => 'Inbound',
        'headline'    => 'Inbound & Receiving',
        'lead'        => 'Receive inbound goods — PO receipts, dock scheduling, and ASN visibility.',
        'capabilities' => [
            ['title' => 'PO Receiving', 'desc' => 'Confirm receipts against purchase order lines.'],
            ['title' => 'Delivery Schedule Log', 'desc' => 'Inbound delivery appointments and carrier updates.'],
            ['title' => 'Jazz ASNs', 'desc' => 'Advanced shipping notices transmitted to Jazz OMS.'],
        ],
    ],
    'po-receiving' => [
        'label'       => 'Inbound',
        'headline'    => 'PO Receiving',
        'lead'        => 'Manage advanced shipping notices from suppliers and CMOs — track inbound shipments, cartons, and expected PO receipts.',
        'capabilities' => [
            ['title' => 'Inbound Shipments', 'desc' => 'View expected deliveries tied to open purchase orders.'],
            ['title' => 'Receipt Planning', 'desc' => 'See quantities, lot details, and arrival dates before goods land.'],
            ['title' => 'PO Alignment', 'desc' => 'Match ASN line items to purchase order lines for receiving.'],
            ['title' => 'Supplier Notices', 'desc' => 'Centralize ASN documents from NutraSeal, VitaQuest, and other partners.'],
        ],
    ],
    'po-payments' => [
        'label'       => 'Procurement',
        'headline'    => 'PO Payments',
        'lead'        => 'Submit and track payment requests applied to purchase orders, including payment type, confirmation numbers, and payer details.',
        'capabilities' => [
            ['title' => 'Payment Requests', 'desc' => 'View payment requests across purchase orders in one list.'],
            ['title' => 'New Payment Request', 'desc' => 'Create check, ACH, and credit card payment requests against open POs.'],
            ['title' => 'PO Payment History', 'desc' => 'See payment history directly on each purchase order.'],
            ['title' => 'Balance Tracking', 'desc' => 'Compare total paid against PO totals to see remaining balance.'],
        ],
    ],
    'po-management' => [
        'label'       => 'Procurement',
        'headline'    => 'Purchase Order Management',
        'lead'        => 'Centralize supplier purchase orders from creation through receipt. Track approvals, delivery dates, and line-item status in one place.',
        'capabilities' => [
            ['title' => 'Create & Submit POs', 'desc' => 'Build purchase orders with supplier catalogs, pricing, and expected delivery windows.'],
            ['title' => 'Approval Workflow', 'desc' => 'Route orders through review and sign-off before they are sent to vendors.'],
            ['title' => 'Receipt Tracking', 'desc' => 'Match incoming shipments to open PO lines and flag partial or overdue deliveries.'],
            ['title' => 'Supplier History', 'desc' => 'Review past orders, lead times, and fulfillment performance by supplier.'],
        ],
    ],
    'travel-expense' => [
        'label'       => 'Finance',
        'headline'    => 'Travel & Expense',
        'lead'        => 'Employees submit NFC-style expense reports with line items, mileage, entertainment, and miscellaneous reimbursements.',
        'capabilities' => [
            ['title' => 'Expense Reports', 'desc' => 'Create and edit draft reports with category expense lines and itemized mileage.'],
            ['title' => 'Receipt PDFs', 'desc' => 'Attach receipt PDFs before submitting for manager approval.'],
            ['title' => 'Approval Workflow', 'desc' => 'Designated T&E approvers review, approve, reject, or return reports for comment.'],
            ['title' => 'Printable Summary', 'desc' => 'Generate a printable report for records and payroll processing.'],
        ],
    ],
    'accs-inventory-reporting' => [
        'label'       => 'Inventory',
        'headline'    => 'ACCS Inventory Reporting',
        'lead'        => 'Live inventory quantities by SKU and source from Adobe Commerce as a Cloud Service.',
        'capabilities' => [
            ['title' => 'Source Items', 'desc' => 'View MSI source-item quantities across ACCS inventory sources.'],
            ['title' => 'SKU Availability', 'desc' => 'See in-stock and out-of-stock status per SKU and source.'],
            ['title' => 'Environment Aware', 'desc' => 'Reads from the configured ACCS stage, dev, or production tenant.'],
            ['title' => 'Operations Alignment', 'desc' => 'Complement Jazz OMS facility inventory with commerce source stock.'],
        ],
    ],
    'inventory-balances' => [
        'label'       => 'Inventory',
        'headline'    => 'Inventory Balances',
        'lead'        => 'Operational inventory on hand from the NutraAxis IMS ledger — SKU × facility × status bucket with company-wide QBO valuation.',
        'capabilities' => [
            ['title' => 'Live Balances', 'desc' => 'Current OK, quarantine, on-hold, destroy, and reserved quantities per SKU and facility.'],
            ['title' => 'Facility Filter', 'desc' => 'Focus on Cart.com, CPPC, White Label, or transit locations as they come online.'],
            ['title' => 'QBO Rollup', 'desc' => 'Company-wide quantity for QuickBooks valuation (OK + quarantine + on hold).'],
            ['title' => 'Ledger Foundation', 'desc' => 'Read-only view of InvCurrentBalance; movements post through InvTransaction in later phases.'],
        ],
    ],
    'inventory-transfers' => [
        'label'       => 'Inventory',
        'headline'    => 'Facility Transfers',
        'lead'        => 'Request and complete hub-and-spoke inventory transfers between Cart.com and spoke facilities.',
        'capabilities' => [
            ['title' => 'Hub and Spoke', 'desc' => 'Replenish CPPC and White Label from Cart.com only.'],
            ['title' => 'Transit Bucket', 'desc' => 'Optional TRANSIT facility for in-flight quantities.'],
            ['title' => 'IMS Posting', 'desc' => 'Ship and receive posts TransferOut / TransferIn to the ledger.'],
            ['title' => 'QBO Valuation', 'desc' => 'Same-SKU transfers keep company QtyOnHand unchanged; G/L moves via Journal Entry when configured.'],
        ],
    ],
    'inventory-adjustments' => [
        'label'       => 'Inventory',
        'headline'    => 'Inventory Adjustments',
        'lead'        => 'Pending shrink and gain requests that post to IMS and QuickBooks InventoryAdjustment on approval.',
        'capabilities' => [
            ['title' => 'Shrink / Gain', 'desc' => 'Signed quantity change against OK, quarantine, on hold, or destroy buckets.'],
            ['title' => 'Approval Workflow', 'desc' => 'Create as Pending; approve to post IMS AdjustmentLoss/Gain + QBO QtyDiff.'],
            ['title' => 'Reason Codes', 'desc' => 'DAMAGE, COUNT_VAR, QUAR_RELEASE, and OTHER_ADJ from InvReasonCode.'],
            ['title' => 'Idempotent QBO', 'desc' => 'DocNumber NA-ADJ-{id} in QBOInventorySyncLog; Error rows can retry.'],
        ],
    ],
    'inventory-jazz-ims-recon' => [
        'label'       => 'Inventory',
        'headline'    => 'Jazz vs IMS CART',
        'lead'        => 'Mothership balance pair — Jazz physical on-hand versus NutraAxis IMS CART ledger.',
        'capabilities' => [
            ['title' => 'CART Alias Aware', 'desc' => 'Maps Jazz facility codes via Facility.ExternalReferenceCode (e.g. FBF09 → CART).'],
            ['title' => 'Physical On Hand', 'desc' => 'Compares Jazz on_hand_quantity to IMS OK + quarantine + on hold.'],
            ['title' => 'Mismatch Focus', 'desc' => 'Filter to SKUs present on only one side or with quantity deltas.'],
            ['title' => 'Prod / UAT Toggle', 'desc' => 'Switch Jazz environment without leaving the recon view.'],
        ],
    ],
    'inventory-qbo-recon' => [
        'label'       => 'Inventory',
        'headline'    => 'QBO Inventory Reconciliation',
        'lead'        => 'Compare NutraAxis IMS company-wide quantity with QuickBooks Online Qty on hand by SKU.',
        'capabilities' => [
            ['title' => 'IMS vs QBO', 'desc' => 'Side-by-side company totals for each SKU.'],
            ['title' => 'Mismatch Focus', 'desc' => 'Highlight rows where ledger and QuickBooks disagree.'],
            ['title' => 'Sandbox Safe', 'desc' => 'Built against QBO Sandbox during UAT.'],
            ['title' => 'Cutover Ready', 'desc' => 'Use before promoting inventory sync to production QuickBooks.'],
        ],
    ],
    'inventory-movement-recon' => [
        'label'       => 'Inventory',
        'headline'    => 'Inventory Movement Completeness',
        'lead'        => 'Layer 1 recon of the inventory cycle — exceptions where source movements are incomplete in IMS or QBO.',
        'capabilities' => [
            ['title' => 'Receipt Gaps', 'desc' => 'Jazz-received PORs missing IMS posts or QBO InventoryAdjustment (+qty).'],
            ['title' => 'Sales Gaps', 'desc' => 'Shipped ACCS lines missing IMS sale or QBO InventoryAdjustment (−qty).'],
            ['title' => 'Transfer Gaps', 'desc' => 'Ship/receive transfers missing outbound/inbound IMS txns or failed JEs.'],
            ['title' => 'Adjustment Queue', 'desc' => 'Pending or approved-unposted shrink/gain adjustments awaiting workflow.'],
        ],
    ],
    'inventory-reconciliation' => [
        'label'       => 'Inventory',
        'headline'    => 'Inventory Reconciliation (Jazz-ACCS)',
        'lead'        => 'Read-only side-by-side view of Jazz OMS and Adobe Commerce inventory for the same SKU.',
        'capabilities' => [
            ['title' => 'Unified SKU View', 'desc' => 'See Jazz facility quantities and ACCS commerce quantities on one row.'],
            ['title' => 'Mismatch Highlighting', 'desc' => 'Rows highlight when Jazz available quantity differs from ACCS quantity.'],
            ['title' => 'Dual-System Coverage', 'desc' => 'Includes SKUs present in only Jazz or only ACCS for full reconciliation.'],
            ['title' => 'Read-Only Compare', 'desc' => 'Use alongside the individual Jazz and ACCS inventory reports for investigation.'],
        ],
    ],
    'inventory-reporting' => [
        'label'       => 'Inventory',
        'headline'    => 'Jazz Current Inventory',
        'lead'        => 'Real-time Jazz OMS visibility into stock on hand and availability by SKU and facility.',
        'capabilities' => [
            ['title' => 'Stock on Hand', 'desc' => 'View current quantities by SKU, lot, and warehouse location.'],
            ['title' => 'Movement History', 'desc' => 'Trace receipts, transfers, adjustments, and shipments over any date range.'],
            ['title' => 'Aging & Expiry', 'desc' => 'Identify slow-moving inventory and lots approaching expiration.'],
            ['title' => 'Export & Share', 'desc' => 'Download reports for finance, operations, and compliance reviews.'],
        ],
    ],
    'sales-reporting' => [
        'label'       => 'Sales',
        'headline'    => 'Sales Reporting Summaries',
        'lead'        => 'ACCS order lookup plus daily and monthly SKU sales summary tables populated by scheduled background jobs.',
        'capabilities' => [
            ['title' => 'ACCS Order Report', 'desc' => 'Search and view Adobe Commerce orders with line-item detail.'],
            ['title' => 'Daily Sales Summary', 'desc' => 'SKU quantities sold per day from the nightly ACCS rollup.'],
            ['title' => 'Monthly Sales Summary', 'desc' => 'SKU quantities sold per month for forecasting and trend analysis.'],
        ],
    ],
    'accs-order-report' => [
        'label'       => 'Sales',
        'headline'    => 'ACCS Order Report',
        'lead'        => 'Browse and search Adobe Commerce (ACCS) orders with customer, totals, and line-item detail.',
        'capabilities' => [
            ['title' => 'Order Search', 'desc' => 'Look up any order by increment ID.'],
            ['title' => 'Recent Orders', 'desc' => 'Browse the most recent ACCS orders in the connected environment.'],
            ['title' => 'Line Items', 'desc' => 'View SKU, quantity, and pricing on each order.'],
        ],
    ],
    'jazz-order-report' => [
        'label'       => 'Sales',
        'headline'    => 'Jazz Order Report',
        'lead'        => 'Browse and search Jazz OMS orders with customer, status, and line-item detail.',
        'capabilities' => [
            ['title' => 'Order Search', 'desc' => 'Look up any order by Jazz order number.'],
            ['title' => 'Recent Orders', 'desc' => 'Browse orders from the connected Jazz OMS environment.'],
            ['title' => 'Line Items', 'desc' => 'View SKU and quantity on each ship-to line.'],
        ],
    ],
    'sales-daily-summary' => [
        'label'       => 'Sales',
        'headline'    => 'Daily Sales Summary',
        'lead'        => 'Daily SKU quantity totals written by the nightly daily sales summary job from ACCS orders.',
        'capabilities' => [
            ['title' => 'By Summary Date', 'desc' => 'Filter rows by the Central Time sales date.'],
            ['title' => 'SKU Detail', 'desc' => 'See SKU name, description, and quantity sold per day.'],
        ],
    ],
    'sales-monthly-summary' => [
        'label'       => 'Sales',
        'headline'    => 'Monthly Sales Summary',
        'lead'        => 'Monthly SKU quantity totals rolled up from daily sales for forecasting and reporting.',
        'capabilities' => [
            ['title' => 'By Month', 'desc' => 'Filter by year and month.'],
            ['title' => 'SKU Totals', 'desc' => 'Total quantity sold per SKU for each calendar month.'],
        ],
    ],
    'inventory-forecasting' => [
        'label'       => 'Planning',
        'headline'    => 'Inventory Forecasting',
        'lead'        => 'Project future demand using historical sales, seasonality, and lead times to keep the right products in stock.',
        'capabilities' => [
            ['title' => 'Demand Projections', 'desc' => 'Generate SKU-level forecasts based on trailing sales and growth trends.'],
            ['title' => 'Reorder Recommendations', 'desc' => 'Get suggested order quantities aligned to supplier lead times.'],
            ['title' => 'Scenario Planning', 'desc' => 'Model best-case and conservative stocking levels before placing orders.'],
            ['title' => 'Safety Stock Alerts', 'desc' => 'Flag items at risk of stockout before customer demand is affected.'],
        ],
    ],
    'labeling-operations' => [
        'label'       => 'Compliance',
        'headline'    => 'Custom Order Fulfillment Operations',
        'lead'        => 'Manage supplement label templates, print batches, compliance checks, version history, white label production orders, and One-A-Day pack fulfillment.',
        'capabilities' => [
            ['title' => 'Label Templates', 'desc' => 'Track labels for each customer and SKU, plus internal label definitions.'],
            ['title' => 'Label Batch Printing', 'desc' => 'Track third-party print orders associated with label order runs.'],
            ['title' => 'Label Compliance Review', 'desc' => 'Log approvals and review activity for batch printing and label order production.'],
            ['title' => 'Label Version Control', 'desc' => 'Track label revisions for customer and internal labels.'],
            ['title' => 'White Label Production Order', 'desc' => 'Track production orders received from Adobe Commerce with header and line detail.'],
            ['title' => 'One-A-Day Pack Batch Order PO', 'desc' => 'Manage purchase orders for One-A-Day pack batch production runs.'],
            ['title' => 'One-A-Day Pack Inventory', 'desc' => 'View on-hand and available One-A-Day pack inventory by SKU.'],
            ['title' => 'One-A-Day Pack Demand', 'desc' => 'Review projected and actual demand for One-A-Day pack SKUs.'],
        ],
    ],
    'coa-management' => [
        'label'       => 'Quality',
        'headline'    => 'Manage our COAs',
        'lead'        => 'Upload Certificates of Analysis PDFs and control which records appear on the public nutraaxislabs.com COA table.',
        'capabilities' => [
            ['title' => 'COA PDF Upload', 'desc' => 'Store COA PDFs in Azure Blob with automatic ProductName+LotNumber naming.'],
            ['title' => 'Publish Control', 'desc' => 'Use the Publish flag to include or exclude COAs from the public marketing page.'],
            ['title' => 'Public API', 'desc' => 'Published COAs are served to nutraaxislabs.com/our-coas via JSON API.'],
        ],
    ],
    'system-performance-dashboard' => [
        'label'       => 'Monitoring',
        'headline'    => 'System Performance Dashboard',
        'lead'        => 'Planned hub for Geckoboard, IT infrastructure monitoring, and Zendesk support totals.',
        'capabilities' => [
            ['title' => 'Geckoboard', 'desc' => 'Live KPI boards for operations and commerce metrics.'],
            ['title' => 'IT Systems', 'desc' => 'Health and performance monitoring for NutraAxis infrastructure.'],
            ['title' => 'Zendesk Totals', 'desc' => 'Support ticket volume and queue summaries.'],
            ['title' => 'Coming Soon', 'desc' => 'Dashboard links will be added as monitoring integrations are configured.'],
        ],
    ],
    'operations-dashboard' => [
        'label'       => 'Overview',
        'headline'    => 'Operations Dashboard',
        'lead'        => 'One-click shortcuts to Microsoft 365, QuickBooks, Adobe Commerce, Jazz support, and Zendesk.',
        'capabilities' => [
            ['title' => 'Issues and Actions', 'desc' => 'SharePoint list for open issues, owners, and follow-up actions.'],
            ['title' => 'Planner', 'desc' => 'Microsoft Planner premium plan for NutraAxis tasks and schedules.'],
            ['title' => 'Document Library', 'desc' => 'SharePoint files and shared documents for the NutraAxis team.'],
            ['title' => 'QuickBooks', 'desc' => 'QuickBooks Online accountant access.'],
            ['title' => 'Adobe Commerce', 'desc' => 'ACCS admin, authoring, assets, and staging storefront.'],
            ['title' => 'Support', 'desc' => 'Cart.com help desk and Zendesk agent dashboard.'],
            ['title' => 'Lucid Chart', 'desc' => 'Lucid diagrams and visual collaboration documents.'],
            ['title' => 'Azure Portal', 'desc' => 'Microsoft Azure cloud resources and services.'],
        ],
    ],
    'legal-agreements' => [
        'label'       => 'Legal',
        'headline'    => 'Legal Agreements & Contracts',
        'lead'        => 'Centralize legal agreements and contracts with counterparties, effective dates, and renewal tracking.',
        'capabilities' => [
            ['title' => 'Agreement Repository', 'desc' => 'Store quality agreements, supply agreements, NDAs, and other legal documents.'],
            ['title' => 'Contract Tracking', 'desc' => 'Track active contracts by supplier, customer, and business unit.'],
            ['title' => 'Renewal Alerts', 'desc' => 'Monitor expiration and renewal dates before agreements lapse.'],
            ['title' => 'Version History', 'desc' => 'Maintain a record of executed versions and amendments.'],
        ],
    ],
    'product-catalog' => [
        'label'       => 'Products',
        'headline'    => 'Product SKU Master',
        'lead'        => 'Single source of truth for SKUs, product attributes, and catalog data used across operations.',
        'capabilities' => [
            ['title' => 'SKU Master', 'desc' => 'Define and maintain SKU codes, descriptions, and product families.'],
            ['title' => 'Product Attributes', 'desc' => 'Track formulation, packaging, regulatory, and commercial attributes per SKU.'],
            ['title' => 'Catalog Search', 'desc' => 'Find products quickly by SKU, name, customer, or category.'],
            ['title' => 'Cross-References', 'desc' => 'Link SKUs to PO lines, labels, inventory, and commerce product IDs.'],
        ],
    ],
    'product-enrichment' => [
        'label'       => 'Products',
        'headline'    => 'Product Page Enrichment',
        'lead'        => 'Manage product detail page HTML and information sheet PDFs served dynamically on nutraaxislabs.com.',
        'capabilities' => [
            ['title' => 'PDP HTML', 'desc' => 'Store the enrichment block HTML for each product SKU.'],
            ['title' => 'Information Sheet PDF', 'desc' => 'Upload one-page product information PDFs to blob storage.'],
            ['title' => 'Publish Control', 'desc' => 'Choose which SKUs appear on the public marketing site.'],
        ],
    ],
    'supplier-management' => [
        'label'       => 'Procurement',
        'headline'    => 'Supplier Management',
        'lead'        => 'Maintain supplier profiles, contacts, lead times, and procurement relationships used across purchase orders and inventory planning.',
        'capabilities' => [
            ['title' => 'Supplier Directory', 'desc' => 'Central list of active vendors, manufacturers, and service providers.'],
            ['title' => 'Contact & Terms', 'desc' => 'Track primary contacts, payment terms, and ordering requirements.'],
            ['title' => 'Performance History', 'desc' => 'Review fulfillment, lead times, and quality metrics by supplier.'],
            ['title' => 'PO Cross-Reference', 'desc' => 'Link suppliers to open and historical purchase orders.'],
        ],
    ],
    'links-index' => [
        'label'       => 'Resources',
        'headline'    => 'Links Index',
        'lead'        => 'Browse and manage curated shortcuts to web applications, Microsoft 365 apps, documents, and external reference sites.',
        'capabilities' => [
            ['title' => 'Web Applications', 'desc' => 'Internal and external web apps used across operations.'],
            ['title' => 'Microsoft 365', 'desc' => 'SharePoint, Teams, and other MS365 application shortcuts.'],
            ['title' => 'Documents & References', 'desc' => 'Shared documents, SOPs, and external reference websites.'],
            ['title' => 'Category Filters', 'desc' => 'Filter by category, status, and search across the full index.'],
        ],
    ],
    'support' => [
        'label'       => 'Help',
        'headline'    => 'Support',
        'lead'        => 'View Zendesk tickets, create support requests, and follow conversations without leaving NutraAxis Operations.',
        'capabilities' => [
            ['title' => 'Zendesk Ticket List', 'desc' => 'Browse open and historical support tickets with status and priority filters.'],
            ['title' => 'Create Tickets', 'desc' => 'Submit new support requests directly into Zendesk from your NutraAxis account.'],
            ['title' => 'Ticket Conversations', 'desc' => 'Read full comment threads and post replies on tickets you have access to.'],
            ['title' => 'Role-Based Access', 'desc' => 'Support role with Read shows requester tickets only; Update enables agent actions in Operations.'],
        ],
    ],
    'accounting' => [
        'label'       => 'Finance',
        'headline'    => 'QuickBooks Online',
        'lead'        => 'Connect QuickBooks Online, manage supplier invoices and invoice payments, and browse AP, AR, purchase orders, inventory, suppliers, and the chart of accounts.',
        'capabilities' => [
            ['title' => 'Supplier Invoices', 'desc' => 'Create vendor invoices with line detail, attachments, and QuickBooks Bill sync status.'],
            ['title' => 'Invoice Payments', 'desc' => 'Record check, ACH, and card payments against supplier invoices without a PO.'],
            ['title' => 'Accounts Payable', 'desc' => 'View vendor bills and outstanding balances from QuickBooks.'],
            ['title' => 'Accounts Receivable', 'desc' => 'View customer invoices and open balances.'],
            ['title' => 'Purchase Orders', 'desc' => 'Browse QuickBooks purchase orders; create and update from Operations is planned.'],
            ['title' => 'Inventory & Suppliers', 'desc' => 'Read inventory items and vendor directory; supplier create/update from Operations is planned.'],
            ['title' => 'QBO Chart of Accounts', 'desc' => 'Browse QuickBooks Online general ledger accounts (not Certificate of Analysis).'],
            ['title' => 'QuickBooks Connection', 'desc' => 'Users with Update access connect and disconnect the QuickBooks Online company.'],
        ],
    ],
];

function app_hub_slugs(): array
{
    return [
        'product-master',
        'inventory-management',
        'procurement',
        'inbound-receiving',
        'sales-reporting',
    ];
}

/**
 * Modules kept in the codebase / app.php registry but hidden from portal
 * menus and cards until the feature is ready to ship.
 */
function app_nav_hidden_module_slugs(): array
{
    return [
        'travel-expense',
        'jazz-order-report',
        'jazz-order-report-uat',
    ];
}

function app_nav_hidden_approval_types(): array
{
    return ['TE'];
}

function app_module_nav_hidden(string $slug): bool
{
    return in_array($slug, app_nav_hidden_module_slugs(), true);
}

function app_approval_type_nav_hidden(string $approvalType): bool
{
    return in_array($approvalType, app_nav_hidden_approval_types(), true);
}

function app_product_master_submodules(): array
{
    global $productMasterSubModules;

    return $productMasterSubModules;
}

function app_inventory_management_submodules(): array
{
    global $inventoryManagementSubModules;

    return $inventoryManagementSubModules;
}

function app_procurement_submodules(): array
{
    global $procurementSubModules;

    return $procurementSubModules;
}

function app_inbound_receiving_submodules(): array
{
    global $inboundReceivingSubModules;

    return $inboundReceivingSubModules;
}

function app_hub_submodules(string $hubSlug): array
{
    return match ($hubSlug) {
        'product-master'        => app_product_master_submodules(),
        'inventory-management'  => app_inventory_management_submodules(),
        'procurement'           => app_procurement_submodules(),
        'inbound-receiving'     => app_inbound_receiving_submodules(),
        'sales-reporting'       => app_sales_submodules(),
        default                 => [],
    };
}

function app_all_leaf_module_definitions(): array
{
    return array_merge(
        app_product_master_submodules(),
        app_inventory_management_submodules(),
        app_procurement_submodules(),
        app_inbound_receiving_submodules(),
        app_sales_submodules(),
    );
}

function app_hub_for_module_slug(string $moduleSlug): ?array
{
    foreach (app_functions() as $hub) {
        if (!in_array($hub['slug'], app_hub_slugs(), true)) {
            continue;
        }

        foreach (app_hub_submodules($hub['slug']) as $child) {
            if (($child['slug'] ?? '') === $moduleSlug) {
                return $hub;
            }
        }
    }

    return null;
}

function app_module_hub_back_link(string $moduleSlug): array
{
    $hub = app_hub_for_module_slug($moduleSlug);

    return [
        'href'  => $hub !== null ? (string) $hub['href'] : '/',
        'label' => $hub !== null
            ? 'Back to ' . (string) ($hub['title'] ?? 'Applications')
            : 'Back to Operations Home',
    ];
}

function app_inventory_submodules(): array
{
    return app_all_leaf_module_definitions();
}

function app_inventory_submodule_slugs(): array
{
    return array_map(
        fn(array $item): string => $item['slug'],
        app_all_leaf_module_definitions()
    );
}

function app_functions(): array
{
    global $appFunctions;

    return $appFunctions;
}

function app_function_groups(): array
{
    return [
        'supply-chain' => [
            'title' => 'Supply Chain',
            'desc'  => 'Product master data through order fulfillment — inventory, procurement, receiving, sales reporting, and labeling.',
        ],
        'admin' => [
            'title' => 'Administration',
            'desc'  => 'Finance, legal, operations shortcuts, support, and system monitoring.',
        ],
    ];
}

function app_functions_grouped(array $modules): array
{
    $groups = app_function_groups();
    $bucketed = array_fill_keys(array_keys($groups), []);

    foreach ($modules as $module) {
        $groupKey = (string) ($module['group'] ?? '');
        if (isset($bucketed[$groupKey])) {
            $bucketed[$groupKey][] = $module;
        }
    }

    $grouped = [];
    foreach ($groups as $key => $meta) {
        if ($bucketed[$key] === []) {
            continue;
        }

        $grouped[] = array_merge($meta, [
            'key'     => $key,
            'modules' => $bucketed[$key],
        ]);
    }

    return $grouped;
}

function account_links(): array
{
    global $accountLinks;

    return $accountLinks;
}

function app_sales_submodules(): array
{
    global $salesReportingSubModules;

    return $salesReportingSubModules;
}

function app_sales_submodule_slugs(): array
{
    return array_map(
        fn(array $item): string => $item['slug'],
        app_sales_submodules()
    );
}

function get_module(string $slug): ?array
{
    global $appFunctions, $modulePages;

    foreach ($appFunctions as $fn) {
        if ($fn['slug'] === $slug) {
            return array_merge($fn, $modulePages[$slug] ?? []);
        }
    }

    foreach (app_all_leaf_module_definitions() as $fn) {
        if ($fn['slug'] === $slug) {
            return array_merge($fn, $modulePages[$slug] ?? []);
        }
    }

    return null;
}
