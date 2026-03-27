<?php
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
require_once __DIR__ . '/../includes/functions.php';
$emails = db()->query("SELECT * FROM notification_emails ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Email Notifications - Firewall Monitor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            background: #f4f7fa;
            min-height: 100vh;
            padding: 20px;
            font-family: 'Inter', sans-serif;
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            margin-bottom: 24px;
        }
        .card-body {
            padding: 24px;
        }
        .table {
            margin-bottom: 0;
        }
        .table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #e5e7eb;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #374151;
            padding: 16px 12px;
        }
        .table tbody td {
            padding: 16px 12px;
            vertical-align: middle;
            border-bottom: 1px solid #f3f4f6;
        }
        .table tbody tr:hover {
            background-color: #f9fafb;
        }
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #6b7280;
        }
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        .form-control {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 14px;
        }
        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .btn {
            border-radius: 8px;
            font-weight: 500;
            padding: 10px 20px;
        }
        .badge {
            font-weight: 500;
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 6px;
        }
        h1.h3, h2.h5 {
            font-weight: 600;
            color: #111827;
        }
    </style>
</head>
<body>
    <div class="container-fluid px-4">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($_SESSION['error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> <?= htmlspecialchars($_SESSION['success']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <div class="card shadow-lg mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <h1 class="h3 mb-0">
                        <i class="bi bi-envelope text-primary"></i> Email Notifications
                    </h1>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Dashboard
                        </a>
                        <a href="../actions/logout.php" class="btn btn-outline-danger">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-lg mb-4">
            <div class="card-body p-4">
                <h2 class="h5 mb-3">
                    <i class="bi bi-plus-circle"></i> Add New Email
                </h2>
                <form action="../actions/save_email.php" method="post" class="row g-3">
                    <div class="col-md-10">
                        <input type="email" class="form-control" name="email" placeholder="Enter email address" required>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-plus"></i> Add
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-lg">
            <div class="card-body p-4">
                <?php if (empty($emails)): ?>
                    <div class="empty-state">
                        <i class="bi bi-envelope-x"></i>
                        <h3>No email addresses configured</h3>
                        <p class="text-muted">Add an email address to receive notifications</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Email Address</th>
                                    <th>Status</th>
                                    <th>Added</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($emails as $e): ?>
                                <tr>
                                    <td><strong><?=htmlspecialchars($e['email'])?></strong></td>
                                    <td>
                                        <?php if ($e['active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?=date('M d, Y', strtotime($e['created_at']))?></td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="../actions/test_email.php?id=<?=$e['id']?>" class="btn btn-info btn-sm" title="Send Test Email">
                                                <i class="bi bi-envelope-check"></i> Test
                                            </a>
                                            <a href="../actions/delete_email.php?id=<?=$e['id']?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this email?')" title="Delete Email">
                                                <i class="bi bi-trash"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

