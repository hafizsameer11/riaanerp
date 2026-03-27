<?php
/**
 * Quick Test Script - Test Email Parsing Immediately
 * 
 * This script quickly tests if email parsing is working
 * Run this to verify emails are being parsed correctly
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Verify PHP 7.4 is being used (required for IMAP support)
$phpVersion = phpversion();
if (!(version_compare($phpVersion, '7.4.0', '>=') && version_compare($phpVersion, '7.5.0', '<'))) {
    die("ERROR: This script must run with PHP 7.4 (IMAP extension required)\n" .
        "Current PHP version: $phpVersion\n" .
        "PHP 8.2 does not have IMAP extension enabled.\n" .
        "Please run this script with: php7.4 quick_test.php\n");
}

// Verify IMAP extension is available
if (!extension_loaded('imap')) {
    die("ERROR: IMAP extension is not loaded!\n" .
        "PHP version: $phpVersion\n" .
        "IMAP extension is required for email parsing.\n" .
        "Please ensure PHP 7.4 with IMAP extension is installed.\n");
}

// Verify IMAP functions are available
if (!function_exists('imap_open')) {
    die("ERROR: imap_open() function is not available!\n" .
        "PHP version: $phpVersion\n" .
        "IMAP extension may not be properly loaded.\n" .
        "Please check PHP configuration and ensure IMAP extension is enabled.\n");
}

echo "========================================\n";
echo "Quick Email Parsing Test\n";
echo "========================================\n\n";

// Get the directory of this script
$scriptDir = __DIR__;
$baseDir = dirname(dirname(dirname($scriptDir)));

// Include config
require_once $baseDir . '/config.php';

// Get email credentials from database (using CLIENT as default)
$mailConfig = $conn->query("SELECT email_address, app_password, imap_host FROM backup_mails WHERE mail_type = 'CLIENT' AND is_active = 1 LIMIT 1")->fetch_assoc();

if (!$mailConfig) {
    die("ERROR: No active email configuration found.\n" .
        "Please configure the email in Backup Monitoring -> Manage Backup Mails\n");
}

$imapHost = $mailConfig['imap_host'] ?? '{imap.gmail.com:993/imap/ssl}INBOX';
$imapUser = $mailConfig['email_address'];
$imapPass = $mailConfig['app_password'];

echo "Connecting to email: $imapUser\n";
$inbox = @imap_open($imapHost, $imapUser, $imapPass);

if (!$inbox) {
    die("ERROR: Cannot connect to email!\n" . imap_last_error() . "\n");
}

echo "✓ Connected successfully\n\n";

// Search for backup emails
echo "Searching for backup emails...\n";

// Get UNSEEN emails
$unseen = imap_search($inbox, 'UNSEEN');
$unseenCount = $unseen ? count($unseen) : 0;
echo "Unread emails: $unseenCount\n";

// Also check recent emails (last 24 hours)
$dateSince = date('d-M-Y', strtotime('-1 day'));
$recent = imap_search($inbox, 'SINCE "' . $dateSince . '"');
$recentCount = $recent ? count($recent) : 0;
echo "Recent emails (last 24h): $recentCount\n\n";

// Find backup emails
$backupEmails = [];
if ($unseen) {
    foreach ($unseen as $emailNo) {
        $overview = imap_fetch_overview($inbox, $emailNo, 0)[0];
        $subject = imap_utf8($overview->subject);
        if (stripos($subject, '[Success]') !== false || 
            stripos($subject, '[Warning]') !== false || 
            stripos($subject, '[Failed]') !== false) {
            $backupEmails[] = [
                'number' => $emailNo,
                'subject' => $subject,
                'from' => imap_utf8($overview->from),
                'date' => $overview->date
            ];
        }
    }
}

// Also check recent emails
if ($recent) {
    foreach ($recent as $emailNo) {
        // Skip if already in backupEmails
        $alreadyAdded = false;
        foreach ($backupEmails as $existing) {
            if ($existing['number'] == $emailNo) {
                $alreadyAdded = true;
                break;
            }
        }
        if ($alreadyAdded) continue;
        
        $overview = imap_fetch_overview($inbox, $emailNo, 0)[0];
        $subject = imap_utf8($overview->subject);
        if (stripos($subject, '[Success]') !== false || 
            stripos($subject, '[Warning]') !== false || 
            stripos($subject, '[Failed]') !== false) {
            $backupEmails[] = [
                'number' => $emailNo,
                'subject' => $subject,
                'from' => imap_utf8($overview->from),
                'date' => $overview->date
            ];
        }
    }
}

echo "Found " . count($backupEmails) . " backup email(s)\n\n";

if (empty($backupEmails)) {
    echo "⚠ No backup emails found!\n";
    echo "\nMake sure:\n";
    echo "1. Email subject contains [Success], [Warning], or [Failed]\n";
    echo "2. Email is unread OR sent in last 24 hours\n";
    echo "3. Email is in INBOX\n\n";
    
    // Show recent emails for debugging
    echo "Recent emails in INBOX (for debugging):\n";
    $allEmails = imap_search($inbox, 'ALL');
    if ($allEmails) {
        $recentList = array_slice($allEmails, -10);
        foreach ($recentList as $emailNo) {
            $overview = imap_fetch_overview($inbox, $emailNo, 0)[0];
            $seen = ($overview->seen) ? 'READ' : 'UNREAD';
            $subject = imap_utf8($overview->subject);
            echo "  #$emailNo [$seen]: " . substr($subject, 0, 60) . "\n";
        }
    }
} else {
    echo "Backup emails found:\n";
    foreach ($backupEmails as $email) {
        echo "  #{$email['number']}: {$email['subject']}\n";
        echo "     From: {$email['from']}\n";
        echo "     Date: {$email['date']}\n\n";
    }
    
    echo "\n✓ Email parsing should work!\n";
    echo "Run the parser script to process these emails:\n";
    echo "  php read_client_emails.php\n\n";
}

imap_close($inbox);

echo "========================================\n";
echo "Test Complete\n";
echo "========================================\n";
