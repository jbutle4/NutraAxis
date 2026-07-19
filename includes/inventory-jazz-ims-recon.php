<?php

require_once __DIR__ . '/inventory-ledger.php';
require_once __DIR__ . '/facility.php';
require_once __DIR__ . '/jazz-oms.php';

function inventory_jazz_ims_recon_require_read(): void
{
    inventory_ledger_require_read();
}

/**
 * Resolve a Jazz facility_code to the canonical IMS FacilityCode, or null if unknown.
 */
function inventory_jazz_ims_recon_resolve_ims_facility(?string $jazzFacilityCode): ?string
{
    $jazzFacilityCode = trim((string) $jazzFacilityCode);
    if ($jazzFacilityCode === '') {
        return null;
    }

    $facility = facility_get_by_code($jazzFacilityCode);
    if ($facility === null) {
        return null;
    }

    return trim((string) ($facility['FacilityCode'] ?? '')) ?: null;
}

function inventory_jazz_ims_recon_is_cart_jazz_facility(?string $jazzFacilityCode): bool
{
    return inventory_jazz_ims_recon_resolve_ims_facility($jazzFacilityCode) === 'CART';
}

/**
 * @return array{ok:bool,error:?string,rows:array<int,array<string,mixed>>,mismatch_count:int,jazz_env:string,jazz_facility_codes:array<int,string>}
 */
function inventory_jazz_ims_recon_build_rows(string $jazzEnvironment = 'production'): array
{
    $jazzEnvironment = strtolower(trim($jazzEnvironment)) === 'uat' ? 'uat' : 'production';
    jazz_oms_use_environment($jazzEnvironment);

    $pdo = db();
    $imsBySku = [];

    try {
        $imsStmt = $pdo->query(<<<SQL
            SELECT
                SKUCode,
                SUM(QtyOK) AS QtyOK,
                SUM(QtyQuarantine) AS QtyQuarantine,
                SUM(QtyOnHold) AS QtyOnHold,
                SUM(QtyOK + QtyQuarantine + QtyOnHold) AS ImsQty
            FROM dbo.InvCurrentBalance
            WHERE FacilityCode = N'CART'
            GROUP BY SKUCode
        SQL);
        foreach ($imsStmt->fetchAll() as $row) {
            $sku = strtoupper(trim((string) ($row['SKUCode'] ?? '')));
            if ($sku === '') {
                continue;
            }
            $imsBySku[$sku] = [
                'sku' => (string) $row['SKUCode'],
                'ims_qty' => (float) ($row['ImsQty'] ?? 0),
                'qty_ok' => (float) ($row['QtyOK'] ?? 0),
                'qty_quarantine' => (float) ($row['QtyQuarantine'] ?? 0),
                'qty_on_hold' => (float) ($row['QtyOnHold'] ?? 0),
            ];
        }
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'error' => 'IMS ledger is not available: ' . $e->getMessage(),
            'rows' => [],
            'mismatch_count' => 0,
            'jazz_env' => $jazzEnvironment,
            'jazz_facility_codes' => [],
        ];
    }

    $configError = jazz_oms_config_error();
    if ($configError !== null) {
        return [
            'ok' => false,
            'error' => $configError,
            'rows' => [],
            'mismatch_count' => 0,
            'jazz_env' => $jazzEnvironment,
            'jazz_facility_codes' => [],
        ];
    }

    $jazzList = jazz_oms_list_inventory();
    if (!$jazzList['ok']) {
        return [
            'ok' => false,
            'error' => (string) ($jazzList['error'] ?? 'Unable to load Jazz inventory.'),
            'rows' => [],
            'mismatch_count' => 0,
            'jazz_env' => $jazzEnvironment,
            'jazz_facility_codes' => [],
        ];
    }

    $jazzBySku = [];
    $jazzFacilityCodes = [];
    foreach ($jazzList['rows'] ?? [] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $facilityCode = trim((string) ($row['facility_code'] ?? ''));
        if (!inventory_jazz_ims_recon_is_cart_jazz_facility($facilityCode)) {
            continue;
        }
        if ($facilityCode !== '' && !in_array($facilityCode, $jazzFacilityCodes, true)) {
            $jazzFacilityCodes[] = $facilityCode;
        }

        $displaySku = trim((string) ($row['sku_code'] ?? ''));
        $sku = strtoupper($displaySku);
        if ($sku === '') {
            continue;
        }

        if (!isset($jazzBySku[$sku])) {
            $jazzBySku[$sku] = [
                'sku' => $displaySku,
                'jazz_on_hand' => 0.0,
                'jazz_available' => 0.0,
                'facility_codes' => [],
            ];
        }
        $jazzBySku[$sku]['jazz_on_hand'] += (float) ($row['on_hand_quantity'] ?? 0);
        $jazzBySku[$sku]['jazz_available'] += (float) ($row['available_quantity'] ?? 0);
        if ($facilityCode !== '' && !in_array($facilityCode, $jazzBySku[$sku]['facility_codes'], true)) {
            $jazzBySku[$sku]['facility_codes'][] = $facilityCode;
        }
    }
    sort($jazzFacilityCodes);

    $keys = array_unique(array_merge(array_keys($imsBySku), array_keys($jazzBySku)));
    sort($keys);

    $rows = [];
    $mismatchCount = 0;
    foreach ($keys as $key) {
        $hasIms = array_key_exists($key, $imsBySku);
        $hasJazz = array_key_exists($key, $jazzBySku);
        $imsQty = $hasIms ? (float) $imsBySku[$key]['ims_qty'] : null;
        $jazzQty = $hasJazz ? (float) $jazzBySku[$key]['jazz_on_hand'] : null;
        $delta = ($hasIms && $hasJazz) ? ($imsQty - $jazzQty) : null;
        $mismatch = !$hasIms || !$hasJazz || ($delta !== null && abs($delta) >= 0.0001);
        if ($mismatch) {
            $mismatchCount++;
        }

        $rows[] = [
            'sku' => $imsBySku[$key]['sku'] ?? ($jazzBySku[$key]['sku'] ?? $key),
            'ims_qty' => $imsQty,
            'ims_qty_ok' => $hasIms ? (float) $imsBySku[$key]['qty_ok'] : null,
            'ims_qty_quarantine' => $hasIms ? (float) $imsBySku[$key]['qty_quarantine'] : null,
            'ims_qty_on_hold' => $hasIms ? (float) $imsBySku[$key]['qty_on_hold'] : null,
            'jazz_on_hand' => $jazzQty,
            'jazz_available' => $hasJazz ? (float) $jazzBySku[$key]['jazz_available'] : null,
            'jazz_facility' => $hasJazz ? implode(', ', $jazzBySku[$key]['facility_codes']) : '',
            'delta' => $delta,
            'has_ims' => $hasIms,
            'has_jazz' => $hasJazz,
            'mismatch' => $mismatch,
        ];
    }

    return [
        'ok' => true,
        'error' => null,
        'rows' => $rows,
        'mismatch_count' => $mismatchCount,
        'jazz_env' => $jazzEnvironment,
        'jazz_facility_codes' => $jazzFacilityCodes,
    ];
}
