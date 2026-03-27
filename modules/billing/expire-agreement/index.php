<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include_once '../../config.php'; // Ensure this path is correct

// Show agreements ending in the next 90 days
$today = date('Y-m-d');
$ninetyDaysFromNow = date('Y-m-d', strtotime('+90 days'));
$where = [];
if (!hasPermission('billing page', 'View all')) {
    $where[] = "b.created_by = " . (int) $_SESSION['user_id'];
}
$whereClause = '';
if (!empty($where)) {
    $whereClause = ' AND ' . implode(' AND ', $where);
}
$expiringQuery = $conn->query("
    SELECT b.*, c.client_name
    FROM billing_items b
    LEFT JOIN clients c ON c.id = b.client_id
    WHERE b.end_date IS NOT NULL
      AND b.end_date >= '$today'
      AND b.end_date <= '$ninetyDaysFromNow'
      $whereClause
    ORDER BY b.end_date ASC
");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Finance Management - Expiring Agreements</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>

<body style="background: #f9f9f9; padding: 20px;">

    <div class=" my-5 mt-5" style="width: 93%; margin: auto; ">
        <h4 class="mb-4 text-danger">⚠️ Expiring Billing Agreements (Next 90 Days)</h4>

        <?php if ($expiringQuery && $expiringQuery->num_rows > 0): ?>
            <table class="table table-hover table-bordered table-striped align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Client</th>
                        <!-- <th>Description</th> -->
                        <th>End Date</th>
                        <th>Frequency</th>
                        <th>IP Address</th>
                        <th>Qty</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1;
                    while ($row = $expiringQuery->fetch_assoc()): ?>
                        <tr>
                            <td class="text-center"><?= $i++ ?></td>
                            <td><?= htmlspecialchars($row['client_name']) ?></td>
                            <!-- <td><?= htmlspecialchars($row['description']) ?></td> -->
                            <td class="text-center text-danger">
                                <strong><?= date('d M Y', strtotime($row['end_date'])) ?></strong></td>
                            <td class="text-center"><?= ucfirst($row['frequency']) ?></td>
                            <td class="text-center"><?= $row['ip_address'] ?></td>
                            <td class="text-center"><?= $row['qty'] ?></td>
                            <td class="text-center">
                                <div class="btn-group" role="group" aria-label="Actions">
                                    <?php if (hasPermission('billing page', 'extend expired')): ?>
                                        <a href="javascript:void(0);" onclick="extendAgreement(<?= $row['id'] ?>)"
                                            class="btn btn-sm text-warning" title="Extend">
                                            <i class="fas fa-redo-alt"></i> <!-- Extend Icon -->
                                        </a>
                                    <?php endif; ?>
                                    <?php if (hasPermission('billing page', 'delete expired')): ?>
                                        <a href="javascript:void(0);" onclick="deleteAgreement(<?= $row['id'] ?>)"
                                            class="btn btn-sm text-danger" title="Delete">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>

                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-success">✅ No expiring agreements in the next 90 days.</div>
        <?php endif; ?>
    </div>
    <!-- Extend Agreement Modal -->
    <div class="modal fade" id="extendModal" tabindex="-1" aria-labelledby="extendModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form id="extendForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="extendModalLabel">Extend Billing Agreement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" name="billing_id" id="extend-billing-id">

                    <div class="mb-3">
                        <label for="new-end-date" class="form-label">New End Date</label>
                        <input type="date" name="new_end_date" id="new-end-date" class="form-control" required>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">Save Changes</button>
                </div>
            </form>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script>
        function extendAgreement(id) {
            // Set ID inside hidden input
            document.getElementById('extend-billing-id').value = id;
            document.getElementById('new-end-date').value = ''; // clear previous date

            // Show Modal
            var extendModal = new bootstrap.Modal(document.getElementById('extendModal'));
            extendModal.show();
        }

        // Handle Extend Form Submit
        document.getElementById('extendForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'extend_manual');

            axios.post('expire-agreement/backend.php', formData)
                .then(res => {
                    if (res.data.trim() === 'success') {
                        alert('✅ Agreement extended.');
                        location.reload();
                    } else {
                        alert('❌ Error: ' + res.data);
                    }
                })
                .catch(err => {
                    alert('❌ Server Error');
                    console.error(err);
                });
        });

        function deleteAgreement(id) {
            if (confirm('Are you sure you want to delete this billing agreement?')) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);

                axios.post('expire-agreement/backend.php', formData)
                    .then(res => {
                        if (res.data.trim() === 'success') {
                            alert('✅ Agreement deleted.');
                            location.reload();
                        } else {
                            alert('❌ Error: ' + res.data);
                        }
                    })
                    .catch(err => {
                        alert('❌ Server Error');
                        console.error(err);
                    });
            }
        }
    </script>

</body>

</html>