<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
hd_require('reports');
$techs = $conn->query("SELECT id, name, surname, username FROM registers WHERE (role_id IS NULL OR role_id <> 100) ORDER BY name, surname");
$techList = [];
while ($t = $techs->fetch_assoc()) {
    $techList[] = $t;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Helpdesk reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        <?= str_replace('</style>', '', str_replace('<style>', '', hd_ui_css())) ?>
        body { background: #f5f7fb; }
        .hd-card { border: 0; border-radius: 12px; box-shadow: 0 8px 24px rgba(16,24,40,.08); }
    </style>
</head>
<body class="p-4">
<div class="container">
    <h3 class="hd-page-title mb-1">Helpdesk reports</h3>
    <p class="hd-page-subtitle mb-3">Export open, closed, on-hold and overdue calls with optional date range and technician filters.</p>
    <div class="card hd-card mb-4">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Date from</label>
                    <input type="date" class="form-control" id="dateFrom">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date to</label>
                    <input type="date" class="form-control" id="dateTo">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Technician (optional)</label>
                    <select class="form-select" id="tech">
                        <option value="">All technicians</option>
                        <?php foreach ($techList as $u):
                            $lbl = trim(($u['name'] ?? '') . ' ' . ($u['surname'] ?? ''));
                            if ($lbl === '') { $lbl = $u['username'] ?? ('User ' . (int) $u['id']); }
                        ?>
                            <option value="<?= (int) $u['id'] ?>"><?= hd_esc($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-3">
                <button class="btn btn-outline-primary" onclick="goExport('open')">Open calls CSV</button>
                <button class="btn btn-outline-primary" onclick="goExport('closed')">Closed calls CSV</button>
                <button class="btn btn-outline-primary" onclick="goExport('on_hold')">On-hold calls CSV</button>
                <button class="btn btn-outline-primary" onclick="goExport('overdue')">Overdue calls CSV</button>
                <a class="btn btn-outline-success" href="billing_report.php">Billing report</a>
                <?php if (hd_can('bulk delete')): ?>
                    <a class="btn btn-outline-danger" href="bulk_delete.php">Bulk delete closed tickets</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <a href="../index.php" class="btn btn-secondary">Back</a>
</div>
<script>
function goExport(type) {
    const params = new URLSearchParams();
    params.set('type', type);
    const d1 = document.getElementById('dateFrom').value;
    const d2 = document.getElementById('dateTo').value;
    const tech = document.getElementById('tech').value;
    if (d1) params.set('date_from', d1);
    if (d2) params.set('date_to', d2);
    if (tech) params.set('tech', tech);
    window.location.href = 'export.php?' + params.toString();
}
</script>
</body>
</html>
