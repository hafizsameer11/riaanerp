<?php

$db_host = "localhost";
$db_user = "clientzone_user";
$db_pass = "S@utech2024!";
$db_name = "clientzone";

include_once '../../config.php'; // Ensure this path is correct



header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=spla_licensing_records.csv');

// Open output stream
$output = fopen('php://output', 'w');

// Add CSV header
fputcsv($output, ['#', 'Client', 'Microsoft Product', 'Quantity', 'Notes']);

// Fetch records
$result = $conn->query("
    SELECT id, client, ms_products, quantity, notes
    FROM spla_licenses
    WHERE is_deleted = 0
    ORDER BY client ASC
");

// Add rows to CSV
$i = 1;
while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $i++,
        $row['client'],
        $row['ms_products'],
        $row['quantity'],
        $row['notes']
    ]);
}

// Close output stream
fclose($output);
?>