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
