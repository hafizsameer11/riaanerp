<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$db_host = "localhost";
// $db_user = "root";
// $db_pass = "";
$db_user = "clientzone_user";
$db_pass = "S@utech2024!";
$db_name = "clientzone";

include_once '../../../config.php'; // Ensure this path is correct
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch suppliers
$suppliers = $conn->query("SELECT * FROM billing_suppliers ORDER BY supplier_name ASC");
$search_suppliers = $conn->query("SELECT id, supplier_name FROM billing_suppliers")
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Suppliers</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>

<body>

    <div class=" my-5" style="width: 93%; margin: auto;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center">
                <?php session_start(); ?>
                <?php include('../../../components/permissioncheck.php') ?>
                <h2 class="mb-0">Manage Suppliers</h2>
            </div>
            <?php if (hasPermission('Manage Suppliers', 'create')): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                    Add Supplier
                </button>
            <?php endif; ?>
        </div>

        <!-- start search option  -->
        <div class="card shadow-sm p-4 mb-4 " style=" margin: auto;">
            <form id="filterForm" class="row g-3 align-items-end">

                <div class="col-md-3">
                    <label class="form-label">Suppliers</label>
                    <!-- <select name="supplier_id" class="form-select" id="supplierSearch">
                        <option value="">All Suppliers</option>
                        <?php foreach ($search_suppliers as $supplier): ?>
                            <option value="<?= $supplier['id'] ?>"><?= htmlspecialchars($supplier['supplier_name']) ?></option>
                        <?php endforeach; ?>
                    </select> -->
                    <input type="text" name="supplierSearch" id="supplierSearch"  class="form-control" placeholder="Search Supplier...">
                </div>
                <div class="col-12 text-end">
                    <button type="submit" id="applyFilter" class="btn btn-primary">Apply Filter</button>
                    <button type="button" onclick="resetFilters()" class="btn btn-secondary">Reset</button>
                </div>
            </form>
        </div>
        <!-- end search option  -->

        <table class="table table-hover table-bordered text-center">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Supplier Name</th>
                    <th>Contact Details</th>
                    <th>Email</th>
                    <th>Sales Person</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="supplierTableBody">
                <?php while ($supplier = $suppliers->fetch_assoc()): ?>
                    <tr>
                        <td><?= $supplier['id'] ?></td>
                        <td><?= htmlspecialchars($supplier['supplier_name']) ?></td>
                        <td><?= htmlspecialchars($supplier['contact_details']) ?></td>
                        <td><?= htmlspecialchars($supplier['email']) ?></td>
                        <td><?= htmlspecialchars($supplier['salesperson']) ?></td>
                        <td class="text-center">
                            <div class="btn-group" role="group" aria-label="Actions">
                                <?php if (hasPermission('Manage Suppliers', 'update')): ?>
                                    <a href="javascript:void(0);" onclick="openEditModal(<?= $supplier['id'] ?>)"
                                        class="btn btn-sm" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if (hasPermission('Manage Suppliers', 'delete')): ?>
                                    <a href="javascript:void(0);" onclick="deleteSupplier(<?= $supplier['id'] ?>)"
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

    <!-- Add Supplier Modal -->
    <div class="modal fade" id="addSupplierModal" tabindex="-1" aria-labelledby="addSupplierModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <form id="addSupplierForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"> Add Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body row g-3">
                    <div class="col-12">
                        <label class="form-label">Supplier Name</label>
                        <input type="text" name="supplier_name" class="form-control" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Contact Details</label>
                        <textarea name="contact_details" class="form-control"></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Sales Person</label>
                        <input type="text" name="sales_person" class="form-control">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Accounts Department Contact</label>
                        <input type="text" name="accounts_contact" class="form-control">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Accounts Department Email Address</label>
                        <input type="email" name="accounts_email" class="form-control">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">Save Supplier</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Supplier Modal -->
    <div class="modal fade" id="editSupplierModal" tabindex="-1" aria-labelledby="editSupplierModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <form id="editSupplierForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"> Edit Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body row g-3">
                    <input type="hidden" name="id" id="edit-id">

                    <div class="col-12">
                        <label class="form-label">Supplier Name</label>
                        <input type="text" name="supplier_name" id="edit-supplier-name" class="form-control" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Contact Details</label>
                        <textarea name="contact_details" id="edit-contact-details" class="form-control"></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" id="edit-email" class="form-control">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Sales Person</label>
                        <input type="text" name="sales_person" id="edit-sales-person" class="form-control">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Accounts Department Contact</label>
                        <input type="text" name="accounts_contact" id="edit-accounts-contact" class="form-control">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Accounts Department Email Address</label>
                        <input type="email" name="accounts_email" id="edit-accounts-email" class="form-control">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Update Supplier</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Handle Add Supplier
        document.getElementById('addSupplierForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'add');

            axios.post('supplier-backend.php', formData)
                .then(response => {
                    if (response.data.trim() === 'success') {
                        alert('Supplier Added Successfully ✅');
                        location.reload();
                    } else {
                        alert('Failed to add supplier ❌');
                    }
                })
                .catch(error => {
                    alert('Server Error ❌');
                    console.error(error);
                });
        });

        // Handle Edit Supplier
        document.getElementById('editSupplierForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'edit');

            axios.post('supplier-backend.php', formData)
                .then(response => {
                    if (response.data.trim() === 'success') {
                        alert('Supplier Updated Successfully ✅');
                        location.reload();
                    } else {
                        alert('Failed to update supplier ❌');
                    }
                })
                .catch(error => {
                    alert('Server Error ❌');
                    console.error(error);
                });
        });

        // Open Edit Modal
        function openEditModal(id) {
            const formData = new FormData();
            formData.append('action', 'fetch');
            formData.append('id', id);

            axios.post('supplier-backend.php', formData)
                .then(response => {
                    const supplier = response.data;

                    document.getElementById('edit-id').value = supplier.id;
                    document.getElementById('edit-supplier-name').value = supplier.supplier_name;
                    document.getElementById('edit-contact-details').value = supplier.contact_details;
                    document.getElementById('edit-email').value = supplier.email;
                    document.getElementById('edit-sales-person').value = supplier.salesperson;
                    document.getElementById('edit-accounts-contact').value = supplier.accounts_contact;
                    document.getElementById('edit-accounts-email').value = supplier.accounts_email;

                    var editModal = new bootstrap.Modal(document.getElementById('editSupplierModal'));
                    editModal.show();
                })
                .catch(error => {
                    alert('Failed to fetch supplier details ❌');
                    console.error(error);
                });
        }

        // Delete Supplier
        function deleteSupplier(id) {
            if (confirm('Are you sure you want to delete this supplier?')) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);

                axios.post('supplier-backend.php', formData)
                    .then(response => {
                        if (response.data.trim() === 'success') {
                            alert('Supplier Deleted Successfully ✅');
                            location.reload();
                        } else {
                            alert('Failed to delete supplier ❌');
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

            axios.post('supplier-filter.php', formData)
                .then(response => {
                    const suppliers = response.data;
                    const tbody = document.getElementById('supplierTableBody');
                    tbody.innerHTML = '';

                    suppliers.forEach(supplier => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                    <td>${supplier.id}</td>
                    <td>${supplier.supplier_name}</td>
                    <td>${supplier.contact_details}</td>
                    <td>${supplier.email}</td>
                    <td>${supplier.salesperson}</td>
                    <td class="text-center">
                        <div class="btn-group" role="group" aria-label="Actions">
                            ${hasPermissionUpdate ? `
                                <a href="javascript:void(0);" onclick="openEditModal(${supplier.id})" class="btn btn-sm" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>` : ''}
                            ${hasPermissionDelete ? `
                                <a href="javascript:void(0);" onclick="deleteSupplier(${supplier.id})" class="btn btn-sm text-danger" title="Delete">
                                    <i class="fas fa-trash-alt"></i>
                                </a>` : ''}
                        </div>
                    </td>
                `;
                        tbody.appendChild(tr);
                    });
                })
                .catch(error => {
                    alert('Failed to filter suppliers ❌');
                    console.error(error);
                });
        });


        const hasPermissionUpdate = <?= hasPermission('Manage Suppliers', 'update') ? 'true' : 'false' ?>;
        const hasPermissionDelete = <?= hasPermission('Manage Suppliers', 'delete') ? 'true' : 'false' ?>;

        // Reset Filters
        function resetFilters() {
            document.getElementById('filterForm').reset();
            document.getElementById('filterForm').dispatchEvent(new Event('button'));
        }
    </script>

</body>

</html>