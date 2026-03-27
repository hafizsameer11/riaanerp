<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database Connection
$db_host = "localhost";
    $db_user = "clientzone_user";
    $db_pass = "S@utech2024!";
    $db_name = "clientzone";

include_once '../../../../config.php'; // Ensure this path is correct

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch Categories
$categories = $conn->query("SELECT id, category_name FROM billing_service_categories ORDER BY category_name ASC");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Bulk Price Increase</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>

<div class="my-5" style="width: 93%; margin: auto;">
    <h2 class="mb-4 text-center">Bulk Price Increase</h2>

    <form id="bulkPriceForm" class="border p-4 rounded shadow-sm bg-light">
        <div class="mb-3">
            <label class="form-label">Select Service Category (Optional)</label>
            <select name="service_category_id" class="form-select">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Percentage Increase (%)</label>
            <input type="number" name="percentage" class="form-control" placeholder="Enter percentage (e.g., 5)" required min="0">
        </div>

        <div class="text-center">
            <button type="submit" class="btn btn-success w-50">Apply Increase</button>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Handle Bulk Price Increase Submit
    document.getElementById('bulkPriceForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'bulk_increase');

        axios.post('backend.php', formData)
            .then(response => {
                if (response.data.trim() === 'success') {
                    alert('✅ Prices Increased Successfully');
                    window.location.href = '../index.php';
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
