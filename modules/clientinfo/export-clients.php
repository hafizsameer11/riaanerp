<?php
require_once '../billing/xlsxwriter.class.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database Connection
include_once '../../config.php';

// Build search query if search parameter exists
$where = '';
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchTerm = $conn->real_escape_string($_GET['search']);
    $where = "WHERE client_name LIKE '%$searchTerm%' OR contact_person LIKE '%$searchTerm%'";
}

// Fetch client records
$query = "SELECT * FROM clients $where ORDER BY client_name ASC";
$result = $conn->query($query);

// Setup Excel
$writer = new XLSXWriter();
$writer->setAuthor('Sautech');

// Header Columns - All client fields
$header = [
    'Client Name' => 'string',
    'Mobile Number' => 'string',
    'Email' => 'string',
    'Contact Person' => 'string',
    'Office Number' => 'string',
    'Accounts Contact' => 'string',
    'Accounts Email' => 'string',
    'Address' => 'string',
    'VAT Number' => 'string',
    'Registration Number' => 'string',
    'Billing Type' => 'string',
    'Status' => 'string',
    'Sales Person' => 'string',
    'Billing Country' => 'string',
    'Currency' => 'string',
    'Currency Symbol' => 'string',
    'Notes' => 'string',
    'Created At' => 'date',
];

// Start Sheet
$writer->writeSheetHeader('Clients', $header);

// Write data rows
while ($row = $result->fetch_assoc()) {
    $writer->writeSheetRow('Clients', [
        $row['client_name'] ?? '',
        $row['number'] ?? '',
        $row['email'] ?? '',
        $row['contact_person'] ?? '',
        $row['office_number'] ?? '',
        $row['accounts_contact'] ?? '',
        $row['accounts_email'] ?? '',
        $row['address'] ?? '',
        $row['vat_number'] ?? '',
        $row['registration_number'] ?? '',
        $row['billing_type'] ?? '',
        $row['status'] ?? '',
        $row['sales_person'] ?? '',
        $row['billing_country'] ?? '',
        $row['currency'] ?? '',
        $row['currency_symbol'] ?? '',
        $row['notes'] ?? '',
        $row['created_at'] ?? '',
    ]);
}

// Output Excel File
$filename = "clients_export_" . date('Y-m-d_H-i-s') . ".xlsx";

header('Content-disposition: attachment; filename="' . $filename . '"');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Transfer-Encoding: binary');
header('Cache-Control: must-revalidate');
header('Pragma: public');

echo $writer->writeToString();
exit;



