<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$db_host = "localhost";
    $db_user = "clientzone_user";
    $db_pass = "S@utech2024!";
    $db_name = "clientzone";

include_once '../../config.php'; // Ensure this path is correct

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle Requests
$action = $_POST['action'] ?? '';

if ($action == 'add') {
    $client_id = intval($_POST['client_id'] ?? 0);
    $device_name = trim($_POST['device_name'] ?? '');
    $device_type = trim($_POST['device_type'] ?? '');
    $device_ip = trim($_POST['device_ip'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $enable_username = trim($_POST['enable_username'] ?? '');
    $enable_password = trim($_POST['enable_password'] ?? '');
    $access_port = trim($_POST['access_port'] ?? '');
    $note = trim($_POST['note'] ?? '');

    $stmt = $conn->prepare("INSERT INTO client_devices 
        (client_id, device_name, device_type, device_ip, location, username, password, enable_username, enable_password, access_port, note) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param(
        "issssssssss",
        $client_id,
        $device_name,
        $device_type,
        $device_ip,
        $location,
        $username,
        $password,
        $enable_username,
        $enable_password,
        $access_port,
        $note
    );

    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "error: " . $stmt->error;
    }

    $stmt->close();
    exit;
} elseif ($action == 'edit') {
    $id = intval($_POST['device_id'] ?? 0);
    $client_id = intval($_POST['client_id'] ?? 0);
    $device_name = trim($_POST['device_name'] ?? '');
    $device_type = trim($_POST['device_type'] ?? '');
    $device_ip = trim($_POST['device_ip'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $enable_username = trim($_POST['enable_username'] ?? '');
    $enable_password = trim($_POST['enable_password'] ?? '');
    $access_port = trim($_POST['access_port'] ?? '');
    $note = trim($_POST['note'] ?? '');

    $stmt = $conn->prepare("UPDATE client_devices SET 
        client_id=?, device_name=?, device_type=?, device_ip=?, location=?, username=?, password=?, enable_username=?, enable_password=?, access_port=?, note=? 
        WHERE id=?");
    $stmt->bind_param(
        "issssssssssi",
        $client_id,
        $device_name,
        $device_type,
        $device_ip,
        $location,
        $username,
        $password,
        $enable_username,
        $enable_password,
        $access_port,
        $note,
        $id
    );

    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "error: " . $stmt->error;
    }

    $stmt->close();
    exit;
} elseif ($action == 'delete') {
    $id = intval($_POST['id'] ?? 0);

    $stmt = $conn->prepare("UPDATE client_devices SET is_deleted = 1 WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "error: " . $stmt->error;
    }

    $stmt->close();
    exit;
} elseif ($action == 'fetch') {
    $id = intval($_POST['id'] ?? 0);

    $query = $conn->query("SELECT * FROM client_devices WHERE id = $id AND is_deleted = 0 LIMIT 1");

    if ($query && $device = $query->fetch_assoc()) {
        header('Content-Type: application/json');
        echo json_encode($device);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Device not found']);
    }
    exit;
}
else {
    echo "invalid action";
}
