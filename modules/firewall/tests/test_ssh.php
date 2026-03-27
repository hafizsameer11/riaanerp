<?php
/**
 * SSH Connection Test Script
 * Use this to test if SSH connection works before using in the main system
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

echo "========================================\n";
echo "SSH Connection Test\n";
echo "========================================\n\n";

// Test configuration
$test_ip = '127.0.0.1';  // Change to your server IP
$test_user = 'your-username';  // Change to your SSH username
$test_pass = 'your-password';  // Change to your SSH password
$test_command = 'echo "SSH Test Successful" && date && whoami';

echo "Testing SSH connection...\n";
echo "IP: $test_ip\n";
echo "User: $test_user\n";
echo "Command: $test_command\n\n";

// Test SSH connection
$result = runSshCommand($test_ip, $test_user, $test_pass, $test_command);

if ($result['ok']) {
    echo "✅ SSH Connection SUCCESSFUL!\n";
    echo "Output:\n";
    echo $result['output'] . "\n";
} else {
    echo "❌ SSH Connection FAILED!\n";
    echo "Error: " . $result['output'] . "\n";
    echo "\nTroubleshooting:\n";
    
    // Check if error is about phpseclib not found
    if (strpos($result['output'], 'phpseclib not found') !== false) {
        echo "1. Install phpseclib via Composer:\n";
        echo "   composer require phpseclib/phpseclib\n";
        echo "   composer install\n";
        echo "2. Verify vendor/autoload.php exists\n";
        echo "3. Check that phpseclib is in vendor/phpseclib/phpseclib/\n";
    } else {
        echo "1. Verify phpseclib is installed: composer show phpseclib/phpseclib\n";
        echo "2. Test SSH manually: ssh $test_user@$test_ip\n";
        echo "3. Check firewall allows port " . SSH_PORT . "\n";
        echo "4. Verify SSH credentials are correct\n";
        echo "5. Check network connectivity to $test_ip\n";
    }
}

echo "\n========================================\n";
echo "Test Complete\n";
echo "========================================\n";

