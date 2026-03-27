<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// DB Connection
$db_host = "localhost";
    $db_user = "clientzone_user";
    $db_pass = "S@utech2024!";
    $db_name = "clientzone";

include_once '../../config.php'; // Ensure this path is correct
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set CSV headers
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="billing_report.csv"');

$output = fopen('php://output', 'w');

// CSV Header
fputcsv($output, [
    'Client Name', 'Description', 'Quantity', 'Unit Price', 'VAT Rate', 
    'VAT Amount', 'Total', 'Invoice Type', 'Currency', 'Frequency', 'Start Date', 'End Date'
]);

// Filtering logic from POST
$where = [];
if (!empty($_POST['client_id'])) {
    $where[] = "b.client_id = " . (int)$_POST['client_id'];
}
if (!empty($_POST['invoice_type'])) {
    $where[] = "b.invoice_type = '" . $conn->real_escape_string($_POST['invoice_type']) . "'";
}
if (!empty($_POST['currency'])) {
    $where[] = "b.currency = '" . $conn->real_escape_string($_POST['currency']) . "'";
}
if (!empty($_POST['frequency'])) {
    $where[] = "b.frequency = '" . $conn->real_escape_string($_POST['frequency']) . "'";
}

$filterSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$query = $conn->query("
    SELECT b.*, c.client_name 
    FROM billing_items b
    LEFT JOIN clients c ON b.client_id = c.id
    $filterSql
    ORDER BY b.id DESC
");

if ($query) {
    while ($row = $query->fetch_assoc()) {
        $subtotal = $row['qty'] * $row['unit_price'];
        $vatAmount = ($row['vat_rate'] / 100) * $subtotal;
        $total = $subtotal + $vatAmount;

        fputcsv($output, [
            $row['client_name'],
            $row['description'],
            $row['qty'],
            number_format($row['unit_price'], 2),
            number_format($row['vat_rate'], 2),
            number_format($vatAmount, 2),
            number_format($total, 2),
            $row['invoice_type'],
            $row['currency'],
            $row['frequency'],
            $row['start_date'],
            $row['end_date'],
        ]);
    }
} else {
    die("❌ Query failed: " . $conn->error);
}

fclose($output);
exit;
