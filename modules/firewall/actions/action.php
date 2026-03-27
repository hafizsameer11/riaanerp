<?php
// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display, but log
ini_set('log_errors', 1);

require_once __DIR__ . '/../includes/auth.php';
requireAuth();
require_once __DIR__ . '/../includes/functions.php';

$siteId = $_POST['site_id'] ?? null;
$action = $_POST['action'] ?? null;

// Debug: Check if we're receiving the data
if (!$siteId || !$action) {
    $_SESSION['error'] = "Missing site_id or action. Received: site_id=" . ($siteId ?? 'null') . ", action=" . ($action ?? 'null');
    header("Location: ../pages/index.php");
    exit;
}

$stmt = db()->prepare("SELECT * FROM sites WHERE id=?");
$stmt->execute([$siteId]);
$site = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$site) {
    $_SESSION['error'] = "Site not found with ID: " . $siteId;
    header("Location: ../pages/index.php");
    exit;
}

$before = $site['status'];
$after = $before;
$output = "";
$sshSuccess = null;
$commandExecuted = null;

$sshUser = $site['ssh_username'];
$sshPass = dec($site['ssh_password_enc']);

if ($action === "custom") {
    // Custom command - use PRIMARY IP
    $customCommand = $_POST['custom_command'] ?? '';
    if (empty($customCommand)) {
        $_SESSION['error'] = "Custom command is required.";
        header("Location: ../pages/index.php");
        exit;
    }
    
    // Sanitize and clean the command
    // Remove null bytes and other dangerous control characters
    $customCommand = str_replace(["\0", "\x00"], '', $customCommand);
    
    // Normalize line endings (Windows to Unix)
    $customCommand = str_replace(["\r\n", "\r"], "\n", $customCommand);
    
    // Trim whitespace from start and end only (preserve internal structure)
    $customCommand = trim($customCommand);
    
    // If command is empty after cleaning, reject it
    if (empty($customCommand)) {
        $_SESSION['error'] = "Custom command is invalid or empty after sanitization.";
        header("Location: ../pages/index.php");
        exit;
    }
    
    // Clean each line but preserve structure
    // Split by newlines and clean each line individually
    $lines = explode("\n", $customCommand);
    $cleanedLines = [];
    foreach ($lines as $line) {
        // Remove control characters except newline and tab (for scripts)
        $line = preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/', '', $line);
        // Remove carriage returns (shouldn't be any after normalization, but just in case)
        $line = str_replace("\r", '', $line);
        // Trim trailing whitespace only (preserve leading spaces for indentation)
        $line = rtrim($line);
        // Keep the line (even if empty, as it might be intentional in scripts)
        $cleanedLines[] = $line;
    }
    
    // Rejoin with newlines (preserve empty lines)
    $customCommand = implode("\n", $cleanedLines);
    
    // Final trim
    $customCommand = trim($customCommand);
    
    // If still empty, reject
    if (empty($customCommand)) {
        $_SESSION['error'] = "Custom command is invalid after sanitization.";
        header("Location: ../pages/index.php");
        exit;
    }
    
    $targetIp = $site['primary_ip']; // Use PRIMARY IP for custom commands
    $commandExecuted = $customCommand; // Store cleaned command
    
    // Log the command being executed (for debugging)
    error_log("Firewall Monitor: Executing custom command on $targetIp: " . substr($customCommand, 0, 100));
    
    $res = runSshCommand($targetIp, $sshUser, $sshPass, $customCommand);
    $output = $res['output'];
    $sshSuccess = $res['ok'] ? 1 : 0;
    
    if ($res['ok']) {
        $output = "✅ Custom Command Executed Successfully\n" . $output;
    } else {
        // Check if error is about phpseclib not being installed
        if (strpos($output, 'phpseclib not found') !== false) {
            $output = "❌ Custom Command Failed - phpseclib not installed\n" . 
                      "Please install phpseclib: composer require phpseclib/phpseclib\n" . 
                      "Original error: " . $output;
        } else {
            // Provide more detailed error information
            $errorDetails = "❌ Custom Command Failed\n";
            $errorDetails .= "Command: " . htmlspecialchars(substr($customCommand, 0, 200)) . "\n";
            $errorDetails .= "Error: " . $output;
            $output = $errorDetails;
        }
    }
    // Don't change status for custom commands
} elseif ($action === "failover") {
    $targetIp = $site['secondary_ip'];
    $cmd = $site['failover_command'] ?? '';
    if (empty($cmd)) {
        $_SESSION['error'] = "Failover command not configured for site: " . $site['name'];
        header("Location: ../pages/index.php");
        exit;
    }
    $commandExecuted = $cmd;
    $res = runSshCommand($targetIp, $sshUser, $sshPass, $cmd);
    $output = $res['output'];
    $sshSuccess = $res['ok'] ? 1 : 0;
    if ($res['ok']) {
        $after = "DOWN";
        $output = "✅ SSH Command Executed Successfully\n" . $output;
    } else {
        // Check if error is about phpseclib not being installed
        if (strpos($output, 'phpseclib not found') !== false) {
            $output = "❌ SSH Command Failed - phpseclib not installed\n" . 
                      "Please install phpseclib: composer require phpseclib/phpseclib\n" . 
                      "Original error: " . $output;
        } else {
            $output = "❌ SSH Command Failed\n" . $output;
        }
    }
} elseif ($action === "failback") {
    $targetIp = $site['secondary_ip'];
    $cmd = $site['failback_command'] ?? '';
    if (empty($cmd)) {
        $_SESSION['error'] = "Failback command not configured for site: " . $site['name'];
        header("Location: ../pages/index.php");
        exit;
    }
    $commandExecuted = $cmd;
    $res = runSshCommand($targetIp, $sshUser, $sshPass, $cmd);
    $output = $res['output'];
    $sshSuccess = $res['ok'] ? 1 : 0;
    if ($res['ok']) {
        $after = "UP";
        $output = "✅ SSH Command Executed Successfully\n" . $output;
    } else {
        // Check if error is about phpseclib not being installed
        if (strpos($output, 'phpseclib not found') !== false) {
            $output = "❌ SSH Command Failed - phpseclib not installed\n" . 
                      "Please install phpseclib: composer require phpseclib/phpseclib\n" . 
                      "Original error: " . $output;
        } else {
            $output = "❌ SSH Command Failed\n" . $output;
        }
    }
} elseif ($action === "reboot") {
        $targetIp = $site['secondary_ip'];
    $cmd = $site['reboot_command'] ?? '';
    if (empty($cmd)) {
        $_SESSION['error'] = "Reboot command not configured for site: " . $site['name'];
        header("Location: ../pages/index.php");
        exit;
    }
    $commandExecuted = $cmd;
    $res = runSshCommand($targetIp, $sshUser, $sshPass, $cmd);
    $output = $res['output'];
    $sshSuccess = $res['ok'] ? 1 : 0;
    if ($res['ok']) {
        $output = "✅ SSH Command Executed Successfully\n" . $output;
    } else {
        // Check if error is about phpseclib not being installed
        if (strpos($output, 'phpseclib not found') !== false) {
            $output = "❌ SSH Command Failed - phpseclib not installed\n" . 
                      "Please install phpseclib: composer require phpseclib/phpseclib\n" . 
                      "Original error: " . $output;
        } else {
            $output = "❌ SSH Command Failed\n" . $output;
        }
    }
} elseif ($action === "stop") {
    $stmt = db()->prepare("UPDATE sites SET monitoring_enabled=0 WHERE id=?");
    $stmt->execute([$siteId]);
    $output = "Monitoring stopped for site: " . $site['name'];
} elseif ($action === "start") {
    $stmt = db()->prepare("UPDATE sites SET monitoring_enabled=1 WHERE id=?");
    $stmt->execute([$siteId]);
    $output = "Monitoring started for site: " . $site['name'];
}

if ($after !== $before) {
    $stmt = db()->prepare("UPDATE sites SET status=? WHERE id=?");
    $stmt->execute([$after, $siteId]);
}

logAction($siteId, $action, "user", $before, $after, $output, $sshSuccess, $commandExecuted);

sendNotificationEmail(getEmails(), "Manual Action: $action - " . $site['name'], $output);

// Set success message
if ($sshSuccess === 1) {
    $_SESSION['success'] = "Action '$action' executed successfully for " . $site['name'];
} elseif ($sshSuccess === 0) {
    $_SESSION['error'] = "Action '$action' failed for " . $site['name'] . ": " . substr($output, 0, 100);
} else {
    $_SESSION['success'] = "Action '$action' completed for " . $site['name'];
}

header("Location: ../pages/index.php");
exit;

