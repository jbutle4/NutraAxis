<?php

require_once __DIR__ . '/auth.php';
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
