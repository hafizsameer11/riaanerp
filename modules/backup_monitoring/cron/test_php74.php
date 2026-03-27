<?php
/**
 * Test Script to Verify PHP 7.4 Usage and IMAP Availability
 * This script checks that PHP 7.4 is being used and IMAP is available
 */

echo "========================================\n";
echo "PHP 7.4 Verification Test\n";
echo "========================================\n\n";

// Check PHP version
$phpVersion = phpversion();
echo "1. PHP Version: $phpVersion\n";

// Check if it's PHP 7.4
if (version_compare($phpVersion, '7.4.0', '>=') && version_compare($phpVersion, '7.5.0', '<')) {
    echo "   ✓ PHP 7.4 detected (correct version)\n";
} else {
    echo "   ✗ ERROR: Not PHP 7.4! Current version: $phpVersion\n";
    echo "   This script must run with PHP 7.4\n";
    exit(1);
}

// Check PHP binary path
if (defined('PHP_BINARY')) {
    echo "\n2. PHP Binary: " . PHP_BINARY . "\n";
} else {
    echo "\n2. PHP Binary: Not defined\n";
}

// Check IMAP extension
echo "\n3. IMAP Extension Check:\n";
if (extension_loaded('imap')) {
    echo "   ✓ IMAP extension is loaded\n";
} else {
    echo "   ✗ ERROR: IMAP extension is NOT loaded!\n";
    exit(1);
}

// Check IMAP functions
echo "\n4. IMAP Functions Check:\n";
$requiredFunctions = [
    'imap_open',
    'imap_close',
    'imap_search',
    'imap_fetch_overview',
    'imap_body',
    'imap_fetchbody',
    'imap_fetchstructure',
    'imap_setflag_full',
    'imap_last_error',
    'imap_utf8'
];

$allFunctionsAvailable = true;
foreach ($requiredFunctions as $func) {
    if (function_exists($func)) {
        echo "   ✓ $func() available\n";
    } else {
        echo "   ✗ $func() NOT available\n";
        $allFunctionsAvailable = false;
    }
}

if (!$allFunctionsAvailable) {
    echo "\n   ERROR: Some IMAP functions are missing!\n";
    exit(1);
}

// Check loaded extensions
echo "\n5. Loaded Extensions:\n";
$extensions = get_loaded_extensions();
$imapFound = false;
foreach ($extensions as $ext) {
    if (strtolower($ext) === 'imap') {
        $imapFound = true;
        echo "   ✓ imap extension found in loaded extensions\n";
        break;
    }
}

if (!$imapFound) {
    echo "   ✗ ERROR: imap extension not found in loaded extensions\n";
    exit(1);
}

// Test IMAP connection capability (without actually connecting)
echo "\n6. IMAP Connection Test:\n";
echo "   ✓ IMAP functions are callable (ready for email connection)\n";

// Summary
echo "\n========================================\n";
echo "TEST RESULT: ✓ ALL CHECKS PASSED\n";
echo "PHP 7.4 is correctly configured with IMAP support\n";
echo "========================================\n";
exit(0);
