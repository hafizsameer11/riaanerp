<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database Connection
$db_host = "localhost";
    $db_user = "clientzone_user";
    $db_pass = "S@utech2024!";
    $db_name = "clientzone";

include_once '../../../config.php'; // Ensure this path is correct
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ➕ ADD Unit Price
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $service_category_id = (int)($_POST['service_category_id'] ?? 0);
    $item_name = $_POST['item_name'] ?? '';
    $unit_price = (float)($_POST['unit_price'] ?? 0);
    $vat_rate = (float)($_POST['vat_rate'] ?? 0);
    $currency = $_POST['currency'] ?? 'USD';

    $stmt = $conn->prepare("
        INSERT INTO billing_category_prices (service_category_id, item_name, unit_price, vat_rate, currency)
        VALUES (?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        echo "error_prepare";
        exit;
    }

    $stmt->bind_param("isdds", $service_category_id, $item_name, $unit_price, $vat_rate, $currency);

    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "error_execute";
    }
    exit;
}

// ✏️ EDIT Unit Price
if (isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = (int)($_POST['id'] ?? 0);
    $service_category_id = (int)($_POST['service_category_id'] ?? 0);
    $item_name = $_POST['item_name'] ?? '';
    $unit_price = (float)($_POST['unit_price'] ?? 0);
    $vat_rate = (float)($_POST['vat_rate'] ?? 0);
    $currency = $_POST['currency'] ?? 'USD';

    $stmt = $conn->prepare("
        UPDATE billing_category_prices 
        SET service_category_id = ?, item_name = ?, unit_price = ?, vat_rate = ?, currency = ?
        WHERE id = ?
    ");

    if (!$stmt) {
        echo "error_prepare";
        exit;
    }

    $stmt->bind_param("isddsi", $service_category_id, $item_name, $unit_price, $vat_rate, $currency, $id);

    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "error_execute";
    }
    exit;
}

// 📥 FETCH Single Unit Price (for Edit Modal)
if (isset($_POST['action']) && $_POST['action'] === 'fetch') {
    $id = (int)($_POST['id'] ?? 0);

    $stmt = $conn->prepare("SELECT * FROM billing_category_prices WHERE id = ?");
    if (!$stmt) {
        echo json_encode(["error" => "error_prepare"]);
        exit;
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $price = $result->fetch_assoc();

    echo json_encode($price);
    exit;
}

// ❌ DELETE Unit Price
if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = (int)($_POST['id'] ?? 0);

    $stmt = $conn->prepare("DELETE FROM billing_category_prices WHERE id = ?");
    if (!$stmt) {
        echo "error_prepare";
        exit;
    }

    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "error_execute";
    }
    exit;
}

?>
