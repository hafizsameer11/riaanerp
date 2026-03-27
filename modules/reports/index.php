<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$db_host = "localhost";
$db_user = "clientzone_user";
$db_pass = "S@utech2024!";
$db_name = "clientzone";

include_once '../../config.php'; // Ensure this path is correct
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

$clients = $conn->query("SELECT id, client_name FROM clients");

// Apply filters
$where = [];
if (!empty($_GET['client_id'])) {
  $where[] = "b.client_id = " . (int) $_GET['client_id'];
}
if (!empty($_GET['invoice_type'])) {
  $where[] = "b.invoice_type = '" . $conn->real_escape_string($_GET['invoice_type']) . "'";
}
if (!empty($_GET['currency'])) {
  $where[] = "b.currency = '" . $conn->real_escape_string($_GET['currency']) . "'";
}
if (!empty($_GET['frequency'])) {
  $where[] = "b.frequency = '" . $conn->real_escape_string($_GET['frequency']) . "'";
}

$filterSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$billingRecords = $conn->query("
    SELECT b.*, c.client_name
    FROM billing_items b
    LEFT JOIN clients c ON c.id = b.client_id
    $filterSql
    ORDER BY b.id DESC
");
session_start();
include('../components/permissioncheck.php');
?>

<!DOCTYPE html>
<html>

<head>
  <meta charset="UTF-8">
  <title>Billing Report</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>

<body>

  <div class="">
    <div style="width:93%; margin: auto; ">
      <!-- Export -->
      <div class="mt-5 mb-5">
        <!-- Left-aligned Title -->
        <h3 class="mb-2 d-flex align-items-center">
          <i class="bi bi-people-fill me-2 text-secondary" style="font-size: 1.5rem;"></i>
          <span class="fw-semibold text-dark">All Clients</span>
        </h3>

        <div class="row gap-3 align-items-center">
          <?php if (hasPermission('report and admin', 'user logins')): ?>
            <a href="../auth/register.php" class="btn btn-primary p-3 h5 py-2 col-md-3 ">User Logins</a>
          <?php endif; ?>
          <?php if (hasPermission('report and admin', 'billing report')): ?>
            <a href="billingReport.php" class="btn btn-danger p-3 h5 py-2 col-md-3  ">Billing Report</a>
          <?php endif; ?>
          <?php if (hasPermission('report and admin', 'role management')): ?>
            <a href="roles/roleManagement.php" class="btn btn-success p-3 h5 py-2 col-md-3  ">Role Management</a>
          <?php endif; ?>
          <?php if (hasPermission('report and admin', 'reseller commission')): ?>
            <a href="reseller_commission.php" class="btn btn-warning p-3 h5 py-2 col-md-3">Reseller Commission Report</a>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>

</body>

</html>