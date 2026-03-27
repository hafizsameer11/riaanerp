<?php
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
require_once __DIR__ . '/../includes/functions.php';
$id = $_GET['id'] ?? null;
if ($id) {
    $stmt = db()->prepare("DELETE FROM sites WHERE id=?");
    $stmt->execute([$id]);
}
header("Location: ../pages/index.php");

