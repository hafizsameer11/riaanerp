<?php
// Database connection
$db_host = "localhost";
    $db_user = "clientzone_user";
    $db_pass = "S@utech2024!";
    $db_name = "clientzone";

include_once '../../config.php'; // Ensure this path is correct

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if update request is received
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "edit") {
    $id = intval($_POST["hosting_record_id"]);

    // Sanitize input using null coalescing
    $fields = [
        'client_name' => $_POST["client_name"],
        'client_id' => $_POST["client_id"],
        'location' => $_POST["location"],
        'asset_type' => $_POST["asset_type"],
        'host' => $_POST["host"],
        'server_name' => $_POST["server_name"],
        'os' => $_POST["os"],
        'cpu' => $_POST["cpu"],
        'ram' => $_POST["ram"],
        'sata' => $_POST["sata"],
        'ssd' => $_POST["ssd"],
        'private_ip' => $_POST["private_ip"],
        'public_ip' => $_POST["public_ip"],
        'username' => $_POST["username"],
        'password' => $_POST["password"],
        'spla' => $_POST["spla"],
        'login_url' => $_POST["login_url"],
        'note' => $_POST["note"],
    ];

    // Apply the logic for the 'os' field
    // if (isset($fields['cpu']) && is_numeric($fields['cpu'])) {
    //     $os = intval($fields['cpu']);
    //     if ($os < 8) {
    //         $fields['cpu'] = 8;
    //     } elseif ($os > 8 && $os % 2 !== 0) { // Odd numbers greater than 8
    //         $fields['cpu'] = $os + 1;
    //     }
    // }

    // Prepare the SQL statement dynamically
    $set = implode(', ', array_map(fn($key) => "$key = ?", array_keys($fields)));
    $types = str_repeat('s', count($fields)) . 'i'; // s for all fields, i for ID

    $stmt = $conn->prepare("UPDATE hosting_assets SET $set WHERE id = ?");
    $values = array_values($fields);
    $values[] = $id;

    $stmt->bind_param($types, ...$values);

    if ($stmt->execute()) {
        echo "success";
    } else {
        http_response_code(500);
        echo "error: " . $stmt->error;
    }

    $stmt->close();
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "add") {
    $fields = ['client_name', 'client_id', 'location', 'asset_type', 'host', 'server_name', 'os', 'cpu', 'ram', 'sata', 'ssd', 'private_ip', 'public_ip', 'username', 'password', 'spla', 'login_url', 'note'];
    $data = [];
    foreach ($fields as $field) {
        $data[$field] = $conn->real_escape_string($_POST[$field] ?? '');
    }

    // Apply the logic for the 'os' field
    // if (isset($data['cpu']) && is_numeric($data['cpu'])) {
    //     $os = intval($data['cpu']);
    //     if ($os < 8) {
    //         $data['cpu'] = 8;
    //     } elseif ($os > 8 && $os % 2 !== 0) { // Odd numbers greater than 8
    //         $data['cpu'] = $os + 1;
    //     }
    // }

    $stmt = $conn->prepare("INSERT INTO hosting_assets (" . implode(',', $fields) . ") VALUES (" . str_repeat('?,', count($fields) - 1) . "?)");
    if (!$stmt) {
        echo "error_prepare";
        exit;
    }

    $stmt->bind_param(str_repeat("s", count($fields)), ...array_values($data));

    if ($stmt->execute()) {
        echo "success_add";
    } else {
        echo "error_execute: " . $stmt->error;
        exit;
    }
    $stmt->close();
    exit;
}

$columns = ['client_name', 'client_id', 'location', 'asset_type', 'host', 'server_name', 'os', 'cpu', 'ram', 'sata', 'ssd', 'private_ip', 'public_ip', 'username', 'password', 'spla', 'login_url', 'note'];

$stmt = $conn->prepare("INSERT INTO hosting_assets (" . implode(',', $columns) . ") VALUES (" . str_repeat('?,', count($columns) - 1) . "?)");

$conn->close();
?>
