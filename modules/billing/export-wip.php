<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database Connection
include_once '../config.php';

// Include XLSXWriter
require_once 'xlsxwriter.class.php';

// Build the same query as wip.php with filters
$where = [];

// General search - searches across multiple fields
if (!empty($_GET['search'])) {
  $searchTerm = $conn->real_escape_string($_GET['search']);
  $searchConditions = [
    "c.client_name LIKE '%$searchTerm%'",
    "w.quote_id LIKE '%$searchTerm%'",
    "w.sales_person LIKE '%$searchTerm%'",
    "w.description LIKE '%$searchTerm%'",
    "w.status LIKE '%$searchTerm%'",
    "w.note LIKE '%$searchTerm%'"
  ];
  $where[] = "(" . implode(' OR ', $searchConditions) . ")";
}

// Specific filters (can be combined with general search)
if (!empty($_GET['client_id'])) {
  $where[] = "w.client_id = " . (int) $_GET['client_id'];
}
if (!empty($_GET['quote_id'])) {
  $where[] = "w.quote_id = " . (int) $_GET['quote_id'];
}
if (!empty($_GET['status'])) {
  $where[] = "w.status = '" . $conn->real_escape_string($_GET['status']) . "'";
}
if (!empty($_GET['note'])) {
  $note = $conn->real_escape_string($_GET['note']);
  $where[] = "w.note LIKE '%$note%'";
}

$filterSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Fetch WIP records with same query as wip.php
$query = "
  SELECT w.*, c.client_name, c.currency, c.currency_symbol
  FROM wip w
  LEFT JOIN clients c ON w.client_id = c.id
  $filterSql
  ORDER BY c.client_name ASC
";

$result = $conn->query($query);

// Setup Excel
$writer = new XLSXWriter();
$writer->setAuthor('Sautech');

// Header Columns
$header = [
  'ID' => 'integer',
  'Client' => 'string',
  'Quote #' => 'string',
  'Sales Person' => 'string',
  'Description' => 'string',
  'Note' => 'string',
  'Price (incl VAT)' => 'price',
  'Currency Symbol' => 'string',
  'Currency' => 'string',
  'Terms' => 'string',
  'Status' => 'string',
  'Created At' => 'date',
  'Updated At' => 'date',
];

// Start Sheet
$writer->writeSheetHeader('WIP Items', $header);

// Add rows
while ($row = $result->fetch_assoc()) {
  $currencySymbol = $row['currency_symbol'] ? $row['currency_symbol'] : (isset($row['currency'][0]) ? $row['currency'][0] : '');
  
  $writer->writeSheetRow('WIP Items', [
    (int)$row['id'],
    $row['client_name'] ?? '',
    $row['quote_id'] ?? '',
    $row['sales_person'] ?? '',
    $row['description'] ?? '',
    $row['note'] ?? '',
    (float)$row['monthly_price_incl_vat'],
    $currencySymbol,
    $row['currency'] ?? '',
    $row['terms'] ?? '',
    $row['status'] ?? '',
    $row['created_at'] ?? '',
    $row['updated_at'] ?? '',
  ]);
}

// Output Excel File
$filename = "wip_items_" . date('Y-m-d_H-i-s') . ".xlsx";

header('Content-disposition: attachment; filename="' . $filename . '"');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Transfer-Encoding: binary');
header('Cache-Control: must-revalidate');
header('Pragma: public');

$writer->writeToStdOut();
exit;
?>
