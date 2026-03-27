<?php
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
require_once __DIR__ . '/../includes/functions.php';

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_defaults'])) {
    $new_count = (int)($_POST['default_ping_count'] ?? PING_DEFAULT_COUNT);
    $new_interval = (int)($_POST['default_ping_interval'] ?? PING_DEFAULT_INTERVAL_SEC);
    
    if ($new_count < 1 || $new_count > 100) {
        $error = 'Ping count must be between 1 and 100';
    } elseif ($new_interval < 1 || $new_interval > 60) {
        $error = 'Ping interval must be between 1 and 60 seconds';
    } else {
        // Update config file
        $configFile = __DIR__ . '/config.php';
        $config = file_get_contents($configFile);
        
        $config = preg_replace(
            "/define\('PING_DEFAULT_COUNT',\s*\d+\);/",
            "define('PING_DEFAULT_COUNT', $new_count);",
            $config
        );
        
        $config = preg_replace(
            "/define\('PING_DEFAULT_INTERVAL_SEC',\s*\d+\);/",
            "define('PING_DEFAULT_INTERVAL_SEC', $new_interval);",
            $config
        );
        
        if (file_put_contents($configFile, $config)) {
            $message = 'Default settings updated successfully! Note: You may need to restart the monitor.php script for changes to take effect.';
        } else {
            $error = 'Failed to update config file. Please check file permissions.';
        }
    }
}

// Reload config to get current values
require_once __DIR__ . '/../includes/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Firewall Monitor</title>
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
        .form-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .form-control {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 14px;
        }
        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .btn {
            border-radius: 8px;
            font-weight: 500;
            padding: 10px 20px;
        }
        h1.h3, h2.h5 {
            font-weight: 600;
            color: #111827;
        }
        .alert {
            border-radius: 8px;
            border: none;
        }
        .card.bg-light {
            background-color: #f9fafb !important;
            border: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
    <div class="container-fluid px-4">
        <div class="card shadow-lg mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                    <h1 class="h3 mb-0">
                        <i class="bi bi-gear text-primary"></i> System Settings
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

        <div class="card shadow-lg">
            <div class="card-body p-4">
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle"></i> <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <h2 class="h5 mb-4">
                    <i class="bi bi-sliders"></i> Default Ping Settings
                </h2>

                <p class="text-muted mb-4">
                    These are the default values used when creating new sites or when a site doesn't have custom ping settings configured.
                </p>

                <form method="post">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="default_ping_count" class="form-label fw-bold">Default Ping Count</label>
                            <input type="number" class="form-control" id="default_ping_count" name="default_ping_count" 
                                   value="<?= PING_DEFAULT_COUNT ?>" min="1" max="100" required>
                            <small class="text-muted">
                                Default number of ping attempts per monitoring check (1-100)
                            </small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="default_ping_interval" class="form-label fw-bold">Default Ping Interval (seconds)</label>
                            <input type="number" class="form-control" id="default_ping_interval" name="default_ping_interval" 
                                   value="<?= PING_DEFAULT_INTERVAL_SEC ?>" min="1" max="60" required>
                            <small class="text-muted">
                                Default seconds between each ping attempt (1-60)
                            </small>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> <strong>Current Defaults:</strong><br>
                        • Ping Count: <strong><?= PING_DEFAULT_COUNT ?></strong> pings per check<br>
                        • Ping Interval: <strong><?= PING_DEFAULT_INTERVAL_SEC ?></strong> seconds between pings<br>
                        <br>
                        <strong>Example:</strong> With these settings, each monitoring check will ping <?= PING_DEFAULT_COUNT ?> times, 
                        waiting <?= PING_DEFAULT_INTERVAL_SEC ?> seconds between each ping. 
                        Total time: approximately <?= (PING_DEFAULT_COUNT - 1) * PING_DEFAULT_INTERVAL_SEC ?> seconds.
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" name="update_defaults" class="btn btn-primary">
                            <i class="bi bi-save"></i> Save Default Settings
                        </button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>

                <hr class="my-5">

                <h2 class="h5 mb-4">
                    <i class="bi bi-info-circle"></i> How Ping Settings Work
                </h2>

                <div class="card bg-light">
                    <div class="card-body">
                        <h6 class="fw-bold">Global Defaults (This Page)</h6>
                        <p>These are the default values used system-wide. They apply to:</p>
                        <ul>
                            <li>New sites when created (if not specified)</li>
                            <li>Existing sites that don't have custom ping settings</li>
                        </ul>

                        <h6 class="fw-bold mt-4">Per-Site Settings</h6>
                        <p>Each site can have its own custom ping settings:</p>
                        <ul>
                            <li>When editing a site, you can set custom <strong>Ping Count</strong> and <strong>Ping Interval</strong></li>
                            <li>If left empty, the site will use the global defaults</li>
                            <li>This allows different sites to have different monitoring sensitivity</li>
                        </ul>

                        <h6 class="fw-bold mt-4">Example Scenarios</h6>
                        <ul>
                            <li><strong>Fast Check:</strong> 5 pings, 1 second interval = ~4 seconds total</li>
                            <li><strong>Standard:</strong> 10 pings, 5 second interval = ~45 seconds total</li>
                            <li><strong>Thorough:</strong> 20 pings, 5 second interval = ~95 seconds total</li>
                            <li><strong>Very Thorough:</strong> 50 pings, 5 second interval = ~245 seconds total</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

