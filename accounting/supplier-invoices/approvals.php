<?php
require_once dirname(__DIR__, 2) . '/includes/approval.php';

header('Location: ' . approval_index_url('QBOInsert', 'pending'), true, 302);
exit;
