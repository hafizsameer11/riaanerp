<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
hd_require('admin mail');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['save_tpl'])) {
    $id = (int) ($_POST['id'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $body = $_POST['body_html'] ?? '';
    if ($id > 0 && $subject !== '') {
        $stmt = $conn->prepare('UPDATE helpdesk_email_templates SET subject = ?, body_html = ? WHERE id = ?');
        $stmt->bind_param('ssi', $subject, $body, $id);
        $stmt->execute();
        $stmt->close();
    }
}

$tpls = $conn->query('SELECT * FROM helpdesk_email_templates ORDER BY name');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email templates</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <?= hd_ui_css() ?>
</head>
<body class="p-4">
<div class="container-fluid">
    <h3 class="hd-page-title mb-1">Email templates</h3>
    <p class="hd-page-subtitle mb-3">Manage outgoing HTML email template subjects and bodies.</p>
    <p class="text-muted">Placeholders: {{ticket_id}}, {{subject}}, {{requester_name}}, {{name}}, {{username}}, {{password}}, {{login_url}}, {{body}}</p>
    <?php while ($t = $tpls->fetch_assoc()): ?>
        <div class="card mb-4 hd-card">
            <div class="card-header"><?= hd_esc($t['name']) ?> <code><?= hd_esc($t['code']) ?></code></div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                    <div class="mb-2">
                        <label class="form-label">Subject</label>
                        <input type="text" name="subject" class="form-control" value="<?= hd_esc($t['subject']) ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">HTML body</label>
                        <textarea name="body_html" class="form-control" rows="8"><?= hd_esc($t['body_html']) ?></textarea>
                    </div>
                    <button type="submit" name="save_tpl" value="1" class="btn btn-primary">Save</button>
                </form>
            </div>
        </div>
    <?php endwhile; ?>
    <a href="../index.php" class="btn btn-secondary">Back</a>
</div>
</body>
</html>
