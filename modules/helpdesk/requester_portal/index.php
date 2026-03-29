<?php
session_start();
require_once dirname(__DIR__, 3) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

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
    <?= hd_ui_css() ?>
    <style>
        .portal-badge-open{background:#e7f4ff;color:#14508f;font-weight:600;padding:.35em .65em;border-radius:999px;font-size:.75rem}
        .portal-badge-hold{background:#fff4df;color:#8a5a00;font-weight:600;padding:.35em .65em;border-radius:999px;font-size:.75rem}
        .portal-badge-closed{background:#e7f8ef;color:#166534;font-weight:600;padding:.35em .65em;border-radius:999px;font-size:.75rem}
    </style>
</head>
<body class="py-4">
<div class="hd-shell">
    <header class="hd-page-hero mb-3 d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
            <h4 class="hd-heading mb-1" style="font-size:1.35rem">Hello, <?= htmlspecialchars($req['name']) ?></h4>
            <p class="text-muted small mb-0">Your tickets only</p>
        </div>
        <a href="logout.php" class="btn btn-outline-secondary px-3">Logout</a>
    </header>
    <div class="card hd-table-card">
        <div class="card-header fw-semibold">My requests</div>
        <div class="card-body p-0">
            <div class="hd-table-wrap">
                <table class="table table-striped table-hover hd-data-table mb-0">
                    <thead><tr><th>ID</th><th>Subject</th><th>Status</th><th>Created</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    <?php while ($t = $tickets->fetch_assoc()): ?>
                        <tr>
                            <td>#<?= (int) $t['id'] ?></td>
                            <td><?= htmlspecialchars($t['subject']) ?></td>
                            <td>
                                <?php $st = (string) $t['status']; $cls = $st === 'closed' ? 'portal-badge-closed' : ($st === 'on_hold' ? 'portal-badge-hold' : 'portal-badge-open'); ?>
                                <span class="<?= $cls ?>"><?= htmlspecialchars($st) ?></span>
                            </td>
                            <td><?= htmlspecialchars($t['created_at']) ?></td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="ticket.php?id=<?= (int) $t['id'] ?>">View</a></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>
