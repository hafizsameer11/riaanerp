<?php
ob_start();  // Start output buffering
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log POST
$log_entry = "=== 2025-04-22 12:44:49 ===\n";
$log_entry .= print_r($_POST, true);
file_put_contents("ajax_debug_log.txt", $log_entry, FILE_APPEND);

include_once '../../config.php'; // Ensure this path is correct
if ($conn->connect_error) {
    $error = "❌ Connection failed: " . $conn->connect_error . "\n";
    file_put_contents("ajax_error_log.txt", $error, FILE_APPEND);
    echo json_encode(["success" => false, "error" => $conn->connect_error]);
    exit;
}

$client_id = intval($_POST['client_id']);
$type = $conn->real_escape_string($_POST['support_type']);
$field1 = $conn->real_escape_string($_POST['field1']);
$field2 = $conn->real_escape_string($_POST['field2']);
$field3 = $conn->real_escape_string($_POST['field3']);
$field4 = $conn->real_escape_string($_POST['field4']);
$field5 = $conn->real_escape_string($_POST['field5']);
$note = $conn->real_escape_string($_POST['note']);
$exchange_id = isset($_POST['exchange_id']) ? intval($_POST['exchange_id']) : 0;

if ($exchange_id > 0) {
    $stmt = $conn->prepare("UPDATE client_support SET field1=?, field2=?, field3=?, field4=?, field5=?, note=? WHERE id=?");
    $stmt->bind_param("ssssssi", $field1, $field2, $field3, $field4, $field5, $note, $exchange_id);
    if (!$stmt->execute()) {
        file_put_contents("ajax_error_log.txt", "❌ UPDATE Error: " . $stmt->error . "\n", FILE_APPEND);
        echo json_encode(["success" => false, "error" => $stmt->error]);
        exit;
    }
} else {
    $stmt = $conn->prepare("INSERT INTO client_support (client_id, support_type, field1, field2, field3, field4, field5, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssss", $client_id, $type, $field1, $field2, $field3, $field4, $field5, $note);
    if (!$stmt->execute()) {
        file_put_contents("ajax_error_log.txt", "❌ INSERT Error: " . $stmt->error . "\n", FILE_APPEND);
        echo json_encode(["success" => false, "error" => $stmt->error]);
        exit;
    }
}

// Final JSON output
$output = json_encode(["success" => true]);
file_put_contents("ajax_raw_output.txt", $output);
file_put_contents("ajax_raw_output_debug.txt", ob_get_contents());
ob_end_clean();
echo $output;
exit;
?>
