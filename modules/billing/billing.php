<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include('../components/permissioncheck.php');
// Database Connection
include_once '../config.php'; // Ensure this path is correct
// Fetch Billing Records
$billingRecords = [];
$where = [];
// if (!hasPermission('billing page', 'View all')) {
//     $where[] = "b.created_by = " . (int) $_SESSION['user_id'];
// }
// Check if date filters are provided (same names and logic as billing report)
$filterStart = $_GET['start_date'] ?? '';
$filterEnd = $_GET['end_date'] ?? '';

if (!empty($filterStart) && !empty($filterEnd)) {
    $filterStart = $conn->real_escape_string($filterStart);
    $filterEnd = $conn->real_escape_string($filterEnd);
    $where[] = "(b.start_date <= '$filterEnd' AND (b.end_date IS NULL OR b.end_date >= '$filterStart'))";
}

$whereClause = '';
if (!empty($where)) {
    $whereClause = ' AND ' . implode(' AND ', $where);
}

$query = "
    SELECT b.*,
           c.client_name,
           s.supplier_name AS supplier_name,
           st.service_type_name,
           sc.category_name,
           ic.company_name 
    FROM billing_items b
    LEFT JOIN clients c ON b.client_id = c.id
    LEFT JOIN billing_suppliers s ON b.supplier_id = s.id
    LEFT JOIN billing_service_types st ON b.service_type_id = st.id
    LEFT JOIN billing_service_categories sc ON b.service_category_id = sc.id
    LEFT JOIN billing_invoice_companies ic ON b.invoicing_company_id = ic.id
    WHERE b.is_deleted = 0
    $whereClause
    ORDER BY c.client_name ASC
";

$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $billingRecords[] = $row;
    }
}
// Fetch Billing Items
$itemsResult = $conn->query("
    SELECT * FROM billing_items 
    WHERE is_deleted = 0
    ORDER BY created_at DESC
");
$clients = [];
// Fetch Filters
if ($_SESSION["role"] == "admin") {
    $clients = $conn->query("SELECT id, client_name FROM clients ORDER BY client_name ASC");
} else {
    $loginUserId = $_SESSION['user_id'] ?? null;

    if (!$loginUserId) {
        die("User not logged in.");
    }

    $getLoginUser = $conn->query("SELECT * FROM resellers WHERE register_id = $loginUserId LIMIT 1");
    $reseller = ($getLoginUser && $getLoginUser->num_rows > 0) ? $getLoginUser->fetch_assoc() : null;

    if ($reseller && !empty($reseller['client_id'])) {
        $client_ids = json_decode($reseller['client_id'], true);

        // Ensure $client_ids is an array
        if (is_array($client_ids)) {
            $client_ids_escaped = array_map('intval', $client_ids);
            $client_ids_str = implode(',', $client_ids_escaped);

            $clients = $conn->query("SELECT id, client_name FROM clients WHERE id IN ($client_ids_str) ORDER BY client_name ASC");
        } else {
            // client_id field was not a valid JSON array
            $clients = false;
        }
    } else {
        // reseller not found or client_id is empty
        $clients = false;
    }
}
$suppliers = $conn->query("SELECT id, supplier_name FROM billing_suppliers ORDER BY supplier_name ASC");
$serviceTypes = $conn->query("SELECT id, service_type_name FROM billing_service_types ORDER BY service_type_name ASC");
$serviceCategories = $conn->query("SELECT id, category_name, has_vm_fields FROM billing_service_categories ORDER BY category_name ASC");
$invoicingCompanies = $conn->query("SELECT id, company_name FROM billing_invoice_companies ORDER BY company_name ASC");


function calculateStatus($endDate)
{
    if (!$endDate)
        return 'Active';
    $now = strtotime(date('Y-m-d'));
    $end = strtotime($endDate);
    $diff = ($end - $now) / (60 * 60 * 24);

    if ($diff < 0)
        return 'Expired';
    if ($diff <= 90)
        return 'Expiring Soon';
    return 'Active';
}

function statusBadge($status)
{
    return match ($status) {
        'Active' => 'success',
        'Expiring Soon' => 'warning',
        'Expired' => 'danger',
        default => 'secondary'
    };
}
session_abort();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Billing Items List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>

<body>
    <div class=" my-5 px-5">

        <div class='d-flex align-items-center justify-content-between mb-4'>
            <div class="d-flex align-items-center ">
                <?php session_start(); ?>
                <!-- Left-aligned Title -->
                <h3 class="mb-2 d-flex align-items-center">
                    <i class="bi bi-people-fill me-2 text-secondary" style="font-size: 1.5rem;"></i>
                    <span class="fw-semibold text-dark">Billing</span>
                </h3>
            </div>
            <div class="d-flex gap-2">
                <a href="export-billing.php" class="btn btn-primary">
                    Export Billing to Excel
                </a>
                <a href="billing-price-increase.php" class="btn btn-primary">
                    Billing Price Increase
                </a>
                <?php if (hasPermission('billing page', 'create')): ?>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addBillingModal">
                        Add New Billing Item
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- Filter -->
    <div class="card shadow-sm p-4 mb-4 " style="width: 93%; margin: auto;">
        <form id="filterForm" class="row g-3 align-items-end" method="GET">

            <div class="col-md-3">
                <label class="form-label">Client</label>
                <select name="client_id" class="form-select">
                    <option value="">All Clients</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= $client['id'] ?>"><?= htmlspecialchars($client['client_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Supplier</label>
                <select name="supplier_id" class="form-select">
                    <option value="">All Suppliers</option>
                    <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?= $supplier['id'] ?>"><?= htmlspecialchars($supplier['supplier_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Service Type</label>
                <select name="service_type_id" class="form-select">
                    <option value="">All Service Types</option>
                    <?php foreach ($serviceTypes as $stype): ?>
                        <option value="<?= $stype['id'] ?>"><?= htmlspecialchars($stype['service_type_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>


            <div class="col-md-3">
                <label class="form-label">Frequency</label>
                <select name="frequency" class="form-select">
                    <option value="">All Frequencies</option>
                    <option value="annually">Annually</option>
                    <option value="monthly">Monthly</option>
                    <option value="once_off">Once Off</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Invoice Company</label>
                <select name="invoicing_company_id" class="form-select">
                    <option value="">All Invoicing Companies</option>
                    <?php foreach ($invoicingCompanies as $company): ?>
                        <option value="<?= $company['id'] ?>">
                            <?= htmlspecialchars($company['company_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Start Date</label>
                <input type="date" name="start_date" class="form-control" 
                       value="<?= htmlspecialchars($_GET['start_date'] ?? '') ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">End Date</label>
                <input type="date" name="end_date" class="form-control" 
                       value="<?= htmlspecialchars($_GET['end_date'] ?? '') ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Search Name or Description</label>
                <input type="text" name="searchDescription" placeholder="Search Name or Description..." class="form-control">
            </div>
            <div class="col-12 text-end">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <button type="button" onclick="resetFilters()" class="btn btn-secondary">Reset</button>
            </div>

        </form>
    </div>

    <div class=" my-5 px-5">
        <div class="table-responsive">
            <table class="table table-hover table-bordered table-striped align-middle shadow-sm rounded bg-white">
                <thead class="table-light text-center">
                    <tr>
                        <th>#</th>
                        <th>Client</th>
                        <!-- <th>Supplier</th>
                        <th>Service Type</th>
                        <th>Service Category</th> -->
                        <th>Description</th>
                        <th>Qty</th>
                        <th>Unit Price</th>
                        <th>VAT Rate</th>
                        <th>Subtotal</th>
                        <th>Total</th>
                        <th>Frequency</th>
                        <!-- <th>Start</th>
                        <th>End</th> -->
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $i = 1;
                    foreach ($billingRecords as $row):
                        $subtotal = $row['qty'] * $row['unit_price'];
                        $vatAmount = ($row['vat_rate'] / 100) * $subtotal;
                        $total = $subtotal + $vatAmount;
                    ?>
                        <tr>
                            <td class="text-center"><?= $i++ ?></td>
                            <td><?= htmlspecialchars($row['client_name']) ?></td>
                            <td><?= htmlspecialchars($row['description']) ?></td>
                            <!-- <td><?= htmlspecialchars($row['supplier_name']) ?></td>
                            <td><?= htmlspecialchars($row['service_type_name']) ?></td>
                            <td><?= htmlspecialchars($row['category_name']) ?></td> -->
                            <td class="text-center"><?= $row['qty'] ?></td>
                            <td class="text-end"><?= $row['currency_symbol'] ?> <?= number_format($row['unit_price'], 2) ?>
                            </td>
                            <td class="text-end"><?= $row['currency_symbol'] ?> <?= number_format($row['vat_rate'], 2) ?>%
                            </td>
                            <td class="text-end"><?= $row['currency_symbol'] ?> <?= number_format($subtotal, 2) ?></td>
                            <td class="text-end"><strong><?= $row['currency_symbol'] ?>
                                    <?= number_format($total, 2) ?></strong></td>
                            <td class="text-center"><?= ucfirst($row['frequency']) ?></td>
                            <!-- <td class="text-center"><?= date('d M Y', strtotime($row['start_date'])) ?></td>
                            <td class="text-center">
                                <?= $row['end_date'] ? date('d M Y', strtotime($row['end_date'])) : '-' ?>
                            </td> -->
                            <td class="text-center">
                                <div class="btn-group" role="group" aria-label="Actions">
                                    <a href="javascript:void(0)"
                                        onclick="openViewModal(<?= (int)$row['id'] ?>)"
                                        class="btn btn-sm"
                                        title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>

                                    <?php if (hasPermission('billing page', 'update')): ?>

                                        <a href="javascript:void(0)"
                                            onclick='openEditModal(
       <?= json_encode($row["id"] ?? null) ?>,
       <?= json_encode($row["client_id"] ?? null) ?>,
       <?= json_encode($row["supplier_id"] ?? null) ?>,
       <?= json_encode($row["service_type_id"] ?? null) ?>,
       <?= json_encode($row["service_category_id"] ?? null) ?>,
       <?= json_encode($row["invoicing_company_id"] ?? null) ?>,
       <?= json_encode($row["description"] ?? "") ?>,
       <?= json_encode($row["qty"] ?? 0) ?>,
       <?= json_encode($row["unit_price"] ?? 0) ?>,
       <?= json_encode($row["vat_rate"] ?? 0) ?>,
       <?= json_encode($row["frequency"] ?? "") ?>,
       <?= json_encode($row["start_date"] ?? "") ?>,
       <?= json_encode($row["end_date"] ?? "") ?>,
       <?= json_encode($row["vat_applied"] ?? 0) ?>
   )'
                                            class="btn btn-sm" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>

                                    <?php endif; ?>
                                    <?php if (hasPermission('billing page', 'delete')): ?>
                                        <a href="javascript:void(0)" onclick="openDeleteModal(<?= $row['id'] ?>)"
                                            class="btn btn-sm text-danger" title="Delete">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    <?php endif; ?>

                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>
    <div class="modal fade" id="viewBillingModal" tabindex="-1" aria-labelledby="viewBillingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="viewBillingModalLabel">Billing Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="viewBillingModalBody">
                    <div class="text-center py-4 text-muted">Loading billing details...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- (Modals for Add/Edit/Delete Billing Items will come next separately) -->
    <!-- Add Billing Modal -->
    <div class="modal fade" id="addBillingModal" tabindex="-1" aria-labelledby="addBillingModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form id="addBillingForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addBillingModalLabel">➕ Add New Billing Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body row g-3">

                    <div class="col-md-6">
                        <label class="form-label">Client</label>
                        <select name="client_id" class="form-select" required>
                            <option value="">Select Client</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= $client['id'] ?>"><?= htmlspecialchars($client['client_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Supplier</label>
                        <select name="supplier_id" class="form-select" required>
                            <option value="">Select Supplier</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?= $supplier['id'] ?>"><?= htmlspecialchars($supplier['supplier_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Service Type</label>
                        <select name="service_type_id" id="add-service-type-id" class="form-select" required
                            onchange="fetchCategories(this.value, 'add')">
                            <option value="">Select Service Type</option>
                            <?php foreach ($serviceTypes as $stype): ?>
                                <option value="<?= $stype['id'] ?>"><?= htmlspecialchars($stype['service_type_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Invoicing Company</label>
                        <select name="invoicing_company_id" id="add-invoicing-company-id" class="form-select" required>
                            <option value="" selected>Select Invoicing Company</option>
                            <?php foreach ($invoicingCompanies as $company): ?>
                                <option value="<?= $company['id'] ?>">
                                    <?= htmlspecialchars($company['company_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>


                    <div class="col-md-6">
                        <label class="form-label">Service Category</label>
                        <select name="service_category_id" id="add-service-category-id" class="form-select" 
                             >
                            <option value="">Select Service Category</option>
                             <?php 
                             if ($serviceCategories && $serviceCategories->num_rows > 0) {
                                 $serviceCategories->data_seek(0); // Reset pointer
                                 while($category = $serviceCategories->fetch_assoc()): ?>
                                <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['category_name']) ?>
                                </option>
                             <?php 
                                 endwhile;
                             }
                             ?>
                        </select>
                    </div>

                    <fieldset id="vmFieldsAdd" style="display:none;" class="border p-3 rounded">
                        <div class="row">
                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label">Memory</label>
                                <input type="text" name="memory" class="form-control">
                            </div>
                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label">HDD SATA</label>
                                <input type="text" name="hdd_sata" class="form-control">
                            </div>
                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label">HDD SSD</label>
                                <input type="text" name="hdd_ssd" class="form-control">
                            </div>
                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label">OS</label>
                                <input type="text" name="os" class="form-control">
                            </div>
                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label">IP Address</label>
                                <input type="text" name="ip_address" class="form-control">
                            </div>
                        </div>
                    </fieldset>

                    <!-- End VM Fields -->

                    <div class="col-md-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" placeholder="Optional..."></textarea>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" name="quantity" class="form-control" value="1" min="1" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Unit Price</label>
                        <input type="number" name="unit_price" class="form-control" step="0.01" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">VAT Rate (%)</label>
                        <input type="number" name="vat_rate" class="form-control" value="15" step="0.01" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Invoice Frequency</label>
                        <select name="invoice_frequency" id="invoice-frequency" class="form-select" required>
                            <option value="once_off">Once Off</option>
                            <option value="monthly">Monthly</option>
                            <option value="annually">Annually</option>
                            <option value="finance">Finance</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" id="start-date-label">Start Date</label>
                        <input type="date" name="start_date" id="start_date" class="form-control" required>
                    </div>

                    <div class="col-md-6" id="end-date-wrapper">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control">
                    </div>


                    <!-- Live calu -->
                    <div class="col-12">
                        <div class="p-3 mt-3 bg-light border rounded">
                            <p><strong>Subtotal:</strong> <span id="subtotalDisplay">0.00</span></p>
                            <p><strong>VAT Amount:</strong> <span id="vatDisplay">0.00</span></p>
                            <p><strong>Total:</strong> <span id="totalDisplay">0.00</span></p>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" value="1" id="chargeVatCheckbox"
                                name="charge_vat" checked>
                            <label class="form-check-label" for="chargeVatCheckbox">
                                Charge VAT for this item
                            </label>
                        </div>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">💾 Save Billing</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ✏️ Edit Billing Modal -->
    <div class="modal fade" id="editBillingModal" tabindex="-1" aria-labelledby="editBillingModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form id="editBillingForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editBillingModalLabel">✏️ Edit Billing Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body row g-3">

                    <input type="hidden" name="billing_id" id="edit-billing-id">



                    <div class="col-md-6">
                        <label class="form-label">Client</label>
                        <select name="client_id" id="edit-client-id" class="form-select" required>
                            <option value="">Select Client</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= $client['id'] ?>"><?= htmlspecialchars($client['client_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <input type="hidden" id="billing_type" name="billing_type" value="">
                    <input type="hidden" id="currency" name="currency" value="">

                    <div class="col-md-6">
                        <label class="form-label">Supplier</label>
                        <select name="supplier_id" id="edit-supplier-id" class="form-select" required>
                            <option value="">Select Supplier</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?= $supplier['id'] ?>"><?= htmlspecialchars($supplier['supplier_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Edit Billing Modal -->
                    <div class="col-md-6">
                        <label class="form-label">Service Type</label>
                        <select name="service_type_id" id="edit-service-type-id" class="form-select" required
                            onchange="fetchCategories(this.value, 'edit')">
                            <option value="">Select Service Type</option>
                            <?php foreach ($serviceTypes as $stype): ?>
                                <option value="<?= $stype['id'] ?>"><?= htmlspecialchars($stype['service_type_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Service Category</label>
                        <select name="service_category_id" id="edit-service-category-id" class="form-select" required
                            onchange="handleEditVMFields(this)">
                            <option value="">Select Service Category</option>
                            <!-- Categories will be dynamically populated here -->
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Invoicing Company</label>
                        <select name="invoicing_company_id" id="edit-invoicing_company_id" class="form-select" required>
                            <option value="">Select Invoice Company</option>
                            <?php foreach ($invoicingCompanies as $company): ?>
                                <option value="<?= $company['id'] ?>" selected><?= htmlspecialchars($company['company_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Dynamic VM Fields inside Fieldset -->
                    <fieldset id="vmFieldsEdit" style="display:none;" class="border p-3 rounded mt-3">
                        <legend class="float-none w-auto px-3">VM Service Details</legend>

                        <div class="row">
                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label">CPU</label>
                                <input type="text" id="edit-cpu" name="cpu" class="form-control">
                            </div>
                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label">Memory</label>
                                <input type="text" id="edit-memory" name="memory" class="form-control">
                            </div>
                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label">HDD SATA</label>
                                <input type="text" id="edit-hdd-sata" name="hdd_sata" class="form-control">
                            </div>
                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label">HDD SSD</label>
                                <input type="text" id="edit-hdd-ssd" name="hdd_ssd" class="form-control">
                            </div>
                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label">OS</label>
                                <input type="text" id="edit-os" name="os" class="form-control">
                            </div>
                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label">IP Address</label>
                                <input type="text" id="edit-ip-address" name="ip_address" class="form-control">
                            </div>
                        </div>
                    </fieldset>

                    <div class="col-md-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="edit-description" class="form-control" rows="3"></textarea>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" name="quantity" id="edit-quantity" class="form-control" min="1" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Unit Price</label>
                        <input type="number" name="unit_price" id="edit-unit-price" class="form-control" step="0.01"
                            required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">VAT Rate (%)</label>
                        <input type="number" name="vat_rate" id="edit-vat-rate" class="form-control" step="0.01"
                            required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Invoice Frequency</label>
                        <select name="invoice_frequency" id="edit-invoice-frequency" class="form-select" required>
                            <option value="once_off">Once Off</option>
                            <option value="monthly">Monthly</option>
                            <option value="annually">Annually</option>
                            <option value="finance">Finance</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" id="edit-start-date" class="form-control" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" id="edit-end-date" class="form-control">
                    </div>

                    <div class="col-12">
                        <div class="p-3 mt-3 bg-light border rounded">
                            <p><strong>Subtotal:</strong> <span id="edit-subtotalDisplay">0.00</span></p>
                            <p><strong>VAT Amount:</strong> <span id="edit-vatDisplay">0.00</span></p>
                            <p><strong>Total:</strong> <span id="edit-totalDisplay">0.00</span></p>
                        </div>
                    </div>


                    <div class="col-md-12">
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" id="edit-charge-vat" name="charge_vat"
                                value="1">
                            <label class="form-check-label" for="edit-charge-vat">
                                Charge VAT for this item
                            </label>
                        </div>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">💾 Update Billing</button>
                </div>

            </form>
        </div>
    </div>


    <?php if (isset($_GET['view_billing']) && is_numeric($_GET['view_billing'])): ?>
        <?php
        $billing_id = (int) $_GET['view_billing'];
        $billingViewQuery = $conn->query("
      SELECT b.*, c.client_name, s.supplier_name, st.service_type_name, sc.category_name, ic.company_name 
      FROM billing_items b
      LEFT JOIN clients c ON b.client_id = c.id
      LEFT JOIN billing_suppliers s ON b.supplier_id = s.id
      LEFT JOIN billing_service_types st ON b.service_type_id = st.id
      LEFT JOIN billing_service_categories sc ON b.service_category_id = sc.id
      LEFT JOIN billing_invoice_companies ic ON b.invoicing_company_id = ic.id
      WHERE b.id = $billing_id
      LIMIT 1
    ");
        $billing_data = $billingViewQuery->fetch_assoc();
        ?>

        <?php if ($billing_data): ?>
            <div class="my-4" style="width: 95%; margin: auto;">
                <div class="card border-0 rounded-3 bg-light">
                    <div class="card-header bg-light text-black d-flex align-items-center">
                        <i class="bi bi-eye-fill me-2" style="font-size: 1.5rem;"></i>
                        <h4 class="mb-0">Viewing Billing Item #<?= htmlspecialchars($billing_data['id']) ?>
                            (<?= htmlspecialchars($billing_data['client_name']) ?>)</h4>
                    </div>

                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <p><strong>Client:</strong> <?= htmlspecialchars($billing_data['client_name']) ?></p>
                                <p><strong>Supplier:</strong> <?= htmlspecialchars($billing_data['supplier_name']) ?></p>
                                <p><strong>Service Type:</strong> <?= htmlspecialchars($billing_data['service_type_name']) ?>
                                </p>
                                <p><strong>Service Category:</strong> <?= htmlspecialchars($billing_data['category_name']) ?>
                                </p>
                                <p><strong>Invoicing Company:</strong> <?= htmlspecialchars($billing_data['company_name'] ?? '') ?>
                                </p>
                                <p><strong>Quantity:</strong> <?= $billing_data['qty'] ?></p>
                                <p><strong>Unit Price:</strong> <?= number_format($billing_data['unit_price'], 2) ?></p>
                                <p><strong>Subtotal:</strong>
                                    <?= number_format($billing_data['qty'] * $billing_data['unit_price'], 2) ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Invoice Type:</strong> <?= htmlspecialchars($billing_data['invoice_type']) ?></p>
                                <p><strong>Currency:</strong> <?= htmlspecialchars($billing_data['currency']) ?></p>
                                <p><strong>VAT Rate:</strong> <?= number_format($billing_data['vat_rate'], 2) ?>%</p>
                                <p><strong>Total:</strong>
                                    <strong><?= number_format(($billing_data['qty'] * $billing_data['unit_price']) + (($billing_data['vat_rate'] / 100) * ($billing_data['qty'] * $billing_data['unit_price'])), 2) ?></strong>
                                </p>
                                <p><strong>Invoice Frequency:</strong> <?= ucfirst($billing_data['frequency']) ?></p>
                                <p><strong>Start Date:</strong> <?= htmlspecialchars($billing_data['start_date']) ?></p>
                                <p><strong>End Date:</strong> <?= htmlspecialchars($billing_data['end_date']) ?: '-' ?></p>
                                <p><strong>Charge VAT:</strong>
                                    <?php if ($billing_data['vat_applied'] == 1): ?>
                                        <span class="badge bg-success">Yes</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">No</span>
                                    <?php endif; ?>
                                </p>
                            </div>

                            <div class="col-12">
                                <p><strong>Description:</strong><br><?= nl2br(htmlspecialchars($billing_data['description'])) ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer bg-light border-0">
                        <div class="d-flex flex-wrap gap-2">
                            <a href="billing_list.php" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-arrow-left me-1"></i> Back to Billing List
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- clients that expire 90 days -->
    <?php
    include_once 'expire-agreement/index.php';
    ?>


    <!-- Bootstrap + JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

    <script>
        function fetchCategories(serviceTypeId, mode) {
            if (!serviceTypeId) {
                const categoryDropdown = document.getElementById(`${mode}-service-category-id`);
                categoryDropdown.innerHTML = '<option value="">Select Service Category</option>';
                return;
            }

            const formData = new FormData();
            formData.append('action', 'fetch_categories');
            formData.append('service_type_id', serviceTypeId);

            axios.post('backend.php', formData)
                .then(response => {
                    const categories = response.data;
                    const categoryDropdown = document.getElementById(`${mode}-service-category-id`);
                    categoryDropdown.innerHTML = '<option value="">Select Service Category</option>';

                    categories.forEach(category => {
                        const option = document.createElement('option');
                        option.value = category.id;
                        option.textContent = category.category_name;
                        option.setAttribute('data-vm', category.has_vm_fields);
                        categoryDropdown.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Failed to fetch categories:', error);
                });
        }

        function openEditModal(
            id, client_id, supplier_id, service_type_id, service_category_id, invoicing_company_id,
            description, qty, unit_price, vat_rate, invoice_frequency,
            start_date, end_date, charge_vat, currency,
            cpu = '', memory = '', hdd_sata = '', hdd_ssd = '', os = '', ip_address = ''
        ) {
            document.getElementById('edit-billing-id').value = id;
            document.getElementById('edit-client-id').value = client_id;
            document.getElementById('edit-supplier-id').value = supplier_id;
            document.getElementById('edit-service-type-id').value = service_type_id;
            document.getElementById('edit-invoicing_company_id').value = invoicing_company_id;
            document.getElementById('edit-description').value = description;
            document.getElementById('edit-quantity').value = qty;
            document.getElementById('edit-unit-price').value = unit_price;
            document.getElementById('edit-vat-rate').value = vat_rate;
            document.getElementById('edit-invoice-frequency').value = invoice_frequency;
            document.getElementById('edit-start-date').value = start_date;
            document.getElementById('edit-end-date').value = end_date;
            document.getElementById('edit-charge-vat').checked = charge_vat == 1 ? true : false;

            // Fetch categories for the selected service type
            const formData = new FormData();
            formData.append('action', 'fetch_categories');
            formData.append('service_type_id', service_type_id);

            axios.post('backend.php', formData)
                .then(response => {
                    const categories = response.data;
                    const categoryDropdown = document.getElementById('edit-service-category-id');
                    categoryDropdown.innerHTML = '<option value="">Select Service Category</option>';

                    categories.forEach(category => {
                        const option = document.createElement('option');
                        option.value = category.id;
                        option.textContent = category.category_name;
                        option.setAttribute('data-vm', category.has_vm_fields);
                        categoryDropdown.appendChild(option);
                    });

                    // Set the selected category
                    categoryDropdown.value = service_category_id;

                    // Show VM fields if applicable
                    const selectedOption = categoryDropdown.querySelector(`option[value='${service_category_id}']`);
                    const isVMCategory = selectedOption ? selectedOption.getAttribute('data-vm') : '0';

                    if (isVMCategory == '1') {
                        document.getElementById('vmFieldsEdit').style.display = 'block';
                        document.getElementById('edit-cpu').value = cpu;
                        document.getElementById('edit-memory').value = memory;
                        document.getElementById('edit-hdd-sata').value = hdd_sata;
                        document.getElementById('edit-hdd-ssd').value = hdd_ssd;
                        document.getElementById('edit-os').value = os;
                        document.getElementById('edit-ip-address').value = ip_address;
                    } else {
                        document.getElementById('vmFieldsEdit').style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Failed to fetch categories:', error);
                });

            var editModal = new bootstrap.Modal(document.getElementById('editBillingModal'));
            editModal.show();
        }
    </script>
    <script>
        function reloadPage() {
            setTimeout(() => location.reload(), 800);
        }
        // frequency 
        // function handleFrequencyFields(frequency) {
        //     const startLabel = document.getElementById('start-date-label');
        //     const endDateWrapper = document.getElementById('end-date-wrapper');

        //     if (frequency === 'once_off') {
        //         startLabel.innerText = 'Invoice Date (Month & Day)';
        //         endDateWrapper.style.display = 'none';
        //     } else if (frequency === 'monthly' || frequency === 'finance') {
        //         startLabel.innerText = 'Start Date';
        //         endDateWrapper.style.display = 'block';
        //     }
        // }

        // Call once if already selected (e.g., during edit)
        document.addEventListener('DOMContentLoaded', function() {
            const freq = document.getElementById('invoice-frequency')?.value;
            // if (freq) handleFrequencyFields(freq);
        });

        // ➕ Handle Add Billing
        document.getElementById('addBillingForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'add');

            axios.post('backend.php', formData)
                .then(response => {
                    if (response.data.trim() === 'success') {
                        alert('Billing Item Added Successfully ✅');
                        location.reload(); // or reloadPage();
                    } else {
                        alert('Error adding billing item ❌\nServer Response: ' + response.data);
                    }
                })
                .catch(error => {
                    alert('Server Error ❌');
                    console.error(error);
                });
        });
        // ✏️ Handle Edit Billing Form Submit
        document.getElementById('editBillingForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'edit'); // Set action for backend

            axios.post('backend.php', formData)
                .then(response => {
                    if (response.data.trim() === 'success') {
                        alert('Billing Item Updated Successfully ✅');

                        // 🔒 Close the modal
                        const editModal = bootstrap.Modal.getInstance(document.getElementById('editBillingModal'));
                        editModal.hide();

                        // ✅ Reapply filters to refresh table without reloading
                        const filterForm = document.getElementById('filterForm');
                        const filterData = new FormData(filterForm);

                        axios.post('billing-filter.php', filterData)
                            .then(filterResponse => {
                                const tbody = document.querySelector('table tbody');
                                tbody.innerHTML = filterResponse.data;
                            })
                            .catch(filterError => {
                                alert('Failed to refresh table ❌');
                                console.error(filterError);
                            });

                    } else {
                        alert('Error updating billing item ❌\nServer: ' + response.data);
                    }
                })
                .catch(error => {
                    alert('Server Error ❌');
                    console.error(error);
                });
        });

        // Dynamically show/hide Edit VM fields
        function handleEditVMFields(select) {
            const selectedOption = select.options[select.selectedIndex];
            const isVM = selectedOption.getAttribute('data-vm') == "1";

            if (isVM) {
                document.getElementById('vmFieldsEdit').style.display = 'block';
            } else {
                document.getElementById('vmFieldsEdit').style.display = 'none';
                // Optionally clear VM fields
                document.getElementById('edit-cpu').value = '';
                document.getElementById('edit-memory').value = '';
                document.getElementById('edit-hdd-sata').value = '';
                document.getElementById('edit-hdd-ssd').value = '';
                document.getElementById('edit-os').value = '';
                document.getElementById('edit-ip-address').value = '';
            }
        }

        function openDeleteModal(id) {
            if (confirm('Are you sure you want to delete this billing item?')) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);

                axios.post('backend.php', formData)
                    .then(response => {
                        if (response.data.trim() === 'success') {
                            alert('Billing Item Deleted Successfully ✅');
                            location.reload();
                        } else {
                            alert('Failed to delete billing item ❌\nServer: ' + response.data);
                        }
                    })
                    .catch(error => {
                        alert('Server Error ❌');
                        console.error(error);
                    });
            }
        }
        // Handle Filter Form Submit
        document.getElementById('filterForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            axios.post('billing-filter.php', formData)
                .then(response => {
                    const tbody = document.querySelector('table tbody');
                    tbody.innerHTML = response.data;
                })
                .catch(error => {
                    alert('Failed to filter results ❌');
                    console.error(error);
                });
        });

        // Reset Filters
        function resetFilters() {
            document.getElementById('filterForm').reset();
            document.getElementById('filterForm').dispatchEvent(new Event('submit'));
        }
        // Show/Hide VM Fields on Service Category Change
        function handleVMFields(select) {
            var selectedOption = select.options[select.selectedIndex];
            var isVMCategory = selectedOption.getAttribute('data-vm') === '1';

            document.getElementById('vmFieldsAdd').style.display = isVMCategory ? 'flex' : 'none';

            const serviceCategoryId = select.value;

            if (serviceCategoryId) {
                const formData = new FormData();
                formData.append('action', 'fetch_unit_price'); // 👈 Important: pass action
                formData.append('service_category_id', serviceCategoryId);

                axios.post('backend.php', formData) // 👈 Post to backend.php now
                    .then(response => {
                        const data = response.data;
                        if (data.unit_price !== undefined) {
                            document.querySelector('input[name="unit_price"]').value = data.unit_price;
                        } else {
                            document.querySelector('input[name="unit_price"]').value = '';
                        }
                    })
                    .catch(error => {
                        console.error('Failed to fetch unit price:', error);
                    });
            } else {
                document.querySelector('input[name="unit_price"]').value = '';
            }
        }

        // Show/Hide VM Fields on Service Category Change in Edit Modal
        function handleEditVMFields(select) {
            var selectedOption = select.options[select.selectedIndex];
            var isVMCategory = selectedOption.getAttribute('data-vm') === '1';

            // ➡️ Show/Hide VM Fields
            document.getElementById('vmFieldsEdit').style.display = isVMCategory ? 'flex' : 'none';

            // ➡️ Fetch and Autofill Unit Price in Edit Modal
            const serviceCategoryId = select.value;

            if (serviceCategoryId) {
                const formData = new FormData();
                formData.append('action', 'fetch_unit_price'); // 👈 Important: action for backend
                formData.append('service_category_id', serviceCategoryId);

                axios.post('backend.php', formData) // 👈 Post to your backend.php
                    .then(response => {
                        const data = response.data;
                        if (data.unit_price !== undefined) {
                            document.getElementById('edit-unit-price').value = data.unit_price;
                        } else {
                            document.getElementById('edit-unit-price').value = '';
                        }
                    })
                    .catch(error => {
                        console.error('❌ Failed to fetch unit price in edit modal:', error);
                    });
            } else {
                document.getElementById('edit-unit-price').value = '';
            }
        }
        // Live sub total
        function updateBillingSummary() {
            const qty = parseFloat(document.querySelector('input[name="quantity"]').value) || 0;
            const unitPrice = parseFloat(document.querySelector('input[name="unit_price"]').value) || 0;
            const vatRate = parseFloat(document.querySelector('input[name="vat_rate"]').value) || 0;

            const subtotal = qty * unitPrice;
            const vatAmount = (vatRate / 100) * subtotal;
            const total = subtotal + vatAmount;

            document.getElementById('subtotalDisplay').innerText = subtotal.toFixed(2);
            document.getElementById('vatDisplay').innerText = vatAmount.toFixed(2);
            document.getElementById('totalDisplay').innerText = total.toFixed(2);
        }

        // ➡️ Attach events to live update
        document.querySelector('input[name="quantity"]').addEventListener('input', updateBillingSummary);
        document.querySelector('input[name="unit_price"]').addEventListener('input', updateBillingSummary);
        document.querySelector('input[name="vat_rate"]').addEventListener('input', updateBillingSummary);

        // edit sub total
        function updateEditBillingSummary() {
            const qty = parseFloat(document.getElementById('edit-quantity').value) || 0;
            const unitPrice = parseFloat(document.getElementById('edit-unit-price').value) || 0;
            const vatRate = parseFloat(document.getElementById('edit-vat-rate').value) || 0;

            const subtotal = qty * unitPrice;
            const vatAmount = (vatRate / 100) * subtotal;
            const total = subtotal + vatAmount;

            document.getElementById('edit-subtotalDisplay').innerText = subtotal.toFixed(2);
            document.getElementById('edit-vatDisplay').innerText = vatAmount.toFixed(2);
            document.getElementById('edit-totalDisplay').innerText = total.toFixed(2);
        }

        // ➡️ Attach event listeners for Edit Modal fields
        document.getElementById('edit-quantity').addEventListener('input', updateEditBillingSummary);
        document.getElementById('edit-unit-price').addEventListener('input', updateEditBillingSummary);
        document.getElementById('edit-vat-rate').addEventListener('input', updateEditBillingSummary);
    </script>

    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script>
        function openViewModal(id) {
            const modalBody = document.getElementById('viewBillingModalBody');
            modalBody.innerHTML = '<div class="text-center py-4 text-muted">Loading billing details...</div>';

            // Fetch data from backend using AJAX
            axios.get('view_billing.php?id=' + id)
                .then(response => {
                    modalBody.innerHTML = response.data; // response is rendered HTML
                })
                .catch(error => {
                    modalBody.innerHTML = '<div class="alert alert-danger">Failed to load billing details.</div>';
                    console.error(error);
                });

            // Show modal
            const viewModal = new bootstrap.Modal(document.getElementById('viewBillingModal'));
            viewModal.show();
        }
    </script>


</body>

</html>