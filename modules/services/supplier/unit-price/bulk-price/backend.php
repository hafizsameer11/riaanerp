<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database Connection
$db_host = "localhost";
    $db_user = "clientzone_user";
    $db_pass = "S@utech2024!";
    $db_name = "clientzone";

include_once '../../../../config.php'; // Ensure this path is correct
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_POST['action']) && $_POST['action'] === 'bulk_increase') {
    $percentage = (float)($_POST['percentage'] ?? 0);
    $service_category_id = isset($_POST['service_category_id']) ? (int)$_POST['service_category_id'] : null;

    if ($percentage <= 0) {
        echo "error_invalid_percentage";
        exit;
    }

    $factor = 1 + ($percentage / 100);

    if (!empty($service_category_id)) {
        $stmt = $conn->prepare("UPDATE billing_category_prices SET unit_price = unit_price * ? WHERE service_category_id = ?");
        if (!$stmt) {
            echo "error_prepare";
            exit;
        }
        $stmt->bind_param("di", $factor, $service_category_id);
        $execute = $stmt->execute();

        if ($execute) {
            if ($stmt->affected_rows > 0) {
                echo "success";
            } else {
                echo "no_rows_updated";
            }
        } else {
            echo "error_execute";
        }
    } else {
        $query = "UPDATE billing_category_prices SET unit_price = unit_price * {$factor}";
        $execute = $conn->query($query);

        if ($execute) {
            if ($conn->affected_rows > 0) {
                echo "success";
            } else {
                echo "no_rows_updated";
            }
        } else {
            echo "error_execute";
        }
    }
    exit;
}

?>
