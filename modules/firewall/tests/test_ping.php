<?php
/**
 * Ping Test Script
 * Use this to test if ping functionality works
 */

require_once __DIR__ . '/../includes/functions.php';

echo "========================================\n";
echo "Ping Test\n";
echo "========================================\n\n";

// Test IPs
$test_ips = [
    '8.8.8.8' => 'Google DNS (should always work)',
    '127.0.0.1' => 'Localhost (should always work)',
    '192.168.255.255' => 'Non-existent IP (should fail)',
];

foreach ($test_ips as $ip => $description) {
    echo "Testing: $ip ($description)\n";
    echo "Ping count: 5, Interval: 2 seconds\n";
    
    $result = pingHost($ip, 5, 2);
    
    echo "Results:\n";
    echo "  Successes: " . $result['successes'] . "\n";
    echo "  Failures: " . $result['failures'] . "\n";
    
    if ($result['successes'] === 5) {
        echo "  Status: ✅ UP (all pings successful)\n";
    } elseif ($result['successes'] === 0) {
        echo "  Status: ❌ DOWN (all pings failed)\n";
    } else {
        echo "  Status: ⚠️  DEGRADED (some pings failed)\n";
    }
    
    echo "\n";
}

echo "========================================\n";
echo "Test Complete\n";
echo "========================================\n";

