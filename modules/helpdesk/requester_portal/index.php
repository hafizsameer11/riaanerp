<?php
session_start();
require_once dirname(__DIR__, 3) . '/config.php';

if (!isset($_SESSION['requester_register_id'])) {
    header('Location: login.php');
    exit;
}

$rid = (int) $_SESSION['requester_register_id'];
$req = $conn->query('SELECT id, name, email FROM helpdesk_requesters WHERE register_id = ' . $rid)->fetch_assoc();
if (!$req) {
    session_destroy();
    header('Location: login.php');
    exit;
}
$qid = (int) $req['id'];

$tickets = $conn->query("SELECT id, subject, status, created_at FROM helpdesk_tickets WHERE requester_id = $qid AND merged_into_ticket_id IS NULL ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My requests</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body{background:#eef3f9;font-family:Inter,Arial,sans-serif}
        .portal-card{border:0;border-radius:12px;box-shadow:0 8px 24px rgba(16,24,40,.08)}
        .badge-open{background:#e7f4ff;color:#14508f}
        .badge-hold{background:#fff4df;color:#8a5a00}
        .badge-closed{background:#e7f8ef;color:#166534}
    </style>
</head>
<body class="p-4">
<div class="container">
    <div class="d-flex justify-content-between mb-3">
        <div>
            <h4 class="mb-0 fw-bold">Hello, <?= htmlspecialchars($req['name']) ?></h4>
            <small class="text-muted">Your tickets only</small>
        </div>
        <a href="logout.php" class="btn btn-outline-secondary">Logout</a>
    </div>
    <table class="table table-striped bg-white portal-card">
        <thead><tr><th>ID</th><th>Subject</th><th>Status</th><th>Created</th><th></th></tr></thead>
        <tbody>
        <?php while ($t = $tickets->fetch_assoc()): ?>
            <tr>
                <td>#<?= (int) $t['id'] ?></td>
                <td><?= htmlspecialchars($t['subject']) ?></td>
                <td>
                    <?php $st = (string) $t['status']; $cls = $st === 'closed' ? 'badge-closed' : ($st === 'on_hold' ? 'badge-hold' : 'badge-open'); ?>
                    <span class="badge <?= $cls ?>"><?= htmlspecialchars($st) ?></span>
                </td>
                <td><?= htmlspecialchars($t['created_at']) ?></td>
                <td><a class="btn btn-sm btn-outline-primary" href="ticket.php?id=<?= (int) $t['id'] ?>">View</a></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
</body>
</html>
