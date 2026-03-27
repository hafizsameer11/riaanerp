<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database Connection
$db_host = "localhost";
$db_user = "clientzone_user";
$db_pass = "S@utech2024!";
$db_name = "clientzone";

include_once '../../config.php'; // Ensure this path is correct

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch Clients
$clients = $conn->query("SELECT id, client_name FROM clients ORDER BY client_name ASC");

// Fetch Device Types and Locations
$deviceTypes = $conn->query("SELECT id, type FROM client_support_items ORDER BY type ASC");
$locations = $conn->query("SELECT id, location FROM support_data ORDER BY location ASC");

// Fetch Devices
$devices = [];
$result = $conn->query("
    SELECT d.*, c.client_name 
    FROM client_devices d 
    LEFT JOIN clients c ON d.client_id = c.id
    WHERE d.is_deleted = 0
    ORDER BY d.created_at DESC
");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $devices[] = $row;
    }
}

// View Single Device Logic
$viewing = false;
$view_data = null;

if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $view_id = intval($_GET['view']);
    $viewQuery = $conn->query("SELECT d.*, c.client_name FROM client_devices d 
                                LEFT JOIN clients c ON d.client_id = c.id
                                WHERE d.id = $view_id AND d.is_deleted = 0 LIMIT 1");

    if ($viewQuery && $viewQuery->num_rows > 0) {
        $viewing = true;
        $view_data = $viewQuery->fetch_assoc();
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Devices List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>

<body>

    <div class=" my-5" style="width: 93%; margin: auto;">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center">
                <?php session_start(); ?>
                <?php include('../components/permissioncheck.php') ?>
                <h3 class="fw-bold">Devices List</h3>
            </div>
            <?php if (hasPermission('devices', 'create')): ?>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addDeviceModal">
                    Add New Device
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter -->
    <div class="card shadow-sm p-4 mb-4 mt-4" style="width: 93%; margin: auto;">
        <form id="filterForm" class="row g-3 align-items-end">

            <div class="col-md-4">
                <label class="form-label">Client</label>
                <select name="client_id" class="form-select">
                    <option value="">All Clients</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= $client['id'] ?>"><?= htmlspecialchars($client['client_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Device Type</label>
                <select name="device_type" class="form-select">
                    <option value="">All Types</option>
                    <?php foreach ($deviceTypes as $type): ?>
                        <option value="<?= htmlspecialchars($type['type']) ?>"><?= htmlspecialchars($type['type']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Location</label>
                <select name="location" class="form-select">
                    <option value="">All Locations</option>
                    <?php foreach ($locations as $location): ?>
                        <option value="<?= htmlspecialchars($location['location']) ?>">
                            <?= htmlspecialchars($location['location']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 text-end">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <button type="button" onclick="resetFilters()" class="btn btn-secondary">Reset</button>
            </div>

        </form>
    </div>




    <div class=" my-5" style="width: 93%; margin: auto;">
        <table class="table table-hover table-bordered table-striped align-middle shadow-sm bg-white">
            <thead class="table-light text-center">
                <tr>
                    <th>#</th>
                    <th>Client</th>
                    <th>Device Name</th>
                    <th>Device Type</th>
                    <th>Device IP</th>
                    <th>Location</th>
                    <th>Username</th>
                    <th>Password</th>
                    <!-- <th>Enable Username</th> -->
                    <!-- <th>Enable Password</th> -->
                    <th>Access Port</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1;
                foreach ($devices as $device): ?>
                    <tr class="text-center">
                        <td><?= $i++ ?></td>
                        <td><?= htmlspecialchars($device['client_name']) ?></td>
                        <td><?= htmlspecialchars($device['device_name']) ?></td>
                        <td><?= htmlspecialchars($device['device_type']) ?></td>
                        <td><?= htmlspecialchars($device['device_ip']) ?></td>
                        <td><?= htmlspecialchars($device['location']) ?></td>
                        <td><?= htmlspecialchars($device['username']) ?></td>
                        <td>
                            <span class="password-mask">*****</span>
                            <button class="btn btn-sm btn-link toggle-password"
                                data-password="<?= htmlspecialchars($device['password']) ?>">👁️</button>
                        </td>
                        <!-- <td> -->
                        </ /?=htmlspecialchars($device['enable_username']) ?>
                        </td>
                        <!-- <td> -->
                        <!-- <span class="password-mask">*****</span>
                            <button class="btn btn-sm btn-link toggle-password" 
                            data-password="<?= htmlspecialchars($device['enable_password']) ?>"
                            >👁️</button>
                        </td> -->
                        <td><?= htmlspecialchars($device['access_port']) ?></td>
                        <td class="text-center">
                            <div class="btn-group" role="group" aria-label="Actions">
                                <a href="?view=<?= $device['id'] ?>" class="btn btn-sm" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if (hasPermission('devices', 'update')): ?>
                                    <a href="javascript:void(0);" onclick="openEditModal(<?= $device['id'] ?>)"
                                        class="btn btn-sm" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if (hasPermission('devices', 'delete')): ?>
                                    <a href="javascript:void(0);" onclick="openDeleteModal(<?= $device['id'] ?>)"
                                        class="btn btn-sm text-danger" title="Delete">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>

                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    </div>

    <!-- Add Device Modal -->
    <div class="modal fade" id="addDeviceModal" tabindex="-1" aria-labelledby="addDeviceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form id="addDeviceForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">➕ Add New Device</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body row g-3">

                    <div class="col-md-6">
                        <label class="form-label">Client</label>
                        <select name="client_id" class="form-select" required>
                            <option value="">Select Client</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= $client['id'] ?>"><?= htmlspecialchars($client['client_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Device Name</label>
                        <input type="text" name="device_name" class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Device Type</label>
                        <input type="text" name="device_type" class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Device IP</label>
                        <input type="text" name="device_ip" class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Location</label>
                        <input type="text" name="location" class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Enable Username</label>
                        <input type="text" name="enable_username" class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Enable Password</label>
                        <input type="password" name="enable_password" class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Access Port</label>
                        <input type="text" name="access_port" class="form-control">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Note</label>
                        <textarea name="note" class="form-control" rows="2"></textarea>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">💾 Save Device</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Device Modal -->
    <div class="modal fade" id="editDeviceModal" tabindex="-1" aria-labelledby="editDeviceModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form id="editDeviceForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">✏️ Edit Device</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body row g-3">

                    <input type="hidden" name="device_id" id="edit-device-id">

                    <div class="col-md-6">
                        <label class="form-label">Client</label>
                        <select name="client_id" id="edit-client-id" class="form-select" required>
                            <option value="">Select Client</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= $client['id'] ?>"><?= htmlspecialchars($client['client_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Device Name</label>
                        <input type="text" name="device_name" id="edit-device-name" class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Device Type</label>
                        <input type="text" name="device_type" id="edit-device-type" class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Device IP</label>
                        <input type="text" name="device_ip" id="edit-device-ip" class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Location</label>
                        <input type="text" name="location" id="edit-location" class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" id="edit-username" class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" id="edit-password" class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Enable Username</label>
                        <input type="text" name="enable_username" id="edit-enable-username" class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Enable Password</label>
                        <input type="password" name="enable_password" id="edit-enable-password" class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Access Port</label>
                        <input type="text" name="access_port" id="edit-access-port" class="form-control">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Note</label>
                        <textarea name="note" id="edit-note" class="form-control" rows="2"></textarea>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">💾 Update Device</button>
                </div>

            </form>
        </div>
    </div>

    <?php if ($viewing && $view_data): ?>
        <div class="my-4" style="width: 95%; margin: auto;">
            <div class="card border-0 rounded-3 bg-light">
                <div class="card-header bg-light text-black d-flex align-items-center">
                    <i class="fas fa-eye me-2" style="font-size: 1.5rem;"></i>
                    <h4 class="mb-0">Viewing Device: <?= htmlspecialchars($view_data['device_name']) ?></h4>
                </div>

                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <p><strong>Client:</strong> <?= htmlspecialchars($view_data['client_name']) ?></p>
                            <p><strong>Device Type:</strong> <?= htmlspecialchars($view_data['device_type']) ?></p>
                            <p><strong>Device IP:</strong> <?= htmlspecialchars($view_data['device_ip']) ?></p>
                            <p><strong>Location:</strong> <?= htmlspecialchars($view_data['location']) ?></p>
                        </div>

                        <div class="col-md-6">
                            <p><strong>Username:</strong> <?= htmlspecialchars($view_data['username']) ?></p>
                            <p><strong>Access Port:</strong> <?= htmlspecialchars($view_data['access_port']) ?></p>
                            <p><strong>Enable Username:</strong> <?= htmlspecialchars($view_data['enable_username']) ?></p>
                        </div>

                        <div class="col-12">
                            <p><strong>Notes:</strong><br><?= nl2br(htmlspecialchars($view_data['note'])) ?></p>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-light border-0">
                    <div class="d-flex flex-wrap gap-2">
                        <a href="index.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-arrow-left me-1"></i> Back to Device List
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>



    <!-- JS + Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

    <script>
        // Toggle password visibility
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', function () {
                const span = this.previousElementSibling;
                if (span.textContent === '*****') {
                    span.textContent = this.getAttribute('data-password');
                } else {
                    span.textContent = '*****';
                }
            });
        });

        // Handle Add Device Form
        document.getElementById('addDeviceForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'add');

            axios.post('backend.php', formData)
                .then(response => {
                    if (response.data.trim() === 'success') {
                        alert('Device Added Successfully ✅');
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                })
                .catch(error => {
                    console.error(error);
                    alert('Server Error ❌');
                });
        });

        // Handle Delete Device
        function openDeleteModal(id) {
            if (confirm('Are you sure you want to delete this device?')) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);

                axios.post('backend.php', formData)
                    .then(response => {
                        if (response.data.trim() === 'success') {
                            alert('Device Deleted Successfully ✅');
                            location.reload();
                        } else {
                            alert('Error: ' + response.data);
                        }
                    })
                    .catch(error => {
                        console.error(error);
                        alert('Server Error ❌');
                    });
            }
        }

        // TODO: Handle Edit Modal (in next part)
        // Open Edit Modal
        function openEditModal(id) {
            const formData = new FormData();
            formData.append('action', 'fetch');
            formData.append('id', id);

            axios.post('backend.php', formData)
                .then(response => {
                    const device = response.data;

                    if (device.error) {
                        alert('Device not found!');
                        return;
                    }

                    document.getElementById('edit-device-id').value = device.id;
                    document.getElementById('edit-client-id').value = device.client_id ?? '';
                    document.getElementById('edit-device-name').value = device.device_name ?? '';
                    document.getElementById('edit-device-type').value = device.device_type ?? '';
                    document.getElementById('edit-device-ip').value = device.device_ip ?? '';
                    document.getElementById('edit-location').value = device.location ?? '';
                    document.getElementById('edit-username').value = device.username ?? '';
                    document.getElementById('edit-password').value = device.password ?? '';
                    document.getElementById('edit-enable-username').value = device.enable_username ?? '';
                    document.getElementById('edit-enable-password').value = device.enable_password ?? '';
                    document.getElementById('edit-access-port').value = device.access_port ?? '';
                    document.getElementById('edit-note').value = device.note ?? '';

                    var editModal = new bootstrap.Modal(document.getElementById('editDeviceModal'));
                    editModal.show();
                })
                .catch(error => {
                    console.error(error);
                    alert('Failed to fetch device details ❌');
                });
        }



        // Handle Edit Form Submit
        document.getElementById('editDeviceForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'edit');

            axios.post('backend.php', formData)
                .then(response => {
                    if (response.data.trim() === 'success') {
                        alert('Device Updated Successfully ✅');
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                })
                .catch(error => {
                    console.error(error);
                    alert('Server Error ❌');
                });
        });
        // Handle Filter Form Submit
        document.getElementById('filterForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);

            axios.post('device-filter.php', formData)
                .then(response => {
                    const tbody = document.querySelector('table tbody');
                    tbody.innerHTML = response.data;
                })
                .catch(error => {
                    alert('Failed to filter results ❌');
                    console.error(error);
                });
        });

        // Reset Filters
        function resetFilters() {
            document.getElementById('filterForm').reset();
            document.getElementById('filterForm').dispatchEvent(new Event('submit'));
        }
    </script>

</body>

</html>