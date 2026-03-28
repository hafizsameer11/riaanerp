<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
hd_require('reports');

$d1 = trim($_GET['date_from'] ?? date('Y-m-01'));
$d2 = trim($_GET['date_to'] ?? date('Y-m-d'));

$where = " t.merged_into_ticket_id IS NULL AND t.status = 'closed' ";
$where .= " AND t.closed_at >= '" . $conn->real_escape_string($d1) . " 00:00:00' ";
$where .= " AND t.closed_at <= '" . $conn->real_escape_string($d2) . " 23:59:59' ";

$sql = "
    SELECT t.id, t.subject, t.created_at, t.closed_at,
        c.client_name, rq.email AS requester_email,
        u.name AS tech_fn, u.surname AS tech_sn,
        s.item_name, s.unit_price, s.currency,
        (SELECT SUM(TIMESTAMPDIFF(MINUTE, ts.started_at, ts.ended_at)) FROM helpdesk_timesheets ts WHERE ts.ticket_id = t.id AND ts.ended_at IS NOT NULL) AS minutes_spent,
        (SELECT GROUP_CONCAT(m.body SEPARATOR ' | ') FROM helpdesk_messages m WHERE m.ticket_id = t.id AND m.add_to_worklog = 1 LIMIT 5) AS worklog_bits
    FROM helpdesk_tickets t
    LEFT JOIN clients c ON c.id = t.client_id
    LEFT JOIN helpdesk_requesters rq ON rq.id = t.requester_id
    LEFT JOIN registers u ON u.id = t.assigned_user_id
    LEFT JOIN helpdesk_ticket_services s ON s.ticket_id = t.id
    WHERE $where
    ORDER BY t.id DESC
";
$rows = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Billing helpdesk report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <?= hd_ui_css() ?>
</head>
<body class="p-4">
<div class="container-fluid">
    <h3 class="hd-page-title mb-1">Billing report</h3>
    <p class="hd-page-subtitle">Closed tickets with time spent, worklog summary and service pricing.</p>
    <form class="row g-2 mb-3" method="get">
        <div class="col-auto"><input type="date" name="date_from" class="form-control" value="<?= hd_esc($d1) ?>"></div>
        <div class="col-auto"><input type="date" name="date_to" class="form-control" value="<?= hd_esc($d2) ?>"></div>
        <div class="col-auto"><button class="btn btn-primary">Filter</button></div>
    </form>
    <div class="table-responsive">
        <table class="table table-sm table-striped bg-white shadow-sm">
            <thead>
                <tr>
                    <th>Helpdesk ID</th>
                    <th>Client</th>
                    <th>Time (min)</th>
                    <th>Created</th>
                    <th>Closed</th>
                    <th>Technician</th>
                    <th>Subject</th>
                    <th>Worklog</th>
                    <th>Service</th>
                    <th>Unit price</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($r = $rows->fetch_assoc()):
                $tech = trim(($r['tech_fn'] ?? '') . ' ' . ($r['tech_sn'] ?? ''));
                ?>
                <tr>
                    <td><?= (int) $r['id'] ?></td>
                    <td><?= hd_esc($r['client_name']) ?></td>
                    <td><?= (int) ($r['minutes_spent'] ?? 0) ?></td>
                    <td><?= hd_esc($r['created_at']) ?></td>
                    <td><?= hd_esc($r['closed_at']) ?></td>
                    <td><?= hd_esc($tech) ?></td>
                    <td><?= hd_esc($r['subject']) ?></td>
                    <td><?= hd_esc(substr($r['worklog_bits'] ?? '', 0, 120)) ?></td>
                    <td><?= hd_esc($r['item_name'] ?? '') ?></td>
                    <td><?= hd_esc(($r['unit_price'] ?? '') . ' ' . ($r['currency'] ?? '')) ?></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <a href="index.php" class="btn btn-secondary">Back</a>
</div>
</body>
</html>
