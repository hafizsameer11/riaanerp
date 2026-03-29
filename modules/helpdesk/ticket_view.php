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
$canDrilldownAllQueues = hd_can_drilldown_all_queues();
if (!hd_may_view_ticket_as_staff($t['assigned_user_id'] ?? null)) {
    http_response_code(403);
    die('You can only view tickets assigned to you or in your queue.');
}

function hd_ticket_has_completed_timesheet($conn, $ticketId)
{
    $r = $conn->query('SELECT COUNT(*) AS c FROM helpdesk_timesheets WHERE ticket_id = ' . (int) $ticketId . ' AND ended_at IS NOT NULL');
    $row = $r->fetch_assoc();
    return $row && (int) $row['c'] > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirectAddTab = '';
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
            $redirectAddTab = 'internal';
        }
    }

    if (!empty($_POST['client_update']) && trim($_POST['client_visible_text'] ?? '') !== '') {
        $txt = trim($_POST['client_visible_text'] ?? '');
        $stmt = $conn->prepare('INSERT INTO helpdesk_messages (ticket_id, direction, body, is_client_visible, created_by_user_id) VALUES (?, \'out\', ?, 1, ?)');
        $stmt->bind_param('isi', $id, $txt, $uid);
        $stmt->execute();
        $stmt->close();
        $flash = 'Client-facing update logged.';
        $redirectAddTab = 'public';
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
        $tabQ = $redirectAddTab !== '' ? '&tab=' . rawurlencode($redirectAddTab) : '';
        header('Location: ticket_view.php?id=' . $id . '&ok=1' . $tabQ);
        exit;
    }
}

if (isset($_GET['ok'])) {
    $flash = 'Saved.';
}
if (isset($_GET['merge_ok'])) {
    $flash = 'Tickets merged successfully.';
}

$addEntryTab = (isset($_GET['tab']) && $_GET['tab'] === 'internal') ? 'internal' : 'public';

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
        .thread-item{border:1px solid #e6edf5;border-radius:10px;padding:12px;margin-bottom:12px;background:#fff}
        .thread-item.internal{background:#f8fafc;border-color:#dbe4f0}
        .thread-html-frame{width:100%;min-height:200px;height:45vh;max-height:560px;border:1px solid #e6edf5;border-radius:8px;background:#fff}
        .thread-plain{word-break:break-word}
        .ticket-summary-card .hd-page-title{margin-bottom:8px}
        .hd-ticket-add-tabs .card-header{background:#fff!important;border-bottom:1px solid #e2e8f0!important;padding:.65rem 1rem 0}
        .hd-ticket-add-tabs .card-header-tabs{margin-bottom:-1px}
        .hd-ticket-add-tabs .tab-pane{padding-top:.25rem}
    </style>
</head>
<body class="py-4">
<div class="container-fluid hd-shell">
    <?php if ($flash): ?><div class="alert alert-success"><?= hd_esc($flash) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger"><?= hd_esc($err) ?></div><?php endif; ?>
    <?php if (hd_helpdesk_scoped_to_own_queue()): ?>
    <div class="alert alert-light border small text-muted py-2 mb-3">You are viewing this ticket as a <strong>technician</strong> (assigned to you only).</div>
    <?php endif; ?>

    <?php
    $assignId = (int) ($t['assigned_user_id'] ?? 0);
    $assignLbl = trim(($t['tech_name'] ?? '') . ' ' . ($t['tech_surname'] ?? ''));
    ?>
    <div class="card hd-card ticket-summary-card mb-3">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div class="flex-grow-1">
                <h3 class="hd-page-title">Ticket #<?= (int) $id ?></h3>
                <p class="mb-1"><strong>Subject:</strong> <?= hd_esc($t['subject']) ?></p>
                <p class="mb-1"><strong>Requester:</strong> <?= hd_esc($t['requester_name']) ?> &lt;<?= hd_esc($t['requester_email']) ?>&gt;</p>
                <p class="mb-1"><strong>Client:</strong> <?= hd_esc($t['client_name'] ?? '') ?></p>
                <p class="mb-1"><strong>Technician:</strong> <?php
                    if ($assignId > 0 && $assignLbl !== '' && ($canDrilldownAllQueues || $assignId === $uid)) {
                        echo '<a href="' . hd_esc(hd_helpdesk_module_path('technician.php', ['id' => $assignId])) . '">' . hd_esc($assignLbl) . '</a>';
                    } elseif ($assignId > 0 && $assignLbl !== '') {
                        echo hd_esc($assignLbl);
                    } else {
                        echo '—';
                    }
                ?></p>
                <p class="mb-0"><strong>Created:</strong> <?= hd_esc($t['created_at']) ?> · <strong>Due:</strong> <?= $t['due_at'] ? hd_esc($t['due_at']) : '—' ?>
                    · <span class="<?= hd_status_badge_class($t['status'], !empty($t['is_overdue']) && $t['status'] !== 'closed') ?>"><?= hd_esc($t['status']) ?></span></p>
            </div>
            <a href="<?= hd_esc(hd_helpdesk_index_path()) ?>" class="btn btn-outline-secondary align-self-start">Back to dashboard</a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <?php if (hd_can('close ticket')): ?>
        <div class="col-lg-6">
            <div class="card h-100 hd-card">
                <div class="card-header fw-semibold">Call status</div>
                <div class="card-body">
                    <form method="post" class="row g-2">
                        <div class="col-12 col-sm-4">
                            <select name="status" class="form-select">
                                <option value="open" <?= $t['status'] === 'open' ? 'selected' : '' ?>>Open</option>
                                <option value="on_hold" <?= $t['status'] === 'on_hold' ? 'selected' : '' ?>>On Hold</option>
                                <option value="closed" <?= $t['status'] === 'closed' ? 'selected' : '' ?>>Close</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-8">
                            <input type="text" name="on_hold_reason" class="form-control" placeholder="On hold note (required if on hold)" value="<?= hd_esc($t['on_hold_reason'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <input type="text" name="status_note" class="form-control" placeholder="Client-facing status description (optional)">
                        </div>
                        <div class="col-12">
                            <button type="submit" name="save_status" value="1" class="btn btn-primary">Save status</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (hd_can('edit ticket')): ?>
        <div class="col-lg-6">
            <div class="card h-100 hd-card">
                <div class="card-header fw-semibold">Assign technician</div>
                <div class="card-body">
                    <form method="post" class="row g-2 align-items-end">
                        <div class="col">
                            <select name="assigned_user_id" class="form-select">
                                <option value="">Unassigned</option>
                                <?php foreach ($techList as $u):
                                    $lbl = trim($u['name'] . ' ' . $u['surname']) ?: $u['username'];
                                    ?>
                                    <option value="<?= (int) $u['id'] ?>" <?= (int) $t['assigned_user_id'] === (int) $u['id'] ? 'selected' : '' ?>><?= hd_esc($lbl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-auto">
                            <button type="submit" name="save_assign" value="1" class="btn btn-secondary">Update</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (hd_can('merge tickets')): ?>
        <div class="col-12">
            <div class="card hd-card">
                <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
                    <span class="text-muted small mb-0">Merge this ticket into another. This cannot be undone.</span>
                    <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#mergeModal">Merge ticket</button>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card hd-card">
                <div class="card-header fw-semibold">Thread / notes</div>
                <div class="card-body">
                    <?php while ($m = $messages->fetch_assoc()): ?>
                        <div class="thread-item <?= $m['direction'] === 'internal' ? 'internal' : '' ?>">
                            <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                                <small class="text-muted"><?= hd_esc($m['created_at']) ?> — <?= hd_esc($m['direction']) ?></small>
                                <?php if (!empty($m['add_to_worklog'])): ?><span class="badge bg-info">worklog</span><?php endif; ?>
                            </div>
                            <?php hd_echo_message_body($m); ?>
                            <?php if (!empty($m['worklog_description'])): ?>
                                <div class="small text-muted mt-2 pt-2 border-top"><?= nl2br(hd_esc($m['worklog_description'])) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>

                    <?php if (hd_can('reply email')): ?>
                    <div class="mt-4 pt-3 border-top">
                        <h6 class="fw-semibold">Reply by email</h6>
                        <form method="post">
                            <textarea name="reply_body" class="form-control" rows="4" placeholder="Message to customer (HTML stripped on send; line breaks kept)"></textarea>
                            <button type="submit" name="post_reply" value="1" class="btn btn-success mt-2">Send reply</button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card hd-card hd-ticket-add-tabs mb-3">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs hd-nav-tabs" id="addEntryTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= $addEntryTab === 'public' ? 'active' : '' ?>" id="add-tab-public" data-bs-toggle="tab" data-bs-target="#add-pane-public" type="button" role="tab" aria-controls="add-pane-public" aria-selected="<?= $addEntryTab === 'public' ? 'true' : 'false' ?>">
                                Public update <span class="badge ms-1 text-bg-primary align-middle" style="font-size:.65rem">Requester</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= $addEntryTab === 'internal' ? 'active' : '' ?>" id="add-tab-internal" data-bs-toggle="tab" data-bs-target="#add-pane-internal" type="button" role="tab" aria-controls="add-pane-internal" aria-selected="<?= $addEntryTab === 'internal' ? 'true' : 'false' ?>">
                                Internal / worklog <span class="badge ms-1 text-bg-secondary align-middle" style="font-size:.65rem">Staff</span>
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="addEntryTabContent">
                        <div class="tab-pane fade <?= $addEntryTab === 'public' ? 'show active' : '' ?>" id="add-pane-public" role="tabpanel" aria-labelledby="add-tab-public" tabindex="0">
                            <p class="small text-muted mb-3">Visible on the ticket thread and to the requester (e.g. portal).<?php if (hd_can('reply email')): ?> To email them, use <strong>Reply by email</strong> in the thread above.<?php endif; ?></p>
                            <form method="post">
                                <label class="form-label small fw-semibold text-primary">Message to requester</label>
                                <textarea name="client_visible_text" class="form-control" rows="4" placeholder="Update visible to the requester"></textarea>
                                <button type="submit" name="client_update" value="1" class="btn btn-primary mt-3">Log public update</button>
                            </form>
                        </div>
                        <div class="tab-pane fade <?= $addEntryTab === 'internal' ? 'show active' : '' ?>" id="add-pane-internal" role="tabpanel" aria-labelledby="add-tab-internal" tabindex="0">
                            <p class="small text-muted mb-3">Not shown to the requester. For private notes and optional billing worklog lines.</p>
                            <form method="post">
                                <label class="form-label small fw-semibold text-secondary">Internal note</label>
                                <textarea name="internal_note" class="form-control mb-3" rows="4" placeholder="Private note — not visible to client"></textarea>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="add_worklog" value="1" id="wl">
                                    <label class="form-check-label" for="wl">Add to worklog</label>
                                </div>
                                <label class="form-label small text-muted">Worklog label (internal)</label>
                                <input type="text" name="worklog_description" class="form-control mb-3" placeholder="Short description for reports / billing">
                                <button type="submit" name="post_internal" value="1" class="btn btn-dark">Save internal note</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card hd-card mb-3">
                <div class="card-header fw-semibold">Service &amp; pricing</div>
                <div class="card-body">
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
                        <button type="submit" name="save_service" value="1" class="btn btn-primary mt-3">Save</button>
                    </form>
                </div>
            </div>

            <?php if (hd_can('timesheet')): ?>
            <div class="card hd-card mb-3">
                <div class="card-header fw-semibold">Timesheet</div>
                <div class="card-body">
                    <form method="post" class="d-flex gap-2 mb-3">
                        <button type="submit" name="timesheet_start" value="1" class="btn btn-sm btn-success">Start</button>
                        <button type="submit" name="timesheet_stop" value="1" class="btn btn-sm btn-warning">Stop</button>
                    </form>
                    <ul class="list-group list-group-flush small">
                        <?php $timesheets->data_seek(0); while ($ts = $timesheets->fetch_assoc()): ?>
                            <li class="list-group-item px-0">
                                <?= hd_esc($ts['started_at']) ?> — <?= $ts['ended_at'] ? hd_esc($ts['ended_at']) : '(running)' ?>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>

            <div class="card hd-card">
                <div class="card-header fw-semibold">Attachments</div>
                <div class="card-body">
                    <ul class="mb-3 ps-3">
                        <?php $attachments->data_seek(0); while ($a = $attachments->fetch_assoc()): ?>
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
