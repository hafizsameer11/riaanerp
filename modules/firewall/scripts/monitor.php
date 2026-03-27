<?php
require_once __DIR__ . '/../includes/functions.php';

echo "========================================\n";
echo "Firewall Monitor Started (Router-based Auto Failback)\n";
echo "========================================\n";
echo date('Y-m-d H:i:s') . " - Monitoring active...\n\n";

$siteLastBatch = [];

while (true) {

    $sites = db()->query("SELECT * FROM sites WHERE monitoring_enabled = 1")->fetchAll();

    if (empty($sites)) {
        echo "No monitored sites.\n";
        sleep(10);
        continue;
    }

    echo "[" . date('H:i:s') . "] Checking " . count($sites) . " site(s)...\n";

    foreach ($sites as $s) {

        $siteId   = $s['id'];
        $count    = $s['ping_count'] ?: PING_DEFAULT_COUNT;
        $interval = $s['ping_interval_sec'] ?: PING_DEFAULT_BATCH_INTERVAL_SEC;
        $oldStatus = $s['status'];

        $now = time();
        $lastBatch = $siteLastBatch[$siteId] ?? 0;

        // Wait between batches
        if ($lastBatch > 0 && ($now - $lastBatch < $interval)) {
            continue;
        }

        echo "  → {$s['name']} (WAN Check: {$s['primary_ip']})\n";
        echo "      Pinging {$count} times... ";

        // Primary WAN check
        $ping = pingHost($s['primary_ip'], $count);

        // Calculate failure percentage
        $failureRate = ($ping['failures'] / $count) * 100;
        
        // Status logic:
        // - All pings succeed (100%) → UP
        // - All pings fail (0% success) → DOWN
        // - More than 50% of pings fail → DEGRADED
        // - 50% or less fail → UP
        if ($ping['successes'] === $count) {
            $status = "UP";
            $icon = "✅";
        } elseif ($ping['successes'] === 0) {
            $status = "DOWN";
            $icon = "❌";
        } elseif ($failureRate > 50) {
            // More than 50% of pings failed
            $status = "DEGRADED";
            $icon = "⚠️";
        } else {
            // 50% or less failed - still considered UP
            $status = "UP";
            $icon = "✅";
        }

        echo "{$icon} {$ping['successes']}/{$count}";
        if ($status !== $oldStatus) echo "  [{$oldStatus} → {$status}]";
        echo "\n";

        // Log result
        logPingCheck(
            $siteId, $count, $interval,
            $ping['successes'], $ping['failures'],
            $status, $status !== $oldStatus, $oldStatus
        );

        // Update site values
        db()->prepare("
            UPDATE sites
            SET status=?, last_ping_time=NOW(), last_ping_successes=?, last_ping_failures=?
            WHERE id=?
        ")->execute([$status, $ping['successes'], $ping['failures'], $siteId]);

        $siteLastBatch[$siteId] = $now;

        // ============================================================
        //  FAILOVER — PRIMARY WAN DOWN
        // ============================================================
        if ($status === "DOWN" && $oldStatus !== "DOWN") {

            echo "      ⚠️ WAN Down → Triggering FAILOVER...\n";

            $sshPass = dec($s['ssh_password_enc']);

            // SSH ALWAYS via secondary IP
            $res = runSshCommand(
                $s['secondary_ip'],
                $s['ssh_username'],
                $sshPass,
                $s['failover_command']
            );

            if ($res['ok']) {
                echo "      ✅ Failover executed.\n";
            } else {
                echo "      ❌ Failover FAILED: {$res['output']}\n";
            }

            logAction(
                $siteId,
                "auto_failover",
                "system",
                $oldStatus,
                "DOWN",
                $res['output'],
                $res['ok'] ? 1 : 0,
                $s['failover_command']
            );

            sendNotificationEmail(
                getEmails(),
                "AUTO FAILOVER: {$s['name']}",
                $res['output']
            );

            echo "      📧 Failover notification sent.\n";
        }

        // ============================================================
        //  AUTO-FAILBACK (NEW REQUIREMENT)
        //  Trigger failback when ROUTER becomes reachable
        // ============================================================
        if ($status === "DOWN" && !empty($s['primary_router_ip'])) {

            echo "      🔍 Checking Router IP: {$s['primary_router_ip']}...\n";

            $routerPing = pingHost($s['primary_router_ip'], 1);

            if ($routerPing['successes'] > 0) {

                echo "      🔄 Router reachable → Triggering AUTO FAILBACK...\n";

                $sshPass = dec($s['ssh_password_enc']);

                // ALWAYS SSH via secondary_ip
                $res = runSshCommand(
                    $s['secondary_ip'],
                    $s['ssh_username'],
                    $sshPass,
                    $s['failback_command']
                );

                if ($res['ok']) {
                    echo "      ✅ Failback executed.\n";
                } else {
                    echo "      ❌ Failback FAILED: {$res['output']}\n";
                }

                logAction(
                    $siteId,
                    "auto_failback",
                    "system",
                    "DOWN",
                    "UP",
                    $res['output'],
                    $res['ok'] ? 1 : 0,
                    $s['failback_command']
                );

                sendNotificationEmail(
                    getEmails(),
                    "AUTO FAILBACK: {$s['name']}",
                    $res['output']
                );

                echo "      📧 Failback notification sent.\n";

                // Update status
                db()->prepare("UPDATE sites SET status='UP' WHERE id=?")
                    ->execute([$siteId]);
            }
        }
    }

    echo "\n";
    sleep(5);
}
