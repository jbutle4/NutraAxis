<?php

/**
 * Bootstrap for public provider-facing pages under /provider-signup/.
 * Does not load Operations Portal chrome (head.php / header.php).
 */
require_once __DIR__ . '/subdomain-routing.php';
subdomain_routing_apply();
