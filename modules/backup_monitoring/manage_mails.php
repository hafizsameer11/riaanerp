<?php
session_start();
include_once '../../config.php';
include_once '../components/permissioncheck.php';

// Check if user has permission to view backup monitoring
if (!hasPermission('billing', 'backup_monitoring')) {
    die('Access denied. You do not have permission to view this page.');
}

$hasAccess = hasPermission('billing', 'backup_monitoring');
$hasCreate = $hasAccess;
$hasEdit = $hasAccess;
$hasDelete = $hasAccess;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Backup Mails - SAU Technologies</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .table thead th {
            background-color: #667eea;
            color: white;
            border: none;
        }
        .badge {
            padding: 0.5em 0.75em;
            font-size: 0.875em;
        }
        .btn-action {
            margin: 0 2px;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-0"><i class="fas fa-envelope me-2"></i>Manage Backup Mails</h2>
                    <p class="mb-0 mt-2">Configure email accounts for backup monitoring</p>
                </div>
                <div>
                    <a href="index.php" class="btn btn-light">
                        <i class="fas fa-arrow-left me-1"></i>Back to Monitoring
                    </a>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <?php if ($hasCreate): ?>
                    <button type="button" class="btn btn-primary" onclick="openAdd()">
                        <i class="fas fa-plus me-1"></i>Add Backup Mail
                    </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-success" onclick="fetchMails()">
                        <i class="fas fa-sync me-1"></i>Refresh
                    </button>
                </div>
            </div>
        </div>

        <!-- Mails Table -->
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Type</th>
                                <th>Email Address</th>
                                <th>IMAP Host</th>
                                <th>Status</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="mailsTable">
                            <tr>
                                <td colspan="6" class="text-center">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" id="addForm">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add Backup Mail</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Mail Type <span class="text-danger">*</span></label>
                        <select name="mail_type" id="add_mail_type" class="form-select" required>
                            <option value="" disabled selected>Select Type</option>
                            <option value="CLIENT">CLIENT</option>
                            <option value="VEEAM">VEEAM</option>
                            <option value="VEEAMCLOUD">VEEAMCLOUD</option>
                            <option value="NAS">NAS</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email_address" id="add_email_address" class="form-control" placeholder="example@domain.com" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mailbox Password <span class="text-danger">*</span></label>
                        <input type="password" name="app_password" id="add_app_password" class="form-control" placeholder="Enter Mailbox Password" required>
                        <small class="form-text text-muted">Mailbox Password (not your regular password)</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">IMAP Host</label>
                        <input type="text" name="imap_host" id="add_imap_host" class="form-control" value="{imap.gmail.com:993/imap/ssl}INBOX" placeholder="{imap.gmail.com:993/imap/ssl}INBOX">
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="add_is_active" value="1" checked>
                        <label class="form-check-label" for="add_is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i>Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" id="editForm">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Backup Mail</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3">
                        <label class="form-label">Mail Type <span class="text-danger">*</span></label>
                        <select name="mail_type" id="edit_mail_type" class="form-select" required>
                            <option value="CLIENT">CLIENT</option>
                            <option value="VEEAM">VEEAM</option>
                            <option value="VEEAMCLOUD">VEEAMCLOUD</option>
                            <option value="NAS">NAS</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email_address" id="edit_email_address" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mailbox Password <span class="text-danger">*</span></label>
                        <input type="password" name="app_password" id="edit_app_password" class="form-control" placeholder="Leave empty to keep current password">
                        <small class="form-text text-muted">Leave empty to keep current password, or enter new password</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">IMAP Host</label>
                        <input type="text" name="imap_host" id="edit_imap_host" class="form-control">
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active" value="1">
                        <label class="form-check-label" for="edit_is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Update</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" id="deleteForm">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Delete Backup Mail</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this backup mail configuration? This action cannot be undone.</p>
                    <input type="hidden" name="id" id="delete_id">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash me-1"></i>Delete</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const hasCreate = <?php echo $hasCreate ? 'true' : 'false'; ?>;
        const hasEdit = <?php echo $hasEdit ? 'true' : 'false'; ?>;
        const hasDelete = <?php echo $hasDelete ? 'true' : 'false'; ?>;

        function fetchMails() {
            $.post("manage_mails_backend.php", { action: "fetch" }, function(html) {
                $("#mailsTable").html(html);
            }).fail(function() {
                $("#mailsTable").html("<tr><td colspan='6' class='text-center text-danger'>Error loading data</td></tr>");
            });
        }

        function openAdd() {
            if (!hasCreate) {
                alert('You do not have permission to create');
                return;
            }
            $('#addForm')[0].reset();
            $('#add_is_active').prop('checked', true);
            new bootstrap.Modal(document.getElementById('addModal')).show();
        }

        function openEdit(id) {
            if (!hasEdit) {
                alert('You do not have permission to edit');
                return;
            }
            $.post("manage_mails_backend.php", { action: "get", id: id }, function(res) {
                $('#edit_id').val(res.id);
                $('#edit_mail_type').val(res.mail_type);
                $('#edit_email_address').val(res.email_address);
                $('#edit_app_password').val(''); // Don't show password
                $('#edit_imap_host').val(res.imap_host);
                $('#edit_is_active').prop('checked', (res.is_active == 1));
                new bootstrap.Modal(document.getElementById('editModal')).show();
            }, "json").fail(function() {
                alert('Error loading mail details');
            });
        }

        function openDelete(id) {
            if (!hasDelete) {
                alert('You do not have permission to delete');
                return;
            }
            $("#delete_id").val(id);
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        // Form submissions
        $('#addForm').on('submit', function(e) {
            e.preventDefault();
            const data = $(this).serialize() + '&action=add';
            $.post('manage_mails_backend.php', data, function(res) {
                if (res.error) {
                    alert(res.error);
                } else {
                    bootstrap.Modal.getInstance(document.getElementById('addModal')).hide();
                    fetchMails();
                }
            }, "json");
        });

        $('#editForm').on('submit', function(e) {
            e.preventDefault();
            const data = $(this).serialize() + '&action=edit';
            $.post('manage_mails_backend.php', data, function(res) {
                if (res.error) {
                    alert(res.error);
                } else {
                    bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
                    fetchMails();
                }
            }, "json");
        });

        $('#deleteForm').on('submit', function(e) {
            e.preventDefault();
            const data = $(this).serialize() + '&action=delete';
            $.post('manage_mails_backend.php', data, function(res) {
                if (res.error) {
                    alert(res.error);
                } else {
                    bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
                    fetchMails();
                }
            }, "json");
        });

        // Initialize
        fetchMails();
    </script>
</body>
</html>
