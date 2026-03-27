<?php
session_start();
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

// Check if user is a reseller and get their assigned clients
$loginUserId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['role'] ?? null;
$resellerClientIds = [];
$isReseller = false;
$isAdmin = false;

// Check if user is admin (not a reseller)
if ($userRole && strtolower($userRole) === 'admin') {
    $isAdmin = true;
}

if ($loginUserId) {
    $getLoginUser = $conn->query("SELECT * FROM resellers WHERE register_id = $loginUserId LIMIT 1");
    if ($getLoginUser && $getLoginUser->num_rows > 0) {
        $reseller = $getLoginUser->fetch_assoc();
        $isReseller = true;
        
        // Decode JSON array of client IDs
        $resellerClientIds = json_decode($reseller['client_id'], true);
        
        if (!is_array($resellerClientIds)) {
            $resellerClientIds = [];
        }
    }
}

// IMPORTANT: Admin users should see ALL items regardless of who created them
// Only resellers should have restrictions

// Fetch clients based on reseller permissions
if ($isReseller && !empty($resellerClientIds)) {
    // Only show assigned clients for resellers
    $clientIdsStr = implode(',', array_map('intval', $resellerClientIds));
    $clients = $conn->query("SELECT id, client_name FROM clients WHERE id IN ($clientIdsStr) ORDER BY client_name");
} else {
    // Show all clients for admins
    $clients = $conn->query("SELECT id, client_name FROM clients ORDER BY client_name");
}

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
// Handle processed filter - based on processed_period matching the selected period
// REQUIREMENT: When viewing a period, show ALL items in that period
// The display logic will show correct processed status (Yes/No) based on the period
if (isset($_GET['processed']) && $_GET['processed'] !== '') {
    $processedFilter = (int) $_GET['processed'];
    
    // If a date range is selected, check if processed_period matches the selected period
    if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
        $startDate = $conn->real_escape_string($_GET['start_date']);
        $endDate = $conn->real_escape_string($_GET['end_date']);
        
        // Check if processed_period columns exist
        $checkPeriodStart = $conn->query("SHOW COLUMNS FROM billing_items LIKE 'processed_period_start'");
        $hasPeriodColumns = $checkPeriodStart->num_rows > 0;
        
        if ($processedFilter == 1) {
            // Show items that are processed FOR the selected period
            if ($hasPeriodColumns) {
                // Items processed for this specific period
                $where[] = "(b.processed = 1 
                            AND b.processed_period_start = '$startDate' 
                            AND b.processed_period_end = '$endDate')";
            } else {
                // Fallback for legacy data: check if item's dates fall within selected period
                $where[] = "(b.processed = 1 
                            AND b.start_date >= '$startDate' 
                            AND b.end_date <= '$endDate')";
            }
        } else {
            // Show items that are NOT processed for the selected period
            if ($hasPeriodColumns) {
                // Items that are either:
                // - Never processed (processed = 0)
                // - Processed but for a different period (processed_period doesn't match)
                // - Processed but period columns are NULL (legacy data)
                $where[] = "(b.processed = 0 
                            OR b.processed_period_start IS NULL 
                            OR b.processed_period_end IS NULL
                            OR b.processed_period_start != '$startDate' 
                            OR b.processed_period_end != '$endDate')";
            } else {
                // Fallback for legacy data
                $where[] = "(b.processed = 0 
                            OR b.start_date < '$startDate' 
                            OR b.end_date > '$endDate')";
            }
        }
    } else {
        // No date range selected - use simple processed filter
        $where[] = "b.processed = " . $processedFilter;
    }
}

if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
    // Get the selected period start and end dates
    $selectedPeriodStartDate = $conn->real_escape_string($_GET['start_date']);
    $selectedPeriodEndDate = $conn->real_escape_string($_GET['end_date']);
    
    // Normalize dates to Y-m-d format
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedPeriodStartDate)) {
        $selectedPeriodStartFormatted = $selectedPeriodStartDate;
    } else {
        $selectedPeriodStartFormatted = date('Y-m-d', strtotime(str_replace('/', '-', $selectedPeriodStartDate)));
    }
    
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedPeriodEndDate)) {
        $selectedPeriodEndFormatted = $selectedPeriodEndDate;
    } else {
        $selectedPeriodEndFormatted = date('Y-m-d', strtotime(str_replace('/', '-', $selectedPeriodEndDate)));
    }
    
    // Filter items that overlap with the selected date range
    // An item belongs to the period if its billing period overlaps with the selected period
    // This checks if the item's period (start_date to end_date) overlaps with the selected period
    $where[] = "(b.start_date <= '$selectedPeriodEndFormatted' AND (b.end_date IS NULL OR b.end_date >= '$selectedPeriodStartFormatted'))";
}
// if (!empty($_GET['status'])) {
//     $where[] = "b.status = '" . $conn->real_escape_string($_GET['status']) . "'";
// }
// if (!empty($_GET['serial_number'])) {
//     $where[] = "b.serial_number = '" . $conn->real_escape_string($_GET['serial_number']) . "'";
// }

// Filter billing items for resellers - only show their assigned clients
// IMPORTANT: Admin users should see ALL items regardless of who created/loaded them
// Do NOT filter by created_by for admin users - they should see everything
if ($isReseller && !empty($resellerClientIds)) {
    $clientIdsStr = implode(',', array_map('intval', $resellerClientIds));
    $where[] = "b.client_id IN ($clientIdsStr)";
}

// Ensure admin users see all items - no filter on created_by
// Only apply is_deleted filter
$where[] = 'b.is_deleted = 0';
$filterSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$billingRecords = $conn->query("
    SELECT DISTINCT b.*, c.client_name, sc.category_name
    FROM billing_items b
    LEFT JOIN clients c ON c.id = b.client_id
    LEFT JOIN billing_service_categories sc ON sc.id = b.service_category_id
    $filterSql
    ORDER BY b.id DESC
");
$currenceyQueries = $conn->query("
    SELECT DISTINCT b.currency
    FROM billing_items b
    WHERE b.is_deleted = 0
    ORDER BY b.currency DESC
");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['billing_ids'])) {
    $billingIds = implode(',', array_map('intval', $_POST['billing_ids'])); // Sanitize IDs
    
    // When processing billing items for a specific period:
    // 1. Set processed = 1
    // 2. Set processed_date = today()
    // 3. Store the SELECTED date range (from filters) as processed_period_start and processed_period_end
    //    This tracks which period the item was processed FOR, not the item's own billing period
    $processedDate = date('Y-m-d'); // Today's date
    
    // Get the selected date range from POST (the period the user is viewing/processing)
    // REQUIREMENT: Date range must be selected to process items - this ensures items are processed for the correct period
    $selectedPeriodStart = !empty($_POST['start_date']) ? $conn->real_escape_string($_POST['start_date']) : null;
    $selectedPeriodEnd = !empty($_POST['end_date']) ? $conn->real_escape_string($_POST['end_date']) : null;
    
    // Ensure processed_period_start and processed_period_end columns exist
    // Check and add columns if they don't exist
    $checkPeriodStart = $conn->query("SHOW COLUMNS FROM billing_items LIKE 'processed_period_start'");
    $checkPeriodEnd = $conn->query("SHOW COLUMNS FROM billing_items LIKE 'processed_period_end'");
    $checkProcessedDate = $conn->query("SHOW COLUMNS FROM billing_items LIKE 'processed_date'");
    
    if ($checkProcessedDate->num_rows == 0) {
        $conn->query("ALTER TABLE `billing_items` ADD COLUMN `processed_date` date DEFAULT NULL AFTER `processed`");
    }
    if ($checkPeriodStart->num_rows == 0) {
        $conn->query("ALTER TABLE `billing_items` ADD COLUMN `processed_period_start` date DEFAULT NULL AFTER `processed_date`");
    }
    if ($checkPeriodEnd->num_rows == 0) {
        $conn->query("ALTER TABLE `billing_items` ADD COLUMN `processed_period_end` date DEFAULT NULL AFTER `processed_period_start`");
    }
    
    // Update processed flag, processed_date, and the period it was processed for
    // The processed_period_start/end stores which VIEWING period the item was processed in
    // This allows items to be processed separately for different viewing periods
    if ($selectedPeriodStart && $selectedPeriodEnd) {
        // Date range is selected - use that as the processed period
        // Normalize dates to ensure Y-m-d format
        $processedPeriodStartFormatted = date('Y-m-d', strtotime($selectedPeriodStart));
        $processedPeriodEndFormatted = date('Y-m-d', strtotime($selectedPeriodEnd));
        
        $sql = "UPDATE billing_items 
                SET processed = 1, 
                    processed_date = '$processedDate',
                    processed_period_start = '$processedPeriodStartFormatted',
                    processed_period_end = '$processedPeriodEndFormatted'
                WHERE id IN ($billingIds)";
    } else {
        // No date range selected - cannot process items without knowing which period they're for
        // Set error message and redirect back
        $_SESSION['error_message'] = "Please select a date range (Processing Period) before processing items. This ensures items are marked as processed for the correct period.";
        $params = [];
        foreach ($_POST as $k => $v) {
            if ($k !== 'billing_ids' && !empty($v)) {
                $params[$k] = $v;
            }
        }
        $query = http_build_query($params);
        header('Location: billingReport.php' . ($query ? '?' . $query : ''));
        exit;
    }

    if ($conn->query($sql)) {
        // ✅ Preserve all filters from POST when redirecting (filters are passed as hidden inputs)
        $params = [
            'client_id'    => $_POST['client_id']    ?? '',
            'invoice_type' => $_POST['invoice_type'] ?? '',
            'currency'     => $_POST['currency']     ?? '',
            'frequency'    => $_POST['frequency']    ?? '',
            'processed'    => $_POST['processed']    ?? '',
            'start_date'   => $_POST['start_date']   ?? '',
            'end_date'     => $_POST['end_date']     ?? '',
        ];

        // Remove empty ones to keep URL clean
        $params = array_filter($params, fn($v) => $v !== '');

        // Build query string (e.g., ?client_id=2&currency=USD&frequency=monthly)
        $query = http_build_query($params);

        // Redirect back to same page with filters intact
        header('Location: billingReport.php' . ($query ? '?' . $query : ''));
        exit;
    } else {
        echo "Error updating records: " . $conn->error;
    }
}
// else {
//     if (empty($_POST['billing_ids'])) {
//         echo "No items selected. Please select at least one item.";
//     }

?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Billing Report</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>

<body>

    <div class="p-5">
        <div>
            <!-- Export -->
            <div class="mt-5 mb-5 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center ">
                    <?php include('../components/permissioncheck.php') ?>
                    <h3 class=" d-flex align-items-center">
                        <i class="bi bi-people-fill me-2 text-secondary" style="font-size: 1.5rem;"></i>
                        <span class="fw-semibold text-dark">Billing Report</span>
                    </h3>
                </div>
                <!-- Left-aligned Title -->
                <form action="export-billing.php" id="processForm" method="POST" class="text-end d-flex gap-2 col-md-3 ">
                    <?php foreach ($_GET as $k => $v): ?>
                        <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
                    <?php endforeach; ?>

                    <!-- User Logins Button -->
                    <!-- Export to Excel Button -->
                    <button type="submit" class="btn btn-success p-3 h5 py-2 w-100">Export to Excel</button>
                </form>
            </div>
        </div>
        <!-- Filters -->
        <form class="card shadow-sm p-4 mb-4" method="GET">
            <div class="row g-3 align-items-end">
                        
                <div class="col-md-3">
                    <label class="form-label">Client</label>
                    <select name="client_id" class="form-select">
                        <option value="">All Clients</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= $client['id'] ?>" <?= isset($_GET['client_id']) && $_GET['client_id'] == $client['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($client['client_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Invoice Type</label>
                    <select name="invoice_type" class="form-select">
                        <option value="">All</option>
                        <option value="debit" <?= ($_GET['invoice_type'] ?? '') === 'debit' ? 'selected' : '' ?>>Debit
                        </option>
                        <option value="invoice" <?= ($_GET['invoice_type'] ?? '') === 'invoice' ? 'selected' : '' ?>>
                            Invoice</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Currency</label>
                    <select name="currency" class="form-select">
                        <option value="">All</option>
                        <?php
                        $currencies = [];
                        while ($row = $currenceyQueries->fetch_assoc()):
                            if (!in_array($row['currency'], $currencies)):
                                $currencies[] = $row['currency'];
                        ?>
                                <option value="<?= $row['currency'] ?>" <?= ($_GET['currency'] ?? '') === $row['currency'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($row['currency'] ?? '') ?>
                                </option>
                        <?php
                            endif;
                        endwhile;
                        ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Frequency</label>
                    <select name="frequency" class="form-select">
                        <option value="">All</option>
                        <option value="once_off" <?= ($_GET['frequency'] ?? '') === 'once_off' ? 'selected' : '' ?>>
                            Once Off</option>
                        <option value="monthly" <?= ($_GET['frequency'] ?? '') === 'monthly' ? 'selected' : '' ?>>
                            Monthly</option>
                        <option value="annually" <?= ($_GET['frequency'] ?? '') === 'annually' ? 'selected' : '' ?>>
                            Annually</option>
                        <option value="finance" <?= ($_GET['frequency'] ?? '') === 'finance' ? 'selected' : '' ?>>
                            Finance</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Processed</label>
                    <select name="processed" class="form-select">
                        <option value="">All</option>
                        <option value="1" <?= (isset($_GET['processed']) && $_GET['processed'] === '1') ? 'selected' : '' ?>>Yes
                        </option>
                        <option value="0" <?= (isset($_GET['processed']) && $_GET['processed'] === '0') ? 'selected' : '' ?>>No
                        </option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Date Range</label>
                    <input type="date" name="start_date" class="form-control"
                        value="<?= htmlspecialchars($_GET['start_date'] ?? '') ?>" 
                        placeholder="Start Date" required>
                    <input type="date" name="end_date" class="form-control mt-2"
                        value="<?= htmlspecialchars($_GET['end_date'] ?? '') ?>"
                        placeholder="End Date" required>
                    <!-- <small class="text-muted d-block mt-1">Select which month to process items for</small> -->
                </div>

                <!-- <div class="col-md-3">
                    <label class="form-label">Agreement Status</label>
                    <select name="status" class="form-select">
                        <option value="">All</option>
                        <option value="Active" <?= (isset($_GET['status']) && $_GET['status'] === 'Active') ? 'selected' : '' ?>>Active
                        </option>
                        <option value="Cancelled" <?= (isset($_GET['status']) && $_GET['status'] === 'Cancelled') ? 'selected' : '' ?>>Cancelled
                        </option>
                        <option value="Expired" <?= (isset($_GET['status']) && $_GET['status'] === 'Expired') ? 'selected' : '' ?>>Expired
                        </option>
                        <option value="Suspended" <?= (isset($_GET['status']) && $_GET['status'] === 'Suspended') ? 'selected' : '' ?>>Suspended
                        </option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Serial Number</label>
                    <input type="text" name="serial_number" class="form-control"
                        value="<?= htmlspecialchars($_GET['serial_number'] ?? '') ?>">
                </div> -->

                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <!-- <a href="reports-billing.php" class="btn btn-secondary">Reset</a> -->
                    <button type="button" onclick="resetFilters()" class="btn btn-secondary">Reset</button>
                </div>
            </div>
        </form>

        <div>
            <!-- Export -->
            <div id="alert">
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($_SESSION['error_message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>
            </div>
            <!-- Table -->
            <form method="POST" action="billingReport.php" id="billing-form">
                <?php foreach ($_GET as $k => $v): ?>
                    <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
                <?php endforeach; ?>

                <div>
                    <!-- Submit Button -->
                    <!-- Table -->
                    <div style="max-height: 600px; overflow-y: auto;" class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light text-center">
                                <tr>
                                    <th><input type="checkbox" id="select-all"></th> <!-- Select All Checkbox -->
                                    <th>#</th>
                                    <th>Client</th>
                                    <th>Service Category</th>
                                    <th>Description</th>
                                    <th>Qty</th>
                                    <th>Price</th>
                                    <th>VAT</th>
                                    <th>Total</th>
                                    <th>Invoice Type</th>
                                    <th>Currency</th>
                                    <th>Frequency</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Processed</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1;
                                $billingRecords->data_seek(0);
                                while ($row = $billingRecords->fetch_assoc()):
                                    $subtotal = $row['qty'] * $row['unit_price'];
                                    $vat = ($row['vat_rate'] / 100) * $subtotal;
                                    $total = $subtotal + $vat;
                                ?>
                                    <tr>
                                        <td class="text-center">
                                            <input type="checkbox" name="billing_ids[]" value="<?= $row['id'] ?>" 
                                                   data-total="<?= $total ?>" data-currency="<?= htmlspecialchars($row['currency'] ?? '') ?>" 
                                                   data-currency-symbol="<?= htmlspecialchars($row['currency_symbol'] ?? '') ?>">
                                            <!-- Checkbox -->
                                        </td>
                                        <td class="text-center"><?= $i++ ?></td>
                                        <td><?= htmlspecialchars($row['client_name'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['category_name'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['description'] ?? '') ?></td>
                                        <td class="text-center"><?= $row['qty'] ?></td>
                                        <td class="text-end" style="white-space: nowrap;">
                                            <?= $row['currency_symbol'] ?> <?= number_format($row['unit_price'], 2) ?>
                                        </td>
                                        <td class="text-end" style="white-space: nowrap;">
                                            <?= $row['currency_symbol'] ?> <?= number_format($vat, 2) ?>
                                        </td>
                                        <td class="text-end total-cell" style="white-space: nowrap;" data-total="<?= $total ?>">
                                            <?= $row['currency_symbol'] ?> <?= number_format($total, 2) ?>
                                        </td>
                                        <td class="text-center"><?= $row['invoice_type'] ?></td>
                                        <td class="text-center"><?= $row['currency'] ?></td>
                                        <td class="text-center"><?= ucfirst($row['frequency']) ?></td>
                                        <td class="text-center text-nowrap">
                                            <!-- show start_date in fomrta form -->
                                            <?= date('d-m-Y', strtotime($row['start_date'])) ?>
                                        </td>
                                        <td class="text-center text-nowrap">
                                            <!-- show start_date in fomrta form -->
                                            <?= date('d-m-Y', strtotime($row['end_date'])) ?>
                                        </td>

                                        <td class="text-center">
                                            <?php
                                            // Display logic: Item is processed ONLY IF:
                                            // 1. processed = 1 in database
                                            // 2. The processed_period_start and processed_period_end match the CURRENT selected period (if period tracking exists)
                                            // OR the item's own dates fall within the selected period (for legacy items)
                                            // This ensures items only show as processed when viewing the SAME period they were processed for
                                            $isProcessed = false;
                                            
                                            if ($row['processed'] == 1) {
                                                if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
                                                    // Get the selected period (what the user is currently viewing)
                                                    $selectedStart = $_GET['start_date'];
                                                    $selectedEnd = $_GET['end_date'];
                                                    
                                                    // Get the period this item was processed for
                                                    $processedPeriodStart = $row['processed_period_start'] ?? null;
                                                    $processedPeriodEnd = $row['processed_period_end'] ?? null;
                                                    
                                                    // Item is processed ONLY if:
                                                    // 1. It has processed_period_start and processed_period_end set (explicit period tracking)
                                                    // 2. The processed period EXACTLY matches the currently selected period
                                                    if ($processedPeriodStart && $processedPeriodEnd) {
                                                        // Items with period tracking: Only show as processed if the period matches exactly
                                                        // Normalize dates to Y-m-d format for comparison (handle any date format)
                                                        $processedStart = !empty($processedPeriodStart) ? date('Y-m-d', strtotime($processedPeriodStart)) : null;
                                                        $processedEnd = !empty($processedPeriodEnd) ? date('Y-m-d', strtotime($processedPeriodEnd)) : null;
                                                        $selectedStartNorm = !empty($selectedStart) ? date('Y-m-d', strtotime($selectedStart)) : null;
                                                        $selectedEndNorm = !empty($selectedEnd) ? date('Y-m-d', strtotime($selectedEnd)) : null;
                                                        
                                                        // Compare dates - item is processed if the stored period exactly matches selected period
                                                        if ($processedStart && $processedEnd && $selectedStartNorm && $selectedEndNorm) {
                                                            $isProcessed = ($processedStart == $selectedStartNorm && 
                                                                          $processedEnd == $selectedEndNorm);
                                                        } else {
                                                            $isProcessed = false;
                                                        }
                                                    } else {
                                                        // Legacy items without period tracking: Check if item's own dates fall within selected period
                                                        // If processed = 1 but no period tracking, show as processed if item dates match selected period
                                                        // This handles items processed before period tracking was added
                                                        $itemStartDate = $row['start_date'] ?? null;
                                                        $itemEndDate = $row['end_date'] ?? null;
                                                        
                                                        if ($itemStartDate && $itemEndDate) {
                                                            // Normalize dates for comparison
                                                            $itemStart = date('Y-m-d', strtotime($itemStartDate));
                                                            $itemEnd = date('Y-m-d', strtotime($itemEndDate));
                                                            $selectedStartNorm = date('Y-m-d', strtotime($selectedStart));
                                                            $selectedEndNorm = date('Y-m-d', strtotime($selectedEnd));
                                                            
                                                            // Show as processed if item's dates fall within or match the selected period
                                                            $isProcessed = ($itemStart >= $selectedStartNorm && 
                                                                          $itemEnd <= $selectedEndNorm);
                                                        } else {
                                                            // If item has no dates, don't show as processed (can't determine period)
                                                            $isProcessed = false;
                                                        }
                                                    }
                                                } else {
                                                    // No date range selected - show global processed status from database
                                                    // This is for backward compatibility when viewing without date filters
                                                    $isProcessed = true;
                                                }
                                            }
                                            echo $isProcessed ? 'Yes' : 'No';
                                            ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-end my-3">
                        <button type="button" id="calculate-total" class="btn btn-primary">Calculate Total</button>
                        <span class="ms-3 fw-bold">Total Amount: <span id="total-amount">0.00</span></span>
                    </div>
                    <?php if (hasPermission('billing report', 'Mark as procced')): ?>
                        <div class="text-end my-3">
                            <button type="submit" class="btn btn-success">Mark as Processed</button>
                            <?php if (!empty($_GET['start_date']) && !empty($_GET['end_date'])): ?>
                                <small class="d-block text-muted mt-2">
                                    Items will be marked as processed for: <?= date('d M Y', strtotime($_GET['start_date'])) . ' - ' . date('d M Y', strtotime($_GET['end_date'])) ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </form>

        </div>

    </div>

    <script>
        // Select all checkboxes
        document.getElementById('select-all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name="billing_ids[]"]');
            checkboxes.forEach(checkbox => checkbox.checked = this.checked);
        });

        // Attach submit handler to billing form only
        document.getElementById('billing-form').addEventListener('submit', function(e) {
            const checkboxes = document.querySelectorAll('input[name="billing_ids[]"]:checked');
            if (checkboxes.length === 0) {
                e.preventDefault();
                // Show Bootstrap alert dynamically
                const alertBox = document.createElement('div');
                alertBox.className = 'alert alert-danger mt-3';
                alertBox.textContent = 'Please select at least one item to process.';
                document.getElementById('alert').innerHTML = ''; // Clear previous alerts
                document.getElementById('alert').appendChild(alertBox);
            }
        });

        // Calculate total amount
        document.getElementById('calculate-total').addEventListener('click', function() {
            const checkboxes = document.querySelectorAll('input[name="billing_ids[]"]:checked');
            let totalsByCurrency = {}; // Group totals by currency
            let grandTotal = 0;

            checkboxes.forEach(checkbox => {
                // Use data attributes for accurate calculation
                const totalValueStr = checkbox.getAttribute('data-total');
                const totalValue = parseFloat(totalValueStr);
                const currency = checkbox.getAttribute('data-currency') || 'Unknown';
                const currencySymbol = checkbox.getAttribute('data-currency-symbol') || '';

                // Include both positive and negative values (negative = discounts to be deducted)
                if (!isNaN(totalValue)) {
                    // Group by currency
                    if (!totalsByCurrency[currency]) {
                        totalsByCurrency[currency] = {
                            symbol: currencySymbol,
                            total: 0
                        };
                    }
                    // Add the value (negative values will subtract from total)
                    totalsByCurrency[currency].total += totalValue;
                    grandTotal += totalValue;
                }
            });

            // Display totals grouped by currency
            let displayText = '';
            if (Object.keys(totalsByCurrency).length === 0) {
                displayText = '0.00';
            } else if (Object.keys(totalsByCurrency).length === 1) {
                // Single currency - show with symbol
                const currency = Object.keys(totalsByCurrency)[0];
                const data = totalsByCurrency[currency];
                displayText = (data.symbol ? data.symbol + ' ' : '') + data.total.toFixed(2);
            } else {
                // Multiple currencies - show breakdown
                displayText = '<div style="text-align: left; display: inline-block;">';
                for (const [currency, data] of Object.entries(totalsByCurrency)) {
                    displayText += (data.symbol ? data.symbol + ' ' : '') + data.total.toFixed(2) + ' (' + currency + ')<br>';
                }
                displayText += '<strong>Grand Total: ' + grandTotal.toFixed(2) + '</strong></div>';
            }

            document.getElementById('total-amount').innerHTML = displayText;
        });
        // Reset Filters - Clear all filters and reload page
        function resetFilters() {
            // Redirect to billingReport.php without any query parameters
            window.location.href = 'billingReport.php';
        }

      
    </script>

</body>

</html>