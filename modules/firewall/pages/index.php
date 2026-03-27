<?php
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
require_once __DIR__ . '/../includes/functions.php';

$status = $_GET['status'] ?? null;
$search = $_GET['search'] ?? null;

$sites = getSites($status, $search);

function statusColor($s) {
    return [
        'UP'       => '#10b981',
        'DEGRADED' => '#f59e0b',
        'DOWN'     => '#ef4444',
        'UNKNOWN'  => '#6b7280'
    ][$s] ?? '#6b7280';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firewall Monitor Dashboard</title>
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
        .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
            vertical-align: middle;
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            margin-bottom: 24px;
        }
        .card-header {
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
            padding: 20px 24px;
            border-radius: 12px 12px 0 0 !important;
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
            white-space: nowrap;
        }
        .table tbody td {
            padding: 16px 12px;
            vertical-align: middle;
            border-bottom: 1px solid #f3f4f6;
        }
        .table tbody tr:hover {
            background-color: #f9fafb;
        }
        .table tbody tr:last-child td {
            border-bottom: none;
        }
        .table-hover tbody tr:hover {
            background-color: #f9fafb;
        }
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            align-items: center;
            justify-content: flex-start;
            min-width: 0;
        }
        .action-buttons form {
            display: inline-flex;
            gap: 4px;
        }
        .action-buttons .btn {
            white-space: nowrap;
            font-size: 11px;
            padding: 5px 8px;
            border-radius: 5px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            line-height: 1.2;
            margin-top: 2px;
            border: 1px solid transparent;
        }
        .action-buttons .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
        }
        .action-buttons .btn i {
            font-size: 13px;
            margin-right: 3px;
        }
        .action-buttons .btn-sm {
            padding: 5px 8px;
            font-size: 11px;
        }
        /* Icon only on very small screens, icon + text on larger */
        .action-buttons .btn .btn-text {
            display: none;
        }
        @media (min-width: 576px) {
            .action-buttons .btn .btn-text {
                display: inline;
            }
            .action-buttons .btn {
                padding: 5px 10px;
                font-size: 12px;
            }
        }
        /* Compact layout for action column */
        .table tbody td:last-child {
            min-width: 200px;
            max-width: 350px;
        }
        .btn-group {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .btn-group .btn {
            white-space: nowrap;
            border-radius: 8px;
            font-weight: 500;
            padding: 10px 16px;
            font-size: 14px;
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
            color: #9ca3af;
        }
        h1.h3 {
            font-weight: 600;
            color: #111827;
            font-size: 24px;
        }
        .badge {
            font-weight: 500;
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 6px;
        }
        code {
            background: #f3f4f6;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            color: #1f2937;
            font-family: 'Courier New', monospace;
        }
        .form-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .form-control, .form-select {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 14px;
        }
        .form-control:focus, .form-select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
        }
        .refresh-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 12px;
            padding: 8px 12px;
            background: #f8f9fa;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
        }
        .refresh-indicator .spinner-border-sm {
            width: 14px;
            height: 14px;
            border-width: 2px;
        }
        .refresh-indicator.active {
            color: #3b82f6;
            background: #eff6ff;
            border-color: #3b82f6;
        }
        .refresh-indicator .refresh-counter {
            font-weight: 600;
            color: #3b82f6;
        }
        .refresh-indicator.active .refresh-counter {
            animation: pulse 1s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        @media (max-width: 768px) {
            .btn-group {
                flex-direction: column;
                width: 100%;
            }
            .btn-group .btn {
                width: 100%;
            }
            .table tbody td:last-child {
                min-width: auto;
                max-width: none;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid px-4">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($_SESSION['error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> <?= htmlspecialchars($_SESSION['success']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <div class="card shadow-lg mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <h1 class="h3 mb-0">
                        <i class="bi bi-shield-check text-primary"></i> Firewall Monitor Dashboard
                    </h1>
                    <div class="btn-group" role="group">
                        <a href="index.php" class="btn btn-primary">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                        <a href="site_form.php" class="btn btn-success">
                            <i class="bi bi-plus-circle"></i> Add New Site
                        </a>
                        <a href="manage_emails.php" class="btn btn-info text-white">
                            <i class="bi bi-envelope"></i> Manage Emails
                        </a>
                        <a href="logs.php" class="btn btn-secondary">
                            <i class="bi bi-list-ul"></i> View Logs
                        </a>
                        <a href="settings.php" class="btn btn-warning text-white">
                            <i class="bi bi-gear"></i> Settings
                        </a>
                        <a href="../actions/logout.php" class="btn btn-outline-danger">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </div>
                </div>

                <form method="get" class="bg-light p-4 rounded-3 border">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Status Filter</label>
                            <select name="status" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="UP" <?=$status==='UP'?'selected':''?>>UP</option>
                                <option value="DEGRADED" <?=$status==='DEGRADED'?'selected':''?>>DEGRADED</option>
                                <option value="DOWN" <?=$status==='DOWN'?'selected':''?>>DOWN</option>
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label fw-bold">Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Search by site name..." value="<?=htmlspecialchars($search ?? '')?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i> Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-lg">
            <div class="card-body p-4">
                <?php if (empty($sites)): ?>
                    <div class="empty-state">
                        <i class="bi bi-router"></i>
                        <h3>No sites found</h3>
                        <p class="text-muted">Add your first site to start monitoring</p>
                        <a href="site_form.php" class="btn btn-primary mt-3">
                            <i class="bi bi-plus-circle"></i> Add New Site
                        </a>
                    </div>
                <?php else: ?>
                    <!-- <div class="refresh-indicator" id="refreshIndicator" style="display: none;">
                        <i class="bi bi-arrow-clockwise"></i>
                        <span>Auto-refreshing every <strong>5 seconds</strong></span>
                        <span class="text-muted">|</span>
                        <span class="refresh-counter" id="refreshCounter">0</span> <span class="text-muted">refreshes</span>
                        <span class="text-muted">|</span>
                        <span id="lastUpdate" class="text-muted">Last updated: <span id="lastUpdateTime">Just now</span></span>
                    </div> -->
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Status</th>
                                    <th>Site Name</th>
                                    <th>Location</th>
                                    <th>Primary IP</th>
                                    <!-- <th>Secondary IP</th> -->
                                    <th>Ping Settings</th>
                                    <th>Monitoring</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="sitesTableBody">
                                <?php foreach ($sites as $s): ?>
                                <tr>
                                    <td>
                                        <span class="status-dot" style="background: <?=statusColor($s['status'])?>;"></span>
                                        <span class="badge bg-<?=$s['status']==='UP'?'success':($s['status']==='DEGRADED'?'warning':($s['status']==='DOWN'?'danger':'secondary'))?>">
                                            <?=$s['status']?>
                                        </span>
                                    </td>
                                    <td><strong><?=htmlspecialchars($s['name'])?></strong></td>
                                    <td><?=htmlspecialchars($s['location'] ?? 'N/A')?></td>
                                    <td><code class="bg-light px-2 py-1 rounded"><?=$s['primary_ip']?></code></td>
                                    <!-- <td><code class="bg-light px-2 py-1 rounded"><?=$s['secondary_ip']?></code></td> -->
                                    <td>
                                        <small>
                                            <strong><?=$s['ping_count'] ?: PING_DEFAULT_COUNT?></strong> pings per batch<br>
                                            <span class="text-muted">Wait <?=$s['ping_interval_sec'] ?: PING_DEFAULT_BATCH_INTERVAL_SEC?>s between batches</span>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($s['monitoring_enabled']): ?>
                                            <span class="badge bg-info">Enabled</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Disabled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <form method="post" action="../actions/action.php" class="d-inline" id="action-form-<?=$s['id']?>">
                                                <input type="hidden" name="site_id" value="<?=$s['id']?>">
                                                <button type="submit" name="action" value="failover" class="btn btn-danger btn-sm" onclick="return confirm('Execute Failover for <?=htmlspecialchars($s['name'])?>?');" title="Execute Failover">
                                                    <i class="bi bi-exclamation-triangle"></i> <span class="btn-text">Failover</span>
                                                </button>
                                                <button type="submit" name="action" value="failback" class="btn btn-success btn-sm" onclick="return confirm('Execute Fail Back for <?=htmlspecialchars($s['name'])?>?');" title="Execute Fail Back">
                                                    <i class="bi bi-arrow-counterclockwise"></i> <span class="btn-text">Fail Back</span>
                                                </button>
                                                <button type="submit" name="action" value="reboot" class="btn btn-warning btn-sm" onclick="return confirm('Execute Reboot for <?=htmlspecialchars($s['name'])?>?');" title="Reboot">
                                                    <i class="bi bi-arrow-clockwise"></i> <span class="btn-text">Reboot</span>
                                                </button>
                                                <button type="submit" name="action" value="<?=$s['monitoring_enabled']?'stop':'start'?>" class="btn btn-secondary btn-sm" title="<?=$s['monitoring_enabled']?'Stop':'Start'?> Monitoring">
                                                    <i class="bi bi-<?=$s['monitoring_enabled']?'pause':'play'?>"></i> <span class="btn-text"><?=$s['monitoring_enabled']?'Stop':'Start'?></span>
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-info btn-sm" onclick="openCustomCommandModal(<?=$s['id']?>, '<?=htmlspecialchars($s['name'], ENT_QUOTES)?>', '<?=htmlspecialchars($s['primary_ip'], ENT_QUOTES)?>')" title="Run Custom Command">
                                                <i class="bi bi-terminal"></i> <span class="btn-text">Custom</span>
                                            </button>
                                            <a href="site_form.php?id=<?=$s['id']?>" class="btn btn-outline-primary btn-sm" title="Edit Site">
                                                <i class="bi bi-pencil"></i> <span class="btn-text">Edit</span>
                                            </a>
                                            <a href="../actions/delete_site.php?id=<?=$s['id']?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to delete this site?')" title="Delete Site">
                                                <i class="bi bi-trash"></i> <span class="btn-text">Delete</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Automatic Actions Section -->
        <!-- <div class="card shadow-lg mb-4">
            <div class="card-header">
                <h2 class="h5 mb-0">
                    <i class="bi bi-arrow-repeat text-info"></i> Recent Actions
                    <small class="text-muted">(Auto-refreshing every 5 seconds)</small>
                </h2>
            </div>
            <div class="card-body p-4">
                <div id="recentActionsContainer">
                    <div class="text-center text-muted py-4">
                        <div class="spinner-border spinner-border-sm" role="status"></div>
                        <span class="ms-2">Loading recent actions...</span>
                    </div>
                </div>
            </div>
        </div> -->

        <!-- Custom Command Modal -->
        <div class="modal fade" id="customCommandModal" tabindex="-1" aria-labelledby="customCommandModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="customCommandModalLabel">
                            <i class="bi bi-terminal"></i> Run Custom Command
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="customCommandForm" method="post" action="../actions/action.php">
                        <div class="modal-body">
                            <input type="hidden" name="site_id" id="customSiteId">
                            <input type="hidden" name="action" value="custom">
                            
                            <div class="mb-3">
                                <label class="form-label"><strong>Site:</strong></label>
                                <p class="mb-0" id="customSiteName"></p>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label"><strong>Primary IP:</strong></label>
                                <p class="mb-0"><code id="customPrimaryIp"></code></p>
                            </div>
                            
                            <div class="mb-3">
                                <label for="customCommand" class="form-label">
                                    <strong>Command/Script:</strong>
                                    <small class="text-muted">(Will be executed on the server)</small>
                                </label>
                                <textarea class="form-control" id="customCommand" name="custom_command" rows="4" placeholder="Enter your command or script here..." required></textarea>
                                <small class="form-text text-muted">Example: whoami, ls -la, /path/to/script.sh, etc.</small>
                            </div>
                            
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i>
                                <strong>Warning:</strong> Make sure the command is safe and intended. The command will be executed immediately upon confirmation.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-info">
                                <i class="bi bi-play-circle"></i> Execute Command
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh configuration
        const REFRESH_INTERVAL = 5000; // 5 seconds
        let refreshTimer = null;
        let isRefreshing = false;
        let refreshCount = 0;

        // Get current filter values
        function getFilters() {
            const urlParams = new URLSearchParams(window.location.search);
            return {
                status: urlParams.get('status') || '',
                search: urlParams.get('search') || ''
            };
        }

        // Format time ago
        function timeAgo(date) {
            const now = new Date();
            const diff = Math.floor((now - date) / 1000);
            
            if (diff < 10) return 'Just now';
            if (diff < 60) return diff + ' seconds ago';
            if (diff < 3600) return Math.floor(diff / 60) + ' minutes ago';
            return Math.floor(diff / 3600) + ' hours ago';
        }

        // Update last update time display
        function updateLastUpdateTime() {
            const timeEl = document.getElementById('lastUpdateTime');
            if (timeEl) {
                timeEl.textContent = new Date().toLocaleTimeString();
            }
            // Update refresh counter
            refreshCount++;
            const counterEl = document.getElementById('refreshCounter');
            if (counterEl) {
                counterEl.textContent = refreshCount;
            }
        }

        // Open custom command modal
        function openCustomCommandModal(siteId, siteName, primaryIp) {
            document.getElementById('customSiteId').value = siteId;
            document.getElementById('customSiteName').textContent = siteName;
            document.getElementById('customPrimaryIp').textContent = primaryIp;
            document.getElementById('customCommand').value = '';
            
            const modal = new bootstrap.Modal(document.getElementById('customCommandModal'));
            modal.show();
        }

        // Handle custom command form submission
        document.addEventListener('DOMContentLoaded', function() {
            const customForm = document.getElementById('customCommandForm');
            if (customForm) {
                customForm.addEventListener('submit', function(e) {
                    const command = document.getElementById('customCommand').value.trim();
                    if (!command) {
                        e.preventDefault();
                        alert('Please enter a command to execute.');
                        return false;
                    }
                    
                    const siteName = document.getElementById('customSiteName').textContent;
                    if (!confirm(`Execute custom command on "${siteName}"?\n\nCommand: ${command}\n\nThis will connect to the Primary IP and execute the command immediately.`)) {
                        e.preventDefault();
                        return false;
                    }
                });
            }
        });

        // Build table row HTML
        function buildTableRow(site) {
            const statusBadgeClass = site.status === 'UP' ? 'success' : 
                                    (site.status === 'DEGRADED' ? 'warning' : 
                                    (site.status === 'DOWN' ? 'danger' : 'secondary'));
            
            const monitoringBadge = site.monitoring_enabled ? 
                '<span class="badge bg-info">Enabled</span>' : 
                '<span class="badge bg-secondary">Disabled</span>';
            
            const monitoringAction = site.monitoring_enabled ? 'stop' : 'start';
            const monitoringIcon = site.monitoring_enabled ? 'pause' : 'play';
            const monitoringText = site.monitoring_enabled ? 'Stop' : 'Start';

            return `
                <tr>
                    <td>
                        <span class="status-dot" style="background: ${site.status_color};"></span>
                        <span class="badge bg-${statusBadgeClass}">${site.status}</span>
                    </td>
                    <td><strong>${site.name}</strong></td>
                    <td>${site.location}</td>
                    <td><code class="bg-light px-2 py-1 rounded">${site.primary_ip}</code></td>
                    <td>
                        <small>
                            <strong>${site.ping_count}</strong> pings per batch<br>
                            <span class="text-muted">Wait ${site.ping_interval_sec}s between batches</span>
                        </small>
                    </td>
                    <td>${monitoringBadge}</td>
                    <td>
                        <div class="action-buttons">
                            <form method="post" action="../actions/action.php" class="d-inline" id="action-form-${site.id}">
                                <input type="hidden" name="site_id" value="${site.id}">
                                <button type="submit" name="action" value="failover" class="btn btn-danger btn-sm" onclick="return confirm('Execute Failover for ${site.name}?');" title="Execute Failover">
                                    <i class="bi bi-exclamation-triangle"></i> <span class="btn-text">Failover</span>
                                </button>
                                <button type="submit" name="action" value="failback" class="btn btn-success btn-sm" onclick="return confirm('Execute Fail Back for ${site.name}?');" title="Execute Fail Back">
                                    <i class="bi bi-arrow-counterclockwise"></i> <span class="btn-text">Fail Back</span>
                                </button>
                                <button type="submit" name="action" value="reboot" class="btn btn-warning btn-sm" onclick="return confirm('Execute Reboot for ${site.name}?');" title="Reboot">
                                    <i class="bi bi-arrow-clockwise"></i> <span class="btn-text">Reboot</span>
                                </button>
                                <button type="submit" name="action" value="${monitoringAction}" class="btn btn-secondary btn-sm" title="${monitoringText} Monitoring">
                                    <i class="bi bi-${monitoringIcon}"></i> <span class="btn-text">${monitoringText}</span>
                                </button>
                            </form>
                            <button type="button" class="btn btn-info btn-sm" onclick="openCustomCommandModal(${site.id}, '${site.name.replace(/'/g, "\\'")}', '${site.primary_ip}')" title="Run Custom Command">
                                <i class="bi bi-terminal"></i> <span class="btn-text">Custom</span>
                            </button>
                            <a href="site_form.php?id=${site.id}" class="btn btn-outline-primary btn-sm" title="Edit Site">
                                <i class="bi bi-pencil"></i> <span class="btn-text">Edit</span>
                            </a>
                            <a href="../actions/delete_site.php?id=${site.id}" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to delete this site?')" title="Delete Site">
                                <i class="bi bi-trash"></i> <span class="btn-text">Delete</span>
                            </a>
                        </div>
                    </td>
                </tr>
            `;
        }

        // Fetch and update recent automatic actions
        async function refreshRecentActions() {
            try {
                const response = await fetch('../api/get_recent_actions.php?limit=10');
                const data = await response.json();
                
                const container = document.getElementById('recentActionsContainer');
                if (!container) return;
                
                if (!data.success) {
                    container.innerHTML = `
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i> 
                            Error loading actions: ${data.error || 'Unknown error'}
                        </div>
                    `;
                    return;
                }
                
                // Debug: Log to console (can be removed later)
                if (data.actions && data.actions.length > 0) {
                    console.log('Recent actions loaded:', data.actions.length, 'actions');
                }
                
                if (data.success && data.actions.length > 0) {
                    container.innerHTML = `
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Time</th>
                                        <th>Action</th>
                                        <th>Site</th>
                                        <th>Status Change</th>
                                        <th>Result</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${data.actions.map(action => `
                                        <tr>
                                            <td>
                                                <div style="white-space: nowrap;">
                                                    <small class="text-muted">${action.created_at_formatted}</small><br>
                                                    <span class="badge bg-secondary">${action.created_at_relative}</span>
                                                </div>
                                            </td>
                                            <td>
                                                ${(() => {
                                                    let badgeClass = 'success';
                                                    let icon = 'arrow-counterclockwise';
                                                    if (action.action_type === 'auto_failover' || action.action_type === 'failover') {
                                                        badgeClass = 'danger';
                                                        icon = 'exclamation-triangle';
                                                    } else if (action.action_type === 'custom') {
                                                        badgeClass = 'info';
                                                        icon = 'terminal';
                                                    }
                                                    return `<span class="badge bg-${badgeClass}">
                                                        <i class="bi bi-${icon}"></i>
                                                        ${action.action_label || (action.action_type === 'auto_failover' ? 'Auto Failover' : (action.action_type === 'auto_failback' ? 'Auto Failback' : action.action_type))}
                                                    </span>
                                                    ${action.is_automatic ? '<br><small class="text-muted"><i class="bi bi-robot"></i> Automatic</small>' : '<br><small class="text-muted"><i class="bi bi-person"></i> ' + (action.initiated_by || 'Manual') + '</small>'}`;
                                                })()}
                                            </td>
                                            <td>
                                                <strong>${action.site_name}</strong><br>
                                                <small class="text-muted">
                                                    <i class="bi bi-geo-alt"></i> ${action.location}<br>
                                                    <i class="bi bi-hdd-network"></i> ${action.primary_ip}
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge bg-${action.status_before === 'UP' ? 'success' : (action.status_before === 'DEGRADED' ? 'warning' : 'danger')}">${action.status_before}</span>
                                                <i class="bi bi-arrow-right mx-1"></i>
                                                <span class="badge bg-${action.status_after === 'UP' ? 'success' : (action.status_after === 'DEGRADED' ? 'warning' : 'danger')}">${action.status_after}</span>
                                            </td>
                                            <td>
                                                ${action.ssh_success 
                                                    ? '<span class="badge bg-success"><i class="bi bi-check-circle"></i> Success</span>'
                                                    : '<span class="badge bg-danger"><i class="bi bi-x-circle"></i> Failed</span>'
                                                }
                                            </td>
                                            <td>
                                                ${action.ssh_output 
                                                    ? `<small class="text-muted" style="max-width: 300px; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${action.ssh_output.replace(/"/g, '&quot;')}">${action.ssh_output}</small>`
                                                    : '<span class="text-muted">—</span>'
                                                }
                                            </td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    `;
                } else {
                    container.innerHTML = `
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-info-circle" style="font-size: 2rem;"></i>
                            <p class="mt-2 mb-0">No actions yet.</p>
                            <small>Failover and failback actions (automatic or manual) will appear here when they occur.</small>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error refreshing recent actions:', error);
            }
        }

        // Fetch and update table data
        async function refreshTable() {
            if (isRefreshing) return;
            
            isRefreshing = true;
            const indicator = document.getElementById('refreshIndicator');
            if (indicator) {
                indicator.classList.add('active');
                indicator.innerHTML = '<div class="spinner-border spinner-border-sm" role="status"></div> <span>Refreshing...</span>';
            }

            try {
                const filters = getFilters();
                const params = new URLSearchParams();
                if (filters.status) params.append('status', filters.status);
                if (filters.search) params.append('search', filters.search);

                const response = await fetch(`../api/get_sites.php?${params.toString()}`);
                const data = await response.json();

                if (data.success) {
                    const tbody = document.getElementById('sitesTableBody');
                    const cardBody = document.querySelector('.card.shadow-lg .card-body');
                    
                    if (data.sites.length === 0) {
                        // No sites - show empty state
                        stopAutoRefresh();
                        const indicator = document.getElementById('refreshIndicator');
                        if (indicator) {
                            indicator.style.display = 'none';
                        }
                        if (cardBody) {
                            cardBody.innerHTML = `
                                <div class="empty-state">
                                    <i class="bi bi-router"></i>
                                    <h3>No sites found</h3>
                                    <p class="text-muted">Add your first site to start monitoring</p>
                                    <a href="site_form.php" class="btn btn-primary mt-3">
                                        <i class="bi bi-plus-circle"></i> Add New Site
                                    </a>
                                </div>
                            `;
                        }
                    } else {
                        // Has sites - show table
                        if (cardBody && !tbody) {
                            // Table doesn't exist, create it
                            cardBody.innerHTML = `
                                <div class="refresh-indicator" id="refreshIndicator">
                                    <i class="bi bi-arrow-clockwise"></i>
                                    <span>Auto-refreshing every <strong>5 seconds</strong></span>
                                    <span class="text-muted">|</span>
                                    <span class="refresh-counter" id="refreshCounter">0</span> <span class="text-muted">refreshes</span>
                                    <span class="text-muted">|</span>
                                    <span id="lastUpdate" class="text-muted">Last updated: <span id="lastUpdateTime">Just now</span></span>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Status</th>
                                                <th>Site Name</th>
                                                <th>Location</th>
                                                <th>Primary IP</th>
                                                <th>Ping Settings</th>
                                                <th>Monitoring</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="sitesTableBody"></tbody>
                                    </table>
                                </div>
                            `;
                            // Restart auto-refresh since table was created
                            startAutoRefresh();
                        }
                        
                        const updatedTbody = document.getElementById('sitesTableBody');
                        const updatedIndicator = document.getElementById('refreshIndicator');
                        if (updatedTbody) {
                            updatedTbody.innerHTML = data.sites.map(buildTableRow).join('');
                            if (updatedIndicator) {
                                updatedIndicator.style.display = 'flex';
                            }
                        }
                    }
                    updateLastUpdateTime();
                }
                
                // Also refresh recent actions
                refreshRecentActions();
            } catch (error) {
                console.error('Error refreshing table:', error);
            } finally {
                isRefreshing = false;
                if (indicator) {
                    indicator.classList.remove('active');
                    const timeStr = new Date().toLocaleTimeString();
                    indicator.innerHTML = `<i class="bi bi-arrow-clockwise"></i> <span>Auto-refreshing every <strong>5 seconds</strong></span> <span class="text-muted">|</span> <span class="refresh-counter" id="refreshCounter">${refreshCount}</span> <span class="text-muted">refreshes</span> <span class="text-muted">|</span> <span id="lastUpdate" class="text-muted">Last updated: <span id="lastUpdateTime">${timeStr}</span></span>`;
                }
            }
        }

        // Start auto-refresh
        function startAutoRefresh() {
            if (refreshTimer) clearInterval(refreshTimer);
            refreshTimer = setInterval(refreshTable, REFRESH_INTERVAL);
        }

        // Stop auto-refresh
        function stopAutoRefresh() {
            if (refreshTimer) {
                clearInterval(refreshTimer);
                refreshTimer = null;
            }
        }

        // Separate timer for actions refresh (runs independently)
        let actionsRefreshTimer = null;
        
        function startActionsAutoRefresh() {
            if (actionsRefreshTimer) clearInterval(actionsRefreshTimer);
            // Refresh actions every 5 seconds independently
            actionsRefreshTimer = setInterval(refreshRecentActions, REFRESH_INTERVAL);
        }
        
        function stopActionsAutoRefresh() {
            if (actionsRefreshTimer) {
                clearInterval(actionsRefreshTimer);
                actionsRefreshTimer = null;
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Show refresh indicator if table exists
            const tbody = document.getElementById('sitesTableBody');
            const indicator = document.getElementById('refreshIndicator');
            if (tbody && indicator) {
                indicator.style.display = 'flex';
            }
            
            // Load recent actions immediately
            refreshRecentActions();
            
            // Start actions auto-refresh (always runs, independent of sites table)
            startActionsAutoRefresh();
            
            // Start auto-refresh only if table exists
            if (tbody) {
                startAutoRefresh();
            }
            
            // Refresh when page becomes visible (user switches back to tab)
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    refreshRecentActions(); // Always refresh actions
                    if (tbody) {
                        refreshTable();
                    }
                }
            });

            // Pause auto-refresh when user is interacting with forms
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function() {
                    stopAutoRefresh();
                });
            });
        });

        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            stopAutoRefresh();
            stopActionsAutoRefresh();
        });
    </script>
</body>
</html>

