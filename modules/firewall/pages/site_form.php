<?php
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
require_once __DIR__ . '/../includes/functions.php';

$id = $_GET['id'] ?? null;
$site = null;
$sshPasswordDecrypted = '';
$loginPasswordDecrypted = '';

if ($id) {
    $stmt = db()->prepare("SELECT * FROM sites WHERE id=?");
    $stmt->execute([$id]);
    $site = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Decrypt passwords for display when editing
    if ($site) {
        if (!empty($site['ssh_password_enc'])) {
            $sshPasswordDecrypted = dec($site['ssh_password_enc']) ?? '';
        }
        if (!empty($site['login_password_enc'])) {
            $loginPasswordDecrypted = dec($site['login_password_enc']) ?? '';
        }
    }
}

function val($f){ global $site; return $site[$f] ?? ''; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=$id?'Edit':'Add'?> Site - Firewall Monitor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #64748b;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --light: #f8fafc;
            --dark: #1e293b;
            --border: #e2e8f0;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            background: #f4f7fa;
            min-height: 100vh;
            padding: 30px 20px;
            font-family: 'Inter', sans-serif;
        }
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header-card {
            background: white;
            border: none;
            border-radius: 16px;
            box-shadow: var(--shadow-lg);
            margin-bottom: 24px;
            padding: 20px 28px;
        }
        .form-card {
            background: white;
            border: none;
            border-radius: 16px;
            box-shadow: var(--shadow-lg);
            padding: 32px;
        }
        .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 24px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border);
        }
        .section-header i {
            color: var(--primary);
            font-size: 20px;
        }
        .section-divider {
            margin: 32px 0;
            padding-top: 32px;
            border-top: 1px solid var(--border);
        }
        .form-label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 8px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-label .text-danger {
            color: var(--danger) !important;
            font-weight: 700;
        }
        .form-control, .form-select {
            border: 2px solid var(--border);
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #fff;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
            outline: none;
        }
        .form-control::placeholder {
            color: #94a3b8;
            font-size: 13px;
        }
        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }
        .form-check-input {
            width: 20px;
            height: 20px;
            border: 2px solid var(--border);
            cursor: pointer;
        }
        .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        .form-check-label {
            font-weight: 500;
            color: var(--dark);
            margin-left: 8px;
            cursor: pointer;
        }
        .btn {
            border-radius: 10px;
            font-weight: 600;
            padding: 12px 24px;
            font-size: 14px;
            transition: all 0.3s ease;
            border: none;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(99, 102, 241, 0.5);
        }
        .btn-secondary {
            background: var(--secondary);
        }
        .btn-secondary:hover {
            background: #475569;
            transform: translateY(-2px);
        }
        .btn-outline-secondary, .btn-outline-danger {
            border-width: 2px;
            font-weight: 500;
        }
        .btn-outline-secondary:hover {
            background: var(--secondary);
            border-color: var(--secondary);
            color: white;
        }
        .btn-outline-danger:hover {
            background: var(--danger);
            border-color: var(--danger);
            color: white;
        }
        h1.h3 {
            font-weight: 700;
            color: var(--dark);
            font-size: 26px;
            margin: 0;
        }
        .alert {
            border-radius: 12px;
            border: none;
            padding: 16px 20px;
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            border-left: 4px solid var(--info);
        }
        .alert i {
            color: var(--info);
            margin-right: 8px;
        }
        .help-text {
            font-size: 12px;
            color: #64748b;
            margin-top: 6px;
            line-height: 1.5;
        }
        .help-text strong {
            color: var(--dark);
        }
        .form-actions {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 2px solid var(--border);
        }
        .row {
            margin-left: -12px;
            margin-right: -12px;
        }
        .row > [class*="col-"] {
            padding-left: 12px;
            padding-right: 12px;
        }
        .mb-3 {
            margin-bottom: 20px !important;
        }
        .password-wrapper {
            position: relative;
        }
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--secondary);
            cursor: pointer;
            padding: 4px 8px;
            font-size: 18px;
            z-index: 10;
            transition: color 0.2s ease;
        }
        .password-toggle:hover {
            color: var(--primary);
        }
        .password-toggle:focus {
            outline: none;
            color: var(--primary);
        }
        .password-wrapper .form-control {
            padding-right: 45px;
        }
        @media (max-width: 768px) {
            body {
                padding: 15px;
            }
            .form-card {
                padding: 20px;
            }
            .section-divider {
                margin: 24px 0;
                padding-top: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="header-card">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <h1 class="h3">
                    <i class="bi bi-<?=$id?'pencil-square':'plus-circle'?> text-primary"></i> <?=$id?'Edit':'Add'?> Site
                </h1>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="index.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> Back
                    </a>
                    <a href="../actions/logout.php" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </div>
            </div>
        </div>

        <div class="form-card">
            <form method="post" action="../actions/save_site.php">
                <input type="hidden" name="id" value="<?=$id?>">

                <!-- Basic Information -->
                <div class="section-header">
                    <i class="bi bi-info-circle"></i>
                    <span>Basic Information</span>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label">Site Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" value="<?=htmlspecialchars(val('name'))?>" required placeholder="Enter site name">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="location" class="form-label">Location</label>
                        <input type="text" class="form-control" id="location" name="location" value="<?=htmlspecialchars(val('location'))?>" placeholder="e.g., Data Center A">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="2" placeholder="Site description or notes"><?=htmlspecialchars(val('description'))?></textarea>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="primary_ip" class="form-label">Primary IP Address <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="primary_ip" name="primary_ip" value="<?=htmlspecialchars(val('primary_ip'))?>" required placeholder="192.168.1.1">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="secondary_ip" class="form-label">Secondary IP Address <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="secondary_ip" name="secondary_ip" value="<?=htmlspecialchars(val('secondary_ip'))?>" required placeholder="192.168.1.2">
                    </div>
                </div>

                <!-- SSH Configuration -->
                <div class="section-divider">
                    <div class="section-header">
                        <i class="bi bi-terminal"></i>
                        <span>SSH Configuration</span>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="ssh_username" class="form-label">SSH Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="ssh_username" name="ssh_username" value="<?=htmlspecialchars(val('ssh_username'))?>" required placeholder="root">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="ssh_password" class="form-label">SSH Password <?=$id?'<small class="text-muted">(leave blank to keep current)</small>':'<span class="text-danger">*</span>'?></label>
                            <div class="password-wrapper">
                                <input type="password" class="form-control" id="ssh_password" name="ssh_password" value="<?=$id && $sshPasswordDecrypted ? htmlspecialchars($sshPasswordDecrypted) : ''?>" <?=$id?'':'required'?> placeholder="Enter SSH password">
                                <button type="button" class="password-toggle" onclick="togglePassword('ssh_password', this)" aria-label="Toggle password visibility">
                                    <i class="bi bi-eye" id="ssh_password_icon"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Web Login -->
                <div class="section-divider">
                    <div class="section-header">
                        <i class="bi bi-globe"></i>
                        <span>Web Login (Optional)</span>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="login_url" class="form-label">Login URL</label>
                            <input type="url" class="form-control" id="login_url" name="login_url" value="<?=htmlspecialchars(val('login_url'))?>" placeholder="https://example.com/login">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="login_username" class="form-label">Login Username</label>
                            <input type="text" class="form-control" id="login_username" name="login_username" value="<?=htmlspecialchars(val('login_username'))?>" placeholder="Web login username">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="login_password" class="form-label">Login Password <?=$id?'<small class="text-muted">(leave blank to keep current)</small>':''?></label>
                            <div class="password-wrapper">
                                <input type="password" class="form-control" id="login_password" name="login_password" value="<?=$id && $loginPasswordDecrypted ? htmlspecialchars($loginPasswordDecrypted) : ''?>" placeholder="Web login password">
                                <button type="button" class="password-toggle" onclick="togglePassword('login_password', this)" aria-label="Toggle password visibility">
                                    <i class="bi bi-eye" id="login_password_icon"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monitoring Settings -->
                <div class="section-divider">
                    <div class="section-header">
                        <i class="bi bi-activity"></i>
                        <span>Monitoring Settings</span>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="ping_count" class="form-label">Ping Count (per batch) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="ping_count" name="ping_count" value="<?=val('ping_count')?>" min="1" max="100" placeholder="<?=PING_DEFAULT_COUNT?>" required>
                            <small class="text-muted">Number of pings to send in one batch (e.g., 10 = ping 10 times in a row)</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="ping_interval_sec" class="form-label">Wait Between Batches (seconds) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="ping_interval_sec" name="ping_interval_sec" value="<?=val('ping_interval_sec')?>" min="1" max="3600" placeholder="<?=PING_DEFAULT_BATCH_INTERVAL_SEC?>" required>
                            <small class="text-muted">Seconds to wait before next batch of pings (e.g., 60 = wait 60 seconds between batches)</small>
                        </div>
                    </div>
                    <?php 
                    $current_count = val('ping_count') ?: PING_DEFAULT_COUNT;
                    $current_interval = val('ping_interval_sec') ?: PING_DEFAULT_BATCH_INTERVAL_SEC;
                    ?>
                    <div class="alert alert-info mb-3">
                        <i class="bi bi-info-circle"></i> <strong>How It Works:</strong><br>
                        The system will ping <strong><?=$current_count?></strong> times in a row (no delay between pings), then wait <strong><?=$current_interval?> seconds</strong> before the next batch.<br>
                        <strong>Failover Rule:</strong> If ALL <?=$current_count?> pings fail → trigger failover. If at least 1 ping succeeds → wait <?=$current_interval?>s and try again.
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="monitoring_enabled" name="monitoring_enabled" value="1" <?=($site['monitoring_enabled']??1)?'checked':''?>>
                            <label class="form-check-label" for="monitoring_enabled">Enable Monitoring</label>
                        </div>
                    </div>
                </div>

                <!-- SSH Commands -->
                <div class="section-divider">
                    <div class="section-header">
                        <i class="bi bi-code-slash"></i>
                        <span>SSH Commands</span>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="failover_command" class="form-label">Failover Command <span class="text-danger">*</span></label>
                            <textarea class="form-control font-monospace" id="failover_command" name="failover_command" rows="2" required placeholder="Command to execute when primary IP fails"><?=htmlspecialchars(val('failover_command'))?></textarea>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="failback_command" class="form-label">Failback Command <span class="text-danger">*</span></label>
                            <textarea class="form-control font-monospace" id="failback_command" name="failback_command" rows="2" required placeholder="Command to restore primary IP"><?=htmlspecialchars(val('failback_command'))?></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="reboot_command" class="form-label">Reboot Command <span class="text-danger">*</span></label>
                            <textarea class="form-control font-monospace" id="reboot_command" name="reboot_command" rows="2" required placeholder="Command to reboot firewall"><?=htmlspecialchars(val('reboot_command'))?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Auto-Failback Settings -->
                <div class="section-divider">
                    <div class="section-header">
                        <i class="bi bi-arrow-counterclockwise"></i>
                        <span>Automatic Failback</span>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="auto_failback_enabled" name="auto_failback_enabled" value="1" <?=($site['auto_failback_enabled']??0)?'checked':''?>>
                            <label class="form-check-label" for="auto_failback_enabled">
                                <strong>Enable Automatic Failback</strong>
                            </label>
                        </div>
                        <small class="text-muted">After failover, automatically restore primary connection when it recovers</small>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="auto_failback_success_pings" class="form-label">Success Pings Required</label>
                            <input type="number" class="form-control" id="auto_failback_success_pings" name="auto_failback_success_pings" value="<?=val('auto_failback_success_pings')?>" min="1" max="100" placeholder="30">
                            <small class="text-muted">Number of consecutive successful pings to primary IP before triggering failback (e.g., 30)</small>
                        </div>
                    </div>

                    <div class="alert alert-info mb-3">
                        <i class="bi bi-info-circle"></i> <strong>Auto-Failback Process:</strong><br>
                        After failover occurs, the system will continue pinging the primary IP. When the primary recovers and achieves <strong><?=val('auto_failback_success_pings') ?: 30?> consecutive successful pings</strong>, it will automatically execute the failback command via SSH to restore the primary connection.
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Save Site
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Cancel
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(inputId + '_icon');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }
    </script>
</body>
</html>

