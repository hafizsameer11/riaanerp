<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$db_host = "localhost";
    $db_user = "clientzone_user";
    $db_pass = "S@utech2024!";
    $db_name = "clientzone";

include_once '../../config.php'; // Ensure this path is correct


$clients = $conn->query("SELECT DISTINCT client FROM spla_licenses ORDER BY client");
$selectedClient = $_GET['client'] ?? '';

$query = "
    SELECT e.*, l.client, l.vm_name, l.ms_products
    FROM spla_licenses_edits e
    JOIN spla_licenses l ON e.spla_id = l.id
    WHERE 1 = 1
";

$params = [];
$types = "";

if ($selectedClient !== '') {
    $query .= " AND l.client = ?";
    $params[] = $selectedClient;
    $types .= "s";
}

$query .= " ORDER BY e.edited_at DESC";
$stmt = $conn->prepare($query);
if ($types !== '') $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<h2>📜 SPLA Change History</h2>
<form method="get">
    <label>Filter by Client:</label>
    <select name="client">
        <option value="">All</option>
        <?php while ($c = $clients->fetch_assoc()): ?>
            <option value="<?= $c['client'] ?>" <?= $selectedClient === $c['client'] ? 'selected' : '' ?>><?= $c['client'] ?></option>
        <?php endwhile; ?>
    </select>
    <button type="submit">🔍 Filter</button>
</form>
<br>

<table border="1" cellpadding="6" cellspacing="0">
    <tr>
        <th>Client</th>
        <th>VM / License</th>
        <th>Field Changed</th>
        <th>Old Value</th>
        <th>New Value</th>
        <th>Edited At</th>
    </tr>
    <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($row['client']) ?></td>
            <td><?= $row['vm_name'] ?: $row['ms_products'] ?></td>
            <td><?= htmlspecialchars($row['field_changed']) ?></td>
            <td><?= htmlspecialchars($row['old_value']) ?></td>
            <td><strong><?= htmlspecialchars($row['new_value']) ?></strong></td>
            <td><?= $row['edited_at'] ?></td>
        </tr>
    <?php endwhile; ?>
</table>
