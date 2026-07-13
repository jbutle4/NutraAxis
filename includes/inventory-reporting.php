<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/jazz-oms.php';

const INVENTORY_REPORTING_PERMISSION_COLUMN = 'InventoryReporting';

function inventory_reporting_permission_value(): ?string
{
    return auth_permission_value(INVENTORY_REPORTING_PERMISSION_COLUMN);
}

function inventory_reporting_can_read(): bool
{
    return auth_can_read(INVENTORY_REPORTING_PERMISSION_COLUMN);
}

function inventory_reporting_require_read(): void
{
    auth_require_login();
    if (inventory_reporting_can_read()) {
        return;
    }
    auth_render_access_denied('You do not have permission to view Jazz Current Inventory.');
}

/**
 * Product names from SKUMaster keyed by lower-case SKUCode.
 *
 * @param list<string> $skuCodes
 * @return array<string, string>
 */
function inventory_reporting_sku_product_names(array $skuCodes): array
{
    $normalized = [];
    foreach ($skuCodes as $sku) {
        $sku = trim((string) $sku);
        if ($sku !== '') {
            $normalized[strtolower($sku)] = $sku;
        }
    }

    if ($normalized === []) {
        return [];
    }

    $pdo = db();
    $placeholders = implode(',', array_fill(0, count($normalized), '?'));
    $stmt = $pdo->prepare(
        "SELECT SKUCode, ProductName FROM dbo.SKUMaster WHERE LOWER(SKUCode) IN ({$placeholders})"
    );
    $stmt->execute(array_keys($normalized));

    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $skuKey = strtolower(trim((string) ($row['SKUCode'] ?? '')));
        $name = trim((string) ($row['ProductName'] ?? ''));
        if ($skuKey !== '' && $name !== '') {
            $map[$skuKey] = $name;
        }
    }

    return $map;
}

/**
 * Attach product_name onto inventory rows from SKUMaster.
 *
 * @param list<array<string, mixed>> $rows
 * @return list<array<string, mixed>>
 */
function inventory_reporting_enrich_product_names(array $rows): array
{
    $skuCodes = [];
    foreach ($rows as $row) {
        $sku = trim((string) ($row['sku_code'] ?? $row['sku'] ?? ''));
        if ($sku !== '') {
            $skuCodes[] = $sku;
        }
    }

    $nameBySku = inventory_reporting_sku_product_names($skuCodes);
    foreach ($rows as $index => $row) {
        $skuKey = strtolower(trim((string) ($row['sku_code'] ?? $row['sku'] ?? '')));
        $rows[$index]['product_name'] = $skuKey !== '' ? ($nameBySku[$skuKey] ?? '') : '';
    }

    return $rows;
}
