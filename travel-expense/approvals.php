<?php
require_once dirname(__DIR__) . '/includes/approval.php';

header('Location: ' . approval_index_url('TE', 'pending'), true, 302);
exit;
