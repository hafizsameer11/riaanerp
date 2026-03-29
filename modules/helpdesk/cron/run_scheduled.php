<?php
/**
 * Overdue refresh + scheduled helpdesk jobs. Run via cron (e.g. every minute with pop_sync).
 * Use PHP 8.2 or 8.3 CLI: php modules/helpdesk/cron/run_scheduled.php
 */
$root = dirname(__DIR__, 3);
require_once $root . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

hd_refresh_all_overdue($conn);

$now = date('Y-m-d H:i:s');
$res = $conn->query("SELECT * FROM helpdesk_scheduled_jobs WHERE is_active = 1 AND next_run_at <= '" . $conn->real_escape_string($now) . "'");
while ($job = $res->fetch_assoc()) {
    hd_create_ticket_from_scheduled_job($conn, $job);
    $next = hd_schedule_next_run($job['schedule_type'], (int) $job['schedule_interval_days'], time());
    $jid = (int) $job['id'];
    if ($next === null) {
        $conn->query('UPDATE helpdesk_scheduled_jobs SET is_active = 0, last_run_at = NOW() WHERE id = ' . $jid);
    } else {
        $nesc = $conn->real_escape_string($next);
        $conn->query("UPDATE helpdesk_scheduled_jobs SET last_run_at = NOW(), next_run_at = '$nesc' WHERE id = $jid");
    }
}

echo "OK\n";
