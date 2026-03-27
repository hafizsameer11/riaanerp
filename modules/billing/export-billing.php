<?php
require_once 'xlsxwriter.class.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database Connection
include_once '../config.php'; // Ensure this path is correct

// Include XLSXWriter
require_once 'xlsxwriter.class.php';

// Fetch billing records
$query = "
    SELECT b.*, 
           c.client_name, 
           s.supplier_name AS supplier_name, 
           st.service_type_name, 
           sc.category_name
    FROM billing_items b
    LEFT JOIN clients c ON b.client_id = c.id
    LEFT JOIN billing_suppliers s ON b.supplier_id = s.id
    LEFT JOIN billing_service_types st ON b.service_type_id = st.id
    LEFT JOIN billing_service_categories sc ON b.service_category_id = sc.id
    WHERE b.is_deleted = 0
    ORDER BY b.created_at DESC
";

$result = $conn->query($query);

// Setup Excel
$writer = new XLSXWriter();
$writer->setAuthor('Sautech');

// Header Columns
$header = [
    'Client' => 'string',
    'Supplier' => 'string',
    'Service Type' => 'string',
    'Service Category' => 'string',
    'Description' => 'string',
    'Quantity' => 'integer',
    'Unit Price' => 'price',
    'VAT Rate' => '0.00%',
    'Subtotal' => 'price',
    'Total (with VAT)' => 'price',
    'Frequency' => 'string',
    'Start Date' => 'date',
    'End Date' => 'date',
];

// Start Sheet
$writer->writeSheetHeader('Billing Items', $header);

while ($row = $result->fetch_assoc()) {
    $subtotal = $row['qty'] * $row['unit_price'];
    $vat = ($row['vat_rate'] / 100) * $subtotal;
    $total = $subtotal + $vat;

    $writer->writeSheetRow('Billing Items', [
        $row['client_name'],
        $row['supplier_name'],
        $row['service_type_name'],
        $row['category_name'],
        $row['description'],
        (int)$row['qty'],
        (float)$row['unit_price'],
        (float)($row['vat_rate'] / 100),
        (float)$subtotal,
        (float)$total,
        $row['frequency'],
        $row['start_date'],
        $row['end_date'] ?? '',
    ]);
}

// Output Excel File
$filename = "billing_items_" . date('Y-m-d_H-i-s') . ".xlsx";

header('Content-disposition: attachment; filename="' . $filename . '"');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Transfer-Encoding: binary');
header('Cache-Control: must-revalidate');
header('Pragma: public');

$writer->writeToStdOut();
exit;
?>
