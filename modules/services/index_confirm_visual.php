<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$db_host = "localhost";
    $db_user = "clientzone_user";
    $db_pass = "S@utech2024!";
    $db_name = "clientzone";

include_once '../../config.php'; // Ensure this path is correct


$vatRow = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'vat_rate'")->fetch_assoc();
$vatRate = isset($vatRow['setting_value']) ? floatval($vatRow['setting_value']) : 15.0;

$typeRes = $conn->query("SELECT DISTINCT service_type FROM client_services ORDER BY service_type ASC");
$types = [];
while ($row = $typeRes->fetch_assoc()) {
    $types[] = $row['service_type'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client     = $_POST['client_name'];
    $type       = $_POST['service_type'];
    $name       = $_POST['service_name'];
    $specs      = $_POST['specs'];
    $status     = $_POST['status'];
    $currency   = $_POST['currency'];
    $quantity   = intval($_POST['quantity']);
    $billingType = $_POST['billing_type'];
    $debitDay   = $billingType === 'Debit Order' ? intval($_POST['debit_order_day']) : null;

    $start = (!empty($_POST['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['start_date'])) ? $_POST['start_date'] : null;
    $end   = (!empty($_POST['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['end_date'])) ? $_POST['end_date'] : null;

    $excludeVAT = isset($_POST['exclude_vat']) ? 1 : 0;
    $exVAT      = floatval($_POST['ex_vat_amount']);
    $vatAmount  = $excludeVAT ? round($exVAT * ($vatRate / 100), 2) : 0;
    $totalVAT   = $excludeVAT ? round($exVAT + $vatAmount, 2) : $exVAT;
    $totalVAT   = $totalVAT * max(1, $quantity);
    $vatAmount  = $vatAmount * max(1, $quantity);
    $invoice    = $_POST['invoice_link'];
    $notes      = $_POST['notes'];

    $sql = "INSERT INTO client_services
        (client_name, service_type, service_name, specs, status, currency, quantity, start_date, end_date, exclude_vat, ex_vat_amount, vat_amount, total_with_vat, invoice_link, notes, billing_type, debit_order_day, is_deleted)
        VALUES (?, ?, ?, ?, ?, ?, ?, " . ($start ? "?" : "NULL") . ", " . ($end ? "?" : "NULL") . ", ?, ?, ?, ?, ?, ?, ?, ?, 0)";

    $stmt = $conn->prepare($sql);

    $types = "sssssss" . ($start ? "s" : "") . ($end ? "s" : "") . "idddsss" . "i";
    $params = [
        &$client, &$type, &$name, &$specs, &$status, &$currency, &$quantity
    ];
    if ($start) $params[] = &$start;
    if ($end)   $params[] = &$end;
    $params[] = &$excludeVAT;
    $params[] = &$exVAT;
    $params[] = &$vatAmount;
    $params[] = &$totalVAT;
    $params[] = &$invoice;
    $params[] = &$notes;
    $params[] = &$billingType;
    $params[] = &$debitDay;

    $bind_names[] = $types;
    foreach ($params as $key => $value) {
        $bind_names[] = &$params[$key];
    }

    call_user_func_array([$stmt, 'bind_param'], $bind_names);

    if (!$stmt->execute()) {
        echo "<pre>Debug: \n";
        echo "SQL: " . $sql . "\n";
        echo "Start Date: ".$start."\n";
        echo "End Date: ".$end."\n";
        echo "Quantity: ".$quantity."\n";
        echo "exVAT: ".$exVAT." | vat: ".$vatAmount." | total: ".$totalVAT."\n";
        die("Error saving: " . $stmt->error . "</pre>");
    }

    header("Location: list.php");
    exit;
}
$clients = $conn->query("SELECT id, client_name FROM clients ORDER BY client_name ASC");
?>

<h2>Add Client Service</h2>

<form method="post">
    Client Name: <input type="text" name="client_name" required><br><br>

    Service Type:
    <input list="type-options" name="service_type" required>
    <datalist id="type-options">
        <?php foreach ($types as $t): ?>
            <option value="<?= htmlspecialchars($t) ?>">
        <?php endforeach; ?>
    </datalist><br><br>

    Service Name: <input type="text" name="service_name" required><br><br>

    Currency:
    <select name="currency">
        <option value="ZAR">ZAR</option>
        <option value="USD">USD</option>
    </select><br><br>

    Quantity: <input type="number" name="quantity" id="qty" value="1" min="1" required><br><br>

    Specs/Details: <textarea name="specs"></textarea><br><br>

    Status:
    <select name="status">
        <option>Active</option>
        <option>Suspended</option>
        <option>Cancelled</option>
    </select><br><br>

    Billing Type:
    <select name="billing_type" id="billing_type">
        <option value="Invoice">Invoice</option>
        <option value="Debit Order">Debit Order</option>
    </select><br><br>

    <div id="debit_day_container" style="display:none">
        Debit Order Run Day (1–31): <input type="number" name="debit_order_day" min="1" max="31"><br><br>
    </div>

    Start Date: <input type="date" name="start_date"><br>
    End Date: <input type="date" name="end_date"><br><br>

    <label>Ex VAT Amount:</label>
    <input type="number" step="0.01" name="ex_vat_amount" id="ex_vat" required><br><br>

    <label><input type="checkbox" name="exclude_vat" id="vat_toggle" checked> Apply VAT (<?= $vatRate ?>%)</label><br><br>

    <label>VAT Amount:</label>
    <span id="vat_display">0.00</span><br>
    <label>Total (incl. VAT):</label>
    <span id="total_display">0.00</span><br><br>

    Invoice Link: <input type="text" name="invoice_link"><br><br>
    Notes: <textarea name="notes"></textarea><br><br>

    <button type="submit">💾 Save</button>
</form>

<script>
function calcVAT() {
    let exVat = parseFloat(document.getElementById('ex_vat').value) || 0;
    let qty = parseInt(document.getElementById('qty').value) || 1;
    let applyVat = document.getElementById('vat_toggle').checked;
    let vatRate = <?= $vatRate ?>;

    let vatAmount = applyVat ? (exVat * vatRate / 100) : 0;
    let total = applyVat ? (exVat + vatAmount) : exVat;

    document.getElementById('vat_display').innerText = (vatAmount * qty).toFixed(2);
    document.getElementById('total_display').innerText = (total * qty).toFixed(2);
}

function toggleDebitField() {
    const billingType = document.getElementById('billing_type').value;
    document.getElementById('debit_day_container').style.display = billingType === 'Debit Order' ? 'block' : 'none';
}

document.getElementById('billing_type').addEventListener('change', toggleDebitField);
document.getElementById('ex_vat').addEventListener('input', calcVAT);
document.getElementById('qty').addEventListener('input', calcVAT);
document.getElementById('vat_toggle').addEventListener('change', calcVAT);

window.onload = function() {
    calcVAT();
    toggleDebitField();
};
</script>
