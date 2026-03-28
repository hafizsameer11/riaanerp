<?php
session_start();
require_once dirname(__DIR__, 3) . '/config.php';

if (!isset($_SESSION['requester_register_id'])) {
    header('Location: login.php');
    exit;
}

$rid = (int) $_SESSION['requester_register_id'];
$req = $conn->query('SELECT id FROM helpdesk_requesters WHERE register_id = ' . $rid)->fetch_assoc();
if (!$req) {
    header('Location: login.php');
    exit;
}
$qid = (int) $req['id'];

$tid = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$t = $conn->query("SELECT * FROM helpdesk_tickets WHERE id = $tid AND requester_id = $qid AND merged_into_ticket_id IS NULL LIMIT 1")->fetch_assoc();
if (!$t) {
    die('Not found.');
}

$msgs = $conn->query("SELECT * FROM helpdesk_messages WHERE ticket_id = $tid AND (direction = 'in' OR (direction = 'out' AND is_client_visible = 1)) ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Ticket #<?= $tid ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body{background:#eef3f9;font-family:Inter,Arial,sans-serif}
        .msg{border:1px solid #e5ecf5;border-radius:10px;padding:10px;background:#fff;margin-bottom:10px}
    </style>
</head>
<body class="p-4">
<div class="container">
    <a href="index.php" class="btn btn-sm btn-secondary mb-2">Back</a>
    <h4 class="fw-bold">Ticket #<?= $tid ?> — <?= htmlspecialchars($t['subject']) ?></h4>
    <p class="text-muted">Status: <?= htmlspecialchars($t['status']) ?></p>
    <?php while ($m = $msgs->fetch_assoc()): ?>
        <div class="msg">
            <small><?= htmlspecialchars($m['created_at']) ?></small>
            <div><?= nl2br(htmlspecialchars($m['body'])) ?></div>
        </div>
    <?php endwhile; ?>
</div>
</body>
</html>
