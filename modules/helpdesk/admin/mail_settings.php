<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
hd_require('admin mail');

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pop_host = trim($_POST['pop_host'] ?? '');
    $pop_port = (int) ($_POST['pop_port'] ?? 110);
    $pop_user = trim($_POST['pop_user'] ?? '');
    $pop_pass = $_POST['pop_password'] ?? '';
    $pop_ssl = !empty($_POST['pop_ssl']) ? 1 : 0;
    $smtp_host = trim($_POST['smtp_host'] ?? '');
    $smtp_port = (int) ($_POST['smtp_port'] ?? 587);
    $smtp_user = trim($_POST['smtp_user'] ?? '');
    $smtp_pass = $_POST['smtp_password'] ?? '';
    $smtp_ssl = !empty($_POST['smtp_ssl']) ? 1 : 0;
    $from_email = trim($_POST['smtp_from_email'] ?? '');
    $from_name = trim($_POST['smtp_from_name'] ?? 'Helpdesk');
    $rand_in = !empty($_POST['randomize_incoming']) ? 1 : 0;

    $pop_store = $pop_pass !== '' ? hd_encrypt_secret($pop_pass) : null;
    $smtp_store = $smtp_pass !== '' ? hd_encrypt_secret($smtp_pass) : null;

    if ($pop_store === null || $smtp_store === null) {
        $existing = hd_get_mail_config_row($conn);
        if ($pop_store === null) {
            $pop_store = $existing['pop_password'] ?? '';
        }
        if ($smtp_store === null) {
            $smtp_store = $existing['smtp_password'] ?? '';
        }
    }

    $stmt = $conn->prepare('UPDATE helpdesk_mail_config SET pop_host=?, pop_port=?, pop_user=?, pop_password=?, pop_ssl=?, smtp_host=?, smtp_port=?, smtp_user=?, smtp_password=?, smtp_ssl=?, smtp_from_email=?, smtp_from_name=?, randomize_incoming=? WHERE id=1');
    $stmt->bind_param(
        'sissisississi',
        $pop_host,
        $pop_port,
        $pop_user,
        $pop_store,
        $pop_ssl,
        $smtp_host,
        $smtp_port,
        $smtp_user,
        $smtp_store,
        $smtp_ssl,
        $from_email,
        $from_name,
        $rand_in
    );
    if ($stmt->execute()) {
        $msg = 'Saved.';
    } else {
        $msg = 'Save failed: ' . $conn->error;
    }
    $stmt->close();
}

$row = hd_get_mail_config_row($conn);
if (!$row) {
    $conn->query('INSERT INTO helpdesk_mail_config (id) VALUES (1)');
    $row = hd_get_mail_config_row($conn);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Helpdesk mail</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <?= hd_ui_css() ?>
</head>
<body class="p-4">
<div class="container" style="max-width:720px">
    <h3 class="hd-page-title mb-1">Mail settings</h3>
    <p class="hd-page-subtitle">Configure incoming POP and outgoing SMTP used by Helpdesk.</p>
    <?php if ($msg): ?><div class="alert alert-info"><?= hd_esc($msg) ?></div><?php endif; ?>
    <p class="text-muted">POP is used by the cron worker (PHP 7.4 + IMAP extension). Leave password blank to keep the current stored password.</p>
    <form method="post" class="card card-body hd-card">
        <h5 class="hd-section-title mb-2">Incoming (POP)</h5>
        <div class="mb-2"><label class="form-label">Host</label><input type="text" name="pop_host" class="form-control" value="<?= hd_esc($row['pop_host']) ?>"></div>
        <div class="mb-2"><label class="form-label">Port</label><input type="number" name="pop_port" class="form-control" value="<?= (int) $row['pop_port'] ?>"></div>
        <div class="mb-2"><label class="form-label">User</label><input type="text" name="pop_user" class="form-control" value="<?= hd_esc($row['pop_user']) ?>"></div>
        <div class="mb-2"><label class="form-label">Password</label><input type="password" name="pop_password" class="form-control" placeholder="(unchanged if empty)"></div>
        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="pop_ssl" value="1" id="pssl" <?= !empty($row['pop_ssl']) ? 'checked' : '' ?>><label class="form-check-label" for="pssl">POP SSL (e.g. port 995)</label></div>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="randomize_incoming" value="1" id="ri" <?= !empty($row['randomize_incoming']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="ri">Randomize technician on new email tickets (least open count)</label>
        </div>
        <h5 class="hd-section-title mb-2 mt-2">Outgoing (SMTP)</h5>
        <div class="mb-2"><label class="form-label">Host</label><input type="text" name="smtp_host" class="form-control" value="<?= hd_esc($row['smtp_host']) ?>"></div>
        <div class="mb-2"><label class="form-label">Port</label><input type="number" name="smtp_port" class="form-control" value="<?= (int) $row['smtp_port'] ?>"></div>
        <div class="mb-2"><label class="form-label">User</label><input type="text" name="smtp_user" class="form-control" value="<?= hd_esc($row['smtp_user']) ?>"></div>
        <div class="mb-2"><label class="form-label">Password</label><input type="password" name="smtp_password" class="form-control" placeholder="(unchanged if empty)"></div>
        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="smtp_ssl" value="1" id="sssl" <?= !empty($row['smtp_ssl']) ? 'checked' : '' ?>><label class="form-check-label" for="sssl">Use STARTTLS</label></div>
        <div class="mb-2"><label class="form-label">From email</label><input type="text" name="smtp_from_email" class="form-control" value="<?= hd_esc($row['smtp_from_email']) ?>"></div>
        <div class="mb-2"><label class="form-label">From name</label><input type="text" name="smtp_from_name" class="form-control" value="<?= hd_esc($row['smtp_from_name']) ?>"></div>
        <button type="submit" class="btn btn-primary">Save</button>
        <a href="../index.php" class="btn btn-secondary">Back</a>
    </form>
</div>
</body>
</html>
