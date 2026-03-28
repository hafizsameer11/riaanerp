<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
hd_require('reports');

$type = $_GET['type'] ?? 'open';
if (!in_array($type, ['open', 'closed', 'on_hold', 'overdue'], true)) {
    $type = 'open';
}

$tech = isset($_GET['tech']) && ctype_digit((string) $_GET['tech']) ? (int) $_GET['tech'] : null;

$where = ' t.merged_into_ticket_id IS NULL ';
if ($type === 'open') {
    $where .= " AND t.status = 'open' ";
} elseif ($type === 'closed') {
    $where .= " AND t.status = 'closed' ";
} elseif ($type === 'on_hold') {
    $where .= " AND t.status = 'on_hold' ";
} else {
    $where .= " AND t.is_overdue = 1 AND t.status != 'closed' ";
}

if ($tech !== null) {
    $where .= ' AND t.assigned_user_id = ' . $tech . ' ';
}

$d1 = trim($_GET['date_from'] ?? '');
$d2 = trim($_GET['date_to'] ?? '');
if ($d1 !== '') {
    $where .= " AND t.created_at >= '" . $conn->real_escape_string($d1) . " 00:00:00' ";
}
if ($d2 !== '') {
    $where .= " AND t.created_at <= '" . $conn->real_escape_string($d2) . " 23:59:59' ";
}

$sql = "
    SELECT t.id, t.subject, t.status, t.on_hold_reason, t.created_at, t.closed_at, t.due_at, t.is_overdue,
        c.client_name, rq.name AS requester_name, rq.email,
        u.name AS tech_fn, u.surname AS tech_sn
    FROM helpdesk_tickets t
    LEFT JOIN clients c ON c.id = t.client_id
    LEFT JOIN helpdesk_requesters rq ON rq.id = t.requester_id
    LEFT JOIN registers u ON u.id = t.assigned_user_id
    WHERE $where
    ORDER BY t.id DESC
";
$res = $conn->query($sql);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="helpdesk_' . $type . '.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['ID', 'Subject', 'Status', 'On Hold Reason', 'Client', 'Requester', 'Email', 'Technician', 'Created', 'Closed', 'Due', 'Overdue']);
while ($row = $res->fetch_assoc()) {
    $techNm = trim(($row['tech_fn'] ?? '') . ' ' . ($row['tech_sn'] ?? ''));
    fputcsv($out, [
        $row['id'],
        $row['subject'],
        $row['status'],
        $row['on_hold_reason'],
        $row['client_name'],
        $row['requester_name'],
        $row['email'],
        $techNm,
        $row['created_at'],
        $row['closed_at'],
        $row['due_at'],
        $row['is_overdue'],
    ]);
}
fclose($out);
exit;
