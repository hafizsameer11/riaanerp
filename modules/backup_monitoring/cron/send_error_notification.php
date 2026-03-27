<?php
/**
 * Error Notification Function
 * 
 * Sends email notification to Helpdesk@sautech.net when backup status is "error" or "failed"
 * Uses the same subject and body as the original email
 * 
 * @param string $emailSubject Original email subject
 * @param string $emailBody Original email body
 * @param string $source Backup source (VEEAM, CLIENT, VEEAMCLOUD, NAS)
 * @param string $status Backup status
 * @param string $logFile Optional log file path for logging
 * @return array ['success' => bool, 'message' => string]
 */
function sendErrorNotification($emailSubject, $emailBody, $source = '', $status = '', $logFile = '') {
    // Create dedicated error notification log file
    $errorLogFile = dirname(__DIR__) . '/logs/error_notification.log';
    
    // Log function - log to both the provided log file and dedicated error log
    $logMessage = function($message) use ($logFile, $errorLogFile) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] ERROR_NOTIFICATION: $message\n";
        
        // Log to dedicated error notification log file
        @file_put_contents($errorLogFile, $logEntry, FILE_APPEND);
        
        // Also log to the provided log file if available
        if (!empty($logFile)) {
            @file_put_contents($logFile, $logEntry, FILE_APPEND);
        }
    };
    
    $logMessage("=== Error Notification Check ===");
    $logMessage("Received status: '$status' (length: " . strlen($status) . ")");
    
    // Check if status is error or failed (case-insensitive)
    $statusLower = strtolower(trim($status));
    $logMessage("Status (lowercase, trimmed): '$statusLower'");
    
    if ($statusLower !== 'error' && $statusLower !== 'failed') {
        // Not an error status, skip notification
        $logMessage("SKIPPED: Status '$statusLower' is not 'error' or 'failed' - notification not sent");
        return ['success' => false, 'message' => 'Status is not error or failed'];
    }
    
    $logMessage("Status is ERROR or FAILED - proceeding with notification");
    $logMessage("Attempting to send error notification for status: '$status', source: '$source', subject: '$emailSubject'");
    
    // Load PHPMailer - use same path calculation as backend.php
    $logMessage("Loading PHPMailer library...");
    $phpmailerLoaded = false;
    
    // Calculate base directory
    // From: /var/www/html/erp/modules/backup_monitoring/cron/send_error_notification.php
    // To: /var/www/html/erp
    $baseDir = dirname(dirname(dirname(__DIR__))); // cron -> backup_monitoring -> modules -> erp
    $logMessage("Base directory: $baseDir");
    $logMessage("Current __DIR__: " . __DIR__);
    
    $autoloadPaths = [
        $baseDir . '/vendor/autoload.php',
        __DIR__ . '/../../vendor/autoload.php',
        __DIR__ . '/../../../vendor/autoload.php',
        dirname($baseDir) . '/vendor/autoload.php'
    ];
    
    // Try to load PHPMailer directly first (bypasses Composer platform check)
    $logMessage("Attempting to load PHPMailer directly...");
    $phpmailerPaths = [
        $baseDir . '/vendor/phpmailer/phpmailer/src/PHPMailer.php',
        __DIR__ . '/../../vendor/phpmailer/phpmailer/src/PHPMailer.php',
        __DIR__ . '/../../../vendor/phpmailer/phpmailer/src/PHPMailer.php'
    ];
    
    foreach ($phpmailerPaths as $phpmailerPath) {
        if (file_exists($phpmailerPath)) {
            $logMessage("Found PHPMailer.php at: $phpmailerPath");
            try {
                $phpmailerDir = dirname($phpmailerPath);
                require_once $phpmailerDir . '/Exception.php';
                require_once $phpmailerDir . '/SMTP.php';
                require_once $phpmailerPath;
                $phpmailerLoaded = class_exists('PHPMailer\PHPMailer\PHPMailer');
                if ($phpmailerLoaded) {
                    $logMessage("PHPMailer class loaded successfully via direct require");
                    break;
                } else {
                    $logMessage("WARNING: PHPMailer files loaded but class not found");
                }
            } catch (Exception $e) {
                $logMessage("ERROR loading PHPMailer directly: " . $e->getMessage());
            } catch (Error $e) {
                $logMessage("FATAL ERROR loading PHPMailer directly: " . $e->getMessage());
            }
        }
    }
    
    // If direct load failed, try autoload as fallback
    if (!$phpmailerLoaded) {
        $logMessage("Direct load failed, trying autoload...");
        foreach ($autoloadPaths as $path) {
            if (file_exists($path)) {
                $logMessage("Found vendor/autoload.php at: $path");
                try {
                    // Suppress Composer platform check by setting environment variable
                    putenv('COMPOSER_PLATFORM_CHECK=0');
                    require_once $path;
                    $phpmailerLoaded = class_exists('PHPMailer\PHPMailer\PHPMailer');
                    if ($phpmailerLoaded) {
                        $logMessage("PHPMailer class loaded successfully via autoload");
                        break;
                    } else {
                        $logMessage("WARNING: autoload.php loaded but PHPMailer class not found");
                    }
                } catch (Exception $e) {
                    $logMessage("ERROR loading autoload.php: " . $e->getMessage());
                } catch (Error $e) {
                    $logMessage("FATAL ERROR loading autoload.php: " . $e->getMessage());
                }
            }
        }
    }
    
    if (!$phpmailerLoaded) {
        $errorMsg = 'PHPMailer not loaded';
        $logMessage("ERROR: $errorMsg");
        $logMessage("Tried autoload paths: " . implode(', ', $autoloadPaths));
        return ['success' => false, 'message' => $errorMsg];
    }
    
    $logMessage("PHPMailer loaded successfully, creating PHPMailer instance...");
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $logMessage("PHPMailer instance created successfully");
        
        // SMTP Configuration (same as sendExcelEmail)
        $logMessage("Configuring SMTP settings...");
        $mail->isSMTP();
        $mail->Host = 'mail-eu.smtp2go.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'sterp';
        $mail->Password = 'GDJbb45WkGijYD5D';
        $mail->Port = 2525;
        $mail->SMTPSecure = false; // Use false for port 2525
        $mail->SMTPAutoTLS = false;
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Set timeout for SMTP connection
        $mail->Timeout = 30;
        $logMessage("SMTP configured: Host=mail-eu.smtp2go.com, Port=2525, Username=sterp");
        
        // Email settings
        $logMessage("Setting email from/to addresses...");
        $mail->setFrom('support@sautech.net', 'Backup Monitoring System');
        $mail->addAddress('Helpdesk@sautech.net');
        $mail->isHTML(true);
        $logMessage("Email addresses set: From=support@sautech.net, To=Helpdesk@sautech.net");
        
        // Use the same subject as the original email (no alert prefix)
        $mail->Subject = $emailSubject;
        
        // Use the same email body as the original email (no modifications)
        // If body contains HTML, send as HTML; otherwise send as plain text
        if (strip_tags($emailBody) !== $emailBody) {
            // Contains HTML - use as is
            $mail->Body = $emailBody;
            $mail->AltBody = strip_tags($emailBody);
        } else {
            // Plain text - convert to HTML for display
            $mail->Body = nl2br(htmlspecialchars($emailBody));
            $mail->AltBody = $emailBody;
        }
        
        $logMessage("Email prepared - Subject: '$emailSubject', Recipient: Helpdesk@sautech.net");
        $logMessage("Email body length: " . strlen($emailBody) . " characters");
        $logMessage("SMTP Host: mail-eu.smtp2go.com, Port: 2525, Username: sterp");
        
        $logMessage("Attempting to send email via SMTP...");
        if ($mail->send()) {
            $successMsg = "Error notification sent successfully to Helpdesk@sautech.net";
            $logMessage("SUCCESS: $successMsg");
            $logMessage("Email sent with subject: '$emailSubject'");
            $logMessage("=== Error Notification Sent Successfully ===");
            return ['success' => true, 'message' => $successMsg];
        } else {
            $errorMsg = 'Failed to send error notification: ' . $mail->ErrorInfo;
            $logMessage("ERROR: $errorMsg");
            $logMessage("SMTP Error Details: " . $mail->ErrorInfo);
            $logMessage("SMTP Debug Output: " . print_r($mail->getSMTPInstance(), true));
            $logMessage("=== Error Notification Failed ===");
            return ['success' => false, 'message' => $errorMsg];
        }
    } catch (Exception $e) {
        $errorMsg = 'Error notification exception: ' . $e->getMessage();
        $logMessage("EXCEPTION: $errorMsg");
        return ['success' => false, 'message' => $errorMsg];
    }
}
