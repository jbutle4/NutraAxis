<?php
require_once dirname(__DIR__, 2) . '/includes/approval.php';

header('Location: ' . approval_index_url('Payment', 'pending'), true, 302);
exit;
