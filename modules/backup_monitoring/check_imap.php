<?php
/**
 * IMAP Extension Checker
 * 
 * This file checks if the IMAP extension is enabled on the server
 * and provides detailed diagnostic information.
 * 
 * Access via: http://your-domain.com/modules/backup_monitoring/check_imap.php
 */

header('Content-Type: text/html; charset=utf-8');

$pageTitle = "IMAP Extension Checker";
$isCliMode = php_sapi_name() === 'cli';

function outputResult($status, $message, $details = '') {
    global $isCliMode;
    
    if ($isCliMode) {
        $statusIcon = $status ? "✓" : "✗";
        echo "\n[" . ($status ? "YES" : "NO") . "] " . $statusIcon . " " . $message;
        if ($details) {
            echo "\n    Details: " . $details;
        }
    } else {
        $badgeClass = $status ? "badge-success" : "badge-danger";
        $icon = $status ? "check-circle" : "times-circle";
        echo "<div class='result-item'>";
        echo "<i class='fas fa-$icon'></i>";
        echo "<span class='$badgeClass'>" . ($status ? "YES" : "NO") . "</span>";
        echo "<strong>$message</strong>";
        if ($details) {
            echo "<div class='details'>$details</div>";
        }
        echo "</div>";
    }
}

$imapAvailable = extension_loaded('imap');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
            font-family: 'Inter', sans-serif;
        }
        .container {
            max-width: 900px;
            margin-top: 30px;
        }
        .checker-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
            margin-bottom: 30px;
        }
        .page-header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }
        .page-header h1 {
            font-size: 2.5em;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .page-header p {
            font-size: 1.1em;
            opacity: 0.9;
        }
        .section-title {
            color: #667eea;
            font-weight: 600;
            margin-top: 30px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        .result-item {
            display: flex;
            align-items: center;
            padding: 15px;
            margin-bottom: 15px;
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            border-radius: 5px;
        }
        .result-item i {
            font-size: 1.5em;
            margin-right: 15px;
            min-width: 30px;
        }
        .result-item .badge {
            margin-right: 15px;
            padding: 8px 12px;
            font-weight: 600;
            min-width: 50px;
            text-align: center;
        }
        .badge-success {
            background-color: #28a745;
            color: white;
        }
        .badge-danger {
            background-color: #dc3545;
            color: white;
        }
        .badge-warning {
            background-color: #ffc107;
            color: #333;
        }
        .badge-info {
            background-color: #17a2b8;
            color: white;
        }
        .details {
            margin-top: 10px;
            padding: 10px;
            background: white;
            border-left: 2px solid #ffc107;
            font-size: 0.9em;
            color: #555;
        }
        .code-block {
            background: #f5f5f5;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            overflow-x: auto;
        }
        .actions {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px solid #e0e0e0;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .action-btn {
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
        }
        .btn-primary-custom {
            background: #667eea;
            color: white;
        }
        .btn-primary-custom:hover {
            background: #764ba2;
            color: white;
            text-decoration: none;
        }
        .btn-secondary-custom {
            background: #e0e0e0;
            color: #333;
        }
        .btn-secondary-custom:hover {
            background: #d0d0d0;
            color: #333;
            text-decoration: none;
        }
        .success-banner {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .error-banner {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .warning-banner {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .info-banner {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .footer {
            text-align: center;
            color: white;
            margin-top: 40px;
            opacity: 0.8;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        th {
            background: #667eea;
            color: white;
            font-weight: 600;
        }
        tr:hover {
            background: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-envelope"></i> IMAP Extension Checker</h1>
            <p>Server Diagnostic Tool</p>
        </div>

        <div class="checker-card">
            <?php
            if ($imapAvailable) {
                echo '<div class="success-banner">';
                echo '<i class="fas fa-check-circle"></i> <strong>GREAT NEWS!</strong> IMAP extension is installed and enabled on this server.';
                echo '</div>';
            } else {
                echo '<div class="error-banner">';
                echo '<i class="fas fa-times-circle"></i> <strong>IMAP NOT AVAILABLE</strong> The IMAP extension is not installed or enabled on this server.';
                echo '</div>';
            }
            ?>

            <h2 class="section-title"><i class="fas fa-terminal"></i> Extension Check</h2>
            <?php
            $imapLoaded = extension_loaded('imap');
            outputResult($imapLoaded, 'IMAP Extension Loaded', 
                $imapLoaded ? 'The imap extension is available' : 'The imap extension is NOT loaded');

            $imapFunctionsAvailable = [
                'imap_open' => function_exists('imap_open'),
                'imap_close' => function_exists('imap_close'),
                'imap_search' => function_exists('imap_search'),
                'imap_fetch_overview' => function_exists('imap_fetch_overview'),
                'imap_body' => function_exists('imap_body'),
            ];
            
            $allFunctionsAvailable = array_reduce($imapFunctionsAvailable, function($carry, $item) {
                return $carry && $item;
            }, true);
            
            outputResult($allFunctionsAvailable, 'IMAP Functions Available', 
                'Key IMAP functions are available');
            ?>

            <h2 class="section-title"><i class="fas fa-info-circle"></i> Server Information</h2>
            <div class="result-item">
                <i class="fas fa-server"></i>
                <span class="badge badge-info">INFO</span>
                <div>
                    <strong>PHP Version:</strong> <?php echo phpversion(); ?>
                </div>
            </div>
            <div class="result-item">
                <i class="fas fa-server"></i>
                <span class="badge badge-info">INFO</span>
                <div>
                    <strong>Web Server:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?>
                </div>
            </div>
            <div class="result-item">
                <i class="fas fa-server"></i>
                <span class="badge badge-info">INFO</span>
                <div>
                    <strong>Operating System:</strong> <?php echo php_uname(); ?>
                </div>
            </div>

            <h2 class="section-title"><i class="fas fa-list"></i> IMAP Function Status</h2>
            <table>
                <thead>
                    <tr>
                        <th>Function Name</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $imapFunctions = [
                        'imap_open' => 'Open IMAP connection',
                        'imap_close' => 'Close IMAP connection',
                        'imap_search' => 'Search emails',
                        'imap_fetch_overview' => 'Fetch email headers',
                        'imap_body' => 'Fetch email body',
                        'imap_fetchstructure' => 'Fetch email structure',
                        'imap_num_msg' => 'Get message count',
                        'imap_header' => 'Get email header',
                        'imap_last_error' => 'Get last error',
                        'imap_errors' => 'Get all errors',
                    ];
                    
                    foreach ($imapFunctions as $func => $desc) {
                        $available = function_exists($func);
                        $status = $available ? '<span class="badge badge-success">Available</span>' : '<span class="badge badge-danger">Not Available</span>';
                        echo "<tr>";
                        echo "<td><strong>$func</strong><br><small>$desc</small></td>";
                        echo "<td>$status</td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>

            <h2 class="section-title"><i class="fas fa-cube"></i> All Loaded Extensions</h2>
            <div class="result-item">
                <i class="fas fa-cube"></i>
                <span class="badge badge-info">INFO</span>
                <div>
                    <strong>Total Extensions Loaded:</strong> <?php echo count(get_loaded_extensions()); ?>
                </div>
            </div>
            <?php
            $extensions = get_loaded_extensions();
            sort($extensions);
            ?>
            <div class="code-block">
                <?php echo htmlspecialchars(implode(', ', $extensions)); ?>
            </div>

            <h2 class="section-title"><i class="fas fa-tools"></i> Troubleshooting</h2>
            
            <?php if (!$imapAvailable): ?>
            <div class="error-banner">
                <h5><i class="fas fa-exclamation-triangle"></i> IMAP Extension is NOT Available</h5>
                <p>To enable IMAP on your server:</p>
            </div>
            
            <div class="result-item">
                <i class="fas fa-windows"></i>
                <span class="badge badge-warning">Windows/XAMPP</span>
                <div>
                    <strong>Steps to Enable IMAP:</strong>
                    <div class="code-block">
1. Open php.ini file (usually in C:\xampp\php\php.ini)<br>
2. Find the line: ;extension=imap<br>
3. Remove the semicolon to make it: extension=imap<br>
4. Save the file<br>
5. Restart Apache in XAMPP Control Panel<br>
6. Refresh this page to verify
                    </div>
                </div>
            </div>

            <div class="result-item">
                <i class="fas fa-linux"></i>
                <span class="badge badge-warning">Linux/Shared Hosting</span>
                <div>
                    <strong>Steps to Enable IMAP:</strong>
                    <div class="code-block">
1. Contact your hosting provider<br>
2. Request them to enable the php-imap extension<br>
3. Ask them to enable: extension=imap in php.ini<br>
4. Request them to restart the web server<br>
5. Refresh this page to verify after 5-10 minutes
                    </div>
                </div>
            </div>

            <div class="result-item">
                <i class="fas fa-cloud"></i>
                <span class="badge badge-warning">Cloud/Managed Server</span>
                <div>
                    <strong>Steps to Enable IMAP:</strong>
                    <div class="code-block">
1. Log in to your hosting control panel (cPanel, Plesk, etc.)<br>
2. Go to PHP Configuration or Extensions section<br>
3. Look for IMAP or php-imap extension<br>
4. Enable it<br>
5. Reload/Restart PHP if prompted<br>
6. Refresh this page to verify
                    </div>
                </div>
            </div>

            <?php else: ?>
            <div class="success-banner">
                <h5><i class="fas fa-check-circle"></i> IMAP Extension is Available</h5>
                <p>Your server has IMAP enabled! Email parsing should work correctly.</p>
                <p>If you're still having issues, check:</p>
                <ul style="margin-bottom: 0; padding-left: 20px;">
                    <li>Email is configured in database (backup_mails table)</li>
                    <li>Email address is correct</li>
                    <li>App password is valid (for Gmail, use 16-character app password)</li>
                    <li>IMAP host is correct for the email provider</li>
                </ul>
            </div>
            <?php endif; ?>

            <div class="actions">
                <h3 class="section-title"><i class="fas fa-cogs"></i> Actions</h3>
                <div class="action-buttons">
                    <a href="javascript:location.reload();" class="action-btn btn-primary-custom">
                        <i class="fas fa-sync"></i> Refresh Check
                    </a>
                    <a href="manage_mails.php" class="action-btn btn-primary-custom">
                        <i class="fas fa-envelope"></i> Configure Email
                    </a>
                    <a href="index.php" class="action-btn btn-secondary-custom">
                        <i class="fas fa-arrow-left"></i> Back to Backup Monitoring
                    </a>
                </div>
            </div>
        </div>

        <div class="footer">
            <p><i class="fas fa-info-circle"></i> Last checked: <?php echo date('Y-m-d H:i:s'); ?></p>
            <p>SAU Technologies - Backup Monitoring System</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
