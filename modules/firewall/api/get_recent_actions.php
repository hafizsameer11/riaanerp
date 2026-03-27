<?php
/**
 * AJAX Endpoint: Get Recent Automatic Actions (JSON)
 * Returns recent auto_failover and auto_failback actions for dashboard display
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

try {
    // Get recent actions (both automatic and manual)
    // Show auto_failover, auto_failback, and also manual failover/failback for visibility
    // Note: LIMIT must be an integer, not a bound parameter in some MySQL/MariaDB versions
    $limit = (int)$limit; // Ensure it's an integer
    $stmt = db()->prepare("
        SELECT 
            a.*,
            s.name AS site_name,
            s.primary_ip,
            s.location
        FROM site_actions a
        JOIN sites s ON a.site_id = s.id
        WHERE a.action_type IN ('auto_failover', 'auto_failback', 'failover', 'failback', 'custom')
        ORDER BY a.created_at DESC
        LIMIT " . $limit . "
    ");
    $stmt->execute();
    $actions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format actions for JSON response
    $formattedActions = [];
    foreach ($actions as $action) {
        // Determine if action is automatic or manual
        $isAutomatic = in_array($action['action_type'], ['auto_failover', 'auto_failback']);
        $actionLabel = $isAutomatic 
            ? ($action['action_type'] === 'auto_failover' ? 'Auto Failover' : 'Auto Failback')
            : ($action['action_type'] === 'custom' ? 'Custom Command' : ucfirst($action['action_type']));
        
        $formattedActions[] = [
            'id' => $action['id'],
            'site_name' => htmlspecialchars($action['site_name']),
            'location' => htmlspecialchars($action['location'] ?? 'N/A'),
            'primary_ip' => $action['primary_ip'],
            'action_type' => $action['action_type'],
            'action_label' => $actionLabel,
            'is_automatic' => $isAutomatic,
            'status_before' => $action['status_before'],
            'status_after' => $action['status_after'],
            'ssh_success' => (bool)$action['ssh_success'],
            'ssh_output' => htmlspecialchars($action['ssh_output'] ?? ''),
            'initiated_by' => htmlspecialchars($action['initiated_by'] ?? 'system'),
            'created_at' => $action['created_at'],
            'created_at_formatted' => date('M d, Y H:i:s', strtotime($action['created_at'])),
            'created_at_relative' => getRelativeTime($action['created_at'])
        ];
    }

    echo json_encode([
        'success' => true,
        'actions' => $formattedActions,
        'count' => count($formattedActions),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'actions' => [],
        'count' => 0
    ]);
}

/**
 * Get relative time (e.g., "2 minutes ago", "1 hour ago")
 */
function getRelativeTime($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M d, Y', $timestamp);
    }
}

