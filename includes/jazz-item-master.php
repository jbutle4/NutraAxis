<?php

require_once __DIR__ . '/inventory-reporting.php';

const JAZZ_ITEM_MASTER_VIEWS = ['sku', 'item'];

const JAZZ_ITEM_MASTER_SKU_COLUMNS = [
    'sku_code',
    'item_code',
    'description',
    'barcode',
    'status',
    'size_code',
    'size_description',
    'cost',
    'original_price',
    'current_price',
    'uses_inventory',
    'lot_number_required',
    'is_kit',
    'updated_at',
];

const JAZZ_ITEM_MASTER_ITEM_COLUMNS = [
    'item_code',
    'description',
    'vendor_code',
    'status',
    'cost',
    'original_price',
    'current_price',
    'inventory_minimum',
    'backorderable',
    'updated_at',
];

function jazz_item_master_require_read(): void
{
    inventory_reporting_require_read();
}

function jazz_item_master_view_from_query(): string
{
    $view = strtolower(trim((string) ($_GET['view'] ?? 'sku')));

    return in_array($view, JAZZ_ITEM_MASTER_VIEWS, true) ? $view : 'sku';
}

/**
 * @param list<array<string, mixed>> $rows
 * @return list<array<string, mixed>>
 */
function jazz_item_master_filter_rows(array $rows, string $query): array
{
    $query = trim(strtolower($query));
    if ($query === '') {
        return $rows;
    }

    return array_values(array_filter($rows, static function (array $row) use ($query): bool {
        foreach (['sku_code', 'item_code', 'description', 'barcode', 'vendor_code'] as $field) {
            if (str_contains(strtolower((string) ($row[$field] ?? '')), $query)) {
                return true;
            }
        }

        return false;
    }));
}

/**
 * @return array{ok: bool, error: ?string, rows: list<array<string, mixed>>, endpoint: string}
 */
function jazz_item_master_list(string $view): array
{
    if ($view === 'item') {
        $result = jazz_oms_list_items();

        return $result + ['endpoint' => '/api/v1/product/item'];
    }

    $result = jazz_oms_list_skus();

    return $result + ['endpoint' => '/api/v1/product/sku'];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return list<string>
 */
function jazz_item_master_columns(string $view, array $rows): array
{
    $preferred = $view === 'item'
        ? JAZZ_ITEM_MASTER_ITEM_COLUMNS
        : JAZZ_ITEM_MASTER_SKU_COLUMNS;

    return jazz_oms_record_columns($rows, $preferred);
}

function jazz_item_master_view_label(string $view): string
{
    return $view === 'item' ? 'Items' : 'SKUs';
}
