<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database Connection
$db_host = "localhost";
$db_user = "clientzone_user";
$db_pass = "S@utech2024!";
$db_name = "clientzone";

include_once '../../config.php'; // Ensure this path is correct

// Function to calculate Reporting Cores
function calculateReportingCores($cpuOrQty)
{
    return ($cpuOrQty % 2 === 0) ? $cpuOrQty : $cpuOrQty + 1;
}


// Fetch Hosting Assets where SPLA = 'Yes'
$hostingResult = $conn->query("
    SELECT id, client_id, client_name, server_name, os, cpu
    FROM hosting_assets
    WHERE spla = 'Yes'
    ORDER BY client_name ASC
");

// Fetch Manual Added Licenses
$licenseResult = $conn->query("
    SELECT id, client, ms_products, quantity, notes
    FROM spla_licenses
    WHERE type = 'license' AND is_deleted = 0
    ORDER BY client ASC
");



// Fetch clients for Add License Modal
$clients = $conn->query("SELECT id, client_name FROM clients ORDER BY client_name ASC");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>SPLA Licensing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

</head>

<body>

    <div class="" style="width: 93%; margin: auto;">
        <div class="d-flex justify-content-between align-items-center mb-4 mt-4">
            <div class="d-flex align-items-center">
                <?php session_start(); ?>
                <?php include('../components/permissioncheck.php') ?>
                <h3 class="text-dark">SPLA Licensing</h3>
            </div>
            <div class="d-flex align-items-center  gap-2">
                <a href="export_pdf.php" class="btn btn-danger">Export PDF</a>
            <a href="export_csv.php" class="btn btn-primary">Export CSV</a>
                <?php if (hasPermission('spla', 'create')): ?>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addLicenseModal">Add New
                        License
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="" style="width: 100%; margin: auto;">
            <table class="table table-hover table-bordered table-striped align-middle shadow-sm rounded bg-white">
                <thead class="table-light text-center">
                    <tr>
                        <th>#</th>
                        <th>Source</th>
                        <th>Client Name</th>
                        <th>Client ID</th>
                        <th>Server Name / License Product</th>
                        <th>OS</th>
                        <th>CPU / Qty</th>
                        <th>Reporting Cores</th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $i = 1;
                    // Hosting Records
                    while ($row = $hostingResult->fetch_assoc()):
                        $reportingCores = calculateReportingCores((int) $row['cpu']);
                        ?>
                        <tr>
                            <td class="text-center"><?= $i++ ?></td>
                            <td class="text-center"><span class="badge bg-info px-3 py-2">Hosting</span></td>
                            <td><?= htmlspecialchars($row['client_name']) ?></td>
                            <td class="text-center"><?= htmlspecialchars($row['client_id']) ?></td>
                            <td><?= htmlspecialchars($row['server_name']) ?></td>
                            <td><?= htmlspecialchars($row['os']) ?></td>
                            <td class="text-center"><?= htmlspecialchars($row['cpu']) ?></td>
                            <td class="text-center">
                                <strong><?= calculateReportingCores((int) ($row['cpu'] ?? $row['quantity'])) ?></strong>
                            </td>
                            <td class="text-center">-</td>
                            <td class="text-center">
                                <div class="btn-group" role="group" aria-label="Actions">
                                    <?php if (hasPermission('spla', 'update')): ?>
                                        <a href="javascript:void(0)" onclick="openDeleteModal(<?= $row['id'] ?>, 'hosting')"
                                            class="btn btn-sm text-danger" title="Delete Hosting">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                    <?php
                    // Manual Licenses
                    while ($row = $licenseResult->fetch_assoc()):
                        $reportingCores = calculateReportingCores((int) $row['quantity']);
                        ?>
                        <tr>
                            <td class="text-center"><?= $i++ ?></td>
                            <td class="text-center"><span class="badge bg-success px-3 py-2">Manual</span></td>
                            <td><?= htmlspecialchars($row['client']) ?></td>
                            <td class="text-center">-</td>
                            <td><?= htmlspecialchars($row['ms_products']) ?></td>
                            <td class="text-center">-</td>
                            <td class="text-center"><?= htmlspecialchars($row['quantity']) ?></td>
                            <td class="text-center"><strong><?= $reportingCores ?></strong></td>
                            <td><?= htmlspecialchars($row['notes']) ?></td>
                            <td class="text-center">
                                <div class="btn-group" role="group" aria-label="Actions">
                                    <?php if (hasPermission('spla', 'update')): ?>
                                        <a href="javascript:void(0)"
                                            onclick="openEditModal(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['ms_products'])) ?>', <?= $row['quantity'] ?>, '<?= htmlspecialchars(addslashes($row['notes'])) ?>')"
                                            class="btn btn-sm" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (hasPermission('spla', 'delete')): ?>
                                        <a href="javascript:void(0)" onclick="openDeleteModal(<?= $row['id'] ?>, 'manual')"
                                            class="btn btn-sm text-danger" title="Delete License">
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

    </div>

    <!-- Add New License Modal -->
    <div class="modal fade" id="addLicenseModal" tabindex="-1" aria-labelledby="addLicenseModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <form id="addLicenseForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addLicenseModalLabel">➕ Add New Manual License</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select Client</label>
                        <select name="client_name" class="form-select" required>
                            <option value="">Select Client</option>
                            <?php while ($row = $clients->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($row['client_name']) ?>">
                                    <?= htmlspecialchars($row['client_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Microsoft Product</label>
                        <input type="text" name="ms_products" class="form-control"
                            placeholder="e.g. Remote Desktop, Exchange" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" name="quantity" class="form-control" value="1" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="note" class="form-control" rows="3" placeholder="Optional..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">💾 Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editLicenseModal" tabindex="-1" aria-labelledby="editLicenseModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <form id="editLicenseForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editLicenseModalLabel">✏️ Edit License</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit-id">
                    <div class="mb-3">
                        <label class="form-label">Microsoft Product</label>
                        <input type="text" class="form-control" name="ms_products" id="edit-ms_products" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" class="form-control" name="quantity" id="edit-quantity" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="note" id="edit-note" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">💾 Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteLicenseModal" tabindex="-1" aria-labelledby="deleteLicenseModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <form id="deleteLicenseForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteLicenseModalLabel">🗑️ Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this record?
                    <input type="hidden" name="id" id="delete-id">
                    <input type="hidden" name="source" id="delete-source">
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-danger">Yes, Delete</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Open Edit Modal
        function openEditModal(id, ms_products, quantity, note) {
            document.getElementById('edit-id').value = id;
            document.getElementById('edit-ms_products').value = ms_products;
            document.getElementById('edit-quantity').value = quantity;
            document.getElementById('edit-note').value = note;
            var editModal = new bootstrap.Modal(document.getElementById('editLicenseModal'));
            editModal.show();
        }

        // Open Delete Modal
        function openDeleteModal(id, source) {
            document.getElementById('delete-id').value = id;
            document.getElementById('delete-source').value = source;
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteLicenseModal'));
            deleteModal.show();
        }

        // Handle Add New License
        document.getElementById('addLicenseForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('add_manual_license', 1);
            axios.post('backend.php', formData)
                .then(response => {
                    if (response.data === 'success') location.reload();
                    else alert('Failed to add.');
                });
        });

        // Handle Edit License
        document.getElementById('editLicenseForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('edit_manual_license', 1);
            axios.post('backend.php', formData)
                .then(response => {
                    if (response.data === 'success') location.reload();
                    else alert('Update failed.');
                });
        });

        // Handle Delete License
        document.getElementById('deleteLicenseForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('delete_manual_license', 1);
            axios.post('backend.php', formData)
                .then(response => {
                    if (response.data === 'success') location.reload();
                    else alert('Delete failed.');
                });
        });
    </script>

</body>

</html>