<?php
/**
 * Helpdesk module bootstrap (loaded by every helpdesk page).
 */
if (!defined('HELPDESK_ROOT')) {
    define('HELPDESK_ROOT', dirname(__DIR__));
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once HELPDESK_ROOT . '/../../config.php';
require_once HELPDESK_ROOT . '/../components/permissioncheck.php';
require_once __DIR__ . '/functions.php';
