<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
// Database Connection
include_once '../config.php'; // Ensure this path is correct

// Fetch Clients
$clients = $conn->query("SELECT id, client_name FROM clients ORDER BY client_name ASC");

// Fetch Billing Items
$billingItems = [];
if (isset($_GET['client_id']) && is_numeric($_GET['client_id'])) {
    $client_id = (int) $_GET['client_id'];
    $user_condition = ($_SESSION['role'] == 'admin') ? '' : " AND created_by = " . intval($_SESSION['user_id']); // Assuming user_id is stored in session
    $billingItems = $conn->query("
        SELECT *
        FROM billing_items 
        WHERE client_id = $client_id AND is_deleted = 0 $user_condition
        ORDER BY start_date DESC
    ");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Billing Price Increase</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>

    <div class="my-5" style="width: 93%; margin: auto;">
        <h2 class="mb-4 text-center">Billing Price Increase</h2>

        <!-- Client Selection -->
        <form id="clientSelectionForm" class="border p-4 rounded shadow-sm bg-light mb-4">
            <div class="mb-3">
                <label class="form-label">Select Client</label>
                <select name="client_id" class="form-select" onchange="this.form.submit()">
                    <option value="">Select Client</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= $client['id'] ?>" <?= isset($_GET['client_id']) && $_GET['client_id'] == $client['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($client['client_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <!-- Billing Items Selection -->
        <?php if (!empty($billingItems) && $billingItems->num_rows > 0): ?>
            <form id="billingPriceForm" class="border p-4 rounded shadow-sm bg-light">
                <div class="mb-3">
                    <label class="form-label">Select Billing Items</label>
                    <select name="billing_item_ids[]" class="form-select select2" multiple required>
                        <?php while ($item = $billingItems->fetch_assoc()): ?>
                            <option value="<?= $item['id'] ?>">
                                <?= htmlspecialchars($item['description']) ?>
                                (Qty: <?= $item['qty'] ?>,
                                Unit Price: <?= number_format($item['unit_price'], 2) ?>,
                                Start: <?= date('M d, Y', strtotime($item['start_date'])) ?>,
                                End: <?= $item['end_date'] ? date('M d, Y', strtotime($item['end_date'])) : 'N/A' ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <small class="text-muted">Hold down the Ctrl (Windows) or Command (Mac) key to select multiple
                        items.</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Percentage Increase (%)</label>
                    <input type="number" name="percentage" class="form-control" placeholder="Enter percentage (e.g., 5 or 5.5)"
                        required min="0" step="0.01">
                </div>

                <div class="text-center">
                    <button type="submit" class="btn btn-success w-50">Apply Increase</button>
                </div>
            </form>
        <?php elseif (isset($_GET['client_id'])): ?>
            <div class="alert alert-warning">No billing items found for the selected client.</div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        $(document).ready(function () {
            // Initialize Select2
            $('.select2').select2({
                // theme: 'bootstrap5',
                placeholder: 'Select Billing Items',
            });
        });

        // Handle Billing Price Increase Submit
        document.getElementById('billingPriceForm')?.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'billing_price_increase');

            axios.post('backend.php', formData)
                .then(response => {
                    if (response.data.trim() === 'success') {
                        alert('✅ Prices Increased Successfully');
                        window.location.reload();
                    } else {
                        alert('❌ Failed to update prices. Server says: ' + response.data);
                    }
                })
                .catch(error => {
                    alert('❌ Server Error');
                    console.error(error);
                });
        });
    </script>

</body>

</html>