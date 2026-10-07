<?php

require_once __DIR__ . '/../portal-manual.php';

/**
 * Supply Chain User Manual content.
 * Page index is built from Product Master, Inventory, Procurement, Inbound,
 * Sales Reporting hubs plus standalone Supply Chain home cards.
 */

/** @return list<array<string, mixed>> */
function supply_chain_manual_modules(): array
{
    $modules = [];
    $seen = [];

    $hubs = [
        'product-master',
        'inventory-management',
        'procurement',
        'inbound-receiving',
        'sales-reporting',
    ];
    foreach ($hubs as $hubSlug) {
        foreach (app_hub_submodules($hubSlug) as $child) {
            $slug = (string) ($child['slug'] ?? '');
            if ($slug === '' || isset($seen[$slug])) {
                continue;
            }
            // Prefer production title when the same slug appears in multiple hubs.
            $seen[$slug] = true;
            $modules[] = $child;
        }
    }

    foreach (app_functions() as $fn) {
        if (($fn['group'] ?? '') !== 'supply-chain') {
            continue;
        }
        $slug = (string) ($fn['slug'] ?? '');
        if ($slug === '' || isset($seen[$slug]) || in_array($slug, app_hub_slugs(), true)) {
            continue;
        }
        if ($slug === 'supply-chain-manual') {
            continue;
        }
        $seen[$slug] = true;
        $modules[] = $fn;
    }

    return $modules;
}

function supply_chain_manual_definition(): array
{
    $pageIndex = portal_manual_modules_to_index(supply_chain_manual_modules());

    return [
        'active_slug' => 'supply-chain-manual',
        'category'    => 'Supply Chain',
        'title'       => 'User Manual',
        'lead'        => 'What to do, who does it, when, and where — across product master, inventory, procurement, receiving, sales reporting, labeling, and COAs.',
        'intro'       => 'Supply Chain runs NutraAxis product and inventory operations end to end: maintain SKUs, watch stock across Jazz / ACCS / IMS / QuickBooks, buy and pay suppliers, receive goods, report orders and sales, fulfill custom labels, and publish Certificates of Analysis. Production cards use live data; **(UAT)** cards talk only to stage / sandbox systems.',
        'golden_rules' => [
            '**Production vs UAT.** Never post a Production PO, receipt, invoice, or inventory adjustment from a UAT screen. UAT cards are labeled and use sandbox Jazz, ACCS Stage, and QBO Sandbox.',
            '**SKU Master is the source of truth for NutraAxis SKUs.** Jazz Item Master and QBO Product Master are reference / sync views — fix catalog attributes on SKU Master first.',
            '**IMS ledger drives operational balances.** Facility transfers and adjustments post to IMS; QBO Qty on hand is the financial view and is reconciled, not edited by hand in QBO for day-to-day stock moves.',
            '**Approvals before money moves.** Purchase orders and supplier invoice payment / QBO insert steps need the right approver. Nobody should approve their own payment or QBO insert when dual control is required.',
            '**Receive against the PO.** Record inbound receipts and ASNs so IMS and Jazz stay aligned; don’t invent stock with an adjustment when a receipt is the right movement.',
            '**Times are Central** unless a screen says otherwise.',
        ],
        'access_html' => 'Access is per module (Product Catalog, Inventory Reporting, PO Management, Sales Reporting, Labeling, and so on). If a card is missing, your role lacks that module’s Read permission — ask a site admin.',
        'roles' => [
            [
                'role'   => 'Supply chain / inventory operator',
                'access' => 'Inventory Reporting, Inventory Forecasting, Product Catalog (as assigned)',
                'does'   => 'Watches balances, runs reconciliations, posts transfers and adjustments, reviews Jazz / ACCS / QBO stock, and uses forecasting for replenishment.',
            ],
            [
                'role'   => 'Buyer / procurement',
                'access' => 'PO Management (Create / Update); often Accounting for invoices',
                'does'   => 'Creates POs and supplier invoices, manages suppliers and bids, submits payment requests, and follows Approvals Queue.',
            ],
            [
                'role'   => 'Receiving / warehouse',
                'access' => 'PO Management (receiving modules)',
                'does'   => 'Schedules deliveries, records PO receipts, and reviews Jazz ASNs.',
            ],
            [
                'role'   => 'Sales / ops analyst',
                'access' => 'Sales Reporting',
                'does'   => 'Looks up ACCS and Jazz orders, daily/monthly sales, and sales-rep territory reporting.',
            ],
            [
                'role'   => 'Labeling / compliance ops',
                'access' => 'Labeling Operations',
                'does'   => 'Runs custom order fulfillment labeling and maintains public COA PDFs.',
            ],
            [
                'role'   => 'Approver (PO / payment / QBO insert)',
                'access' => 'Approval rights on the relevant queue',
                'does'   => 'Clears or returns pending POs and supplier invoice payment / QBO insert steps.',
            ],
        ],
        'rhythm_intro' => 'Cadence varies by role. Inventory and receiving are daily; procurement follows open POs and invoices; sales reporting is as needed plus month-end.',
        'rhythm' => [
            ['when' => 'Daily', 'who' => 'Inventory', 'what' => 'Check **Inventory Balances** and **Movement Completeness** for failed or missing IMS/QBO posts.', 'link' => ['inventory-balances', '', null]],
            ['when' => 'Daily', 'who' => 'Receiving', 'what' => 'Update **Delivery Schedule Log** and record **PO Receiving** for arrivals.', 'link' => ['po-receiving', '', null]],
            ['when' => 'Daily', 'who' => 'Buyer / AP', 'what' => 'Clear **Approvals Queue**; advance supplier invoices that are ready for payment or QBO insert.', 'link' => ['procurement-approvals', '', null]],
            ['when' => 'Weekly', 'who' => 'Inventory', 'what' => 'Run **Jazz vs IMS CART**, **QBO Inventory Reconciliation**, and **Inventory Reconciliation** (Jazz vs ACCS); align only when the gap is understood.', 'link' => ['inventory-jazz-ims-recon', '', null]],
            ['when' => 'Weekly', 'who' => 'Buyer', 'what' => 'Review open POs, Initiatives & Bids, and unpaid supplier invoices.', 'link' => ['po-management', '', null]],
            ['when' => 'Month-end', 'who' => 'Ops / finance', 'what' => 'Confirm monthly sales summaries and COA uploads for new lots.', 'link' => ['sales-monthly-summary', '', null]],
        ],
        'workflows' => supply_chain_manual_workflows(),
        'page_index' => $pageIndex,
        'page_guide' => supply_chain_manual_pages(),
        'statuses' => [
            'Purchase order' => ['Draft', 'Submitted', 'Approved / Ordered', 'Partially received', 'Closed / Cancelled'],
            'Supplier invoice' => ['Draft', 'Submitted', 'Payment approval', 'QBO insert', 'Synced / Paid'],
            'Inventory movement' => ['Receipt', 'Sale', 'Transfer', 'Adjustment', 'Jazz sync reconcile'],
            'ASN / receipt' => ['Expected', 'In transit', 'Received', 'Cancelled'],
        ],
        'other_instructions' => [
            'UAT cards' => 'Anything titled **(UAT)** or described as UAT System writes only to stage/sandbox. Use them to rehearse flows before Production.',
            'Hub cards vs leaves' => 'Home **Supply Chain** cards open hubs (Product Master, Inventory Management, Procurement, …). Each hub lists its modules. This manual links straight to the leaf pages.',
            'QuickBooks views' => 'QBO Inventory, QBO Purchase Orders, and QBO Suppliers under Supply Chain hubs are read/sync views of the connected QuickBooks company. Bill creation for vendor spend goes through Supplier Invoices in Procurement / Accounting.',
            'Asking for access' => 'Site Admin → Roles: grant the module columns you need (Product Catalog, Inventory Reporting, PO Management, Sales Reporting, Labeling Operations, Accounting).',
            'Site Documentation' => 'For a full inventory of portal pages and scheduled jobs, see **Site Documentation** under Operations on the home page.',
        ],
        'troubleshooting' => [
            ['A Supply Chain card is missing', 'Your role lacks that module’s Read permission. Ask a site admin.'],
            ['Balances don’t match Jazz or QBO', 'Use Jazz vs IMS CART and QBO Inventory Reconciliation first. Only run Align when you understand the gap and have approval.'],
            ['PO won’t submit or approve', 'Check required fields, supplier link, and that you are not blocked as the requestor on a dual-control step. See Approvals Queue.'],
            ['Receipt didn’t raise stock', 'Confirm the receipt posted; check Movement Completeness for a missing IMS or QBO line.'],
            ['UAT data appeared in Production', 'Stop and tell IT. Confirm you were on a Production URL, not a (UAT) card.'],
            ['Anything else', 'Contact the Supply Chain / Operations admin (Joe Butler), or open an item in IT Product Backlog under Operations.'],
        ],
    ];
}

function supply_chain_manual_workflows(): array
{
    return [
        'sku' => [
            'title'   => 'Maintain a SKU (Product Master)',
            'summary' => 'Create or update the NutraAxis SKU, keep Jazz and QBO references aligned, and enrich the public product page when needed.',
            'cadence' => 'As products launch or change.',
            'steps'   => [
                ['who' => 'Catalog owner', 'when' => 'On change', 'what' => 'Create or edit the SKU on **SKU Master** (attributes, pricing, QBO sync flags).', 'link' => ['product-catalog', '', null]],
                ['who' => 'Catalog owner', 'when' => 'After save', 'what' => 'Confirm the Jazz item on **Jazz Item Master** and the QBO item on **QBO Product Master**.', 'link' => ['jazz-item-master', '', null]],
                ['who' => 'Marketing / catalog', 'when' => 'For web PDPs', 'what' => 'Update PDP HTML and information-sheet PDFs on **Product Page Enrichment**.', 'link' => ['product-enrichment', '', null]],
            ],
        ],
        'buy' => [
            'title'   => 'Buy: PO → receive → invoice → pay',
            'summary' => 'Standard goods or services purchase against a supplier, through receiving and AP.',
            'cadence' => 'Per order; approvals same day when possible.',
            'steps'   => [
                ['who' => 'Buyer', 'when' => 'Start', 'what' => 'Confirm or create the supplier on **Supplier Management** (QBO vendor link when bills will sync).', 'link' => ['supplier-management', '', null]],
                ['who' => 'Buyer', 'when' => 'Same day', 'what' => 'Create the **PO** with lines and quantities; submit for approval.', 'link' => ['po-management', '', null]],
                ['who' => 'Approver', 'when' => 'When pending', 'what' => 'Approve or return from **Approvals Queue**.', 'link' => ['procurement-approvals', '', null]],
                ['who' => 'Receiving', 'when' => 'On arrival', 'what' => 'Schedule on **Delivery Schedule Log** if needed; record **PO Receiving**; review **Jazz ASNs**.', 'link' => ['po-receiving', '', null]],
                ['who' => 'Buyer / AP', 'when' => 'When billed', 'what' => 'Enter **Supplier Invoice** (PO or non-PO), attach PDF, advance payment and QBO insert approvals.', 'link' => ['supplier-invoices', '', null]],
                ['who' => 'AP', 'when' => 'When paying', 'what' => 'Use **PO Payments** or **Invoice Payments**; confirm QBO bill sync status.', 'link' => ['po-payments', '', null]],
            ],
        ],
        'bids' => [
            'title'   => 'Initiatives & Bids (no PO yet)',
            'summary' => 'Light RFP / estimate work that should not create a PO until awarded; award can create a draft invoice.',
            'cadence' => 'Per initiative.',
            'steps'   => [
                ['who' => 'Buyer', 'when' => 'Start', 'what' => 'Open **Initiatives & Bids**, create the initiative, collect supplier estimates.', 'link' => ['procurement-bids', '', null]],
                ['who' => 'Buyer', 'when' => 'On award', 'what' => 'Award the bid (creates draft/estimate invoice path as designed) or convert to a formal PO when purchasing goods under PO control.', 'link' => ['procurement-bids', '', null]],
            ],
        ],
        'stock' => [
            'title'   => 'Keep stock accurate (IMS + reconciliations)',
            'summary' => 'Operational stock lives in the IMS ledger. Reconcile Jazz mothership and QuickBooks; fix gaps with the right movement type.',
            'cadence' => 'Daily glance; weekly deep reconcile.',
            'steps'   => [
                ['who' => 'Inventory', 'when' => 'Daily', 'what' => 'Review **Inventory Balances** by facility.', 'link' => ['inventory-balances', '', null]],
                ['who' => 'Inventory', 'when' => 'As needed', 'what' => 'Post **Facility Transfers** between Cart.com, CPPC, White Label, and transit.', 'link' => ['inventory-transfers', '', null]],
                ['who' => 'Inventory', 'when' => 'When shrink/gain is real', 'what' => 'Submit **Inventory Adjustments** for approval (IMS + QBO).', 'link' => ['inventory-adjustments', '', null]],
                ['who' => 'Inventory', 'when' => 'Weekly', 'what' => '**Jazz vs IMS CART** → investigate → **Jazz → IMS CART Align** only when intentional.', 'link' => ['inventory-jazz-ims-recon', '', null]],
                ['who' => 'Inventory', 'when' => 'Weekly', 'what' => '**QBO Inventory Reconciliation** and **Movement Completeness** for missing posts.', 'link' => ['inventory-qbo-recon', '', null]],
                ['who' => 'Inventory', 'when' => 'Weekly', 'what' => '**Inventory Reconciliation** (Jazz vs ACCS) and planning on **Inventory Forecasting**.', 'link' => ['inventory-reconciliation', '', null]],
            ],
        ],
        'sales' => [
            'title'   => 'Look up orders and sales',
            'summary' => 'ACCS and Jazz order detail, daily/monthly rollups, and sales-rep territory views.',
            'cadence' => 'As needed; month-end for summaries.',
            'steps'   => [
                ['who' => 'Analyst', 'when' => 'When researching an order', 'what' => '**ACCS Order Report** or **Jazz Order Report** (use UAT variants only for stage/test).', 'link' => ['accs-order-report', '', null]],
                ['who' => 'Analyst', 'when' => 'Daily / monthly', 'what' => '**Daily Sales Summary** and **Monthly Sales Summary** for SKU quantity totals.', 'link' => ['sales-daily-summary', '', null]],
                ['who' => 'Sales ops', 'when' => 'As needed', 'what' => '**Sales Rep Reporting** for territory-matched ACCS orders (territories maintained under Administration).', 'link' => ['sales-rep-reporting', '', null]],
            ],
        ],
        'coa' => [
            'title'   => 'Publish a Certificate of Analysis',
            'summary' => 'Upload COA PDFs and metadata so the public nutraaxislabs.com COA table stays current.',
            'cadence' => 'Per lot release.',
            'steps'   => [
                ['who' => 'Compliance ops', 'when' => 'When the lab PDF is ready', 'what' => 'Open **Manage our COAs**, upload the PDF, set product/lot metadata, and publish.', 'link' => ['coa-management', '', null]],
            ],
        ],
    ];
}

function supply_chain_manual_pages(): array
{
    return [
        'product-catalog' => [
            'who'     => 'Catalog owners',
            'purpose' => 'Canonical NutraAxis SKU master — codes, attributes, pricing, and QuickBooks sync settings.',
            'screens' => ['SKU list with search/filters', 'SKU detail / edit'],
            'analyze' => ['Missing QBO link blocks bill and inventory sync for that SKU.', 'Inactive SKUs should not appear in new POs.'],
            'actions' => ['Create / edit SKU', 'Set sync flags'],
        ],
        'product-enrichment' => [
            'who'     => 'Catalog / marketing',
            'purpose' => 'PDP HTML and information-sheet PDFs for nutraaxislabs.com product pages.',
            'actions' => ['Upload / replace HTML and PDFs'],
        ],
        'jazz-item-master' => [
            'who'     => 'Inventory / catalog',
            'purpose' => 'Read-only Jazz OMS item reference for reconciliation against SKU Master.',
            'analyze' => ['Items in Jazz but not in SKU Master need a catalog decision before inventory workflows.'],
            'actions' => ['Search / browse'],
        ],
        'inventory-balances' => [
            'who'     => 'Inventory operators',
            'purpose' => 'Live IMS ledger stock by SKU, facility, and status bucket.',
            'analyze' => ['Negative or unexpected facility balances usually mean a missed transfer, receipt, or sale post — check Movement Completeness.'],
            'actions' => ['Filter by facility / SKU'],
        ],
        'inventory-transfers' => [
            'who'     => 'Inventory operators',
            'purpose' => 'Move stock between Cart.com, CPPC, White Label, and transit on the IMS ledger.',
            'actions' => ['Create transfer', 'Confirm'],
        ],
        'inventory-adjustments' => [
            'who'     => 'Inventory operators + approvers',
            'purpose' => 'Approved shrink/gain that updates IMS and QuickBooks Qty on hand.',
            'analyze' => ['Prefer a receipt or sale correction over an adjustment when the root cause is a missing document.'],
            'actions' => ['Submit adjustment', 'Approve'],
        ],
        'inventory-jazz-ims-recon' => [
            'who'     => 'Inventory operators',
            'purpose' => 'Compare Jazz mothership on-hand with IMS CART ledger balances.',
            'analyze' => ['Large gaps after a sync failure or missed sale need Movement Completeness before Align.'],
            'actions' => ['Run compare', 'Export'],
        ],
        'inventory-jazz-ims-align' => [
            'who'     => 'Inventory admin',
            'purpose' => 'Post JazzSyncReconcile so IMS CART matches Jazz mothership on-hand.',
            'analyze' => ['Align is a controlled catch-up — not a daily habit. Investigate first.'],
            'actions' => ['Preview', 'Post align'],
        ],
        'inventory-qbo-recon' => [
            'who'     => 'Inventory / accounting',
            'purpose' => 'Compare IMS location totals with QuickBooks Qty on hand.',
            'actions' => ['Run compare'],
        ],
        'inventory-movement-recon' => [
            'who'     => 'Inventory operators',
            'purpose' => 'Find receipts, sales, transfers, and adjustments missing IMS or QBO posts.',
            'analyze' => ['Clear gaps here before trusting balances or month-end numbers.'],
            'actions' => ['Review gaps', 'Retry / resolve per row actions'],
        ],
        'qbo-inventory' => [
            'who'     => 'Inventory / accounting',
            'purpose' => 'QuickBooks Online quantity on hand by SKU — financial inventory view.',
            'actions' => ['Browse / search'],
        ],
        'inventory-reporting' => [
            'who'     => 'Inventory operators',
            'purpose' => 'Jazz OMS production stock by SKU and facility.',
            'actions' => ['Browse / filter'],
        ],
        'accs-inventory-reporting' => [
            'who'     => 'Inventory / ecommerce ops',
            'purpose' => 'Adobe Commerce (ACCS) inventory by SKU and source.',
            'actions' => ['Browse / filter'],
        ],
        'inventory-reconciliation' => [
            'who'     => 'Inventory operators',
            'purpose' => 'Side-by-side Jazz vs ACCS quantity compare.',
            'actions' => ['Run compare'],
        ],
        'inventory-forecasting' => [
            'who'     => 'Planning / inventory',
            'purpose' => 'Demand projections and replenishment planning from sales history.',
            'actions' => ['Review projections', 'Export'],
        ],
        'po-management' => [
            'who'     => 'Buyers',
            'purpose' => 'Create, approve, and track purchase orders.',
            'screens' => ['PO list', 'PO detail', 'Create / edit'],
            'actions' => ['Create', 'Submit', 'Close / cancel'],
        ],
        'procurement-approvals' => [
            'who'     => 'Approvers',
            'purpose' => 'Pending and completed PO and supplier invoice approvals (payment + QBO insert recovery).',
            'actions' => ['Approve', 'Return', 'Retry QBO insert when offered'],
        ],
        'supplier-management' => [
            'who'     => 'Buyers',
            'purpose' => 'Supplier profiles, contacts, CMO relationships, and QBO vendor links.',
            'actions' => ['Create / edit supplier', 'Link QBO vendor'],
        ],
        'procurement-bids' => [
            'who'     => 'Buyers',
            'purpose' => 'Light RFPs and estimates that do not become POs until awarded.',
            'actions' => ['Create initiative', 'Collect bids', 'Award'],
        ],
        'supplier-invoices' => [
            'who'     => 'Buyers / AP',
            'purpose' => 'Vendor invoices with lines, attachments, and QuickBooks Bill sync — PO and non-PO.',
            'actions' => ['Create invoice', 'Attach PDF', 'Submit for payment / QBO insert'],
        ],
        'po-payments' => [
            'who'     => 'AP',
            'purpose' => 'Payment requests against purchase orders.',
            'actions' => ['Submit payment request', 'Track status'],
        ],
        'invoice-payments' => [
            'who'     => 'AP',
            'purpose' => 'Record check, ACH, and card payments against supplier invoices without a PO.',
            'actions' => ['Record payment'],
        ],
        'po-receiving' => [
            'who'     => 'Receiving',
            'purpose' => 'Schedule and record inbound shipments against purchase orders.',
            'actions' => ['Create receipt', 'Confirm quantities'],
        ],
        'delivery-scheduling-log' => [
            'who'     => 'Receiving',
            'purpose' => 'Inbound delivery appointments and carrier updates.',
            'actions' => ['Log appointment', 'Update status'],
        ],
        'jazz-asns' => [
            'who'     => 'Receiving',
            'purpose' => 'Advanced shipping notices synced from Jazz OMS.',
            'actions' => ['Browse ASN detail'],
        ],
        'accs-order-report' => [
            'who'     => 'Sales / ops analysts',
            'purpose' => 'Browse and search Adobe Commerce production orders and detail.',
            'actions' => ['Search', 'Open order detail'],
        ],
        'jazz-order-report' => [
            'who'     => 'Sales / ops analysts',
            'purpose' => 'Browse and search Jazz OMS production orders and lines.',
            'actions' => ['Search', 'Open order detail'],
        ],
        'sales-daily-summary' => [
            'who'     => 'Ops / planning',
            'purpose' => 'Daily SKU quantity totals from ACCS orders.',
            'actions' => ['Filter by date', 'Export'],
        ],
        'sales-monthly-summary' => [
            'who'     => 'Ops / planning',
            'purpose' => 'Monthly SKU quantity totals from daily sales.',
            'actions' => ['Filter by month', 'Export'],
        ],
        'sales-rep-reporting' => [
            'who'     => 'Sales ops',
            'purpose' => 'ACCS orders matched to sales-rep territories.',
            'actions' => ['Filter by rep / period'],
        ],
        'labeling-operations' => [
            'who'     => 'Fulfillment / labeling',
            'purpose' => 'Label templates, batches, and compliance workflows for custom order fulfillment.',
            'actions' => ['Manage templates', 'Run batches'],
        ],
        'coa-management' => [
            'who'     => 'Compliance ops',
            'purpose' => 'Upload COA PDFs and metadata for the public nutraaxislabs.com COA table.',
            'actions' => ['Upload PDF', 'Edit metadata', 'Publish'],
        ],
    ];
}
