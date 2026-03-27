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
    $service_type_name = $conn->real_escape_string($_POST['service_type_name']);
    $note = $conn->real_escape_string($_POST['note']);

    $insert = $conn->query("
        INSERT INTO billing_service_types (service_type_name, note)
        VALUES ('$service_type_name', '$note')
    ");

    echo $insert ? 'success' : 'error';
    exit;
}

if ($action == 'edit') {
    $id = intval($_POST['id']);
    $service_type_name = $conn->real_escape_string($_POST['service_type_name']);
    $note = $conn->real_escape_string($_POST['note']);

    $update = $conn->query("
        UPDATE billing_service_types
        SET service_type_name = '$service_type_name',
            note = '$note'
        WHERE id = $id
    ");

    echo $update ? 'success' : 'error';
    exit;
}

if ($action == 'delete') {
    $id = intval($_POST['id']);
    $delete = $conn->query("DELETE FROM billing_service_types WHERE id = $id");
    echo $delete ? 'success' : 'error';
    exit;
}

// Fetch single service type for edit
if ($action == 'fetch') {
    $id = intval($_POST['id']);
    $query = $conn->query("SELECT * FROM billing_service_types WHERE id = $id LIMIT 1");

    if ($query && $row = $query->fetch_assoc()) {
        header('Content-Type: application/json');
        echo json_encode($row);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Service Type not found']);
    }
    exit;
}
?>
