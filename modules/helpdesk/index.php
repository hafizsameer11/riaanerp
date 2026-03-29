<?php
require_once __DIR__ . '/includes/bootstrap.php';
hd_require_any();
if (!hd_can('dashboard')) {
    if (hd_can('create ticket')) {
        header('Location: ticket_new.php');
        exit;
    }
    if (hd_can('reports')) {
        header('Location: reports/index.php');
        exit;
    }
    http_response_code(403);
    die('No Helpdesk dashboard permission. Enable "dashboard" in Role Permissions for this role.');
}

$isAdmin = isset($_SESSION['role']) && strtolower((string) $_SESSION['role']) === 'admin';
$uid = (int) $_SESSION['user_id'];
$canDrilldownAllQueues = hd_can_drilldown_all_queues();
$hdScopedTech = hd_helpdesk_scoped_to_own_queue();
hd_refresh_all_overdue($conn);

$techUsers = [];
$tr = $conn->query("SELECT id, name, surname, username FROM registers WHERE (role_id IS NULL OR role_id <> 100) ORDER BY name, surname");
while ($row = $tr->fetch_assoc()) {
    $techUsers[] = $row;
}

function hd_dashboard_next_due()
{
    return date('Y-m-d\TH:i', hd_add_working_hours(time(), 24));
}

$clients = [];
$cr = $conn->query('SELECT id, client_name FROM clients ORDER BY client_name');
while ($c = $cr->fetch_assoc()) {
    $clients[] = $c;
}
$requesters = [];
$rr = $conn->query('SELECT r.id, r.name, r.email, r.client_id, c.client_name FROM helpdesk_requesters r LEFT JOIN clients c ON c.id = r.client_id ORDER BY r.name');
while ($r = $rr->fetch_assoc()) {
    $requesters[] = $r;
}
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'my';
if (!in_array($tab, ['my', 'global', 'assets'], true)) {
    $tab = 'my';
}
if ($hdScopedTech && $tab !== 'my') {
    header('Location: index.php?tab=my');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['save_new_request']) && hd_can('create ticket')) {
        require_once __DIR__ . '/includes/NotificationService.php';
        $client_id = (int) ($_POST['client_id'] ?? 0) ?: null;
        $requester_id = (int) ($_POST['requester_id'] ?? 0) ?: null;
        $assigned_user_id = isset($_POST['assigned_user_id']) && $_POST['assigned_user_id'] !== '' ? (int) $_POST['assigned_user_id'] : null;
        $due_at_raw = trim($_POST['due_at'] ?? '');
        $due_at = $due_at_raw !== '' ? str_replace('T', ' ', $due_at_raw) . ':00' : str_replace('T', ' ', hd_dashboard_next_due()) . ':00';
        $subject = trim($_POST['subject'] ?? '');
        if ($subject === '') {
            $subject = '(no subject)';
        }
        $description = trim($_POST['description'] ?? '');
        if ($requester_id > 0) {
            if (!$client_id) {
                $rqRow = $conn->query('SELECT client_id FROM helpdesk_requesters WHERE id = ' . (int) $requester_id)->fetch_assoc();
                if ($rqRow && !empty($rqRow['client_id'])) {
                    $client_id = (int) $rqRow['client_id'];
                }
            }
            if (hd_can('randomize assignment')) {
                $pool = hd_random_assignment_pool_user_ids($conn);
                $assigned_user_id = hd_pick_random_assignee($conn, $pool);
            }
            if ($hdScopedTech) {
                $assigned_user_id = $uid;
            }
            $cidSql = $client_id ? (int) $client_id : 'NULL';
            $aidSql = $assigned_user_id !== null ? (int) $assigned_user_id : 'NULL';
            $subEsc = "'" . $conn->real_escape_string($subject) . "'";
            $dueEsc = "'" . $conn->real_escape_string($due_at) . "'";
            $randFlag = hd_can('randomize assignment') ? 1 : 0;
            $conn->query("INSERT INTO helpdesk_tickets (client_id, requester_id, assigned_user_id, status, subject, due_at, source, random_assigned) VALUES ($cidSql, $requester_id, $aidSql, 'open', $subEsc, $dueEsc, 'manual', $randFlag)");
            $tid = (int) $conn->insert_id;

            if ($tid > 0) {
                if ($description !== '') {
                    $stmt = $conn->prepare('INSERT INTO helpdesk_messages (ticket_id, direction, body, is_client_visible, add_to_worklog, created_by_user_id) VALUES (?, \'in\', ?, 1, 0, ?)');
                    $uidIns = (int) $_SESSION['user_id'];
                    $stmt->bind_param('isi', $tid, $description, $uidIns);
                    $stmt->execute();
                    $stmt->close();
                }
                if (!empty($_FILES['attach']) && is_array($_FILES['attach']['name'])) {
                    if (!is_dir(HELPDESK_UPLOAD_DIR)) {
                        mkdir(HELPDESK_UPLOAD_DIR, 0755, true);
                    }
                    $count = count($_FILES['attach']['name']);
                    for ($i = 0; $i < $count; $i++) {
                        if (($_FILES['attach']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($_FILES['attach']['tmp_name'][$i])) {
                            continue;
                        }
                        $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['attach']['name'][$i]));
                        $destName = $tid . '_' . time() . '_' . $safe;
                        $destPath = HELPDESK_UPLOAD_DIR . '/' . $destName;
                        if (move_uploaded_file($_FILES['attach']['tmp_name'][$i], $destPath)) {
                            $sz = (int) filesize($destPath);
                            $rel = 'uploads/' . $destName;
                            $stmt = $conn->prepare('INSERT INTO helpdesk_attachments (ticket_id, filename, stored_path, size_bytes) VALUES (?, ?, ?, ?)');
                            $stmt->bind_param('issi', $tid, $safe, $rel, $sz);
                            $stmt->execute();
                            $stmt->close();
                        }
                    }
                }
                hd_recalc_overdue_for_ticket($conn, $tid);
                $cfg = hd_get_mail_config_row($conn);
                if ($cfg && !empty($cfg['smtp_host'])) {
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
        } else {
            header('Location: index.php?tab=' . urlencode((string) $tab) . '&new_req_err=1');
            exit;
        }
    }

    if (!empty($_POST['assign_ticket']) && hd_can('edit ticket')) {
        $ticketId = (int) ($_POST['ticket_id'] ?? 0);
        $assignee = isset($_POST['assigned_user_id']) && $_POST['assigned_user_id'] !== '' ? (int) $_POST['assigned_user_id'] : null;
        if ($ticketId > 0) {
            if ($assignee === null) {
                $conn->query("UPDATE helpdesk_tickets SET assigned_user_id = NULL WHERE id = $ticketId");
            } else {
                $conn->query("UPDATE helpdesk_tickets SET assigned_user_id = $assignee WHERE id = $ticketId");
            }
        }
        $q = http_build_query([
            'tab' => $_GET['tab'] ?? 'my',
            'tech' => $_GET['tech'] ?? null,
        ]);
        header('Location: index.php' . ($q ? ('?' . $q) : ''));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && hd_can('randomize assignment')) {
    hd_backfill_random_assign_unassigned($conn);
}

$techStats = [];
$unRow = null;
if ($hdScopedTech) {
    $meRow = $conn->query('
        SELECT
            COALESCE(SUM(CASE WHEN status = \'open\' THEN 1 ELSE 0 END), 0) AS open_cnt,
            COALESCE(SUM(CASE WHEN status = \'on_hold\' THEN 1 ELSE 0 END), 0) AS hold_cnt,
            COALESCE(SUM(CASE WHEN is_overdue = 1 AND status != \'closed\' THEN 1 ELSE 0 END), 0) AS overdue_cnt
        FROM helpdesk_tickets
        WHERE merged_into_ticket_id IS NULL AND assigned_user_id = ' . $uid . '
    ')->fetch_assoc();
    $totOpen = (int) ($meRow['open_cnt'] ?? 0);
    $totHold = (int) ($meRow['hold_cnt'] ?? 0);
    $totOver = (int) ($meRow['overdue_cnt'] ?? 0);
} else {
    $res = $conn->query("
    SELECT r.id, r.name, r.surname, r.username,
        COALESCE(SUM(CASE WHEN t.status = 'open' THEN 1 ELSE 0 END),0) AS open_cnt,
        COALESCE(SUM(CASE WHEN t.status = 'on_hold' THEN 1 ELSE 0 END),0) AS hold_cnt,
        COALESCE(SUM(CASE WHEN t.is_overdue = 1 AND t.status != 'closed' THEN 1 ELSE 0 END),0) AS overdue_cnt
    FROM registers r
    LEFT JOIN helpdesk_tickets t ON t.assigned_user_id = r.id AND t.merged_into_ticket_id IS NULL
    WHERE (r.role_id IS NULL OR r.role_id <> 100)
    GROUP BY r.id, r.name, r.surname, r.username
    ORDER BY open_cnt DESC, r.name
");
    while ($row = $res->fetch_assoc()) {
        $techStats[] = $row;
    }

    $unRow = $conn->query("
    SELECT
        SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open_cnt,
        SUM(CASE WHEN status = 'on_hold' THEN 1 ELSE 0 END) AS hold_cnt,
        SUM(CASE WHEN is_overdue = 1 AND status != 'closed' THEN 1 ELSE 0 END) AS overdue_cnt
    FROM helpdesk_tickets
    WHERE assigned_user_id IS NULL AND merged_into_ticket_id IS NULL
")->fetch_assoc();
}

$techFilterId = (isset($_GET['tech']) && ctype_digit((string) $_GET['tech'])) ? (int) $_GET['tech'] : 0;
if ($hdScopedTech) {
    $techFilterId = 0;
}

$listWhere = ' t.merged_into_ticket_id IS NULL ';
if ($hdScopedTech) {
    $listWhere .= ' AND t.assigned_user_id = ' . $uid . ' ';
} elseif ($tab === 'my') {
    $listWhere .= ' AND (t.assigned_user_id = ' . $uid . ' OR t.assigned_user_id IS NULL) ';
} elseif ($tab === 'global') {
    $relaxForTechDrilldown = $techFilterId > 0 && $canDrilldownAllQueues;
    if (!$isAdmin && !$relaxForTechDrilldown) {
        $listWhere .= ' AND (t.assigned_user_id = ' . $uid . ' OR t.assigned_user_id IS NULL) ';
    }
}
if ($techFilterId > 0) {
    $listWhere .= ' AND t.assigned_user_id = ' . $techFilterId . ' ';
}
// assets tab: different content below

$ticketList = $conn->query("
    SELECT t.*, c.client_name, rq.name AS requester_name, rq.email AS requester_email,
        u.name AS tech_name, u.surname AS tech_surname
    FROM helpdesk_tickets t
    LEFT JOIN clients c ON c.id = t.client_id
    LEFT JOIN helpdesk_requesters rq ON rq.id = t.requester_id
    LEFT JOIN registers u ON u.id = t.assigned_user_id
    WHERE $listWhere AND t.status != 'closed'
    ORDER BY t.is_overdue DESC, t.due_at IS NULL, t.due_at ASC, t.id DESC
    LIMIT 200
");

if (!$hdScopedTech) {
    $totOpen = 0;
    $totHold = 0;
    $totOver = 0;
    foreach ($techStats as $tr) {
        $totOpen += (int) $tr['open_cnt'];
        $totHold += (int) $tr['hold_cnt'];
        $totOver += (int) $tr['overdue_cnt'];
    }
    if ($unRow) {
        $totOpen += (int) $unRow['open_cnt'];
        $totHold += (int) $unRow['hold_cnt'];
        $totOver += (int) $unRow['overdue_cnt'];
    }
}

$filterTechStats = null;
$filterTechLabel = '';
if ($techFilterId > 0) {
    foreach ($techStats as $tr) {
        if ((int) $tr['id'] === $techFilterId) {
            $filterTechStats = $tr;
            $filterTechLabel = trim(($tr['name'] ?? '') . ' ' . ($tr['surname'] ?? ''));
            if ($filterTechLabel === '') {
                $filterTechLabel = (string) ($tr['username'] ?? '');
            }
            break;
        }
    }
    if ($filterTechLabel === '') {
        $r = $conn->query('SELECT name, surname, username FROM registers WHERE id = ' . $techFilterId . ' LIMIT 1');
        $rowR = $r ? $r->fetch_assoc() : null;
        if ($rowR) {
            $filterTechLabel = trim(($rowR['name'] ?? '') . ' ' . ($rowR['surname'] ?? ''));
            if ($filterTechLabel === '') {
                $filterTechLabel = (string) ($rowR['username'] ?? '');
            }
        }
        if ($filterTechLabel === '') {
            $filterTechLabel = 'User #' . $techFilterId;
        }
    }
}
$showTechFilterBanner = $techFilterId > 0 && ($canDrilldownAllQueues || $techFilterId === $uid);
$drilldownTab = $canDrilldownAllQueues ? 'global' : 'my';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Helpdesk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        <?= str_replace('</style>', '', str_replace('<style>', '', hd_ui_css())) ?>
        body { background: #eef3f9; font-family: Inter, Arial, sans-serif; color: #1f2a37; }
        .page-title { font-weight: 700; color: #16324f; margin-bottom: 6px; }
        .page-subtitle { color: #5a6a7f; font-size: 14px; margin-bottom: 14px; }
        .hd-tab { border-bottom: 2px solid #e2e8f0; gap: .25rem; }
        .hd-tab .nav-link { color: #64748b; border: 0; border-radius: 8px 8px 0 0; margin-bottom: -2px; font-weight: 600; padding: .65rem 1.1rem; }
        .hd-tab .nav-link:hover { color: #1e4a72; background: #f8fafc; }
        .hd-tab .nav-link.active { color: #1f6fb2; border-bottom: 2px solid #1f6fb2; background: #fff; font-weight: 700; }
        .tech-header { background: linear-gradient(135deg,#1a4d7a 0%,#1f4f86 100%); color: #fff; font-size: .8125rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; }
        .panel-card { border: 0; border-radius: 12px; overflow: hidden; box-shadow: 0 8px 24px rgba(16,24,40,.08); }
        .summary-card { border: 1px solid #e8eef5; border-radius: 12px; background: linear-gradient(180deg, #ffffff, #f9fbff); box-shadow: 0 2px 12px rgba(15,23,42,.04); }
        .summary-label { font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: 600; letter-spacing: .04em; }
        .summary-value { font-size: 26px; font-weight: 800; color: #0f2942; line-height: 1; }
        .row-unassigned { background: #e6f1ec !important; }
        .row-total { background: #e0f0fa !important; font-weight: 700; }
        .badge-soft { background: #e8f2ff; color: #1b4f88; border-radius: 999px; padding: 4px 10px; font-size: 12px; font-weight: 600; }
        .modal-header { border-bottom: 1px solid #e7edf5; }
        .modal-footer { border-top: 1px solid #e7edf5; }
    </style>
</head>
<body class="py-4">
<div class="container-fluid hd-shell">
    <div class="page-title"><?= $hdScopedTech ? 'My helpdesk queue' : 'Helpdesk Dashboard' ?></div>
    <div class="page-subtitle"><?= $hdScopedTech ? 'Open calls assigned to you only — team-wide views are available to managers.' : 'Track open workload, assignments, overdue calls and quick actions in one place.' ?></div>
    <div class="row g-2 mb-3">
        <div class="<?= $hdScopedTech ? 'col-md-4' : 'col-md-3' ?>">
            <div class="summary-card p-3">
                <div class="summary-label">Open</div>
                <div class="summary-value"><?= (int) $totOpen ?></div>
            </div>
        </div>
        <div class="<?= $hdScopedTech ? 'col-md-4' : 'col-md-3' ?>">
            <div class="summary-card p-3">
                <div class="summary-label">On Hold</div>
                <div class="summary-value"><?= (int) $totHold ?></div>
            </div>
        </div>
        <div class="<?= $hdScopedTech ? 'col-md-4' : 'col-md-3' ?>">
            <div class="summary-card p-3">
                <div class="summary-label">Overdue</div>
                <div class="summary-value"><?= (int) $totOver ?></div>
            </div>
        </div>
        <?php if (!$hdScopedTech): ?>
        <div class="col-md-3">
            <div class="summary-card p-3">
                <div class="summary-label">Unassigned Open</div>
                <div class="summary-value"><?= (int) ($unRow['open_cnt'] ?? 0) ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
        <?php if (hd_can('create ticket')): ?>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#newRequestModal">New Request</button>
        <?php endif; ?>
        <?php if ($hdScopedTech && !hd_can('scheduled')): ?>
        <?php elseif ($hdScopedTech && hd_can('scheduled')): ?>
            <div class="dropdown">
                <button class="btn btn-success dropdown-toggle" type="button" data-bs-toggle="dropdown">Create new</button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="scheduled.php?new=1">Scheduled call</a></li>
                </ul>
            </div>
        <?php else: ?>
        <div class="dropdown">
            <button class="btn btn-success dropdown-toggle" type="button" data-bs-toggle="dropdown">Create new</button>
            <ul class="dropdown-menu">
                <?php if (hd_can('create ticket')): ?>
                    <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#newRequestModal">Manual ticket (quick modal)</button></li>
                <?php endif; ?>
                <?php if (hd_can('scheduled')): ?>
                    <li><a class="dropdown-item" href="scheduled.php?new=1">Scheduled call</a></li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>
        <?php if (hd_may_view_tickets_all()): ?>
            <a class="btn btn-outline-primary" href="<?= hd_esc(hd_helpdesk_module_path('tickets_all.php')) ?>">All tickets</a>
        <?php endif; ?>
    </div>
    <?php if (isset($_GET['new_req_err'])): ?><div class="alert alert-danger">Requester is required to log a new request.</div><?php endif; ?>

    <?php if ($hdScopedTech): ?>
    <div class="alert alert-light border py-2 px-3 small text-muted mb-3" role="note">
        You are in <strong>technician</strong> mode: only tickets assigned to you appear below.
    </div>
    <?php else: ?>
    <ul class="nav nav-tabs hd-tab mb-3">
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'my' ? 'active' : '' ?>" href="?tab=my">My View</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'global' ? 'active' : '' ?>" href="?tab=global">Global View</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'assets' ? 'active' : '' ?>" href="?tab=assets">Assets</a>
        </li>
    </ul>
    <?php endif; ?>

    <?php if ($tab !== 'assets'): ?>
        <?php if (!$hdScopedTech): ?>
        <div class="hd-table-card mb-4">
            <div class="tech-header d-flex justify-content-between align-items-center card-header text-white border-0 rounded-0 py-3">
                <span>Requests by technician</span>
                <div>
                    <a class="btn btn-sm btn-outline-light" href="reports/export.php?type=open">Export open (CSV)</a>
                </div>
            </div>
            <div class="hd-table-wrap">
                <table class="table hd-data-table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Technician</th>
                            <th class="text-end">Open</th>
                            <th class="text-end">OnHold</th>
                            <th class="text-end">OverDue</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($techStats as $tr):
                        $nm = trim($tr['name'] . ' ' . $tr['surname']);
                        if ($nm === '') {
                            $nm = $tr['username'];
                        }
                        $tid = (int) $tr['id'];
                        $canLinkTechRow = $canDrilldownAllQueues || $tid === $uid;
                        $techRowHref = $canLinkTechRow ? hd_helpdesk_module_path('technician.php', ['id' => $tid]) : '';
                        ?>
                        <tr>
                            <td><?php if ($canLinkTechRow): ?><a href="<?= hd_esc($techRowHref) ?>"><?= hd_esc($nm) ?></a><?php else: ?><?= hd_esc($nm) ?><?php endif; ?></td>
                            <td class="text-end fw-semibold"><a href="reports/export.php?type=open&amp;tech=<?= $tid ?>"><?= (int) $tr['open_cnt'] ?></a></td>
                            <td class="text-end fw-semibold"><?= (int) $tr['hold_cnt'] ?></td>
                            <td class="text-end fw-semibold"><a href="reports/export.php?type=overdue&amp;tech=<?= $tid ?>"><?= (int) $tr['overdue_cnt'] ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($unRow && ((int)$unRow['open_cnt'] + (int)$unRow['hold_cnt'] + (int)$unRow['overdue_cnt'] > 0)): ?>
                        <tr class="row-unassigned">
                            <td>Unassigned</td>
                            <td class="text-end fw-semibold"><?= (int) $unRow['open_cnt'] ?></td>
                            <td class="text-end fw-semibold"><?= (int) $unRow['hold_cnt'] ?></td>
                            <td class="text-end fw-semibold"><?= (int) $unRow['overdue_cnt'] ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr class="row-total fw-bold">
                        <td>Total</td>
                        <td class="text-end"><?= $totOpen ?></td>
                        <td class="text-end"><?= $totHold ?></td>
                        <td class="text-end"><?= $totOver ?></td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($showTechFilterBanner): ?>
            <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3" role="status">
                <div>
                    <strong>Technician</strong>
                    — <?= hd_esc($filterTechLabel) ?>
                    <?php if ($filterTechStats): ?>
                        <span class="text-muted ms-1">
                            (Open: <strong><?= (int) $filterTechStats['open_cnt'] ?></strong>,
                            On hold: <strong><?= (int) $filterTechStats['hold_cnt'] ?></strong>,
                            Overdue: <strong><?= (int) $filterTechStats['overdue_cnt'] ?></strong>)
                        </span>
                    <?php endif; ?>
                </div>
                <a class="btn btn-sm btn-outline-primary" href="<?= hd_esc(hd_helpdesk_index_path(['tab' => $drilldownTab])) ?>">Clear filter</a>
            </div>
        <?php endif; ?>

        <div class="hd-table-card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="fw-semibold"><?= $hdScopedTech ? 'My open tickets' : 'Open tickets' ?></span>
                <span class="badge-soft"><?= $hdScopedTech ? 'Your assignments' : 'Live queue' ?></span>
            </div>
            <div class="hd-table-wrap">
            <table class="table hd-data-table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Subject</th>
                        <th>Client</th>
                        <th>Requester</th>
                        <th>Technician</th>
                        <th>Status</th>
                        <th>Due</th>
                        <?php if (hd_can('edit ticket')): ?><th>Assign</th><?php endif; ?>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($t = $ticketList->fetch_assoc()):
                    $techLabel = trim(($t['tech_name'] ?? '') . ' ' . ($t['tech_surname'] ?? ''));
                    if ($techLabel === '') {
                        $techLabel = '—';
                    }
                    ?>
                    <tr class="<?= !empty($t['is_overdue']) ? 'table-warning' : '' ?>">
                        <td>#<?= (int) $t['id'] ?></td>
                        <td><?= hd_esc($t['subject']) ?></td>
                        <td><?= hd_esc($t['client_name'] ?? '') ?></td>
                        <td><?= hd_esc($t['requester_name'] ?? '') ?></td>
                        <td><?php
                            $aid = (int) ($t['assigned_user_id'] ?? 0);
                            if ($aid > 0 && $techLabel !== '—' && ($canDrilldownAllQueues || $aid === $uid)) {
                                echo '<a href="' . hd_esc(hd_helpdesk_module_path('technician.php', ['id' => $aid])) . '">' . hd_esc($techLabel) . '</a>';
                            } else {
                                echo hd_esc($techLabel);
                            }
                        ?></td>
                        <td><?= hd_esc($t['status']) ?></td>
                        <td><?= $t['due_at'] ? hd_esc($t['due_at']) : '—' ?></td>
                        <?php if (hd_can('edit ticket')): ?>
                            <td style="min-width:220px">
                                <form method="post" class="d-flex gap-1">
                                    <input type="hidden" name="ticket_id" value="<?= (int) $t['id'] ?>">
                                    <select name="assigned_user_id" class="form-select form-select-sm">
                                        <option value="">Unassigned</option>
                                        <?php foreach ($techUsers as $u):
                                            $nm = trim($u['name'] . ' ' . $u['surname']);
                                            if ($nm === '') {
                                                $nm = $u['username'];
                                            }
                                            ?>
                                            <option value="<?= (int) $u['id'] ?>" <?= (int) $t['assigned_user_id'] === (int) $u['id'] ? 'selected' : '' ?>>
                                                <?= hd_esc($nm) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-sm btn-outline-primary" name="assign_ticket" value="1">Save</button>
                                </form>
                            </td>
                        <?php endif; ?>
                        <td><a class="btn btn-sm btn-primary" href="ticket_view.php?id=<?= (int) $t['id'] ?>">Open</a></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            </div>
        </div>
    <?php else: ?>
        <div class="card panel-card">
            <div class="card-body">
                <h5 class="mb-2">Assets</h5>
                <p class="text-muted mb-3">This tab is a bridge to your existing Client assets data. It currently reuses the main Clients module rather than a separate Helpdesk-only asset screen.</p>
                <button type="button" class="btn btn-primary" onclick="openClientsInErpFrame()">Open Clients Module</button>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if (hd_can('create ticket')): ?>
<div class="modal fade" id="newRequestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Log New Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Client</label>
                            <select name="client_id" class="form-select">
                                <option value="">-</option>
                                <?php foreach ($clients as $c): ?>
                                    <option value="<?= (int) $c['id'] ?>"><?= hd_esc($c['client_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Requester</label>
                            <select name="requester_id" class="form-select" required>
                                <option value="">Select</option>
                                <?php foreach ($requesters as $rq): ?>
                                    <option value="<?= (int) $rq['id'] ?>" data-client-id="<?= (int) ($rq['client_id'] ?? 0) ?>"><?= hd_esc($rq['name'] . ' - ' . $rq['email']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <?php if ($hdScopedTech): ?>
                                <label class="form-label">Assignment</label>
                                <p class="small text-muted border rounded bg-light py-2 px-3 mb-0">New tickets are assigned to <strong>you</strong> automatically.</p>
                                <input type="hidden" name="assigned_user_id" value="<?= (int) $uid ?>">
                            <?php else: ?>
                                <label class="form-label">Technician</label>
                                <select name="assigned_user_id" class="form-select">
                                    <option value="">- Unassigned -</option>
                                    <?php foreach ($techUsers as $u):
                                        $nm = trim(($u['name'] ?? '') . ' ' . ($u['surname'] ?? ''));
                                        if ($nm === '') { $nm = (string) ($u['username'] ?? 'User'); }
                                    ?>
                                        <option value="<?= (int) $u['id'] ?>"><?= hd_esc($nm) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (hd_can('randomize assignment')): ?>
                                    <div class="form-text">Auto-random assignment active for your role.</div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Due by</label>
                            <input type="datetime-local" class="form-control" name="due_at" value="<?= hd_esc(hd_dashboard_next_due()) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Subject</label>
                            <input type="text" class="form-control" name="subject">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="4"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Attachments</label>
                            <input type="file" class="form-control" name="attach[]" multiple>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save_new_request" value="1" class="btn btn-primary">Add Request</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openClientsInErpFrame() {
    if (window.parent && window.parent.document) {
        const frame = window.parent.document.getElementById('moduleFrame');
        if (frame) {
            frame.src = 'modules/clientinfo/clientinfo.php';
            return;
        }
    }
    window.location.href = '../clientinfo/clientinfo.php';
}

(function () {
    const modal = document.getElementById('newRequestModal');
    if (!modal) return;
    const requester = modal.querySelector('select[name="requester_id"]');
    const client = modal.querySelector('select[name="client_id"]');
    if (!requester || !client) return;
    requester.addEventListener('change', function () {
        const opt = requester.options[requester.selectedIndex];
        const cid = opt ? (opt.getAttribute('data-client-id') || '') : '';
        if (cid && !client.value) {
            client.value = cid;
        }
    });
})();
</script>
</body>
</html>
