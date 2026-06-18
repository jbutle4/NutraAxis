<?php

require_once __DIR__ . '/inventory-reporting.php';
require_once __DIR__ . '/jazz-oms.php';

const JAZZ_ORDER_STATUSES = ['NEW', 'ALLOCATED', 'PRINTED', 'SHIPPED', 'CANCELED', 'CLOSED'];

const JAZZ_ORDER_LIST_COLUMNS = [
    'order_number',
    'order_date',
    'status',
    'qty_ordered',
    'qty_shipped',
    'source_code',
    'po_number',
];

function jazz_order_report_require_read(): void
{
    auth_require_login();
    if (inventory_reporting_can_read()) {
        return;
    }
    auth_render_access_denied('You do not have permission to view Jazz Orders.');
}

function jazz_order_report_filters_from_query(array $query): array
{
    $filters = [];
    foreach (['status', 'order_number', 'order_date', 'po_number', 'start_date', 'end_date', 'customer_number'] as $key) {
        $value = trim((string) ($query[$key] ?? ''));
        if ($value !== '') {
            $filters[$key] = $value;
        }
    }

    return $filters;
}

function jazz_order_report_list_href(array $filters = []): string
{
    $query = array_filter($filters, static fn($value): bool => trim((string) $value) !== '');

    return '/jazz-orders/' . ($query === [] ? '' : '?' . http_build_query($query));
}

function jazz_order_report_detail_href(string $orderNumber): string
{
    return '/jazz-orders/order.php?order=' . rawurlencode(trim($orderNumber));
}

/**
 * @param list<array<string, mixed>> $rows
 * @return list<string>
 */
function jazz_order_report_columns(array $rows): array
{
    return jazz_oms_record_columns($rows, JAZZ_ORDER_LIST_COLUMNS);
}

function jazz_order_report_status_class(string $status): string
{
    return match (strtoupper(trim($status))) {
        'NEW'       => 'status-badge-open',
        'ALLOCATED', 'PRINTED' => 'status-badge-pending',
        'SHIPPED', 'CLOSED' => 'status-badge-active',
        'CANCELED'  => 'status-badge-inactive',
        default     => 'status-badge',
    };
}

/**
 * @param list<array<string, mixed>> $details
 * @return list<string>
 */
function jazz_order_report_detail_columns(array $details): array
{
    return jazz_oms_record_columns($details, [
        'line_number',
        'sku_code',
        'qty_ordered',
        'qty_allocated',
        'qty_shipped',
        'unit_price',
    ]);
}
