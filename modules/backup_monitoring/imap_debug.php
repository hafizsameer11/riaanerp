<?php
echo "<pre>";

// PHP basic info
echo "PHP Version:\n";
echo phpversion() . "\n\n";

// SAPI (Apache vs CLI)
echo "PHP SAPI:\n";
echo php_sapi_name() . "\n\n";

// Check IMAP function
echo "imap_open() exists?\n";
var_dump(function_exists('imap_open'));
echo "\n\n";

// Loaded extensions
echo "Is IMAP extension loaded?\n";
var_dump(extension_loaded('imap'));
echo "\n\n";

// php.ini file used
echo "Loaded php.ini:\n";
echo php_ini_loaded_file() . "\n\n";

// Scan ini files
echo "Additional ini files:\n";
print_r(php_ini_scanned_files());
echo "\n\n";

// Disabled functions
echo "Disabled functions:\n";
echo ini_get('disable_functions') ?: 'NONE';
echo "\n\n";

// Test IMAP call safely
echo "Trying imap_open():\n";
if (function_exists('imap_open')) {
    echo "imap_open is callable ✅\n";
} else {
    echo "imap_open is NOT callable ❌\n";
}

echo "</pre>";
