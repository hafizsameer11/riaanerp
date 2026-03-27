<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once '../../../config.php'; // Adjust path as needed

header('Content-Type: application/json');

if ($conn->connect_error) {
    die(json_encode(['error' => 'Database connection failed']));
}

$supplierSearch = $_POST['supplierSearch'] ?? '';
$sql = "SELECT * FROM billing_suppliers ";
if (!empty($supplierSearch)) {
    $safeTerm = $conn->real_escape_string($supplierSearch);
    $sql .= " WHERE supplier_name LIKE '%$safeTerm%' ORDER BY supplier_name ASC";
}

$result = $conn->query($sql);
// Check for query error
if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => 'Query failed: ' . $conn->error]);
    exit;
}

$suppliers = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $suppliers[] = $row;
    }
}

echo json_encode($suppliers);
exit;
