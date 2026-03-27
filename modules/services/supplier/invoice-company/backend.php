<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$db_host = "localhost";
    $db_user = "clientzone_user";
    $db_pass = "S@utech2024!";
    $db_name = "clientzone";

include_once '../../../config.php'; // Ensure this path is correct
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$action = $_POST['action'] ?? '';

if ($action == 'add') {
    $name = $conn->real_escape_string($_POST['company_name']);
    $address = $conn->real_escape_string($_POST['address']);
    $vat_number = $conn->real_escape_string($_POST['vat_number']);
    $registration_number = $conn->real_escape_string($_POST['registration_number']);
    $contact_details = $conn->real_escape_string($_POST['contact_details']);
    $vat_rate = (float)$_POST['vat_rate'];

    $result = $conn->query("INSERT INTO billing_invoice_companies (company_name, address, vat_number, registration_number, contact_details, vat_rate) 
                            VALUES ('$name', '$address', '$vat_number', '$registration_number', '$contact_details', $vat_rate)");
    echo $result ? 'success' : 'error';
    exit;
}

if ($action == 'edit') {
    $id = (int)$_POST['id'];
    $name = $conn->real_escape_string($_POST['company_name']);
    $address = $conn->real_escape_string($_POST['address']);
    $vat_number = $conn->real_escape_string($_POST['vat_number']);
    $registration_number = $conn->real_escape_string($_POST['registration_number']);
    $contact_details = $conn->real_escape_string($_POST['contact_details']);
    $vat_rate = (float)$_POST['vat_rate'];

    $result = $conn->query("UPDATE billing_invoice_companies 
                            SET company_name = '$name', 
                                address = '$address', 
                                vat_number = '$vat_number', 
                                registration_number = '$registration_number', 
                                contact_details = '$contact_details', 
                                vat_rate = $vat_rate 
                            WHERE id = $id");
    echo $result ? 'success' : 'error';
    exit;
}

if ($action == 'delete') {
    $id = (int)$_POST['id'];
    $result = $conn->query("DELETE FROM billing_invoice_companies WHERE id = $id");
    echo $result ? 'success' : 'error';
    exit;
}

if ($action == 'fetch') {
    $id = (int)$_POST['id'];
    $result = $conn->query("SELECT * FROM billing_invoice_companies WHERE id = $id LIMIT 1");
    echo json_encode($result->fetch_assoc());
    exit;
}
?>
