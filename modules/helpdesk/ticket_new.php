<?php
require_once __DIR__ . '/includes/bootstrap.php';
hd_require('create ticket');
require_once __DIR__ . '/includes/NotificationService.php';

$err = '';
$ok = '';

function hd_next_working_day_due()
{
    $t = strtotime('+1 day');
    while ((int) date('N', $t) >= 6) {
        $t = strtotime('+1 day', $t);
    }
    return date('Y-m-d 17:00:00', $t);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = (int) ($_POST['client_id'] ?? 0) ?: null;
    $requester_id = (int) ($_POST['requester_id'] ?? 0) ?: null;
    $assigned_user_id = isset($_POST['assigned_user_id']) && $_POST['assigned_user_id'] !== '' ? (int) $_POST['assigned_user_id'] : null;
    $due_raw = trim($_POST['due_at'] ?? '');
    $due_at = $due_raw !== '' ? $due_raw : hd_next_working_day_due();
    $subject = trim($_POST['subject'] ?? '');
    if ($subject === '') {
        $subject = '(no subject)';
    }
    $description = trim($_POST['description'] ?? '');
    $randomize = hd_can('randomize assignment');

    if (!$requester_id) {
        $err = 'Requester is required.';
    } else {
        if (!$client_id) {
            $rqRow = $conn->query('SELECT client_id FROM helpdesk_requesters WHERE id = ' . (int) $requester_id)->fetch_assoc();
            if ($rqRow && !empty($rqRow['client_id'])) {
                $client_id = (int) $rqRow['client_id'];
            }
        }
        if ($randomize) {
            $pool = hd_randomize_pool_user_ids($conn);
            if (count($pool) === 0) {
                $pool = hd_technician_pool_user_ids($conn);
            }
            $assigned_user_id = hd_pick_random_assignee($conn, $pool);
        }

        $cidSql = $client_id ? (int) $client_id : 'NULL';
        $aidSql = $assigned_user_id !== null ? (int) $assigned_user_id : 'NULL';
        $subEsc = "'" . $conn->real_escape_string($subject) . "'";
        $dueEsc = "'" . $conn->real_escape_string($due_at) . "'";
        $randFlag = $randomize ? 1 : 0;
        $sqlIns = "INSERT INTO helpdesk_tickets (client_id, requester_id, assigned_user_id, status, subject, due_at, source, random_assigned) VALUES ($cidSql, " . (int) $requester_id . ", $aidSql, 'open', $subEsc, $dueEsc, 'manual', $randFlag)";
        if (!$conn->query($sqlIns)) {
            $err = 'Could not create ticket: ' . $conn->error;
        } else {
            $tid = (int) $conn->insert_id;

            if ($description !== '') {
                $stmt = $conn->prepare('INSERT INTO helpdesk_messages (ticket_id, direction, body, is_client_visible, add_to_worklog, created_by_user_id) VALUES (?, \'in\', ?, 1, 0, ?)');
                $uid = (int) $_SESSION['user_id'];
                $stmt->bind_param('isi', $tid, $description, $uid);
                $stmt->execute();
                $stmt->close();
            }

            if (!empty($_FILES['attach']) && is_array($_FILES['attach']['name'])) {
                if (!is_dir(HELPDESK_UPLOAD_DIR)) {
                    mkdir(HELPDESK_UPLOAD_DIR, 0755, true);
                }
                $names = $_FILES['attach']['name'];
                $tmp = $_FILES['attach']['tmp_name'];
                $errs = $_FILES['attach']['error'];
                $count = count($names);
                for ($i = 0; $i < $count; $i++) {
                    if ($errs[$i] !== UPLOAD_ERR_OK || !is_uploaded_file($tmp[$i])) {
                        continue;
                    }
                    $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($names[$i]));
                    $destName = $tid . '_' . time() . '_' . $safe;
                    $destPath = HELPDESK_UPLOAD_DIR . '/' . $destName;
                    if (move_uploaded_file($tmp[$i], $destPath)) {
                        $sz = (int) filesize($destPath);
                        $stmt = $conn->prepare('INSERT INTO helpdesk_attachments (ticket_id, filename, stored_path, size_bytes) VALUES (?, ?, ?, ?)');
                        $rel = 'uploads/' . $destName;
                        $stmt->bind_param('issi', $tid, $safe, $rel, $sz);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }

            hd_recalc_overdue_for_ticket($conn, $tid);

            $cfg = hd_get_mail_config_row($conn);
            if ($cfg && $cfg['smtp_host']) {
                require_once __DIR__ . '/includes/MailService.php';
                $rq = $conn->query('SELECT name, email FROM helpdesk_requesters WHERE id = ' . (int) $requester_id)->fetch_assoc();
                if ($rq && filter_var($rq['email'], FILTER_VALIDATE_EMAIL)) {
                    HelpdeskMailService::sendTemplate($conn, $cfg, $rq['email'], 'ticket_created', [
                        'ticket_id' => (string) $tid,
                        'subject' => $subject,
                        'requester_name' => $rq['name'],
                    ], $tid, null, null);
                }
            }

            HelpdeskNotificationService::trigger($conn, 'ticket_created', $tid);

            header('Location: ticket_view.php?id=' . $tid);
            exit;
        }
    }
}

$clients = $conn->query('SELECT id, client_name FROM clients ORDER BY client_name');
$requesters = $conn->query('SELECT r.id, r.name, r.email, c.client_name FROM helpdesk_requesters r LEFT JOIN clients c ON c.id = r.client_id ORDER BY r.name');
$techs = $conn->query('SELECT id, name, surname, username FROM registers WHERE (role_id IS NULL OR role_id <> 100) ORDER BY name, surname');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New Helpdesk Request</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        <?= str_replace('</style>', '', str_replace('<style>', '', hd_ui_css())) ?>
        body { background: #f5f7fb; }
        .hd-card { border: 0; border-radius: 12px; box-shadow: 0 8px 24px rgba(16,24,40,.08); }
        .hd-title { color: #183b56; font-weight: 700; }
        .hd-subtitle { color: #5b667a; font-size: 14px; }
    </style>
</head>
<body class="p-4">
<div class="container" style="max-width:720px">
    <h3 class="mb-1 hd-title">Log New Request</h3>
    <div class="hd-subtitle mb-3">Create a manual helpdesk call with requester, assignment, due date and attachments.</div>
    <?php if ($err): ?><div class="alert alert-danger"><?= hd_esc($err) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="card card-body hd-card">
        <div class="mb-2">
            <label class="form-label">Client</label>
            <select name="client_id" class="form-select">
                <option value="">—</option>
                <?php while ($c = $clients->fetch_assoc()): ?>
                    <option value="<?= (int) $c['id'] ?>"><?= hd_esc($c['client_name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="mb-2">
            <label class="form-label">Requester</label>
            <select name="requester_id" class="form-select" required>
                <option value="">Select</option>
                <?php while ($q = $requesters->fetch_assoc()): ?>
                    <option value="<?= (int) $q['id'] ?>"><?= hd_esc($q['name'] . ' — ' . $q['email']) ?></option>
                <?php endwhile; ?>
            </select>
            <small class="text-muted">Manage in <a href="requesters.php">Requesters</a></small>
        </div>
        <div class="mb-2">
            <label class="form-label">Technician</label>
            <select name="assigned_user_id" class="form-select">
                <option value="">— Unassigned —</option>
                <?php while ($u = $techs->fetch_assoc()):
                    $lbl = trim($u['name'] . ' ' . $u['surname']);
                    if ($lbl === '') {
                        $lbl = $u['username'];
                    }
                    ?>
                    <option value="<?= (int) $u['id'] ?>"><?= hd_esc($lbl) ?></option>
                <?php endwhile; ?>
            </select>
            <?php if (hd_can('randomize assignment')): ?>
                <div class="form-text">Randomized assignment is active for your role. Ticket will auto-assign to the least loaded technician.</div>
            <?php endif; ?>
        </div>
        <div class="mb-2">
            <label class="form-label">Due by</label>
            <input type="datetime-local" name="due_at" class="form-control" value="<?= hd_esc(str_replace(' ', 'T', substr(hd_next_working_day_due(), 0, 16))) ?>">
        </div>
        <div class="mb-2">
            <label class="form-label">Subject</label>
            <input type="text" name="subject" class="form-control">
        </div>
        <div class="mb-2">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="5"></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Attachments</label>
            <input type="file" name="attach[]" class="form-control" multiple>
        </div>
        <button type="submit" class="btn btn-primary">Add Request</button>
        <a href="index.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>
</body>
</html>
