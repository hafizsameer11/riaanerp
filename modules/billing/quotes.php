<?php
session_start();
// Database Connection
include_once '../config.php'; // Ensure this path is correct
// making query to get all clients and companys
$clients = $conn->query("SELECT * FROM clients");
$companies = $conn->query("SELECT * FROM billing_invoice_companies");
$serviceTypes = $conn->query("SELECT * FROM billing_service_types");
$serviceCategories = $conn->query("SELECT * FROM billing_service_categories");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Quotes Module</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body class="p-5">

    <div class="pt-4">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?= $_SESSION['success']; ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?= $_SESSION['error']; ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif;
        session_write_close();
        ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center">
                <?php session_start(); ?>
                <?php include('../components/permissioncheck.php') ?>
                <h2 class="">Quotes Module</h2>
            </div>

            <?php if (hasPermission('quotes', 'create')): ?>
                <!-- Add Quote Button -->
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addQuoteModal">Add Quote</button>
            <?php endif; ?>
        </div>

        <!-- Filter Form -->
        <form class="card shadow-sm mb-4 p-4" method="GET">
            <div class="row g-3 align-items-end">
                <!-- Client Filter -->
                <div class="col-md-3">
                    <label class="form-label">Client</label>
                    <select name="client_id" class="form-select">
                        <option value="">All Clients</option>
                        <?php
                        $clients->data_seek(0); // Reset pointer for reuse
                        while ($client = $clients->fetch_assoc()): ?>
                            <option value="<?= $client['id'] ?>" <?= isset($_GET['client_id']) && $_GET['client_id'] == $client['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($client['client_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Company Filter -->
                <div class="col-md-3">
                    <label class="form-label">Company</label>
                    <select name="company_id" class="form-select">
                        <option value="">All Companies</option>
                        <?php
                        $companies->data_seek(0); // Reset pointer for reuse
                        while ($company = $companies->fetch_assoc()): ?>
                            <option value="<?= $company['id'] ?>" <?= isset($_GET['company_id']) && $_GET['company_id'] == $company['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($company['company_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Status Filter -->
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="Quoted" <?= ($_GET['status'] ?? '') === 'Quoted' ? 'selected' : '' ?>>Quoted
                        </option>
                        <option value="Followed up" <?= ($_GET['status'] ?? '') === 'Followed up' ? 'selected' : '' ?>>
                            Followed up</option>
                        <option value="Declined" <?= ($_GET['status'] ?? '') === 'Declined' ? 'selected' : '' ?>>Declined
                        </option>
                        <option value="Approved" <?= ($_GET['status'] ?? '') === 'Approved' ? 'selected' : '' ?>>Approved
                        </option>
                    </select>
                </div>

                <!-- Apply and Reset Buttons -->
                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="quotes.php" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>

        <!-- Quotes Table -->
        <h4>All Quotes</h4>

        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Quote No</th>
                    <th>Client</th>
                    <th>Company</th>
                    <th>Items</th> <!-- New column for items -->
                    <th>Total</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $where = [];
                if (!empty($_GET['client_id'])) {
                    $where[] = "q.client_id = " . (int) $_GET['client_id'];
                }
                if (!empty($_GET['company_id'])) {
                    $where[] = "q.quoted_company_id = " . (int) $_GET['company_id'];
                }
                if (!empty($_GET['status'])) {
                    $where[] = "q.status = '" . $conn->real_escape_string($_GET['status']) . "'";
                }
                if (!hasPermission('quotes', 'View all')) {
                    $where[] = "q.created_by = " . (int) $_SESSION['user_id'];
                }

                $filterSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

                $quotes = $conn->query("
                    SELECT 
                        q.*, 
                        c.client_name, 
                        c.currency,
                        c.currency_symbol,
                        b.company_name
                    FROM quotes q
                    JOIN clients c ON q.client_id = c.id
                    JOIN billing_invoice_companies b ON q.quoted_company_id = b.id
                    $filterSql
                    ORDER BY q.id DESC
                "); ?>
                <?php
                $i = 1;
                while ($row = $quotes->fetch_assoc()):
                    // Fetch items for the current quote
                    $items = $conn->query("SELECT 
                st.service_type_name, 
                sc.category_name, 
                qi.qty, 
                qi.unit_price, 
                qi.vat, 
                qi.total_incl_vat 
                FROM quote_items qi
                JOIN billing_service_types st ON qi.service_type_id = st.id
                JOIN billing_service_categories sc ON qi.service_category_id = sc.id
                WHERE qi.quote_id = " . (int) $row['id']);
                    ?>
                    <tr>
                        <td class="text-center"><?= $i++ ?></td>
                        <td><?= htmlspecialchars($row['quote_number']) ?></td>
                        <td><?= htmlspecialchars($row['client_name']) ?></td>
                        <td><?= htmlspecialchars($row['company_name']) ?></td>
                        <td>
                            <ul>
                                <?php while ($item = $items->fetch_assoc()): ?>
                                    <li>
                                        <?= htmlspecialchars($item['service_type_name']) ?> -
                                        <?= htmlspecialchars($item['category_name']) ?>:
                                        <?= $item['qty'] ?> x <?= number_format($item['unit_price'], 2) ?>
                                        (VAT: <?= number_format($item['vat'], 2) ?>%)
                                    </li>
                                <?php endwhile; ?>
                            </ul>
                        </td>
                        <td class="text-end">
                            <?= $row['currency_symbol'] ? $row['currency_symbol'] : (isset($row['currency'][0]) ? $row['currency'][0] : '') ?>  
                            <?= number_format($row['total'], 2) ?>
                        </td>
                        <td class="text-center"><?= htmlspecialchars($row['status']) ?></td>
                        <td class="text-center">
                            <div class="btn-group" role="group">
                                <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#viewQuoteModal"
                                    data-id="<?= $row['id'] ?>">
                                    View
                                </button>
                                <?php if (hasPermission('quotes', 'send_email')): ?>
                                    <button class="btn btn-sm btn-success" data-bs-toggle="modal"
                                        data-bs-target="#sendQuoteModal" data-id="<?= $row['id'] ?>">
                                        Send Email
                                    </button>
                                <?php endif; ?>
                                <?php if (hasPermission('quotes', 'update')): ?>
                                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal"
                                        data-bs-target="#editQuoteModal" data-id="<?= $row['id'] ?>">
                                        Edit
                                    </button>
                                <?php endif; ?>
                                <?php if (hasPermission('quotes', 'delete')): ?>
                                    <button class="btn btn-sm btn-danger" data-bs-toggle="modal"
                                        data-bs-target="#deleteQuoteModal" data-id="<?= $row['id'] ?>">
                                        Delete
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Add Quote Modal -->
    <div class="modal fade" id="addQuoteModal" tabindex="-1" aria-labelledby="addQuoteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl"> <!-- wider modal -->
            <form method="POST" action="quotes_actions.php">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addQuoteModalLabel">Add Quote</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <?php
                        $latest = $conn->query("SELECT quote_number FROM quotes ORDER BY id DESC LIMIT 1");
                        $newQuoteNumber = "STQ-110000";
                        if ($latest->num_rows > 0) {
                            $last = $latest->fetch_assoc()['quote_number'];
                            $num = intval(str_replace("STQ-", "", $last)) + 1;
                            $newQuoteNumber = "STQ-" . $num;
                        }
                        ?>
                        <input type="hidden" name="action" value="add">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Quote Number</label>
                                <input type="text" name="quote_number" class="form-control"
                                    value="<?= $newQuoteNumber ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Reference</label>
                                <input type="text" name="reference" class="form-control"
                                    placeholder="Enter customer reference">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Client</label>
                                <select name="client_id" id="add-client" class="form-select" required>
                                    <option value="" disabled selected>Select a client</option>
                                    <?php $clients->data_seek(0);
                                    while ($client = $clients->fetch_assoc()): ?>
                                        <option value="<?= $client['id'] ?>"><?= $client['client_name'] ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Invoicing Company</label>
                                <select name="quoted_company_id" class="form-select" required>
                                    <option value="" disabled selected>Select a company</option>
                                    <?php $companies->data_seek(0);
                                    while ($company = $companies->fetch_assoc()): ?>
                                        <option value="<?= $company['id'] ?>"><?= $company['company_name'] ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Currency</label>
                                <input type="text" id="add-currencey" placeholder="Currency" class="form-control" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Currency Symbol</label>
                                <input type="text" id="add-currencey-symbol" placeholder="Currency Symbol" class="form-control" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Quote Date</label>
                                <input type="date" name="quote_date" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Due Date</label>
                                <input type="date" name="due_date" class="form-control" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Sales Person</label>
                                <input type="text" name="sales_person" class="form-control"
                                    placeholder="Sales person name">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option>Quoted</option>
                                    <option>Followed up</option>
                                    <option>Declined</option>
                                    <option>Approved</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Note</label>
                                <textarea name="note" class="form-control" required></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Quote Items</label>
                                <table class="table table-bordered" id="quote-items-table">
                                    <thead>
                                        <tr>
                                            <th>Service Type</th>
                                            <th>Service Category</th>
                                            <th>Description</th> <!-- New column for description -->
                                            <th>Qty</th>
                                            <th>Unit Price</th>
                                            <th>VAT %</th>
                                            <th>Price Ex VAT</th>
                                            <th>Total Incl VAT</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="quote-items-body">
                                        <!-- Rows will be added dynamically -->
                                    </tbody>
                                </table>
                                <button type="button" class="btn btn-secondary" id="add-item-btn">Add Item</button>
                            </div>
                            <div class="mb-3">
                                <h4>Totals</h4>
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="form-label">Sub Total</label>
                                        <input type="text" id="add-sub-total" class="form-control" readonly>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Discount (%)</label>
                                        <input type="number" id="add-discount" name="discount" class="form-control"
                                            value="0" step="0.01" min="0" max="100">
                                    </div>
                                    <!-- <div class="col-md-3">
                                        <label class="form-label">Total Exclusive</label>
                                        <input type="text" id="add-total-exclusive" class="form-control" readonly>
                                    </div> -->
                                    <div class="col-md-3">
                                        <label class="form-label">Total VAT</label>
                                        <input type="text" id="add-total-vat" class="form-control" readonly>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">After Discount</label>
                                        <input type="text" id="add-after-discount" class="form-control" readonly>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Grand Total</label>
                                        <input type="text" id="add-grand-total" class="form-control" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Add Quote</button>
                    </div>
                </div>
            </form>
        </div>
    </div>


    <!-- Edit Quote Modal -->
    <div class="modal fade" id="editQuoteModal" tabindex="-1" aria-labelledby="editQuoteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form method="POST" action="quotes_actions.php">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editQuoteModalLabel">Edit Quote</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit-id">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Quote Number</label>
                                <input type="text" name="quote_number" id="edit-quote-number" class="form-control"
                                    readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Reference</label>
                                <input type="text" name="reference" id="edit-reference" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Client</label>
                                <select name="client_id" id="edit-client-id" class="form-select" required>
                                    <option value="" disabled>Select a client</option>
                                    <?php $clients->data_seek(0);
                                    while ($client = $clients->fetch_assoc()): ?>
                                        <option value="<?= $client['id'] ?>"><?= $client['client_name'] ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Invoicing Company</label>
                                <select name="quoted_company_id" id="edit-company-id" class="form-select" required>
                                    <option value="" disabled>Select a company</option>
                                    <?php $companies->data_seek(0);
                                    while ($company = $companies->fetch_assoc()): ?>
                                        <option value="<?= $company['id'] ?>"><?= $company['company_name'] ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Currency</label>
                                <input type="text" id="edit-currencey" placeholder="Currency" class="form-control" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Currency Symbol</label>
                                <input type="text" id="edit-currencey-symbol" placeholder="Currency Symbol" class="form-control" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Quote Date</label>
                                <input type="date" name="quote_date" id="edit-quote-date" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Due Date</label>
                                <input type="date" name="due_date" id="edit-due-date" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Sales Person</label>
                                <input type="text" name="sales_person" id="edit-sales-person" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" id="edit-status" class="form-select">
                                    <option>Quoted</option>
                                    <option>Followed up</option>
                                    <option>Declined</option>
                                    <option>Approved</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Note</label>
                                <textarea name="note" id="edit-description" class="form-control"></textarea>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Quote Items</label>
                            <table class="table table-bordered" id="edit-quote-items-table">
                                <thead>
                                    <tr>
                                        <th>Service Type</th>
                                        <th>Service Category</th>
                                        <th>Description</th> <!-- New column for description -->
                                        <th>Qty</th>
                                        <th>Unit Price</th>
                                        <th>VAT %</th>
                                        <th>Price Ex VAT</th>
                                        <th>Total Incl VAT</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="edit-quote-items-body">
                                    <!-- Rows will be dynamically populated -->
                                </tbody>
                            </table>
                            <button type="button" class="btn btn-secondary" id="edit-add-item-btn">Add Item</button>
                        </div>
                        <div class="mb-3">
                            <h4>Totals</h4>
                            <div class="row">
                                <div class="col-md-3">
                                    <label class="form-label">Sub Total</label>
                                    <input type="text" id="edit-sub-total" class="form-control" readonly>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Discount (%)</label>
                                    <input type="number" id="edit-discount" name="discount" class="form-control"
                                        value="0" step="0.01" min="0" max="100">
                                </div>
                                <!-- <div class="col-md-3">
                                    <label class="form-label">Total Exclusive</label>
                                    <input type="text" id="edit-total-exclusive" class="form-control" readonly>
                                </div> -->
                                <div class="col-md-3">
                                    <label class="form-label">Total VAT</label>
                                    <input type="text" id="edit-total-vat" class="form-control" readonly>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">After Discount</label>
                                    <input type="text" id="edit-after-discount" class="form-control" readonly>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Grand Total</label>
                                    <input type="text" id="edit-grand-total" class="form-control" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-warning">Update Quote</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Quote Modal -->
    <div class="modal fade" id="deleteQuoteModal" tabindex="-1" aria-labelledby="deleteQuoteModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="quotes_actions.php">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteQuoteModalLabel">Delete Quote</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="delete-id">
                        <p>Are you sure you want to delete this quote?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="viewQuoteModal" tabindex="-1" aria-labelledby="viewQuoteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewQuoteModalLabel">Quote Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><strong>Quote Number:</strong> <span id="view-quote-number"></span></div>
                        <div class="col-md-6"><strong>Client:</strong> <span id="view-client-name"></span></div>
                        <div class="col-md-6"><strong>Invoicing Company:</strong> <span id="view-company-name"></span>
                        </div>
                        <div class="col-md-6"><strong>Status:</strong> <span id="view-status"></span></div>
                        <div class="col-md-6"><strong>Sales Person:</strong> <span id="view-sales-person"></span></div>
                        <div class="col-md-6"><strong>Reference:</strong> <span id="view-reference"></span></div>
                        <div class="col-md-6"><strong>Quote Date:</strong> <span id="view-quote-date"></span></div>
                        <div class="col-md-6"><strong>Due Date:</strong> <span id="view-due-date"></span></div>
                        <div class="col-12"><strong>Note:</strong> <span id="view-description"></span></div>
                    </div>
                    <hr>
                    <h5>Quote Items</h5>
                    <div id="view-items">
                        <!-- Items will be dynamically populated -->
                    </div>
                    <hr>
                    <h5>Totals</h5>
                    <div class="row">
                        <div class="col-md-3"><strong>Sub Total:</strong> <span id="view-sub-total">0.00</span>
                        </div>
                        <div class="col-md-3"><strong>Discount (%):</strong> <span id="view-discount">0.00</span>
                        </div>
                        <div class="col-md-3"><strong>Total VAT:</strong> <span id="view-total-vat">0.00</span>
                        </div>
                        <div class="col-md-3"><strong>After Discount:</strong> <span
                                id="view-after-discount">0.00</span></div>
                        <div class="col-md-3"><strong>Grand Total:</strong> <span id="view-grand-total">0.00</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- send email -->
    <!-- Send Quote Modal -->
    <div class="modal fade" id="sendQuoteModal" tabindex="-1" aria-labelledby="sendQuoteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="quotes_actions.php">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Send Quote</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="send_quote">
                        <input type="hidden" name="quote_id" id="send-quote-id">
                        <div class="mb-3">
                            <label>Sender Name</label>
                            <input type="text" name="sender_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Recipient Name</label>
                            <input type="text" name="recipient_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Recipient Email</label>
                            <input type="email" name="recipient_email" id="send-recipient-email" class="form-control"
                                required>
                        </div>
                        <div class="mb-3">
                            <label>Message (optional)</label>
                            <textarea name="message" class="form-control"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-success">Send Quote</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script>
        const serviceTypes = <?= json_encode(iterator_to_array($serviceTypes, true)) ?>;
        const serviceCategories = <?= json_encode(iterator_to_array($serviceCategories, true)) ?>;
        const companies = <?= json_encode(iterator_to_array($companies, true)) ?>;
        const clients = <?= json_encode(iterator_to_array($clients, true)) ?>;
        document.getElementById('add-client').addEventListener('change', function () {
            const selectedClient = clients.find(client => client.id == this.value);
            if (selectedClient) {
                document.getElementById('add-currencey').value = selectedClient.currency;
                document.getElementById('add-currencey-symbol').value = selectedClient.currency_symbol;
            }
        });
        document.getElementById('edit-client-id').addEventListener('change', function () {
            const selectedClient = clients.find(client => client.id == this.value);
            if (selectedClient) {
                document.getElementById('edit-currencey').value = selectedClient.currency;
                document.getElementById('edit-currencey-symbol').value = selectedClient.currency_symbol;
            }
        });
        let invoice_company_Id = null;
        const companySelect = document.querySelector('[name="quoted_company_id"]');
        companySelect.addEventListener('change', function () {
            invoice_company_Id = companySelect.value;
        })
        const discountInput = document.getElementById('add-discount');

        // Recalculate totals when the discount value changes

        const AllCompanyVat = document.querySelectorAll('[name="quoted_company_id"]');
        AllCompanyVat.forEach(select => {
            select.addEventListener('change', function () {
                // change all vat[] values to the selected company's VAT rate
                const vatRate = companies.find(company => company.id == select.value);
                console.log
                const vatInputs = document.querySelectorAll('[name="vat[]"]');
                vatInputs.forEach(vatInput => {
                    vatInput.value = vatRate ? vatRate.vat_rate : 0;
                });
            });
        });


        console.log(invoice_company_Id);
        document.addEventListener('DOMContentLoaded', () => {
            const vatInput = document.getElementById('add-vat'); // VAT input field
            const quoteItemsBody = document.getElementById('quote-items-body');
            const quoteTotal = document.getElementById('quote-total');
            // Add a new row to the quote items table
            function addQuoteItemRow(data = {}) {
                const row = document.createElement('tr');
                row.innerHTML = `
        <td>
            <select name="service_type_id[]" class="form-select service-type" required>
                <option value="">Select Service Type</option>
                ${serviceTypes.map(st => `<option value="${st.id}" ${data.service_type_id == st.id ? 'selected' : ''}>${st.service_type_name}</option>`).join('')}
            </select>
        </td>
        <td>
            <select name="service_category_id[]" class="form-select service-category" required>
                <option value="">Select Service Category</option>
            </select>
        </td>
        <td>
            <input type="text" name="description[]" class="form-control description" value="${data.description || ''}" placeholder="Enter description">
        </td>
        <td>
            <input type="number" name="qty[]" class="form-control qty" value="${data.qty || 1}" min="1" required>
        </td>
        <td>
            <input type="number" name="unit_price[]" class="form-control unit-price" value="${data.unit_price || ''}" step="0.01" required>
        </td>
        <td>
            <input type="number" name="vat[]" class="form-control vat" value="${data.vat || 0}" step="0.01" readonly>
        </td>
        <td>
            <input type="number" name="price-ex-vat[]" class="form-control price-ex-vat" value="${data.price_ex_vat || ''}" step="0.01" readonly>
        </td>
        <td>
            <input type="number" name="total-incl-vat[]" class="form-control total-incl-vat" value="${data.total_incl_vat || ''}" step="0.01" readonly>
        </td>
        <td>
            <button type="button" class="btn btn-danger btn-sm remove-item-btn">&times;</button>
        </td>
    `;
                document.getElementById('quote-items-body').appendChild(row);
                updateRowEvents(row);
            }
            // Update events for a row
            function updateRowEvents(row) {
                const serviceTypeSelect = row.querySelector('.service-type');
                const serviceCategorySelect = row.querySelector('.service-category');
                const qtyInput = row.querySelector('.qty');
                const unitPriceInput = row.querySelector('.unit-price');
                const vatInput = row.querySelector('.vat');
                const priceExVatInput = row.querySelector('.price-ex-vat');
                const totalInclVatInput = row.querySelector('.total-incl-vat');

                // Fetch categories when service type changes
                serviceTypeSelect.addEventListener('change', (event) => {
                    event.preventDefault(); // Prevent form submission
                    const typeId = serviceTypeSelect.value;
                    console.log("Service type selected:", typeId); // Debug log
                    fetchCategories(typeId, serviceCategorySelect);
                });

                // Fetch unit price when service category changes
                serviceCategorySelect.addEventListener('change', () => {
                    event.preventDefault(); // Prevent form submission
                    const categoryId = serviceCategorySelect.value;
                    fetchUnitPrice(categoryId, unitPriceInput, vatInput, qtyInput, priceExVatInput, totalInclVatInput);
                });

                // Recalculate totals when qty or unit price changes
                [qtyInput, unitPriceInput].forEach(input => {
                    input.addEventListener('input', () => {
                        updateRowTotals(qtyInput, unitPriceInput, vatInput, priceExVatInput, totalInclVatInput);
                    });
                });

                // Remove row
                const removeButton = row.querySelector('.remove-item-btn');
                removeButton.addEventListener('click', () => {
                    row.remove(); // Remove the row from the DOM
                    updateTotal(); // Recalculate the total
                });
            }

            document.getElementById('quote-items-body').addEventListener('click', function (e) {
                if (e.target.classList.contains('remove-item-btn')) {
                    const row = e.target.closest('tr');
                    row.remove();
                    updateTotal(); // Recalculate the total after row removal
                }
            });
            document.getElementById('edit-quote-items-body').addEventListener('click', function (e) {
                if (e.target.classList.contains('remove-item-btn')) {
                    const row = e.target.closest('tr');
                    row.remove();
                    updateTotal(); // Recalculate the total after row removal
                }
            });

            // Fetch categories for a service type
            function fetchCategories(typeId, categorySelect) {
                const formData = new URLSearchParams();
                formData.append("action", "fetch_categories");
                formData.append("service_type_id", typeId);

                axios.post('fetchData.php', formData)
                    .then(response => {
                        console.log("Categories fetched:", response.data);
                        const categories = Array.isArray(response.data) ? response.data : [];
                        categorySelect.innerHTML = '<option value="">Select Service Category</option>';
                        categories.forEach(category => {
                            const option = document.createElement('option');
                            option.value = category.id;
                            option.textContent = category.category_name;
                            categorySelect.appendChild(option);
                        });
                    })
                    .catch(err => console.error('Error fetching categories:', err));
            }

            // Fetch unit price and VAT for a service category
            function fetchUnitPrice(categoryId, unitPriceInput, vatInput, qtyInput, priceExVatInput, totalInclVatInput) {
                const formData = new URLSearchParams();
                formData.append("action", "fetch_unit_price");
                formData.append("service_category_id", categoryId);
                console.log("service_category_id", categoryId);
                formData.append("company_id", invoice_company_Id);
                console.log(invoice_company_Id);
                axios.post('fetchData.php', formData)
                    .then(response => {
                        console.log("Unit price fetched:", response.data);
                        console.log('company id after fetch :', invoice_company_Id);
                        const data = response.data;
                        unitPriceInput.value = data.unit_price || 0;
                        vatInput.value = data.company_vat || 0;
                        updateRowTotals(qtyInput, unitPriceInput, vatInput, priceExVatInput, totalInclVatInput);
                    })
                    .catch(err => console.error('Error fetching unit price:', err));
            }


            // Update totals for a row
            function updateRowTotals(qtyInput, unitPriceInput, vatInput, priceExVatInput, totalInclVatInput) {
                const qty = parseFloat(qtyInput.value) || 0;
                const unitPrice = parseFloat(unitPriceInput.value) || 0;
                const vat = parseFloat(vatInput.value) || 0;

                const priceExVat = qty * unitPrice;
                const totalInclVat = priceExVat + (priceExVat * vat / 100);

                priceExVatInput.value = priceExVat.toFixed(2);
                totalInclVatInput.value = totalInclVat.toFixed(2);

                updateTotal();
            }

            // Update the total for all rows
            function updateTotal() {
                let subTotal = 0;
                let totalVAT = 0;
                let discountPercentage = parseFloat(document.getElementById('add-discount').value) || 0;

                document.querySelectorAll('#quote-items-body tr').forEach(row => {
                    const qty = parseFloat(row.querySelector('.qty').value) || 0;
                    const unitPrice = parseFloat(row.querySelector('.unit-price').value) || 0;
                    const vat = parseFloat(row.querySelector('.vat').value) || 0;

                    const priceExVat = qty * unitPrice;
                    const totalInclVat = priceExVat + (priceExVat * vat / 100);

                    subTotal += priceExVat;
                    totalVAT += priceExVat * vat / 100;
                });

                const discountAmount = (subTotal * discountPercentage) / 100;
                const afterDiscount = subTotal - discountAmount;

                document.getElementById('add-sub-total').value = subTotal.toFixed(2);
                document.getElementById('add-total-vat').value = totalVAT.toFixed(2);
                document.getElementById('add-after-discount').value = afterDiscount.toFixed(2);
                document.getElementById('add-grand-total').value = (afterDiscount + totalVAT).toFixed(2);
            }

            // Add initial row
            document.getElementById('add-item-btn').addEventListener('click', () => addQuoteItemRow());
            addQuoteItemRow();
            discountInput.addEventListener('input', () => {
                updateTotal();
            });

        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sendQuoteModal = document.getElementById('sendQuoteModal');

            sendQuoteModal.addEventListener('show.bs.modal', (event) => {
                const button = event.relatedTarget;
                const quoteId = button.getAttribute('data-id');
                document.getElementById('send-quote-id').value = quoteId;
            });
        });
        // view1
        document.addEventListener('DOMContentLoaded', () => {
            const viewQuoteModal = document.getElementById('viewQuoteModal');

            viewQuoteModal.addEventListener('show.bs.modal', (event) => {
                const button = event.relatedTarget;
                const quoteId = button.getAttribute('data-id');
                const formData = new URLSearchParams();
                formData.append("action", "fetch_quote_details");
                formData.append("quote_id", quoteId);

                // Fetch quote details
                axios.post('fetchData.php', formData)
                    .then(response => {
                        const data = response.data;

                        // Populate main quote fields
                        document.getElementById('view-quote-number').textContent = data.quote.quote_number;
                        document.getElementById('view-client-name').textContent = data.quote.client_name;
                        document.getElementById('view-company-name').textContent = data.quote.invoice_company_name;
                        document.getElementById('view-status').textContent = data.quote.status;
                        document.getElementById('view-sales-person').textContent = data.quote.sales_person;
                        document.getElementById('view-reference').textContent = data.quote.reference;
                        document.getElementById('view-quote-date').textContent = data.quote.quote_date;
                        document.getElementById('view-due-date').textContent = data.quote.due_date;
                        document.getElementById('view-after-discount').textContent = (
                            parseFloat(data.quote.total_exclusive)
                        ).toFixed(2);
                        document.getElementById('view-description').textContent = data.quote.description;

                        // Populate quote items
                        const itemsContainer = document.getElementById('view-items');
                        itemsContainer.innerHTML = ''; // Clear existing items
                        const table = document.createElement('table');
                        table.className = 'table table-sm table-bordered mb-0';
                        table.innerHTML = `
                    <thead>
                        <tr>
                            <th>Service Type</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">VAT %</th>
                            <th class="text-end">Price Ex VAT</th>
                            <th class="text-end">Total Incl VAT</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${data.items.map(item => `
                            <tr>
                                <td>${item.service_type_name}</td>
                                <td>${item.category_name}</td>
                                <td>${item.description || ''}</td>
                                <td class="text-end">${item.qty}</td>
                                <td class="text-end">${parseFloat(item.unit_price).toFixed(2)}</td>
                                <td class="text-end">${parseFloat(item.vat).toFixed(2)}</td>
                                <td class="text-end">${parseFloat(item.price_ex_vat).toFixed(2)}</td>
                                <td class="text-end">${parseFloat(item.total_incl_vat).toFixed(2)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                `;
                        itemsContainer.appendChild(table);

                        // Populate totals
                        document.getElementById('view-sub-total').textContent =data.quote.currency_symbol ? data.quote.currency_symbol : data.quote.currency.slice(0,1) + " "+  parseFloat(data.quote.total_exclusive).toFixed(2);
                        document.getElementById('view-discount').textContent =data.quote.currency_symbol ? data.quote.currency_symbol : data.quote.currency.slice(0,1) + " "+ parseFloat(data.quote.discount).toFixed(2);
                        document.getElementById('view-total-vat').textContent = data.quote.currency_symbol ? data.quote.currency_symbol : data.quote.currency.slice(0,1) + " "+parseFloat(data.quote.total_vat).toFixed(2);
                        document.getElementById('view-grand-total').textContent = data.quote.currency_symbol ? data.quote.currency_symbol : data.quote.currency.slice(0,1) + " "+ parseFloat(data.quote.total).toFixed(2);
                    })
                    .catch(err => console.error('Error fetching quote details:', err));
            });
        });

        // edit1
        document.addEventListener('DOMContentLoaded', () => {
            const editQuoteModal = document.getElementById('editQuoteModal');
            const editQuoteItemsBody = document.getElementById('edit-quote-items-body');
            let invoice_company_Id = null;
            // Show the Edit Modal and populate data
            editQuoteModal.addEventListener('show.bs.modal', (event) => {
                const button = event.relatedTarget;
                const quoteId = button.getAttribute('data-id');
                const formData = new URLSearchParams();
                formData.append("action", "fetch_quote_details");
                formData.append("quote_id", quoteId);

                // Fetch quote details
                axios.post('fetchData.php', formData)
                    .then(response => {
                        const data = response.data;

                        if (data.error) {
                            alert(data.error);
                            return;
                        }

                        // Populate main quote fields
                        document.getElementById('edit-id').value = data.quote.id;
                        document.getElementById('edit-quote-number').value = data.quote.quote_number;
                        document.getElementById('edit-reference').value = data.quote.reference;
                        document.getElementById('edit-sales-person').value = data.quote.sales_person;
                        document.getElementById('edit-quote-date').value = data.quote.quote_date;
                        document.getElementById('edit-due-date').value = data.quote.due_date;
                        document.getElementById('edit-client-id').value = data.quote.client_id;
                        document.getElementById('edit-company-id').value = data.quote.quoted_company_id;
                        invoice_company_Id = data.quote.quoted_company_id;
                        document.getElementById('edit-discount').value = data.quote.discount;
                        document.getElementById('edit-description').value = data.quote.description;
                        document.getElementById('edit-status').value = data.quote.status;
                        document.getElementById('edit-currencey').value = data.quote.currency;
                document.getElementById('edit-currencey-symbol').value = data.quote.currency_symbol;

                        // Populate totals
                        document.getElementById('edit-sub-total').value = parseFloat(data.quote.total_exclusive).toFixed(2);
                        document.getElementById('edit-total-vat').value = parseFloat(data.quote.total_vat).toFixed(2);
                        document.getElementById('edit-after-discount').value = (
                            parseFloat(data.quote.total_exclusive) -
                            (parseFloat(data.quote.total_exclusive) * parseFloat(data.quote.discount) / 100)
                        ).toFixed(2);
                        document.getElementById('edit-grand-total').value = parseFloat(data.quote.total).toFixed(2);

                        // Populate quote items
                        editQuoteItemsBody.innerHTML = ''; // Clear existing rows
                        data.items.forEach(item => {
                            const row = createEditRow(item);
                            editQuoteItemsBody.appendChild(row);
                        });

                        // Recalculate totals after populating items
                        updateEditTotal();
                    })
                    .catch(err => console.error('Error fetching quote details:', err));
            });

            // Function to create a new row for the Edit Quote Modal
            function createEditRow(data = {}) {
                const row = document.createElement('tr');
                row.innerHTML = `
            <td>
                <select name="service_type_id[]" class="form-select edit-service-type" required>
                    <option value="">Select Service Type</option>
                    ${serviceTypes.map(st => `<option value="${st.id}" ${data.service_type_id == st.id ? 'selected' : ''}>${st.service_type_name}</option>`).join('')}
                </select>
            </td>
            <td>
                <select name="service_category_id[]" class="form-select edit-service-category" required>
                    <option value="">Select Service Category</option>
                    ${serviceCategories
                        .filter(category => category.service_type_id == data.service_type_id)
                        .map(category => {
                            const selected = data.service_category_id == category.id ? 'selected' : '';
                            return `<option value="${category.id}" ${selected}>${category.category_name}</option>`;
                        }).join('')}
                </select>
            </td>
            <td>
                <input type="text" name="description[]" class="form-control edit-description" value="${data.description || ''}" placeholder="Enter description">
            </td>
            <td><input type="number" name="qty[]" class="form-control edit-qty" value="${data.qty || 1}" required></td>
            <td><input type="number" name="unit_price[]" class="form-control edit-unit-price" value="${data.unit_price || 0}" required></td>
            <td><input type="number" name="vat[]" class="form-control edit-vat" value="${data.vat || 0}" readonly></td>
            <td><input type="number" class="form-control edit-price-ex-vat" value="${data.price_ex_vat || 0}" readonly></td>
            <td><input type="number" class="form-control edit-total-incl-vat" value="${data.total_incl_vat || 0}" readonly></td>
            <td><button type="button" class="btn btn-danger btn-sm remove-item-btn">&times;</button></td>
        `;
                updateEditRowEvents(row);
                return row;
            }

            // Update events for a row
            function updateEditRowEvents(row) {
                const serviceTypeSelect = row.querySelector('.edit-service-type');
                const serviceCategorySelect = row.querySelector('.edit-service-category');
                const qtyInput = row.querySelector('.edit-qty');
                const unitPriceInput = row.querySelector('.edit-unit-price');
                const vatInput = row.querySelector('.edit-vat');
                const priceExVatInput = row.querySelector('.edit-price-ex-vat');
                const totalInclVatInput = row.querySelector('.edit-total-incl-vat');

                // 🧠 Fetch categories when service type changes
                serviceTypeSelect.addEventListener('change', () => {
                    const typeId = serviceTypeSelect.value;
                    fetchCategories(typeId, serviceCategorySelect);
                });

                // 💵 Fetch unit price when category changes
                serviceCategorySelect.addEventListener('change', () => {
                    const categoryId = serviceCategorySelect.value;
                    fetchUnitPrice(categoryId, unitPriceInput, vatInput, qtyInput, priceExVatInput, totalInclVatInput);
                });

                // ➕ Recalculate totals when qty or unit price changes
                [qtyInput, unitPriceInput].forEach(input => {
                    input.addEventListener('input', () => {
                        updateRowTotals(qtyInput, unitPriceInput, vatInput, priceExVatInput, totalInclVatInput);
                    });
                });

                // ❌ Remove row
                const removeButton = row.querySelector('.remove-item-btn');
                removeButton.addEventListener('click', () => {
                    row.remove();
                    updateEditTotal();
                });
            }


            document.getElementById('edit-add-item-btn').addEventListener('click', () => {
                const row = createEditRow(); // Blank row
                document.getElementById('edit-quote-items-body').appendChild(row);
            });
            function fetchCategories(typeId, categorySelect) {
                const formData = new URLSearchParams();
                formData.append("action", "fetch_categories");
                formData.append("service_type_id", typeId);

                axios.post('fetchData.php', formData)
                    .then(response => {
                        console.log("Categories fetched:", response.data);
                        const categories = Array.isArray(response.data) ? response.data : [];
                        categorySelect.innerHTML = '<option value="">Select Service Category</option>';
                        categories.forEach(category => {
                            const option = document.createElement('option');
                            option.value = category.id;
                            option.textContent = category.category_name;
                            categorySelect.appendChild(option);
                        });
                    })
                    .catch(err => console.error('Error fetching categories:', err));
            }

            // Fetch unit price and VAT for a service category
            function fetchUnitPrice(categoryId, unitPriceInput, vatInput, qtyInput, priceExVatInput, totalInclVatInput) {
                const formData = new URLSearchParams();
                formData.append("action", "fetch_unit_price");
                formData.append("service_category_id", categoryId);
                console.log("service_category_id", categoryId);
                formData.append("company_id", invoice_company_Id);
                console.log(invoice_company_Id);
                axios.post('fetchData.php', formData)
                    .then(response => {
                        console.log("Unit price fetched:", response.data);
                        console.log('company id after fetch :', invoice_company_Id);
                        const data = response.data;
                        unitPriceInput.value = data.unit_price || 0;
                        vatInput.value = data.company_vat || 0;
                        updateRowTotals(qtyInput, unitPriceInput, vatInput, priceExVatInput, totalInclVatInput);
                    })
                    .catch(err => console.error('Error fetching unit price:', err));
            }

            // Update totals for a row
            function updateRowTotals(qtyInput, unitPriceInput, vatInput, priceExVatInput, totalInclVatInput) {
                const qty = parseFloat(qtyInput.value) || 0;
                const unitPrice = parseFloat(unitPriceInput.value) || 0;
                const vat = parseFloat(vatInput.value) || 0;

                const priceExVat = qty * unitPrice;
                const totalInclVat = priceExVat + (priceExVat * vat / 100);

                priceExVatInput.value = priceExVat.toFixed(2);
                totalInclVatInput.value = totalInclVat.toFixed(2);

                updateEditTotal();
            }

            // Update the total for all rows
            function updateEditTotal() {
                let subTotal = 0;
                let totalVAT = 0;
                let discountPercentage = parseFloat(document.getElementById('edit-discount').value) || 0;

                document.querySelectorAll('#edit-quote-items-body tr').forEach(row => {
                    const qty = parseFloat(row.querySelector('.edit-qty').value) || 0;
                    const unitPrice = parseFloat(row.querySelector('.edit-unit-price').value) || 0;
                    const vat = parseFloat(row.querySelector('.edit-vat').value) || 0;

                    const priceExVat = qty * unitPrice;
                    const totalInclVat = priceExVat + (priceExVat * vat / 100);

                    subTotal += priceExVat;
                    totalVAT += priceExVat * vat / 100;
                });

                const discountAmount = (subTotal * discountPercentage) / 100;
                const afterDiscount = subTotal - discountAmount;

                document.getElementById('edit-sub-total').value = subTotal.toFixed(2);
                document.getElementById('edit-total-vat').value = totalVAT.toFixed(2);
                document.getElementById('edit-after-discount').value = afterDiscount.toFixed(2);
                document.getElementById('edit-grand-total').value = (afterDiscount + totalVAT).toFixed(2);
            }
            document.getElementById('edit-discount').addEventListener('input', updateEditTotal);

        });
    </script>
    <script>
        const deleteQuoteModal = document.getElementById('deleteQuoteModal');

        deleteQuoteModal.addEventListener('show.bs.modal', (event) => {
            const button = event.relatedTarget;
            const quoteId = button.getAttribute('data-id');
            document.getElementById('delete-id').value = quoteId;
        });
        function updateEditTotal() {
            let total = 0;
            document.querySelectorAll('#edit-quote-items-body .edit-total-incl-vat').forEach(input => {
                total += parseFloat(input.value) || 0;
            });
            document.getElementById('edit-quote-total').textContent = total.toFixed(2);
        }

    </script>
</body>

</html>