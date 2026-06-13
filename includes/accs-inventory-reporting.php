<?php

require_once __DIR__ . '/inventory-reporting.php';
require_once __DIR__ . '/adobe-commerce.php';

function accs_inventory_reporting_permission_value(): ?string
{
    return inventory_reporting_permission_value();
}

function accs_inventory_reporting_can_read(): bool
{
    return inventory_reporting_can_read();
}

function accs_inventory_reporting_require_read(): void
{
    auth_require_login();
    if (accs_inventory_reporting_can_read()) {
        return;
    }
    auth_render_access_denied('You do not have permission to view ACCS Inventory Reporting.');
}
