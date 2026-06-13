<?php
require dirname(__DIR__) . '/includes/init.php';

auth_logout();
header('Location: /', true, 302);
exit;
