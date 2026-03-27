<?php

$db_host = "localhost";
$db_user = "clientzone_user";
$db_pass = "S@utech2024!";
$db_name = "clientzone";

include_once '../../config.php'; // Ensure this path is correct
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$roles = $conn->query("SELECT * FROM roles");
$permissions = [
    "Hosting and Licensing" => [
        'hosting',
        'logins',
        'spla',
        'devices'
    ],
    "hosting" => [
        'create',
        'update',
        'delete',
        'export csv',
    ],
    "logins" => [
        'create',
        'update',
        'delete',
    ],
    "spla" => [
        'create',
        'update',
        'delete',
    ],
    "devices" => [
        'create',
        'update',
        'delete',
    ],
    'admin service' => [
        "Manage Suppliers",
        "Manage Service Types",
        "Manage Service Categories",
        "Unit Prices",
        "Manage Hosting Assets",
        "Manage Invoice Companies",
        "Finance Calculator",
        "Reseller",
    ],
    "Manage Suppliers" => ['create', 'update', 'delete'],
    "Manage Service Types" => ['create', 'update', 'delete'],
    "Manage Service Categories" => ['create', 'update', 'delete'],
    "Unit Prices" => ['create', 'update', 'delete', 'Bulk Price',],
    "Manage Hosting Assets" => ['create'],
    "Manage Invoice Companies" => ['create', 'update', 'delete'],
    "Reseller" => ['create', 'update', 'delete'],
    "clients" => [
        "create",
        "update",
        "delete"
    ],
    "billing" => [
        'billing',
        'wip',
        'quotes',
        'expenses',
    ],
    'billing page' => [
        'create',
        'update',
        'delete',
        'extend expired',
        'delete expired',
        'View all'
    ],
    'wip' => ['create', 'update', 'delete'],
    'quotes' => ['create', 'update', 'delete', 'send_email','View all'],
    'expenses' => ['create', 'update', 'delete'],
    "report and admin" => [
        'user logins',
        'billing report',
        "reseller commission",
        'role management',
    ],
    'user logins' => ['create', 'update', 'delete'],
    'billing report' => ['Mark as procced'],
    "reseller commission" => ['Send Email','View all'],
];
$allFuncs = ['hosting', 'logins', 'spla', 'devices', 'create', 'update', 'delete', 'view', 'send_email', 'ban_user'];


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['role_id']) && isset($_POST['save_permissions'])) {
    $roleId = (int) $_POST['role_id'];

    // Always remove existing permissions for this role
    $conn->query("DELETE FROM permissions WHERE role_id = $roleId");

    // Only insert if there are permissions checked
    if (isset($_POST['permission'])) {
        foreach ($_POST['permission'] as $page => $actions) {
            foreach ($actions as $func => $val) {
                $stmt = $conn->prepare("INSERT INTO permissions (role_id, page, function_name, allowed) VALUES (?, ?, ?, 1)");
                $stmt->bind_param("iss", $roleId, $page, $func);
                $stmt->execute();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Role Permissions</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body>
    <div class="container mt-5">
        <h1 class="mb-4">Manage Role Permissions</h1>

        <!-- Role Selector -->
        <form method="POST" class="mb-4">
            <div class="mb-3">
                <label for="roleSelect" class="form-label">Select Role</label>
                <select name="role_id" id="roleSelect" class="form-select" required onchange="this.form.submit()">
                    <option value="">Select</option>
                    <?php while ($r = $roles->fetch_assoc()): ?>
                        <option value="<?= $r['id'] ?>" <?= (isset($_POST['role_id']) && $_POST['role_id'] == $r['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($r['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </form>

        <?php if (!empty($_POST['role_id'])): ?>
            <?php
            $roleId = (int) $_POST['role_id'];

            // Preload permission map
            $permissionMap = [];
            $permResult = $conn->query("SELECT page, function_name FROM permissions WHERE role_id = $roleId AND allowed = 1");
            while ($perm = $permResult->fetch_assoc()) {
                $permissionMap[$perm['page']][$perm['function_name']] = true;
            }
            ?>

            <!-- Permissions Form -->
            <form method="POST">
                <input type="hidden" name="role_id" value="<?= $roleId ?>">
                <input type="hidden" name="save_permissions" value="1">

                <!-- Tabs for Pages -->
                <ul class="nav nav-tabs mb-3" id="permissionTabs" role="tablist">
                    <?php $i = 0;
                    foreach ($permissions as $page => $funcs): ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= $i === 0 ? 'active' : '' ?>" id="tab-<?= $i ?>" data-bs-toggle="tab"
                                data-bs-target="#content-<?= $i ?>" type="button" role="tab">
                                <?= htmlspecialchars($page) ?>
                            </button>
                        </li>
                        <?php $i++; endforeach; ?>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="permissionTabsContent">
                    <?php
                    $i = 0;
                    foreach ($permissions as $page => $functions):
                        ?>
                        <div class="tab-pane fade <?= $i === 0 ? 'show active' : '' ?>" id="content-<?= $i ?>" role="tabpanel">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <?php foreach ($functions as $f): ?>
                                            <th class="text-center"><?= ucfirst(str_replace('_', ' ', $f)) ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <?php foreach ($functions as $f): ?>
                                            <td class="text-center">
                                                <input type="checkbox" style="width: 25px; height: 25px;"
                                                    name="permission[<?= $page ?>][<?= $f ?>]" value="1"
                                                    <?= !empty($permissionMap[$page][$f]) ? 'checked' : '' ?>>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <?php $i++; endforeach; ?>
                </div>

                <button type="submit" class="btn btn-primary mt-3">Save Permissions</button>
            </form>


        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>