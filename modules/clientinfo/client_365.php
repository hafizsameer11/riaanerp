<?php
$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
if ($client_id <= 0)
    die("Invalid client ID.");

include_once '../config.php'; // Ensure this path is correct
if ($conn->connect_error)
    die("Connection failed: " . $conn->connect_error);

$client = $conn->query("SELECT client_name FROM clients WHERE id = $client_id")->fetch_assoc();
$client_name = $client ? $client['client_name'] : "Unknown";

// Handle edit prefill
$edit_record = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_record = $conn->query("SELECT * FROM client_365 WHERE id = $edit_id AND client_id = $client_id")->fetch_assoc();
}

// Add or update record
if (isset($_POST['save_record'])) {
    $product = $conn->real_escape_string($_POST['product']);
    $fullname = $conn->real_escape_string($_POST['fullname']);
    $username = $conn->real_escape_string($_POST['username']);
    $password = $conn->real_escape_string($_POST['password']);
    $note = $conn->real_escape_string($_POST['note']);

    if (isset($_POST['record_id']) && intval($_POST['record_id']) > 0) {
        $id = intval($_POST['record_id']);
        $conn->query("UPDATE client_365 SET product='$product', fullname='$fullname', username='$username', password='$password', note='$note' WHERE id=$id AND client_id=$client_id");
    } else {
        $conn->query("INSERT INTO client_365 (client_id, product, fullname, username, password, note) VALUES ($client_id, '$product', '$fullname', '$username', '$password', '$note')");
    }

    header("Location: ?client_id=$client_id");
    exit;
}
$categories = $conn->query("SELECT id, category_name FROM billing_service_categories ORDER BY category_name ASC");
// Delete record
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM client_365 WHERE id = $id AND client_id = $client_id");
    header("Location: ?client_id=$client_id");
    exit;
}

// Export CSV
if (isset($_GET['export'])) {
    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=client_365.csv");

    $res = $conn->query("SELECT * FROM client_365 WHERE client_id = $client_id");
    echo "Product,Full Name,Username,Password,Note\n";
    while ($row = $res->fetch_assoc()) {
        echo "{$row['product']},{$row['fullname']},{$row['username']},{$row['password']},{$row['note']}\n";
    }
    exit;
}

$records = $conn->query("SELECT * FROM client_365 WHERE client_id = $client_id ORDER BY id DESC");
?>

<!DOCTYPE html>
<html>

<head>
    <title>Client 365</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="p-4">
    <div class="container">
        <div class="d-flex align-items-center mb-4">
            <?php include('../components/Backbtn.php') ?>
            <h3 class="mb-4">
                <img src="images/0365.jpg" alt="365 Icon" style="height: 100px; vertical-align: middle; margin-right: 8px;">
                <span style="color: #0d6efd; font-size: 130%;">365 Products</span>
            </h3>
        </div>
        <p><strong class="text-dark">Client: <?= htmlspecialchars($client_name) ?> (ID: <?= $client_id ?>)</strong></p>

        <form method="post" class="row g-3">
            <?php if ($edit_record): ?>
                <input type="hidden" name="record_id" value="<?= $edit_record['id'] ?>">
            <?php endif; ?>
            <input type="hidden" name="client_id" value="<?= $client_id ?>">

            <!-- filepath: c:\xampp\htdocs\sautech\modules\clientinfo\client_365.php -->
            <div class="col-md-3">
                <label class="form-label">Product</label>
                <select name="product" class="form-select" required>
                    <option value="">Select Product</option>
                    <?php while ($category = $categories->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($category['category_name']) ?>"
                            <?= isset($edit_record['product']) && $edit_record['product'] === $category['category_name'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category['category_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Full Name</label>
                <input type="text" name="fullname" value="<?= $edit_record['fullname'] ?? '' ?>" class="form-control"
                    required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" value="<?= $edit_record['username'] ?? '' ?>" class="form-control"
                    required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Password</label>
                <input type="text" name="password" value="<?= $edit_record['password'] ?? '' ?>" class="form-control"
                    required>
            </div>
            <div class="col-md-12">
                <label class="form-label">Note</label>
                <input type="text" name="note" value="<?= $edit_record['note'] ?? '' ?>" class="form-control">
            </div>
            <div class="col-12">
                <button class="btn btn-primary" type="submit" name="save_record">
                    <?= $edit_record ? 'Update' : 'Add' ?> Record
                </button>
            </div>
        </form>

        <hr class="my-4">

        <table class="table table-bordered table-striped">
            <thead class="table-light">
                <tr>
                    <th>Product</th>
                    <th>Full Name</th>
                    <th>Username</th>
                    <th>Password</th>
                    <th>Note</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($r = $records->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['product']) ?></td>
                        <td><?= htmlspecialchars($r['fullname']) ?></td>
                        <td><?= htmlspecialchars($r['username']) ?></td>
                        <td><?= htmlspecialchars($r['password']) ?></td>
                        <td><?= htmlspecialchars($r['note']) ?></td>
                        <td>
                            <a href="?client_id=<?= $client_id ?>&edit=<?= $r['id'] ?>"
                                class="btn btn-sm btn-primary">Edit</a>
                            <a href="?client_id=<?= $client_id ?>&delete=<?= $r['id'] ?>" class="btn btn-sm btn-danger"
                                onclick="return confirm('Delete this record?')">Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <a href="?client_id=<?= $client_id ?>&export=1" class="btn btn-success mt-3" style="width: 150px;">Export
            CSV</a>
    </div>
</body>

</html>