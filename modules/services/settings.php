<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$db_host = "localhost";
    $db_user = "clientzone_user";
    $db_pass = "S@utech2024!";
    $db_name = "clientzone";

include_once '../../config.php'; // Ensure this path is correct


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newVAT = floatval($_POST['vat_rate']);
    $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'vat_rate'");
    $stmt->bind_param("s", $_POST['vat_rate']);
    $stmt->execute();

    // Recalculate VAT for all services
    $services = $conn->query("SELECT id, ex_vat_amount, quantity, exclude_vat FROM client_services WHERE is_deleted = 0");
    while ($row = $services->fetch_assoc()) {
        if ($row['exclude_vat']) {
            $qty = max(1, intval($row['quantity']));
            $ex = floatval($row['ex_vat_amount']);
            $vat = round($ex * ($newVAT / 100), 2);
            $total = round($ex + $vat, 2);
            $vatAll = $vat * $qty;
            $totalAll = $total * $qty;

            $update = $conn->prepare("UPDATE client_services SET vat_amount = ?, total_with_vat = ? WHERE id = ?");
            $update->bind_param("ddi", $vatAll, $totalAll, $row['id']);
            $update->execute();
        }
    }

    echo "<p style='color:green;'>VAT rate updated to $newVAT% and all applicable services recalculated.</p>";
}

// Load current value
$row = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'vat_rate'")->fetch_assoc();
$currentVAT = $row ? $row['setting_value'] : '15';
?>

<h2>Global VAT Settings</h2>

<form method="post">
    Current VAT Rate: <input type="number" name="vat_rate" step="0.01" value="<?= htmlspecialchars($currentVAT) ?>" required> %<br><br>
    <button type="submit">Update VAT Rate</button>
</form>

<?php
$check = $conn->query("SELECT * FROM client_services ORDER BY id DESC LIMIT 5");
while ($row = $check->fetch_assoc()) {
    echo "<pre>" . print_r($row, true) . "</pre>";
}
?>

<hr>
<h3>🛠 Debug: Last 5 Saved Services</h3>

<?php
$result = $conn->query("SELECT id, client_name, service_type, service_name, is_deleted FROM client_services ORDER BY id DESC LIMIT 5");
if ($result && $result->num_rows > 0) {
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>ID</th><th>Client</th><th>Type</th><th>Service</th><th>Deleted?</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['client_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['service_type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['service_name']) . "</td>";
        echo "<td>" . ($row['is_deleted'] ? 'Yes' : 'No') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No services found.</p>";
}
?>

