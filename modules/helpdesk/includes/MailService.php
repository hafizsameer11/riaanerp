<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$vendorRoot = dirname(__DIR__, 3) . '/vendor';
if (PHP_VERSION_ID >= 80000) {
    require_once $vendorRoot . '/autoload.php';
} else {
    // PHP 7.4 cron compatibility: load only PHPMailer classes directly.
    require_once $vendorRoot . '/phpmailer/phpmailer/src/Exception.php';
    require_once $vendorRoot . '/phpmailer/phpmailer/src/PHPMailer.php';
    require_once $vendorRoot . '/phpmailer/phpmailer/src/SMTP.php';
}

class HelpdeskMailService
{
    /**
     * One-off SMTP send (no helpdesk_messages row).
     *
     * @return array{ok:bool,error?:string}
     */
    public static function sendRaw($cfg, $toEmail, $subject, $htmlBody)
    {
        if (empty($cfg['smtp_host'])) {
            return ['ok' => false, 'error' => 'SMTP not configured'];
        }
        try {
            $mail = self::baseMailer($cfg);
            $mail->addAddress($toEmail);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->send();
            return ['ok' => true];
        } catch (Exception $e) {
            return ['ok' => false, 'error' => $mail->ErrorInfo ?? $e->getMessage()];
        }
    }

    /**
     * @param array $cfg Row from helpdesk_mail_config with *_dec passwords
     * @return PHPMailer
     */
    private static function baseMailer($cfg)
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $cfg['smtp_host'];
        $mail->Port = (int) $cfg['smtp_port'];
        $mail->SMTPAuth = ($cfg['smtp_user'] !== '');
        if ($mail->SMTPAuth) {
            $mail->Username = $cfg['smtp_user'];
            $mail->Password = $cfg['smtp_password_dec'];
        }
        if (!empty($cfg['smtp_ssl'])) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = false;
            $mail->SMTPAutoTLS = false;
        }
        $fromEmail = $cfg['smtp_from_email'] ?: $cfg['smtp_user'];
        $mail->setFrom($fromEmail, $cfg['smtp_from_name'] ?: 'Helpdesk');
        $mail->isHTML(true);
        return $mail;
    }

    /**
     * @param mysqli $conn
     * @param array $cfg
     * @param string $toEmail
     * @param string $subject
     * @param string $htmlBody
     * @param int $ticketId
     * @param string|null $inReplyToMsgId Our message id to reply under (no brackets)
     * @param string|null $referencesChain Space-separated message ids
     * @return array{ok:bool,message_id?:string,error?:string}
     */
    public static function sendTicketMessage($conn, $cfg, $toEmail, $subject, $htmlBody, $ticketId, $inReplyToMsgId = null, $referencesChain = null)
    {
        if (empty($cfg['smtp_host'])) {
            return ['ok' => false, 'error' => 'SMTP not configured'];
        }
        try {
            $mail = self::baseMailer($cfg);
            $mail->addAddress($toEmail);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;

            $newId = hd_generate_message_id();
            $mail->MessageID = '<' . $newId . '>';

            if ($inReplyToMsgId) {
                $mailaddReply = trim($inReplyToMsgId);
                if ($mailaddReply !== '') {
                    $mail->addCustomHeader('In-Reply-To', '<' . $mailaddReply . '>');
                }
            }
            if ($referencesChain) {
                $refs = trim($referencesChain);
                if ($refs !== '') {
                    $parts = preg_split('/\s+/', $refs);
                    $line = '';
                    foreach ($parts as $p) {
                        $p = trim(hd_normalize_msg_id($p));
                        if ($p !== '') {
                            $line .= ' <' . $p . '>';
                        }
                    }
                    $line = trim($line);
                    if ($line !== '') {
                        $mail->addCustomHeader('References', $line);
                    }
                }
            }

            $mail->send();

            $stmt = $conn->prepare('INSERT INTO helpdesk_messages (ticket_id, direction, body, body_html, is_client_visible, email_message_id, email_in_reply_to, email_references, created_by_user_id) VALUES (?, \'out\', ?, ?, 1, ?, ?, ?, ?)');
            $plain = strip_tags($htmlBody);
            $uid = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
            $ir = $inReplyToMsgId ?: '';
            $ref = $referencesChain ?: '';
            $stmt->bind_param('issssssi', $ticketId, $plain, $htmlBody, $newId, $ir, $ref, $uid);
            $stmt->execute();
            $stmt->close();

            return ['ok' => true, 'message_id' => $newId];
        } catch (Exception $e) {
            return ['ok' => false, 'error' => $mail->ErrorInfo ?? $e->getMessage()];
        }
    }

    /**
     * Send using a DB template by code.
     *
     * @return array{ok:bool,message_id?:string,error?:string}
     */
    public static function sendTemplate($conn, $cfg, $toEmail, $templateCode, array $vars, $ticketId, $inReplyToMsgId = null, $referencesChain = null)
    {
        $stmt = $conn->prepare('SELECT subject, body_html FROM helpdesk_email_templates WHERE code = ? AND is_active = 1 LIMIT 1');
        $stmt->bind_param('s', $templateCode);
        $stmt->execute();
        $tpl = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$tpl) {
            return ['ok' => false, 'error' => 'Template not found'];
        }
        $subj = hd_render_template($tpl['subject'], $vars);
        $body = hd_render_template($tpl['body_html'], $vars);
        if ((int) $ticketId < 1) {
            return self::sendRaw($cfg, $toEmail, $subj, $body);
        }
        return self::sendTicketMessage($conn, $cfg, $toEmail, $subj, $body, $ticketId, $inReplyToMsgId, $referencesChain);
    }
}
