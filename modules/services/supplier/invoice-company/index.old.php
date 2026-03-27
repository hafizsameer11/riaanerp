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
$companies = $conn->query("SELECT * FROM billing_invoice_companies ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Invoicing Companies</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>

<body>
    <div class="my-5" style="width: 93%; margin: auto;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center">
                <?php session_start(); ?>
                <?php include('../../../components/permissioncheck.php') ?>
                <h3 class="mb-0">Manage Invoicing Companies</h3>
            </div>
            <?php if (hasPermission('Manage Invoice Companies', 'create')): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCompanyModal">Add
                    Company</button>
            <?php endif; ?>
        </div>

        <table class="table table-hover table-bordered text-center">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Company Name</th>
                    <th>Address</th>
                    <th>VAT Number</th>
                    <th>Registration Number</th>
                    <th>Contact Details</th>
                    <th>VAT Rate (%)</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $companies->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td><?= htmlspecialchars($row['company_name']) ?></td>
                        <td><?= htmlspecialchars($row['address']) ?></td>
                        <td><?= htmlspecialchars($row['vat_number']) ?></td>
                        <td><?= htmlspecialchars($row['registration_number']) ?></td>
                        <td><?= htmlspecialchars($row['contact_details']) ?></td>
                        <td><?= htmlspecialchars($row['vat_rate']) ?></td>
                        <td class="text-center">
                            <div class="btn-group" role="group" aria-label="Actions">
                                <?php if (hasPermission('Manage Invoice Companies', 'update')): ?>
                                    <a href="javascript:void(0);" onclick="openEditModal(<?= $row['id'] ?>)" class="btn btn-sm"
                                        title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if (hasPermission('Manage Invoice Companies', 'delete')): ?>
                                    <a href="javascript:void(0);" onclick="deleteCompany(<?= $row['id'] ?>)"
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

    <!-- Add Modal -->
    <div class="modal fade" id="addCompanyModal" tabindex="-1">
        <div class="modal-dialog">
            <form id="addCompanyForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Company</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-12">
                        <label class="form-label">Company Name</label>
                        <input type="text" name="company_name" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">VAT Number</label>
                        <input type="text" name="vat_number" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Registration Number</label>
                        <input type="text" name="registration_number" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Contact Details</label>
                        <input type="text" name="contact_details" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">VAT Rate (%)</label>
                        <input type="number" name="vat_rate" class="form-control" step="0.01" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editCompanyModal" tabindex="-1">
        <div class="modal-dialog">
            <form id="editCompanyForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Company</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <input type="hidden" name="id" id="edit-id">
                    <div class="col-12">
                        <label class="form-label">Company Name</label>
                        <input type="text" name="company_name" id="edit-company-name" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <textarea name="address" id="edit-address" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">VAT Number</label>
                        <input type="text" name="vat_number" id="edit-vat-number" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Registration Number</label>
                        <input type="text" name="registration_number" id="edit-registration-number" class="form-control"
                            required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Contact Details</label>
                        <input type="text" name="contact_details" id="edit-contact-details" class="form-control"
                            required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">VAT Rate (%)</label>
                        <input type="number" name="vat_rate" id="edit-vat-rate" class="form-control" step="0.01"
                            required>
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
        // ADD
        document.getElementById('addCompanyForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'add');
            axios.post('backend.php', formData).then(res => {
                if (res.data.trim() === 'success') {
                    alert('✅ Company Added');
                    location.reload();
                } else {
                    alert('❌ Add failed');
                }
            });
        });

        // EDIT
        document.getElementById('editCompanyForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'edit');
            axios.post('backend.php', formData).then(res => {
                if (res.data.trim() === 'success') {
                    alert('✅ Company Updated');
                    location.reload();
                } else {
                    alert('❌ Update failed');
                }
            });
        });

        // FETCH FOR EDIT
        function openEditModal(id) {
            const formData = new FormData();
            formData.append('action', 'fetch');
            formData.append('id', id);
            axios.post('backend.php', formData).then(res => {
                const c = res.data;
                document.getElementById('edit-id').value = c.id;
                document.getElementById('edit-company-name').value = c.company_name;
                document.getElementById('edit-address').value = c.address;
                document.getElementById('edit-vat-number').value = c.vat_number;
                document.getElementById('edit-registration-number').value = c.registration_number;
                document.getElementById('edit-contact-details').value = c.contact_details;
                document.getElementById('edit-vat-rate').value = c.vat_rate;
                new bootstrap.Modal(document.getElementById('editCompanyModal')).show();
            });
        }

        // DELETE
        function deleteCompany(id) {
            if (confirm('Delete this company?')) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);
                axios.post('backend.php', formData).then(res => {
                    if (res.data.trim() === 'success') {
                        alert('✅ Deleted');
                        location.reload();
                    } else {
                        alert('❌ Failed');
                    }
                });
            }
        }
    </script>
</body>

</html>