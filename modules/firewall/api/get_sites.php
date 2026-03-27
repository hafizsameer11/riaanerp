<?php
/**
 * AJAX Endpoint: Get Sites Data (JSON)
 * Returns site data in JSON format for auto-refresh functionality
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$status = $_GET['status'] ?? null;
$search = $_GET['search'] ?? null;

$sites = getSites($status, $search);

// Format sites data for JSON response
$response = [
    'success' => true,
    'sites' => [],
    'timestamp' => date('Y-m-d H:i:s')
];

function statusColor($s) {
    return [
        'UP'       => '#10b981',
        'DEGRADED' => '#f59e0b',
        'DOWN'     => '#ef4444',
        'UNKNOWN'  => '#6b7280'
    ][$s] ?? '#6b7280';
}

foreach ($sites as $s) {
    $response['sites'][] = [
        'id' => $s['id'],
        'name' => htmlspecialchars($s['name']),
        'location' => htmlspecialchars($s['location'] ?? 'N/A'),
        'primary_ip' => $s['primary_ip'],
        'status' => $s['status'],
        'status_color' => statusColor($s['status']),
        'ping_count' => $s['ping_count'] ?: PING_DEFAULT_COUNT,
        'ping_interval_sec' => $s['ping_interval_sec'] ?: PING_DEFAULT_BATCH_INTERVAL_SEC,
        'monitoring_enabled' => (bool)$s['monitoring_enabled']
    ];
}

echo json_encode($response);

