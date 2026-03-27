<?php

$db_host = "localhost";
$db_user = "clientzone_user";
$db_pass = "S@utech2024!";
$db_name = "clientzone";

include_once '../../config.php'; // Ensure this path is correct
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Add Role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_role'])) {
    $roleName = trim($_POST['role']);
    $stmt = $conn->prepare("INSERT INTO roles (name) VALUES (?)");
    $stmt->bind_param("s", $roleName);
    $stmt->execute();
    header("Location: roles.php");
}

// Update Role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    $roleId = $_POST['role_id'];
    $roleName = trim($_POST['role']);
    $stmt = $conn->prepare("UPDATE roles SET name = ? WHERE id = ?");
    $stmt->bind_param("si", $roleName, $roleId);
    $stmt->execute();
    header("Location: roles.php");
}

// Delete Role
if (isset($_GET['delete'])) {
    $roleId = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM roles WHERE id = ?");
    $stmt->bind_param("i", $roleId);
    $stmt->execute();
    header("Location: roles.php");
}

// Fetch Roles
$result = $conn->query("SELECT * FROM roles");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Roles</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-5">
        <div class="d-flex align-items-center mb-4 ">
            <?php include('../../components/Backbtn.php') ?>
            <h1 class="">Manage Roles</h1>
        </div>

        <!-- Add Role Form -->
        <form method="POST" class="needs-validation mb-4" novalidate>
            <div class="mb-3">
                <label for="role" class="form-label">Role Name</label>
                <input type="text" class="form-control" id="role" name="role" required>
                <div class="invalid-feedback">
                    Please provide a role name.
                </div>
            </div>
            <button type="submit" name="add_role" class="btn btn-primary">Add Role</button>
        </form>

        <!-- Roles Table -->
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Role Name</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td>
                            <!-- Update Button -->
                            <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal"
                                data-bs-target="#updateModal" data-role-id="<?= $row['id'] ?>"
                                data-role-name="<?= htmlspecialchars($row['name']) ?>">
                                Update
                            </button>
                            <!-- Delete Button -->
                            <a href="?delete=<?= $row['id'] ?>" class="btn btn-danger btn-sm"
                                onclick="return confirm('Are you sure?')">Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Update Modal -->
    <div class="modal fade" id="updateModal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="updateModalLabel">Update Role</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="role_id" id="modalRoleId">
                        <div class="mb-3">
                            <label for="modalRoleName" class="form-label">Role Name</label>
                            <input type="text" class="form-control" id="modalRoleName" name="role" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="update_role" class="btn btn-primary">Update Role</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Populate modal with role data
        const updateModal = document.getElementById('updateModal');
        updateModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const roleId = button.getAttribute('data-role-id');
            const roleName = button.getAttribute('data-role-name');

            const modalRoleId = updateModal.querySelector('#modalRoleId');
            const modalRoleName = updateModal.querySelector('#modalRoleName');

            modalRoleId.value = roleId;
            modalRoleName.value = roleName;
        });
    </script>
</body>

</html>