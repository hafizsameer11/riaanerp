<?php
$db_host = "localhost";
    $db_user = "clientzone_user";
    $db_pass = "S@utech2024!";
    $db_name = "clientzone";

include_once '../../config.php'; // Ensure this path is correct

$id = intval($_GET['id']);
$conn->query("UPDATE client_services SET is_deleted = 1 WHERE id = $id");
header("Location: list.php");

