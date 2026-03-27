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
    $category_name = $conn->real_escape_string($_POST['category_name']);
    $notes = $conn->real_escape_string($_POST['notes']);
    $is_vm_category = isset($_POST['is_vm_category']) ? 1 : 0;
    $service_type_id = intval($_POST['service_type_id']);

    $insert = $conn->query("
        INSERT INTO billing_service_categories (category_name, note, has_vm_fields, service_type_id)
        VALUES ('$category_name', '$notes', $is_vm_category, $service_type_id)
    ");

    echo $insert ? 'success' : 'error';
    exit;
}

if ($action == 'edit') {
    $id = intval($_POST['id']);
    $category_name = $conn->real_escape_string($_POST['category_name']);
    $notes = $conn->real_escape_string($_POST['notes']);
    $is_vm_category = isset($_POST['is_vm_category']) ? 1 : 0;
    $service_type_id = intval($_POST['service_type_id']);

    $update = $conn->query("
        UPDATE billing_service_categories
        SET category_name = '$category_name',
            note = '$notes',
            has_vm_fields = $is_vm_category,
            service_type_id = $service_type_id
        WHERE id = $id
    ");

    echo $update ? 'success' : 'error';
    exit;
}
if ($action == 'delete') {
    $id = intval($_POST['id']);
    
    // Soft delete: mark as is_deleted = 1
    $update = $conn->query("UPDATE billing_service_categories SET is_deleted = 1 WHERE id = $id");

    echo $update ? 'success' : 'error: ' . $conn->error;
    exit;
}



if ($action == 'fetch') {
    $id = intval($_POST['id']);
    $query = $conn->query("SELECT * FROM billing_service_categories WHERE id = $id LIMIT 1");

    if ($query && $row = $query->fetch_assoc()) {
        header('Content-Type: application/json');
        echo json_encode($row);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Service Category not found']);
    }
    exit;
}
?>
