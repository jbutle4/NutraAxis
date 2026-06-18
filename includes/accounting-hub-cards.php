<?php

require_once __DIR__ . '/hub-cards.php';

function accounting_hub_cards(): array
{
    return [
        [
            'title' => 'Accounts Payable',
            'desc'  => 'Open bills and vendor balances from QuickBooks.',
            'href'  => '/accounting/ap.php',
            'tier'  => ENVIRONMENT_TIER_PRODUCTION,
            'cta'   => 'View AP',
        ],
        [
            'title' => 'Accounts Receivable',
            'desc'  => 'Customer invoices and outstanding balances.',
            'href'  => '/accounting/ar.php',
            'tier'  => ENVIRONMENT_TIER_PRODUCTION,
            'cta'   => 'View AR',
        ],
        [
            'title' => 'Purchase Orders',
            'desc'  => 'QuickBooks purchase orders and status.',
            'href'  => '/accounting/pos.php',
            'tier'  => ENVIRONMENT_TIER_PRODUCTION,
            'cta'   => 'View POs',
        ],
        [
            'title' => 'Inventory',
            'desc'  => 'Inventory items, SKU, and quantity on hand.',
            'href'  => '/accounting/inventory.php',
            'tier'  => ENVIRONMENT_TIER_PRODUCTION,
            'cta'   => 'View Inventory',
        ],
        [
            'title' => 'Suppliers',
            'desc'  => 'QuickBooks vendor directory and balances.',
            'href'  => '/accounting/suppliers.php',
            'tier'  => ENVIRONMENT_TIER_PRODUCTION,
            'cta'   => 'View Suppliers',
        ],
        [
            'title' => 'Chart of Accounts',
            'desc'  => 'General ledger accounts and current balances.',
            'href'  => '/accounting/chart-of-accounts.php',
            'tier'  => ENVIRONMENT_TIER_PRODUCTION,
            'cta'   => 'View Accounts',
        ],
        [
            'title' => 'UAT Accounts Payable',
            'desc'  => 'UAT AP Page — QuickBooks sandbox bills and vendor balances.',
            'href'  => '/accounting/ap-uat.php',
            'tier'  => ENVIRONMENT_TIER_UAT,
            'cta'   => 'View UAT AP',
        ],
        [
            'title' => 'UAT Accounts Receivable',
            'desc'  => 'UAT AR Page — QuickBooks sandbox invoices and balances.',
            'href'  => '/accounting/ar-uat.php',
            'tier'  => ENVIRONMENT_TIER_UAT,
            'cta'   => 'View UAT AR',
        ],
        [
            'title' => 'UAT Purchase Orders',
            'desc'  => 'UAT Purchase Orders — QuickBooks sandbox POs.',
            'href'  => '/accounting/pos-uat.php',
            'tier'  => ENVIRONMENT_TIER_UAT,
            'cta'   => 'View UAT POs',
        ],
        [
            'title' => 'UAT Inventory',
            'desc'  => 'UAT Inventory — QuickBooks sandbox inventory items.',
            'href'  => '/accounting/inventory-uat.php',
            'tier'  => ENVIRONMENT_TIER_UAT,
            'cta'   => 'View UAT Inventory',
        ],
        [
            'title' => 'UAT Suppliers',
            'desc'  => 'UAT Suppliers — QuickBooks sandbox vendor directory.',
            'href'  => '/accounting/suppliers-uat.php',
            'tier'  => ENVIRONMENT_TIER_UAT,
            'cta'   => 'View UAT Suppliers',
        ],
        [
            'title' => 'UAT Chart of Accounts',
            'desc'  => 'UAT Chart of Accounts — QuickBooks sandbox GL accounts.',
            'href'  => '/accounting/chart-of-accounts-uat.php',
            'tier'  => ENVIRONMENT_TIER_UAT,
            'cta'   => 'View UAT Accounts',
        ],
    ];
}

function accounting_render_hub_cards(): void
{
    $sections = hub_cards_partition_uat(accounting_hub_cards());

    accounting_render_card_group($sections['production']);
    if ($sections['uat'] !== []) {
        echo '<h2 class="hub-uat-section-title">UAT / Test Systems</h2>';
        accounting_render_card_group($sections['uat']);
    }
}

function accounting_render_card_group(array $cards): void
{
    echo '<div class="functions">';
    foreach ($cards as $card) {
        $tierClass = hub_card_tier_class($card);
        echo '<a class="function-card ' . htmlspecialchars($tierClass) . '" href="' . htmlspecialchars((string) $card['href']) . '">';
        echo '<h3>' . htmlspecialchars((string) $card['title']) . '</h3>';
        echo '<p>' . htmlspecialchars((string) $card['desc']) . '</p>';
        echo '<span class="function-link">' . htmlspecialchars((string) ($card['cta'] ?? 'Open'));
        echo '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span>';
        echo '</a>';
    }
    echo '</div>';
}
