<?php

require_once __DIR__ . '/inventory-reporting.php';
require_once __DIR__ . '/accs-inventory-reporting.php';

function inventory_reconciliation_permission_value(): ?string
{
    return inventory_reporting_permission_value();
}

function inventory_reconciliation_can_read(): bool
{
    return inventory_reporting_can_read();
}

function inventory_reconciliation_require_read(): void
{
    auth_require_login();
    if (inventory_reconciliation_can_read()) {
        return;
    }
    auth_render_access_denied('You do not have permission to view Inventory Reconciliation.');
}

function inventory_reconciliation_normalize_sku($value): string
{
    return strtoupper(trim((string) $value));
}

/** @return array<string, array{quantity: float, status: int, display_sku: string}> */
function inventory_reconciliation_index_accs(array $accsRows): array
{
    $indexed = [];

    foreach ($accsRows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $displaySku = trim((string) ($row['sku'] ?? ''));
        $skuKey = inventory_reconciliation_normalize_sku($displaySku);
        if ($skuKey === '') {
            continue;
        }

        if (!isset($indexed[$skuKey])) {
            $indexed[$skuKey] = [
                'quantity'    => 0.0,
                'status'      => 0,
                'display_sku' => $displaySku,
            ];
        }

        $indexed[$skuKey]['quantity'] += (float) ($row['quantity'] ?? 0);
        if ((int) ($row['status'] ?? 0) === 1) {
            $indexed[$skuKey]['status'] = 1;
        }
    }

    return $indexed;
}

function inventory_reconciliation_quantities_match($jazzQty, $accsQty): bool
{
    if ($jazzQty === null || $accsQty === null || $jazzQty === '' || $accsQty === '') {
        return false;
    }

    return abs((float) $jazzQty - (float) $accsQty) < 0.0001;
}

function inventory_reconciliation_row_has_mismatch(array $row): bool
{
    if (!$row['has_jazz'] || !$row['has_accs']) {
        return true;
    }

    return !inventory_reconciliation_quantities_match($row['available'], $row['accs_qty']);
}

/**
 * @param array<int, array<string, mixed>> $jazzRows
 * @param array<int, array<string, mixed>> $accsRows
 * @return array<int, array<string, mixed>>
 */
function inventory_reconciliation_build_rows(array $jazzRows, array $accsRows): array
{
    $accsBySku = inventory_reconciliation_index_accs($accsRows);
    $rows = [];
    $jazzSkuKeys = [];

    foreach ($jazzRows as $jazz) {
        if (!is_array($jazz)) {
            continue;
        }

        $displaySku = trim((string) ($jazz['sku_code'] ?? ''));
        $skuKey = inventory_reconciliation_normalize_sku($displaySku);
        if ($skuKey === '') {
            continue;
        }

        $jazzSkuKeys[$skuKey] = true;
        $accs = $accsBySku[$skuKey] ?? null;

        $rows[] = [
            'sku'         => $displaySku,
            'facility'    => (string) ($jazz['facility_code'] ?? '—'),
            'available'   => $jazz['available_quantity'] ?? null,
            'on_hand'     => $jazz['on_hand_quantity'] ?? null,
            'ordered'     => $jazz['qty_ordered'] ?? null,
            'total'       => $jazz['total_quantity'] ?? null,
            'accs_qty'    => $accs['quantity'] ?? null,
            'accs_status' => $accs['status'] ?? null,
            'has_jazz'    => true,
            'has_accs'    => $accs !== null,
        ];
    }

    foreach ($accsBySku as $skuKey => $accs) {
        if (isset($jazzSkuKeys[$skuKey])) {
            continue;
        }

        $rows[] = [
            'sku'         => $accs['display_sku'],
            'facility'    => '—',
            'available'   => null,
            'on_hand'     => null,
            'ordered'     => null,
            'total'       => null,
            'accs_qty'    => $accs['quantity'],
            'accs_status' => $accs['status'],
            'has_jazz'    => false,
            'has_accs'    => true,
        ];
    }

    usort(
        $rows,
        static function (array $left, array $right): int {
            $skuCompare = strcasecmp((string) $left['sku'], (string) $right['sku']);
            if ($skuCompare !== 0) {
                return $skuCompare;
            }

            return strcasecmp((string) $left['facility'], (string) $right['facility']);
        }
    );

    return $rows;
}

function inventory_reconciliation_count_mismatches(array $rows): int
{
    $count = 0;
    foreach ($rows as $row) {
        if (inventory_reconciliation_row_has_mismatch($row)) {
            $count++;
        }
    }

    return $count;
}
