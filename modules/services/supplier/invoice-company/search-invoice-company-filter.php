<?php
// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database config
include_once '../../../config.php';

// Set response type
header('Content-Type: application/json');

// Check DB connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Get the search term from POST
$searchTerm = $_POST['search_term'] ?? '';

// Base query
$sql = "SELECT * FROM billing_invoice_companies ";

// If there's a search term, filter by company name
if (!empty($searchTerm)) {
    $safeTerm = $conn->real_escape_string($searchTerm);
    $sql .= " WHERE company_name LIKE '%$safeTerm%' ORDER BY company_name ASC";
}

// Run the query
$result = $conn->query($sql);

// Check for query error
if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => 'Query failed: ' . $conn->error]);
    exit;
}

// Prepare result
$companies = [];

while ($row = $result->fetch_assoc()) {
    $companies[] = $row;
}

// Return JSON response
echo json_encode($companies);
exit;
