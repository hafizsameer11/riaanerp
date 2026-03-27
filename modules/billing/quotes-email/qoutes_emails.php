<?php
// quotes_email_records.php

include_once '../../config.php'; // Ensure this path is correct
// Handle filters
$where = [];
if (!empty($_GET['client_id'])) {
    $where[] = "e.client_id = " . (int)$_GET['client_id'];
}
if (!empty($_GET['quote_number'])) {
    $quoteNo = $conn->real_escape_string($_GET['quote_number']);
    $where[] = "q.quote_number LIKE '%$quoteNo%'";
}

$filterSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$result = $conn->query("SELECT e.*, c.client_name, q.quote_number FROM quote_emails e
    LEFT JOIN clients c ON e.client_id = c.id
    LEFT JOIN quotes q ON e.quote_id = q.id
    $filterSql ORDER BY e.id DESC");

$clients = $conn->query("SELECT id, client_name FROM clients");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Quote Email Records</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
<div class="container">
    <h2 class="mb-4">Quote Email Records</h2>

    <!-- Filters -->
    <form method="GET" class="card p-3 mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Client</label>
                <select name="client_id" class="form-select">
                    <option value="">All Clients</option>
                    <?php while ($row = $clients->fetch_assoc()): ?>
                        <option value="<?= $row['id'] ?>" <?= ($_GET['client_id'] ?? '') == $row['id'] ? 'selected' : '' ?>><?= htmlspecialchars($row['client_name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Quote Number</label>
                <input type="text" name="quote_number" class="form-control" value="<?= htmlspecialchars($_GET['quote_number'] ?? '') ?>">
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-primary">Apply Filters</button>
                <a href="quotes_email_records.php" class="btn btn-secondary">Reset</a>
            </div>
        </div>
    </form>

    <!-- Email Records Table -->
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Client</th>
                <th>Quote #</th>
                <th>Sender Name</th>
                <th>Sender Email</th>
                <th>Recipient Name</th>
                <th>Recipient Email</th>
                <th>Sent At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result->num_rows > 0):
            while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['client_name']) ?></td>
                    <td><?= htmlspecialchars($row['quote_number']) ?></td>
                    <td><?= htmlspecialchars($row['sender_name']) ?></td>
                    <td><?= htmlspecialchars($row['sender_email']) ?></td>
                    <td><?= htmlspecialchars($row['recipient_name']) ?></td>
                    <td><?= htmlspecialchars($row['recipient_email']) ?></td>
                    <td><?= $row['sent_at'] ?></td>
                    <td><a href="view_quote.php?id=<?= $row['quote_id'] ?>" class="btn btn-sm btn-info">View Quote</a></td>
                </tr>
            <?php endwhile;
        else: ?>
            <tr><td colspan="9" class="text-center">No email records found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
