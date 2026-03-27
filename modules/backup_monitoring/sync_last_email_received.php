<?php
/**
 * Sync last_email_received_at with latest_backup_at for existing records
 * This fixes records where last_email_received_at is NULL or doesn't match latest_backup_at
 */

// Use the same connection method as backend.php
include_once dirname(dirname(__DIR__)) . '/config.php';
// $conn should be available from config.php

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Syncing last_email_received_at with latest_backup_at...\n\n";

// Update records where last_email_received_at is NULL but latest_backup_at is not
$update1 = "UPDATE backup_jobs 
            SET last_email_received_at = latest_backup_at 
            WHERE latest_backup_at IS NOT NULL 
            AND (last_email_received_at IS NULL OR last_email_received_at != latest_backup_at)";

if ($conn->query($update1)) {
    $affected1 = $conn->affected_rows;
    echo "✓ Updated $affected1 records where last_email_received_at was NULL or didn't match latest_backup_at\n";
} else {
    echo "✗ Error updating records: " . $conn->error . "\n";
}

// Also set is_active = 1 for records that have recent backups (within retention period)
// This ensures active jobs show as active
$update2 = "UPDATE backup_jobs 
            SET is_active = 1 
            WHERE latest_backup_at IS NOT NULL 
            AND latest_backup_at >= DATE_SUB(NOW(), INTERVAL retention_days DAY)
            AND is_active = 0";

if ($conn->query($update2)) {
    $affected2 = $conn->affected_rows;
    echo "✓ Activated $affected2 jobs with recent backups\n";
} else {
    echo "✗ Error activating jobs: " . $conn->error . "\n";
}

$conn->close();
echo "\nSync completed!\n";
