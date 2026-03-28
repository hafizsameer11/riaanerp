<?php

require_once __DIR__ . '/MailService.php';

class HelpdeskNotificationService
{
    /**
     * Trigger notifications for a helpdesk event code.
     *
     * @param mysqli $conn
     * @param string $eventCode
     * @param int $ticketId
     * @param array $vars
     * @return void
     */
    public static function trigger($conn, $eventCode, $ticketId, array $vars = [])
    {
        $ticketId = (int) $ticketId;
        if ($ticketId < 1) {
            return;
        }

        $cfg = hd_get_mail_config_row($conn);
        if (!$cfg || empty($cfg['smtp_host'])) {
            return;
        }

        $stmt = $conn->prepare(
            "SELECT r.template_id, t.code
             FROM helpdesk_notification_rules r
             JOIN helpdesk_email_templates t ON t.id = r.template_id
             WHERE r.event_code = ? AND r.is_active = 1 AND t.is_active = 1"
        );
        $stmt->bind_param('s', $eventCode);
        $stmt->execute();
        $rows = $stmt->get_result();

        $meta = $conn->query(
            "SELECT t.id, t.subject, rq.name AS requester_name, rq.email AS requester_email
             FROM helpdesk_tickets t
             LEFT JOIN helpdesk_requesters rq ON rq.id = t.requester_id
             WHERE t.id = $ticketId LIMIT 1"
        )->fetch_assoc();
        if (!$meta || !filter_var($meta['requester_email'], FILTER_VALIDATE_EMAIL)) {
            $stmt->close();
            return;
        }

        $baseVars = [
            'ticket_id' => (string) $meta['id'],
            'subject' => (string) $meta['subject'],
            'requester_name' => (string) ($meta['requester_name'] ?: ''),
            'body' => (string) ($vars['body'] ?? ''),
            'status' => (string) ($vars['status'] ?? ''),
            'on_hold_reason' => (string) ($vars['on_hold_reason'] ?? ''),
        ];
        $payload = array_merge($baseVars, $vars);

        while ($row = $rows->fetch_assoc()) {
            HelpdeskMailService::sendTemplate(
                $conn,
                $cfg,
                $meta['requester_email'],
                $row['code'],
                $payload,
                $ticketId,
                null,
                null
            );
        }
        $stmt->close();
    }
}

