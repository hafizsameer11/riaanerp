<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<pre>POST DATA:\n";
    print_r($_POST);
    echo "\nFILES:\n";
    print_r($_FILES);
    echo "</pre>";
}

ini_set('display_errors', 1);

// Declare all expected variables upfront
$viewing = false;
$editing = false;
$view_data = null;
$support = null;
$docs = null;
$clients = null;
$id = null;

include_once '../../config.php'; // Ensure this path is correct
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle new client creation
if (isset($_POST['save_client'])) {
    $stmt = $conn->prepare("INSERT INTO clients (client_name, email, contact_person, office_number, accounts_contact, accounts_email, address, notes, vat_number, registration_number, billing_type, status, sales_person, billing_country, currency, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

    if (!$stmt) {
        die("❌ SQL Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("sssssssssssssss",
        $_POST['client_name'],
        $_POST['email'],
        $_POST['contact_person'],
        $_POST['office_number'],
        $_POST['accounts_contact'],
        $_POST['accounts_email'],
        $_POST['address'],
        $_POST['notes'],
        $_POST['vat_number'],
        $_POST['registration_number'],
        $_POST['billing_type'],
        $_POST['status'],
        $_POST['sales_person'],
        $_POST['billing_country'],
        $_POST['currency']
    );

    $stmt->execute();

    if ($stmt->affected_rows < 1) {
        die("❌ Insert failed. MySQL error: " . $stmt->error);
    }

    header("Location: clientinfo.php#submitted");
    exit;
}

// ... (rest of code remains unchanged as previously discussed)
?>