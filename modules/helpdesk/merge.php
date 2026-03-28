<?php
require_once __DIR__ . '/includes/bootstrap.php';
hd_require('merge tickets');

$err = '';
$ok = '';
$returnTo = trim($_POST['return_to'] ?? $_GET['return_to'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $from = (int) ($_POST['from_id'] ?? 0);
    $into = (int) ($_POST['into_id'] ?? 0);
    if ($from < 1 || $into < 1 || $from === $into) {
        $err = 'Select two distinct ticket IDs.';
    } else {
        $a = $conn->query("SELECT id FROM helpdesk_tickets WHERE id = $from AND merged_into_ticket_id IS NULL")->fetch_assoc();
        $b = $conn->query("SELECT id FROM helpdesk_tickets WHERE id = $into AND merged_into_ticket_id IS NULL")->fetch_assoc();
        if (!$a || !$b) {
            $err = 'Invalid tickets or already merged.';
        } else {
            $conn->begin_transaction();
            try {
                if (!$conn->query("UPDATE helpdesk_messages SET ticket_id = $into WHERE ticket_id = $from")) {
                    throw new RuntimeException('Failed to move messages.');
                }
                if (!$conn->query("UPDATE helpdesk_attachments SET ticket_id = $into WHERE ticket_id = $from")) {
                    throw new RuntimeException('Failed to move attachments.');
                }
                if (!$conn->query("UPDATE helpdesk_timesheets SET ticket_id = $into WHERE ticket_id = $from")) {
                    throw new RuntimeException('Failed to move timesheets.');
                }

                // Service row can conflict (unique by ticket_id): keep target row if already exists.
                $srcSvc = $conn->query("SELECT id, item_name, unit_price, currency FROM helpdesk_ticket_services WHERE ticket_id = $from LIMIT 1")->fetch_assoc();
                $dstSvc = $conn->query("SELECT id FROM helpdesk_ticket_services WHERE ticket_id = $into LIMIT 1")->fetch_assoc();
                if ($srcSvc) {
                    if ($dstSvc) {
                        $item = $conn->real_escape_string((string) ($srcSvc['item_name'] ?? ''));
                        $note = 'Merge note: source ticket #' . $from . ' had service "' . $item . '" ' . ($srcSvc['unit_price'] ?? '') . ' ' . ($srcSvc['currency'] ?? '');
                        $noteEsc = $conn->real_escape_string($note);
                        $uid = (int) $_SESSION['user_id'];
                        $conn->query("INSERT INTO helpdesk_messages (ticket_id, direction, body, is_client_visible, created_by_user_id) VALUES ($into, 'internal', '$noteEsc', 0, $uid)");
                        $conn->query("DELETE FROM helpdesk_ticket_services WHERE ticket_id = $from");
                    } else {
                        if (!$conn->query("UPDATE helpdesk_ticket_services SET ticket_id = $into WHERE ticket_id = $from")) {
                            throw new RuntimeException('Failed to move service row.');
                        }
                    }
                }

                if (!$conn->query("UPDATE helpdesk_tickets SET status = 'closed', closed_at = NOW(), merged_into_ticket_id = $into WHERE id = $from")) {
                    throw new RuntimeException('Failed to close source ticket.');
                }
                $uid = (int) $_SESSION['user_id'];
                $stmt = $conn->prepare('INSERT INTO helpdesk_merge_audit (from_ticket_id, into_ticket_id, user_id) VALUES (?, ?, ?)');
                $stmt->bind_param('iii', $from, $into, $uid);
                $stmt->execute();
                $stmt->close();
                $conn->commit();
                $ok = "Merged ticket #$from into #$into.";
                if ($returnTo !== '') {
                    $sep = strpos($returnTo, '?') === false ? '?' : '&';
                    header('Location: ' . $returnTo . $sep . 'merge_ok=1');
                    exit;
                }
            } catch (Throwable $e) {
                $conn->rollback();
                $err = 'Merge failed: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Merge tickets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <?= hd_ui_css() ?>
</head>
<body class="p-4">
<div class="container" style="max-width:560px">
    <h3 class="hd-page-title mb-1">Merge tickets</h3>
    <p class="text-muted">Moves messages, attachments, timesheets and service row from the source ticket into the target ticket, then closes the source ticket.</p>
    <?php if ($err): ?><div class="alert alert-danger"><?= hd_esc($err) ?></div><?php endif; ?>
    <?php if ($ok): ?><div class="alert alert-success"><?= hd_esc($ok) ?></div><?php endif; ?>
    <form method="post" class="card card-body hd-card">
        <?php if ($returnTo !== ''): ?><input type="hidden" name="return_to" value="<?= hd_esc($returnTo) ?>"><?php endif; ?>
        <div class="mb-2">
            <label class="form-label">From ticket ID (source)</label>
            <input type="number" name="from_id" class="form-control" required min="1">
        </div>
        <div class="mb-2">
            <label class="form-label">Into ticket ID (keep this ticket)</label>
            <input type="number" name="into_id" class="form-control" required min="1">
        </div>
        <button type="submit" class="btn btn-warning">Merge</button>
        <a href="index.php" class="btn btn-secondary">Back</a>
    </form>
</div>
</body>
</html>
