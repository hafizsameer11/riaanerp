<?php
/**
 * VEEAM Email Parser - Processes ALL emails (SEEN + UNSEEN) and deletes after storage (POP behavior)
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
 * 1. Connects to VEEAM email account
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
 * Usage: php7.4 read_veeam_emails.php
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
$source = 'VEEAM';
$logFile = $scriptDir . '/logs/veeam_log.txt';

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
        
        // Get email body - try multiple methods
        $rawBody = imap_body($inbox, $emailNo);
        if ($rawBody === false || empty(trim($rawBody))) {
            $rawBody = imap_fetchbody($inbox, $emailNo, 1);
        }
        
        // Parse MIME multipart message and extract clean body content
        $body = '';
        
        // Check if this is a MIME multipart message (contains boundary markers)
        if (preg_match('/--[a-zA-Z0-9]+/', $rawBody)) {
            logMessage("  ✓ Detected MIME multipart message", $logFile);
            
            // Extract boundary from message
            $boundary = '';
            if (preg_match('/boundary="?([^"\s]+)"?/i', $rawBody, $matches)) {
                $boundary = $matches[1];
            } elseif (preg_match('/--([a-zA-Z0-9]+)/', $rawBody, $matches)) {
                $boundary = $matches[1];
            }
            
            if ($boundary) {
                logMessage("  ✓ Found boundary: $boundary", $logFile);
                
                // Split by boundary
                $parts = preg_split('/--' . preg_quote($boundary, '/') . '(?:--)?/', $rawBody);
                
                foreach ($parts as $part) {
                    $part = trim($part);
                    if (empty($part)) continue;
                    
                    // Check if this part has Content-Type header
                    if (preg_match('/Content-Type:\s*([^;\r\n]+)/i', $part, $typeMatches)) {
                        $contentType = trim($typeMatches[1]);
                        
                        // Extract the actual content (after headers)
                        $content = preg_replace('/^.*?\r?\n\r?\n/s', '', $part, 1);
                        if (empty($content)) {
                            $content = preg_replace('/^.*?\n\n/s', '', $part, 1);
                        }
                        $content = trim($content);
                        
                        // Check Content-Transfer-Encoding
                        $encoding = '';
                        if (preg_match('/Content-Transfer-Encoding:\s*([^\r\n]+)/i', $part, $encMatches)) {
                            $encoding = strtolower(trim($encMatches[1]));
                        }
                        
                        // Decode based on encoding
                        if ($encoding === 'base64' || $encoding === 'quoted-printable') {
                            if ($encoding === 'base64') {
                                $decoded = @base64_decode($content, true);
                                if ($decoded !== false && strlen($decoded) > 10) {
                                    $content = $decoded;
                                    logMessage("  ✓ Decoded base64 part: $contentType", $logFile);
                                }
                            } else {
                                $content = quoted_printable_decode($content);
                            }
                        }
                        
                        // Convert encoding to UTF-8
                        $content = mb_convert_encoding($content, 'UTF-8', 'auto');
                        
                        // Remove UTF-8 BOM and encoding artifacts
                        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
                        $content = preg_replace('/\xEF\xBB\xBF/', '', $content);
                        $content = preg_replace('/\xC2\xA0/', ' ', $content);
                        $content = preg_replace('/\x{00A0}/u', ' ', $content);
                        $content = preg_replace('/\xC2(?![\x80-\xBF])/', '', $content);
                        $content = preg_replace('/Â(?![\x80-\xBF])/u', '', $content);
                        $content = preg_replace('/Â[\s\.,;:!?]/u', '', $content);
                        
                        // Prefer HTML over plain text, but use plain text if HTML is not available
                        if (stripos($contentType, 'text/html') !== false) {
                            $body = $content;
                            logMessage("  ✓ Using HTML part", $logFile);
                            break; // HTML is preferred, use it
                        } elseif (stripos($contentType, 'text/plain') !== false && empty($body)) {
                            $body = $content;
                            logMessage("  ✓ Using plain text part", $logFile);
                        }
                    } else {
                        // No Content-Type header, might be the actual content
                        // Try to decode if it looks encoded
                        $content = trim($part);
                        
                        // Remove any remaining headers at the start
                        $content = preg_replace('/^[A-Za-z-]+:\s*[^\r\n]+\r?\n/m', '', $content);
                        $content = preg_replace('/^\r?\n+/', '', $content);
                        
                        // Try base64 decode
                        if (preg_match('/^[A-Za-z0-9+\/=\s]+$/', substr($content, 0, 100)) && strlen($content) > 50) {
                            $decoded = @base64_decode($content, true);
                            if ($decoded !== false && strlen($decoded) > 10) {
                                $content = $decoded;
                                logMessage("  ✓ Decoded base64 content", $logFile);
                            }
                        }
                        
                        // Decode quoted-printable
                        $content = quoted_printable_decode($content);
                        
                        // Convert encoding
                        $content = mb_convert_encoding($content, 'UTF-8', 'auto');
                        
                        // Remove UTF-8 BOM and encoding artifacts
                        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
                        $content = preg_replace('/\xEF\xBB\xBF/', '', $content);
                        $content = preg_replace('/\xC2\xA0/', ' ', $content);
                        $content = preg_replace('/\x{00A0}/u', ' ', $content);
                        $content = preg_replace('/\xC2(?![\x80-\xBF])/', '', $content);
                        $content = preg_replace('/Â(?![\x80-\xBF])/u', '', $content);
                        $content = preg_replace('/Â[\s\.,;:!?]/u', '', $content);
                        
                        if (!empty(trim($content)) && strlen($content) > 50) {
                            $body = $content;
                            logMessage("  ✓ Using extracted content", $logFile);
                        }
                    }
                }
            }
        }
        
        // If no body extracted from multipart, try direct extraction
        if (empty($body)) {
            logMessage("  ℹ Not multipart or extraction failed, trying direct method", $logFile);
            $body = $rawBody;
            
            // Remove MIME boundary markers if present
            $body = preg_replace('/--[a-zA-Z0-9]+\r?\n?/m', '', $body);
            $body = preg_replace('/Content-Type:\s*[^\r\n]+\r?\n?/mi', '', $body);
            $body = preg_replace('/Content-Transfer-Encoding:\s*[^\r\n]+\r?\n?/mi', '', $body);
            $body = preg_replace('/charset="?[^"\r\n]+"?\r?\n?/mi', '', $body);
            $body = preg_replace('/^\r?\n+/m', '', $body);
            
            // Try base64 decode if it looks encoded
            $bodyTrimmed = trim($body);
            if (strlen($bodyTrimmed) > 50 && preg_match('/^[A-Za-z0-9+\/=\s]+$/', substr($bodyTrimmed, 0, 200))) {
                $decoded = @base64_decode($bodyTrimmed, true);
                if ($decoded !== false && strlen($decoded) > 10) {
                    $body = $decoded;
                    logMessage("  ✓ Decoded base64 email body", $logFile);
                }
            }
            
            // Decode quoted-printable
        $body = quoted_printable_decode($body);
            
            // Convert encoding to UTF-8
            $body = mb_convert_encoding($body, 'UTF-8', 'auto');
            
            // Remove UTF-8 BOM and encoding artifacts
            $body = preg_replace('/^\xEF\xBB\xBF/', '', $body);
            $body = preg_replace('/\xEF\xBB\xBF/', '', $body);
            $body = preg_replace('/\xC2\xA0/', ' ', $body);
            $body = preg_replace('/\x{00A0}/u', ' ', $body);
            $body = preg_replace('/\xC2(?![\x80-\xBF])/', '', $body);
            $body = preg_replace('/Â(?![\x80-\xBF])/u', '', $body);
            $body = preg_replace('/Â[\s\.,;:!?]/u', '', $body);
        }
        
        // Clean up: Remove any remaining boundary markers or headers
        $body = preg_replace('/^--[a-zA-Z0-9]+.*?--$/ms', '', $body);
        $body = preg_replace('/^Content-Type:\s*[^\r\n]+$/mi', '', $body);
        $body = preg_replace('/^Content-Transfer-Encoding:\s*[^\r\n]+$/mi', '', $body);
        $body = preg_replace('/^[A-Za-z-]+:\s*[^\r\n]+$/m', '', $body);
        $body = preg_replace('/^\r?\n+/m', '', $body);
        $body = preg_replace('/\r?\n+$/m', '', $body);
        $body = trim($body);
        
        // Remove UTF-8 BOM (Byte Order Mark) and encoding artifacts
        // UTF-8 BOM is \xEF\xBB\xBF which can appear as "Â" when not properly handled
        $body = preg_replace('/^\xEF\xBB\xBF/', '', $body); // Remove BOM at start
        $body = preg_replace('/\xEF\xBB\xBF/', '', $body); // Remove BOM anywhere
        // Remove standalone "Â" characters that are encoding artifacts (UTF-8 0xC2 0x80-0xBF range issues)
        $body = preg_replace('/\xC2\xA0/', ' ', $body); // Replace non-breaking space
        $body = preg_replace('/\x{00A0}/u', ' ', $body); // Replace non-breaking space (Unicode)
        // Remove standalone "Â" character (U+00C2) when it's not part of a valid UTF-8 sequence
        $body = preg_replace('/\xC2(?![\x80-\xBF])/', '', $body); // Remove invalid C2 sequences
        $body = preg_replace('/Â(?![\x80-\xBF])/u', '', $body); // Remove standalone Â in UTF-8 mode
        // More aggressive: remove Â followed by space or punctuation (common artifact)
        $body = preg_replace('/Â[\s\.,;:!?]/u', '', $body);
        
        // Remove HTML tags for better parsing (but keep original decoded body for storage)
        $bodyPlain = strip_tags($body);
        
        // Also decode HTML entities in bodyPlain for better text extraction
        $bodyPlain = html_entity_decode($bodyPlain, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Replace HTML line breaks with newlines for better pattern matching
        $bodyPlain = preg_replace('/<br\s*\/?>/i', "\n", $bodyPlain);
        $bodyPlain = preg_replace('/<\/?p[^>]*>/i', "\n", $bodyPlain);
        $bodyPlain = preg_replace('/<\/?div[^>]*>/i', "\n", $bodyPlain);
        
        // Remove UTF-8 BOM and encoding artifacts from bodyPlain as well
        $bodyPlain = preg_replace('/^\xEF\xBB\xBF/', '', $bodyPlain);
        $bodyPlain = preg_replace('/\xEF\xBB\xBF/', '', $bodyPlain);
        $bodyPlain = preg_replace('/\xC2\xA0/', ' ', $bodyPlain);
        $bodyPlain = preg_replace('/\x{00A0}/u', ' ', $bodyPlain);
        $bodyPlain = preg_replace('/\xC2(?![\x80-\xBF])/', '', $bodyPlain);
        $bodyPlain = preg_replace('/Â(?![\x80-\xBF])/u', '', $bodyPlain);
        $bodyPlain = preg_replace('/Â[\s\.,;:!?]/u', '', $bodyPlain);
        
        // Log body preview for debugging
        logMessage("  Body preview (first 300 chars): " . substr($bodyPlain, 0, 300), $logFile);
        
        $emailType = $isUnseen ? "UNSEEN" : "SEEN";
        logMessage("Processing email #$emailNo ($emailType): " . substr($subject, 0, 50) . "...", $logFile);
        logMessage("  Email details - Subject: '$subject'", $logFile);
        
        // Extract backup information from subject and body
        $status = 'Unknown';
        $deviceType = '';
        $backupDate = null;
        $backupSize = '';
        
        // VEEAM Email Subject Format: "[Success] DeviceName" or "Backup job: DeviceName"
        // Try to extract status from subject
        if (preg_match('/\[(Success|Warning|Error|Failed)\]/i', $subject, $matches)) {
            $status = ucfirst(strtolower($matches[1]));
            if ($status === 'Failed') {
                $status = 'Error';
            }
        }
        
        // Try to extract device name from subject or body
        // Pattern: [Status] DeviceName (with optional trailing text) or Backup job: DeviceName
        // Example: [Success] CenturionDay (1 objects) -> CenturionDay
        if (preg_match('/\[(?:Success|Warning|Error|Failed)\]\s*([^\s\(]+)/i', $subject, $matches)) {
            $deviceType = trim($matches[1]);
        } elseif (preg_match('/\[(?:Success|Warning|Error|Failed)\]\s*(.+?)(?:\s*\(|$)/i', $subject, $matches)) {
            $deviceType = trim($matches[1]);
        } elseif (preg_match('/Backup\s+job[:\s]+([^\s\(]+)/i', $bodyPlain, $matches)) {
            $deviceType = trim($matches[1]);
        } elseif (preg_match('/Backup\s+job[:\s]+(.+?)(?:\s*\(|\s*Success|\s*Warning|\s*Error|$)/i', $bodyPlain, $matches)) {
            $deviceType = trim($matches[1]);
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
        
        // Extract backup size from BODY (not subject) - this is critical for Veeam
        // Try extracting from both HTML body and plain text body
        // Pattern: "Backup size: 748,4 MB" or "Backup size\n748,4 MB" or "Total size: 500 GB"
        // Handle commas as decimal separators and values on new lines
        // Use bodyPlain (already decoded and cleaned) for extraction
        
        logMessage("  Attempting to extract backup size from email body...", $logFile);
        
        // Pattern 1: "Backup size: 748,4 MB" or "Backup size 748,4 MB" (with or without colon, case insensitive)
        if (preg_match('/Backup\s+size[:\s]+([\d,\.]+\s*(?:GB|MB|KB|TB|B))/i', $bodyPlain, $matches)) {
            $backupSize = trim($matches[1]);
            logMessage("  ✓ Extracted backup size (pattern 1): '$backupSize'", $logFile);
        }
        // Pattern 2: "Backup size\n748,4 MB" (with newline or line break)
        elseif (preg_match('/Backup\s+size[:\s]*[\r\n]+\s*([\d,\.]+\s*(?:GB|MB|KB|TB|B))/i', $bodyPlain, $matches)) {
            $backupSize = trim($matches[1]);
            logMessage("  ✓ Extracted backup size (pattern 2 - with newline): '$backupSize'", $logFile);
        }
        // Pattern 3: "Total size: 500 GB"
        elseif (preg_match('/Total\s+size[:\s]+([\d,\.]+\s*(?:GB|MB|KB|TB|B))/i', $bodyPlain, $matches)) {
            $backupSize = trim($matches[1]);
            logMessage("  ✓ Extracted total size: '$backupSize'", $logFile);
        }
        // Pattern 4: "Total size\n500 GB" (with newline)
        elseif (preg_match('/Total\s+size[:\s]*[\r\n]+\s*([\d,\.]+\s*(?:GB|MB|KB|TB|B))/i', $bodyPlain, $matches)) {
            $backupSize = trim($matches[1]);
            logMessage("  ✓ Extracted total size (with newline): '$backupSize'", $logFile);
        }
        // Pattern 5: Try from HTML body if plain text didn't work (before strip_tags)
        elseif (preg_match('/Backup\s+size[:\s]+([\d,\.]+\s*(?:GB|MB|KB|TB|B))/i', $body, $matches)) {
            $backupSize = trim(strip_tags($matches[1]));
            logMessage("  ✓ Extracted backup size from HTML: '$backupSize'", $logFile);
        }
        // Pattern 6: Look for size in HTML with <br> tags or other HTML formatting
        elseif (preg_match('/Backup\s+size[:\s]*(?:<[^>]+>)*\s*([\d,\.]+\s*(?:GB|MB|KB|TB|B))/i', $body, $matches)) {
            $backupSize = trim(strip_tags($matches[1]));
            logMessage("  ✓ Extracted backup size from HTML (with tags): '$backupSize'", $logFile);
        }
        // Pattern 7: Generic "Size: X GB/MB" (but only if "Backup size" not found)
        elseif (preg_match('/\bSize[:\s]+([\d,\.]+\s*(?:GB|MB|KB|TB|B))/i', $bodyPlain, $matches)) {
            $backupSize = trim($matches[1]);
            logMessage("  ✓ Extracted size (generic): '$backupSize'", $logFile);
        }
        
        // If still not found, try more aggressive patterns
        if (empty($backupSize)) {
            // Look for any pattern like "X,XX MB" or "X.XX GB" near "Backup size" or "size" (case insensitive, flexible spacing)
            if (preg_match('/(?:Backup\s+size|size)[:\s]*[\r\n\s]*([\d,\.]+\s*(?:GB|MB|KB|TB|B))/i', $bodyPlain, $matches)) {
                $backupSize = trim($matches[1]);
                logMessage("  ✓ Extracted backup size (aggressive pattern): '$backupSize'", $logFile);
            }
            // Last resort: Look for any number followed by GB/MB/TB/KB/B after "Backup" or "size"
            elseif (preg_match('/(?:Backup|size).*?([\d,\.]+\s*(?:GB|MB|KB|TB|B))/i', $bodyPlain, $matches)) {
                $backupSize = trim($matches[1]);
                logMessage("  ✓ Extracted backup size (last resort pattern): '$backupSize'", $logFile);
            }
        }
        
        // Keep backup size as-is from email (preserve comma or dot as shown in original email)
        // No conversion - store actual value as it appears: "748,4 MB" stays "748,4 MB"
        
        if (empty($backupSize)) {
            logMessage("  ⚠ Backup size not found in email body - checking body content...", $logFile);
            // Log a sample of the body to help debug
            $sample = substr($bodyPlain, 0, 500);
            logMessage("  Body sample for debugging: " . $sample, $logFile);
        } else {
            logMessage("  ✓ Backup size successfully extracted: '$backupSize'", $logFile);
        }
        
        logMessage("  Extracted - Status: '$status', DeviceType: '$deviceType', Date: '$backupDate', Size: '$backupSize'", $logFile);
        
        // Check if this is a backup email (has status and device type)
        $isBackupEmail = !empty($status) && $status !== 'Unknown' && !empty($deviceType);
        
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
            // Log what we're about to insert
            logMessage("  Preparing to insert - backup_size: '$backupSize'", $logFile);
            
                                $insertUndefinedStmt = $conn->prepare("INSERT INTO undefined_mails (source, device_type, status, backup_date, backup_size, email_subject, email_body, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                                if ($insertUndefinedStmt) {
                $insertUndefinedStmt->bind_param("sssssss", $source, $deviceType, $statusForDb, $backupDate, $backupSize, $subject, $body);
                                    if ($insertUndefinedStmt->execute()) {
                                        $savedToUndefined++;
                    $emailProcessedSuccessfully = true;
                    logMessage("  ✓ Saved to undefined_mails (step 1: store email in database) - backup_size: '$backupSize'", $logFile);
                    
                    // Send error notification if status is error or failed
                    if (strtolower($statusForDb) === 'error' || strtolower($statusForDb) === 'failed') {
                        $notificationResult = sendErrorNotification($subject, $body, $source, $statusForDb, $logFile);
                        if ($notificationResult['success']) {
                            logMessage("  ✓ Error notification sent successfully", $logFile);
                            } else {
                            logMessage("  ⚠ Failed to send error notification: " . $notificationResult['message'], $logFile);
                        }
                    }
                } else {
                    logMessage("  ✗ Failed to save to undefined_mails: " . $conn->error, $logFile);
                    $errorCount++;
                    continue; // Skip this email if we can't save it
                }
                $insertUndefinedStmt->close();
            } else {
                logMessage("  ✗ Failed to prepare undefined_mails INSERT: " . $conn->error, $logFile);
                $errorCount++;
                continue; // Skip this email if we can't prepare statement
            }
        } else {
            // Duplicate - mark as processed so it can be deleted
            $emailProcessedSuccessfully = true;
            $duplicateCount++;
        }
        
        // STEP 2: Delete email from mailbox (POP behavior) - AFTER storing in database
        if ($emailProcessedSuccessfully) {
            // Mark email for deletion after successful storage in database
            if (imap_delete($inbox, $emailNo)) {
                logMessage("  ✓ Email #$emailNo marked for deletion (POP behavior: stored in database, email will be removed)", $logFile);
            } else {
                logMessage("  ✗ Failed to mark email #$emailNo for deletion: " . imap_last_error(), $logFile);
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
                    logMessage("  Preparing to insert into backup_logs - backup_size: '$backupSize'", $logFile);
                    
                    $insertStmt = $conn->prepare("INSERT INTO backup_logs (backup_job_id, source, status, backup_date, backup_size, email_subject, email_body, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    if ($insertStmt) {
                        $insertStmt->bind_param("issssss", $backupJobId, $source, $statusForDb, $backupDate, $backupSize, $subject, $body);
                        
                        if ($insertStmt->execute()) {
                            $savedToLogs++;
                            logMessage("  ✓ Saved to backup_logs (step 4: matching backup job - also saved to backup_logs) - backup_size: '$backupSize'", $logFile);
                            
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
                            
                            // Send error notification if status is error or failed (only if not already sent above)
                            if ((strtolower($statusForDb) === 'error' || strtolower($statusForDb) === 'failed') && $isUndefinedDuplicate) {
                                // Only send if it was a duplicate in undefined_mails (meaning notification wasn't sent above)
                                $notificationResult = sendErrorNotification($subject, $body, $source, $statusForDb, $logFile);
                                if ($notificationResult['success']) {
                                    logMessage("  ✓ Error notification sent successfully", $logFile);
                                } else {
                                    logMessage("  ⚠ Failed to send error notification: " . $notificationResult['message'], $logFile);
                                }
                            }
                            
                            // Update backup job
                            $updateStmt = $conn->prepare("UPDATE backup_jobs SET latest_status = ?, latest_backup_at = ?, latest_backup_size = ?, last_email_received_at = ? WHERE id = ?");
                            if ($updateStmt) {
                                $updateStmt->bind_param("ssssi", $status, $backupDate, $backupSize, $backupDate, $backupJobId);
                                $updateStmt->execute();
                                $updateStmt->close();
                                logMessage("  ✓ Backup job updated with backup_size: '$backupSize'", $logFile);
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
logMessage("Backup source: $source (VEEAM mailbox)", $logFile);
logMessage("========================================", $logFile);

exit(0);
