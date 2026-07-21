<?php

require_once __DIR__ . '/env.php';

const ENVIRONMENT_TIER_HUB = 'hub';
const ENVIRONMENT_TIER_PRODUCTION = 'production';
const ENVIRONMENT_TIER_UAT = 'uat';
const ENVIRONMENT_TIER_EXTERNAL = 'external';

const ENVIRONMENT_TIER_ORDER = [
    ENVIRONMENT_TIER_PRODUCTION => 0,
    ENVIRONMENT_TIER_HUB       => 1,
    ENVIRONMENT_TIER_EXTERNAL  => 2,
    ENVIRONMENT_TIER_UAT       => 3,
];

function data_profile(): string
{
    $profile = $GLOBALS['_data_profile'] ?? 'production';

    return $profile === 'uat' ? 'uat' : 'production';
}

function data_profile_set(string $profile): void
{
    $GLOBALS['_data_profile'] = $profile === 'uat' ? 'uat' : 'production';
}

function data_profile_is_uat(): bool
{
    return data_profile() === 'uat';
}

function data_profile_is_production(): bool
{
    return data_profile() === 'production';
}

function environment_tier_normalize(?string $tier): string
{
    $tier = strtolower(trim((string) $tier));

    return match ($tier) {
        ENVIRONMENT_TIER_HUB,
        ENVIRONMENT_TIER_PRODUCTION,
        ENVIRONMENT_TIER_UAT,
        ENVIRONMENT_TIER_EXTERNAL => $tier,
        'test' => ENVIRONMENT_TIER_UAT,
        default => ENVIRONMENT_TIER_PRODUCTION,
    };
}

function environment_tier_card_class(?string $tier): string
{
    return 'card-tier-' . environment_tier_normalize($tier);
}

function environment_tier_sort(array $items): array
{
    usort($items, static function (array $a, array $b): int {
        $sortA = $a['sort'] ?? null;
        $sortB = $b['sort'] ?? null;
        if ($sortA !== null || $sortB !== null) {
            return ($sortA ?? PHP_INT_MAX) <=> ($sortB ?? PHP_INT_MAX);
        }

        $orderA = ENVIRONMENT_TIER_ORDER[environment_tier_normalize($a['tier'] ?? ENVIRONMENT_TIER_PRODUCTION)] ?? 99;
        $orderB = ENVIRONMENT_TIER_ORDER[environment_tier_normalize($b['tier'] ?? ENVIRONMENT_TIER_PRODUCTION)] ?? 99;

        if ($orderA !== $orderB) {
            return $orderA <=> $orderB;
        }

        return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
    });

    return $items;
}

function data_profile_env_value(string $uatKey, string $productionKey, string $legacyKey = ''): string
{
    $keys = data_profile_is_uat()
        ? [$uatKey, $legacyKey]
        : [$productionKey, $legacyKey];

    return trim((string) env_first($keys, ''));
}

function hub_card_tier_class(array $item): string
{
    return environment_tier_card_class($item['tier'] ?? ENVIRONMENT_TIER_PRODUCTION);
}

/**
 * Map a production page path to its UAT twin when the active data profile is UAT.
 */
function data_profile_page_path(string $productionPath): string
{
    if (!data_profile_is_uat()) {
        return $productionPath;
    }

    static $map = [
        '/inventory-reporting'              => '/inventory-reporting-uat',
        '/inventory-reporting/'             => '/inventory-reporting-uat/',
        '/accs-inventory-reporting'         => '/accs-inventory-reporting-uat',
        '/accs-inventory-reporting/'        => '/accs-inventory-reporting-uat/',
        '/inventory-reconciliation'         => '/inventory-reconciliation-uat',
        '/inventory-reconciliation/'        => '/inventory-reconciliation-uat/',
        '/jazz-item-master'                 => '/jazz-item-master-uat',
        '/jazz-item-master/'                => '/jazz-item-master-uat/',
        '/po-receiving/jazz-asns.php'       => '/po-receiving/jazz-asns-uat.php',
        '/po-receiving/jazz-asn.php'        => '/po-receiving/jazz-asn-uat.php',
        '/sales-reporting/accs-order-report'  => '/sales-reporting/accs-order-report-uat',
        '/sales-reporting/accs-order-report/' => '/sales-reporting/accs-order-report-uat/',
        '/sales-reporting/order.php'        => '/sales-reporting/order-uat.php',
        '/sales-reporting/jazz-order-report'  => '/sales-reporting/jazz-order-report-uat',
        '/sales-reporting/jazz-order-report/' => '/sales-reporting/jazz-order-report-uat/',
        '/sales-reporting/jazz-order.php'     => '/sales-reporting/jazz-order-uat.php',
        '/accounting/ap.php'                => '/accounting/ap-uat.php',
        '/accounting/ar.php'                => '/accounting/ar-uat.php',
        '/accounting/pos.php'               => '/accounting/pos-uat.php',
        '/accounting/inventory.php'         => '/accounting/inventory-uat.php',
        '/accounting/suppliers.php'         => '/accounting/suppliers-uat.php',
        '/accounting/chart-of-accounts.php' => '/accounting/chart-of-accounts-uat.php',
        '/accounting/supplier-invoices'     => '/accounting/supplier-invoices-uat',
        '/accounting/supplier-invoices/'    => '/accounting/supplier-invoices-uat/',
        '/accounting/invoice-payments'      => '/accounting/invoice-payments-uat',
        '/accounting/invoice-payments/'     => '/accounting/invoice-payments-uat/',
    ];

    if (isset($map[$productionPath])) {
        return $map[$productionPath];
    }

    // Remap nested module paths (e.g. /accounting/supplier-invoices/view.php).
    foreach ($map as $from => $to) {
        if (!str_ends_with($from, '/')) {
            continue;
        }
        if (str_starts_with($productionPath, $from)) {
            return $to . substr($productionPath, strlen($from));
        }
    }

    return $productionPath;
}

function hub_cards_partition_uat(array $items): array
{
    $production = [];
    $uat = [];

    foreach ($items as $item) {
        if (environment_tier_normalize($item['tier'] ?? ENVIRONMENT_TIER_PRODUCTION) === ENVIRONMENT_TIER_UAT) {
            $uat[] = $item;
        } else {
            $production[] = $item;
        }
    }

    return [
        'production' => environment_tier_sort($production),
        'uat'        => environment_tier_sort($uat),
    ];
}
