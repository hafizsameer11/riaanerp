<?php
/**
 * Web Wrapper for Email Parsers
 * 
 * This script allows running email parsers via web browser.
 * It ensures PHP 7.4 is used for execution (required for IMAP extension).
 */

session_start();
include_once '../../config.php';
include_once '../components/permissioncheck.php';

// Check if user has access to backup monitoring
if (!hasPermission('billing', 'backup_monitoring')) {
    http_response_code(403);
    die('Access denied');
}

$parser = strtolower($_GET['parser'] ?? 'all');

// Map parser names to script files
$scriptMap = [
    'client' => 'read_client_emails.php',
    'veeam' => 'read_veeam_emails.php',
    'veeamcloud' => 'read_veeamcloud_emails.php',
    'nas' => 'read_nas_emails.php',
    'all' => 'run_all_parsers.php'
];

if (!isset($scriptMap[$parser])) {
    die('Invalid parser: ' . htmlspecialchars($parser));
}

$scriptPath = __DIR__ . '/cron/' . $scriptMap[$parser];

if (!file_exists($scriptPath)) {
    die('Parser script not found: ' . htmlspecialchars($scriptMap[$parser]));
}

// Use PHP 7.4 for execution
$phpPath = '/usr/bin/php7.4';
if (!file_exists($phpPath)) {
    // Fallback to php7.4 command
    $phpPath = 'php7.4';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Run Email Parser - <?php echo htmlspecialchars(ucfirst($parser)); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }
        .output-container {
            background: #000;
            color: #0f0;
            padding: 20px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            max-height: 600px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4><i class="fas fa-play me-2"></i>Running Email Parser: <?php echo htmlspecialchars(ucfirst($parser)); ?></h4>
            </div>
            <div class="card-body">
                <p><strong>Script:</strong> <?php echo htmlspecialchars($scriptMap[$parser]); ?></p>
                <p><strong>PHP Path:</strong> <?php echo htmlspecialchars($phpPath); ?></p>
                <hr>
                <h5>Output:</h5>
                <div class="output-container">
<?php
// Execute the parser script
$command = escapeshellarg($phpPath) . ' ' . escapeshellarg($scriptPath) . ' 2>&1';
$output = [];
$returnVar = 0;

exec($command, $output, $returnVar);

// Display output
foreach ($output as $line) {
    echo htmlspecialchars($line) . "\n";
}

if ($returnVar !== 0) {
    echo "\n\n<strong style='color: #f00;'>Error: Script exited with code $returnVar</strong>\n";
} else {
    echo "\n\n<strong style='color: #0f0;'>✓ Script completed successfully</strong>\n";
}
?>
                </div>
                <hr>
                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left me-1"></i>Back to Monitoring
                </a>
            </div>
        </div>
    </div>
</body>
</html>
