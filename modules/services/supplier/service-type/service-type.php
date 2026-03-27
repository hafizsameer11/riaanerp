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

// Fetch all service types
$serviceTypes = $conn->query("SELECT * FROM billing_service_types ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Service Types</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>

<body>

    <div class="px-4 my-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center">
                <?php session_start(); ?>
                <?php include('../../../components/permissioncheck.php') ?>
                <h2 class="mb-0">Manage Service Types</h2>
            </div>
            <?php if (hasPermission('Manage Service Types', 'create')): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addServiceTypeModal">
                    Add Service Type
                </button>
            <?php endif; ?>
        </div>

        <table class="table table-hover table-bordered text-center">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Service Type Name</th>
                    <th>Notes</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($type = $serviceTypes->fetch_assoc()): ?>
                    <tr>
                        <td><?= $type['id'] ?></td>
                        <td><?= htmlspecialchars($type['service_type_name']) ?></td>
                        <td><?= htmlspecialchars($type['note']) ?></td>
                        <td>
                            <div class="btn-group" role="group" aria-label="Actions">
                                <?php if (hasPermission('Manage Service Types', 'update')): ?>
                                    <a href="javascript:void(0);" onclick="openEditModal(<?= $type['id'] ?>)" class="btn btn-sm"
                                        title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if (hasPermission('Manage Service Types', 'delete')): ?>
                                    <a href="javascript:void(0);" onclick="deleteServiceType(<?= $type['id'] ?>)"
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

    <!-- Add Service Type Modal -->
    <div class="modal fade" id="addServiceTypeModal" tabindex="-1" aria-labelledby="addServiceTypeModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <form id="addServiceTypeForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Service Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body row g-3">
                    <div class="col-12">
                        <label class="form-label">Service Type Name</label>
                        <input type="text" name="service_type_name" class="form-control" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="note" class="form-control"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">Save Service Type</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Service Type Modal -->
    <div class="modal fade" id="editServiceTypeModal" tabindex="-1" aria-labelledby="editServiceTypeModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <form id="editServiceTypeForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Service Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body row g-3">
                    <input type="hidden" name="id" id="edit-id">

                    <div class="col-12">
                        <label class="form-label">Service Type Name</label>
                        <input type="text" name="service_type_name" id="edit-service-type-name" class="form-control"
                            required>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="note" id="edit-note" class="form-control"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Update Service Type</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Handle Add Service Type
        document.getElementById('addServiceTypeForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'add');

            axios.post('service-type-backend.php', formData)
                .then(response => {
                    if (response.data.trim() === 'success') {
                        alert('Service Type Added Successfully ✅');
                        location.reload();
                    } else {
                        alert('Failed to add service type ❌');
                    }
                })
                .catch(error => {
                    alert('Server Error ❌');
                    console.error(error);
                });
        });

        // Handle Edit Service Type
        document.getElementById('editServiceTypeForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'edit');

            axios.post('service-type-backend.php', formData)
                .then(response => {
                    if (response.data.trim() === 'success') {
                        alert('Service Type Updated Successfully ✅');
                        location.reload();
                    } else {
                        alert('Failed to update service type ❌');
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

            axios.post('service-type-backend.php', formData)
                .then(response => {
                    const type = response.data;

                    document.getElementById('edit-id').value = type.id;
                    document.getElementById('edit-service-type-name').value = type.service_type_name;
                    document.getElementById('edit-note').value = type.notes;

                    var editModal = new bootstrap.Modal(document.getElementById('editServiceTypeModal'));
                    editModal.show();
                })
                .catch(error => {
                    alert('Failed to fetch service type ❌');
                    console.error(error);
                });
        }

        // Delete Service Type
        function deleteServiceType(id) {
            if (confirm('Are you sure you want to delete this service type?')) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);

                axios.post('service-type-backend.php', formData)
                    .then(response => {
                        if (response.data.trim() === 'success') {
                            alert('Service Type Deleted Successfully ✅');
                            location.reload();
                        } else {
                            alert('Failed to delete service type ❌');
                        }
                    })
                    .catch(error => {
                        alert('Server Error ❌');
                        console.error(error);
                    });
            }
        }
    </script>

</body>

</html>