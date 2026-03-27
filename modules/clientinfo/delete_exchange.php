<?php
include_once '../../config.php'; // Ensure this path is correct

$id = intval($_GET['id']);
$conn->query("DELETE FROM client_support WHERE id = $id");
echo "✅ Deleted";
?>
