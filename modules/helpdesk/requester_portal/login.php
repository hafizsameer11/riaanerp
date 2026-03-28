<?php
session_start();
require_once dirname(__DIR__, 3) . '/config.php';

if (isset($_SESSION['requester_register_id'])) {
    header('Location: index.php');
    exit;
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    $stmt = $conn->prepare('SELECT id, password, role_id FROM registers WHERE username = ? LIMIT 1');
    $stmt->bind_param('s', $user);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row && (int) $row['role_id'] === 100 && hash_equals((string) $row['password'], (string) $pass)) {
        $_SESSION['requester_register_id'] = (int) $row['id'];
        header('Location: index.php');
        exit;
    }
    $err = 'Invalid login.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Requester login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body{background:#eef3f9;font-family:Inter,Arial,sans-serif}
        .portal-card{border:0;border-radius:12px;box-shadow:0 8px 24px rgba(16,24,40,.08)}
    </style>
</head>
<body class="p-5">
<div class="container" style="max-width:400px">
    <h4 class="mb-1 fw-bold">Helpdesk requester portal</h4>
    <p class="text-muted mb-3">Sign in to view and track only your own support tickets.</p>
    <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <form method="post" class="card card-body portal-card">
        <div class="mb-2">
            <label class="form-label">Email</label>
            <input type="text" name="username" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button class="btn btn-primary w-100">Login</button>
    </form>
</div>
</body>
</html>
