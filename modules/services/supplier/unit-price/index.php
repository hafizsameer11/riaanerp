<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$db_host = "localhost";
$db_user = "clientzone_user";
$db_pass = "S@utech2024!";
$db_name = "clientzone";

include_once '../../../config.php'; // Ensure this path is correct

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch Service Categories
$categories = $conn->query("SELECT id, category_name FROM billing_service_categories ORDER BY category_name ASC");

// Fetch Unit Prices
$unitPrices = $conn->query("SELECT p.*, c.category_name FROM billing_category_prices p LEFT JOIN billing_service_categories c ON p.service_category_id = c.id ORDER BY p.created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Unit Prices</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>

<body>

    <div class="my-5" style="width: 93%; margin: auto;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center">
                <?php session_start(); ?>
                <?php include('../../../components/permissioncheck.php') ?>
                <h2 class="mb-0">Manage Unit Prices</h2>
            </div>

            <div class="d-flex gap-2">
                <?php if (hasPermission('Unit Prices', 'Bulk Price')): ?>
                    <a href="bulk-price/index.php" class="btn btn-success">
                        Bulk Price Increase
                    </a>
                <?php endif; ?>

                <?php if (hasPermission('Unit Prices', 'create')): ?>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUnitPriceModal">
                        Add Unit Price
                    </button>
                <?php endif; ?>
            </div>
        </div>


        <table class="table table-hover table-bordered text-center">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Service Category</th>
                    <th>Item Name</th>
                    <th>Unit Price</th>
                    <th>VAT Rate (%)</th>
                    <th>Currency</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($price = $unitPrices->fetch_assoc()): ?>
                    <tr>
                        <td><?= $price['id'] ?></td>
                        <td><?= htmlspecialchars($price['category_name']) ?></td>
                        <td><?= htmlspecialchars($price['item_name']) ?></td>
                        <td><?= number_format($price['unit_price'], 2) ?></td>
                        <td><?= number_format($price['vat_rate'], 2) ?></td>
                        <td><?= htmlspecialchars($price['currency']) ?></td>
                        <td>
                            <div class="btn-group" role="group">
                                <?php if (hasPermission('Unit Prices', 'update')): ?>
                                    <a href="javascript:void(0);" onclick="openEditModal(<?= $price['id'] ?>)"
                                        class="btn btn-sm" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if (hasPermission('Unit Prices', 'delete')): ?>
                                    <a href="javascript:void(0);" onclick="deleteUnitPrice(<?= $price['id'] ?>)"
                                        class="btn btn-sm text-danger" title="Delete">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Add Unit Price Modal -->
    <div class="modal fade" id="addUnitPriceModal" tabindex="-1" aria-labelledby="addUnitPriceModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <form id="addUnitPriceForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Unit Price</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-12">
                        <label class="form-label">Service Category</label>
                        <select name="service_category_id" class="form-select" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Item Name</label>
                        <input type="text" name="item_name" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Unit Price</label>
                        <input type="number" step="0.01" name="unit_price" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">VAT Rate (%)</label>
                        <input type="number" step="0.01" name="vat_rate" class="form-control" value="15" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Currency</label>
                        <input type="text" name="currency" class="form-control" value="USD" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Unit Price Modal -->
    <div class="modal fade" id="editUnitPriceModal" tabindex="-1" aria-labelledby="editUnitPriceModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <form id="editUnitPriceForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Unit Price</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body row g-3">
                    <input type="hidden" name="id" id="edit-id">

                    <div class="col-12">
                        <label class="form-label">Service Category</label>
                        <select name="service_category_id" id="edit-service-category-id" class="form-select" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Item Name</label>
                        <input type="text" name="item_name" id="edit-item-name" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Unit Price</label>
                        <input type="number" step="0.01" name="unit_price" id="edit-unit-price" class="form-control"
                            required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">VAT Rate (%)</label>
                        <input type="number" step="0.01" name="vat_rate" id="edit-vat-rate" class="form-control"
                            required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Currency</label>
                        <input type="text" name="currency" id="edit-currency" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // ➕ Handle Add Unit Price
        document.getElementById('addUnitPriceForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'add');

            axios.post('backend.php', formData)
                .then(response => {
                    if (response.data.trim() === 'success') {
                        alert('✅ Unit Price Added Successfully');
                        location.reload();
                    } else {
                        alert('❌ Failed to add unit price. Server says: ' + response.data);
                    }
                })
                .catch(error => {
                    alert('❌ Server Error');
                    console.error(error);
                });
        });

        // ✏️ Handle Edit Unit Price
        document.getElementById('editUnitPriceForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'edit');

            axios.post('backend.php', formData)
                .then(response => {
                    if (response.data.trim() === 'success') {
                        alert('✅ Unit Price Updated Successfully');
                        location.reload();
                    } else {
                        alert('❌ Failed to update unit price. Server says: ' + response.data);
                    }
                })
                .catch(error => {
                    alert('❌ Server Error');
                    console.error(error);
                });
        });

        // 📥 Open Edit Modal
        function openEditModal(id) {
            const formData = new FormData();
            formData.append('action', 'fetch');
            formData.append('id', id);

            axios.post('backend.php', formData)
                .then(response => {
                    const price = response.data;

                    document.getElementById('edit-id').value = price.id;
                    document.getElementById('edit-service-category-id').value = price.service_category_id;
                    document.getElementById('edit-item-name').value = price.item_name;
                    document.getElementById('edit-unit-price').value = price.unit_price;
                    document.getElementById('edit-vat-rate').value = price.vat_rate;
                    document.getElementById('edit-currency').value = price.currency;

                    var editModal = new bootstrap.Modal(document.getElementById('editUnitPriceModal'));
                    editModal.show();
                })
                .catch(error => {
                    alert('❌ Failed to fetch unit price details');
                    console.error(error);
                });
        }

        // ❌ Delete Unit Price
        function deleteUnitPrice(id) {
            if (confirm('Are you sure you want to delete this unit price?')) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);

                axios.post('backend.php', formData)
                    .then(response => {
                        if (response.data.trim() === 'success') {
                            alert('✅ Unit Price Deleted Successfully');
                            location.reload();
                        } else {
                            alert('❌ Failed to delete unit price. Server says: ' + response.data);
                        }
                    })
                    .catch(error => {
                        alert('❌ Server Error');
                        console.error(error);
                    });
            }
        }
    </script>

</body>

</html>