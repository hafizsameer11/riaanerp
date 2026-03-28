<?php
/**
 * Helpdesk helpers (PHP 7.4 compatible).
 */

if (!defined('HELPDESK_ROOT')) {
    define('HELPDESK_ROOT', dirname(__DIR__));
}

if (!defined('HELPDESK_UPLOAD_DIR')) {
    define('HELPDESK_UPLOAD_DIR', HELPDESK_ROOT . '/uploads');
}

/**
 * @return bool
 */
function hd_can($function_name)
{
    return hasPermission('helpdesk', $function_name);
}

/**
 * Any helpdesk access (admin has all via permissioncheck).
 */
function hd_has_any_access()
{
    if (isset($_SESSION['role']) && strtolower((string) $_SESSION['role']) === 'admin') {
        return true;
    }
    if (!isset($_SESSION['permissions']['helpdesk'])) {
        return false;
    }
    return count($_SESSION['permissions']['helpdesk']) > 0;
}

function hd_require_any()
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../auth/login.php');
        exit;
    }
    if (!hd_has_any_access()) {
        http_response_code(403);
        die('Access denied. No Helpdesk permission.');
    }
}

function hd_require($function_name)
{
    hd_require_any();
    if (!hd_can($function_name)) {
        http_response_code(403);
        die('Access denied.');
    }
}

function hd_esc($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/**
 * Add working-hour hours (Mon–Fri count each calendar hour) to a timestamp.
 *
 * @param int $ts Unix timestamp
 * @param int $workingHours Number of hours (e.g. 48)
 * @return int Unix timestamp when deadline is reached
 */
function hd_add_working_hours($ts, $workingHours)
{
    $remaining = (int) $workingHours;
    $cursor = (int) $ts;
    $safety = 0;
    while ($remaining > 0 && $safety < 5000) {
        $safety++;
        $d = (int) date('N', $cursor); // 1=Mon .. 7=Sun
        if ($d < 6) {
            $remaining--;
        }
        if ($remaining <= 0) {
            break;
        }
        $cursor += 3600;
    }
    return $cursor;
}

/**
 * @param mysqli $conn
 * @param int $ticket_id
 */
function hd_recalc_overdue_for_ticket($conn, $ticket_id)
{
    $stmt = $conn->prepare('SELECT id, status, created_at, due_at, is_overdue FROM helpdesk_tickets WHERE id = ? AND merged_into_ticket_id IS NULL LIMIT 1');
    $stmt->bind_param('i', $ticket_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row || $row['status'] === 'closed') {
        return;
    }

    // Overdue if now past due_at or past created_at + 48 working hours
    $createdTs = strtotime($row['created_at']);
    $thresholdTs = hd_add_working_hours($createdTs, 48);
    $dueTs = $row['due_at'] ? strtotime($row['due_at']) : null;

    $now = time();
    $workingOverdue = ($now > $thresholdTs);
    $dueOverdue = ($dueTs !== null && $dueTs > 0 && $now > $dueTs);
    $flag = ($workingOverdue || $dueOverdue) ? 1 : 0;

    $u = $conn->prepare('UPDATE helpdesk_tickets SET is_overdue = ? WHERE id = ?');
    $u->bind_param('ii', $flag, $ticket_id);
    $u->execute();
    $u->close();
}

/**
 * Run overdue refresh for all open/on_hold tickets.
 *
 * @param mysqli $conn
 */
function hd_refresh_all_overdue($conn)
{
    $r = $conn->query("SELECT id FROM helpdesk_tickets WHERE status IN ('open','on_hold') AND merged_into_ticket_id IS NULL");
    while ($row = $r->fetch_assoc()) {
        hd_recalc_overdue_for_ticket($conn, (int) $row['id']);
    }
}

/**
 * Find ticket id for inbound email using only In-Reply-To / References vs stored message ids.
 *
 * @param mysqli $conn
 * @param string|null $inReplyTo
 * @param string|null $references
 * @return int|null
 */
function hd_find_thread_ticket_id($conn, $inReplyTo, $references)
{
    $ids = [];
    if ($inReplyTo) {
        $ids[] = hd_normalize_msg_id($inReplyTo);
    }
    if ($references) {
        foreach (preg_split('/\s+/', trim($references)) as $p) {
            $n = hd_normalize_msg_id($p);
            if ($n !== '') {
                $ids[] = $n;
            }
        }
    }
    $ids = array_unique(array_filter($ids));
    if (count($ids) === 0) {
        return null;
    }

    foreach ($ids as $mid) {
        $stmt = $conn->prepare('SELECT ticket_id FROM helpdesk_messages WHERE email_message_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->bind_param('s', $mid);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($res) {
            return (int) $res['ticket_id'];
        }
    }
    return null;
}

/**
 * @param string $raw
 * @return string
 */
function hd_normalize_msg_id($raw)
{
    $s = trim($raw);
    $s = trim($s, '<> ');
    return $s;
}

/**
 * Generate a Message-ID header value (without brackets).
 */
function hd_generate_message_id()
{
    $host = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost';
    return 'helpdesk.' . bin2hex(random_bytes(8)) . '@' . $host;
}

/**
 * Pick technician for random assignment.
 * Prioritizes least open tickets, then least recently assigned, then user id.
 *
 * @param mysqli $conn
 * @param int[] $candidateUserIds registers.id values
 * @return int|null
 */
function hd_pick_random_assignee($conn, array $candidateUserIds)
{
    if (count($candidateUserIds) === 0) {
        return null;
    }
    $candidateUserIds = array_map('intval', $candidateUserIds);
    $candidateUserIds = array_filter($candidateUserIds);
    if (count($candidateUserIds) === 0) {
        return null;
    }
    $in = implode(',', $candidateUserIds);
    $sql = "
        SELECT
            r.id,
            COUNT(CASE WHEN t.status IN ('open','on_hold') THEN 1 END) AS open_count,
            MAX(t.created_at) AS last_assigned_at
        FROM registers r
        LEFT JOIN helpdesk_tickets t
            ON t.assigned_user_id = r.id
            AND t.merged_into_ticket_id IS NULL
        WHERE r.id IN ($in)
        GROUP BY r.id
        ORDER BY open_count ASC, last_assigned_at ASC, r.id ASC
        LIMIT 1
    ";
    $row = $conn->query($sql)->fetch_assoc();
    return $row ? (int) $row['id'] : null;
}

/**
 * @return int[] Users whose role has helpdesk randomize permission
 */
function hd_randomize_pool_user_ids($conn)
{
    $roleIds = [];
    $rr = $conn->query("SELECT DISTINCT role_id FROM permissions WHERE page = 'helpdesk' AND function_name = 'randomize assignment' AND allowed = 1");
    while ($row = $rr->fetch_assoc()) {
        $roleIds[] = (int) $row['role_id'];
    }
    if (count($roleIds) === 0) {
        return [];
    }
    $inRoles = implode(',', array_map('intval', $roleIds));
    $out = [];
    $r = $conn->query("SELECT id FROM registers WHERE role_id IN ($inRoles) AND (role_id IS NULL OR role_id <> 100)");
    while ($row = $r->fetch_assoc()) {
        $out[] = (int) $row['id'];
    }
    return $out;
}

/**
 * @return int[] Fallback technician pool (non-requester users)
 */
function hd_technician_pool_user_ids($conn)
{
    $out = [];
    $r = $conn->query("SELECT id FROM registers WHERE (role_id IS NULL OR role_id <> 100)");
    while ($row = $r->fetch_assoc()) {
        $out[] = (int) $row['id'];
    }
    return $out;
}

/**
 * Simple encryption for POP/SMTP passwords (same idea as backup monitoring).
 */
function hd_encrypt_secret($plain)
{
    if ($plain === '' || $plain === null) {
        return '';
    }
    if (!defined('HELPDESK_ENC_KEY')) {
        define('HELPDESK_ENC_KEY', 'G7fP9xL2tQ8wR4kB1mZ6uH3cV0aN5sD');
    }
    $key = HELPDESK_ENC_KEY;
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plain, 'AES-256-CBC', hash('sha256', $key, true), OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) {
        return base64_encode($plain);
    }
    return base64_encode($iv . $cipher);
}

function hd_decrypt_secret($stored)
{
    if ($stored === '' || $stored === null) {
        return '';
    }
    if (!defined('HELPDESK_ENC_KEY')) {
        define('HELPDESK_ENC_KEY', 'G7fP9xL2tQ8wR4kB1mZ6uH3cV0aN5sD');
    }
    $raw = base64_decode($stored, true);
    if ($raw === false || strlen($raw) < 17) {
        return $stored;
    }
    $iv = substr($raw, 0, 16);
    $cipher = substr($raw, 16);
    $plain = openssl_decrypt($cipher, 'AES-256-CBC', hash('sha256', HELPDESK_ENC_KEY, true), OPENSSL_RAW_DATA, $iv);
    return $plain !== false ? $plain : $stored;
}

/**
 * @param mysqli $conn
 * @return array|null
 */
function hd_get_mail_config_row($conn)
{
    $r = $conn->query('SELECT * FROM helpdesk_mail_config WHERE id = 1 LIMIT 1');
    if (!$r) {
        return null;
    }
    $row = $r->fetch_assoc();
    if (!$row) {
        return null;
    }
    $row['pop_password_dec'] = hd_decrypt_secret($row['pop_password']);
    $row['smtp_password_dec'] = hd_decrypt_secret($row['smtp_password']);
    return $row;
}

/**
 * Render template with {{var}} replacements.
 *
 * @param string $html
 * @param array $vars
 * @return string
 */
function hd_render_template($html, array $vars)
{
    foreach ($vars as $k => $v) {
        $html = str_replace('{{' . $k . '}}', $v, $html);
    }
    return $html;
}

function hd_schedule_next_run($type, $intervalDays, $fromTs)
{
    $t = (int) $fromTs;
    switch ($type) {
        case 'once':
            return null;
        case 'daily':
            return date('Y-m-d H:i:s', strtotime('+1 day', $t));
        case 'weekly':
            return date('Y-m-d H:i:s', strtotime('+7 days', $t));
        case 'monthly':
            return date('Y-m-d H:i:s', strtotime('+1 month', $t));
        case 'periodic':
            $d = max(1, (int) $intervalDays);
            return date('Y-m-d H:i:s', strtotime('+' . $d . ' days', $t));
        default:
            return date('Y-m-d H:i:s', strtotime('+1 day', $t));
    }
}

/**
 * @param mysqli $conn
 * @param array $job
 * @return int
 */
/**
 * @param mysqli $conn
 * @param string $email
 * @param string $displayName
 * @return int requester id
 */
function hd_find_or_create_requester_from_email($conn, $email, $displayName)
{
    $email = trim(strtolower($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email = 'unknown+' . time() . '@invalid.local';
    }
    $stmt = $conn->prepare('SELECT id FROM helpdesk_requesters WHERE LOWER(email) = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $ex = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($ex) {
        return (int) $ex['id'];
    }
    $name = $displayName !== '' ? $displayName : $email;
    $nameEsc = $conn->real_escape_string($name);
    $emailEsc = $conn->real_escape_string($email);
    $pass = bin2hex(random_bytes(4));
    $passEsc = $conn->real_escape_string($pass);
    $conn->query("INSERT INTO registers (username, password, name, surname, email, address, role_id) VALUES ('$emailEsc', '$passEsc', '$nameEsc', '', '$emailEsc', '-', 100)");
    $regId = (int) $conn->insert_id;
    $conn->query("INSERT INTO helpdesk_requesters (name, email, register_id) VALUES ('$nameEsc', '$emailEsc', $regId)");
    return (int) $conn->insert_id;
}

function hd_create_ticket_from_scheduled_job($conn, array $job)
{
    $client_id = !empty($job['client_id']) ? (int) $job['client_id'] : null;
    $requester_id = (int) $job['requester_id'];
    $assigned_user_id = !empty($job['assigned_user_id']) ? (int) $job['assigned_user_id'] : null;
    $subject = $job['subject'];
    $desc = isset($job['description']) ? $job['description'] : '';
    $cidSql = $client_id ? (int) $client_id : 'NULL';
    $aidSql = $assigned_user_id !== null ? (int) $assigned_user_id : 'NULL';
    $subEsc = "'" . $conn->real_escape_string($subject) . "'";
    $due = date('Y-m-d H:i:s', hd_add_working_hours(time(), 24));
    $dueEsc = "'" . $conn->real_escape_string($due) . "'";
    $conn->query("INSERT INTO helpdesk_tickets (client_id, requester_id, assigned_user_id, status, subject, due_at, source) VALUES ($cidSql, $requester_id, $aidSql, 'open', $subEsc, $dueEsc, 'scheduled')");
    $tid = (int) $conn->insert_id;
    if ($desc !== '') {
        $stmt = $conn->prepare('INSERT INTO helpdesk_messages (ticket_id, direction, body, is_client_visible) VALUES (?, \'in\', ?, 1)');
        $stmt->bind_param('is', $tid, $desc);
        $stmt->execute();
        $stmt->close();
    }
    // Copy any scheduled attachment templates into this ticket.
    $jobId = isset($job['id']) ? (int) $job['id'] : 0;
    if ($jobId > 0) {
        hd_copy_scheduled_attachments_to_ticket($conn, $jobId, $tid);
    }
    hd_recalc_overdue_for_ticket($conn, $tid);
    return $tid;
}

function hd_table_exists($conn, $tableName)
{
    $tbl = $conn->real_escape_string($tableName);
    $row = $conn->query("SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '$tbl'")->fetch_assoc();
    return $row && (int) $row['c'] > 0;
}

function hd_copy_scheduled_attachments_to_ticket($conn, $scheduledJobId, $ticketId)
{
    if (!hd_table_exists($conn, 'helpdesk_scheduled_attachments')) {
        return;
    }
    $jid = (int) $scheduledJobId;
    $tid = (int) $ticketId;
    $rows = $conn->query("SELECT filename, stored_path, size_bytes FROM helpdesk_scheduled_attachments WHERE scheduled_job_id = $jid");
    if (!$rows) {
        return;
    }
    while ($a = $rows->fetch_assoc()) {
        $filename = $a['filename'] ?? 'attachment.bin';
        $stored = $a['stored_path'] ?? '';
        if ($stored === '') {
            continue;
        }
        $src = dirname(__DIR__) . '/' . ltrim($stored, '/');
        if (!is_file($src)) {
            continue;
        }
        if (!is_dir(HELPDESK_UPLOAD_DIR)) {
            mkdir(HELPDESK_UPLOAD_DIR, 0755, true);
        }
        $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($filename));
        $destName = $tid . '_sched_' . time() . '_' . $safe;
        $destAbs = HELPDESK_UPLOAD_DIR . '/' . $destName;
        if (!copy($src, $destAbs)) {
            continue;
        }
        $rel = 'uploads/' . $destName;
        $sz = (int) filesize($destAbs);
        $stmt = $conn->prepare('INSERT INTO helpdesk_attachments (ticket_id, filename, stored_path, size_bytes) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('issi', $tid, $safe, $rel, $sz);
        $stmt->execute();
        $stmt->close();
    }
}

function hd_ui_css()
{
    return '<style>
        body{background:#eef3f9;font-family:Inter,Arial,sans-serif;color:#1f2a37}
        .hd-page-title{font-size:28px;font-weight:800;color:#153450;margin:0}
        .hd-page-subtitle{color:#60748a;margin-top:4px}
        .hd-card{border:0;border-radius:12px;box-shadow:0 8px 24px rgba(16,24,40,.08)}
        .hd-card .card-header{background:#fff;border-bottom:1px solid #e8eef5;font-weight:700}
        .hd-toolbar{display:flex;gap:8px;align-items:center;justify-content:space-between;flex-wrap:wrap}
        .hd-badge{padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700}
        .hd-badge-open{background:#e7f4ff;color:#14508f}
        .hd-badge-hold{background:#fff4df;color:#8a5a00}
        .hd-badge-closed{background:#e7f8ef;color:#166534}
        .hd-badge-overdue{background:#fee2e2;color:#b91c1c}
        .hd-section-title{font-size:18px;font-weight:700;color:#153450}
        .table thead th{background:#f8fbff;color:#2a3f56;font-weight:700}
        .btn-primary{font-weight:600}
    </style>';
}

function hd_status_badge_class($status, $isOverdue = false)
{
    if ($isOverdue) {
        return 'hd-badge hd-badge-overdue';
    }
    if ($status === 'closed') {
        return 'hd-badge hd-badge-closed';
    }
    if ($status === 'on_hold') {
        return 'hd-badge hd-badge-hold';
    }
    return 'hd-badge hd-badge-open';
}
