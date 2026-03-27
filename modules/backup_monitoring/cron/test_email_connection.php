<?php
/**
 * Email Connection Test Script
 * This script only tests if you can connect to Gmail
 * It does NOT process any emails
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Verify PHP 7.4 is being used (required for IMAP support)
$phpVersion = phpversion();
if (!(version_compare($phpVersion, '7.4.0', '>=') && version_compare($phpVersion, '7.5.0', '<'))) {
    die("ERROR: This script must run with PHP 7.4 (IMAP extension required)\n" .
        "Current PHP version: $phpVersion\n" .
        "PHP 8.2 does not have IMAP extension enabled.\n" .
        "Please run this script with: php7.4 test_email_connection.php\n");
}

// Verify IMAP extension is available
if (!extension_loaded('imap')) {
    die("ERROR: IMAP extension is not loaded!\n" .
        "PHP version: $phpVersion\n" .
        "IMAP extension is required for email connection testing.\n" .
        "Please ensure PHP 7.4 with IMAP extension is installed.\n");
}

echo "========================================\n";
echo "   EMAIL CONNECTION TEST\n";
echo "========================================\n\n";

// Check if IMAP extension is enabled
if (!function_exists('imap_open')) {
    echo "✗ ERROR: IMAP extension is NOT enabled in PHP!\n\n";
    echo "========================================\n";
    echo "   HOW TO ENABLE IMAP IN XAMPP\n";
    echo "========================================\n\n";
    echo "Step 1: Open php.ini file\n";
    echo "  Location: C:\\xampp\\php\\php.ini\n\n";
    echo "Step 2: Find this line (search for 'imap'):\n";
    echo "  ;extension=imap\n\n";
    echo "Step 3: Remove the semicolon to make it:\n";
    echo "  extension=imap\n\n";
    echo "Step 4: Save the file\n\n";
    echo "Step 5: Restart Apache in XAMPP Control Panel\n";
    echo "  - Click 'Stop' next to Apache\n";
    echo "  - Wait a few seconds\n";
    echo "  - Click 'Start' next to Apache\n\n";
    echo "Step 6: Run this test script again\n\n";
    echo "========================================\n";
    echo "   ALTERNATIVE: Check php.ini location\n";
    echo "========================================\n";
    echo "Your php.ini file is located at:\n";
    $phpIniPath = php_ini_loaded_file();
    if ($phpIniPath) {
        echo "  $phpIniPath\n";
    } else {
        echo "  Could not find php.ini file\n";
        echo "  Try: C:\\xampp\\php\\php.ini\n";
    }
    echo "\n";
    die();
}

echo "✓ IMAP extension is enabled\n\n";

// Email credentials
$imapHost = '{imap.gmail.com:993/imap/ssl}INBOX';
$imapUser = 'malikrohail252@gmail.com';
$imapPass = 'vebxwsjreqifndty'; // Gmail App Password

echo "Testing connection to: $imapUser\n";
echo "Host: $imapHost\n\n";

// Test 1: Try to connect to INBOX
echo "--- Test 1: Connecting to INBOX ---\n";
$inbox = @imap_open($imapHost, $imapUser, $imapPass);

if ($inbox) {
    echo "✓ SUCCESS: Connected to INBOX!\n";
    
    // Get mailbox info
    $mailboxInfo = imap_mailboxmsginfo($inbox);
    echo "  Total messages in INBOX: " . $mailboxInfo->Nmsgs . "\n";
    echo "  Unread messages: " . ($mailboxInfo->Nmsgs - $mailboxInfo->Recent) . "\n";
    
    // Check for backup emails
    $backupEmails = imap_search($inbox, 'SUBJECT "[Success]" OR SUBJECT "[Warning]" OR SUBJECT "[Failed]"');
    if ($backupEmails) {
        echo "  Found " . count($backupEmails) . " backup email(s) in INBOX\n";
    } else {
        echo "  No backup emails found in INBOX\n";
    }
    
    imap_close($inbox);
} else {
    $error = imap_last_error();
    echo "✗ FAILED: Could not connect to INBOX\n";
    echo "  Error: $error\n";
    echo "\n  Troubleshooting:\n";
    echo "  1. Check if IMAP is enabled in Gmail\n";
    echo "  2. Verify the app password is correct\n";
    echo "  3. Make sure 'Less secure app access' is enabled (if needed)\n";
}

echo "\n";

// Test 2: Try to connect to Sent Mail
echo "--- Test 2: Connecting to Sent Mail ---\n";
$sentFolder = '{imap.gmail.com:993/imap/ssl}[Gmail]/Sent Mail';
$sentInbox = @imap_open($sentFolder, $imapUser, $imapPass);

if ($sentInbox) {
    echo "✓ SUCCESS: Connected to Sent Mail!\n";
    
    // Get mailbox info
    $mailboxInfo = imap_mailboxmsginfo($sentInbox);
    echo "  Total messages in Sent Mail: " . $mailboxInfo->Nmsgs . "\n";
    
    // Check for recent backup emails (last 7 days)
    $dateSince = date('d-M-Y', strtotime('-7 days'));
    $recentEmails = imap_search($sentInbox, 'SINCE "' . $dateSince . '"');
    
    if ($recentEmails) {
        // Filter for backup emails
        $backupCount = 0;
        foreach ($recentEmails as $emailNo) {
            $overview = imap_fetch_overview($sentInbox, $emailNo, 0)[0];
            $subject = imap_utf8($overview->subject);
            if (stripos($subject, '[Success]') !== false || 
                stripos($subject, '[Warning]') !== false || 
                stripos($subject, '[Failed]') !== false) {
                $backupCount++;
            }
        }
        echo "  Found $backupCount backup email(s) in Sent Mail (last 7 days)\n";
        
        // Show recent backup emails
        if ($backupCount > 0) {
            echo "\n  Recent backup emails:\n";
            $shown = 0;
            foreach ($recentEmails as $emailNo) {
                if ($shown >= 5) break; // Show max 5
                $overview = imap_fetch_overview($sentInbox, $emailNo, 0)[0];
                $subject = imap_utf8($overview->subject);
                if (stripos($subject, '[Success]') !== false || 
                    stripos($subject, '[Warning]') !== false || 
                    stripos($subject, '[Failed]') !== false) {
                    echo "    - " . $subject . "\n";
                    $shown++;
                }
            }
        }
    } else {
        echo "  No emails found in Sent Mail (last 7 days)\n";
    }
    
    imap_close($sentInbox);
} else {
    $error = imap_last_error();
    echo "✗ FAILED: Could not connect to Sent Mail\n";
    echo "  Error: $error\n";
    echo "  (This is okay if you don't need to check Sent Mail)\n";
}

echo "\n";

// Test 3: List available folders
echo "--- Test 3: Available Gmail Folders ---\n";
$inbox = @imap_open($imapHost, $imapUser, $imapPass);
if ($inbox) {
    $folders = imap_list($inbox, '{imap.gmail.com:993/imap/ssl}', '*');
    if ($folders) {
        echo "✓ Found " . count($folders) . " folder(s):\n";
        foreach ($folders as $folder) {
            $folderName = str_replace('{imap.gmail.com:993/imap/ssl}', '', $folder);
            echo "  - $folderName\n";
        }
    }
    imap_close($inbox);
} else {
    echo "✗ Could not list folders (connection failed)\n";
}

echo "\n";
echo "========================================\n";
echo "   CONNECTION TEST COMPLETED\n";
echo "========================================\n";
echo "\n";
echo "SUMMARY:\n";
echo "--------\n";

// Final summary
$inbox = @imap_open($imapHost, $imapUser, $imapPass);
if ($inbox) {
    echo "✓ Email connection is WORKING!\n";
    echo "  You can now run the email parser script.\n";
    imap_close($inbox);
} else {
    echo "✗ Email connection is NOT WORKING!\n";
    echo "  Please fix the connection issues above.\n";
}

echo "\n";
