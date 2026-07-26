<?php

require_once __DIR__ . '/inventory-ledger.php';
require_once __DIR__ . '/quickbooks.php';

function inventory_qbo_recon_require_read(): void
{
    inventory_ledger_require_read();
}

/**
 * Company-wide IMS qty vs QBO Inventory QtyOnHand.
 *
 * After Jazz→IMS CART align, large deltas are expected: IMS may reflect mothership
 * on-hand while QBO starts at 0 and only accumulates receipt/sale/adjustment posts.
 *
 * @return array{
 *   ok:bool,
 *   error:?string,
 *   rows:array<int,array<string,mixed>>,
 *   mismatch_count:int,
 *   summary:array<string,mixed>
 * }
 */
function inventory_qbo_recon_build_rows(): array
{
    $pdo = db();
    $emptySummary = [
        'ims_sku_count' => 0,
        'qbo_sku_count' => 0,
        'compared_count' => 0,
        'ims_total_qty' => 0.0,
        'qbo_total_qty' => 0.0,
        'delta_total' => null,
        'matched_count' => 0,
        'missing_in_qbo' => 0,
        'missing_in_ims' => 0,
        'qty_mismatch' => 0,
    ];

    try {
        $imsStmt = $pdo->query(<<<SQL
            SELECT
                b.SKUCode,
                SUM(b.QtyOK + b.QtyQuarantine + b.QtyOnHold) AS ImsQty,
                MAX(m.QBO_ItemID) AS QBO_ItemID,
                MAX(m.SKUStatus) AS SKUStatus
            FROM dbo.InvCurrentBalance b
            LEFT JOIN dbo.SKUMaster m ON m.SKUCode = b.SKUCode
            GROUP BY b.SKUCode
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
                'qbo_item_id' => trim((string) ($row['QBO_ItemID'] ?? '')),
                'sku_status' => (string) ($row['SKUStatus'] ?? ''),
            ];
        }
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'error' => 'IMS ledger is not available: ' . $e->getMessage(),
            'rows' => [],
            'mismatch_count' => 0,
            'summary' => $emptySummary,
        ];
    }

    $qboBySku = [];
    $qboById = [];
    if (qbo_is_connected()) {
        $list = function_exists('qbo_list_product_items')
            ? qbo_list_product_items()
            : qbo_list_inventory_items();
        if (!$list['ok']) {
            return [
                'ok' => false,
                'error' => (string) ($list['error'] ?? 'Unable to load QuickBooks items.'),
                'rows' => [],
                'mismatch_count' => 0,
                'summary' => $emptySummary,
            ];
        }
        foreach ($list['rows'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $type = (string) ($item['Type'] ?? '');
            if ($type !== '' && strcasecmp($type, 'Inventory') !== 0) {
                continue;
            }
            $id = trim((string) ($item['Id'] ?? ''));
            $sku = strtoupper(trim((string) ($item['Sku'] ?? '')));
            $entry = [
                'sku' => $sku !== '' ? (string) ($item['Sku'] ?? '') : '',
                'qbo_qty' => (float) ($item['QtyOnHand'] ?? 0),
                'qbo_id' => $id,
                'name' => (string) ($item['Name'] ?? ''),
            ];
            if ($sku !== '') {
                $qboBySku[$sku] = $entry;
            }
            if ($id !== '') {
                $qboById[$id] = $entry;
            }
        }
    }

    $keys = array_unique(array_merge(array_keys($imsBySku), array_keys($qboBySku)));
    sort($keys);

    $rows = [];
    $mismatchCount = 0;
    $matchedCount = 0;
    $missingInQbo = 0;
    $missingInIms = 0;
    $qtyMismatch = 0;
    $imsTotal = 0.0;
    $qboTotal = 0.0;
    $bothSides = 0;

    foreach ($keys as $key) {
        $ims = $imsBySku[$key] ?? null;
        $qbo = $qboBySku[$key] ?? null;
        $matchMethod = $qbo !== null ? 'sku' : null;

        // Prefer SKUMaster.QBO_ItemID when Sku match is missing or Id disagrees.
        if ($ims !== null && ($ims['qbo_item_id'] ?? '') !== '') {
            $byId = $qboById[$ims['qbo_item_id']] ?? null;
            if ($byId !== null) {
                if ($qbo === null) {
                    $qbo = $byId;
                    $matchMethod = 'item_id';
                } elseif (($qbo['qbo_id'] ?? '') !== ($byId['qbo_id'] ?? '')) {
                    // Sku matched a different item than SKUMaster — trust Id for qty/name.
                    $qbo = $byId;
                    $matchMethod = 'item_id_override';
                }
            }
        }

        $hasIms = $ims !== null;
        $hasQbo = $qbo !== null;
        $imsQty = $hasIms ? (float) $ims['ims_qty'] : null;
        $qboQty = $hasQbo ? (float) $qbo['qbo_qty'] : null;
        $delta = ($hasIms && $hasQbo) ? ($imsQty - $qboQty) : null;
        $mismatch = !$hasIms || !$hasQbo || ($delta !== null && abs($delta) >= 0.0001);

        if ($hasIms) {
            $imsTotal += (float) $imsQty;
        }
        if ($hasQbo) {
            $qboTotal += (float) $qboQty;
        }
        if ($hasIms && $hasQbo) {
            $bothSides++;
        }

        if ($mismatch) {
            $mismatchCount++;
            if (!$hasQbo) {
                $missingInQbo++;
            } elseif (!$hasIms) {
                $missingInIms++;
            } else {
                $qtyMismatch++;
            }
        } else {
            $matchedCount++;
        }

        $rows[] = [
            'sku' => $ims['sku'] ?? ($qbo['sku'] !== '' ? $qbo['sku'] : $key),
            'name' => $qbo['name'] ?? '',
            'ims_qty' => $imsQty,
            'qbo_qty' => $qboQty,
            'delta' => $delta,
            'qbo_id' => $qbo['qbo_id'] ?? ($ims['qbo_item_id'] ?? ''),
            'has_ims' => $hasIms,
            'has_qbo' => $hasQbo,
            'mismatch' => $mismatch,
            'match_method' => $matchMethod,
            'sku_status' => $ims['sku_status'] ?? '',
        ];
    }

    return [
        'ok' => true,
        'error' => null,
        'rows' => $rows,
        'mismatch_count' => $mismatchCount,
        'summary' => [
            'ims_sku_count' => count($imsBySku),
            'qbo_sku_count' => count($qboBySku),
            'compared_count' => count($rows),
            'ims_total_qty' => $imsTotal,
            'qbo_total_qty' => $qboTotal,
            'delta_total' => $bothSides > 0 ? ($imsTotal - $qboTotal) : null,
            'matched_count' => $matchedCount,
            'missing_in_qbo' => $missingInQbo,
            'missing_in_ims' => $missingInIms,
            'qty_mismatch' => $qtyMismatch,
        ],
    ];
}
