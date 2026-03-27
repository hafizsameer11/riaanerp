<?php
/**
 * Diagnostic Script - Check Cron Job Setup
 * 
 * This script checks if everything is configured correctly for email parsing
 * Run this to diagnose issues with the cron job system
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Verify PHP 7.4 is being used (required for IMAP support)
$phpVersion = phpversion();
if (!(version_compare($phpVersion, '7.4.0', '>=') && version_compare($phpVersion, '7.5.0', '<'))) {
    die("ERROR: This script must run with PHP 7.4 (IMAP extension required)\n" .
        "Current PHP version: $phpVersion\n" .
        "PHP 8.2 does not have IMAP extension enabled.\n" .
        "Please run this script with: php7.4 diagnose.php\n");
}

// Verify IMAP extension is available
if (!extension_loaded('imap')) {
    die("ERROR: IMAP extension is not loaded!\n" .
        "PHP version: $phpVersion\n" .
        "IMAP extension is required for email parsing.\n" .
        "Please ensure PHP 7.4 with IMAP extension is installed.\n");
}

echo "========================================\n";
echo "Backup Monitoring - Diagnostic Script\n";
echo "========================================\n\n";

$errors = [];
$warnings = [];
$success = [];

// 1. Check PHP version
echo "1. Checking PHP version...\n";
echo "   PHP Version: $phpVersion\n";
if (version_compare($phpVersion, '7.4.0', '>=') && version_compare($phpVersion, '7.5.0', '<')) {
    $success[] = "PHP version is 7.4 (correct version for IMAP)";
} else {
    $warnings[] = "PHP version should be 7.4 for IMAP support. Current: $phpVersion";
}
echo "\n";

// 2. Check IMAP extension
echo "2. Checking IMAP extension...\n";
if (!function_exists('imap_open')) {
    $errors[] = "IMAP extension is NOT enabled!";
    echo "   ❌ IMAP extension is NOT enabled\n";
    echo "   To enable:\n";
    echo "   1. Open php.ini (usually C:\\xampp\\php\\php.ini)\n";
    echo "   2. Find: ;extension=imap\n";
    echo "   3. Change to: extension=imap\n";
    echo "   4. Restart Apache\n";
} else {
    $success[] = "IMAP extension is enabled";
    echo "   ✓ IMAP extension is enabled\n";
}
echo "\n";

// 3. Check config file
echo "3. Checking config file...\n";
$scriptDir = __DIR__;
$baseDir = dirname(dirname(dirname($scriptDir)));
$configFile = $baseDir . '/config.php';

if (!file_exists($configFile)) {
    $errors[] = "Config file not found: $configFile";
    echo "   ❌ Config file not found\n";
} else {
    $success[] = "Config file exists";
    echo "   ✓ Config file found\n";
    
    // Try to include it
    try {
        require_once $configFile;
        if (isset($conn)) {
            $success[] = "Database connection available";
            echo "   ✓ Database connection available\n";
        } else {
            $warnings[] = "Database connection variable (\$conn) not found in config";
            echo "   ⚠ Database connection variable not found\n";
        }
    } catch (Exception $e) {
        $errors[] = "Error loading config: " . $e->getMessage();
        echo "   ❌ Error loading config: " . $e->getMessage() . "\n";
    }
}
echo "\n";

// 4. Check email parser scripts
echo "4. Checking email parser scripts...\n";
$parsers = [
    'CLIENT' => 'read_client_emails.php',
    'VEEAM' => 'read_veeam_emails.php',
    'VEEAMCLOUD' => 'read_veeamcloud_emails.php',
    'NAS' => 'read_nas_emails.php'
];

foreach ($parsers as $source => $script) {
    $scriptPath = $scriptDir . '/' . $script;
    if (file_exists($scriptPath)) {
        echo "   ✓ $script exists\n";
        $success[] = "$source parser script exists";
    } else {
        $errors[] = "Parser script not found: $script";
        echo "   ❌ $script NOT found\n";
    }
}
echo "\n";

// 5. Check PHP executable
echo "5. Checking PHP executable...\n";
$phpPath = 'php';
if (defined('PHP_BINARY') && PHP_BINARY && file_exists(PHP_BINARY)) {
    $phpPath = PHP_BINARY;
    echo "   ✓ PHP_BINARY: $phpPath\n";
} elseif (file_exists('C:\\xampp\\php\\php.exe')) {
    $phpPath = 'C:\\xampp\\php\\php.exe';
    echo "   ✓ Found PHP at: $phpPath\n";
} elseif (file_exists('C:\\php\\php.exe')) {
    $phpPath = 'C:\\php\\php.exe';
    echo "   ✓ Found PHP at: $phpPath\n";
} else {
    $whereOutput = @shell_exec('where php 2>&1');
    if ($whereOutput && stripos($whereOutput, 'php.exe') !== false) {
        $lines = explode("\n", trim($whereOutput));
        if (!empty($lines[0]) && file_exists(trim($lines[0]))) {
            $phpPath = trim($lines[0]);
            echo "   ✓ Found PHP in PATH: $phpPath\n";
        } else {
            $warnings[] = "Could not find PHP executable automatically";
            echo "   ⚠ Could not find PHP executable automatically\n";
        }
    } else {
        $warnings[] = "Could not find PHP executable";
        echo "   ⚠ Could not find PHP executable\n";
    }
}
$success[] = "PHP executable: $phpPath";
echo "\n";

// 6. Test email connection (if config loaded)
echo "6. Testing email connection...\n";
if (function_exists('imap_open')) {
    // Get email credentials from database (using CLIENT as default)
    $mailConfig = null;
    if (isset($conn)) {
        $mailConfig = $conn->query("SELECT email_address, app_password, imap_host FROM backup_mails WHERE mail_type = 'CLIENT' AND is_active = 1 LIMIT 1")->fetch_assoc();
    }
    
    if (!$mailConfig) {
        $warnings[] = "No active email configuration found in database";
        echo "   ⚠ No active email configuration found\n";
        echo "   Please configure emails in Backup Monitoring -> Manage Backup Mails\n";
    } else {
        $imapHost = $mailConfig['imap_host'] ?? '{imap.gmail.com:993/imap/ssl}INBOX';
        $imapUser = $mailConfig['email_address'];
        $imapPass = $mailConfig['app_password'];
        
        echo "   Connecting to: $imapUser\n";
        $inbox = @imap_open($imapHost, $imapUser, $imapPass);
        
        if (!$inbox) {
            $error = imap_last_error();
            $errors[] = "IMAP connection failed: $error";
            echo "   ❌ Connection failed: $error\n";
        } else {
            $success[] = "Email connection successful";
            echo "   ✓ Connected successfully\n";
            
            // Check for emails
            $unseen = imap_search($inbox, 'UNSEEN');
            $unseenCount = $unseen ? count($unseen) : 0;
            echo "   Unread emails: $unseenCount\n";
            
            // Check for backup emails
            if ($unseen) {
                $backupEmails = 0;
                foreach ($unseen as $emailNo) {
                    $overview = imap_fetch_overview($inbox, $emailNo, 0)[0];
                    $subject = imap_utf8($overview->subject);
                    if (stripos($subject, '[Success]') !== false || 
                        stripos($subject, '[Warning]') !== false || 
                        stripos($subject, '[Failed]') !== false) {
                        $backupEmails++;
                    }
                }
                echo "   Backup emails (unread): $backupEmails\n";
                if ($backupEmails > 0) {
                    $success[] = "Found $backupEmails unread backup email(s)";
                } else {
                    $warnings[] = "No unread backup emails found (but $unseenCount unread emails exist)";
                }
            } else {
                $warnings[] = "No unread emails found";
            }
            
            imap_close($inbox);
        }
    }
} else {
    $warnings[] = "Cannot test email connection - IMAP extension not enabled";
    echo "   ⚠ Cannot test - IMAP extension not enabled\n";
}
echo "\n";

// 7. Check log file permissions
echo "7. Checking log file permissions...\n";
$logFile = $scriptDir . '/cron_log.txt';
if (file_exists($logFile)) {
    if (is_writable($logFile)) {
        $success[] = "Log file is writable";
        echo "   ✓ Log file exists and is writable\n";
    } else {
        $errors[] = "Log file is not writable: $logFile";
        echo "   ❌ Log file is not writable\n";
    }
} else {
    // Try to create it
    if (@file_put_contents($logFile, "Test\n")) {
        $success[] = "Log file can be created";
        echo "   ✓ Log file can be created\n";
        unlink($logFile);
    } else {
        $errors[] = "Cannot create log file: $logFile";
        echo "   ❌ Cannot create log file\n";
    }
}
echo "\n";

// 8. Check database tables
echo "8. Checking database tables...\n";
if (isset($conn)) {
    $tables = ['backup_jobs', 'backup_logs', 'undefined_mails'];
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            $success[] = "Table '$table' exists";
            echo "   ✓ Table '$table' exists\n";
            
            // Count records
            $countResult = $conn->query("SELECT COUNT(*) as cnt FROM $table");
            if ($countResult) {
                $count = $countResult->fetch_assoc()['cnt'];
                echo "      Records: $count\n";
            }
        } else {
            $errors[] = "Table '$table' does not exist";
            echo "   ❌ Table '$table' does NOT exist\n";
        }
    }
} else {
    $warnings[] = "Cannot check database tables - connection not available";
    echo "   ⚠ Cannot check - database connection not available\n";
}
echo "\n";

// Summary
echo "========================================\n";
echo "DIAGNOSTIC SUMMARY\n";
echo "========================================\n\n";

if (empty($errors) && empty($warnings)) {
    echo "✓ All checks passed! System is ready.\n\n";
} else {
    if (!empty($errors)) {
        echo "❌ ERRORS (must fix):\n";
        foreach ($errors as $error) {
            echo "   - $error\n";
        }
        echo "\n";
    }
    
    if (!empty($warnings)) {
        echo "⚠ WARNINGS (should fix):\n";
        foreach ($warnings as $warning) {
            echo "   - $warning\n";
        }
        echo "\n";
    }
}

if (!empty($success)) {
    echo "✓ SUCCESS:\n";
    foreach ($success as $msg) {
        echo "   - $msg\n";
    }
    echo "\n";
}

// Recommendations
echo "========================================\n";
echo "RECOMMENDATIONS\n";
echo "========================================\n\n";

if (!empty($errors)) {
    echo "1. Fix all ERRORS above before running cron jobs\n";
}

echo "2. Test email parsing manually:\n";
echo "   cd " . $scriptDir . "\n";
echo "   " . $phpPath . " read_client_emails.php\n\n";

echo "3. Test all parsers:\n";
echo "   " . $phpPath . " run_all_parsers.php\n\n";

echo "4. Check log file after running:\n";
echo "   " . $logFile . "\n\n";

echo "5. Set up Windows Task Scheduler:\n";
echo "   - Open Task Scheduler (Win+R, type: taskschd.msc)\n";
echo "   - Create task to run: " . $scriptDir . "\\run_parsers.bat\n";
echo "   - Set to run every 5 minutes\n\n";

echo "========================================\n";
