<?php
require_once __DIR__ . '/includes/bootstrap.php';
hd_require_any();
require_once __DIR__ . '/includes/NotificationService.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id < 1) {
    die('Invalid ticket.');
}

$flash = '';
$err = '';

$t = $conn->query("
    SELECT t.*, c.client_name, rq.name AS requester_name, rq.email AS requester_email,
        u.name AS tech_name, u.surname AS tech_surname
    FROM helpdesk_tickets t
    LEFT JOIN clients c ON c.id = t.client_id
    LEFT JOIN helpdesk_requesters rq ON rq.id = t.requester_id
    LEFT JOIN registers u ON u.id = t.assigned_user_id
    WHERE t.id = $id AND t.merged_into_ticket_id IS NULL
    LIMIT 1
")->fetch_assoc();

if (!$t) {
    die('Ticket not found.');
}

$uid = (int) $_SESSION['user_id'];
$isAdmin = isset($_SESSION['role']) && strtolower((string) $_SESSION['role']) === 'admin';
$canSeeTicket = $isAdmin || hd_can('edit ticket') || (int) $t['assigned_user_id'] === $uid;
if (!$canSeeTicket) {
    http_response_code(403);
    die('You can only view tickets assigned to you.');
}

function hd_ticket_has_completed_timesheet($conn, $ticketId)
{
    $r = $conn->query('SELECT COUNT(*) AS c FROM helpdesk_timesheets WHERE ticket_id = ' . (int) $ticketId . ' AND ended_at IS NOT NULL');
    $row = $r->fetch_assoc();
    return $row && (int) $row['c'] > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['save_status']) && hd_can('close ticket')) {
        $st = $_POST['status'] ?? '';
        if (!in_array($st, ['open', 'on_hold', 'closed'], true)) {
            $err = 'Invalid status.';
        } elseif ($st === 'on_hold' && trim($_POST['on_hold_reason'] ?? '') === '') {
            $err = 'On hold requires a description / note.';
        } elseif ($st === 'closed' && hd_can('timesheet') && !hd_ticket_has_completed_timesheet($conn, $id)) {
            $err = 'Enter time on the timesheet (start/end) before closing.';
        } else {
            $reason = $conn->real_escape_string(trim($_POST['on_hold_reason'] ?? ''));
            $statusNote = trim($_POST['status_note'] ?? '');
            $closedAt = $st === 'closed' ? "'" . $conn->real_escape_string(date('Y-m-d H:i:s')) . "'" : 'NULL';
            $sql = "UPDATE helpdesk_tickets SET status = '" . $conn->real_escape_string($st) . "', on_hold_reason = " . ($st === 'on_hold' ? "'$reason'" : 'NULL') . ", closed_at = ";
            if ($st === 'closed') {
                $sql .= $closedAt;
            } else {
                $sql .= 'NULL';
            }
            $sql .= " WHERE id = $id";
            $conn->query($sql);
            if ($statusNote !== '') {
                $stmt = $conn->prepare("INSERT INTO helpdesk_messages (ticket_id, direction, body, is_client_visible, created_by_user_id) VALUES (?, 'out', ?, 1, ?)");
                $stmt->bind_param('isi', $id, $statusNote, $uid);
                $stmt->execute();
                $stmt->close();
            }
            hd_recalc_overdue_for_ticket($conn, $id);
            $flash = 'Status saved.';
            if ($st !== 'on_hold') {
                HelpdeskNotificationService::trigger($conn, 'ticket_status_changed', $id, [
                    'status' => $st,
                    'on_hold_reason' => trim($_POST['on_hold_reason'] ?? ''),
                    'body' => $statusNote,
                ]);
            }
            if ($st === 'closed') {
                HelpdeskNotificationService::trigger($conn, 'ticket_closed', $id, [
                    'status' => $st,
                    'body' => $statusNote,
                ]);
            }
        }
    }

    if (!empty($_POST['save_assign']) && hd_can('edit ticket')) {
        if (isset($_POST['assigned_user_id']) && $_POST['assigned_user_id'] !== '') {
            $aid = (int) $_POST['assigned_user_id'];
            $conn->query('UPDATE helpdesk_tickets SET assigned_user_id = ' . $aid . " WHERE id = $id");
        } else {
            $conn->query("UPDATE helpdesk_tickets SET assigned_user_id = NULL WHERE id = $id");
        }
        $flash = 'Assignment updated.';
    }

    if (!empty($_POST['post_reply']) && hd_can('reply email')) {
        $body = trim($_POST['reply_body'] ?? '');
        if ($body === '') {
            $err = 'Reply body required.';
        } else {
            require_once __DIR__ . '/includes/MailService.php';
            $cfg = hd_get_mail_config_row($conn);
            $to = $t['requester_email'];
            if (!$cfg || !$cfg['smtp_host']) {
                $err = 'SMTP not configured.';
            } elseif (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $err = 'Invalid requester email.';
            } else {
                $subj = $t['subject'];
                if (stripos($subj, 'Re:') !== 0) {
                    $subj = 'Re: ' . $subj;
                }
                $html = '<p>' . nl2br(hd_esc($body)) . '</p>';

                $chain = $conn->query("SELECT email_message_id, direction, email_references FROM helpdesk_messages WHERE ticket_id = $id AND email_message_id IS NOT NULL AND email_message_id != '' ORDER BY id ASC");
                $refs = [];
                $lastInboundMid = null;
                while ($row = $chain->fetch_assoc()) {
                    $refs[] = $row['email_message_id'];
                    if ($row['direction'] === 'in') {
                        $lastInboundMid = $row['email_message_id'];
                    }
                }
                $refStr = implode(' ', array_unique($refs));
                $inRep = $lastInboundMid;
                $res = HelpdeskMailService::sendTicketMessage($conn, $cfg, $to, $subj, $html, $id, $inRep, $refStr);
                if ($res['ok']) {
                    $flash = 'Reply sent.';
                } else {
                    $err = 'Mail error: ' . ($res['error'] ?? '');
                }
            }
        }
    }

    if (!empty($_POST['post_internal'])) {
        $note = trim($_POST['internal_note'] ?? '');
        $wl = !empty($_POST['add_worklog']) ? 1 : 0;
        $wld = trim($_POST['worklog_description'] ?? '');
        if ($note !== '' || $wld !== '') {
            $stmt = $conn->prepare('INSERT INTO helpdesk_messages (ticket_id, direction, body, is_client_visible, add_to_worklog, worklog_description, created_by_user_id) VALUES (?, \'internal\', ?, 0, ?, ?, ?)');
            $stmt->bind_param('isisi', $id, $note, $wl, $wld, $uid);
            $stmt->execute();
            $stmt->close();
            $flash = 'Internal note saved.';
        }
    }

    if (!empty($_POST['client_update']) && trim($_POST['client_visible_text'] ?? '') !== '') {
        $txt = trim($_POST['client_visible_text'] ?? '');
        $stmt = $conn->prepare('INSERT INTO helpdesk_messages (ticket_id, direction, body, is_client_visible, created_by_user_id) VALUES (?, \'out\', ?, 1, ?)');
        $stmt->bind_param('isi', $id, $txt, $uid);
        $stmt->execute();
        $stmt->close();
        $flash = 'Client-facing update logged.';
    }

    if (!empty($_POST['timesheet_start']) && hd_can('timesheet')) {
        $conn->query('UPDATE helpdesk_timesheets SET is_running = 0, ended_at = NOW() WHERE ticket_id = ' . $id . ' AND user_id = ' . $uid . ' AND is_running = 1');
        $stmt = $conn->prepare('INSERT INTO helpdesk_timesheets (ticket_id, user_id, started_at, is_running) VALUES (?, ?, NOW(), 1)');
        $stmt->bind_param('ii', $id, $uid);
        $stmt->execute();
        $stmt->close();
        $flash = 'Timer started.';
    }
    if (!empty($_POST['timesheet_stop']) && hd_can('timesheet')) {
        $rid = $conn->query('SELECT id FROM helpdesk_timesheets WHERE ticket_id = ' . $id . ' AND user_id = ' . $uid . ' AND is_running = 1 ORDER BY id DESC LIMIT 1')->fetch_assoc();
        if ($rid) {
            $conn->query('UPDATE helpdesk_timesheets SET is_running = 0, ended_at = NOW() WHERE id = ' . (int) $rid['id']);
        }
        $flash = 'Timer stopped.';
    }

    if (!empty($_POST['save_service'])) {
        $catId = (int) ($_POST['service_category_id'] ?? 0);
        $priceId = (int) ($_POST['category_price_id'] ?? 0);
        $itemName = '';
        $unitPrice = 0.0;
        $currency = 'USD';
        if ($priceId > 0) {
            $pr = $conn->query('SELECT item_name, unit_price, currency FROM billing_category_prices WHERE id = ' . $priceId)->fetch_assoc();
            if ($pr) {
                $itemName = $pr['item_name'];
                $unitPrice = (float) $pr['unit_price'];
                $currency = $pr['currency'];
            }
        }
        $conn->query('DELETE FROM helpdesk_ticket_services WHERE ticket_id = ' . $id);
        if ($catId > 0 || $priceId > 0) {
            $stmt = $conn->prepare('INSERT INTO helpdesk_ticket_services (ticket_id, service_category_id, category_price_id, item_name, unit_price, currency) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('iiisds', $id, $catId, $priceId, $itemName, $unitPrice, $currency);
            $stmt->execute();
            $stmt->close();
        }
        $flash = 'Service / pricing updated.';
    }

    if (!empty($_POST['upload_more']) && isset($_FILES['attach'])) {
        if (!is_dir(HELPDESK_UPLOAD_DIR)) {
            mkdir(HELPDESK_UPLOAD_DIR, 0755, true);
        }
        $names = $_FILES['attach']['name'];
        $tmp = $_FILES['attach']['tmp_name'];
        $errs = $_FILES['attach']['error'];
        for ($i = 0; $i < count($names); $i++) {
            if ($errs[$i] !== UPLOAD_ERR_OK || !is_uploaded_file($tmp[$i])) {
                continue;
            }
            $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($names[$i]));
            $destName = $id . '_' . time() . '_' . $safe;
            $destPath = HELPDESK_UPLOAD_DIR . '/' . $destName;
            if (move_uploaded_file($tmp[$i], $destPath)) {
                $sz = (int) filesize($destPath);
                $rel = 'uploads/' . $destName;
                $stmt = $conn->prepare('INSERT INTO helpdesk_attachments (ticket_id, filename, stored_path, size_bytes) VALUES (?, ?, ?, ?)');
                $stmt->bind_param('issi', $id, $safe, $rel, $sz);
                $stmt->execute();
                $stmt->close();
            }
        }
        $flash = 'Attachments uploaded.';
    }

    if ($flash && !$err) {
        header('Location: ticket_view.php?id=' . $id . '&ok=1');
        exit;
    }
}

if (isset($_GET['ok'])) {
    $flash = 'Saved.';
}
if (isset($_GET['merge_ok'])) {
    $flash = 'Tickets merged successfully.';
}

hd_refresh_all_overdue($conn);
$t = $conn->query("
    SELECT t.*, c.client_name, rq.name AS requester_name, rq.email AS requester_email,
        u.name AS tech_name, u.surname AS tech_surname
    FROM helpdesk_tickets t
    LEFT JOIN clients c ON c.id = t.client_id
    LEFT JOIN helpdesk_requesters rq ON rq.id = t.requester_id
    LEFT JOIN registers u ON u.id = t.assigned_user_id
    WHERE t.id = $id AND t.merged_into_ticket_id IS NULL
    LIMIT 1
")->fetch_assoc();

$messages = $conn->query("SELECT * FROM helpdesk_messages WHERE ticket_id = $id ORDER BY id ASC");
$attachments = $conn->query("SELECT * FROM helpdesk_attachments WHERE ticket_id = $id ORDER BY id ASC");
$timesheets = $conn->query("SELECT * FROM helpdesk_timesheets WHERE ticket_id = $id ORDER BY id DESC");
$service = $conn->query('SELECT * FROM helpdesk_ticket_services WHERE ticket_id = ' . $id . ' LIMIT 1')->fetch_assoc();

$categories = $conn->query('SELECT id, category_name FROM billing_service_categories WHERE is_deleted = 0 ORDER BY category_name');
$techs = $conn->query('SELECT id, name, surname, username FROM registers WHERE (role_id IS NULL OR role_id <> 100) ORDER BY name');
$techList = [];
while ($row = $techs->fetch_assoc()) {
    $techList[] = $row;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Ticket #<?= (int) $id ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <?= hd_ui_css() ?>
    <style>
        .thread-item{border:1px solid #e6edf5;border-radius:10px;padding:10px;margin-bottom:10px;background:#fff}
        .thread-item.internal{background:#f8fafc}
    </style>
</head>
<body class="p-3">
<div class="container-fluid">
    <?php if ($flash): ?><div class="alert alert-success"><?= hd_esc($flash) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger"><?= hd_esc($err) ?></div><?php endif; ?>

    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h3 class="hd-page-title">Ticket #<?= (int) $id ?></h3>
            <p class="mb-0"><strong>Subject:</strong> <?= hd_esc($t['subject']) ?></p>
            <p class="mb-0"><strong>Requester:</strong> <?= hd_esc($t['requester_name']) ?> &lt;<?= hd_esc($t['requester_email']) ?>&gt;</p>
            <p class="mb-0"><strong>Client:</strong> <?= hd_esc($t['client_name'] ?? '') ?></p>
            <p class="mb-0"><strong>Created:</strong> <?= hd_esc($t['created_at']) ?> | <strong>Due:</strong> <?= $t['due_at'] ? hd_esc($t['due_at']) : '—' ?> | <span class="<?= hd_status_badge_class($t['status'], !empty($t['is_overdue']) && $t['status'] !== 'closed') ?>"><?= hd_esc($t['status']) ?></span></p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary">Back</a>
    </div>

    <div class="row g-3">
    <?php if (hd_can('close ticket')): ?>
    <div class="col-lg-6">
    <div class="card mb-3 hd-card">
        <div class="card-header">Call status</div>
        <div class="card-body">
            <form method="post" class="row g-2">
                <div class="col-md-4">
                    <select name="status" class="form-select">
                        <option value="open" <?= $t['status'] === 'open' ? 'selected' : '' ?>>Open</option>
                        <option value="on_hold" <?= $t['status'] === 'on_hold' ? 'selected' : '' ?>>On Hold</option>
                        <option value="closed" <?= $t['status'] === 'closed' ? 'selected' : '' ?>>Close</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <input type="text" name="on_hold_reason" class="form-control" placeholder="On hold note (required if on hold)" value="<?= hd_esc($t['on_hold_reason'] ?? '') ?>">
                </div>
                <div class="col-md-10">
                    <input type="text" name="status_note" class="form-control" placeholder="Client-facing status description (optional)">
                </div>
                <div class="col-md-2">
                    <button type="submit" name="save_status" value="1" class="btn btn-primary w-100">Save</button>
                </div>
            </form>
        </div>
    </div>
    </div>
    <?php endif; ?>

    <?php if (hd_can('edit ticket')): ?>
    <div class="col-lg-6">
    <div class="card mb-3 hd-card">
        <div class="card-header">Assign technician</div>
        <div class="card-body">
            <form method="post" class="row g-2">
                <div class="col-md-8">
                    <select name="assigned_user_id" class="form-select">
                        <option value="">Unassigned</option>
                        <?php foreach ($techList as $u):
                            $lbl = trim($u['name'] . ' ' . $u['surname']) ?: $u['username'];
                            ?>
                            <option value="<?= (int) $u['id'] ?>" <?= (int) $t['assigned_user_id'] === (int) $u['id'] ? 'selected' : '' ?>><?= hd_esc($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" name="save_assign" value="1" class="btn btn-secondary">Update</button>
                </div>
            </form>
        </div>
    </div>
    </div>
    <?php endif; ?>
    <?php if (hd_can('merge tickets')): ?>
    <div class="col-lg-6">
        <div class="card mb-3 hd-card">
            <div class="card-header">Merge ticket</div>
            <div class="card-body d-flex justify-content-between align-items-center">
                <div class="text-muted small">Merge this ticket into another ticket. This action cannot be undone.</div>
                <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#mergeModal">Merge</button>
            </div>
        </div>
    </div>
    <?php endif; ?>
    </div>

    <div class="row">
        <div class="col-lg-7">
            <h5>Thread / notes</h5>
            <?php while ($m = $messages->fetch_assoc()): ?>
                <div class="thread-item <?= $m['direction'] === 'internal' ? 'internal' : '' ?>">
                    <small class="text-muted"><?= hd_esc($m['created_at']) ?> — <?= hd_esc($m['direction']) ?>
                        <?php if (!empty($m['add_to_worklog'])): ?><span class="badge bg-info">worklog</span><?php endif; ?>
                    </small>
                    <div><?= $m['direction'] === 'out' || $m['direction'] === 'in' ? nl2br(hd_esc($m['body'])) : nl2br(hd_esc($m['body'])) ?></div>
                    <?php if (!empty($m['worklog_description'])): ?><div class="small text-muted mt-1"><?= nl2br(hd_esc($m['worklog_description'])) ?></div><?php endif; ?>
                </div>
            <?php endwhile; ?>

            <?php if (hd_can('reply email')): ?>
            <h6 class="mt-3">Reply by email (customer)</h6>
            <form method="post">
                <textarea name="reply_body" class="form-control" rows="4" placeholder="Email body (HTML stripped on send; line breaks kept)"></textarea>
                <button type="submit" name="post_reply" value="1" class="btn btn-success mt-2">Send reply</button>
            </form>
            <?php endif; ?>

            <h6 class="mt-3">Client-visible update (logged only; use Reply to email customer)</h6>
            <form method="post">
                <textarea name="client_visible_text" class="form-control" rows="2"></textarea>
                <button type="submit" name="client_update" value="1" class="btn btn-outline-primary mt-2">Log update</button>
            </form>

            <h6 class="mt-3">Internal / worklog</h6>
            <form method="post">
                <textarea name="internal_note" class="form-control mb-2" rows="2" placeholder="Internal note"></textarea>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="add_worklog" value="1" id="wl">
                    <label class="form-check-label" for="wl">Add to worklog</label>
                </div>
                <input type="text" name="worklog_description" class="form-control mb-2" placeholder="Worklog description (internal)">
                <button type="submit" name="post_internal" value="1" class="btn btn-outline-secondary">Save internal</button>
            </form>
        </div>
        <div class="col-lg-5">
            <h5 class="hd-section-title">Service &amp; pricing</h5>
            <form method="post">
                <label class="form-label">Service category</label>
                <select name="service_category_id" class="form-select" id="svcCat">
                    <option value="0">—</option>
                    <?php $categories->data_seek(0); while ($c = $categories->fetch_assoc()): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= $service && (int) $service['service_category_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= hd_esc($c['category_name']) ?></option>
                    <?php endwhile; ?>
                </select>
                <label class="form-label mt-2">Unit price item</label>
                <select name="category_price_id" class="form-select" id="svcPrice">
                    <option value="0">—</option>
                    <?php
                    $prices = $conn->query('SELECT id, service_category_id, item_name, unit_price, currency FROM billing_category_prices ORDER BY service_category_id, item_name');
                    while ($p = $prices->fetch_assoc()): ?>
                        <option data-cat="<?= (int) $p['service_category_id'] ?>" value="<?= (int) $p['id'] ?>" <?= $service && (int) $service['category_price_id'] === (int) $p['id'] ? 'selected' : '' ?>><?= hd_esc($p['item_name'] . ' — ' . $p['unit_price'] . ' ' . $p['currency']) ?></option>
                    <?php endwhile; ?>
                </select>
                <button type="submit" name="save_service" value="1" class="btn btn-primary mt-2">Save</button>
            </form>

            <?php if (hd_can('timesheet')): ?>
            <h5 class="mt-4">Timesheet</h5>
            <form method="post" class="d-flex gap-2 mb-2">
                <button type="submit" name="timesheet_start" value="1" class="btn btn-sm btn-success">Start</button>
                <button type="submit" name="timesheet_stop" value="1" class="btn btn-sm btn-warning">Stop</button>
            </form>
            <ul class="list-group small">
                <?php while ($ts = $timesheets->fetch_assoc()): ?>
                    <li class="list-group-item">
                        <?= hd_esc($ts['started_at']) ?> — <?= $ts['ended_at'] ? hd_esc($ts['ended_at']) : '(running)' ?>
                    </li>
                <?php endwhile; ?>
            </ul>
            <?php endif; ?>

            <h5 class="mt-4">Attachments</h5>
            <ul>
                <?php while ($a = $attachments->fetch_assoc()): ?>
                    <li><a href="attachment.php?id=<?= (int) $a['id'] ?>" target="_blank"><?= hd_esc($a['filename']) ?></a></li>
                <?php endwhile; ?>
            </ul>
            <form method="post" enctype="multipart/form-data">
                <input type="file" name="attach[]" class="form-control" multiple>
                <button type="submit" name="upload_more" value="1" class="btn btn-sm btn-secondary mt-2">Upload</button>
            </form>
        </div>
    </div>
</div>
<?php if (hd_can('merge tickets')): ?>
<div class="modal fade" id="mergeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="merge.php">
                <div class="modal-header">
                    <h5 class="modal-title">Merge Ticket #<?= (int) $id ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="from_id" value="<?= (int) $id ?>">
                    <input type="hidden" name="return_to" value="ticket_view.php?id=<?= (int) $id ?>">
                    <div class="mb-2">
                        <label class="form-label">Merge into ticket ID</label>
                        <input type="number" min="1" name="into_id" class="form-control" required>
                    </div>
                    <div class="alert alert-warning mb-0">Source ticket will be closed and linked to target ticket.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Confirm merge</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    var cat = document.getElementById('svcCat');
    var price = document.getElementById('svcPrice');
    if (!cat || !price) return;
    function filt() {
        var c = cat.value;
        for (var i = 0; i < price.options.length; i++) {
            var o = price.options[i];
            if (!o.value) { o.style.display = ''; continue; }
            o.style.display = (o.getAttribute('data-cat') === c) ? '' : 'none';
        }
    }
    cat.addEventListener('change', filt);
    filt();
})();
</script>
</body>
</html>
