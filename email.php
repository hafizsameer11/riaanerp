<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host = 'ironfoot.onlinehosting.co.za';
    $mail->Port = 25;
    $mail->SMTPAuth = false; // No auth
    $mail->SMTPSecure = false; // No encryption
    $mail->SMTPAutoTLS = false; // 

    // Sender and recipient
    $mail->setFrom('support@sautech.net', 'SAU Technologies Support Team');
    $mail->addAddress('riaan@sautech.net', 'Hamza Bilal');

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Test Email';
    $mail->Body = 'This is a test email.';

    $mail->send();
    echo 'Message has been sent';
} catch (Exception $e) {
    echo "Mailer Error: {$mail->ErrorInfo}";
}
?>