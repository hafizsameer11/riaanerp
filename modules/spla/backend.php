<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database Connection
$db_host = "localhost";
    $db_user = "clientzone_user";
    $db_pass = "S@utech2024!";
    $db_name = "clientzone";

include_once '../../config.php'; // Ensure this path is correct

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle Add Manual License
if (isset($_POST['add_manual_license'])) {
    $client_name = $_POST['client_name'] ?? '';
    $ms_products = $_POST['ms_products'] ?? '';
    $quantity = (int)($_POST['quantity'] ?? 1);
    $note = $_POST['note'] ?? '';

    $stmt = $conn->prepare("INSERT INTO spla_licenses (type, client, ms_products, quantity, notes, is_deleted) VALUES ('license', ?, ?, ?, ?, 0)");
    if (!$stmt) die("Prepare failed: " . $conn->error);

    $stmt->bind_param("ssis", $client_name, $ms_products, $quantity, $note);

    if ($stmt->execute()) echo "success";
    else echo "error";
    exit;
}

// Handle Edit Manual License
if (isset($_POST['edit_manual_license'])) {
    $id = (int)($_POST['id'] ?? 0);
    $ms_products = $_POST['ms_products'] ?? '';
    $quantity = (int)($_POST['quantity'] ?? 1);
    $note = $_POST['note'] ?? '';

    $stmt = $conn->prepare("UPDATE spla_licenses SET ms_products = ?, quantity = ?, notes = ? WHERE id = ?");
    if (!$stmt) die("Prepare failed: " . $conn->error);

    $stmt->bind_param("sisi", $ms_products, $quantity, $note, $id);

    if ($stmt->execute()) echo "success";
    else echo "error";
    exit;
}

// Handle Delete Manual License
if (isset($_POST['delete_manual_license'])) {
    $id = (int)($_POST['id'] ?? 0);
    $source = $_POST['source'] ?? '';

    if ($source === 'manual') {
        $stmt = $conn->prepare("UPDATE spla_licenses SET is_deleted = 1 WHERE id = ?");
    } elseif ($source === 'hosting') {
        $stmt = $conn->prepare("DELETE FROM hosting_assets WHERE id = ?");
    } else {
        echo "error";
        exit;
    }

    if (!$stmt) die("Prepare failed: " . $conn->error);

    $stmt->bind_param("i", $id);

    if ($stmt->execute()) echo "success";
    else echo "error";
    exit;
}

echo "error"; // If no valid action matched
exit;
?>
