<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

file_put_contents("debug_log.txt", print_r($_POST, true));


echo "<pre>";
print_r($_POST);
echo "</pre>";
include_once '../../config.php'; // Ensure this path is correct
$client_id = intval($_POST['client_id']);
$type = $conn->real_escape_string($_POST['support_type']);
$field1 = $conn->real_escape_string($_POST['field1']); // Domain
$field2 = $conn->real_escape_string($_POST['field2']); // Full Name
$field3 = $conn->real_escape_string($_POST['field3']); // Login
$field4 = $conn->real_escape_string($_POST['field4']); // Password
$field5 = $conn->real_escape_string($_POST['field5']); // SpamTitan
$note = $conn->real_escape_string($_POST['note']);
$exchange_id = isset($_POST['exchange_id']) ? intval($_POST['exchange_id']) : 0;

if ($exchange_id > 0) {
    $stmt = $conn->prepare("UPDATE client_support SET field1=?, field2=?, field3=?, field4=?, field5=?, note=? WHERE id=?");
    $stmt->bind_param("ssssssi", $field1, $field2, $field3, $field4, $field5, $note, $exchange_id);
    if (!$stmt->execute()) {
        die("❌ Update Failed: " . $stmt->error);
    }
} else {
    $stmt = $conn->prepare("INSERT INTO client_support (client_id, support_type, field1, field2, field3, field4, field5, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssss", $client_id, $type, $field1, $field2, $field3, $field4, $field5, $note);
    if (!$stmt->execute()) {
        die("❌ Insert Failed: " . $stmt->error);
    }
}

echo "✅ Entry saved successfully.";
?>
