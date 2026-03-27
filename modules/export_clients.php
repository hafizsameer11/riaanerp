<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// $db_host = "localhost";
//     $db_user = "clientzone_user";
//     $db_pass = "S@utech2024!";
//     $db_name = "clientzone";

// $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);


// if ($conn->connect_error) {
//     die("Connection failed: " . $conn->connect_error);
// }
include_once '../config.php'; // Ensure this path is correct
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="clients.csv"');

$output = fopen('php://output', 'w');

fputcsv($output, [
    'Client Name', 'Email', 'Contact Person', 'Office Number',
    'Accounts Contact', 'Accounts Email', 'Address', 'Notes', 
    'VAT Number', 'Registration Number', 'Billing Type', 'Status', 
    'Sales Person', 'Billing Country', 'Currency', 'Created At'
]);

$result = $conn->query("SELECT * FROM clients ORDER BY created_at DESC");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['client_name'], $row['email'], $row['contact_person'], 
            $row['office_number'], $row['accounts_contact'], $row['accounts_email'], 
            $row['address'], $row['notes'], $row['vat_number'], 
            $row['registration_number'], $row['billing_type'], $row['status'], 
            $row['sales_person'], $row['billing_country'], $row['currency'], $row['created_at']
        ]);
    }
} else {
    die("❌ Query failed: " . $conn->error);
}

fclose($output);
exit;

