<?php

// ---------------------
// DATABASE CONFIG
// ---------------------
// define('DB_HOST', '127.0.0.1');
// define('DB_NAME', 'firewall_monitor');
// define('DB_USER', 'root');     // mac default: root
// define('DB_PASS', '');         // mac default: empty

define('DB_HOST', 'localhost');
define('DB_NAME', 'firewall_monitor');
define('DB_USER', 'firewall_user');     // mac default: root
define('DB_PASS', 'StrongPass#2025');

// ---------------------
// SYSTEM ENCRYPTION KEY
// ---------------------
define('ENC_KEY', 'G7fP9xL2tQ8wR4kB1mZ6uH3cV0aN5sD');

// ---------------------
// MONITORING DEFAULTS
// ---------------------
define('PING_DEFAULT_COUNT', 10);              // Number of pings per batch
define('PING_DEFAULT_BATCH_INTERVAL_SEC', 60);  // Seconds to wait between batches

// ---------------------
// SMTP CONFIG (NO AUTH, NO TLS)
// ---------------------
define('SMTP_HOST', '156.38.197.98');
define('SMTP_PORT', 587);
define('SMTP_FROM', 'monitor@system.local');
define('SMTP_FROM_NAME', 'Firewall Monitor');

// ---------------------
// SSH CONFIG
// ---------------------
define('SSH_PORT', 22);

// ---------------------
// AUTHENTICATION
// ---------------------
define('PIN_CODE', '4683'); // Change this to match your ERP system PIN

