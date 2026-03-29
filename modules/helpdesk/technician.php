<?php
require_once __DIR__ . '/includes/bootstrap.php';
hd_require_any();

$techId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($techId < 1 || !hd_may_view_technician_profile($conn, $techId)) {
    http_response_code(403);
    die('Access denied or invalid technician.');
}

$reg = $conn->query(
    'SELECT id, username, name, surname, email, role_id FROM registers WHERE id = ' . $techId . ' LIMIT 1'
)->fetch_assoc();
if (!$reg || (int) $reg['role_id'] === 100) {
    http_response_code(404);
    die('Technician not found.');
}

$displayName = trim(($reg['name'] ?? '') . ' ' . ($reg['surname'] ?? ''));
if ($displayName === '') {
    $displayName = (string) ($reg['username'] ?? 'User');
}

$baseWhere = 'assigned_user_id = ' . $techId . ' AND merged_into_ticket_id IS NULL';

$cntRow = $conn->query(
    "SELECT
        SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open_cnt,
        SUM(CASE WHEN status = 'on_hold' THEN 1 ELSE 0 END) AS hold_cnt,
        SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) AS closed_cnt
     FROM helpdesk_tickets WHERE $baseWhere"
)->fetch_assoc();

$openCnt = (int) ($cntRow['open_cnt'] ?? 0);
$holdCnt = (int) ($cntRow['hold_cnt'] ?? 0);
$closedCnt = (int) ($cntRow['closed_cnt'] ?? 0);

$activeSql = "
    SELECT t.*, c.client_name, rq.name AS requester_name
    FROM helpdesk_tickets t
    LEFT JOIN clients c ON c.id = t.client_id
    LEFT JOIN helpdesk_requesters rq ON rq.id = t.requester_id
    WHERE $baseWhere AND t.status IN ('open','on_hold')
    ORDER BY t.is_overdue DESC, t.due_at IS NULL, t.due_at ASC, t.id DESC
";
$activeList = $conn->query($activeSql);

$perPage = 50;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$historyView = isset($_GET['view']) && $_GET['view'] === 'history';

$countClosed = $conn->query("SELECT COUNT(*) AS c FROM helpdesk_tickets WHERE $baseWhere AND status = 'closed'")->fetch_assoc();
$totalClosed = (int) ($countClosed['c'] ?? 0);
$totalPages = max(1, (int) ceil($totalClosed / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$historySql = "
    SELECT t.*, c.client_name, rq.name AS requester_name
    FROM helpdesk_tickets t
    LEFT JOIN clients c ON c.id = t.client_id
    LEFT JOIN helpdesk_requesters rq ON rq.id = t.requester_id
    WHERE $baseWhere AND t.status = 'closed'
    ORDER BY t.closed_at IS NULL, t.closed_at DESC, t.id DESC
    LIMIT $perPage OFFSET $offset
";
$historyList = $conn->query($historySql);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= hd_esc($displayName) ?> — Helpdesk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        <?= str_replace('</style>', '', str_replace('<style>', '', hd_ui_css())) ?>
        .hd-tech-stat{border:1px solid #e2e8f0;border-radius:12px;background:linear-gradient(180deg,#fff,#f8fafc);box-shadow:0 2px 12px rgba(15,23,42,.04);padding:1.1rem 1.25rem}
        .hd-tech-stat .lbl{font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;color:#64748b;font-weight:600}
        .hd-tech-stat .val{font-size:1.65rem;font-weight:800;color:#0f2942;line-height:1.2}
    </style>
</head>
<body class="py-4">
<div class="container-fluid hd-shell">
    <header class="hd-page-hero d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
            <h1 class="hd-heading mb-1"><?= hd_esc($displayName) ?></h1>
            <p class="text-muted small mb-0">
                <?= hd_esc($reg['username'] ?? '') ?>
                <?php if (!empty($reg['email'])): ?> · <?= hd_esc($reg['email']) ?><?php endif; ?>
                · ID <?= (int) $reg['id'] ?>
            </p>
        </div>
        <a class="btn btn-outline-secondary px-3" href="<?= hd_esc(hd_helpdesk_index_path()) ?>">Back to dashboard</a>
    </header>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="hd-tech-stat h-100">
                <div class="lbl">Open</div>
                <div class="val"><?= $openCnt ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="hd-tech-stat h-100">
                <div class="lbl">On hold</div>
                <div class="val"><?= $holdCnt ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="hd-tech-stat h-100">
                <div class="lbl">Closed (history)</div>
                <div class="val"><?= $closedCnt ?></div>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs hd-nav-tabs mb-0" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $historyView ? '' : 'active' ?>" id="tab-active" data-bs-toggle="tab" data-bs-target="#pane-active" type="button" role="tab">Active tickets</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $historyView ? 'active' : '' ?>" id="tab-history" data-bs-toggle="tab" data-bs-target="#pane-history" type="button" role="tab">Closed history</button>
        </li>
    </ul>
    <div class="tab-content">
        <div class="tab-pane fade pt-3 <?= $historyView ? '' : 'show active' ?>" id="pane-active" role="tabpanel">
            <div class="hd-table-card">
                <div class="card-header">Active tickets</div>
                <div class="hd-table-wrap">
                <table class="table hd-data-table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Subject</th>
                            <th>Client</th>
                            <th>Requester</th>
                            <th>Status</th>
                            <th>Due</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($activeList && $activeList->num_rows === 0): ?>
                        <tr><td colspan="7" class="text-muted text-center py-5">No active tickets.</td></tr>
                    <?php elseif ($activeList): ?>
                        <?php while ($t = $activeList->fetch_assoc()): ?>
                            <tr class="<?= !empty($t['is_overdue']) ? 'table-warning' : '' ?>">
                                <td>#<?= (int) $t['id'] ?></td>
                                <td><?= hd_esc($t['subject']) ?></td>
                                <td><?= hd_esc($t['client_name'] ?? '') ?></td>
                                <td><?= hd_esc($t['requester_name'] ?? '') ?></td>
                                <td><span class="<?= hd_status_badge_class($t['status'], !empty($t['is_overdue'])) ?>"><?= hd_esc($t['status']) ?></span></td>
                                <td><?= $t['due_at'] ? hd_esc($t['due_at']) : '—' ?></td>
                                <td><a class="btn btn-sm btn-primary" href="<?= hd_esc(hd_helpdesk_module_path('ticket_view.php', ['id' => (int) $t['id']])) ?>">Open</a></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
        <div class="tab-pane fade pt-3 <?= $historyView ? 'show active' : '' ?>" id="pane-history" role="tabpanel">
            <?php if ($totalClosed > $perPage): ?>
                <nav class="mb-2 d-flex flex-wrap gap-2 align-items-center">
                    <span class="text-muted small">Page <?= (int) $page ?> of <?= (int) $totalPages ?></span>
                    <?php if ($page > 1): ?>
                        <a class="btn btn-sm btn-outline-primary" href="<?= hd_esc(hd_helpdesk_module_path('technician.php', ['id' => $techId, 'view' => 'history', 'page' => $page - 1])) ?>">Previous</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a class="btn btn-sm btn-outline-primary" href="<?= hd_esc(hd_helpdesk_module_path('technician.php', ['id' => $techId, 'view' => 'history', 'page' => $page + 1])) ?>">Next</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
            <div class="hd-table-card">
                <div class="card-header">Closed tickets</div>
                <div class="hd-table-wrap">
                <table class="table hd-data-table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Subject</th>
                            <th>Client</th>
                            <th>Requester</th>
                            <th>Closed</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($historyList && $historyList->num_rows === 0): ?>
                        <tr><td colspan="6" class="text-muted text-center py-5">No closed tickets.</td></tr>
                    <?php elseif ($historyList): ?>
                        <?php while ($t = $historyList->fetch_assoc()): ?>
                            <tr>
                                <td>#<?= (int) $t['id'] ?></td>
                                <td><?= hd_esc($t['subject']) ?></td>
                                <td><?= hd_esc($t['client_name'] ?? '') ?></td>
                                <td><?= hd_esc($t['requester_name'] ?? '') ?></td>
                                <td><?= $t['closed_at'] ? hd_esc($t['closed_at']) : '—' ?></td>
                                <td><a class="btn btn-sm btn-outline-primary" href="<?= hd_esc(hd_helpdesk_module_path('ticket_view.php', ['id' => (int) $t['id']])) ?>">Open</a></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
