<?php

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

$inventorySubModules = [
    [
        'slug'  => 'inventory-reporting',
        'title' => 'Jazz Current Inventory',
        'desc'  => 'Jazz OMS stock levels by SKU and facility.',
        'href'  => '/inventory-reporting/',
        'icon'  => 'boxes',
        'tier'  => ENVIRONMENT_TIER_PRODUCTION,
    ],
    [
        'slug'  => 'jazz-item-master',
        'title' => 'Jazz Item Master',
        'desc'  => 'SKU and item reference data synced from Jazz OMS.',
        'href'  => '/jazz-item-master/',
        'icon'  => 'catalog',
        'tier'  => ENVIRONMENT_TIER_PRODUCTION,
    ],
    [
        'slug'  => 'accs-inventory-reporting',
        'title' => 'ACCS Inventory Reporting',
        'desc'  => 'Adobe Commerce (ACCS) inventory by SKU and source.',
        'href'  => '/accs-inventory-reporting/',
        'icon'  => 'chart',
        'tier'  => ENVIRONMENT_TIER_PRODUCTION,
    ],
    [
        'slug'  => 'inventory-reconciliation',
        'title' => 'Inventory Reconciliation (Jazz-ACCS)',
        'desc'  => 'Compare Jazz OMS and ACCS inventory levels for the same SKU.',
        'href'  => '/inventory-reconciliation/',
        'icon'  => 'trend',
        'tier'  => ENVIRONMENT_TIER_PRODUCTION,
    ],
    [
        'slug'  => 'jazz-orders',
        'title' => 'Jazz Orders',
        'desc'  => 'Browse the Jazz OMS order queue and fulfillment status report.',
        'href'  => '/jazz-orders/',
        'icon'  => 'clipboard',
        'tier'  => ENVIRONMENT_TIER_PRODUCTION,
    ],
    [
        'slug'  => 'inventory-forecasting',
        'title' => 'Inventory Forecasting',
        'desc'  => 'Project demand and plan replenishment with confidence.',
        'href'  => '/inventory-demand/',
        'icon'  => 'trend',
        'tier'  => ENVIRONMENT_TIER_PRODUCTION,
    ],
    [
        'slug'  => 'po-management',
        'title' => 'PO Management',
        'desc'  => 'Create, track, and manage purchase orders across suppliers.',
        'href'  => '/po-management/',
        'icon'  => 'clipboard',
        'tier'  => ENVIRONMENT_TIER_PRODUCTION,
    ],
    [
        'slug'  => 'po-receiving',
        'title' => 'PO Receiving',
        'desc'  => 'Advanced shipping notices for inbound shipments, expected receipts, and PO receiving.',
        'href'  => '/po-receiving/',
        'icon'  => 'boxes',
        'tier'  => ENVIRONMENT_TIER_PRODUCTION,
    ],
    [
        'slug'  => 'po-payments',
        'title' => 'PO Payments',
        'desc'  => 'Record and track payments made against purchase orders.',
        'href'  => '/po-payments/',
        'icon'  => 'payment',
        'tier'  => ENVIRONMENT_TIER_PRODUCTION,
    ],
    [
        'slug'  => 'product-catalog',
        'title' => 'Product SKU Master',
        'desc'  => 'Maintain the master product catalog and SKU reference data.',
        'href'  => '/product-catalog/',
        'icon'  => 'catalog',
        'tier'  => ENVIRONMENT_TIER_PRODUCTION,
    ],
    [
        'slug'  => 'supplier-management',
        'title' => 'Supplier Management',
        'desc'  => 'Maintain supplier profiles, contacts, and procurement relationships.',
        'href'  => '/supplier-management/',
        'icon'  => 'supplier',
        'tier'  => ENVIRONMENT_TIER_PRODUCTION,
    ],
    [
        'slug'  => 'jazz-asns',
        'title' => 'Jazz ASNs',
        'desc'  => 'Browse advanced shipping notices synced from Jazz OMS.',
        'href'  => '/po-receiving/jazz-asns.php',
        'icon'  => 'document',
        'tier'  => ENVIRONMENT_TIER_PRODUCTION,
    ],
    [
        'slug'  => 'delivery-scheduling-log',
        'title' => 'Delivery Scheduling Log',
        'desc'  => 'Track inbound delivery appointments and scheduling updates.',
        'href'  => '/delivery-scheduling-log/',
        'icon'  => 'calendar',
        'tier'  => ENVIRONMENT_TIER_PRODUCTION,
    ],
    [
        'slug'  => 'inventory-reporting-uat',
        'title' => 'Jazz Current Inventory',
        'desc'  => 'UAT System Jazz OMS stock levels by SKU and facility.',
        'href'  => '/inventory-reporting-uat/',
        'icon'  => 'boxes',
        'tier'  => ENVIRONMENT_TIER_UAT,
    ],
    [
        'slug'  => 'jazz-item-master-uat',
        'title' => 'Jazz Item Master',
        'desc'  => 'UAT System SKU and item reference data synced from Jazz OMS.',
        'href'  => '/jazz-item-master-uat/',
        'icon'  => 'catalog',
        'tier'  => ENVIRONMENT_TIER_UAT,
    ],
    [
        'slug'  => 'accs-inventory-reporting-uat',
        'title' => 'ACCS Inventory Reporting',
        'desc'  => 'UAT System Adobe Commerce (ACCS) inventory by SKU and source.',
        'href'  => '/accs-inventory-reporting-uat/',
        'icon'  => 'chart',
        'tier'  => ENVIRONMENT_TIER_UAT,
    ],
    [
        'slug'  => 'inventory-reconciliation-uat',
        'title' => 'Inventory Reconciliation (Jazz-ACCS)',
        'desc'  => 'UAT System Compare Jazz OMS and ACCS inventory levels for the same SKU.',
        'href'  => '/inventory-reconciliation-uat/',
        'icon'  => 'trend',
        'tier'  => ENVIRONMENT_TIER_UAT,
    ],
    [
        'slug'  => 'jazz-asns-uat',
        'title' => 'Jazz ASNs — UAT',
        'desc'  => 'UAT System Browse advanced shipping notices synced from Jazz OMS.',
        'href'  => '/po-receiving/jazz-asns-uat.php',
        'icon'  => 'document',
        'tier'  => ENVIRONMENT_TIER_UAT,
    ],
];

$salesReportingSubModules = [
    [
        'slug'  => 'accs-order-report',
        'title' => 'ACCS Order Report',
        'desc'  => 'Browse and search Adobe Commerce (ACCS) orders and order detail.',
        'href'  => '/sales-reporting/accs-order-report/',
        'icon'  => 'clipboard',
        'tier'  => ENVIRONMENT_TIER_PRODUCTION,
    ],
    [
        'slug'  => 'sales-daily-summary',
        'title' => 'Daily Sales Summary',
        'desc'  => 'Daily SKU quantity totals rolled up from ACCS orders.',
        'href'  => '/sales-reporting/daily-sales-summary/',
        'icon'  => 'chart',
        'tier'  => ENVIRONMENT_TIER_PRODUCTION,
    ],
    [
        'slug'  => 'sales-monthly-summary',
        'title' => 'Monthly Sales Summary',
        'desc'  => 'Monthly SKU quantity totals materialized from daily sales.',
        'href'  => '/sales-reporting/monthly-sales-summary/',
        'icon'  => 'trend',
        'tier'  => ENVIRONMENT_TIER_PRODUCTION,
    ],
    [
        'slug'  => 'accs-order-report-uat',
        'title' => 'ACCS Order Report',
        'desc'  => 'UAT System Browse and search Adobe Commerce (ACCS) orders and order detail.',
        'href'  => '/sales-reporting/accs-order-report-uat/',
        'icon'  => 'clipboard',
        'tier'  => ENVIRONMENT_TIER_UAT,
    ],
];

$appFunctions = [
    [
        'slug'  => 'inventory-management',
        'title' => 'Supply Chain Management',
        'desc'  => 'Inventory reporting, forecasting, suppliers, SKU master, and purchase orders.',
        'href'  => '/inventory-management/',
        'icon'  => 'inventory',
        'tier'  => ENVIRONMENT_TIER_HUB,
    ],
    [
        'slug'  => 'legal-agreements',
        'title' => 'Legal Agreements & Contracts',
        'desc'  => 'Store and track legal agreements, contracts, and renewal dates.',
        'href'  => '/legal-agreements/',
        'icon'  => 'document',
        'tier'  => ENVIRONMENT_TIER_PRODUCTION,
    ],
    [
        'slug'  => 'sales-reporting',
        'title' => 'Sales Reporting Summaries',
        'desc'  => 'ACCS order lookup, daily sales, and monthly sales summary tables.',
        'href'  => '/sales-reporting/',
        'icon'  => 'chart',
        'tier'  => ENVIRONMENT_TIER_HUB,
    ],
    [
        'slug'  => 'labeling-operations',
        'title' => 'Custom Order Fulfillment Operations',
        'desc'  => 'Manage label templates, batches, and compliance workflows.',
        'href'  => '/labeling-operations/',
        'icon'  => 'tag',
        'tier'  => ENVIRONMENT_TIER_HUB,
    ],
    [
        'slug'  => 'operations-dashboard',
        'title' => 'Operations Dashboard',
        'desc'  => 'Shortcuts to Microsoft 365, QuickBooks, Adobe Commerce, and support tools.',
        'href'  => '/operations-dashboard/',
        'icon'  => 'dashboard',
        'tier'  => ENVIRONMENT_TIER_HUB,
    ],
    [
        'slug'  => 'system-performance-dashboard',
        'title' => 'System Performance Dashboard',
        'desc'  => 'IT system monitoring, Geckoboard dashboards, and Zendesk totals — coming soon.',
        'href'  => '/system-performance-dashboard/',
        'icon'  => 'trend',
        'tier'  => ENVIRONMENT_TIER_HUB,
    ],
    [
        'slug'  => 'support',
        'title' => 'Support',
        'desc'  => 'View Zendesk tickets, create requests, and manage support conversations.',
        'href'  => '/support/',
        'icon'  => 'support',
        'tier'  => ENVIRONMENT_TIER_PRODUCTION,
    ],
    [
        'slug'  => 'accounting',
        'title' => 'Accounting',
        'desc'  => 'Read-only QuickBooks Online views for AP, AR, POs, inventory, suppliers, and chart of accounts.',
        'href'  => '/accounting/',
        'icon'  => 'accounting',
        'tier'  => ENVIRONMENT_TIER_HUB,
    ],
];

$accountLinks = [
    ['title' => 'Site Admin', 'href' => '/site-admin/'],
    ['title' => 'My Account', 'href' => '/my-account/'],
    ['title' => 'Log Out',   'href' => '/logout/', 'class' => 'nav-logout'],
];

$modulePages = [
    'inventory-management' => [
        'label'       => 'Inventory',
        'headline'    => 'Supply Chain Management',
        'lead'        => 'Central hub for inventory reporting, demand forecasting, supplier records, SKU master data, and purchase order workflows.',
        'capabilities' => [
            ['title' => 'Jazz Current Inventory', 'desc' => 'Jazz OMS stock on hand by SKU and facility.'],
            ['title' => 'Jazz Item Master', 'desc' => 'SKU and item reference data synced from Jazz OMS.'],
            ['title' => 'Jazz Orders', 'desc' => 'Order queue and fulfillment status from Jazz OMS.'],
            ['title' => 'ACCS Inventory Reporting', 'desc' => 'Adobe Commerce inventory by SKU and source.'],
            ['title' => 'Inventory Forecasting', 'desc' => 'Project demand and plan replenishment with confidence.'],
            ['title' => 'Supplier Management', 'desc' => 'Maintain supplier profiles and procurement relationships.'],
            ['title' => 'Product SKU Master', 'desc' => 'Define and maintain SKU codes, attributes, and catalog data.'],
            ['title' => 'PO Management', 'desc' => 'Create, approve, and track purchase orders across suppliers.'],
            ['title' => 'PO Payments', 'desc' => 'Record checks, ACH, and card payments against purchase orders.'],
            ['title' => 'PO Receiving', 'desc' => 'Advanced shipping notices for inbound shipments and expected receipts.'],
            ['title' => 'Jazz ASNs', 'desc' => 'Browse advanced shipping notices synced from Jazz OMS.'],
            ['title' => 'Delivery Scheduling Log', 'desc' => 'Track inbound delivery appointments and scheduling updates.'],
        ],
    ],
    'po-receiving' => [
        'label'       => 'Supply Chain',
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
        'lead'        => 'Track payments applied to purchase orders, including payment type, confirmation numbers, and payer details.',
        'capabilities' => [
            ['title' => 'Payment Register', 'desc' => 'View all payments across purchase orders in one list.'],
            ['title' => 'Record Payments', 'desc' => 'Log check, ACH, and credit card payments against open POs.'],
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
    'jazz-orders' => [
        'label'       => 'Supply Chain',
        'headline'    => 'Jazz Orders',
        'lead'        => 'Order queue and fulfillment status from Jazz OMS production APIs.',
        'capabilities' => [
            ['title' => 'Order Queue', 'desc' => 'Filter by NEW, ALLOCATED, PRINTED, and other Jazz order statuses.'],
            ['title' => 'Order Search', 'desc' => 'Look up orders by order number, PO number, or date range.'],
            ['title' => 'Line Detail', 'desc' => 'View SKU-level quantities ordered, allocated, and shipped.'],
            ['title' => 'Production Jazz', 'desc' => 'Reads from the configured production Jazz OMS tenant.'],
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
        'headline'    => 'Accounting',
        'lead'        => 'Connect QuickBooks Online and browse accounts payable, receivable, purchase orders, inventory, suppliers, and the chart of accounts.',
        'capabilities' => [
            ['title' => 'Accounts Payable', 'desc' => 'View vendor bills and outstanding balances from QuickBooks.'],
            ['title' => 'Accounts Receivable', 'desc' => 'View customer invoices and open balances.'],
            ['title' => 'Purchase Orders', 'desc' => 'Browse QuickBooks purchase orders; create and update from Operations is planned.'],
            ['title' => 'Inventory & Suppliers', 'desc' => 'Read inventory items and vendor directory; supplier create/update from Operations is planned.'],
            ['title' => 'Chart of Accounts', 'desc' => 'Browse general ledger accounts and current balances.'],
            ['title' => 'QuickBooks Connection', 'desc' => 'Users with Update access connect and disconnect the QuickBooks Online company.'],
        ],
    ],
];

function app_inventory_submodules(): array
{
    global $inventorySubModules;

    return $inventorySubModules;
}

function app_inventory_submodule_slugs(): array
{
    return array_map(
        fn(array $item): string => $item['slug'],
        app_inventory_submodules()
    );
}

function app_functions(): array
{
    global $appFunctions;

    return $appFunctions;
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
    global $appFunctions, $inventorySubModules, $salesReportingSubModules, $modulePages;

    foreach ($appFunctions as $fn) {
        if ($fn['slug'] === $slug) {
            return array_merge($fn, $modulePages[$slug] ?? []);
        }
    }

    foreach ($inventorySubModules as $fn) {
        if ($fn['slug'] === $slug) {
            return array_merge($fn, $modulePages[$slug] ?? []);
        }
    }

    foreach ($salesReportingSubModules as $fn) {
        if ($fn['slug'] === $slug) {
            return array_merge($fn, $modulePages[$slug] ?? []);
        }
    }

    return null;
}
