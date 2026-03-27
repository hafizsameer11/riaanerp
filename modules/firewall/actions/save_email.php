<?php
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
require_once __DIR__ . '/../includes/functions.php';

$email = trim($_POST['email'] ?? '');

if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    // Check if email already exists
    $check = db()->prepare("SELECT id FROM notification_emails WHERE email = ?");
    $check->execute([$email]);
    
    if ($check->fetch()) {
        $_SESSION['error'] = "Email address already exists.";
    } else {
        // Insert with active=1 explicitly
        $stmt = db()->prepare("INSERT INTO notification_emails (email, active) VALUES (?, 1)");
        $stmt->execute([$email]);
        $_SESSION['success'] = "Email address added successfully.";
    }
} else {
    $_SESSION['error'] = "Invalid email address.";
}

header("Location: ../pages/manage_emails.php");
exit;

