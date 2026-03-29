<?php
require_once __DIR__ . '/includes/bootstrap.php';
hd_require_any();

if (!hd_may_view_tickets_all()) {
    http_response_code(403);
    die('Access denied.');
}

$status = isset($_GET['status']) ? (string) $_GET['status'] : 'all';
if (!in_array($status, ['all', 'open', 'on_hold', 'closed'], true)) {
    $status = 'all';
}

$techFilter = isset($_GET['tech']) && $_GET['tech'] !== '' && ctype_digit((string) $_GET['tech'])
    ? (int) $_GET['tech'] : 0;

$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
$qSubject = trim((string) ($_GET['q'] ?? ''));

$perPage = 50;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

$where = ['t.merged_into_ticket_id IS NULL'];

if ($status !== 'all') {
    $where[] = "t.status = '" . $conn->real_escape_string($status) . "'";
}

if ($techFilter > 0) {
    $where[] = 't.assigned_user_id = ' . $techFilter;
}

if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    if ($status === 'closed') {
        $where[] = "t.closed_at >= '" . $conn->real_escape_string($dateFrom) . " 00:00:00'";
    } else {
        $where[] = "t.created_at >= '" . $conn->real_escape_string($dateFrom) . " 00:00:00'";
    }
}
if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    if ($status === 'closed') {
        $where[] = "t.closed_at <= '" . $conn->real_escape_string($dateTo) . " 23:59:59'";
    } else {
        $where[] = "t.created_at <= '" . $conn->real_escape_string($dateTo) . " 23:59:59'";
    }
}

if ($qSubject !== '') {
    $where[] = "t.subject LIKE '%" . $conn->real_escape_string($qSubject) . "%'";
}

$whereSql = implode(' AND ', $where);

$countRow = $conn->query("SELECT COUNT(*) AS c FROM helpdesk_tickets t WHERE $whereSql")->fetch_assoc();
$totalRows = (int) ($countRow['c'] ?? 0);
$totalPages = max(1, (int) ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$listSql = "
    SELECT t.*, c.client_name, rq.name AS requester_name,
        u.name AS tech_name, u.surname AS tech_surname, u.username AS tech_username
    FROM helpdesk_tickets t
    LEFT JOIN clients c ON c.id = t.client_id
    LEFT JOIN helpdesk_requesters rq ON rq.id = t.requester_id
    LEFT JOIN registers u ON u.id = t.assigned_user_id
    WHERE $whereSql
    ORDER BY t.id DESC
    LIMIT $perPage OFFSET $offset
";
$list = $conn->query($listSql);

$techs = $conn->query("SELECT id, name, surname, username FROM registers WHERE (role_id IS NULL OR role_id <> 100) ORDER BY name, surname");
$techList = [];
while ($row = $techs->fetch_assoc()) {
    $techList[] = $row;
}

$buildQuery = function (array $extra) use ($status, $techFilter, $dateFrom, $dateTo, $qSubject) {
    $p = array_merge([
        'status' => $status,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'q' => $qSubject,
    ], $extra);
    if ($techFilter > 0) {
        $p['tech'] = $techFilter;
    }
    return array_filter($p, function ($v) {
        return $v !== '' && $v !== null;
    });
};

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All tickets — Helpdesk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        <?= str_replace('</style>', '', str_replace('<style>', '', hd_ui_css())) ?>
    </style>
</head>
<body class="py-4">
<div class="container-fluid hd-shell">
    <header class="hd-page-hero d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
            <h1 class="hd-heading mb-1">All tickets</h1>
            <p class="text-muted small mb-0" style="max-width:42rem">Browse open, on-hold, and closed tickets. With status <strong>Closed</strong>, the date range applies to <strong>closed at</strong>; otherwise it applies to <strong>created at</strong>.</p>
        </div>
        <a class="btn btn-outline-secondary px-3" href="<?= hd_esc(hd_helpdesk_index_path()) ?>">Back to dashboard</a>
    </header>

    <div class="card hd-filter-card hd-card mb-3">
        <div class="card-header">Filters</div>
        <div class="card-body">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All</option>
                        <option value="open" <?= $status === 'open' ? 'selected' : '' ?>>Open</option>
                        <option value="on_hold" <?= $status === 'on_hold' ? 'selected' : '' ?>>On hold</option>
                        <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>Closed</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Technician</label>
                    <select name="tech" class="form-select">
                        <option value="">All</option>
                        <?php foreach ($techList as $u):
                            $lbl = trim(($u['name'] ?? '') . ' ' . ($u['surname'] ?? ''));
                            if ($lbl === '') {
                                $lbl = $u['username'] ?? ('User ' . (int) $u['id']);
                            }
                            ?>
                            <option value="<?= (int) $u['id'] ?>" <?= $techFilter === (int) $u['id'] ? 'selected' : '' ?>><?= hd_esc($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date from</label>
                    <input type="date" name="date_from" class="form-control" value="<?= hd_esc($dateFrom) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date to</label>
                    <input type="date" name="date_to" class="form-control" value="<?= hd_esc($dateTo) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Subject contains</label>
                    <input type="text" name="q" class="form-control" value="<?= hd_esc($qSubject) ?>" placeholder="Search…">
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100">Apply</button>
                </div>
            </form>
        </div>
    </div>

    <div class="hd-results-bar">
        <span class="text-secondary small fw-medium"><?= (int) $totalRows ?> ticket<?= $totalRows !== 1 ? 's' : '' ?> · page <?= (int) $page ?> of <?= (int) $totalPages ?></span>
        <div class="btn-group shadow-sm">
            <?php if ($page > 1): ?>
                <a class="btn btn-sm btn-outline-primary" href="<?= hd_esc(hd_helpdesk_module_path('tickets_all.php', $buildQuery(['page' => $page - 1]))) ?>">Previous</a>
            <?php endif; ?>
            <?php if ($page < $totalPages): ?>
                <a class="btn btn-sm btn-outline-primary" href="<?= hd_esc(hd_helpdesk_module_path('tickets_all.php', $buildQuery(['page' => $page + 1]))) ?>">Next</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="hd-table-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Results</span>
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
                    <th>Created</th>
                    <th>Closed</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($list && $list->num_rows === 0): ?>
                <tr><td colspan="9" class="text-muted text-center py-5">No tickets match your filters.</td></tr>
            <?php elseif ($list): ?>
                <?php while ($t = $list->fetch_assoc()):
                    $tlbl = trim(($t['tech_name'] ?? '') . ' ' . ($t['tech_surname'] ?? ''));
                    if ($tlbl === '') {
                        $tlbl = $t['tech_username'] ?? '—';
                    }
                    if ((int) ($t['assigned_user_id'] ?? 0) === 0) {
                        $tlbl = '—';
                    }
                    ?>
                    <tr class="<?= !empty($t['is_overdue']) && $t['status'] !== 'closed' ? 'table-warning' : '' ?>">
                        <td>#<?= (int) $t['id'] ?></td>
                        <td><?= hd_esc($t['subject']) ?></td>
                        <td><?= hd_esc($t['client_name'] ?? '') ?></td>
                        <td><?= hd_esc($t['requester_name'] ?? '') ?></td>
                        <td><?= hd_esc($tlbl) ?></td>
                        <td><span class="<?= hd_status_badge_class($t['status'], !empty($t['is_overdue']) && $t['status'] !== 'closed') ?>"><?= hd_esc($t['status']) ?></span></td>
                        <td><?= hd_esc($t['created_at']) ?></td>
                        <td><?= !empty($t['closed_at']) ? hd_esc($t['closed_at']) : '—' ?></td>
                        <td><a class="btn btn-sm btn-primary" href="<?= hd_esc(hd_helpdesk_module_path('ticket_view.php', ['id' => (int) $t['id']])) ?>">Open</a></td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
</body>
</html>
