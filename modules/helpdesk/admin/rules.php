<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
hd_require('admin mail');

$msg = '';

$events = [
    'ticket_created' => 'Ticket Created',
    'ticket_status_changed' => 'Status Changed',
    'ticket_on_hold' => 'Set On Hold',
    'ticket_closed' => 'Closed',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_rule'])) {
        $event = trim($_POST['event_code'] ?? '');
        $templateId = (int) ($_POST['template_id'] ?? 0);
        $active = !empty($_POST['is_active']) ? 1 : 0;
        if (isset($events[$event]) && $templateId > 0) {
            $stmt = $conn->prepare('INSERT INTO helpdesk_notification_rules (event_code, template_id, is_active) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE template_id = VALUES(template_id), is_active = VALUES(is_active)');
            $stmt->bind_param('sii', $event, $templateId, $active);
            $stmt->execute();
            $stmt->close();
            $msg = 'Rule saved.';
        }
    } elseif (isset($_POST['delete_rule'])) {
        $id = (int) $_POST['delete_rule'];
        $conn->query('DELETE FROM helpdesk_notification_rules WHERE id = ' . $id);
        $msg = 'Rule deleted.';
    }
}

$templates = $conn->query('SELECT id, name, code FROM helpdesk_email_templates ORDER BY name');
$templateList = [];
while ($t = $templates->fetch_assoc()) {
    $templateList[] = $t;
}
$rules = $conn->query('SELECT r.*, t.name AS template_name, t.code AS template_code FROM helpdesk_notification_rules r LEFT JOIN helpdesk_email_templates t ON t.id = r.template_id ORDER BY r.event_code');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Notification rules</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <?= hd_ui_css() ?>
</head>
<body class="p-4">
<div class="container" style="max-width: 860px;">
    <h3 class="hd-page-title mb-1">Notification rules</h3>
    <p class="hd-page-subtitle">Map Helpdesk events to templates and control whether each notification is active.</p>
    <?php if ($msg): ?><div class="alert alert-info"><?= hd_esc($msg) ?></div><?php endif; ?>

    <form method="post" class="card card-body hd-card mb-4">
        <div class="row g-2">
            <div class="col-md-4">
                <label class="form-label">Event</label>
                <select name="event_code" class="form-select" required>
                    <option value="">Select</option>
                    <?php foreach ($events as $code => $label): ?>
                        <option value="<?= hd_esc($code) ?>"><?= hd_esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Template</label>
                <select name="template_id" class="form-select" required>
                    <option value="">Select</option>
                    <?php foreach ($templateList as $tpl): ?>
                        <option value="<?= (int) $tpl['id'] ?>"><?= hd_esc($tpl['name']) ?> (<?= hd_esc($tpl['code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" name="is_active" value="1" checked>
                    <label class="form-check-label">Active</label>
                </div>
            </div>
        </div>
        <div class="mt-3">
            <button class="btn btn-primary" name="save_rule" value="1">Save rule</button>
            <a class="btn btn-secondary" href="../index.php">Back</a>
        </div>
    </form>

    <table class="table table-striped bg-white shadow-sm">
        <thead><tr><th>Event</th><th>Template</th><th>Active</th><th></th></tr></thead>
        <tbody>
        <?php while ($r = $rules->fetch_assoc()): ?>
            <tr>
                <td><?= hd_esc($r['event_code']) ?></td>
                <td><?= hd_esc($r['template_name'] ?: $r['template_code']) ?></td>
                <td><?= !empty($r['is_active']) ? 'Yes' : 'No' ?></td>
                <td>
                    <form method="post" class="d-inline" onsubmit="return confirm('Delete this rule?');">
                        <button class="btn btn-sm btn-outline-danger" name="delete_rule" value="<?= (int) $r['id'] ?>">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
</body>
</html>

