<?php
$db_host = "localhost";
$db_user = "clientzone_user";
$db_pass = "S@utech2024!";
$db_name = "clientzone";

include_once '../../config.php'; // Ensure this path is correct
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
session_start();
require_once '../../vendor/autoload.php';
require_once '../../helper/email_helper.php';
include('../components/permissioncheck.php');
$alert = '';
$reseller_id = $_GET['reseller_id'] ?? '';
$client_filter = $_GET['client_filter'] ?? '';
$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';

$resellers = $conn->query("SELECT r.id,r.name, c.client_name FROM resellers r LEFT JOIN clients c ON r.client_id = c.id");

$filter = '';
if (!hasPermission('reseller commission', 'View all')) {
    // $filter = "WHERE b.created_by = $_SESSION[user_id]" ;
    $loginUserId = $_SESSION['user_id'];
    $getLoginUser = $conn->query("SELECT * FROM resellers WHERE register_id = $loginUserId LIMIT 1");
    $reseller = $getLoginUser ? $getLoginUser->fetch_assoc() : 'not found';
    $reseller_id = $reseller['id'] ?? '';
    $getClient = $conn->query("SELECT client_id FROM resellers WHERE id = $reseller_id");
    $client = $getClient->fetch_assoc();
    $client_id = $client['client_id'];
    $client_ids = json_decode($client['client_id'], true);
    if (is_array($client_ids)) {
        $client_ids_escaped = array_map('intval', $client_ids);
        $client_ids_str = implode(',', $client_ids_escaped);
        $filter .= " AND b.client_id IN ($client_ids_str)";
    } else {
        $client_id_escaped = intval($client['client_id']);
        $filter .= " AND b.client_id = $client_id_escaped";
    }
}
// Get clients assigned to selected reseller
$assigned_clients = [];
if ($reseller_id) {
    $getClient = $conn->query("SELECT client_id FROM resellers WHERE id = $reseller_id");
    $client = $getClient->fetch_assoc();
    $client_id = $client['client_id'];
    $client_ids = json_decode($client['client_id'], true);
    if (is_array($client_ids)) {
        $client_ids_escaped = array_map('intval', $client_ids);
        $client_ids_str = implode(',', $client_ids_escaped);
        $filter .= " AND b.client_id IN ($client_ids_str)";
        
        // Get list of assigned clients for dropdown
        $clients_query = $conn->query("SELECT id, client_name FROM clients WHERE id IN ($client_ids_str) ORDER BY client_name ASC");
        while ($c = $clients_query->fetch_assoc()) {
            $assigned_clients[] = $c;
        }
    } else {
        $client_id_escaped = intval($client['client_id']);
        $filter .= " AND b.client_id = $client_id_escaped";
        
        // Get single assigned client for dropdown
        $clients_query = $conn->query("SELECT id, client_name FROM clients WHERE id = $client_id_escaped");
        if ($c = $clients_query->fetch_assoc()) {
            $assigned_clients[] = $c;
        }
    }
}

// Additional filter for specific client if selected
if ($client_filter && $reseller_id) {
    $client_filter_escaped = intval($client_filter);
    $filter .= " AND b.client_id = $client_filter_escaped";
}

if (!empty($_GET['start']) || !empty($_GET['end'])) {
    if (!empty($_GET['start']) && !empty($_GET['end'])) {
        $startDate = $conn->real_escape_string($_GET['start']);
        $endDate = $conn->real_escape_string($_GET['end']);
        $filter .= " AND (b.start_date <= '$endDate' AND b.end_date >= '$startDate')";;
    } elseif (!empty($_GET['start'])) {
        $startDate = $conn->real_escape_string($_GET['start']);
        $filter .= " AND b.end_date >= '" . $startDate . "'";
    } elseif (!empty($_GET['end'])) {
        $endDate = $conn->real_escape_string($_GET['end']);
        $filter .= " AND b.start_date <= '" . $endDate . "'";
    }
}
if ($filter) {
    $filter = 'WHERE ' . substr($filter, 4) . ' AND b.is_deleted = 0'; // Remove the leading ' AND ' and add is_deleted check
} else {
    $filter = 'WHERE b.is_deleted = 0'; 
}

// Query to show all individual billing items grouped by client
$sql = "SELECT 
            b.*,
            c.client_name,
            c.currency,
            c.currency_symbol,
            (CAST(b.qty AS DECIMAL(10,2)) * CAST(b.unit_price AS DECIMAL(10,2))) as item_total_ex_vat,
            ((CAST(b.qty AS DECIMAL(10,2)) * CAST(b.unit_price AS DECIMAL(10,2))) * (COALESCE(CAST(b.vat_rate AS DECIMAL(10,2)), 0) / 100)) as item_vat,
            ((CAST(b.qty AS DECIMAL(10,2)) * CAST(b.unit_price AS DECIMAL(10,2))) + ((CAST(b.qty AS DECIMAL(10,2)) * CAST(b.unit_price AS DECIMAL(10,2))) * (COALESCE(CAST(b.vat_rate AS DECIMAL(10,2)), 0) / 100))) as item_total_incl_vat
        FROM billing_items b 
        LEFT JOIN clients c ON b.client_id = c.id
        $filter 
        ORDER BY c.client_name ASC, b.id ASC";

$records = $conn->query($sql);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['export_csv'])) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="reseller_commission.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Client', 'Description', 'Qty', 'Unit Price', 'Total Ex VAT', 'VAT %', 'VAT Amount', 'Total Incl VAT']);
        
        // Re-query for export
        $export_records = $conn->query($sql);
        $currentClient = '';
        $clientTotalExVat = 0;
        $clientTotalVat = 0;
        $clientTotalInclVat = 0;
        
        foreach ($export_records as $row) {
            // If new client, add subtotal row for previous client
            if ($currentClient !== '' && $currentClient !== $row['client_name']) {
                fputcsv($out, ['', '--- Client Subtotal ---', '', '', number_format($clientTotalExVat, 2), '', number_format($clientTotalVat, 2), number_format($clientTotalInclVat, 2)]);
                fputcsv($out, []); // Empty row
                $clientTotalExVat = 0;
                $clientTotalVat = 0;
                $clientTotalInclVat = 0;
            }
            
            $currentClient = $row['client_name'];
            $itemExVat = $row['item_total_ex_vat'];
            $itemVat = $row['item_vat'];
            $itemInclVat = $row['item_total_incl_vat'];
            
            $clientTotalExVat += $itemExVat;
            $clientTotalVat += $itemVat;
            $clientTotalInclVat += $itemInclVat;
            
            fputcsv($out, [
                $row['client_name'],
                $row['description'] ?? '',
                $row['qty'] ?? 0,
                number_format($row['unit_price'] ?? 0, 2),
                number_format($itemExVat, 2),
                $row['vat_rate'] ?? 0,
                number_format($itemVat, 2),
                number_format($itemInclVat, 2)
            ]);
        }
        
        // Add final client subtotal
        if ($currentClient !== '') {
            fputcsv($out, ['', '--- Client Subtotal ---', '', '', number_format($clientTotalExVat, 2), '', number_format($clientTotalVat, 2), number_format($clientTotalInclVat, 2)]);
        }
        
        fclose($out);
        exit;
    }

    if (isset($_POST['send_email'])) {
        ob_start();
        $csv = fopen('php://output', 'w');
        fputcsv($csv, ['Client', 'Description', 'Qty', 'Unit Price', 'Total Ex VAT', 'VAT %', 'VAT Amount', 'Total Incl VAT']);
        
        // Re-query for email
        $email_records = $conn->query($sql);
        $currentClient = '';
        $clientTotalExVat = 0;
        $clientTotalVat = 0;
        $clientTotalInclVat = 0;
        
        foreach ($email_records as $row) {
            // If new client, add subtotal row for previous client
            if ($currentClient !== '' && $currentClient !== $row['client_name']) {
                fputcsv($csv, ['', '--- Client Subtotal ---', '', '', number_format($clientTotalExVat, 2), '', number_format($clientTotalVat, 2), number_format($clientTotalInclVat, 2)]);
                fputcsv($csv, []); // Empty row
                $clientTotalExVat = 0;
                $clientTotalVat = 0;
                $clientTotalInclVat = 0;
            }
            
            $currentClient = $row['client_name'];
            $itemExVat = $row['item_total_ex_vat'];
            $itemVat = $row['item_vat'];
            $itemInclVat = $row['item_total_incl_vat'];
            
            $clientTotalExVat += $itemExVat;
            $clientTotalVat += $itemVat;
            $clientTotalInclVat += $itemInclVat;
            
            fputcsv($csv, [
                $row['client_name'],
                $row['description'] ?? '',
                $row['qty'] ?? 0,
                number_format($row['unit_price'] ?? 0, 2),
                number_format($itemExVat, 2),
                $row['vat_rate'] ?? 0,
                number_format($itemVat, 2),
                number_format($itemInclVat, 2)
            ]);
        }
        
        // Add final client subtotal
        if ($currentClient !== '') {
            fputcsv($csv, ['', '--- Client Subtotal ---', '', '', number_format($clientTotalExVat, 2), '', number_format($clientTotalVat, 2), number_format($clientTotalInclVat, 2)]);
        }
        
        fclose($csv);
        $csvContent = ob_get_clean();

        $sendStatus = sendEmailWithAttachment(
            $_POST['recipient_email'],
            $_POST['recipient_name'],
            $_POST['sender_name'],
            $csvContent
        );

        if ($sendStatus === true) {
            $alert = '📧 Report emailed successfully!';
        } else {
            $alert = '❌ Email failed: ' . $sendStatus;
        }
    }

}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Reseller Commission Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="px-5 mt-5">
        <?php if ($alert): ?>
            <div class="alert alert-success"><?= htmlspecialchars($alert) ?></div>
        <?php endif;
        session_abort();
        ?>

        <div class="d-flex align-items-center">
            <?php session_start(); ?>
            <h2>Reseller Commission Report</h2>
        </div>

        <form method="GET" class="row g-3 mb-4">
            <?php if (hasPermission('reseller commission', 'View all')): ?>
            <div class="col-md-3">
                <label>Reseller</label>
                <select name="reseller_id" class="form-select" id="resellerSelect">
                    <option value="">All Resellers</option>
                    <?php while ($r = $resellers->fetch_assoc()): ?>
                        <option value="<?= $r['id'] ?>" <?= $r['id'] == $reseller_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($r['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <?php if ($reseller_id && !empty($assigned_clients)): ?>
            <div class="col-md-3">
                <label>Client (Assigned to Reseller)</label>
                <select name="client_filter" class="form-select">
                    <option value="">All Clients</option>
                    <?php foreach ($assigned_clients as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $c['id'] == $client_filter ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['client_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="<?= (!hasPermission('reseller commission', 'View all')) ? 'col-md-4' : 'col-md-3'  ?>">
                <label>Start Date</label>
                <input type="date" name="start" class="form-control" value="<?= htmlspecialchars($start) ?>">
            </div>
            <div class="<?= (!hasPermission('reseller commission', 'View all')) ? 'col-md-4' : 'col-md-3'  ?>">
                <label>End Date</label>
                <input type="date" name="end" class="form-control" value="<?= htmlspecialchars($end) ?>">
            </div>
            <div class="<?= (!hasPermission('reseller commission', 'View all')) ? 'col-md-4' : 'col-md-3'  ?> d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Apply Filter</button>
            </div>
        </form>

        <?php if ($records && $records->num_rows > 0): ?>
            <form method="POST" class="mb-4">
                <input type="hidden" name="reseller_id" value="<?= htmlspecialchars($reseller_id) ?>">
                <input type="hidden" name="client_filter" value="<?= htmlspecialchars($client_filter) ?>">
                <input type="hidden" name="start" value="<?= htmlspecialchars($start) ?>">
                <input type="hidden" name="end" value="<?= htmlspecialchars($end) ?>">

                <div class="row g-2 mb-3">
                    <?php if (hasPermission('reseller commission', 'Send Email')): ?>

                        <div class="col-md-3">
                            <label>Sender Name</label>
                            <input type="text" name="sender_name" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label>Recipient Name</label>
                            <input type="text" name="recipient_name" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label>Recipient Email</label>
                            <input type="email" name="recipient_email" class="form-control">
                        </div>
                    <?php endif; ?>
                    <div class="col-md-3 d-flex align-items-end">
                        <?php if (hasPermission('reseller commission', 'Send Email')): ?>
                            <div class="form-check me-2">
                                <input class="form-check-input" type="checkbox" name="send_email" id="send_email">
                                <label class="form-check-label" for="send_email">Email Report</label>
                            </div>
                        <?php endif; ?>
                        <button type="submit" name="export_csv" class="btn btn-success">Export CSV</button>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>Client</th>
                            <th>Description</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Total Ex VAT</th>
                            <th>VAT %</th>
                            <th>VAT Amount</th>
                            <th>Total Incl VAT</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $grandTotalExVat = 0;
                        $grandTotalVat = 0;
                        $grandTotalInclVat = 0;
                        $currentClient = '';
                        $clientTotalExVat = 0;
                        $clientTotalVat = 0;
                        $clientTotalInclVat = 0;
                        $clientItemCount = 0;

                        foreach ($records as $row):
                            $itemExVat = $row['item_total_ex_vat'] ?? 0;
                            $itemVat = $row['item_vat'] ?? 0;
                            $itemInclVat = $row['item_total_incl_vat'] ?? 0;
                            $vatRate = $row['vat_rate'] ?? 0;
                            
                            // Check if this is a new client
                            if ($currentClient !== '' && $currentClient !== $row['client_name']) {
                                // Display subtotal for previous client
                                ?>
                                <tr class="table-info">
                                    <td colspan="4" class="text-end fw-bold"><?= htmlspecialchars($currentClient) ?> Subtotal (<?= $clientItemCount ?> items):</td>
                                    <td class="fw-bold"><?= number_format($clientTotalExVat, 2) ?></td>
                                    <td></td>
                                    <td class="fw-bold"><?= number_format($clientTotalVat, 2) ?></td>
                                    <td class="fw-bold"><?= number_format($clientTotalInclVat, 2) ?></td>
                                </tr>
                                <tr><td colspan="8" style="border: none; height: 10px;"></td></tr>
                                <?php
                                // Reset client totals
                                $clientTotalExVat = 0;
                                $clientTotalVat = 0;
                                $clientTotalInclVat = 0;
                                $clientItemCount = 0;
                            }
                            
                            $currentClient = $row['client_name'];
                            $clientTotalExVat += $itemExVat;
                            $clientTotalVat += $itemVat;
                            $clientTotalInclVat += $itemInclVat;
                            $clientItemCount++;
                            
                            $grandTotalExVat += $itemExVat;
                            $grandTotalVat += $itemVat;
                            $grandTotalInclVat += $itemInclVat;
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($row['client_name']) ?></td>
                                <td><?= htmlspecialchars($row['description'] ?? '') ?></td>
                                <td class="text-center"><?= htmlspecialchars($row['qty'] ?? 0) ?></td>
                                <td class="text-end"><?= ($row['currency_symbol'] ?? '') ?> <?= number_format($row['unit_price'] ?? 0, 2) ?></td>
                                <td class="text-end"><?= ($row['currency_symbol'] ?? '') ?> <?= number_format($itemExVat, 2) ?></td>
                                <td class="text-center"><?= number_format($vatRate, 2) ?>%</td>
                                <td class="text-end"><?= ($row['currency_symbol'] ?? '') ?> <?= number_format($itemVat, 2) ?></td>
                                <td class="text-end"><?= ($row['currency_symbol'] ?? '') ?> <?= number_format($itemInclVat, 2) ?></td>
                            </tr>
                        <?php 
                        endforeach; 
                        
                        // Display final client subtotal
                        if ($currentClient !== '') {
                            ?>
                            <tr class="table-info">
                                <td colspan="4" class="text-end fw-bold"><?= htmlspecialchars($currentClient) ?> Subtotal (<?= $clientItemCount ?> items):</td>
                                <td class="fw-bold"><?= number_format($clientTotalExVat, 2) ?></td>
                                <td></td>
                                <td class="fw-bold"><?= number_format($clientTotalVat, 2) ?></td>
                                <td class="fw-bold"><?= number_format($clientTotalInclVat, 2) ?></td>
                            </tr>
                            <?php
                        }
                        ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="7" style="border:none;">
                                <div style="width: 100%;">
                                    <!-- Left spacer to push totals right -->
                                    <div style="width: 38%; display: inline-block;"></div>
                                    <!-- Totals Section -->
                                    <div
                                        style="width: 60%; display: inline-block; vertical-align: top; border: 1px solid #ddd; padding: 10px; font-size: 10pt;">
                                        <div style="text-align: left; font-weight: bold; margin-bottom: 10px;">
                                            Grand Total:
                                        </div>

                                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                            <span><strong>Grand Total Ex VAT</strong></span>
                                            <span><?= number_format($grandTotalExVat, 2) ?></span>
                                        </div>

                                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                            <span><strong>Grand Total VAT</strong></span>
                                            <span><?= number_format($grandTotalVat, 2) ?></span>
                                        </div>

                                        <div
                                            style="display: flex; justify-content: space-between; border-top: 1px solid #000; padding-top: 8px;">
                                            <span><strong>Grand Total Incl VAT</strong></span>
                                            <span><strong><?= number_format($grandTotalInclVat, 2) ?></strong></span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            <?php else: ?>
                <p class="text-muted">No billing items found.</p>
            <?php endif; ?>

        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>