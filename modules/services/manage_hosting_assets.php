<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$db_host = "localhost";
    $db_user = "clientzone_user";
    $db_pass = "S@utech2024!";
    $db_name = "clientzone";

include_once '../../config.php'; // Ensure this path is correct

if ($conn->connect_error)
    die("Connection failed: " . $conn->connect_error);

// Add/Delete Support Values Logic
foreach (["location", "asset_type", "host", "os"] as $type) {
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        if (isset($_POST["add_$type"])) {
            $val = $conn->real_escape_string($_POST["new_$type"]);
            $conn->query("INSERT IGNORE INTO hosting_{$type}s (name) VALUES ('$val')");
        }
        if (isset($_POST["delete_$type"])) {
            $val = $conn->real_escape_string($_POST["remove_$type"]);
            $conn->query("DELETE FROM hosting_{$type}s WHERE name = '$val'");
        }
    }
}

// Fetch Support Values
$support = [];
foreach (["location", "asset_type", "host", "os"] as $type) {
    $support[$type] = [];
    $q = $conn->query("SELECT name FROM hosting_{$type}s ORDER BY name ASC");
    while ($r = $q->fetch_assoc()) {
        $support[$type][] = $r['name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Hosting Assets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="px-5 my-5">
        <div class="d-flex align-items-center mb-4">
            <?php session_start(); ?>
            <?php include('../components/permissioncheck.php') ?>
            <h3 class="text-dark">Manage Hosting Assets</h3>
        </div>
        <?php if (hasPermission('Manage Hosting Assets', 'create')): ?>
        
        <div class="card p-4 shadow-sm">
            <h4 class="text-success mb-4">Add / Delete Support Values</h4>
            <form method="POST" class="row g-4">
                <?php foreach (['location', 'asset_type', 'host', 'os'] as $field): ?>
                    <div class="col-md-6">
                        <input type="text" name="new_<?= $field ?>" class="form-control"
                            placeholder="New <?= ucwords(str_replace('_', ' ', $field)) ?>">
                        <button type="submit" name="add_<?= $field ?>"
                            class="btn btn-success btn-sm mt-2 w-100">Add</button>
                    </div>
                    <div class="col-md-6">
                        <select name="remove_<?= $field ?>" class="form-select">
                            <option value="">Delete <?= ucwords($field) ?></option>
                            <?php foreach ($support[$field] as $val): ?>
                                <option value="<?= $val ?>"><?= $val ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="delete_<?= $field ?>"
                            class="btn btn-danger btn-sm mt-2 w-100">Delete</button>
                    </div>
                <?php endforeach; ?>
            </form>
        </div>
        <?php endif; ?>
        <div class="row mt-5">
            <?php foreach (['location', 'asset_type', 'host', 'os'] as $field): ?>
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><?= ucwords(str_replace('_', ' ', $field)) ?></h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th><?= ucwords(str_replace('_', ' ', $field)) ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($support[$field] as $index => $val): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td><?= htmlspecialchars($val) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>

</html>