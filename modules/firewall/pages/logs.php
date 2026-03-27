<?php
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
require_once __DIR__ . '/../includes/functions.php';

$siteId = $_GET['site_id'] ?? null;
$actionPage = max(1, (int)($_GET['action_page'] ?? 1));
$pingPage = max(1, (int)($_GET['ping_page'] ?? 1));
$perPage = 20;
$actionOffset = ($actionPage - 1) * $perPage;
$pingOffset = ($pingPage - 1) * $perPage;

// Get all sites with log counts
$sites = db()->query("
    SELECT s.*,
           (SELECT COUNT(*) FROM site_actions WHERE site_id = s.id) AS action_count,
           (SELECT COUNT(*) FROM ping_logs WHERE site_id = s.id) AS ping_count
    FROM sites s
    ORDER BY s.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// If site_id is selected, get logs for that site
$logs = [];
$pingLogs = [];
$selectedSite = null;
$totalLogs = 0;
$totalPingLogs = 0;

if ($siteId) {
    $selectedSite = db()->prepare("SELECT * FROM sites WHERE id = ?");
    $selectedSite->execute([$siteId]);
    $selectedSite = $selectedSite->fetch(PDO::FETCH_ASSOC);
    
    if ($selectedSite) {
        // Get action logs with pagination
        $totalLogs = db()->prepare("SELECT COUNT(*) FROM site_actions WHERE site_id = ?");
        $totalLogs->execute([$siteId]);
        $totalLogs = $totalLogs->fetchColumn();
        
        // Get action logs with pagination (LIMIT/OFFSET must be integers, not bound parameters)
        $logs = db()->prepare("
            SELECT a.*, s.name AS site_name, s.primary_ip
            FROM site_actions a
            JOIN sites s ON a.site_id = s.id
            WHERE a.site_id = ?
            ORDER BY a.id DESC
            LIMIT " . (int)$perPage . " OFFSET " . (int)$actionOffset . "
        ");
        $logs->execute([$siteId]);
        $logs = $logs->fetchAll(PDO::FETCH_ASSOC);
        
        // Get ping logs with pagination
        $totalPingLogs = db()->prepare("SELECT COUNT(*) FROM ping_logs WHERE site_id = ?");
        $totalPingLogs->execute([$siteId]);
        $totalPingLogs = $totalPingLogs->fetchColumn();
        
        $pingLogs = db()->prepare("
            SELECT p.*, s.name AS site_name, s.primary_ip
            FROM ping_logs p
            JOIN sites s ON p.site_id = s.id
            WHERE p.site_id = ?
            ORDER BY p.id DESC
            LIMIT " . (int)$perPage . " OFFSET " . (int)$pingOffset . "
        ");
        $pingLogs->execute([$siteId]);
        $pingLogs = $pingLogs->fetchAll(PDO::FETCH_ASSOC);
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Action Logs - Firewall Monitor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            background: #f4f7fa;
            min-height: 100vh;
            padding: 20px;
            font-family: 'Inter', sans-serif;
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            margin-bottom: 24px;
        }
        .card-body {
            padding: 24px;
        }
        .table {
            margin-bottom: 0;
        }
        .table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #e5e7eb;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #374151;
            padding: 16px 12px;
        }
        .table tbody td {
            padding: 16px 12px;
            vertical-align: middle;
            border-bottom: 1px solid #f3f4f6;
        }
        .table tbody tr:hover {
            background-color: #f9fafb;
        }
        .output {
            max-width: 400px;
            max-height: 150px;
            overflow: auto;
            background: #f3f4f6;
            padding: 10px;
            border-radius: 6px;
            font-family: 'Monaco', 'Courier New', monospace;
            font-size: 12px;
            white-space: pre-wrap;
            word-break: break-all;
        }
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #6b7280;
        }
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        .badge {
            font-weight: 500;
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 6px;
        }
        .btn {
            border-radius: 8px;
            font-weight: 500;
        }
        h1.h3, h2.h5, h3.h5 {
            font-weight: 600;
            color: #111827;
        }
        code {
            background: #f3f4f6;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        .pagination {
            margin-top: 20px;
        }
        .page-link {
            border-radius: 6px;
            margin: 0 2px;
            border: 1px solid #d1d5db;
        }
    </style>
</head>
<body>
    <div class="container-fluid px-4">
        <div class="card shadow-lg mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <h1 class="h3 mb-0">
                        <i class="bi bi-list-ul text-primary"></i> Action Logs
                    </h1>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Dashboard
                        </a>
                        <a href="../actions/logout.php" class="btn btn-outline-danger">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!$siteId): ?>
            <!-- Site List View -->
            <div class="card shadow-lg">
                <div class="card-body p-4">
                    <h2 class="h5 mb-4">
                        <i class="bi bi-building"></i> Select a Site to View Logs
                    </h2>
                    <?php if (empty($sites)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-info-circle" style="font-size: 48px;"></i>
                            <p class="mt-3">No sites found. Add sites to start monitoring.</p>
                        </div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($sites as $site): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card h-100 border">
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <i class="bi bi-router"></i> <?=htmlspecialchars($site['name'])?>
                                        </h5>
                                        <p class="card-text text-muted small mb-2">
                                            <i class="bi bi-geo-alt"></i> <?=htmlspecialchars($site['location'] ?? 'N/A')?><br>
                                            <i class="bi bi-hdd-network"></i> Primary: <code><?=$site['primary_ip']?></code><br>
                                            <i class="bi bi-hdd-stack"></i> Secondary: <code><?=$site['secondary_ip']?></code>
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center mt-3">
                                            <div>
                                                <span class="badge bg-info"><?=$site['action_count']?> Actions</span>
                                                <span class="badge bg-secondary"><?=$site['ping_count']?> Pings</span>
                                            </div>
                                            <a href="?site_id=<?=$site['id']?>" class="btn btn-primary btn-sm">
                                                <i class="bi bi-eye"></i> View Logs
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Site Detail View -->
            <?php if ($selectedSite): ?>
                <div class="card shadow-lg mb-3">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="h5 mb-1">
                                    <i class="bi bi-router"></i> <?=htmlspecialchars($selectedSite['name'])?>
                                </h2>
                                <small class="text-muted">
                                    <i class="bi bi-hdd-network"></i> Primary IP: <code><?=$selectedSite['primary_ip']?></code> | 
                                    <i class="bi bi-hdd-stack"></i> Secondary IP: <code><?=$selectedSite['secondary_ip']?></code>
                                </small>
                            </div>
                            <a href="logs.php" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-arrow-left"></i> Back to Sites
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Action Logs -->
                <div class="card shadow-lg mb-4">
                    <div class="card-body p-4">
                        <h3 class="h5 mb-4">
                            <i class="bi bi-list-ul"></i> Action Logs
                            <span class="badge bg-secondary"><?=$totalLogs?> total</span>
                        </h3>
                        <?php if (empty($logs)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-file-text"></i> No action logs for this site yet.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Time</th>
                                            <th>Action</th>
                                            <th>Initiated By</th>
                                            <th>Status Before</th>
                                            <th>Status After</th>
                                            <th>SSH Status</th>
                                            <th>Command</th>
                                            <th>Output</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($logs as $l): ?>
                                        <tr>
                                            <td>
                                                <div style="white-space: nowrap;">
                                                    <?=date('M d, Y', strtotime($l['created_at']))?><br>
                                                    <small class="text-muted"><?=date('H:i:s', strtotime($l['created_at']))?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?=$l['action_type']==='failover'||$l['action_type']==='auto_failover'?'danger':($l['action_type']==='failback'?'success':($l['action_type']==='reboot'?'warning':($l['action_type']==='start'?'info':'secondary')))?>">
                                                    <?=htmlspecialchars($l['action_type'])?>
                                                </span>
                                            </td>
                                            <td>
                                                <i class="bi bi-person"></i> <?=htmlspecialchars($l['initiated_by'])?>
                                            </td>
                                            <td>
                                                <?php if ($l['status_before']): ?>
                                                    <span class="badge bg-<?=$l['status_before']==='UP'?'success':($l['status_before']==='DEGRADED'?'warning':($l['status_before']==='DOWN'?'danger':'secondary'))?>">
                                                        <?=htmlspecialchars($l['status_before'])?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($l['status_after']): ?>
                                                    <span class="badge bg-<?=$l['status_after']==='UP'?'success':($l['status_after']==='DEGRADED'?'warning':($l['status_after']==='DOWN'?'danger':'secondary'))?>">
                                                        <?=htmlspecialchars($l['status_after'])?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($l['ssh_success'] !== null): ?>
                                                    <?php if ($l['ssh_success']): ?>
                                                        <span class="badge bg-success">
                                                            <i class="bi bi-check-circle"></i> Success
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">
                                                            <i class="bi bi-x-circle"></i> Failed
                                                        </span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($l['command_executed']): ?>
                                                    <code style="font-size: 11px;" title="<?=htmlspecialchars($l['command_executed'])?>">
                                                        <?=htmlspecialchars(substr($l['command_executed'], 0, 30))?><?=strlen($l['command_executed']) > 30 ? '...' : ''?>
                                                    </code>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($l['ssh_output']): ?>
                                                    <div class="output"><?=htmlspecialchars($l['ssh_output'])?></div>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination for Action Logs -->
                            <?php if ($totalLogs > $perPage): ?>
                                <nav aria-label="Action logs pagination" class="mt-3">
                                    <ul class="pagination justify-content-center">
                                        <?php
                                        $totalActionPages = ceil($totalLogs / $perPage);
                                        if ($actionPage > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?site_id=<?=$siteId?>&action_page=<?=$actionPage-1?>&ping_page=<?=$pingPage?>">Previous</a>
                                            </li>
                                        <?php endif;
                                        for ($i = 1; $i <= $totalActionPages; $i++):
                                            if ($i == 1 || $i == $totalActionPages || ($i >= $actionPage - 2 && $i <= $actionPage + 2)):
                                        ?>
                                        <li class="page-item <?=$i == $actionPage ? 'active' : ''?>">
                                            <a class="page-link" href="?site_id=<?=$siteId?>&action_page=<?=$i?>&ping_page=<?=$pingPage?>"><?=$i?></a>
                                        </li>
                                        <?php elseif ($i == $actionPage - 3 || $i == $actionPage + 3): ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">...</span>
                                            </li>
                                        <?php endif; endfor;
                                        if ($actionPage < $totalActionPages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?site_id=<?=$siteId?>&action_page=<?=$actionPage+1?>&ping_page=<?=$pingPage?>">Next</a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Ping Logs -->
                <div class="card shadow-lg">
                    <div class="card-body p-4">
                        <h3 class="h5 mb-4">
                            <i class="bi bi-activity"></i> Ping Check History
                            <span class="badge bg-secondary"><?=$totalPingLogs?> total</span>
                        </h3>
                        <?php if (empty($pingLogs)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-info-circle"></i> No ping logs yet. Start monitoring to see ping history.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Time</th>
                                            <th>Ping IP</th>
                                            <th>Ping Count</th>
                                            <th>Successes</th>
                                            <th>Failures</th>
                                            <th>Status</th>
                                            <th>Status Changed</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pingLogs as $p): ?>
                                        <tr>
                                            <td>
                                                <div style="white-space: nowrap;">
                                                    <?=date('M d', strtotime($p['created_at']))?><br>
                                                    <small class="text-muted"><?=date('H:i:s', strtotime($p['created_at']))?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <code class="bg-light px-2 py-1 rounded"><?=$p['primary_ip']?></code>
                                            </td>
                                            <td><?=$p['ping_count']?> pings<br><small class="text-muted"><?=$p['ping_interval_sec']?>s interval</small></td>
                                            <td><span class="badge bg-success"><?=$p['successes']?></span></td>
                                            <td><span class="badge bg-danger"><?=$p['failures']?></span></td>
                                            <td>
                                                <span class="badge bg-<?=$p['status']==='UP'?'success':($p['status']==='DEGRADED'?'warning':'danger')?>">
                                                    <?=$p['status']?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($p['status_changed']): ?>
                                                    <span class="badge bg-warning">
                                                        <i class="bi bi-arrow-repeat"></i> Changed
                                                    </span>
                                                    <?php if ($p['previous_status']): ?>
                                                        <br><small class="text-muted">from <?=$p['previous_status']?></small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination for Ping Logs -->
                            <?php if ($totalPingLogs > $perPage): ?>
                                <nav aria-label="Ping logs pagination" class="mt-3">
                                    <ul class="pagination justify-content-center">
                                        <?php
                                        $totalPingPages = ceil($totalPingLogs / $perPage);
                                        if ($pingPage > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?site_id=<?=$siteId?>&action_page=<?=$actionPage?>&ping_page=<?=$pingPage-1?>">Previous</a>
                                            </li>
                                        <?php endif;
                                        for ($i = 1; $i <= $totalPingPages; $i++):
                                            if ($i == 1 || $i == $totalPingPages || ($i >= $pingPage - 2 && $i <= $pingPage + 2)):
                                        ?>
                                        <li class="page-item <?=$i == $pingPage ? 'active' : ''?>">
                                            <a class="page-link" href="?site_id=<?=$siteId?>&action_page=<?=$actionPage?>&ping_page=<?=$i?>"><?=$i?></a>
                                        </li>
                                        <?php elseif ($i == $pingPage - 3 || $i == $pingPage + 3): ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">...</span>
                                            </li>
                                        <?php endif; endfor;
                                        if ($pingPage < $totalPingPages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?site_id=<?=$siteId?>&action_page=<?=$actionPage?>&ping_page=<?=$pingPage+1?>">Next</a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    Site not found. <a href="logs.php">Back to site list</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

