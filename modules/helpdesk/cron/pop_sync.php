<?php
/**
 * POP sync for Helpdesk (PHP 7.4 + IMAP). Run every minute via cron.
 * /usr/bin/php7.4 /path/to/modules/helpdesk/cron/pop_sync.php
 */
ini_set('max_execution_time', 180);
error_reporting(E_ALL);
ini_set('display_errors', 1);

$scriptDir = __DIR__;
$root = dirname($scriptDir, 3);
require_once $root . '/config.php';
require_once dirname($scriptDir) . '/includes/functions.php';
require_once dirname($scriptDir) . '/includes/MailService.php';
require_once dirname($scriptDir) . '/includes/NotificationService.php';
$canSendNotifications = true;

$logFile = $scriptDir . '/logs/pop_sync.log';
if (!is_dir(dirname($logFile))) {
    mkdir(dirname($logFile), 0755, true);
}

function hd_pop_log($msg, $logFile)
{
    $line = date('Y-m-d H:i:s') . ' ' . $msg . "\n";
    file_put_contents($logFile, $line, FILE_APPEND);
    echo $line;
}

function hd_decode_mime_str($s)
{
    if (function_exists('iconv_mime_decode')) {
        $d = @iconv_mime_decode($s, 0, 'UTF-8');
        return $d !== false ? $d : $s;
    }
    return $s;
}

/**
 * Extract text/HTML body and save attachment parts to ticket folder.
 *
 * @return array{plain:string,html:string}
 */
function hd_imap_walk_parts_save_att($imap, $msgno, $structure, $partPrefix, $ticketId, $conn, $messageDbId)
{
    $plain = '';
    $html = '';

    if (!isset($structure->parts)) {
        $p = $partPrefix === '' ? '1' : $partPrefix;
        $data = imap_fetchbody($imap, $msgno, $p);
        if ($structure->encoding == 3) {
            $data = base64_decode($data, true);
        } elseif ($structure->encoding == 4) {
            $data = quoted_printable_decode($data);
        }
        if ($structure->type == 0 && $structure->subtype === 'PLAIN') {
            $plain = $data ?: '';
        } elseif ($structure->type == 0 && $structure->subtype === 'HTML') {
            $html = $data ?: '';
        }
        if ($plain === '' && $html === '') {
            $raw = @imap_body($imap, $msgno);
            if ($raw) {
                $plain = $raw;
            }
        }
        return ['plain' => $plain, 'html' => $html];
    }

    foreach ($structure->parts as $idx => $sub) {
        $p = $partPrefix === '' ? (string) ($idx + 1) : $partPrefix . '.' . ($idx + 1);
        if ($sub->type == 0) {
            $data = imap_fetchbody($imap, $msgno, $p);
            if ($sub->encoding == 3) {
                $data = base64_decode($data, true);
            } elseif ($sub->encoding == 4) {
                $data = quoted_printable_decode($data);
            }
            if ($sub->subtype === 'PLAIN') {
                $plain = $data ?: $plain;
            } elseif ($sub->subtype === 'HTML') {
                $html = $data ?: $html;
            }
            continue;
        }

        $isAttach = false;
        if (!empty($sub->disposition) && strtolower((string) $sub->disposition) === 'attachment') {
            $isAttach = true;
        }
        if (isset($sub->structure)) {
            // nested
        }
        if ($isAttach || ($sub->type > 0 && !empty($sub->dparameters))) {
            $fname = 'file.bin';
            if (!empty($sub->dparameters)) {
                foreach ($sub->dparameters as $dp) {
                    if (strtolower((string) $dp->attribute) === 'filename') {
                        $fname = hd_decode_mime_str($dp->value);
                        break;
                    }
                }
            }
            $data = imap_fetchbody($imap, $msgno, $p);
            if ($sub->encoding == 3) {
                $data = base64_decode($data, true);
            } elseif ($sub->encoding == 4) {
                $data = quoted_printable_decode($data);
            }
            if ($data === '' || $data === null) {
                continue;
            }
            if (!is_dir(HELPDESK_UPLOAD_DIR)) {
                mkdir(HELPDESK_UPLOAD_DIR, 0755, true);
            }
            $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($fname));
            $destName = $ticketId . '_m' . (int) $messageDbId . '_' . time() . '_' . $safe;
            $destPath = HELPDESK_UPLOAD_DIR . '/' . $destName;
            if (file_put_contents($destPath, $data) !== false) {
                $sz = (int) filesize($destPath);
                $rel = 'uploads/' . $destName;
                $midBind = $messageDbId > 0 ? $messageDbId : null;
                if ($midBind) {
                    $stmt = $conn->prepare('INSERT INTO helpdesk_attachments (ticket_id, message_id, filename, stored_path, size_bytes) VALUES (?, ?, ?, ?, ?)');
                    $stmt->bind_param('iissi', $ticketId, $midBind, $safe, $rel, $sz);
                } else {
                    $stmt = $conn->prepare('INSERT INTO helpdesk_attachments (ticket_id, filename, stored_path, size_bytes) VALUES (?, ?, ?, ?)');
                    $stmt->bind_param('issi', $ticketId, $safe, $rel, $sz);
                }
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    if ($plain === '' && $html === '' && $partPrefix === '') {
        $raw = @imap_body($imap, $msgno);
        if ($raw) {
            $plain = $raw;
        }
    }

    return ['plain' => $plain, 'html' => $html];
}

if (!function_exists('imap_open')) {
    hd_pop_log('ERROR: PHP IMAP extension not loaded', $logFile);
    exit(1);
}

$cfg = hd_get_mail_config_row($conn);
if (!$cfg || $cfg['pop_host'] === '' || $cfg['pop_user'] === '') {
    hd_pop_log('POP not configured; skip.', $logFile);
    exit(0);
}

$mailbox = '{' . $cfg['pop_host'] . ':' . (int) $cfg['pop_port'] . '/pop3' . (!empty($cfg['pop_ssl']) ? '/ssl/novalidate-cert' : '/notls') . '}INBOX';

$inbox = @imap_open($mailbox, $cfg['pop_user'], $cfg['pop_password_dec']);
if (!$inbox) {
    hd_pop_log('Connect failed: ' . imap_last_error(), $logFile);
    exit(1);
}

$nums = imap_search($inbox, 'ALL');
if (!$nums) {
    imap_close($inbox);
    hd_pop_log('Mailbox empty.', $logFile);
    exit(0);
}

foreach ($nums as $msgno) {
    $overview = imap_fetch_overview($inbox, $msgno, 0);
    if (!$overview || empty($overview[0])) {
        @imap_delete($inbox, $msgno);
        continue;
    }
    $ov = $overview[0];
    $h = imap_rfc822_parse_headers(imap_fetchheader($inbox, $msgno));

    $mid = !empty($h->message_id) ? hd_normalize_msg_id($h->message_id) : hd_generate_message_id();
    $irt = !empty($h->in_reply_to) ? hd_normalize_msg_id($h->in_reply_to) : '';
    $refsRaw = isset($h->references) ? (is_array($h->references) ? implode(' ', $h->references) : $h->references) : '';

    $subj = isset($ov->subject) ? $ov->subject : '';
    if (function_exists('imap_utf8')) {
        $subj = imap_utf8($subj);
    }
    $subj = hd_decode_mime_str($subj);
    if (trim($subj) === '') {
        $subj = '(no subject)';
    }

    $fromEmail = '';
    $fromName = '';
    if (isset($h->from) && is_array($h->from) && isset($h->from[0])) {
        $f = $h->from[0];
        if (isset($f->mailbox, $f->host)) {
            $fromEmail = $f->mailbox . '@' . $f->host;
        }
        if (!empty($f->personal)) {
            $fromName = hd_decode_mime_str($f->personal);
        }
    }
    if ($fromEmail === '') {
        $fromEmail = 'unknown@localhost';
    }

    $requesterId = hd_find_or_create_requester_from_email($conn, $fromEmail, $fromName);
    $threadTicketId = hd_find_thread_ticket_id($conn, $irt, $refsRaw);

    $structure = imap_fetchstructure($inbox, $msgno);

    try {
        if ($threadTicketId) {
            $ticketId = (int) $threadTicketId;
            $stmt = $conn->prepare('INSERT INTO helpdesk_messages (ticket_id, direction, body, is_client_visible, email_message_id, email_in_reply_to, email_references) VALUES (?, \'in\', \'\', 1, ?, ?, ?)');
            $stmt->bind_param('isss', $ticketId, $mid, $irt, $refsRaw);
            $stmt->execute();
            $msgDbId = (int) $stmt->insert_id;
            $stmt->close();

            $bod = hd_imap_walk_parts_save_att($inbox, $msgno, $structure, '', $ticketId, $conn, $msgDbId);
            $bodyFinal = $bod['html'] !== '' ? $bod['html'] : $bod['plain'];
            $stmt = $conn->prepare('UPDATE helpdesk_messages SET body = ?, body_html = ? WHERE id = ?');
            $stmt->bind_param('ssi', $bodyFinal, $bod['html'], $msgDbId);
            $stmt->execute();
            $stmt->close();
            hd_recalc_overdue_for_ticket($conn, $ticketId);
        } else {
            $assignSql = 'NULL';
            $pool = hd_randomize_pool_user_ids($conn);
            if (count($pool) === 0 && !empty($cfg['randomize_incoming'])) {
                $pool = hd_technician_pool_user_ids($conn);
            }
            $pick = hd_pick_random_assignee($conn, $pool);
            if ($pick) {
                $assignSql = (string) (int) $pick;
            }

            $rid = (int) $requesterId;
            $subEsc = "'" . $conn->real_escape_string($subj) . "'";
            $due = date('Y-m-d H:i:s', hd_add_working_hours(time(), 24));
            $dueEsc = "'" . $conn->real_escape_string($due) . "'";
            $conn->query("INSERT INTO helpdesk_tickets (client_id, requester_id, assigned_user_id, status, subject, due_at, source) VALUES (NULL, $rid, $assignSql, 'open', $subEsc, $dueEsc, 'email')");
            $ticketId = (int) $conn->insert_id;

            $stmt = $conn->prepare('INSERT INTO helpdesk_messages (ticket_id, direction, body, is_client_visible, email_message_id, email_in_reply_to, email_references) VALUES (?, \'in\', \'\', 1, ?, ?, ?)');
            $stmt->bind_param('isss', $ticketId, $mid, $irt, $refsRaw);
            $stmt->execute();
            $msgDbId = (int) $stmt->insert_id;
            $stmt->close();

            $bod = hd_imap_walk_parts_save_att($inbox, $msgno, $structure, '', $ticketId, $conn, $msgDbId);
            $bodyFinal = $bod['html'] !== '' ? $bod['html'] : $bod['plain'];
            $stmt = $conn->prepare('UPDATE helpdesk_messages SET body = ?, body_html = ? WHERE id = ?');
            $stmt->bind_param('ssi', $bodyFinal, $bod['html'], $msgDbId);
            $stmt->execute();
            $stmt->close();

            hd_recalc_overdue_for_ticket($conn, $ticketId);

            if ($canSendNotifications && $cfg['smtp_host']) {
                $rq = $conn->query('SELECT name, email FROM helpdesk_requesters WHERE id = ' . $rid)->fetch_assoc();
                if ($rq && filter_var($rq['email'], FILTER_VALIDATE_EMAIL)) {
                    HelpdeskMailService::sendTemplate($conn, $cfg, $rq['email'], 'ticket_created', [
                        'ticket_id' => (string) $ticketId,
                        'subject' => $subj,
                        'requester_name' => $rq['name'],
                    ], $ticketId, null, null);
                }
            }
            if ($canSendNotifications) {
                HelpdeskNotificationService::trigger($conn, 'ticket_created', $ticketId);
            }
        }
    } catch (Throwable $e) {
        hd_pop_log('Msg ' . $msgno . ' error: ' . $e->getMessage(), $logFile);
    }

    @imap_delete($inbox, $msgno);
}

imap_expunge($inbox);
imap_close($inbox);
hd_pop_log('Processed ' . count($nums) . ' message(s).', $logFile);
