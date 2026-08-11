<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/page-data-profile.php';

$activeSlug = $activeSlug ?? 'sales-rep-reporting';
$reportListPath = data_profile_page_path('/sales-reporting/sales-rep-reporting/');
$orderDetailPath = data_profile_page_path('/sales-reporting/sales-rep-reporting/view.php');
$orderDetailBackLabel = 'Back to Sales Rep Reporting' . (data_profile_is_uat() ? ' (UAT)' : '');
$orderDetailTitleSuffix = 'Sales Rep Reporting';

require dirname(__DIR__) . '/order.php';
