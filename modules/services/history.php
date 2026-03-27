<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$db_host = "localhost";
    $db_user = "clientzone_user";
    $db_pass = "S@utech2024!";
    $db_name = "clientzone";

include_once '../../config.php'; // Ensure this path is correct


$selectedClient = $_GET['client'] ?? '';
$selectedService = $_GET['service'] ?? '';

$clientResult = $conn->query("SELECT DISTINCT client_name FROM client_services WHERE is_deleted = 0 ORDER BY client_name ASC");
$serviceResult = $conn->query("SELECT DISTINCT service_name FROM client_services WHERE is_deleted = 0 ORDER BY service_name ASC");

$query = "
    SELECT cs.client_name, cs.service_name, cse.old_quantity, cse.new_quantity, cse.edited_at
    FROM client_service_edits cse
    JOIN client_services cs ON cse.service_id = cs.id
    WHERE 1
";

$params = [];
$types = "";

if (!empty($selectedClient)) {
    $query .= " AND cs.client_name = ?";
    $types .= "s";
    $params[] = $selectedClient;
}
if (!empty($selectedService)) {
    $query .= " AND cs.service_name = ?";
    $types .= "s";
    $params[] = $selectedService;
}

$query .= " ORDER BY cse.edited_at DESC";

$stmt = $conn->prepare($query);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<h2>🕓 Service Quantity Change History</h2>

<form method="get">
    <label>Client:</label>
    <select name="client">
        <option value="">All Clients</option>
        <?php while ($row = $clientResult->fetch_assoc()): ?>
            <option value="<?= $row['client_name'] ?>" <?= $selectedClient === $row['client_name'] ? 'selected' : '' ?>><?= $row['client_name'] ?></option>
        <?php endwhile; ?>
    </select>

    <label>Service:</label>
    <select name="service">
        <option value="">All Services</option>
        <?php while ($row = $serviceResult->fetch_assoc()): ?>
            <option value="<?= $row['service_name'] ?>" <?= $selectedService === $row['service_name'] ? 'selected' : '' ?>><?= $row['service_name'] ?></option>
        <?php endwhile; ?>
    </select>

    <button type="submit">🔍 View History</button>
</form>
<br>

<table border="1" cellpadding="6" cellspacing="0">
    <tr>
        <th>Client</th>
        <th>Service</th>
        <th>Old Quantity</th>
        <th>New Quantity</th>
        <th>Edited At</th>
    </tr>
    <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($row['client_name']) ?></td>
            <td><?= htmlspecialchars($row['service_name']) ?></td>
            <td><?= $row['old_quantity'] ?></td>
            <td><strong><?= $row['new_quantity'] ?></strong></td>
            <td><?= $row['edited_at'] ?></td>
        </tr>
    <?php endwhile; ?>
</table>
