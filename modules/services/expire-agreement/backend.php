<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = new mysqli("localhost", "root", "", "sautech");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$action = $_POST['action'] ?? '';

if (isset($_POST['action']) && $_POST['action'] === 'extend_manual') {
    $id = (int)($_POST['billing_id'] ?? 0);
    $new_end_date = $_POST['new_end_date'] ?? '';

    if ($id > 0 && !empty($new_end_date)) {
        $stmt = $conn->prepare("UPDATE billing_items SET end_date = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('si', $new_end_date, $id);
            if ($stmt->execute()) {
                echo "success";
            } else {
                echo "error_execute";
            }
        } else {
            echo "error_prepare";
        }
    } else {
        echo "invalid_input";
    }
    exit;
}


if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $delete = $conn->query("DELETE FROM billing_items WHERE id = $id");
    echo $delete ? 'success' : 'error_delete';
    exit;
}
