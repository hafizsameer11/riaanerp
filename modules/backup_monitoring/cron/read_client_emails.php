<?php
/**
 * CLIENT Email Parser - Processes ALL emails (SEEN + UNSEEN) and deletes after storage (POP behavior)
 * 
 * NEW FLOW (per user requirement):
 * 1. Email arrives in mailbox
 * 2. ERP fetches it via POP
 * 3. ERP stores the email in database (ALWAYS - regardless of classification) → undefined_mails
 * 4. ERP removes (POP deletes) it from the mailbox
 * 5. ERP checks: No matching backup job → already in undefined_mails (done)
 * 6. If matching backup job → also save to backup_logs
 * 
 * This script:
 * 1. Connects to CLIENT email account
 * 2. Reads ALL emails (SEEN + UNSEEN) from mailbox (no date restriction)
 * 3. Extracts backup data (status, device type, backup date, backup size)
 * 4. Stores ALL emails in undefined_mails FIRST (always, regardless of classification)
 * 5. Deletes email from mailbox (POP behavior)
 * 6. Checks for matching backup job
 * 7. If job found → also saves to backup_logs
 * 
 * Requirements:
 * - PHP 7.4 with IMAP extension
 * - Email configuration in backup_mails table
 * 
 * Usage: php7.4 read_client_emails.php
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('max_execution_time', 300);

// Get script directory
$scriptDir = __DIR__;
$baseDir = dirname(dirname(dirname($scriptDir)));
require_once $baseDir . '/config.php';
require_once $scriptDir . '/EmailParserHelper.php';
require_once $scriptDir . '/send_error_notification.php';

// Source type
$source = 'CLIENT';
$logFile = $scriptDir . '/logs/client_log.txt';

// Create logs directory if it doesn't exist
$logsDir = dirname($logFile);
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
 * Fetch single row from statement (PHP 7.4 compatible)
 */
function fetchSingleRow($stmt) {
    if (extension_loaded('mysqlnd')) {
        $result = $stmt->get_result();
        return $result ? $result->fetch_assoc() : null;
    }
    // Fallback for PHP 7.4 CLI
    $stmt->store_result();
    $meta = $stmt->result_metadata();
    if (!$meta) return null;
    
    $fields = [];
    $params = [];
    while ($field = $meta->fetch_field()) {
        $params[] = &$fields[$field->name];
    }
    call_user_func_array([$stmt, 'bind_result'], $params);
    
    $row = null;
    if ($stmt->fetch()) {
        $row = [];
        foreach ($fields as $key => $value) {
            $row[$key] = $value;
        }
    }
    $meta->free();
    return $row;
}

// Start logging
logMessage("========================================", $logFile);
logMessage("=== Backup Email Parser Started ===", $logFile);
logMessage("Source: $source", $logFile);

// Get email configuration
$mailConfig = EmailParserHelper::getEmailConfig($source, $conn);
if (!$mailConfig) {
    logMessage("ERROR: No email configuration found for $source backups", $logFile);
    logMessage("Please configure the email in Backup Monitoring -> Manage Backup Mails", $logFile);
    exit(1);
}

// Get IMAP connection details
$imapHost = EmailParserHelper::getImapHost($mailConfig);
$imapUser = $mailConfig['email_address'];
$imapPass = $mailConfig['app_password'];

logMessage("Connecting to: $imapUser", $logFile);

// Connect to IMAP
$inbox = @imap_open($imapHost, $imapUser, $imapPass);
if (!$inbox) {
    $error = imap_last_error();
    logMessage("ERROR: Failed to connect to email: $error", $logFile);
    exit(1);
}

logMessage("✓ Connected successfully", $logFile);
logMessage("", $logFile);

// Get ALL emails (SEEN and UNSEEN) from mailbox - process everything, no date filter
logMessage("--- Searching for emails ---", $logFile);
$allEmailsInMailbox = imap_search($inbox, 'ALL');
$allEmails = [];
$processedEmailIds = [];

if ($allEmailsInMailbox) {
    $seenCount = 0;
    $unseenCount = 0;
    foreach ($allEmailsInMailbox as $emailNo) {
        $allEmails[] = $emailNo;
        $processedEmailIds[$emailNo] = true;
        // Check if email is SEEN or UNSEEN for logging
        $overview = imap_fetch_overview($inbox, $emailNo, 0)[0];
        $isUnseen = !isset($overview->seen) || $overview->seen == 0;
        if ($isUnseen) {
            $unseenCount++;
        } else {
            $seenCount++;
        }
    }
    logMessage("Found " . count($allEmailsInMailbox) . " email(s) in mailbox: $unseenCount UNSEEN, $seenCount SEEN", $logFile);
} else {
    logMessage("No emails found in mailbox", $logFile);
    imap_close($inbox);
    exit(0);
}

$totalEmails = count($allEmails);
logMessage("Total emails to process: $totalEmails", $logFile);
logMessage("", $logFile);

// Processing counters
$processedCount = 0;
$savedToLogs = 0;
$savedToUndefined = 0;
$duplicateCount = 0;
$errorCount = 0;

// Process each email
foreach ($allEmails as $emailNo) {
    $emailProcessedSuccessfully = false;
    $isUnseen = false;
    
    try {
        // Get email overview
        $overview = imap_fetch_overview($inbox, $emailNo, 0)[0];
        
        // Decode subject properly - handle MIME encoded subjects
        $subject = isset($overview->subject) ? imap_utf8($overview->subject) : '';
        if (empty($subject) && isset($overview->subject)) {
            // Fallback: try mb_decode_mimeheader if imap_utf8 doesn't work
            $subject = mb_decode_mimeheader($overview->subject);
        }
        // If still encoded (base64 or quoted-printable), try manual decoding
        if (preg_match('/=\?([^?]+)\?([BQ])\?([^?]+)\?=/i', $subject, $matches)) {
            if (strtoupper($matches[2]) === 'B') {
                $decoded = base64_decode($matches[3]);
                $subject = mb_convert_encoding($decoded, 'UTF-8', $matches[1]);
            } elseif (strtoupper($matches[2]) === 'Q') {
                $decoded = quoted_printable_decode(str_replace('_', ' ', $matches[3]));
                $subject = mb_convert_encoding($decoded, 'UTF-8', $matches[1]);
            }
        }
        
        $date = isset($overview->date) ? $overview->date : '';
        $messageId = isset($overview->message_id) ? $overview->message_id : '';
        
        // Check if email is SEEN or UNSEEN (for logging purposes only)
        $isUnseen = !isset($overview->seen) || $overview->seen == 0;
        
        // Get email body - try multiple methods to get the actual content
        $body = imap_body($inbox, $emailNo);
        if ($body === false || empty(trim($body))) {
            $body = imap_fetchbody($inbox, $emailNo, 1);
        }
        // Try alternative body parts if first attempt fails
        if (empty(trim($body)) || strlen($body) < 50) {
            $body = imap_fetchbody($inbox, $emailNo, "1.1"); // Try HTML part
            if (empty(trim($body))) {
                $body = imap_fetchbody($inbox, $emailNo, "1.2"); // Try text part
            }
        }
        
        // Decode base64 if the body appears to be base64 encoded
        // Check if body is base64 (starts with base64-like pattern and is mostly alphanumeric)
        if (preg_match('/^[A-Za-z0-9+\/=\s]+$/', substr($body, 0, 100)) && strlen($body) > 100) {
            $decoded = base64_decode($body, true);
            if ($decoded !== false && strlen($decoded) > 10) {
                $body = $decoded;
                logMessage("  ✓ Decoded base64 email body", $logFile);
            }
        }
        
        // Decode quoted-printable
        $body = quoted_printable_decode($body);
        
        // Convert encoding
        $body = mb_convert_encoding($body, 'UTF-8', 'ISO-8859-1');
        
        // Remove UTF-8 BOM and encoding artifacts
        $body = preg_replace('/^\xEF\xBB\xBF/', '', $body);
        $body = preg_replace('/\xEF\xBB\xBF/', '', $body);
        $body = preg_replace('/\xC2\xA0/', ' ', $body);
        $body = preg_replace('/\x{00A0}/u', ' ', $body);
        $body = preg_replace('/\xC2(?![\x80-\xBF])/', '', $body);
        $body = preg_replace('/Â(?![\x80-\xBF])/u', '', $body);
        $body = preg_replace('/Â[\s\.,;:!?]/u', '', $body);
        
        // Remove HTML tags for better parsing
        $bodyPlain = strip_tags($body);
        
        // Remove UTF-8 BOM and encoding artifacts from bodyPlain as well
        $bodyPlain = preg_replace('/^\xEF\xBB\xBF/', '', $bodyPlain);
        $bodyPlain = preg_replace('/\xEF\xBB\xBF/', '', $bodyPlain);
        $bodyPlain = preg_replace('/\xC2\xA0/', ' ', $bodyPlain);
        $bodyPlain = preg_replace('/\x{00A0}/u', ' ', $bodyPlain);
        $bodyPlain = preg_replace('/\xC2(?![\x80-\xBF])/', '', $bodyPlain);
        $bodyPlain = preg_replace('/Â(?![\x80-\xBF])/u', '', $bodyPlain);
        $bodyPlain = preg_replace('/Â[\s\.,;:!?]/u', '', $bodyPlain);
        
        // If bodyPlain is still empty or too short, try to get plain text version
        if (empty(trim($bodyPlain)) || strlen($bodyPlain) < 50) {
            // Try to get plain text part directly
            $textBody = imap_fetchbody($inbox, $emailNo, "1");
            if (!empty($textBody)) {
                $textBody = base64_decode($textBody, true);
                if ($textBody === false) {
                    $textBody = quoted_printable_decode($textBody);
                }
                // Convert encoding and clean up
                $textBody = mb_convert_encoding($textBody, 'UTF-8', 'ISO-8859-1');
                // Remove UTF-8 BOM and encoding artifacts
                $textBody = preg_replace('/^\xEF\xBB\xBF/', '', $textBody);
                $textBody = preg_replace('/\xEF\xBB\xBF/', '', $textBody);
                $textBody = preg_replace('/\xC2\xA0/', ' ', $textBody);
                $textBody = preg_replace('/\x{00A0}/u', ' ', $textBody);
                $textBody = preg_replace('/\xC2(?![\x80-\xBF])/', '', $textBody);
                $textBody = preg_replace('/Â(?![\x80-\xBF])/u', '', $textBody);
                $textBody = preg_replace('/Â[\s\.,;:!?]/u', '', $textBody);
                $bodyPlain = strip_tags($textBody);
                if (!empty($bodyPlain)) {
                    $body = $textBody;
                    logMessage("  ✓ Using alternative body extraction method", $logFile);
                }
            }
        }
        
        // Debug: Log first 500 chars of bodyPlain to see what we're working with
        logMessage("  Body preview (first 500 chars): " . substr($bodyPlain, 0, 500), $logFile);
        
        $emailType = $isUnseen ? "UNSEEN" : "SEEN";
        logMessage("Processing email #$emailNo ($emailType): " . substr($subject, 0, 50) . "...", $logFile);
        logMessage("  Email details - Subject: '$subject'", $logFile);
        
        // Extract backup information from subject and body
        $status = 'Unknown';
        $deviceType = '';
        $backupDate = null;
        $backupSize = '';
        
        // CLIENT Email Subject Format: "[Success] DeviceName" or "RCC-Carmen-DailyBackup" or "Backup job: DeviceName"
        // Try to extract status from subject first
        if (preg_match('/\[(Success|Warning|Error|Failed)\]/i', $subject, $matches)) {
            $status = ucfirst(strtolower($matches[1]));
            if ($status === 'Failed') {
                $status = 'Error';
            }
        }
        
        // If status not found in subject, try to extract from body
        // Look for status in body (e.g., "Agent Backup job: DeviceName" followed by status, or status in summary table)
        if ($status === 'Unknown') {
            // Pattern 1: Look for status in summary table (Success: 1, Warning: 0, Error: 0) - highest priority
            if (preg_match('/\b(Success|Warning|Error|Failed)\s*:\s*[1-9]/i', $bodyPlain, $matches)) {
                $status = ucfirst(strtolower($matches[1]));
                if ($status === 'Failed') {
                    $status = 'Error';
                }
                logMessage("  ✓ Extracted status from summary table: '$status'", $logFile);
            }
            // Pattern 2: Look for status word anywhere in body (case-insensitive, whole word only)
            // This is more aggressive - look for Success, Warning, Error, Failed as standalone words
            elseif (preg_match('/\b(Success|Warning|Error|Failed)\b/i', $bodyPlain, $matches)) {
                $statusWord = strtolower($matches[1]);
                if ($statusWord === 'success' || $statusWord === 'warning' || $statusWord === 'error' || $statusWord === 'failed') {
                    $status = ucfirst($statusWord);
                    if ($status === 'Failed') {
                        $status = 'Error';
                    }
                    logMessage("  ✓ Extracted status from body (standalone word): '$status'", $logFile);
                }
            }
            // Pattern 3: Look for status near "Agent Backup job" or "Veeam Agent" (common in Veeam emails)
            if ($status === 'Unknown' && preg_match('/(?:Agent\s+Backup\s+job|Veeam\s+Agent)[^\n]*(Success|Warning|Error|Failed)/i', $bodyPlain, $matches)) {
                $status = ucfirst(strtolower($matches[1]));
                if ($status === 'Failed') {
                    $status = 'Error';
                }
                logMessage("  ✓ Extracted status near Agent Backup job: '$status'", $logFile);
            }
            // Pattern 4: Look for status in Details table Status column
            if ($status === 'Unknown' && preg_match('/Name\s+Status[^\n]*\n[^\n]*\n[^\n]*\b(Success|Warning|Error|Failed)\b/i', $bodyPlain, $matches)) {
                $status = ucfirst(strtolower($matches[1]));
                if ($status === 'Failed') {
                    $status = 'Error';
                }
                logMessage("  ✓ Extracted status from Details table: '$status'", $logFile);
            }
        }
        
        // Try to extract device name from subject or body
        // NEW PRIORITY (per user requirement): Subject > Details table > Agent Backup job > Generic Backup job
        // Device type should come from subject (e.g., "RCC-Carmen-DailyBackup")
        
        // Pattern 1: [Status] DeviceName (from subject) - highest priority
        // Example: [Success] CenturionDay (1 objects) -> CenturionDay
        if (preg_match('/\[(?:Success|Warning|Error|Failed)\]\s*([^\s\(]+)/i', $subject, $matches)) {
            $deviceType = trim($matches[1]);
            logMessage("  ✓ Extracted device from subject pattern [Status] DeviceName: '$deviceType'", $logFile);
        } elseif (preg_match('/\[(?:Success|Warning|Error|Failed)\]\s*(.+?)(?:\s*\(|$)/i', $subject, $matches)) {
            $deviceType = trim($matches[1]);
            logMessage("  ✓ Extracted device from subject pattern [Status] DeviceName: '$deviceType'", $logFile);
        }
        // Pattern 2: Subject itself as device type (when subject doesn't match [Status] pattern)
        // Example: "RCC-Carmen-DailyBackup" -> "RCC-Carmen-DailyBackup"
        // This is the PRIMARY pattern for emails like "RCC-Carmen-DailyBackup"
        elseif (!empty($subject) && !preg_match('/\[(?:Success|Warning|Error|Failed)\]/i', $subject)) {
            $deviceType = trim($subject);
            // Clean up subject: remove common email prefixes/suffixes if any
            $deviceType = preg_replace('/^(Re|Fwd|Fw)[:\s]+/i', '', $deviceType);
            $deviceType = trim($deviceType);
            logMessage("  ✓ Using subject as device type: '$deviceType'", $logFile);
        }
        // Pattern 3: Extract device name from Details table "Name" column (fallback - actual device name)
        // Look for table structure: "Name" column header followed by device name in next data row
        // Example: "Name Status Start time...\nLAPTOP-UJGC58H5 Success..."
        // Note: This is a fallback - subject takes priority per user requirement
        elseif (preg_match('/Details\s*Name\s+Status[^\n]*\n[^\n]*\n\s*([A-Z0-9\-_]+)/i', $bodyPlain, $matches)) {
            $deviceType = trim($matches[1]);
            logMessage("  ✓ Extracted device from Details table (fallback): '$deviceType'", $logFile);
        } elseif (preg_match('/Name\s+Status\s+Start\s+time[^\n]*\n[^\n]*\n\s*([A-Z0-9\-_]+)/i', $bodyPlain, $matches)) {
            $deviceType = trim($matches[1]);
            logMessage("  ✓ Extracted device from Details table (fallback): '$deviceType'", $logFile);
        } elseif (preg_match('/Details[^\n]*\n[^\n]*Name[^\n]*\n[^\n]*\n\s*([A-Z0-9\-_]+)/i', $bodyPlain, $matches)) {
            $deviceType = trim($matches[1]);
            logMessage("  ✓ Extracted device from Details table (fallback): '$deviceType'", $logFile);
        }
        // Pattern 3: "Agent Backup job: DeviceName" (for Veeam Agent emails - job name, not device name)
        // Example: "Agent Backup job: Carmen-Daily-Backup" -> "Carmen-Daily-Backup"
        // Note: This is the job name, not the device name, but use it if device name not found
        elseif (preg_match('/Agent\s+Backup\s+job[:\s]+([^\n\r]+)/i', $bodyPlain, $matches)) {
            $deviceType = trim($matches[1]);
            // Remove any trailing text after device name (e.g., "Carmen-Daily-Backup Veeam Agent" -> "Carmen-Daily-Backup")
            if (preg_match('/^(.+?)(?:\s+Veeam\s+Agent|$)/i', $deviceType, $nameMatches)) {
                $deviceType = trim($nameMatches[1]);
            }
            logMessage("  ✓ Extracted device from Agent Backup job: '$deviceType'", $logFile);
        }
        // Pattern 4: "Backup job: DeviceName" (generic pattern)
        elseif (preg_match('/Backup\s+job[:\s]+([^\n\r\(]+)/i', $bodyPlain, $matches)) {
            $deviceType = trim($matches[1]);
            // Remove any trailing text
            if (preg_match('/^(.+?)(?:\s*\(|\s*Success|\s*Warning|\s*Error|\s*Veeam|$)/i', $deviceType, $nameMatches)) {
                $deviceType = trim($nameMatches[1]);
            }
            logMessage("  ✓ Extracted device from Backup job: '$deviceType'", $logFile);
        }
        
        // Try to extract date from email date or body
        // Pattern: Day, DD Month YYYY HH:MM:SS
        // Use bodyPlain for better parsing (no HTML tags)
        if (!empty($date)) {
            $backupDate = date('Y-m-d H:i:s', strtotime($date));
        } elseif (preg_match('/(?:Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday),\s*(\d{1,2})\s+(January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{4})\s+(\d{1,2}):(\d{2}):(\d{2})/i', $bodyPlain, $matches)) {
            $monthNames = ['january' => '01', 'february' => '02', 'march' => '03', 'april' => '04', 'may' => '05', 'june' => '06',
                          'july' => '07', 'august' => '08', 'september' => '09', 'october' => '10', 'november' => '11', 'december' => '12'];
            $month = strtolower($matches[2]);
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $year = $matches[3];
            $hour = str_pad($matches[4], 2, '0', STR_PAD_LEFT);
            $min = str_pad($matches[5], 2, '0', STR_PAD_LEFT);
            $sec = str_pad($matches[6], 2, '0', STR_PAD_LEFT);
            $backupDate = "$year-{$monthNames[$month]}-$day $hour:$min:$sec";
        }
        
        // Extract backup size from BODY (not subject) - this is critical for Client backups
        // Pattern: "Backup size: 2,3 GB" or "Backup size\n2,3 GB" or "Total size: 108,1 GB"
        // Handle commas as decimal separators and values on new lines
        // Use bodyPlain for better parsing (no HTML tags)
        // Priority: "Backup size" > "Total size" > "Size"
        if (preg_match('/Backup\s+size[:\s]+([\d,\.]+\s*(?:GB|MB|KB|TB|B))/i', $bodyPlain, $matches)) {
            $backupSize = trim($matches[1]);
            logMessage("  ✓ Extracted backup size: '$backupSize'", $logFile);
        } elseif (preg_match('/Backup\s+size[:\s]*\n?\s*([\d,\.]+\s*(?:GB|MB|KB|TB|B))/i', $bodyPlain, $matches)) {
            $backupSize = trim($matches[1]);
            logMessage("  ✓ Extracted backup size (with newline): '$backupSize'", $logFile);
        } elseif (preg_match('/Total\s+size[:\s]+([\d,\.]+\s*(?:GB|MB|KB|TB|B))/i', $bodyPlain, $matches)) {
            $backupSize = trim($matches[1]);
            logMessage("  ✓ Extracted total size: '$backupSize'", $logFile);
        } elseif (preg_match('/Total\s+size[:\s]*\n?\s*([\d,\.]+\s*(?:GB|MB|KB|TB|B))/i', $bodyPlain, $matches)) {
            $backupSize = trim($matches[1]);
            logMessage("  ✓ Extracted total size (with newline): '$backupSize'", $logFile);
        } elseif (preg_match('/Size[:\s]+([\d,\.]+\s*(?:GB|MB|KB|TB|B))/i', $bodyPlain, $matches)) {
            $backupSize = trim($matches[1]);
            logMessage("  ✓ Extracted size: '$backupSize'", $logFile);
        }
        
        // Keep backup size as-is from email (preserve comma or dot as shown in original email)
        // No conversion - store actual value as it appears: "748,4 MB" stays "748,4 MB"
        
        // Ensure status is properly formatted before classification
        // Limit status length and convert Unknown to UNKNOWN
        $statusForClassification = substr($status, 0, 50);
        if (empty($statusForClassification) || $statusForClassification === 'Unknown') {
            $statusForClassification = 'UNKNOWN';
        }
        
        logMessage("  Extracted - Status: '$status' (formatted: '$statusForClassification'), DeviceType: '$deviceType', Date: '$backupDate', Size: '$backupSize'", $logFile);
        
        // Check if this is a backup email
        // Criteria: Must have status (not Unknown) and device type, OR have "Agent Backup job" or "Veeam Agent" in body
        $hasBackupJobPattern = preg_match('/Agent\s+Backup\s+job|Backup\s+job|Veeam\s+Agent/i', $bodyPlain);
        
        // If we have backup job pattern but status is still Unknown, try harder to extract status
        if ($hasBackupJobPattern && ($status === 'Unknown' || empty($status))) {
            // Look for status in a more specific pattern: after "Agent Backup job" section
            if (preg_match('/Agent\s+Backup\s+job[^\n]*(Success|Warning|Error|Failed)/i', $bodyPlain, $statusMatches)) {
                $status = ucfirst(strtolower($statusMatches[1]));
                if ($status === 'Failed') {
                    $status = 'Error';
                }
                logMessage("  ✓ Re-extracted status from Agent Backup job section: '$status'", $logFile);
            }
            // Also try looking for status in the right side of banner or summary
            elseif (preg_match('/\b(Success|Warning|Error|Failed)\b/i', $bodyPlain, $statusMatches)) {
                $statusWord = strtolower($statusMatches[1]);
                if ($statusWord === 'success' || $statusWord === 'warning' || $statusWord === 'error' || $statusWord === 'failed') {
                    $status = ucfirst($statusWord);
                    if ($status === 'Failed') {
                        $status = 'Error';
                    }
                    logMessage("  ✓ Re-extracted status from body (fallback): '$status'", $logFile);
                }
            }
        }
        
        // Classify as backup email if:
        // 1. Has valid status (not Unknown/UNKNOWN) AND device type, OR
        // 2. Has "Agent Backup job" pattern AND device type (even if status is Unknown - will be saved as UNKNOWN)
        $isBackupEmail = (!empty($status) && $status !== 'Unknown' && $status !== 'UNKNOWN' && !empty($deviceType)) || 
                         ($hasBackupJobPattern && !empty($deviceType));
        
        logMessage("  Email classification - isBackupEmail: " . ($isBackupEmail ? "YES" : "NO"), $logFile);
        
        // NEW FLOW (per user requirement):
        // 1. Email arrives in mailbox
        // 2. ERP fetches it via POP
        // 3. ERP stores the email in database (ALWAYS - regardless of classification) → undefined_mails
        // 4. ERP removes (POP deletes) it from the mailbox
        // 5. ERP checks: No matching backup job → already in undefined_mails (done)
        // 6. If matching backup job → also save to backup_logs
        
        // STEP 1: Store ALL emails in undefined_mails FIRST (always, regardless of classification)
        $statusForDb = substr($status, 0, 20); // Limit status to 20 chars
        if (empty($statusForDb) || $statusForDb === 'Unknown') {
            $statusForDb = 'UNKNOWN';
        }
        
        // Check for duplicate in undefined_mails
        $isUndefinedDuplicate = false;
        if (!empty($backupDate)) {
            $checkUndefinedStmt = $conn->prepare("SELECT id FROM undefined_mails WHERE source = ? AND email_subject = ? AND backup_date = ? LIMIT 1");
            if ($checkUndefinedStmt) {
                $checkUndefinedStmt->bind_param("sss", $source, $subject, $backupDate);
                $checkUndefinedStmt->execute();
                $existingUndefined = fetchSingleRow($checkUndefinedStmt);
                $checkUndefinedStmt->close();
                
                if ($existingUndefined) {
                    $isUndefinedDuplicate = true;
                    logMessage("  ℹ️ Already exists in undefined_mails (skipping duplicate)", $logFile);
                }
            }
        } else {
            // Fallback: If no date, check by source + subject + created within last 24 hours
            $checkUndefinedStmt = $conn->prepare("SELECT id FROM undefined_mails WHERE source = ? AND email_subject = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) LIMIT 1");
            if ($checkUndefinedStmt) {
                $checkUndefinedStmt->bind_param("ss", $source, $subject);
                $checkUndefinedStmt->execute();
                $existingUndefined = fetchSingleRow($checkUndefinedStmt);
                $checkUndefinedStmt->close();
                
                if ($existingUndefined) {
                    $isUndefinedDuplicate = true;
                    logMessage("  ℹ️ Already exists in undefined_mails (skipping duplicate)", $logFile);
                }
            }
        }
        
        // Insert into undefined_mails if not duplicate
        if (!$isUndefinedDuplicate) {
            $insertUndefinedStmt = $conn->prepare("INSERT INTO undefined_mails (source, device_type, status, backup_date, backup_size, email_subject, email_body, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            if ($insertUndefinedStmt) {
                $insertUndefinedStmt->bind_param("sssssss", $source, $deviceType, $statusForDb, $backupDate, $backupSize, $subject, $body);
                if ($insertUndefinedStmt->execute()) {
                    $savedToUndefined++;
                    $emailProcessedSuccessfully = true;
                    logMessage("  ✓ Saved to undefined_mails (step 1: store email in database)", $logFile);
                    
                    // Send error notification if status is error or failed
                    logMessage("  Checking status for notification: status='$status', statusForDb='$statusForDb'", $logFile);
                    if (strtolower($statusForDb) === 'error' || strtolower($statusForDb) === 'failed') {
                        logMessage("  Status is ERROR or FAILED - calling sendErrorNotification()", $logFile);
                        $notificationResult = sendErrorNotification($subject, $body, $source, $statusForDb, $logFile);
                        if ($notificationResult['success']) {
                            logMessage("  ✓ Error notification sent successfully", $logFile);
                        } else {
                            logMessage("  ⚠ Failed to send error notification: " . $notificationResult['message'], $logFile);
                        }
                    } else {
                        logMessage("  Status is not ERROR or FAILED (statusForDb='$statusForDb') - notification skipped", $logFile);
                    }
                } else {
                    logMessage("  ✗ Failed to save to undefined_mails: " . $conn->error, $logFile);
                    $errorCount++;
                    // Delete email even if save failed (POP behavior - we fetched it, so delete it)
                    logMessage("  ⚠ Deleting email #$emailNo despite save failure (POP behavior)", $logFile);
                    @imap_delete($inbox, $emailNo);
                    continue; // Skip this email if we can't save it
                }
                $insertUndefinedStmt->close();
            } else {
                logMessage("  ✗ Failed to prepare undefined_mails INSERT: " . $conn->error, $logFile);
                $errorCount++;
                // Delete email even if prepare failed (POP behavior - we fetched it, so delete it)
                logMessage("  ⚠ Deleting email #$emailNo despite prepare failure (POP behavior)", $logFile);
                @imap_delete($inbox, $emailNo);
                continue; // Skip this email if we can't prepare statement
            }
        } else {
            // Duplicate - mark as processed so it can be deleted
            $emailProcessedSuccessfully = true;
            $duplicateCount++;
        }
        
        // STEP 2: Delete email from mailbox (POP behavior) - AFTER storing in database
        // ALWAYS delete email after processing (even if duplicate or error) - POP behavior
        if ($emailProcessedSuccessfully || $isUndefinedDuplicate) {
            // Mark email for deletion after successful storage in database
            if (@imap_delete($inbox, $emailNo)) {
                logMessage("  ✓ Email #$emailNo marked for deletion (POP behavior: stored in database, email will be removed)", $logFile);
            } else {
                $deleteError = imap_last_error();
                logMessage("  ✗ Failed to mark email #$emailNo for deletion: " . ($deleteError ? $deleteError : 'Unknown error'), $logFile);
            }
        } else {
            // Even if processing failed, try to delete the email (POP behavior - fetch and delete)
            logMessage("  ⚠ Processing failed, but attempting to delete email #$emailNo anyway (POP behavior)", $logFile);
            if (@imap_delete($inbox, $emailNo)) {
                logMessage("  ✓ Email #$emailNo marked for deletion despite processing failure", $logFile);
            }
        }
        
        // STEP 3: Check if backup job exists (only if this is a backup email)
        if ($isBackupEmail && $emailProcessedSuccessfully) {
            // Find backup job - use case-insensitive matching and trim whitespace
            // Try exact match first
            $deviceTypeTrimmed = trim($deviceType);
            $sourceTrimmed = trim($source);
            $jobStmt = $conn->prepare("SELECT id FROM backup_jobs WHERE TRIM(device_type) = ? AND TRIM(backup_source) = ? LIMIT 1");
            if (!$jobStmt) {
                logMessage("  ✗ Failed to prepare job lookup: " . $conn->error, $logFile);
                // Don't increment error count - email already saved to undefined_mails
                continue;
            }
            $jobStmt->bind_param("ss", $deviceTypeTrimmed, $sourceTrimmed);
            $jobStmt->execute();
            $job = fetchSingleRow($jobStmt);
            $jobStmt->close();
            
            // If not found, try case-insensitive match
            if (!$job) {
                $jobStmt = $conn->prepare("SELECT id FROM backup_jobs WHERE LOWER(TRIM(device_type)) = LOWER(?) AND LOWER(TRIM(backup_source)) = LOWER(?) LIMIT 1");
                if ($jobStmt) {
                    $jobStmt->bind_param("ss", $deviceTypeTrimmed, $sourceTrimmed);
                    $jobStmt->execute();
                    $job = fetchSingleRow($jobStmt);
                    $jobStmt->close();
                    if ($job) {
                        logMessage("  ✓ Found backup job using case-insensitive match", $logFile);
                    }
                }
            }
            
            if ($job) {
                $backupJobId = $job['id'];
                logMessage("  ✓ Found backup job ID: $backupJobId (step 3: matching backup job found)", $logFile);
                
                // Check for duplicate in backup_logs
                $isBackupLogDuplicate = false;
                if (!empty($backupDate)) {
                    $checkStmt = $conn->prepare("SELECT id FROM backup_logs WHERE source = ? AND email_subject = ? AND backup_date = ? AND status = ? AND backup_size = ? LIMIT 1");
                    if ($checkStmt) {
                        $checkStmt->bind_param("sssss", $source, $subject, $backupDate, $status, $backupSize);
                        $checkStmt->execute();
                        $existing = fetchSingleRow($checkStmt);
                        $checkStmt->close();
                        
                        if ($existing) {
                            $isBackupLogDuplicate = true;
                            logMessage("  ⚠ Skipping duplicate backup log entry (matched by: source+subject+date+status+size)", $logFile);
                        }
                    }
                } else {
                    // Fallback check: Check by source + subject + created within last 24 hours
                    $checkFallbackStmt = $conn->prepare("SELECT id FROM backup_logs WHERE source = ? AND email_subject = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) LIMIT 1");
                    if ($checkFallbackStmt) {
                        $checkFallbackStmt->bind_param("ss", $source, $subject);
                        $checkFallbackStmt->execute();
                        $existingFallback = fetchSingleRow($checkFallbackStmt);
                        $checkFallbackStmt->close();
                        
                        if ($existingFallback) {
                            $isBackupLogDuplicate = true;
                            logMessage("  ⚠ Skipping duplicate backup log entry (matched by: source+subject+created_recent)", $logFile);
                        }
                    }
                }
                
                // Insert into backup_logs if not duplicate
                if (!$isBackupLogDuplicate) {
                    $insertStmt = $conn->prepare("INSERT INTO backup_logs (backup_job_id, source, status, backup_date, backup_size, email_subject, email_body, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    if ($insertStmt) {
                        $insertStmt->bind_param("issssss", $backupJobId, $source, $statusForDb, $backupDate, $backupSize, $subject, $body);
                        
                        if ($insertStmt->execute()) {
                            $savedToLogs++;
                            logMessage("  ✓ Saved to backup_logs (step 4: matching backup job - also saved to backup_logs)", $logFile);
                            
                            // Delete from undefined_mails since we found a matching job and saved to backup_logs
                            // This prevents the email from showing in undefined_mails when a job exists
                            $deleteUndefinedStmt = $conn->prepare("DELETE FROM undefined_mails WHERE source = ? AND email_subject = ? AND backup_date = ? LIMIT 1");
                            if ($deleteUndefinedStmt) {
                                if (!empty($backupDate)) {
                                    $deleteUndefinedStmt->bind_param("sss", $source, $subject, $backupDate);
                                } else {
                                    // Fallback: delete by source + subject + created within last hour
                                    $deleteUndefinedStmt = $conn->prepare("DELETE FROM undefined_mails WHERE source = ? AND email_subject = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) LIMIT 1");
                                    if ($deleteUndefinedStmt) {
                                        $deleteUndefinedStmt->bind_param("ss", $source, $subject);
                                    }
                                }
                                if ($deleteUndefinedStmt && $deleteUndefinedStmt->execute()) {
                                    $deletedCount = $deleteUndefinedStmt->affected_rows;
                                    if ($deletedCount > 0) {
                                        logMessage("  ✓ Removed from undefined_mails (matching job found - email now only in backup_logs)", $logFile);
                                    } else {
                                        logMessage("  ⚠ Could not find matching entry in undefined_mails to delete", $logFile);
                                    }
                                    $deleteUndefinedStmt->close();
                                } else {
                                    if ($deleteUndefinedStmt) {
                                        logMessage("  ⚠ Failed to delete from undefined_mails: " . $conn->error, $logFile);
                                        $deleteUndefinedStmt->close();
                                    }
                                }
                            }
                            
                            // Update backup job
                            $updateStmt = $conn->prepare("UPDATE backup_jobs SET latest_status = ?, latest_backup_at = ?, latest_backup_size = ?, last_email_received_at = ? WHERE id = ?");
                            if ($updateStmt) {
                                $updateStmt->bind_param("ssssi", $status, $backupDate, $backupSize, $backupDate, $backupJobId);
                                $updateStmt->execute();
                                $updateStmt->close();
                                logMessage("  ✓ Backup job updated", $logFile);
                            }
                        } else {
                            logMessage("  ✗ Failed to save to backup_logs: " . $conn->error, $logFile);
                            // Don't increment error count - email already saved to undefined_mails
                        }
                        $insertStmt->close();
                    } else {
                        logMessage("  ✗ Failed to prepare backup_logs INSERT: " . $conn->error, $logFile);
                        // Don't increment error count - email already saved to undefined_mails
                    }
                } else {
                    $duplicateCount++;
                }
            } else {
                logMessage("  ⚠ No backup job found for device_type='$deviceType' and backup_source='$source' (step 3: no matching job - email already in undefined_mails)", $logFile);
            }
        } else if (!$isBackupEmail) {
            logMessage("  ℹ️ Not a backup email - stored in undefined_mails only (step 3: no backup job check needed)", $logFile);
        }
        
        // Email processing complete (deletion already handled above)
        
        $processedCount++;
        
    } catch (Exception $e) {
        logMessage("  ✗ Error processing email #$emailNo: " . $e->getMessage(), $logFile);
        $errorCount++;
        // Even on error, try to delete the email (POP behavior)
        logMessage("  ⚠ Attempting to delete email #$emailNo despite error (POP behavior)", $logFile);
        @imap_delete($inbox, $emailNo);
    }
}

// Expunge deleted emails (POP behavior: permanently remove deleted emails)
imap_expunge($inbox);
imap_close($inbox);

// Summary
logMessage("========================================", $logFile);
logMessage("Email Parsing Completed", $logFile);
logMessage("Total emails processed (SEEN + UNSEEN, all in mailbox): $processedCount", $logFile);
logMessage("Saved to undefined_mails (step 1: all emails stored in database): $savedToUndefined", $logFile);
logMessage("Saved to backup_logs (step 4: matching backup jobs found): $savedToLogs", $logFile);
logMessage("Duplicates skipped: $duplicateCount", $logFile);
logMessage("Errors: $errorCount", $logFile);
logMessage("All emails deleted (POP behavior): " . ($savedToUndefined + $duplicateCount) . " (SEEN + UNSEEN, deleted after storage in database)", $logFile);
logMessage("Backup source: $source (CLIENT mailbox)", $logFile);
logMessage("========================================", $logFile);

exit(0);
