<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$db_host = "localhost";
    $db_user = "clientzone_user";
    $db_pass = "S@utech2024!";
    $db_name = "clientzone";

include_once '../../config.php'; // Ensure this path is correct


$selectedMonth = $_GET['month'] ?? date('Y-m');
$selectedService = $_GET['service'] ?? '';
$selectedType = $_GET['type'] ?? '';
$selectedClient = $_GET['client'] ?? '';
$monthStart = $selectedMonth . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));

$serviceResult = $conn->query("SELECT DISTINCT service_name FROM client_services WHERE is_deleted = 0 ORDER BY service_name ASC");
$typeResult = $conn->query("SELECT DISTINCT service_type FROM client_services WHERE is_deleted = 0 ORDER BY service_type ASC");
$clientResult = $conn->query("SELECT DISTINCT client_name FROM client_services WHERE is_deleted = 0 ORDER BY client_name ASC");

$query = "
    SELECT cs.*, 
           COALESCE(
               (SELECT ce.new_quantity FROM client_service_edits ce 
                WHERE ce.service_id = cs.id AND ce.edited_at <= ? 
                ORDER BY ce.edited_at DESC LIMIT 1),
               cs.quantity
           ) AS quantity_in_month
    FROM client_services cs
    WHERE cs.created_at <= ?
";

$params = [$monthEnd, $monthEnd];
$types = "ss";

if (!empty($selectedService)) {
    $query .= " AND cs.service_name = ?";
    $types .= "s";
    $params[] = $selectedService;
}
if (!empty($selectedType)) {
    $query .= " AND cs.service_type = ?";
    $types .= "s";
    $params[] = $selectedType;
}
if (!empty($selectedClient)) {
    $query .= " AND cs.client_name = ?";
    $types .= "s";
    $params[] = $selectedClient;
}

$query .= " ORDER BY cs.service_type, cs.service_name, cs.client_name";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="report_' . $selectedMonth . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Client', 'Type', 'Service', 'Status', 'Specs', 'Currency', 'Quantity', 'Ex VAT', 'Total incl VAT', 'Start Date', 'End Date']);
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['client_name'], $row['service_type'], $row['service_name'], $row['status'], $row['specs'],
            $row['currency'], $row['quantity_in_month'], $row['ex_vat_amount'], $row['total_with_vat'],
            $row['start_date'], $row['end_date']
        ]);
    }
    fclose($output);
    exit;
}
?>

<h2>📊 Service Report by Month</h2>

<form method="get">
    <label>Select Month:</label>
    <input type="month" name="month" value="<?= htmlspecialchars($selectedMonth) ?>">

    <label>Client:</label>
    <select name="client">
        <option value="">All Clients</option>
        <?php while ($row = $clientResult->fetch_assoc()): ?>
            <option value="<?= htmlspecialchars($row['client_name']) ?>" <?= ($selectedClient === $row['client_name']) ? 'selected' : '' ?>><?= htmlspecialchars($row['client_name']) ?></option>
        <?php endwhile; ?>
    </select>

    <label>Type:</label>
    <select name="type">
        <option value="">All Types</option>
        <?php while ($row = $typeResult->fetch_assoc()): ?>
            <option value="<?= htmlspecialchars($row['service_type']) ?>" <?= ($selectedType === $row['service_type']) ? 'selected' : '' ?>><?= htmlspecialchars($row['service_type']) ?></option>
        <?php endwhile; ?>
    </select>

    <label>Service:</label>
    <select name="service">
        <option value="">All Services</option>
        <?php while ($row = $serviceResult->fetch_assoc()): ?>
            <option value="<?= htmlspecialchars($row['service_name']) ?>" <?= ($selectedService === $row['service_name']) ? 'selected' : '' ?>><?= htmlspecialchars($row['service_name']) ?></option>
        <?php endwhile; ?>
    </select>

    <button type="submit">🔍 View Report</button>
    <button type="submit" name="export" value="csv">⬇ Export CSV</button>
</form>
<br>

<table border="1" cellpadding="6" cellspacing="0">
    <tr>
        <th>Client</th>
        <th>Type</th>
        <th>Service</th>
        <th>Status</th>
        <th>Specs</th>
        <th>Currency</th>
        <th>Quantity (as of <?= $selectedMonth ?>)</th>
        <th>Ex VAT</th>
        <th>Total (incl VAT)</th>
        <th>Start Date</th>
        <th>End Date</th>
    </tr>
    <?php $result->data_seek(0); while ($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($row['client_name']) ?></td>
            <td><?= htmlspecialchars($row['service_type']) ?></td>
            <td><?= htmlspecialchars($row['service_name']) ?></td>
            <td><?= htmlspecialchars($row['status']) ?></td>
            <td><?= nl2br(htmlspecialchars($row['specs'])) ?></td>
            <td><?= $row['currency'] ?></td>
            <td><strong><?= $row['quantity_in_month'] ?></strong></td>
            <td><?= number_format($row['ex_vat_amount'], 2) ?></td>
            <td><?= number_format($row['total_with_vat'], 2) ?></td>
            <td><?= $row['start_date'] ?></td>
            <td><?= $row['end_date'] ?></td>
        </tr>
    <?php endwhile; ?>
</table>
