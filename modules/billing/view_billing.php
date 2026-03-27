<?php
include_once '../config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    echo '<div class="alert alert-danger">Invalid billing ID.</div>';
    exit;
}

$query = $conn->query("
    SELECT b.*, c.client_name, s.supplier_name, st.service_type_name, sc.category_name, ic.company_name 
    FROM billing_items b
    LEFT JOIN clients c ON b.client_id = c.id
    LEFT JOIN billing_suppliers s ON b.supplier_id = s.id
    LEFT JOIN billing_service_types st ON b.service_type_id = st.id
    LEFT JOIN billing_service_categories sc ON b.service_category_id = sc.id
    LEFT JOIN billing_invoice_companies ic ON b.invoicing_company_id = ic.id
    WHERE b.id = $id
    LIMIT 1
");

if (!$query || $query->num_rows === 0) {
    echo '<div class="alert alert-warning">Billing record not found.</div>';
    exit;
}

$row = $query->fetch_assoc();
?>

<div class="row g-3">
  <div class="col-md-6">
    <p><strong>Client:</strong> <?= htmlspecialchars($row['client_name']) ?></p>
    <p><strong>Supplier:</strong> <?= htmlspecialchars($row['supplier_name']) ?></p>
    <p><strong>Service Type:</strong> <?= htmlspecialchars($row['service_type_name']) ?></p>
    <p><strong>Service Category:</strong> <?= htmlspecialchars($row['category_name']) ?></p>
    <p><strong>Invoicing Company:</strong> <?= htmlspecialchars($row['company_name']) ?></p>
  </div>
  <div class="col-md-6">
    <p><strong>Qty:</strong> <?= $row['qty'] ?></p>
    <p><strong>Unit Price:</strong> <?= number_format($row['unit_price'], 2) ?></p>
    <p><strong>VAT Rate:</strong> <?= number_format($row['vat_rate'], 2) ?>%</p>
    <p><strong>Total:</strong> <b><?= number_format(($row['qty'] * $row['unit_price']) + (($row['vat_rate'] / 100) * ($row['qty'] * $row['unit_price'])), 2) ?></b></p>
    <p><strong>Frequency:</strong> <?= ucfirst($row['frequency']) ?></p>
  </div>
  <div class="col-12">
    <p><strong>Description:</strong><br><?= nl2br(htmlspecialchars($row['description'])) ?></p>
  </div>
</div>
