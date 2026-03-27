<?php
/**
 * Check for Missing Backups
 * Runs daily to check if any backup job hasn't received an email today
 * Sends alert to helpdesk@sautech.net
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../config.php';
require_once '../../vendor/autoload.php';

$helpdeskEmail = 'helpdesk@sautech.net';
$today = date('Y-m-d');

function sendAlertEmail($to, $subject, $body) {
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'relay.sautech.co.za';
        $mail->SMTPAuth = true;
        $mail->Username = 'erpsautech';
        $mail->Password = 'Erp$au+ech#782';
        $mail->Port = 2525;
        $mail->SMTPSecure = false;
        $mail->SMTPAutoTLS = false;
        
        $mail->setFrom('support@sautech.net', 'Backup Monitoring System');
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Email alert failed: " . $mail->ErrorInfo);
        return false;
    }
}

// Get all active backup jobs
$jobs = $conn->query("
    SELECT bj.*, c.client_name 
    FROM backup_jobs bj
    LEFT JOIN clients c ON c.id = bj.client_id
    WHERE bj.is_active = 1
    ORDER BY c.client_name, bj.device_type
");

$missingBackups = [];

while ($job = $jobs->fetch_assoc()) {
    // Check if we received an email today for this job
    $lastEmailDate = $job['last_email_received_at'];
    
    if (empty($lastEmailDate)) {
        // Never received an email
        $missingBackups[] = $job;
    } else {
        $lastEmailDateOnly = date('Y-m-d', strtotime($lastEmailDate));
        if ($lastEmailDateOnly !== $today) {
            // Last email was not today
            $missingBackups[] = $job;
        }
    }
}

// Send alert if there are missing backups
if (!empty($missingBackups)) {
    $subject = "[Alert] Missing Backup Reports - " . count($missingBackups) . " Job(s)";
    
    $body = "<h2>Missing Backup Reports</h2>";
    $body .= "<p>The following backup jobs did not receive a report today:</p>";
    $body .= "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse;'>";
    $body .= "<tr style='background-color: #f0f0f0;'><th>Client</th><th>Contact</th><th>Device Type</th><th>Source</th><th>Last Email Received</th></tr>";
    
    foreach ($missingBackups as $job) {
        $lastEmail = $job['last_email_received_at'] ? date('Y-m-d H:i:s', strtotime($job['last_email_received_at'])) : 'Never';
        $body .= "<tr>";
        $body .= "<td>" . htmlspecialchars($job['client_name']) . "</td>";
        $body .= "<td>" . htmlspecialchars($job['contact_name']) . "</td>";
        $body .= "<td>" . htmlspecialchars($job['device_type']) . "</td>";
        $body .= "<td>" . htmlspecialchars($job['backup_source']) . "</td>";
        $body .= "<td>" . htmlspecialchars($lastEmail) . "</td>";
        $body .= "</tr>";
    }
    
    $body .= "</table>";
    $body .= "<p><strong>Date:</strong> " . date('Y-m-d H:i:s') . "</p>";
    
    sendAlertEmail($helpdeskEmail, $subject, $body);
    echo "Alert sent for " . count($missingBackups) . " missing backup(s)\n";
} else {
    echo "All backup jobs received reports today.\n";
}
