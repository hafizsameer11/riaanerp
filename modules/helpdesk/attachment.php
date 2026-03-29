<?php
require_once __DIR__ . '/includes/bootstrap.php';
hd_require('dashboard');

$aid = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$stmt = $conn->prepare('SELECT a.*, t.merged_into_ticket_id FROM helpdesk_attachments a JOIN helpdesk_tickets t ON t.id = a.ticket_id WHERE a.id = ? LIMIT 1');
$stmt->bind_param('i', $aid);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || $row['merged_into_ticket_id']) {
    http_response_code(404);
    die('Not found.');
}

$tid = (int) $row['ticket_id'];
$t = $conn->query('SELECT assigned_user_id FROM helpdesk_tickets WHERE id = ' . $tid)->fetch_assoc();
if (!$t || !hd_may_view_ticket_as_staff($t['assigned_user_id'] ?? null)) {
    http_response_code(403);
    die('Access denied.');
}

$path = realpath(HELPDESK_ROOT . '/' . $row['stored_path']);
$base = realpath(HELPDESK_UPLOAD_DIR);
if ($path === false || $base === false || strpos($path, $base) !== 0) {
    http_response_code(404);
    die('Invalid path.');
}

header('Content-Type: ' . ($row['mime_type'] ?: 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . basename($row['filename']) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
