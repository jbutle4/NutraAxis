<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/adobe-commerce.php';

const SALES_REPORTING_PERMISSION_COLUMN = 'SalesReporting';

function sales_reporting_permission_value(): ?string
{
    return auth_permission_value(SALES_REPORTING_PERMISSION_COLUMN);
}

function sales_reporting_can_read(): bool
{
    return auth_can_read(SALES_REPORTING_PERMISSION_COLUMN);
}

function sales_reporting_require_read(): void
{
    auth_require_login();
    if (sales_reporting_can_read()) {
        return;
    }
    auth_render_access_denied('You do not have permission to view Sales Reporting.');
}
