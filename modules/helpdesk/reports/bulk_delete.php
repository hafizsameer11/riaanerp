<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
hd_require('bulk delete');

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['confirm'])) {
    $ids = isset($_POST['ticket_ids']) ? array_map('intval', (array) $_POST['ticket_ids']) : [];
    $ids = array_filter($ids);
    if (count($ids) === 0) {
        $d1 = trim($_POST['closed_from'] ?? '');
        $d2 = trim($_POST['closed_to'] ?? '');
        if ($d1 !== '' && $d2 !== '') {
            $res = $conn->query("SELECT id FROM helpdesk_tickets WHERE status = 'closed' AND merged_into_ticket_id IS NULL AND closed_at >= '" . $conn->real_escape_string($d1) . " 00:00:00' AND closed_at <= '" . $conn->real_escape_string($d2) . " 23:59:59'");
            while ($r = $res->fetch_assoc()) {
                $ids[] = (int) $r['id'];
            }
        }
    }
    $deleted = 0;
    foreach ($ids as $tid) {
        $t = $conn->query("SELECT id FROM helpdesk_tickets WHERE id = $tid AND status = 'closed' AND merged_into_ticket_id IS NULL LIMIT 1")->fetch_assoc();
        if (!$t) {
            continue;
        }
        $atts = $conn->query('SELECT stored_path FROM helpdesk_attachments WHERE ticket_id = ' . $tid);
        while ($a = $atts->fetch_assoc()) {
            $p = realpath(HELPDESK_ROOT . '/' . $a['stored_path']);
            $b = realpath(HELPDESK_UPLOAD_DIR);
            if ($p && $b && strpos($p, $b) === 0 && is_file($p)) {
                @unlink($p);
            }
        }
        $conn->query('DELETE FROM helpdesk_attachments WHERE ticket_id = ' . $tid);
        $conn->query('DELETE FROM helpdesk_messages WHERE ticket_id = ' . $tid);
        $conn->query('DELETE FROM helpdesk_timesheets WHERE ticket_id = ' . $tid);
        $conn->query('DELETE FROM helpdesk_ticket_services WHERE ticket_id = ' . $tid);
        $conn->query('DELETE FROM helpdesk_tickets WHERE id = ' . $tid);
        $deleted++;
    }
    $msg = "Deleted $deleted ticket(s).";
}

$candidates = $conn->query("SELECT id, subject, closed_at FROM helpdesk_tickets WHERE status = 'closed' AND merged_into_ticket_id IS NULL ORDER BY closed_at DESC LIMIT 500");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bulk delete</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <?= hd_ui_css() ?>
</head>
<body class="py-4">
<div class="container-fluid hd-shell">
    <header class="hd-page-hero mb-3">
        <h3 class="hd-heading mb-1">Bulk delete closed tickets</h3>
        <p class="text-muted small mb-0" style="max-width:42rem">Permanent cleanup for very old closed calls. Removes ticket rows, messages, timesheets, service line, and attachment files from disk.</p>
    </header>
    <?php if ($msg): ?><div class="alert alert-success"><?= hd_esc($msg) ?></div><?php endif; ?>

    <div class="card hd-filter-card hd-card mb-4">
        <div class="card-header">By closed date range</div>
        <div class="card-body">
            <form method="post" class="row g-3 align-items-end" onsubmit="return confirm('Permanently delete all closed tickets in this range?');">
                <input type="hidden" name="confirm" value="1">
                <div class="col-auto"><label class="form-label">From</label><input type="date" name="closed_from" class="form-control" required></div>
                <div class="col-auto"><label class="form-label">To</label><input type="date" name="closed_to" class="form-control" required></div>
                <div class="col-auto"><button type="submit" class="btn btn-danger fw-semibold">Delete range</button></div>
            </form>
        </div>
    </div>

    <div class="card hd-table-card mb-3">
        <div class="card-header fw-semibold">Or select tickets</div>
        <div class="card-body p-0">
            <form method="post" onsubmit="return confirm('Delete selected tickets?');">
                <input type="hidden" name="confirm" value="1">
                <div class="hd-table-wrap" style="max-height:400px;overflow:auto">
                    <table class="table table-striped table-hover table-sm hd-data-table mb-0">
                        <thead>
                            <tr>
                                <th style="width:2.5rem"></th>
                                <th>ID</th>
                                <th>Subject</th>
                                <th>Closed</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($c = $candidates->fetch_assoc()): ?>
                            <tr>
                                <td><input type="checkbox" name="ticket_ids[]" value="<?= (int) $c['id'] ?>" class="form-check-input mt-0"></td>
                                <td>#<?= (int) $c['id'] ?></td>
                                <td><?= hd_esc($c['subject']) ?></td>
                                <td><?= hd_esc($c['closed_at']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-body border-top py-3">
                    <button type="submit" class="btn btn-danger fw-semibold">Delete selected</button>
                </div>
            </form>
        </div>
    </div>

    <a href="index.php" class="btn btn-outline-secondary px-3">Back to reports</a>
</div>
</body>
</html>
