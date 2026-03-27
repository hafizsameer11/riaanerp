<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$db_host = "localhost";
    $db_user = "clientzone_user";
    $db_pass = "S@utech2024!";
    $db_name = "clientzone";

include_once '../../../config.php'; // Ensure this path is correct

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = $_POST['category_id'];
    $price_updates = $_POST['price_updates'];

    foreach ($price_updates as $update) {
        $item_id = $update['item_id'];
        $new_price = $update['new_price'];

        // Update unit price in billing_items table
        $stmt = $conn->prepare("UPDATE billing_items SET unit_price = ? WHERE id = ? AND service_category_id = ?");
        $stmt->bind_param('dii', $new_price, $item_id, $category_id);
        if (!$stmt->execute()) {
            echo 'error';
            exit;
        }
    }

    echo 'success';
}
?>
