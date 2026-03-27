<?php
/**
 * Master Cron Job Script - Run All Email Parsers
 * 
 * This script runs all email parsers automatically:
 * - Client Backups
 * - Veeam Backups
 * - VeeamCloud Backups
 * - NAS Backups
 * 
 * Run this script via Windows Task Scheduler or cron every few minutes
 * 
 * Usage:
 *   php7.4 run_all_parsers.php  (Linux - uses PHP 7.4 with IMAP support)
 *   php run_all_parsers.php      (Windows - uses default PHP)
 * 
 * Or via Windows Task Scheduler:
 *   C:\xampp\php\php.exe C:\xampp\htdocs\sautech\modules\billing\backup_monitoring\cron\run_all_parsers.php
 * 
 * NOTE: On Linux, this script automatically uses php7.4 which has IMAP extension enabled.
 *       PHP 8.2 does not have IMAP extension, so php7.4 must be used.
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('max_execution_time', 300); // 5 minutes max execution time

// Verify PHP 7.4 is being used (required for IMAP support)
$phpVersion = phpversion();
if (!(version_compare($phpVersion, '7.4.0', '>=') && version_compare($phpVersion, '7.5.0', '<'))) {
    $errorMsg = "ERROR: This script must run with PHP 7.4 (IMAP extension required)\n";
    $errorMsg .= "Current PHP version: $phpVersion\n";
    $errorMsg .= "PHP 8.2 does not have IMAP extension enabled.\n";
    $errorMsg .= "Please run this script with: php7.4 run_all_parsers.php\n";
    $errorMsg .= "Or update cron job to use: /usr/bin/php7.4\n";
    die($errorMsg);
}

// Verify IMAP extension is available
if (!extension_loaded('imap')) {
    $errorMsg = "ERROR: IMAP extension is not loaded!\n";
    $errorMsg .= "PHP version: $phpVersion\n";
    $errorMsg .= "IMAP extension is required for email parsing.\n";
    die($errorMsg);
}

// Get the directory of this script
$scriptDir = __DIR__;
$baseDir = dirname(dirname(dirname($scriptDir)));

// Include config
require_once $baseDir . '/config.php';

// Log file paths - separate log for each parser
$logFile = $scriptDir . '/cron_log.txt'; // Main combined log
$logFiles = [
    'CLIENT' => $scriptDir . '/logs/client_log.txt',
    'VEEAM' => $scriptDir . '/logs/veeam_log.txt',
    'VEEAMCLOUD' => $scriptDir . '/logs/veeamcloud_log.txt',
    'NAS' => $scriptDir . '/logs/nas_log.txt'
];

// Create logs directory if it doesn't exist
$logsDir = $scriptDir . '/logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}

/**
 * Write log message with timestamp
 */
function logMessage($message, $logFile) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    echo $logEntry;
}

/**
 * Run email parser script
 */
function runParser($scriptPath, $source, $logFile, $sourceLogFile = null) {
    // Log to main log file
    logMessage("========================================", $logFile);
    logMessage("Starting parser: $source", $logFile);
    logMessage("Script: $scriptPath", $logFile);
    
    // Also log to source-specific log file if provided
    if ($sourceLogFile) {
        logMessage("========================================", $sourceLogFile);
        logMessage("Starting parser: $source", $sourceLogFile);
        logMessage("Script: $scriptPath", $sourceLogFile);
    }
    
    if (!file_exists($scriptPath)) {
        logMessage("ERROR: Script not found: $scriptPath", $logFile);
        if ($sourceLogFile) {
            logMessage("ERROR: Script not found: $scriptPath", $sourceLogFile);
        }
        return false;
    }
    
    // Get PHP executable path - prioritize PHP 7.4 for IMAP support
    $phpPath = 'php7.4'; // Default to PHP 7.4 which has IMAP extension
    if (file_exists('/usr/bin/php7.4')) {
        $phpPath = '/usr/bin/php7.4';
    } elseif (file_exists('/usr/bin/php7.4')) {
        $phpPath = '/usr/bin/php7.4';
    } elseif (defined('PHP_BINARY') && PHP_BINARY && file_exists(PHP_BINARY)) {
        // Fallback to current PHP binary if php7.4 not found
        $phpPath = PHP_BINARY;
    } elseif (file_exists('C:\\xampp\\php\\php.exe')) {
        $phpPath = 'C:\\xampp\\php\\php.exe';
    } elseif (file_exists('C:\\php\\php.exe')) {
        $phpPath = 'C:\\php\\php.exe';
    } else {
        // Last resort: try php7.4 command
        $phpPath = 'php7.4';
    }
    
    // Change to script directory
    $scriptDir = dirname($scriptPath);
    $oldCwd = getcwd();
    chdir($scriptDir);
    
    // Run the script
    $command = escapeshellarg($phpPath) . ' ' . escapeshellarg(basename($scriptPath)) . ' 2>&1';
    logMessage("Command: $command", $logFile);
    if ($sourceLogFile) {
        logMessage("Command: $command", $sourceLogFile);
    }
    
    $output = [];
    $returnVar = 0;
    exec($command, $output, $returnVar);
    
    // Restore directory
    chdir($oldCwd);
    
    // Process output
    $outputStr = implode("\n", $output);
    
    // Log full output for debugging (first 5000 chars to capture more details)
    if (strlen($outputStr) > 0) {
        // Log to main file (truncated)
        logMessage("Parser output (first 5000 chars):", $logFile);
        logMessage(substr($outputStr, 0, 5000), $logFile);
        if (strlen($outputStr) > 5000) {
            logMessage("... (output truncated, showing last 2000 chars) ...", $logFile);
            logMessage(substr($outputStr, -2000), $logFile);
        }
        
        // Log FULL output to source-specific log file
        if ($sourceLogFile) {
            logMessage("========================================", $sourceLogFile);
            logMessage("FULL PARSER OUTPUT:", $sourceLogFile);
            logMessage("========================================", $sourceLogFile);
            logMessage($outputStr, $sourceLogFile);
            logMessage("========================================", $sourceLogFile);
        }
    }
    
    // Extract key information
    $emailsProcessed = 0;
    if (preg_match('/Total emails processed:\s*(\d+)/', $outputStr, $m)) {
        $emailsProcessed = (int)$m[1];
    } elseif (preg_match('/Found (\d+) .*backup email/i', $outputStr, $m)) {
        $emailsProcessed = (int)$m[1];
    } elseif (preg_match('/Found (\d+) .*email/i', $outputStr, $m)) {
        $emailsProcessed = (int)$m[1];
    }
    
    $success = ($returnVar === 0);
    $hasErrors = (stripos($outputStr, 'ERROR') !== false || 
                  stripos($outputStr, 'Fatal error') !== false ||
                  stripos($outputStr, 'Call to undefined') !== false);
    
    if ($hasErrors) {
        logMessage("ERROR: Parser failed for $source (return code: $returnVar)", $logFile);
        logMessage("Error output: " . substr($outputStr, 0, 1000), $logFile);
        if ($sourceLogFile) {
            logMessage("ERROR: Parser failed for $source (return code: $returnVar)", $sourceLogFile);
            logMessage("Error output: " . substr($outputStr, 0, 1000), $sourceLogFile);
        }
        return false;
    } elseif ($emailsProcessed > 0) {
        logMessage("SUCCESS: Processed $emailsProcessed email(s) for $source", $logFile);
        if ($sourceLogFile) {
            logMessage("SUCCESS: Processed $emailsProcessed email(s) for $source", $sourceLogFile);
        }
        return true;
    } elseif (stripos($outputStr, 'No new emails') !== false || 
              stripos($outputStr, 'No backup emails') !== false ||
              stripos($outputStr, 'Email Parsing Completed') !== false) {
        logMessage("INFO: No new emails found for $source (parser completed successfully)", $logFile);
        if ($sourceLogFile) {
            logMessage("INFO: No new emails found for $source (parser completed successfully)", $sourceLogFile);
        }
        return true;
    } else {
        logMessage("WARNING: Parser completed but unclear result for $source", $logFile);
        logMessage("Output preview: " . substr($outputStr, 0, 200), $logFile);
        if ($sourceLogFile) {
            logMessage("WARNING: Parser completed but unclear result for $source", $sourceLogFile);
            logMessage("Output preview: " . substr($outputStr, 0, 200), $sourceLogFile);
        }
        return true; // Assume success if no clear error
    }
}

// Start logging
logMessage("========================================", $logFile);
logMessage("CRON JOB STARTED - Email Parsing", $logFile);
logMessage("========================================", $logFile);

// Define parsers to run
$parsers = [
    'CLIENT' => 'read_client_emails.php',
    'VEEAM' => 'read_veeam_emails.php',
    'VEEAMCLOUD' => 'read_veeamcloud_emails.php',
    'NAS' => 'read_nas_emails.php'
];

$results = [];
$totalEmails = 0;

// Run each parser
foreach ($parsers as $source => $scriptFile) {
    $scriptPath = $scriptDir . '/' . $scriptFile;
    $sourceLogFile = isset($logFiles[$source]) ? $logFiles[$source] : null;
    $result = runParser($scriptPath, $source, $logFile, $sourceLogFile);
    $results[$source] = $result;
    
    // Small delay between parsers to avoid overwhelming the email server
    sleep(2);
}

// Summary - log to main file
logMessage("========================================", $logFile);
logMessage("CRON JOB COMPLETED", $logFile);
logMessage("Results:", $logFile);
foreach ($results as $source => $result) {
    $status = $result ? 'SUCCESS' : 'FAILED';
    logMessage("  $source: $status", $logFile);
    
    // Also log summary to individual log file
    if (isset($logFiles[$source])) {
        logMessage("========================================", $logFiles[$source]);
        logMessage("CRON JOB COMPLETED", $logFiles[$source]);
        logMessage("Result: $status", $logFiles[$source]);
        logMessage("========================================", $logFiles[$source]);
        logMessage("", $logFiles[$source]);
    }
}
logMessage("========================================", $logFile);
logMessage("", $logFile);

// Exit with appropriate code
$allSuccess = !in_array(false, $results);
exit($allSuccess ? 0 : 1);
