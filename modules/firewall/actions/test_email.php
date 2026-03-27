<?php
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
require_once __DIR__ . '/../includes/functions.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    $_SESSION['error'] = "Email ID is required.";
    header("Location: ../pages/manage_emails.php");
    exit;
}

// Get email address
$stmt = db()->prepare("SELECT email FROM notification_emails WHERE id = ? AND active = 1");
$stmt->execute([$id]);
$emailData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$emailData) {
    $_SESSION['error'] = "Email address not found or inactive.";
    header("Location: ../pages/manage_emails.php");
    exit;
}

$email = $emailData['email'];

// Send test email
$subject = "Firewall Monitor - Test Email";
$body = "This is a test email from the Firewall Monitor system.\n\n";
$body .= "If you received this email, your email notifications are working correctly.\n\n";
$body .= "Time: " . date('Y-m-d H:i:s') . "\n";
$body .= "System: Firewall Monitor\n";

$actualError = '';
$result = sendNotificationEmail([$email], $subject, $body, $actualError);

if ($result) {
    $_SESSION['success'] = "Test email sent successfully to: " . htmlspecialchars($email) . ". Please check your inbox (and spam folder).";
} else {
    // Show actual SMTP error instead of generic messages
    $errorDetails = "Failed to send test email to: " . htmlspecialchars($email) . "\n\n";
    
    if (!empty($actualError)) {
        // Show the cleaned actual error from PHPMailer
        $errorDetails .= "Error: " . htmlspecialchars($actualError) . "\n\n";
        
        // Provide specific solution based on error type
        if (stripos($actualError, 'certificate verify failed') !== false || 
            stripos($actualError, 'SSL operation failed') !== false) {
            $errorDetails .= "Solution: TLS/SSL certificate verification failed.\n";
            $errorDetails .= "Add this to config.php to disable TLS:\n";
            $errorDetails .= "define('SMTP_SECURE', false);\n\n";
        } elseif (stripos($actualError, 'Could not connect') !== false || 
                   stripos($actualError, 'Connection failed') !== false) {
            $errorDetails .= "Solution: Cannot connect to SMTP server.\n";
            $errorDetails .= "Check if " . SMTP_HOST . ":" . SMTP_PORT . " is accessible.\n\n";
        } elseif (stripos($actualError, 'Authentication failed') !== false) {
            $errorDetails .= "Solution: SMTP authentication failed.\n";
            $errorDetails .= "Verify username and password in config.php.\n\n";
        }
    } else {
        $errorDetails .= "No detailed error information available.\n\n";
    }
    
    $errorDetails .= "Current Configuration:\n";
    $errorDetails .= "Host: " . SMTP_HOST . ":" . SMTP_PORT . "\n";
    $errorDetails .= "Security: " . (defined('SMTP_SECURE') ? constant('SMTP_SECURE') : 'TLS (auto)') . "\n";
    
    $_SESSION['error'] = $errorDetails;
}

header("Location: ../pages/manage_emails.php");
exit;

