<?php
require_once __DIR__ . '/includes/bootstrap.php';
hd_require('scheduled');

$err = '';
$flash = '';
$editId = isset($_GET['edit']) && ctype_digit((string) $_GET['edit']) ? (int) $_GET['edit'] : 0;
$editJob = null;
if ($editId > 0) {
    $editJob = $conn->query('SELECT * FROM helpdesk_scheduled_jobs WHERE id = ' . $editId)->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['delete_job'])) {
        $jid = (int) $_POST['delete_job'];
        $conn->query('DELETE FROM helpdesk_scheduled_jobs WHERE id = ' . $jid);
        header('Location: scheduled.php');
        exit;
    }
    if (!empty($_POST['log_again'])) {
        $jid = (int) $_POST['log_again'];
        $job = $conn->query('SELECT * FROM helpdesk_scheduled_jobs WHERE id = ' . $jid)->fetch_assoc();
        if ($job) {
            hd_create_ticket_from_scheduled_job($conn, $job);
            header('Location: index.php');
            exit;
        }
    }
    if (!empty($_POST['save_job'])) {
        $job_id = isset($_POST['job_id']) && ctype_digit((string) $_POST['job_id']) ? (int) $_POST['job_id'] : 0;
        $client_id = (int) ($_POST['client_id'] ?? 0) ?: null;
        $requester_id = (int) ($_POST['requester_id'] ?? 0) ?: null;
        $assigned = isset($_POST['assigned_user_id']) && $_POST['assigned_user_id'] !== '' ? (int) $_POST['assigned_user_id'] : null;
        $subject = trim($_POST['subject'] ?? '') ?: '(no subject)';
        $desc = trim($_POST['description'] ?? '');
        $stype = $_POST['schedule_type'] ?? 'once';
        if (!in_array($stype, ['daily', 'weekly', 'monthly', 'periodic', 'once'], true)) {
            $stype = 'once';
        }
        $interval = (int) ($_POST['schedule_interval_days'] ?? 1);
        $next = trim($_POST['next_run_at'] ?? '');
        if ($next === '') {
            $next = date('Y-m-d H:i:s');
        } else {
            $next = str_replace('T', ' ', $next);
            if (strlen($next) === 16) {
                $next .= ':00';
            }
        }
        if (!$requester_id) {
            $err = 'Requester required.';
        } else {
            $aidSql = $assigned !== null ? (int) $assigned : 'NULL';
            $cidSql = $client_id ? (int) $client_id : 'NULL';
            $subEsc = "'" . $conn->real_escape_string($subject) . "'";
            $descEsc = "'" . $conn->real_escape_string($desc) . "'";
            $stypeEsc = $conn->real_escape_string($stype);
            $nextEsc = "'" . $conn->real_escape_string($next) . "'";
            if ($job_id > 0) {
                $conn->query("UPDATE helpdesk_scheduled_jobs SET client_id=$cidSql, requester_id=" . (int) $requester_id . ", assigned_user_id=$aidSql, subject=$subEsc, description=$descEsc, schedule_type='$stypeEsc', schedule_interval_days=" . (int) $interval . ", next_run_at=$nextEsc WHERE id = $job_id");
                $savedJobId = $job_id;
            } else {
                $conn->query("INSERT INTO helpdesk_scheduled_jobs (client_id, requester_id, assigned_user_id, subject, description, schedule_type, schedule_interval_days, next_run_at, is_active) VALUES ($cidSql, " . (int) $requester_id . ", $aidSql, $subEsc, $descEsc, '$stypeEsc', " . (int) $interval . ", $nextEsc, 1)");
                $savedJobId = (int) $conn->insert_id;
            }

            if (hd_table_exists($conn, 'helpdesk_scheduled_attachments') && !empty($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
                if (!is_dir(HELPDESK_UPLOAD_DIR)) {
                    mkdir(HELPDESK_UPLOAD_DIR, 0755, true);
                }
                foreach ($_FILES['attachments']['name'] as $i => $nm) {
                    if (($_FILES['attachments']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                        continue;
                    }
                    $tmp = $_FILES['attachments']['tmp_name'][$i] ?? '';
                    if ($tmp === '' || !is_uploaded_file($tmp)) {
                        continue;
                    }
                    $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename((string) $nm));
                    $destName = 'sched_' . $savedJobId . '_' . time() . '_' . $safe;
                    $destAbs = HELPDESK_UPLOAD_DIR . '/' . $destName;
                    if (!move_uploaded_file($tmp, $destAbs)) {
                        continue;
                    }
                    $rel = 'uploads/' . $destName;
                    $sz = (int) filesize($destAbs);
                    $stmt = $conn->prepare('INSERT INTO helpdesk_scheduled_attachments (scheduled_job_id, filename, stored_path, size_bytes) VALUES (?, ?, ?, ?)');
                    $stmt->bind_param('issi', $savedJobId, $safe, $rel, $sz);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            header('Location: scheduled.php' . ($job_id > 0 ? '?edit=' . $job_id : ''));
            exit;
        }
    }
}

$jobs = $conn->query('SELECT j.*, c.client_name, r.name AS requester_name FROM helpdesk_scheduled_jobs j LEFT JOIN clients c ON c.id = j.client_id LEFT JOIN helpdesk_requesters r ON r.id = j.requester_id WHERE j.is_active = 1 ORDER BY j.next_run_at');
$clients = $conn->query('SELECT id, client_name FROM clients ORDER BY client_name');
$requesters = $conn->query('SELECT id, name, email FROM helpdesk_requesters ORDER BY name');
$techs = $conn->query('SELECT id, name, surname, username FROM registers WHERE (role_id IS NULL OR role_id <> 100) ORDER BY name');
$techList = [];
while ($row = $techs->fetch_assoc()) {
    $techList[] = $row;
}

$schedAttachments = [];
if ($editJob && hd_table_exists($conn, 'helpdesk_scheduled_attachments')) {
    $ars = $conn->query('SELECT id, filename, stored_path FROM helpdesk_scheduled_attachments WHERE scheduled_job_id = ' . (int) $editJob['id'] . ' ORDER BY id DESC');
    while ($a = $ars->fetch_assoc()) {
        $schedAttachments[] = $a;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Scheduled calls</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <?= hd_ui_css() ?>
</head>
<body class="py-4">
<div class="container-fluid hd-shell">
    <header class="hd-page-hero mb-3">
        <h3 class="hd-heading mb-1">Scheduled calls</h3>
        <p class="text-muted small mb-0" style="max-width:36rem">Create recurring tickets and control when they are logged automatically.</p>
    </header>
    <?php if ($err): ?><div class="alert alert-danger"><?= hd_esc($err) ?></div><?php endif; ?>
    <?php if ($flash): ?><div class="alert alert-success"><?= hd_esc($flash) ?></div><?php endif; ?>

    <div class="row">
        <div class="col-md-5">
            <div class="card hd-filter-card hd-card mb-4">
                <div class="card-header"><?= $editJob ? 'Edit scheduled call #' . (int) $editJob['id'] : 'Add scheduled call' ?></div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data">
                        <?php if ($editJob): ?>
                            <input type="hidden" name="job_id" value="<?= (int) $editJob['id'] ?>">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label">Client</label>
                            <select name="client_id" class="form-select">
                                <option value="">—</option>
                                <?php $clients->data_seek(0); while ($c = $clients->fetch_assoc()): ?>
                                    <option value="<?= (int) $c['id'] ?>" <?= $editJob && (int) $editJob['client_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= hd_esc($c['client_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Requester</label>
                            <select name="requester_id" class="form-select" required>
                                <option value="">—</option>
                                <?php $requesters->data_seek(0); while ($q = $requesters->fetch_assoc()): ?>
                                    <option value="<?= (int) $q['id'] ?>" <?= $editJob && (int) $editJob['requester_id'] === (int) $q['id'] ? 'selected' : '' ?>><?= hd_esc($q['name'] . ' — ' . $q['email']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Technician</label>
                            <select name="assigned_user_id" class="form-select">
                                <option value="">—</option>
                                <?php foreach ($techList as $u):
                                    $lbl = trim($u['name'] . ' ' . $u['surname']) ?: $u['username'];
                                    ?>
                                    <option value="<?= (int) $u['id'] ?>" <?= $editJob && (int) $editJob['assigned_user_id'] === (int) $u['id'] ? 'selected' : '' ?>><?= hd_esc($lbl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Subject</label>
                            <input type="text" name="subject" class="form-control" required value="<?= hd_esc($editJob['subject'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"><?= hd_esc($editJob['description'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">File / image upload</label>
                            <input type="file" name="attachments[]" class="form-control" multiple>
                            <?php if ($editJob && count($schedAttachments) > 0): ?>
                                <div class="small text-muted mt-1">Saved attachments: <?= (int) count($schedAttachments) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Schedule</label>
                            <select name="schedule_type" class="form-select">
                                <?php $st = $editJob['schedule_type'] ?? 'once'; ?>
                                <option value="once" <?= $st === 'once' ? 'selected' : '' ?>>One-time</option>
                                <option value="daily" <?= $st === 'daily' ? 'selected' : '' ?>>Daily</option>
                                <option value="weekly" <?= $st === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                                <option value="monthly" <?= $st === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                                <option value="periodic" <?= $st === 'periodic' ? 'selected' : '' ?>>Periodic (days)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Interval days (periodic)</label>
                            <input type="number" name="schedule_interval_days" class="form-control" value="<?= (int) ($editJob['schedule_interval_days'] ?? 1) ?>" min="1">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Next run</label>
                            <?php $nrv = $editJob ? substr(str_replace(' ', 'T', (string) $editJob['next_run_at']), 0, 16) : str_replace(' ', 'T', date('Y-m-d H:i')); ?>
                            <input type="datetime-local" name="next_run_at" class="form-control" value="<?= hd_esc($nrv) ?>">
                        </div>
                        <button type="submit" name="save_job" value="1" class="btn btn-primary"><?= $editJob ? 'Update Request' : 'Add Request' ?></button>
                        <?php if ($editJob): ?><a href="scheduled.php" class="btn btn-outline-secondary">Cancel edit</a><?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-7">
            <div class="card hd-table-card">
                <div class="card-header fw-semibold">Scheduled jobs</div>
                <div class="card-body p-0">
                    <div class="hd-table-wrap">
                        <table class="table table-striped table-hover hd-data-table mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Subject</th>
                                    <th>Requester</th>
                                    <th>Created</th>
                                    <th>Next run</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php while ($j = $jobs->fetch_assoc()): ?>
                                <tr>
                                    <td><?= (int) $j['id'] ?></td>
                                    <td><?= hd_esc($j['subject']) ?></td>
                                    <td><?= hd_esc($j['requester_name'] ?? '') ?></td>
                                    <td><?= hd_esc($j['created_at']) ?></td>
                                    <td><?= hd_esc($j['next_run_at']) ?></td>
                                    <td class="text-end text-nowrap">
                                        <a href="scheduled.php?edit=<?= (int) $j['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="log_again" value="<?= (int) $j['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-success">Log again</button>
                                        </form>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Delete job?');">
                                            <input type="hidden" name="delete_job" value="<?= (int) $j['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
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
</body>
</html>
