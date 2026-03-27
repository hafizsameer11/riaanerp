<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once '../../../config.php';
header('Content-Type: application/json');

if ($conn->connect_error) {
    die(json_encode(['error' => 'Database connection failed']));
}

$categorySearch= $_POST['categorySearch'] ?? '';

$sql = "SELECT 
            c.*,
            st.service_type_name AS service_type_name
        FROM billing_service_categories c 
        LEFT JOIN billing_service_types st ON c.service_type_id = st.id 
        WHERE c.is_deleted = 0";

if (!empty($categorySearch)) {
    $safeTerm = $conn->real_escape_string($categorySearch);
    $sql .= " AND category_name LIKE '%$safeTerm%' ORDER BY category_name ASC";
}

$result = $conn->query($sql);
if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => 'Query failed: ' . $conn->error]);
    exit;
}
$categories = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
}

echo json_encode($categories);
exit;
