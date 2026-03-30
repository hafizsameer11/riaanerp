<?php
session_start();
include_once '../../config.php';
include_once '../components/permissioncheck.php';

// Check if user has permission to view backup monitoring
if (!hasPermission('billing', 'backup_monitoring')) {
    die('Access denied. You do not have permission to view this page.');
}

$clients = $conn->query("SELECT id, client_name, contact_person FROM clients ORDER BY client_name ASC");

// For now, if user has access to backup_monitoring, they can do all actions
// You can add granular permissions later if needed
$hasAccess = hasPermission('billing', 'backup_monitoring');
$hasCreate = $hasAccess; // Can be changed to hasPermission('backup_monitoring', 'create') if granular permissions are set up
$hasEdit = $hasAccess;   // Can be changed to hasPermission('backup_monitoring', 'edit') if granular permissions are set up
$hasDelete = $hasAccess; // Can be changed to hasPermission('backup_monitoring', 'delete') if granular permissions are set up
$hasExport = $hasAccess; // Can be changed to hasPermission('backup_monitoring', 'export') if granular permissions are set up
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup Monitoring - SAU Technologies</title>
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
        .nav-tabs {
            display: inline-flex !important;
        }
        .nav-tabs .nav-link {
            color: #495057;
            font-weight: 500;
            border: none;
            border-bottom: 3px solid transparent;
            padding: 0.5rem 0.75rem;
            transition: all 0.3s ease;
        }
        .nav-tabs .nav-link:hover {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        .nav-tabs .nav-link.active {
            color: #667eea;
            border-bottom: 3px solid #667eea;
            background-color: transparent;
        }
        .action-buttons {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        .filter-section {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
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
    <div class="container-fluid py-4 px-5">
        <!-- Page Title -->
        <div class="mb-4">
            <h2 class="mb-0 d-flex align-items-center">
                <i class="fas fa-database me-2"></i>
                <span class="fw-semibold text-dark">Backup Monitoring</span>
            </h2>
        </div>

        <!-- Main Content -->
        <div>
                <!-- Action Buttons -->
                <div class="action-buttons mb-3">
                    <div class="d-flex flex-wrap align-items-center gap-2 justify-content-between w-100">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <?php if ($hasCreate): ?>
                            <button type="button" class="btn btn-primary" id="btnAddJob" onclick="openAdd()">
                                <i class="fas fa-plus me-1"></i>Add Backup Job
                            </button>
                            <?php endif; ?>
                            
                            <?php if ($hasExport): ?>
                            <button type="button" class="btn btn-success" id="btnExport" onclick="openExportModal()">
                                <i class="fas fa-file-excel me-1"></i>Export to Excel
                            </button>
                            <button type="button" class="btn btn-info" onclick="openReportsModal()">
                                <i class="fas fa-chart-bar me-1"></i>Reports
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="window.location.href='manage_mails.php'">
                                <i class="fas fa-envelope me-1"></i>Manage Backup Mails
                            </button>
                            <?php endif; ?>
                            
                            <!-- <button type="button" class="btn btn-warning" id="btnParseEmails" onclick="parseEmails()" title="Parse emails for current tab">
                                <i class="fas fa-sync me-1"></i>Parse Emails
                            </button> -->
                        </div>
                        <!-- <div class="text-muted small" id="lastParseTime" style="display: none;">
                            <i class="fas fa-clock me-1"></i>Last parsed: <span id="lastParseTimestamp">Never</span>
                        </div> -->
                    </div>
                </div>
        
        <!-- Parsing Status (shown during parsing) -->
        <div id="parseStatus" style="display: none;"></div>

        <!-- Filter Section -->
        <div class="card shadow-sm p-4 mb-4">
            <form id="filterForm" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="startDate">
                </div>
                <div class="col-md-2">
                    <label class="form-label">End Date</label>
                    <input type="date" class="form-control" id="endDate">
                </div>
                <div class="col-md-2" id="statusFilterContainer">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="Success">Success</option>
                        <option value="Warning">Warning</option>
                        <option value="Failed">Failed</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" id="searchText" placeholder="Search client, contact, device, status">
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-primary w-100" onclick="fetchJobs()">
                        <i class="fas fa-filter me-1"></i>Filter
                    </button>
                </div>
                <div class="col-md-12">
                    <button type="button" class="btn btn-outline-secondary" onclick="resetFilters()">
                        <i class="fas fa-redo me-1"></i>Reset Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Tab Content -->
        <div class="card shadow-sm">
            <div class="card-body">
                <div id="tabContent">Loading...</div>
            </div>
        </div>
    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" id="addForm">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add Backup Job</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Backup Source <span class="text-danger">*</span></label>
                        <select name="backup_source" id="add_backup_source" class="form-select" required>
                            <option value="CLIENT">CLIENT</option>
                            <option value="VEEAM">VEEAM</option>
                            <option value="VEEAMCLOUD">VEEAMCLOUD</option>
                            <option value="NAS">NAS</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Client <span class="text-danger">*</span></label>
                        <select name="client_id" id="add_client_id" class="form-select" required>
                            <option value="" disabled selected>Select Client</option>
                            <?php
                            $clients->data_seek(0);
                            while ($c = $clients->fetch_assoc()) {
                                echo "<option value='" . (int)$c['id'] . "' data-contact='" . htmlspecialchars($c['contact_person'], ENT_QUOTES) . "'>" . htmlspecialchars($c['client_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Person <span class="text-danger">*</span></label>
                        <input type="text" name="contact_name" id="add_contact_name" class="form-control" placeholder="Contact Person" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Device Type (PC/Server Name) <span class="text-danger">*</span></label>
                        <input type="text" name="device_type" class="form-control" placeholder="Example: Lungile-PC" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Retention Days <span class="text-danger">*</span></label>
                        <input type="number" name="retention_days" value="30" class="form-control" min="1" required>
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
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Backup Job</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3">
                        <label class="form-label">Client <span class="text-danger">*</span></label>
                        <select name="client_id" id="edit_client_id" class="form-select" required>
                            <option value="" disabled>Select Client</option>
                            <?php
                            $clients->data_seek(0);
                            while ($c = $clients->fetch_assoc()) {
                                echo "<option value='" . (int)$c['id'] . "'>" . htmlspecialchars($c['client_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Person <span class="text-danger">*</span></label>
                        <input type="text" name="contact_name" id="edit_contact_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Device Type <span class="text-danger">*</span></label>
                        <input type="text" name="device_type" id="edit_device_type" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Retention Days <span class="text-danger">*</span></label>
                        <input type="number" name="retention_days" id="edit_retention_days" class="form-control" min="1" required>
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
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Delete Backup Job</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this backup job? This action cannot be undone.</p>
                    <input type="hidden" name="id" id="delete_id">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash me-1"></i>Delete</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Create Job from Undefined Modal -->
    <div class="modal fade" id="createFromUndefinedModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" id="createFromUndefinedForm">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Create Job from Undefined Mail</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="undefined_mail_id" id="create_undefined_id">
                    <div class="alert alert-info">
                        <strong>Device Type:</strong> <span id="create_device_type_display"></span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Backup Source <span class="text-danger">*</span></label>
                        <select name="backup_source" id="create_backup_source" class="form-select" required>
                            <option value="" disabled selected>Select Backup Source</option>
                            <option value="CLIENT">CLIENT</option>
                            <option value="VEEAM">VEEAM</option>
                            <option value="VEEAMCLOUD">VEEAMCLOUD</option>
                            <option value="NAS">NAS</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Device Type <span class="text-danger">*</span></label>
                        <input type="text" name="device_type" id="create_device_type" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Client <span class="text-danger">*</span></label>
                        <select name="client_id" id="create_client_id" class="form-select" required>
                            <option value="" disabled selected>Select Client</option>
                            <?php
                            $clients->data_seek(0);
                            while ($c = $clients->fetch_assoc()) {
                                echo "<option value='" . (int)$c['id'] . "' data-contact='" . htmlspecialchars($c['contact_person'], ENT_QUOTES) . "'>" . htmlspecialchars($c['client_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Person <span class="text-danger">*</span></label>
                        <input type="text" name="contact_person" id="create_contact_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Retention Days <span class="text-danger">*</span></label>
                        <input type="number" name="days" value="30" class="form-control" min="1" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i>Create Job</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Details Modal -->
    <div class="modal fade" id="viewDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="fas fa-eye me-2"></i>Backup Job Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="viewDetailsContent">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading details...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Export Email Modal -->
    <div class="modal fade" id="exportEmailModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-file-excel me-2"></i>Export to Excel</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Email Address (Optional)</label>
                        <input type="email" class="form-control" id="exportEmail" placeholder="Enter email to send report, or leave empty to download">
                        <small class="form-text text-muted">If email is provided, the Excel file will be sent via email. Otherwise, it will be downloaded.</small>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Export Details:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Tab: <span id="exportTabName"></span></li>
                            <li>Records will include current filters (date range, search)</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="exportExcelDownload()">
                        <i class="fas fa-download me-1"></i>Download Only
                    </button>
                    <button type="button" class="btn btn-success" onclick="exportExcelWithEmail()">
                        <i class="fas fa-envelope me-1"></i>Send via Email
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reports Modal -->
    <div class="modal fade" id="reportsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="fas fa-chart-bar me-2"></i>Backup Reports</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Client</label>
                        <select id="report_client_id" class="form-select">
                            <option value="">All Clients</option>
                            <?php
                            $clients->data_seek(0);
                            while ($c = $clients->fetch_assoc()) {
                                echo "<option value='" . (int)$c['id'] . "'>" . htmlspecialchars($c['client_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Device Type</label>
                        <input type="text" id="report_device_type" class="form-control" placeholder="Leave empty for all">
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Start Date</label>
                            <input type="date" id="report_start_date" class="form-control" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Date</label>
                            <input type="date" id="report_end_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Source</label>
                        <select id="report_source" class="form-select">
                            <option value="">All Sources</option>
                            <option value="CLIENT">Client</option>
                            <option value="VEEAM">Veeam</option>
                            <option value="VEEAMCLOUD">VeeamCloud</option>
                            <option value="NAS">NAS</option>
                        </select>
                    </div>
                    <div id="reportResults" class="mt-3"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="generateReport()">
                        <i class="fas fa-search me-1"></i>Generate Report
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentType = 'CLIENT';
        const hasCreate = <?php echo $hasCreate ? 'true' : 'false'; ?>;
        const hasEdit = <?php echo $hasEdit ? 'true' : 'false'; ?>;
        const hasDelete = <?php echo $hasDelete ? 'true' : 'false'; ?>;
        const hasExport = <?php echo $hasExport ? 'true' : 'false'; ?>;
        let isParsing = false; // Flag to prevent multiple simultaneous parsing calls
        let currentViewJobId = null;

        // Helper function to remove SPAM prefix from email subjects
        function cleanEmailSubject(subject) {
            if (!subject) return 'N/A';
            return subject.replace(/^\*\*\*SPAM\*\*\*\s*/, '').trim();
        }

        function loadTab(file, type) {
            currentType = type;
            
            // Control Add Job button and label based on current tab
            if (hasCreate) {
                if (currentType === 'UNDEFINED') {
                    $('#btnAddJob').show().html('<i class="fas fa-plus me-1"></i>Add New Job');
                } else {
                    $('#btnAddJob').show().html('<i class="fas fa-plus me-1"></i>Add Backup Job');
                }
            }
            
            // Show status filter for all tabs
            $('#statusFilterContainer').show();
            
            // Parse Emails button not applicable for UNDEFINED
            if (currentType === 'UNDEFINED') {
                $('#btnParseEmails').hide();
            } else {
                $('#btnParseEmails').show();
            }

            $("#tabContent").load("tabs/" + file, function() {
                // Automatically parse emails when tab is opened (only for backup source tabs)
                if (currentType !== 'UNDEFINED') {
                    console.log('Tab loaded, auto-parsing emails for:', currentType);
                    // Small delay to ensure tab content is fully loaded
                    setTimeout(function() {
                        parseEmails(true); // true = silent mode (no alert on success)
                    }, 100);
                } else {
                    fetchJobs();
                }
            });
        }
        
        function parseEmails(silent = false) {
            // Prevent multiple simultaneous calls
            if (isParsing) {
                console.log('Parsing already in progress, skipping...');
                return;
            }
            
            if (currentType === 'UNDEFINED') {
                if (!silent) {
                    alert('Email parsing is not available for Undefined Mails tab');
                }
                return;
            }
            
            console.log('parseEmails called for:', currentType, 'silent:', silent);
            
            // Set parsing flag
            isParsing = true;
            
            // Show loading state
            const btn = $('#btnParseEmails');
            const originalHtml = btn.html();
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Parsing...');
            
            // Show parsing status
            let statusDiv = $('#parseStatus');
            if (statusDiv.length === 0) {
                // Create status div if it doesn't exist
                statusDiv = $('<div id="parseStatus" class="alert alert-info mt-2"></div>');
                $('.action-buttons').after(statusDiv);
            }
            
            // Show status (always show, but hide quickly in silent mode)
            statusDiv.removeClass('alert-success alert-warning alert-danger').addClass('alert-info');
            statusDiv.html('<i class="fas fa-spinner fa-spin me-1"></i>Parsing emails... Please wait.').show();
            
            $.ajax({
                url: "backend.php",
                type: "POST",
                data: {
                    action: 'parse_emails',
                    source: currentType
                },
                dataType: 'json',
                timeout: 90000, // 90 seconds timeout
                success: function(response) {
                    console.log('Parse response:', response);
                    
                    // Reset parsing flag
                    isParsing = false;
                    
                    // Re-enable button
                    btn.prop('disabled', false).html(originalHtml);
                    
                    // Update last parse time
                    const now = new Date();
                    $('#lastParseTimestamp').text(now.toLocaleTimeString());
                    $('#lastParseTime').show();
                    
                    // Always refresh the table after parsing (to show updated data)
                    setTimeout(function() {
                        console.log('Refreshing jobs table...');
                        fetchJobs();
                    }, 300);
                    
                    if (response.success) {
                        statusDiv.removeClass('alert-info alert-warning alert-danger').addClass('alert-success');
                        statusDiv.html('<i class="fas fa-check-circle me-1"></i>' + (response.message || 'Email parsing completed'));
                        
                        if (!silent) {
                            // Show success message longer
                            setTimeout(function() {
                                statusDiv.fadeOut(2000);
                            }, 3000);
                        } else {
                            // Auto-hide after 2 seconds in silent mode
                            setTimeout(function() {
                                statusDiv.fadeOut(1000);
                            }, 2000);
                        }
                    } else {
                        statusDiv.removeClass('alert-info alert-success alert-warning').addClass('alert-danger');
                        
                        let errorMessage = response.message || 'No new emails found';
                        
                        // Check for IMAP not available error
                        if (response.imap_available === false) {
                            errorMessage = '<strong><i class="fas fa-exclamation-circle"></i> IMAP Extension Not Available</strong><br/>' + 
                                         '<small>The PHP IMAP extension is not enabled in the background job environment (CLI PHP).</small><br/>' +
                                         '<br/>' +
                                         '<span class="badge badge-info">Email:</span> ' + (response.email_configured || 'N/A') + '<br/>' +
                                         '<span class="badge badge-info">Host:</span> ' + (response.imap_host || 'Not set') + '<br/>';
                            
                            // Add environment info if available
                            if (response.php_environment) {
                                errorMessage += '<span class="badge badge-warning">Environment:</span> ' + response.php_environment + '<br/>';
                            }
                            
                            errorMessage += '<br/><strong style="color:#dc3545;">What you need to do:</strong><br/>' +
                                          '<small>1. Contact your hosting provider<br/>' +
                                          '2. Request: "Please enable php-imap extension for the CLI PHP environment"<br/>' +
                                          '3. They should enable IMAP in both web AND CLI PHP environments<br/>' +
                                          '4. After enabling, refresh this page and try again<br/>' +
                                          '<br/>' +
                                          '<a href="check_imap_detailed.php" target="_blank" class="btn btn-sm btn-info">Run Diagnostic Tool</a></small>';
                        }
                        
                        statusDiv.html('<i class="fas fa-exclamation-circle me-1"></i>' + errorMessage);
                        setTimeout(function() {
                            statusDiv.fadeOut(1000);
                        }, 8000);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Parse error:', status, error, xhr);
                    
                    // Reset parsing flag
                    isParsing = false;
                    
                    // Re-enable button
                    btn.prop('disabled', false).html(originalHtml);
                    
                    statusDiv.removeClass('alert-info alert-success alert-warning').addClass('alert-danger');
                    
                    let errorMsg = 'Error parsing emails. ';
                    if (status === 'timeout') {
                        errorMsg += 'Request timed out.';
                    } else if (status === 'parsererror') {
                        errorMsg += 'Invalid response from server.';
                        console.error('Response text:', xhr.responseText);
                    } else {
                        errorMsg += error || 'Unknown error.';
                    }
                    
                    statusDiv.html('<i class="fas fa-times-circle me-1"></i>' + errorMsg);
                    setTimeout(function() {
                        statusDiv.fadeOut(1000);
                    }, 5000);
                    
                    // Still refresh to show current data even on error
                    setTimeout(function() {
                        fetchJobs();
                    }, 500);
                }
            });
        }

        function fetchJobs() {
            const action = currentType === 'UNDEFINED' ? 'fetch_undefined' : 'fetch';
            const payload = {
                action: action,
                type: currentType,
                source: currentType,
                start: $('#startDate').val(),
                end: $('#endDate').val(),
                search: $('#searchText').val()
            };
            
            // Add status filter for UNDEFINED tab
            if (currentType === 'UNDEFINED' && $('#statusFilter').val()) {
                payload.status = $('#statusFilter').val();
            }

            $.post("backend.php", payload, function(html) {
                $("#jobsTable").html(html);
            }).fail(function() {
                const colCount = currentType === 'UNDEFINED' ? 7 : 7;
                $("#jobsTable").html("<tr><td colspan='" + colCount + "' class='text-center text-danger'>Error loading data</td></tr>");
            });
        }

        function resetFilters() {
            $('#startDate').val('');
            $('#endDate').val('');
            $('#searchText').val('');
            $('#statusFilter').val('');
            fetchJobs();
        }

        // Refresh only the current table (respecting active tab and filters)
        function refreshTable() {
            if (typeof fetchJobs === 'function') {
                fetchJobs();
            }
        }

        /**
         * Decode base64-wrapped email bodies (MIME parts stored raw before parser fix). Strips
         * whitespace, fixes padding, then atob — so HTML renders in the modal iframe.
         */
        function decodeBase64EmailBodyIfNeeded(s) {
            if (!s || typeof s !== 'string' || s.length < 80) return s;
            const compact = s.replace(/\s+/g, '');
            if (compact.length < 100 || !/^[A-Za-z0-9+\/=]+$/.test(compact)) return s;
            const padLen = (4 - (compact.length % 4)) % 4;
            const padded = compact + '='.repeat(padLen);
            try {
                const decoded = atob(padded);
                if (decoded.indexOf('<') !== -1 || decoded.indexOf('&') !== -1) return decoded;
            } catch (e) { /* not valid base64 */ }
            return s;
        }

        function openExportModal() {
            if (!hasExport) {
                alert('You do not have permission to export');
                return;
            }
            
            // Set tab name
            const tabNames = {
                'CLIENT': 'Client Backups',
                'VEEAM': 'Veeam Backups',
                'VEEAMCLOUD': 'VeeamCloud Backups',
                'NAS': 'NAS Backups',
                'UNDEFINED': 'Undefined Mails'
            };
            $('#exportTabName').text(tabNames[currentType] || currentType);
            $('#exportEmail').val('');
            
            new bootstrap.Modal(document.getElementById('exportEmailModal')).show();
        }

        function exportExcelDownload() {
            if (!hasExport) {
                alert('You do not have permission to export');
                return;
            }
            
            const params = new URLSearchParams({
                action: 'export',
                type: currentType,
                start: $('#startDate').val(),
                end: $('#endDate').val(),
                search: $('#searchText').val()
            });
            
            bootstrap.Modal.getInstance(document.getElementById('exportEmailModal')).hide();
            window.open("backend.php?" + params.toString(), "_blank");
        }

        function exportExcelWithEmail() {
            if (!hasExport) {
                alert('You do not have permission to export');
                return;
            }
            
            const email = $('#exportEmail').val().trim();
            
            if (!email) {
                alert('Please enter an email address');
                return;
            }
            
            // Validate email format
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                alert('Please enter a valid email address');
                return;
            }
            
            // Show loading state
            const btn = $('button[onclick="exportExcelWithEmail()"]');
            const originalHtml = btn.html();
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Sending...');
            
            // Send export request with email
            $.ajax({
                url: 'backend.php',
                type: 'POST',
                data: {
                    action: 'export',
                    type: currentType,
                    start: $('#startDate').val(),
                    end: $('#endDate').val(),
                    search: $('#searchText').val(),
                    email: email
                },
                dataType: 'json',
                success: function(response) {
                    btn.prop('disabled', false).html(originalHtml);
                    bootstrap.Modal.getInstance(document.getElementById('exportEmailModal')).hide();
                    
                    if (response.success) {
                        alert('Excel report has been sent successfully to ' + email);
                    } else {
                        alert('Error: ' + (response.message || 'Failed to send email'));
                    }
                },
                error: function(xhr, status, error) {
                    btn.prop('disabled', false).html(originalHtml);
                    console.error('Export error:', error);
                    alert('Error sending email. Please try again.');
                }
            });
        }

        function openAdd() {
            if (!hasCreate) {
                alert('You do not have permission to create');
                return;
            }
            // Set backup source based on current tab; for UNDEFINED allow user to choose (default CLIENT)
            const defaultSource = currentType === 'UNDEFINED' ? 'CLIENT' : currentType;
            $('#add_backup_source').val(defaultSource);
            $('#addForm')[0].reset();
            // Re-apply default source after reset
            $('#add_backup_source').val(defaultSource);
            $('#add_is_active').prop('checked', true);
            new bootstrap.Modal(document.getElementById('addModal')).show();
        }

        function viewDetails(id) {
            openView(id);
        }

        function openView(id) {
            currentViewJobId = id;
            const modalContent = $('#viewDetailsContent');
            modalContent.html('<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Loading details...</p></div>');
            loadJobDetails(id);
            const modalEl = document.getElementById('viewDetailsModal');
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }

        function loadJobDetails(id) {
            const modalContent = $('#viewDetailsContent');
            $.post("backend.php", { action: "get_job_details", id: id }, function(response) {
                if (response.error) {
                    modalContent.html('<div class="alert alert-danger">' + response.error + '</div>');
                    return;
                }
                
                const job = response.job;
                const logs = response.logs;
                const latestLog = logs && logs.length ? logs[0] : null;
                const latestStatus = latestLog ? latestLog.status : job.latest_status;
                const latestBackupAt = latestLog && latestLog.backup_date ? latestLog.backup_date : job.latest_backup_at;
                const latestBackupSize = latestLog && latestLog.backup_size ? latestLog.backup_size : job.latest_backup_size;
                const lastEmailReceived = latestLog
                    ? (latestLog.created_at || latestLog.backup_date || job.last_email_received_at || job.latest_backup_at || 'N/A')
                    : (job.last_email_received_at || job.latest_backup_at || 'N/A');
                
                let html = '<div class="row mb-4">';
                html += '<div class="col-md-6"><h5>Job Information</h5>';
                html += '<table class="table table-bordered">';
                html += '<tr><th>Client:</th><td>' + escapeHtml(job.client_name) + '</td></tr>';
                html += '<tr><th>Contact:</th><td>' + escapeHtml(job.contact_name) + '</td></tr>';
                html += '<tr><th>Device Type:</th><td>' + escapeHtml(job.device_type) + '</td></tr>';
                html += '<tr><th>Source:</th><td>' + escapeHtml(job.backup_source) + '</td></tr>';
                html += '<tr><th>Retention Days:</th><td>' + job.retention_days + ' days</td></tr>';
                html += '<tr><th>Status:</th><td>' + getStatusBadgeHtml(latestStatus) + '</td></tr>';
                html += '<tr><th>Latest Backup:</th><td>' + (latestBackupAt || 'N/A') + '</td></tr>';
                html += '<tr><th>Latest Backup Size:</th><td>' + (latestBackupSize || 'N/A') + '</td></tr>';
                html += '<tr><th>Last Email Received:</th><td>' + lastEmailReceived + '</td></tr>';
                html += '<tr><th>Active:</th><td>' + (job.is_active == 1 ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>') + '</td></tr>';
                html += '</table></div>';
                
                html += '<div class="col-md-6"><h5>Statistics</h5>';
                html += '<table class="table table-bordered">';
                
                // Count by status
                const statusCounts = { Success: 0, Warning: 0, Failed: 0 };
                logs.forEach(log => {
                    const normalizedStatus = log.status === 'Error' ? 'Failed' : log.status;
                    if (statusCounts.hasOwnProperty(normalizedStatus)) {
                        statusCounts[normalizedStatus]++;
                    }
                });
                
                html += '<tr><th>Total Logs:</th><td>' + logs.length + '</td></tr>';
                html += '<tr><th>Success:</th><td><span class="badge bg-success">' + statusCounts.Success + '</span></td></tr>';
                html += '<tr><th>Warning:</th><td><span class="badge bg-warning">' + statusCounts.Warning + '</span></td></tr>';
                html += '<tr><th>Failed:</th><td><span class="badge bg-danger">' + statusCounts.Failed + '</span></td></tr>';
                html += '</table></div></div>';
                
                html += '<hr><h5>All Backup Logs (' + logs.length + ')</h5>';
                html += '<div class="table-responsive" style="max-height: 400px; overflow-y: auto;">';
                html += '<table class="table table-sm table-striped">';
                html += '<thead class="table-dark sticky-top"><tr>';
                html += '<th>Date</th><th>Status</th><th>Backup Size</th><th>Email Subject</th><th>Actions</th>';
                html += '</tr></thead><tbody>';
                
                if (logs.length === 0) {
                    html += '<tr><td colspan="5" class="text-center text-muted">No backup logs found</td></tr>';
                } else {
                    logs.forEach(log => {
                        const logDate = log.backup_date ? new Date(log.backup_date).toLocaleString() : 'N/A';
                        html += '<tr>';
                        html += '<td>' + logDate + '</td>';
                        html += '<td>' + getStatusBadgeHtml(log.status) + '</td>';
                        html += '<td>' + (log.backup_size || 'N/A') + '</td>';
                        html += '<td>' + escapeHtml(cleanEmailSubject(log.email_subject).substring(0, 50)) + '</td>';
                        html += '<td>';
                        html += '<button class="btn btn-sm btn-outline-info me-1" onclick="viewLogDetails(' + log.id + ')" title="View Email Body"><i class="fas fa-eye"></i></button>';
                        html += '<button class="btn btn-sm btn-outline-danger" onclick="deleteBackupLog(' + log.id + ')" title="Delete Log"><i class="fas fa-trash"></i></button>';
                        html += '</td>';
                        html += '</tr>';
                    });
                }
                
                html += '</tbody></table></div>';
                
                // Store logs data for viewLogDetails function
                window.currentLogsData = logs;
                
                modalContent.html(html);
            }, "json").fail(function() {
                modalContent.html('<div class="alert alert-danger">Error loading job details</div>');
            });
        }

        function deleteBackupLog(logId) {
            if (!confirm('Are you sure you want to delete this backup log?')) return;
            $.post("backend.php", { action: "delete_backup_log", id: logId }, function(res) {
                if (res.success) {
                    if (currentViewJobId) {
                        loadJobDetails(currentViewJobId);
                    }
                    if (typeof fetchJobs === 'function') {
                        fetchJobs();
                    }
                } else {
                    alert(res.error || 'Error deleting backup log');
                }
            }, "json").fail(function() {
                alert('Error deleting backup log');
            });
        }
        
        function viewLogDetails(logId) {
            if (!window.currentLogsData) return;
            
            const log = window.currentLogsData.find(l => l.id == logId);
            if (!log) return;
            
            let html = '<h6>Email Details</h6>';
            html += '<p><strong>Subject:</strong> ' + escapeHtml(cleanEmailSubject(log.email_subject)) + '</p>';
            html += '<p><strong>Date:</strong> ' + (log.backup_date || 'N/A') + '</p>';
            html += '<p><strong>Status:</strong> ' + getStatusBadgeHtml(log.status) + '</p>';
            html += '<p><strong>Backup Size:</strong> ' + (log.backup_size || 'N/A') + '</p>';
            html += '<hr><h6>Email Body:</h6>';
            html += '<div style="max-height: 500px; overflow-y: auto; background: #ffffff; padding: 10px; border-radius: 5px; border: 1px solid #dee2e6;">';
            html += '<iframe id="emailBodyFrame_' + logId + '" sandbox="allow-same-origin" style="width: 100%; min-height: 400px; border: none; background: white;"></iframe>';
            html += '</div>';
            
            // Show in a new modal or alert
            const modal = $('<div class="modal fade"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5>Email Details</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">' + html + '</div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div></div></div></div>');
            $('body').append(modal);
            new bootstrap.Modal(modal[0]).show();
            
            // Set iframe content after modal is created
            setTimeout(function() {
                const iframe = document.getElementById('emailBodyFrame_' + logId);
                if (iframe && log.email_body) {
                    let emailBody = decodeBase64EmailBodyIfNeeded(log.email_body);
                    
                    // Clean up UTF-8 BOM and encoding artifacts (remove "Â" characters)
                    emailBody = emailBody.replace(/\u00EF\u00BB\u00BF/g, ''); // Remove UTF-8 BOM
                    emailBody = emailBody.replace(/\u00C2\u00A0/g, ' '); // Replace non-breaking space
                    emailBody = emailBody.replace(/\u00A0/g, ' '); // Replace non-breaking space (Unicode)
                    emailBody = emailBody.replace(/Â(?![\u0080-\u00BF])/g, ''); // Remove standalone Â
                    emailBody = emailBody.replace(/Â[\s\.,;:!?]/g, ''); // Remove Â followed by space/punctuation
                    
                    iframe.srcdoc = emailBody;
                }
            }, 100);
            
            modal.on('hidden.bs.modal', function() {
                modal.remove();
            });
        }

        (function() {
            const modalEl = document.getElementById('viewDetailsModal');
            if (!modalEl) return;
            modalEl.addEventListener('hidden.bs.modal', function() {
                document.body.classList.remove('modal-open');
                document.body.style.removeProperty('padding-right');
                document.querySelectorAll('.modal-backdrop').forEach(function(backdrop) {
                    backdrop.remove();
                });
            });
        })();
        
        function escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }
        
        function getStatusBadgeHtml(status) {
            if (!status) return '<span class="badge bg-secondary">N/A</span>';
            // Convert "Error" to "Failed" for display
            if (status === 'Error') {
                status = 'Failed';
            }
            const badges = {
                'Success': '<span class="badge bg-success">Success</span>',
                'Warning': '<span class="badge bg-warning text-dark">Warning</span>',
                'Failed': '<span class="badge bg-danger">Failed</span>'
            };
            return badges[status] || '<span class="badge bg-secondary">' + escapeHtml(status) + '</span>';
        }

        function openEdit(id) {
            if (!hasEdit) {
                alert('You do not have permission to edit');
                return;
            }
            $.post("backend.php", { action: "get_job", id: id }, function(res) {
                if (res.error) {
                    alert(res.error);
                    return;
                }
                const job = res.job || res;
                $('#edit_id').val(job.id);
                $('#edit_client_id').val(job.client_id);
                $('#edit_contact_name').val(job.contact_name || job.contact_person || '');
                $('#edit_device_type').val(job.device_type || '');
                $('#edit_retention_days').val(job.retention_days || job.days || 30);
                $('#edit_is_active').prop('checked', (job.is_active == 1));
                new bootstrap.Modal(document.getElementById('editModal')).show();
            }, "json").fail(function() {
                alert('Error loading job details');
            });
        }

        function editJob(id) {
            openEdit(id);
        }

        function openDelete(id) {
            if (!hasDelete) {
                alert('You do not have permission to delete');
                return;
            }
            $("#delete_id").val(id);
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        function deleteJob(id) {
            openDelete(id);
        }

        function createJobFromUndefined(id) {
            if (!hasCreate) {
                alert('You do not have permission to create');
                return;
            }
            $.post("backend.php", { action: "get_undefined", id: id }, function(res) {
                const data = res.data || res;
                if (res.error) {
                    alert(res.error);
                    return;
                }
                $('#create_undefined_id').val(data.id);
                $('#create_device_type').val(data.device_type || '');
                $('#create_backup_source').val(data.source || '');
                $('#create_device_type_display').text(data.device_type || 'N/A');
                $('#create_client_id').val('');
                $('#create_contact_name').val('');
                new bootstrap.Modal(document.getElementById('createFromUndefinedModal')).show();
            }, "json").fail(function() {
                alert('Error loading undefined mail details');
            });
        }

        function viewUndefinedDetails(id) {
            $.post("backend.php", { action: "get_undefined", id: id }, function(res) {
                if (res.error) {
                    alert(res.error);
                    return;
                }

                const data = res.data || res;
                
                let html = '<h6>Undefined Mail Details</h6>';
                html += '<table class="table table-bordered">';
                html += '<tr><th>Source:</th><td>' + escapeHtml(data.source || 'N/A') + '</td></tr>';
                html += '<tr><th>Device Type:</th><td>' + escapeHtml(data.device_type || 'N/A') + '</td></tr>';
                html += '<tr><th>Status:</th><td>' + getStatusBadgeHtml(data.status) + '</td></tr>';
                html += '<tr><th>Backup Date:</th><td>' + (data.backup_date || 'N/A') + '</td></tr>';
                html += '<tr><th>Backup Size:</th><td>' + (data.backup_size || 'N/A') + '</td></tr>';
                html += '<tr><th>Email Subject:</th><td>' + escapeHtml(data.email_subject || 'N/A') + '</td></tr>';
                html += '</table>';
                html += '<hr><h6>Email Body:</h6>';
                html += '<div style="max-height: 500px; overflow-y: auto; background: #ffffff; padding: 15px; border-radius: 5px; border: 1px solid #dee2e6;">';
                html += '<iframe id="emailBodyFrame_undefined_' + id + '" sandbox="allow-same-origin" style="width: 100%; min-height: 400px; border: none; background: white;"></iframe>';
                html += '</div>';
                
                // Show in a modal
                const modal = $('<div class="modal fade"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header bg-info text-white"><h5 class="modal-title"><i class="fas fa-envelope me-2"></i>Undefined Mail Details</h5><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body">' + html + '</div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div></div></div></div>');
                $('body').append(modal);
                new bootstrap.Modal(modal[0]).show();
                
                // Set iframe content after modal is created
                setTimeout(function() {
                    const iframe = document.getElementById('emailBodyFrame_undefined_' + id);
                    if (iframe && data.email_body) {
                        let emailBody = decodeBase64EmailBodyIfNeeded(data.email_body);
                        
                        iframe.srcdoc = emailBody;
                    }
                }, 100);
                
                modal.on('hidden.bs.modal', function() {
                    modal.remove();
                });
            }, "json").fail(function() {
                alert('Error loading undefined mail details');
            });
        }

        function deleteUndefined(id) {
            if (!hasDelete) {
                alert('You do not have permission to delete');
                return;
            }
            if (!confirm('Are you sure you want to delete this undefined mail?')) return;
            
            $.post("backend.php", { action: "delete_undefined", id: id }, function(res) {
                if (res.success) {
                    fetchJobs();
                } else {
                    alert('Error deleting undefined mail');
                }
            }, "json");
        }

        function openViewUndefined(id) {
            viewUndefinedDetails(id);
        }

        function openEditUndefined(id) {
            createJobFromUndefined(id);
        }

        function openDeleteUndefined(id) {
            deleteUndefined(id);
        }

        function openReportsModal() {
            new bootstrap.Modal(document.getElementById('reportsModal')).show();
        }

        function generateReport() {
            const payload = {
                action: 'report',
                client_id: $('#report_client_id').val() || 0,
                device_type: $('#report_device_type').val(),
                start: $('#report_start_date').val(),
                end: $('#report_end_date').val(),
                source: $('#report_source').val()
            };

            $.ajax({
                url: "backend.php",
                type: "POST",
                data: payload,
                dataType: "json",
                success: function(report) {
                let html = '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Date</th><th>Client</th><th>Device</th><th>Status</th><th>Size</th></tr></thead><tbody>';
                
                if (report.length === 0) {
                    html += '<tr><td colspan="5" class="text-center">No records found</td></tr>';
                } else {
                    report.forEach(function(row) {
                        // Convert "Error" to "Failed" for display
                        let displayStatus = row.status === 'Error' ? 'Failed' : row.status;
                        const statusBadge = displayStatus === 'Success' ? '<span class="badge bg-success">Success</span>' :
                                          displayStatus === 'Warning' ? '<span class="badge bg-warning">Warning</span>' :
                                          '<span class="badge bg-danger">Failed</span>';
                        html += `<tr>
                            <td>${row.backup_date}</td>
                            <td>${row.client_name}</td>
                            <td>${row.device_type}</td>
                            <td>${statusBadge}</td>
                            <td>${row.backup_size || 'N/A'}</td>
                        </tr>`;
                    });
                }
                
                html += '</tbody></table></div>';
                $('#reportResults').html(html);
                },
                error: function() {
                    $('#reportResults').html('<div class="alert alert-danger">Error loading report</div>');
                }
            });
        }


        // Form submissions
        $('#addForm').on('submit', function(e) {
            e.preventDefault();
            const data = $(this).serialize() + '&action=add_job';
            $.post('backend.php', data, function(res) {
                if (res.error) {
                    alert(res.error);
                } else {
                    bootstrap.Modal.getInstance(document.getElementById('addModal')).hide();
                    fetchJobs();
                }
            }, "json");
        });

        $('#editForm').on('submit', function(e) {
            e.preventDefault();
            const payload = {
                action: 'update_job',
                job_id: $('#edit_id').val(),
                client_id: $('#edit_client_id').val(),
                contact_person: $('#edit_contact_name').val(),
                device_type: $('#edit_device_type').val(),
                backup_source: currentType,
                days: $('#edit_retention_days').val()
            };
            $.post('backend.php', payload, function(res) {
                if (res.error) {
                    alert(res.error);
                } else {
                    bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
                    fetchJobs();
                }
            }, "json");
        });

        $('#deleteForm').on('submit', function(e) {
            e.preventDefault();
            const data = $(this).serialize() + '&action=delete_job';
            $.post('backend.php', data, function(res) {
                if (res.error) {
                    alert(res.error);
                } else {
                    bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
                    fetchJobs();
                }
            }, "json");
        });

        $('#createFromUndefinedForm').on('submit', function(e) {
            e.preventDefault();
            const data = $(this).serialize() + '&action=create_job_from_undefined';
            $.post('backend.php', data, function(res) {
                if (res.error) {
                    alert(res.error);
                } else {
                    bootstrap.Modal.getInstance(document.getElementById('createFromUndefinedModal')).hide();
                    fetchJobs();
                }
            }, "json");
        });

        // Auto-fill contact from client selection
        $('#add_client_id, #create_client_id').on('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const contactPerson = selectedOption.getAttribute('data-contact') || '';
            const formId = $(this).attr('id');
            if (formId === 'add_client_id') {
                $('#add_contact_name').val(contactPerson);
            } else {
                $('#create_contact_name').val(contactPerson);
            }
        });

        // Prevent form submission on Enter key, trigger fetchJobs instead
        $('#filterForm').on('submit', function(e) {
            e.preventDefault();
            fetchJobs();
            return false;
        });

        // Also handle Enter key in search field directly
        $('#searchText').on('keypress', function(e) {
            if (e.which === 13 || e.keyCode === 13) {
                e.preventDefault();
                fetchJobs();
                return false;
            }
        });

        // Initialize
        // Check for URL parameter to auto-activate specific tab
        const urlParams = new URLSearchParams(window.location.search);
        const typeParam = urlParams.get('type');
        
        // Map of valid types to their tab files
        const tabMapping = {
            'CLIENT': 'client.php',
            'VEEAM': 'veeam.php',
            'VEEAMCLOUD': 'veeamcloud.php',
            'NAS': 'nas.php',
            'UNDEFINED': 'undefined.php'
        };
        
        if (typeParam && tabMapping[typeParam]) {
            // Load the specific tab based on URL parameter
            loadTab(tabMapping[typeParam], typeParam);
        } else {
            // Default to Client tab
            loadTab("client.php", "CLIENT");
        }
    </script>
</body>

</html>
