<?php
/**
 * Database Migration Script for WIP Table Enum Updates
 * 
 * Run this file once by accessing it via URL:
 * http://your-domain/modules/billing/update_wip_enums.php
 * 
 * This will:
 * 1. Add 'Annually' to the terms enum
 * 2. Add 'monthly_billing' and 'invoiced' to the status enum
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once '../../config.php';

$success = [];
$errors = [];

// Get current enum values for terms
$terms_result = $conn->query("SHOW COLUMNS FROM wip WHERE Field = 'terms'");
$terms_column = $terms_result->fetch_assoc();
$current_terms_enum = $terms_column['Type'] ?? '';

// Get current enum values for status
$status_result = $conn->query("SHOW COLUMNS FROM wip WHERE Field = 'status'");
$status_column = $status_result->fetch_assoc();
$current_status_enum = $status_column['Type'] ?? '';

// Check if 'Annually' already exists in terms enum
$terms_has_annually = strpos($current_terms_enum, 'annually') !== false;

// Check if 'monthly_billing' and 'invoiced' already exist in status enum
$status_has_monthly_billing = strpos($current_status_enum, 'monthly_billing') !== false;
$status_has_invoiced = strpos($current_status_enum, 'invoiced') !== false;

// Step 1: Update terms enum to add 'Annually'
if (!$terms_has_annually) {
    // Extract existing enum values from enum('val1','val2') format
    preg_match("/enum\((.+)\)/i", $current_terms_enum, $matches);
    if (isset($matches[1])) {
        // Split by ',' and clean up quotes
        $enum_parts = preg_split("/','/", $matches[1]);
        $existing_terms = array_map(function($val) {
            return trim($val, " '");
        }, $enum_parts);
    } else {
        $existing_terms = ['once_off', 'monthly'];
    }
    
    // Add 'Annually' to the list if not already present
    if (!in_array('annually', $existing_terms)) {
        $existing_terms[] = 'annually';
    }
    $new_terms_enum = "enum('" . implode("','", $existing_terms) . "')";
    
    $sql1 = "ALTER TABLE `wip` MODIFY COLUMN `terms` $new_terms_enum NOT NULL DEFAULT 'once_off'";
    
    if ($conn->query($sql1)) {
        $success[] = "✅ Added 'annually' to 'terms' enum successfully";
    } else {
        $errors[] = "❌ Error updating 'terms' enum: " . $conn->error;
    }
} else {
    $success[] = "ℹ️ 'Annually' already exists in 'terms' enum";
}

// Step 2: Update status enum to add 'monthly_billing' and 'invoiced'
if (!$status_has_monthly_billing || !$status_has_invoiced) {
    // Extract existing enum values from enum('val1','val2') format
    preg_match("/enum\((.+)\)/i", $current_status_enum, $matches);
    if (isset($matches[1])) {
        // Split by ',' and clean up quotes
        $enum_parts = preg_split("/','/", $matches[1]);
        $existing_status = array_map(function($val) {
            return trim($val, " '");
        }, $enum_parts);
    } else {
        $existing_status = ['Quoted', 'Followed up', 'Declined', 'Approved'];
    }
    
    // Add new values if they don't exist
    if (!$status_has_monthly_billing && !in_array('Monthly Billing', $existing_status)) {
        $existing_status[] = 'Monthly Billing';
    }
    if (!$status_has_invoiced && !in_array('Invoiced', $existing_status)) {
        $existing_status[] = 'Invoiced';
    }
    
    $new_status_enum = "enum('" . implode("','", $existing_status) . "')";
    
    $sql2 = "ALTER TABLE `wip` MODIFY COLUMN `status` $new_status_enum DEFAULT 'Quoted'";
    
    if ($conn->query($sql2)) {
        $added = [];
        if (!$status_has_monthly_billing) $added[] = 'Monthly Billing';
        if (!$status_has_invoiced) $added[] = 'Invoiced';
        $success[] = "✅ Added '" . implode("' and '", $added) . "' to 'status' enum successfully";
    } else {
        $errors[] = "❌ Error updating 'status' enum: " . $conn->error;
    }
} else {
    $success[] = "ℹ️ 'Monthly Billing' and 'Invoiced' already exist in 'status' enum";
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>WIP Table Enum Migration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; background-color: #f5f5f5; }
        .container { max-width: 800px; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .info { color: #17a2b8; }
    </style>
</head>
<body>
    <div class="container">
        <h2>📊 WIP Table Enum Migration</h2>
        <hr>

<?php

// Display results
echo "<div class='mt-4'>";
echo "<h4>Migration Results:</h4>";

if (!empty($success)) {
    echo "<div class='alert alert-success'>";
    echo "<h5>✅ Success Messages:</h5>";
    echo "<ul>";
    foreach ($success as $msg) {
        echo "<li>" . htmlspecialchars($msg) . "</li>";
    }
    echo "</ul>";
    echo "</div>";
}

if (!empty($errors)) {
    echo "<div class='alert alert-danger'>";
    echo "<h5>❌ Error Messages:</h5>";
    echo "<ul>";
    foreach ($errors as $msg) {
        echo "<li>" . htmlspecialchars($msg) . "</li>";
    }
    echo "</ul>";
    echo "</div>";
}

// Final status
if (empty($errors)) {
    echo "<div class='alert alert-info mt-3'>";
    echo "<strong>🎉 Migration completed successfully!</strong><br>";
    echo "The WIP table enums have been updated. You can now use 'Annually' in terms and 'monthly_billing' and 'invoiced' in status.";
    echo "</div>";
} else {
    echo "<div class='alert alert-warning mt-3'>";
    echo "<strong>⚠️ Migration completed with some errors.</strong><br>";
    echo "Please review the errors above and fix them manually if needed.";
    echo "</div>";
}

echo "</div>";

// Show current database status
echo "<div class='mt-4'>";
echo "<h4>Current Database Status:</h4>";
echo "<table class='table table-bordered'>";
echo "<tr><th>Column</th><th>Current Enum Values</th><th>Status</th></tr>";

// Get updated enum values
$terms_result_after = $conn->query("SHOW COLUMNS FROM wip WHERE Field = 'terms'");
$terms_column_after = $terms_result_after->fetch_assoc();
$terms_enum_after = $terms_column_after['Type'] ?? 'N/A';

$status_result_after = $conn->query("SHOW COLUMNS FROM wip WHERE Field = 'status'");
$status_column_after = $status_result_after->fetch_assoc();
$status_enum_after = $status_column_after['Type'] ?? 'N/A';

echo "<tr><td>wip.terms</td><td><code>" . htmlspecialchars($terms_enum_after) . "</code></td><td>" . 
     (strpos($terms_enum_after, 'Annually') !== false ? "<span class='success'>✅ Contains 'Annually'</span>" : "<span class='error'>❌ Missing 'Annually'</span>") . "</td></tr>";

echo "<tr><td>wip.status</td><td><code>" . htmlspecialchars($status_enum_after) . "</code></td><td>" . 
     ((strpos($status_enum_after, 'monthly_billing') !== false && strpos($status_enum_after, 'invoiced') !== false) ? 
      "<span class='success'>✅ Contains 'monthly_billing' and 'invoiced'</span>" : 
      "<span class='error'>❌ Missing required values</span>") . "</td></tr>";

echo "</table>";
echo "</div>";

echo "<div class='mt-4'>";
echo "<a href='wip.php' class='btn btn-primary'>Go to WIP Module</a> ";
echo "<a href='javascript:location.reload()' class='btn btn-secondary'>Refresh This Page</a>";
echo "</div>";

$conn->close();
?>

    </div>
</body>
</html>
