<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendEmailWithAttachment($to, $toName, $fromName, $csvContent)
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'relay.sautech.co.za';
        $mail->SMTPAuth = true;
        $mail->Username = 'erpsautech';
        $mail->Password = 'Erp$au+ech#782';
        $mail->Port = 2525;
        $mail->SMTPSecure = false;
        $mail->SMTPAutoTLS = false;

        // Recipients
        $mail->setFrom('support@sautech.net', $fromName);
        $mail->addAddress($to, $toName);

        // Email content
        $mail->isHTML(true);
        $mail->Subject = 'Reseller Commission Report';
        $mail->Body = "Hello $toName,<br><br>Please find attached your commission report.<br><br>Regards,<br>$fromName";

        // Attach CSV as a string
        $mail->addStringAttachment($csvContent, 'reseller_commission.csv', 'base64', 'text/csv');

        $mail->send();
        return true;
    } catch (Exception $e) {
        return $mail->ErrorInfo;
    }
}
function sendQuote($to, $toName, $fromName, $csvContent)
{
    $mail = new PHPMailer(true);

    try {
        // SMTP settings
        $mail->isSMTP();
        $mail->Host = 'ironfoot.onlinehosting.co.za'; // ✅ custom SMTP server
        $mail->SMTPAuth = false;                       // ✅ no authentication needed
        $mail->Port = 25;                              // ✅ port 25 (non-encrypted)
        $mail->SMTPSecure = false;

        // From and to
        $mail->setFrom('Support@sautech.net', $fromName);
        $mail->addAddress($to, $toName);

        // Email content
        $mail->isHTML(true);
        $mail->Subject = 'Sautech Quote';
        $mail->Body = "Hello $toName,<br><br>Please find attached your quote.<br><br>Regards,<br>$fromName";

        // Attach CSV as a string
        $mail->addStringAttachment($csvContent, 'reseller_commission.csv', 'base64', 'text/csv');

        $mail->send();
        return true;
    } catch (Exception $e) {
        return $mail->ErrorInfo;
    }
}
