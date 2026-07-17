<?php

require_once __DIR__ . '/inventory-ledger.php';
require_once __DIR__ . '/quickbooks.php';

function inventory_qbo_recon_require_read(): void
{
    inventory_ledger_require_read();
}

/**
 * @return array{ok:bool,error:?string,rows:array<int,array<string,mixed>>,mismatch_count:int}
 */
function inventory_qbo_recon_build_rows(): array
{
    $pdo = db();

    try {
        $imsStmt = $pdo->query(<<<SQL
            SELECT
                SKUCode,
                SUM(QtyOK + QtyQuarantine + QtyOnHold) AS ImsQty
            FROM dbo.InvCurrentBalance
            GROUP BY SKUCode
        SQL);
        $imsBySku = [];
        foreach ($imsStmt->fetchAll() as $row) {
            $sku = strtoupper(trim((string) ($row['SKUCode'] ?? '')));
            if ($sku === '') {
                continue;
            }
            $imsBySku[$sku] = [
                'sku' => (string) $row['SKUCode'],
                'ims_qty' => (float) ($row['ImsQty'] ?? 0),
            ];
        }
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'IMS ledger is not available: ' . $e->getMessage(), 'rows' => [], 'mismatch_count' => 0];
    }

    $qboBySku = [];
    if (qbo_is_connected()) {
        $list = function_exists('qbo_list_product_items')
            ? qbo_list_product_items()
            : qbo_list_inventory_items();
        if (!$list['ok']) {
            return ['ok' => false, 'error' => (string) ($list['error'] ?? 'Unable to load QuickBooks items.'), 'rows' => [], 'mismatch_count' => 0];
        }
        foreach ($list['rows'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $type = (string) ($item['Type'] ?? '');
            if ($type !== '' && strcasecmp($type, 'Inventory') !== 0) {
                continue;
            }
            $sku = strtoupper(trim((string) ($item['Sku'] ?? '')));
            if ($sku === '') {
                continue;
            }
            $qboBySku[$sku] = [
                'sku' => (string) ($item['Sku'] ?? ''),
                'qbo_qty' => (float) ($item['QtyOnHand'] ?? 0),
                'qbo_id' => (string) ($item['Id'] ?? ''),
                'name' => (string) ($item['Name'] ?? ''),
            ];
        }
    }

    $keys = array_unique(array_merge(array_keys($imsBySku), array_keys($qboBySku)));
    sort($keys);

    $rows = [];
    $mismatchCount = 0;
    foreach ($keys as $key) {
        $imsQty = $imsBySku[$key]['ims_qty'] ?? null;
        $qboQty = $qboBySku[$key]['qbo_qty'] ?? null;
        $hasIms = array_key_exists($key, $imsBySku);
        $hasQbo = array_key_exists($key, $qboBySku);
        $delta = ($hasIms && $hasQbo) ? ((float) $imsQty - (float) $qboQty) : null;
        $mismatch = !$hasIms || !$hasQbo || ($delta !== null && abs($delta) >= 0.0001);
        if ($mismatch) {
            $mismatchCount++;
        }
        $rows[] = [
            'sku' => $imsBySku[$key]['sku'] ?? ($qboBySku[$key]['sku'] ?? $key),
            'name' => $qboBySku[$key]['name'] ?? '',
            'ims_qty' => $imsQty,
            'qbo_qty' => $qboQty,
            'delta' => $delta,
            'qbo_id' => $qboBySku[$key]['qbo_id'] ?? '',
            'has_ims' => $hasIms,
            'has_qbo' => $hasQbo,
            'mismatch' => $mismatch,
        ];
    }

    return ['ok' => true, 'error' => null, 'rows' => $rows, 'mismatch_count' => $mismatchCount];
}
