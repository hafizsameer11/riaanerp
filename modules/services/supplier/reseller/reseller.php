<?php
$db_host = "localhost";
$db_user = "clientzone_user";
$db_pass = "S@utech2024!";
$db_name = "clientzone";

include_once '../../../config.php'; // Ensure this path is correct
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$alert = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add']) || isset($_POST['update'])) {
        $client_id = intval($_POST['client_id']);
        $description = $conn->real_escape_string($_POST['description']);
        $name = $conn->real_escape_string($_POST['name']);
        $email = $conn->real_escape_string($_POST['email']);
        $user_id = $conn->real_escape_string($_POST['user_id']);
    }

    if (isset($_POST['add'])) {
        $client_ids = json_encode($_POST['client_ids']); // Convert array to JSON

        $conn->query("INSERT INTO resellers (client_id, description, name, email,register_id) VALUES ('$client_ids', '$description', '$name', '$email','$user_id')");
        $alert = 'Reseller added successfully!';
        header("Location: reseller.php");
    } elseif (isset($_POST['update'])) {
        $id = intval($_POST['id']);
        $client_ids = json_encode($_POST['client_ids']); // Convert array to JSON
        $description = $conn->real_escape_string($_POST['description']);
        $name = $conn->real_escape_string($_POST['name']);
        $email = $conn->real_escape_string($_POST['email']);
        $user_id = $conn->real_escape_string($_POST['user_id']);
        
        $conn->query("UPDATE resellers SET client_id='$client_ids', description='$description', name='$name', email='$email' , register_id='$user_id'  WHERE id=$id");
        $alert = 'Reseller updated successfully!';
        header("Location: reseller.php");
    } elseif (isset($_POST['delete'])) {
        $id = intval($_POST['id']);
        $conn->query("DELETE FROM resellers WHERE id=$id");
        $alert = 'Reseller deleted successfully!';
        header("Location: reseller.php");
    }
}


$clients = $conn->query("SELECT id, client_name FROM clients ORDER BY client_name ASC");
$registers = $conn->query(" SELECT r.*, roles.name AS role_name FROM registers r LEFT JOIN roles ON r.role_id = roles.id");
$resellers = $conn->query("
        SELECT r.*, c.client_name, u.name AS user_name
        FROM resellers r 
        LEFT JOIN clients c ON r.client_id = c.id
        LEFT JOIN registers u ON r.register_id = u.id
        ORDER BY r.name ASC"
    );
?>
<?php session_start(); ?>
<?php include('../../../components/permissioncheck.php') ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Reseller Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .select2-container {
            width: 100% !important;
        }
    </style>
</head>

<body>
    <div class="px-5 mt-5">
        <?php if ($alert): ?>
            <div class="alert alert-success"><?= htmlspecialchars($alert) ?></div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="d-flex align-items-center">

                <h2>Reseller Management</h2>
            </div>
            <?php if (hasPermission('Reseller', 'create')): ?>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addModal">Add Reseller</button>
            <?php endif; ?>
        </div>

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Client</th>
                    <th>Assigned To</th>
                    <th>Description</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($r = $resellers->fetch_assoc()): ?>
                    <?php
                    $client_ids = json_decode($r['client_id'], true); // Decode JSON to array
                    $client_names = [];
                    foreach ($client_ids as $client_id) {
                        $client = $conn->query("SELECT client_name FROM clients WHERE id = $client_id")->fetch_assoc();
                        if ($client) {
                            $client_names[] = $client['client_name'];
                        }
                    }
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($r['name']) ?></td>
                        <td><?= htmlspecialchars($r['email']) ?></td>
                        <td><?= htmlspecialchars(implode(' - ', $client_names)) ?></td>
                        <!-- Display multiple client names -->
                        <td><?= htmlspecialchars($r['user_name'] ? $r['user_name'] : 'N/A') ?></td>
                        <td><?= htmlspecialchars($r['description']) ?></td>
                        <td>
                            <?php if (hasPermission('Reseller', 'update')): ?>
                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal"
                                    data-bs-target="#editModal<?= $r['id'] ?>">Edit</button>
                            <?php endif; ?>
                            <?php if (hasPermission('Reseller', 'delete')): ?>
                                <button class="btn btn-danger btn-sm" data-bs-toggle="modal"
                                    data-bs-target="#deleteModal<?= $r['id'] ?>">Delete</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php
    $resellers->data_seek(0); // Reset pointer
    while ($r = $resellers->fetch_assoc()):
        ?>
        <!-- Edit Modal -->
        <div class="modal fade" id="editModal<?= $r['id'] ?>" tabindex="-1">
            <div class="modal-dialog">
                <form method="POST" class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Reseller</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" value="<?= htmlspecialchars($r['name']) ?>" class="form-control"
                                required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($r['email']) ?>"
                                class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Clients</label>
                            <select name="client_ids[]" class="form-select select2" multiple required>
                                <?php
                                $clients->data_seek(0); // Reset the pointer for the clients query
                                $selectedClients = json_decode($r['client_id'], true); // Decode JSON to array
                                while ($c = $clients->fetch_assoc()): ?>
                                    <option value="<?= $c['id'] ?>" <?= in_array($c['id'], $selectedClients) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['client_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <small class="text-muted">Select multiple clients.</small>
                        </div>
                        <div class="mb-3">
                            <label for="">Assign to</label>
                            <select name="user_id" class='form-select' id="user_id">
                                <?php
                                $registers->data_seek(0); // Reset the pointer for the clients query
                                while ($c = $registers->fetch_assoc()): ?>
                                    <option value="<?= $c['id'] ?>" <?= $c['id'] == $r['register_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['role_name']) ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <input type="text" name="description" value="<?= htmlspecialchars($r['description']) ?>"
                                class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button name="update" class="btn btn-primary">Update</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
        <!-- Delete Modal -->
        <div class="modal fade" id="deleteModal<?= $r['id'] ?>" tabindex="-1">
            <div class="modal-dialog">
                <form method="POST" class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Reseller</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        Are you sure you want to delete <strong><?= htmlspecialchars($r['client_name']) ?></strong>?
                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                    </div>
                    <div class="modal-footer">
                        <button name="delete" class="btn btn-danger">Delete</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

    <?php endwhile; ?>


    <!-- Add Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Reseller</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Clients</label>
                        <select name="client_ids[]" class="form-select select2" multiple required>
                            <?php
                            $clients->data_seek(0); // Reset the pointer for the clients query
                            while ($c = $clients->fetch_assoc()): ?>
                                <option value="<?= $c['id'] ?>">
                                    <?= htmlspecialchars($c['client_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <small class="text-muted">Select multiple clients.</small>
                    </div>
                    <div class="mb-3">
                            <label for="">Assign to</label>
                            <select name="user_id" class='form-select' id="user_id">
                                <?php
                                $registers->data_seek(0); // Reset the pointer for the clients query
                                while ($c = $registers->fetch_assoc()): ?>
                                    <option value="<?= $c['id'] ?>" >
                                        <?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['role_name']) ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button name="add" class="btn btn-success">Add</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Include jQuery -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Include Select2 CSS -->

    <!-- Include Select2 JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
         $(document).ready(function () {
        $('.select2').each(function () {
            var $parentModal = $(this).closest('.modal');
            $(this).select2({
                dropdownParent: $parentModal.length ? $parentModal : $(document.body) // Use modal if inside, else body
            });
        });
    });
    </script>
</body>

</html>