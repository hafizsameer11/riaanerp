<?php
require_once __DIR__ . '/includes/bootstrap.php';
hd_require('requesters');

$err = '';
$flash = isset($_GET['saved']) ? 'Saved.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['delete_id'])) {
        $did = (int) $_POST['delete_id'];
        $conn->query('DELETE FROM helpdesk_requesters WHERE id = ' . $did);
        header('Location: requesters.php?saved=1');
        exit;
    }
    if (!empty($_POST['reset_register'])) {
        $rid = (int) $_POST['requester_id'];
        $newPass = bin2hex(random_bytes(4));
        $row = $conn->query('SELECT register_id, email, name FROM helpdesk_requesters WHERE id = ' . $rid)->fetch_assoc();
        if ($row && $row['register_id']) {
            $esc = $conn->real_escape_string($newPass);
            $conn->query('UPDATE registers SET password = \'' . $esc . '\' WHERE id = ' . (int) $row['register_id']);
            $cfg = hd_get_mail_config_row($conn);
            if ($cfg && $cfg['smtp_host'] && filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                require_once __DIR__ . '/includes/MailService.php';
                $loginUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['PHP_SELF']) . '/requester_portal/login.php';
                HelpdeskMailService::sendTemplate($conn, $cfg, $row['email'], 'requester_welcome', [
                    'name' => $row['name'],
                    'login_url' => $loginUrl,
                    'username' => $row['email'],
                    'password' => $newPass,
                ], 0, null, null);
            }
            header('Location: requesters.php?saved=1');
            exit;
        }
        $err = 'No login linked.';
    }
    if (!empty($_POST['save_requester'])) {
        $id = (int) ($_POST['id'] ?? 0);
        $client_id = (int) ($_POST['client_id'] ?? 0) ?: null;
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $work = trim($_POST['work_number'] ?? '');
        $mob = trim($_POST['mobile_number'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = 'Name and valid email required.';
        } else {
            if ($id > 0) {
                $cidSql = $client_id ? (int) $client_id : 'NULL';
                $nameEsc = $conn->real_escape_string($name);
                $emailEsc = $conn->real_escape_string($email);
                $workEsc = $conn->real_escape_string($work);
                $mobEsc = $conn->real_escape_string($mob);
                $conn->query("UPDATE helpdesk_requesters SET client_id = $cidSql, name = '$nameEsc', email = '$emailEsc', work_number = '$workEsc', mobile_number = '$mobEsc' WHERE id = $id");
                $rr = $conn->query('SELECT register_id FROM helpdesk_requesters WHERE id = ' . $id)->fetch_assoc();
                if ($rr && !empty($rr['register_id'])) {
                    $conn->query("UPDATE registers SET username = '$emailEsc', email = '$emailEsc', name = '$nameEsc' WHERE id = " . (int) $rr['register_id']);
                }
                header('Location: requesters.php?saved=1');
                exit;
            }
            $pass = bin2hex(random_bytes(4));
            $passEsc = $conn->real_escape_string($pass);
            $nameEsc = $conn->real_escape_string($name);
            $emailEsc = $conn->real_escape_string($email);
            $workEsc = $conn->real_escape_string($work);
            $mobEsc = $conn->real_escape_string($mob);
            $cidSql = $client_id ? (int) $client_id : 'NULL';
            $conn->query("INSERT INTO registers (username, password, name, surname, email, address, role_id) VALUES ('$emailEsc', '$passEsc', '$nameEsc', '', '$emailEsc', '-', 100)");
            $regId = (int) $conn->insert_id;
            $conn->query("INSERT INTO helpdesk_requesters (client_id, name, email, work_number, mobile_number, register_id) VALUES ($cidSql, '$nameEsc', '$emailEsc', '$workEsc', '$mobEsc', $regId)");
            $cfg = hd_get_mail_config_row($conn);
            if ($cfg && $cfg['smtp_host']) {
                require_once __DIR__ . '/includes/MailService.php';
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $loginUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/modules/helpdesk/requester_portal/login.php';
                HelpdeskMailService::sendTemplate($conn, $cfg, $email, 'requester_welcome', [
                    'name' => $name,
                    'login_url' => $loginUrl,
                    'username' => $email,
                    'password' => $pass,
                ], 0, null, null);
            }
            header('Location: requesters.php?saved=1');
            exit;
        }
    }
}

$clients = $conn->query('SELECT id, client_name FROM clients ORDER BY client_name');
$list = $conn->query('SELECT r.*, c.client_name FROM helpdesk_requesters r LEFT JOIN clients c ON c.id = r.client_id ORDER BY r.name');
$edit = null;
if (!empty($_GET['edit'])) {
    $eid = (int) $_GET['edit'];
    $edit = $conn->query('SELECT * FROM helpdesk_requesters WHERE id = ' . $eid)->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Requesters</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <?= hd_ui_css() ?>
</head>
<body class="py-4">
<div class="container-fluid hd-shell">
    <header class="hd-page-hero mb-3">
        <h3 class="hd-heading mb-1">Requesters</h3>
        <p class="text-muted small mb-0" style="max-width:36rem">Manage requester contacts and self-service portal accounts.</p>
    </header>
    <?php if ($err): ?><div class="alert alert-danger"><?= hd_esc($err) ?></div><?php endif; ?>
    <?php if ($flash): ?><div class="alert alert-success"><?= hd_esc($flash) ?></div><?php endif; ?>

    <div class="row">
        <div class="col-md-5">
            <div class="card hd-filter-card hd-card">
                <div class="card-header"><?= $edit ? 'Edit' : 'Add' ?> requester</div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="id" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
                        <div class="mb-3">
                            <label class="form-label">Client</label>
                            <select name="client_id" class="form-select">
                                <option value="">—</option>
                                <?php $clients->data_seek(0); while ($c = $clients->fetch_assoc()): ?>
                                    <option value="<?= (int) $c['id'] ?>" <?= $edit && (int) $edit['client_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= hd_esc($c['client_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" required value="<?= $edit ? hd_esc($edit['name']) : '' ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email (login)</label>
                            <input type="email" name="email" class="form-control" required value="<?= $edit ? hd_esc($edit['email']) : '' ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Work number</label>
                            <input type="text" name="work_number" class="form-control" value="<?= $edit ? hd_esc($edit['work_number'] ?? '') : '' ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mobile</label>
                            <input type="text" name="mobile_number" class="form-control" value="<?= $edit ? hd_esc($edit['mobile_number'] ?? '') : '' ?>">
                        </div>
                        <button type="submit" name="save_requester" value="1" class="btn btn-primary">Save</button>
                        <?php if ($edit): ?><a href="requesters.php" class="btn btn-outline-secondary">Cancel</a><?php endif; ?>
                    </form>
                    <?php if ($edit && $edit['register_id']): ?>
                    <form method="post" class="mt-3" onsubmit="return confirm('Reset password and email login?');">
                        <input type="hidden" name="requester_id" value="<?= (int) $edit['id'] ?>">
                        <button type="submit" name="reset_register" value="1" class="btn btn-outline-warning btn-sm">Reset password &amp; email</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-7">
            <div class="card hd-table-card">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2 py-3">
                    <span class="fw-semibold">Requester list</span>
                    <input type="text" id="rqSearch" class="form-control form-control-sm" style="max-width:260px" placeholder="Search name or email">
                </div>
                <div class="card-body p-0">
                    <div class="hd-table-wrap">
                        <table class="table table-striped table-hover hd-data-table mb-0" id="rqTable">
                            <thead><tr><th>Name</th><th>Email</th><th>Client</th><th class="text-end">Actions</th></tr></thead>
                            <tbody>
                            <?php while ($r = $list->fetch_assoc()): ?>
                                <tr>
                                    <td><?= hd_esc($r['name']) ?></td>
                                    <td><?= hd_esc($r['email']) ?></td>
                                    <td><?= hd_esc($r['client_name'] ?? '') ?></td>
                                    <td class="text-end text-nowrap">
                                        <a href="requesters.php?edit=<?= (int) $r['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Delete?');">
                                            <input type="hidden" name="delete_id" value="<?= (int) $r['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <a href="index.php" class="btn btn-outline-secondary px-3 mt-3">Back to dashboard</a>
</div>
<script>
document.getElementById('rqSearch')?.addEventListener('input', function () {
    var q = this.value.toLowerCase();
    var rows = document.querySelectorAll('#rqTable tbody tr');
    rows.forEach(function (tr) {
        tr.style.display = tr.textContent.toLowerCase().indexOf(q) >= 0 ? '' : 'none';
    });
});
</script>
</body>
</html>
