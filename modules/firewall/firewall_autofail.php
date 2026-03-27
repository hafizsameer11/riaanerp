<?php
/**
 * Database Update Script: Add Auto-Failback Fields
 * 
 * This script adds the following fields to the sites table:
 * - auto_failback_enabled (TINYINT) - Enable/disable auto-failback per site
 * - auto_failback_success_pings (INT) - Number of consecutive successful pings required
 * - consecutive_primary_successes (INT) - Tracks current consecutive success count
 * 
 * Run this script once to update your database schema.
 * Usage: Access via browser: http://your-domain/modules/firewall/firewall_autofail.php
 */

require_once __DIR__ . '/includes/config.php';

echo "========================================\n";
echo "Database Update: Auto-Failback Fields\n";
echo "========================================\n\n";

// Verify constants are defined
if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
    die("❌ ERROR: Database constants not defined in config.php\n");
}

try {
    // Direct database connection using credentials from config.php
    $host = DB_HOST;
    $dbname = DB_NAME;
    $user = DB_USER;
    $pass = DB_PASS;
    
    echo "[0/4] Connecting to database...\n";
    echo "      Host: " . $host . "\n";
    echo "      Database: " . $dbname . "\n";
    echo "      User: " . $user . "\n\n";
    
    $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "[1/4] Checking database connection...\n";
    $db->query("SELECT 1");
    echo "✅ Database connection successful\n\n";
    
    echo "[2/4] Adding new columns to sites table...\n";
    
    // Check if columns already exist and add them if they don't
    $existingColumns = $db->query("SHOW COLUMNS FROM sites")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('auto_failback_enabled', $existingColumns)) {
        $db->exec("ALTER TABLE sites ADD COLUMN auto_failback_enabled TINYINT(1) DEFAULT 0");
        echo "✅ Added column: auto_failback_enabled\n";
    } else {
        echo "⚠️  Column 'auto_failback_enabled' already exists. Skipping...\n";
    }
    
    if (!in_array('auto_failback_success_pings', $existingColumns)) {
        $db->exec("ALTER TABLE sites ADD COLUMN auto_failback_success_pings INT DEFAULT 30");
        echo "✅ Added column: auto_failback_success_pings\n";
    } else {
        echo "⚠️  Column 'auto_failback_success_pings' already exists. Skipping...\n";
    }
    
    if (!in_array('consecutive_primary_successes', $existingColumns)) {
        $db->exec("ALTER TABLE sites ADD COLUMN consecutive_primary_successes INT DEFAULT 0");
        echo "✅ Added column: consecutive_primary_successes\n";
    } else {
        echo "⚠️  Column 'consecutive_primary_successes' already exists. Skipping...\n";
    }
    
    echo "\n[3/4] Updating existing records with default values...\n";
    
    $stmt = $db->prepare("UPDATE sites SET auto_failback_enabled = 0 WHERE auto_failback_enabled IS NULL");
    $stmt->execute();
    $affected = $stmt->rowCount();
    echo "✅ Updated {$affected} record(s) for auto_failback_enabled\n";
    
    $stmt = $db->prepare("UPDATE sites SET auto_failback_success_pings = 30 WHERE auto_failback_success_pings IS NULL");
    $stmt->execute();
    $affected = $stmt->rowCount();
    echo "✅ Updated {$affected} record(s) for auto_failback_success_pings\n";
    
    $stmt = $db->prepare("UPDATE sites SET consecutive_primary_successes = 0 WHERE consecutive_primary_successes IS NULL");
    $stmt->execute();
    $affected = $stmt->rowCount();
    echo "✅ Updated {$affected} record(s) for consecutive_primary_successes\n";
    
    echo "\n[4/4] Verifying columns...\n";
    
    $columns = $db->query("SHOW COLUMNS FROM sites WHERE Field IN ('auto_failback_enabled', 'auto_failback_success_pings', 'consecutive_primary_successes')")->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($columns) === 3) {
        echo "✅ All columns verified successfully:\n";
        foreach ($columns as $col) {
            echo "   - {$col['Field']} ({$col['Type']}) - Default: {$col['Default']}\n";
        }
    } else {
        echo "⚠️  Warning: Expected 3 columns, found " . count($columns) . "\n";
    }
    
    echo "\n========================================\n";
    echo "✅ Database update completed successfully!\n";
    echo "========================================\n";
    echo "\nYou can now use the auto-failback feature in the Firewall Monitor.\n";
    echo "Edit any site and enable 'Automatic Failback' in the settings.\n\n";
    
} catch (PDOException $e) {
    echo "\n❌ Database Error: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n\n";
    
    // Provide helpful troubleshooting based on error code
    if ($e->getCode() == 1698 || strpos($e->getMessage(), 'Access denied') !== false) {
        echo "═══════════════════════════════════════════════════════════\n";
        echo "TROUBLESHOOTING: Database Access Denied\n";
        echo "═══════════════════════════════════════════════════════════\n\n";
        echo "Credentials being used:\n";
        echo "  Host: " . (defined('DB_HOST') ? DB_HOST : 'NOT DEFINED') . "\n";
        echo "  Database: " . (defined('DB_NAME') ? DB_NAME : 'NOT DEFINED') . "\n";
        echo "  User: " . (defined('DB_USER') ? DB_USER : 'NOT DEFINED') . "\n";
        echo "  Password: " . (defined('DB_PASS') && DB_PASS ? "***" : "(empty or not defined)") . "\n\n";
        echo "This error means the database credentials in config.php are incorrect.\n\n";
        echo "SOLUTION:\n";
        echo "1. Open: modules/firewall/includes/config.php on your LIVE server\n";
        echo "2. Make sure these lines are uncommented and have correct values:\n\n";
        echo "   define('DB_HOST', 'localhost');\n";
        echo "   define('DB_NAME', 'firewall_monitor');\n";
        echo "   define('DB_USER', 'firewall_user');\n";
        echo "   define('DB_PASS', 'StrongPass#2025');\n\n";
        echo "3. Save the file and run this script again.\n\n";
        echo "NOTE: Make sure you're editing the config.php file on the LIVE server,\n";
        echo "      not the local development version!\n\n";
    } elseif ($e->getCode() == 1049) {
        echo "═══════════════════════════════════════════════════════════\n";
        echo "TROUBLESHOOTING: Database Not Found\n";
        echo "═══════════════════════════════════════════════════════════\n\n";
        echo "The database does not exist.\n\n";
        echo "SOLUTION:\n";
        echo "1. Create the database first\n";
        echo "2. Or update DB_NAME in config.php to match your existing database.\n\n";
    } elseif ($e->getCode() == 2002) {
        echo "═══════════════════════════════════════════════════════════\n";
        echo "TROUBLESHOOTING: Cannot Connect to Database Server\n";
        echo "═══════════════════════════════════════════════════════════\n\n";
        echo "Cannot connect to database server.\n\n";
        echo "SOLUTION:\n";
        echo "1. Check if MySQL/MariaDB is running\n";
        echo "2. Verify DB_HOST in config.php is correct\n";
        echo "3. Check firewall settings\n\n";
    }
    
    exit(1);
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n\n";
    exit(1);
}

