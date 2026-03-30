<?php
session_start();
include_once '../../config.php';
include_once '../components/permissioncheck.php';

// Check if user has permission to view backup monitoring
if (!hasPermission('billing', 'backup_monitoring')) {
    die('Access denied. You do not have permission to view this page.');
}

// Encryption key (must match the one used to encrypt passwords)
// If passwords are encrypted, define this key in config.php or here
define('ENC_KEY', 'G7fP9xL2tQ8wR4kB1mZ6uH3cV0aN5sD'); // Default key - adjust if different

/**
 * Decrypt password from database
 * Uses AES-256-CBC encryption
 * @param string $encrypted Encrypted password (base64 encoded)
 * @return string Decrypted password
 */
function decryptPassword($encrypted) {
    if (empty($encrypted)) {
        return '';
    }
    
    try {
        // Decode from base64
        $data = base64_decode($encrypted, true);
        if ($data === false) {
            // If it's not encrypted (plain text), return as is
            return $encrypted;
        }
        
        // Extract IV and cipher
        $iv = substr($data, 0, 16);
        $cipher = substr($data, 16);
        
        // Decrypt using AES-256-CBC
        $decrypted = openssl_decrypt($cipher, 'AES-256-CBC', ENC_KEY, 0, $iv);
        
        if ($decrypted === false) {
            // Decryption failed, return the original encrypted value
            return $encrypted;
        }
        
        return $decrypted;
    } catch (Exception $e) {
        error_log('Password decryption error: ' . $e->getMessage());
        return $encrypted;
    }
}

// Helper function to remove SPAM prefix from email subjects
function cleanEmailSubject($subject) {
    if (empty($subject)) {
        return 'N/A';
    }
    return preg_replace('/^\*\*\*SPAM\*\*\*\s*/', '', trim($subject));
}

// Determine if we should show decrypted passwords
$showDecrypted = isset($_GET['show']) && $_GET['show'] === 'true';
$hasAccess = hasPermission('billing', 'backup_monitoring');

// Check if viewing backup logs for a specific job
$jobId = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;
$viewLogs = $jobId > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Backup Mails - SAU Technologies</title>
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
            font-weight: 600;
        }
        .table tbody tr:hover {
            background-color: #f5f5f5;
        }
        .badge {
            padding: 0.5em 0.75em;
            font-size: 0.875em;
        }
        .password-container {
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .password-masked {
            font-family: 'Courier New', monospace;
            background-color: #f0f0f0;
            padding: 0.5rem;
            border-radius: 4px;
            user-select: none;
            min-width: 200px;
        }
        .password-visible {
            font-family: 'Courier New', monospace;
            background-color: #e8f5e9;
            padding: 0.5rem;
            border-radius: 4px;
            word-break: break-all;
            user-select: text;
            min-width: 200px;
            border: 1px solid #4caf50;
        }
        .copy-btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            cursor: pointer;
        }
        .toggle-password-btn {
            background: none;
            border: none;
            color: #667eea;
            cursor: pointer;
            padding: 0;
            font-size: 1.2rem;
        }
        .toggle-password-btn:hover {
            color: #764ba2;
        }
        .card {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: none;
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 2px solid #667eea;
            font-weight: 600;
        }
        .info-box {
            background-color: #e3f2fd;
            border-left: 4px solid #667eea;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 4px;
        }
        .info-box i {
            color: #667eea;
            margin-right: 0.5rem;
        }
        .no-data {
            text-align: center;
            padding: 3rem;
            color: #999;
        }
        .no-data i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #ddd;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <?php if ($viewLogs): ?>
            <!-- View Backup Logs for Job -->
            <?php
            // Fetch job details
            $jobQuery = "SELECT bj.*, c.client_name, c.contact_person 
                         FROM backup_jobs bj 
                         LEFT JOIN clients c ON bj.client_id = c.id 
                         WHERE bj.id = ?";
            $jobStmt = $conn->prepare($jobQuery);
            $jobStmt->bind_param("i", $jobId);
            $jobStmt->execute();
            $jobResult = $jobStmt->get_result();
            $job = $jobResult->fetch_assoc();
            $jobStmt->close();
            
            if (!$job) {
                echo "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle me-2'></i>Backup job not found.</div>";
                echo "<a href='index.php' class='btn btn-secondary'><i class='fas fa-arrow-left me-1'></i>Back</a>";
                exit;
            }
            ?>
            
            <!-- Page Header -->
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-0"><i class="fas fa-list-alt me-2"></i>Backup Logs for Job #<?php echo $jobId; ?></h2>
                        <p class="mb-0 mt-2">
                            <strong>Device:</strong> <?php echo htmlspecialchars($job['device_type']); ?> | 
                            <strong>Source:</strong> <?php echo htmlspecialchars($job['backup_source']); ?> | 
                            <strong>Client:</strong> <?php echo htmlspecialchars($job['client_name'] ?? 'N/A'); ?>
                        </p>
                    </div>
                    <div>
                        <a href="index.php" class="btn btn-light">
                            <i class="fas fa-arrow-left me-1"></i>Back
                        </a>
                    </div>
                </div>
            </div>

            <!-- Job Information Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-info-circle me-2"></i>Job Information
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <strong>Device Type:</strong><br>
                            <span class="badge bg-primary"><?php echo htmlspecialchars($job['device_type']); ?></span>
                        </div>
                        <div class="col-md-3">
                            <strong>Backup Source:</strong><br>
                            <span class="badge bg-info"><?php echo htmlspecialchars($job['backup_source']); ?></span>
                        </div>
                        <div class="col-md-3">
                            <strong>Client:</strong><br>
                            <?php echo htmlspecialchars($job['client_name'] ?? 'N/A'); ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Latest Status:</strong><br>
                            <?php
                            $status = strtolower(trim($job['latest_status'] ?? ''));
                            $statusClass = 'secondary';
                            if ($status === 'success') $statusClass = 'success';
                            elseif ($status === 'warning') $statusClass = 'warning';
                            elseif (in_array($status, ['error', 'failed'])) $statusClass = 'danger';
                            ?>
                            <span class="badge bg-<?php echo $statusClass; ?>"><?php echo htmlspecialchars($job['latest_status'] ?? 'N/A'); ?></span>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-3">
                            <strong>Latest Backup:</strong><br>
                            <?php echo $job['latest_backup_at'] ? date('Y-m-d H:i', strtotime($job['latest_backup_at'])) : 'N/A'; ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Latest Size:</strong><br>
                            <?php echo htmlspecialchars($job['latest_backup_size'] ?? 'N/A'); ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Retention Days:</strong><br>
                            <?php echo htmlspecialchars($job['retention_days'] ?? $job['days'] ?? '30'); ?> days
                        </div>
                    </div>
                </div>
            </div>

            <!-- Backup Logs Table -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-table me-2"></i>All Backup Logs
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 5%">ID</th>
                                    <th style="width: 10%">Status</th>
                                    <th style="width: 15%">Backup Date</th>
                                    <th style="width: 15%">Backup Size</th>
                                    <th style="width: 25%">Email Subject</th>
                                    <th style="width: 10%">Source</th>
                                    <th style="width: 20%">Created At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Fetch all backup logs for this job
                                $logsQuery = "SELECT id, status, backup_date, backup_size, email_subject, source, created_at, email_body 
                                             FROM backup_logs 
                                             WHERE backup_job_id = ? 
                                             ORDER BY backup_date DESC, created_at DESC";
                                $logsStmt = $conn->prepare($logsQuery);
                                $logsStmt->bind_param("i", $jobId);
                                $logsStmt->execute();
                                $logsResult = $logsStmt->get_result();
                                
                                if ($logsResult->num_rows === 0) {
                                    echo "<tr><td colspan='7'>";
                                    echo "<div class='no-data'>";
                                    echo "<i class='fas fa-inbox'></i>";
                                    echo "<p class='mt-2'>No backup logs found for this job</p>";
                                    echo "</div>";
                                    echo "</td></tr>";
                                } else {
                                    $logCount = 0;
                                    while ($log = $logsResult->fetch_assoc()) {
                                        $logCount++;
                                        
                                        // Status badge
                                        $status = strtolower(trim($log['status']));
                                        $statusClass = 'secondary';
                                        if ($status === 'success') $statusClass = 'success';
                                        elseif ($status === 'warning') $statusClass = 'warning';
                                        elseif (in_array($status, ['error', 'failed'])) $statusClass = 'danger';
                                        
                                        $statusBadge = '<span class="badge bg-' . $statusClass . '">' . htmlspecialchars($log['status']) . '</span>';
                                        
                                        // Format dates
                                        $backupDate = $log['backup_date'] ? date('Y-m-d H:i', strtotime($log['backup_date'])) : 'N/A';
                                        $createdDate = $log['created_at'] ? date('Y-m-d H:i', strtotime($log['created_at'])) : 'N/A';
                                        
                                        // Clean email subject (remove SPAM prefix) and truncate if too long
                                        $emailSubject = cleanEmailSubject($log['email_subject']);
                                        $emailSubject = htmlspecialchars($emailSubject);
                                        if (strlen($emailSubject) > 50) {
                                            $emailSubject = substr($emailSubject, 0, 50) . '...';
                                        }
                                        
                                        echo "<tr>";
                                        echo "<td><strong>#{$log['id']}</strong></td>";
                                        echo "<td>{$statusBadge}</td>";
                                        echo "<td><small>{$backupDate}</small></td>";
                                        echo "<td><small>" . htmlspecialchars($log['backup_size'] ?? 'N/A') . "</small></td>";
                                        echo "<td>";
                                        echo "<small title='" . htmlspecialchars(cleanEmailSubject($log['email_subject'])) . "'>" . $emailSubject . "</small>";
                                        if (!empty($log['email_body'])) {
                                            echo " <button class='btn btn-sm btn-link p-0' onclick='showEmailBody({$log['id']})' title='View Email Body'><i class='fas fa-eye'></i></button>";
                                        }
                                        echo "</td>";
                                        echo "<td><span class='badge bg-info'>" . htmlspecialchars($log['source']) . "</span></td>";
                                        echo "<td><small class='text-muted'>{$createdDate}</small></td>";
                                        echo "</tr>";
                                        
                                        // Store email body in hidden div for modal (decode base64 if needed)
                                        if (!empty($log['email_body'])) {
                                            $emailBody = $log['email_body'];
                                            
                                            // Check if it's base64 encoded
                                            $decoded = @base64_decode($emailBody, true);
                                            if ($decoded !== false && base64_encode($decoded) === $emailBody) {
                                                // It's base64 encoded, decode it
                                                $emailBody = $decoded;
                                            }
                                            
                                            // Decode HTML entities
                                            $emailBody = html_entity_decode($emailBody, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                            
                                            echo "<div id='email_body_{$log['id']}' style='display:none;'>" . htmlspecialchars($emailBody) . "</div>";
                                        }
                                    }
                                    echo "<tr><td colspan='7' class='text-center text-muted py-2'>";
                                    echo "Total logs: <strong>$logCount</strong>";
                                    echo "</td></tr>";
                                }
                                $logsStmt->close();
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- View Backup Mails (Original Functionality) -->
            <!-- Page Header -->
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-0"><i class="fas fa-envelope me-2"></i>Backup Mails Database</h2>
                        <p class="mb-0 mt-2">View all backup mail configurations with decrypted app passwords</p>
                    </div>
                    <div>
                        <a href="manage_mails.php" class="btn btn-light me-2">
                            <i class="fas fa-edit me-1"></i>Manage Mails
                        </a>
                        <a href="index.php" class="btn btn-light">
                            <i class="fas fa-arrow-left me-1"></i>Back
                        </a>
                    </div>
                </div>
            </div>

            <!-- Info Box -->
            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <strong>Data Decryption:</strong> All passwords are automatically decrypted and displayed below in plain text format.
                <?php if (!$showDecrypted): ?>
                    <a href="?show=true" class="ms-2">Show all passwords</a>
                <?php endif; ?>
            </div>

            <!-- Main Table Card -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-table me-2"></i>All Backup Mail Entries
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 10%">ID</th>
                                    <th style="width: 15%">Mail Type</th>
                                    <th style="width: 25%">Email Address</th>
                                    <th style="width: 25%">App Password</th>
                                    <th style="width: 20%">IMAP Host</th>
                                    <th style="width: 10%">Status</th>
                                    <th style="width: 10%">Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Fetch all backup mails from database
                                $query = "SELECT id, mail_type, email_address, app_password, imap_host, is_active, updated_at 
                                          FROM backup_mails 
                                          ORDER BY CASE mail_type 
                                                      WHEN 'CLIENT' THEN 1
                                                      WHEN 'VEEAM' THEN 2
                                                      WHEN 'VEEAMCLOUD' THEN 3
                                                      WHEN 'NAS' THEN 4
                                                  END";
                                
                                $result = $conn->query($query);
                                
                                if (!$result) {
                                    echo "<tr><td colspan='7' class='alert alert-danger m-2'>Error fetching data: " . $conn->error . "</td></tr>";
                                } elseif ($result->num_rows === 0) {
                                    echo "<tr><td colspan='7'>";
                                    echo "<div class='no-data'>";
                                    echo "<i class='fas fa-inbox'></i>";
                                    echo "<p class='mt-2'>No backup mail configurations found</p>";
                                    echo "<a href='manage_mails.php' class='btn btn-primary btn-sm'>Add New Backup Mail</a>";
                                    echo "</div>";
                                    echo "</td></tr>";
                                } else {
                                    $rowCount = 0;
                                    while ($row = $result->fetch_assoc()) {
                                        $rowCount++;
                                        
                                        // Decrypt the password
                                        $decryptedPassword = decryptPassword($row['app_password']);
                                        
                                        // Status badge
                                        $statusBadge = $row['is_active'] == 1 
                                            ? '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Active</span>' 
                                            : '<span class="badge bg-secondary"><i class="fas fa-ban me-1"></i>Inactive</span>';
                                        
                                        // Format updated date
                                        $updatedDate = $row['updated_at'] 
                                            ? date('Y-m-d H:i', strtotime($row['updated_at'])) 
                                            : '<span class="text-muted">N/A</span>';
                                        
                                        echo "<tr>";
                                        echo "<td><strong>#{$row['id']}</strong></td>";
                                        echo "<td>";
                                        echo "<span class='badge bg-primary'>" . htmlspecialchars($row['mail_type']) . "</span>";
                                        echo "</td>";
                                        echo "<td>";
                                        echo "<small>" . htmlspecialchars($row['email_address']) . "</small>";
                                        echo "</td>";
                                        echo "<td>";
                                        echo "<div class='password-container'>";
                                        
                                        if (empty($decryptedPassword)) {
                                            echo "<span class='text-muted'><em>Not set</em></span>";
                                        } else {
                                            echo "<span class='password-visible' id='pwd_{$row['id']}'>";
                                            echo htmlspecialchars($decryptedPassword);
                                            echo "</span>";
                                            echo "<button class='btn btn-sm copy-btn' onclick='copyToClipboard(\"pwd_{$row['id']}\")'>";
                                            echo "<i class='fas fa-copy'></i>";
                                            echo "</button>";
                                        }
                                        
                                        echo "</div>";
                                        echo "</td>";
                                        echo "<td>";
                                        echo "<small class='text-muted'>" . htmlspecialchars($row['imap_host']) . "</small>";
                                        echo "</td>";
                                        echo "<td>";
                                        echo $statusBadge;
                                        echo "</td>";
                                        echo "<td>";
                                        echo "<small class='text-muted'>" . $updatedDate . "</small>";
                                        echo "</td>";
                                        echo "</tr>";
                                    }
                                    echo "<tr><td colspan='7' class='text-center text-muted py-2'>";
                                    echo "Total records: <strong>$rowCount</strong>";
                                    echo "</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- JSON Data Export Card (Optional) -->
            <div class="card mt-4">
                <div class="card-header">
                    <i class="fas fa-code me-2"></i>Export Data (JSON Format)
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">Click the button below to export all backup mail data as JSON with decrypted passwords:</p>
                    <button class="btn btn-primary" onclick="exportAsJSON()">
                        <i class="fas fa-download me-1"></i>Export as JSON
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Email Body Modal -->
    <?php if ($viewLogs): ?>
    <div class="modal fade" id="emailBodyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-envelope me-2"></i>Email Body</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <pre id="emailBodyContent" style="white-space: pre-wrap; word-wrap: break-word; max-height: 500px; overflow-y: auto; background: #f8f9fa; padding: 15px; border-radius: 5px;"></pre>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        <?php if ($viewLogs): ?>
        function decodeBase64EmailBodyIfNeeded(s) {
            if (!s || typeof s !== 'string' || s.length < 80) return s;
            const compact = s.replace(/\s+/g, '');
            if (compact.length < 100 || !/^[A-Za-z0-9+\/=]+$/.test(compact)) return s;
            const padLen = (4 - (compact.length % 4)) % 4;
            const padded = compact + '='.repeat(padLen);
            try {
                const decoded = atob(padded);
                if (decoded.indexOf('<') !== -1 || decoded.indexOf('&') !== -1) return decoded;
            } catch (e) { }
            return s;
        }
        /**
         * Show email body in modal
         */
        function showEmailBody(logId) {
            const emailBody = document.getElementById('email_body_' + logId);
            if (emailBody) {
                let bodyContent = emailBody.textContent || emailBody.innerText;
                bodyContent = decodeBase64EmailBodyIfNeeded(bodyContent);
                
                // Display in modal - if it's HTML, use innerHTML, otherwise use textContent
                const contentDiv = document.getElementById('emailBodyContent');
                if (bodyContent.includes('<') && bodyContent.includes('>')) {
                    // It's HTML, display as HTML
                    contentDiv.innerHTML = bodyContent;
                } else {
                    // Plain text, display as pre-formatted text
                    contentDiv.textContent = bodyContent;
                }
                
                const modal = new bootstrap.Modal(document.getElementById('emailBodyModal'));
                modal.show();
            }
        }
        <?php else: ?>
        /**
         * Copy text to clipboard
         * @param {string} elementId - ID of the element containing text to copy
         */
        function copyToClipboard(elementId) {
            const element = document.getElementById(elementId);
            const text = element.innerText;
            
            navigator.clipboard.writeText(text).then(() => {
                // Show success feedback
                const btn = event.target.closest('.copy-btn');
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check" style="color: #4caf50;"></i>';
                
                setTimeout(() => {
                    btn.innerHTML = originalHTML;
                }, 2000);
            }).catch(err => {
                alert('Failed to copy password');
                console.error('Copy failed:', err);
            });
        }

        /**
         * Export all data as JSON
         */
        function exportAsJSON() {
            // Collect all table data
            const data = [];
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length >= 7) {
                    const passwordElement = cells[3].querySelector('.password-visible');
                    const password = passwordElement ? passwordElement.innerText : '';
                    
                    const rowData = {
                        id: cells[0].innerText.replace('#', ''),
                        mail_type: cells[1].innerText.trim(),
                        email_address: cells[2].innerText.trim(),
                        app_password: password,
                        imap_host: cells[4].innerText.trim(),
                        status: cells[5].innerText.trim(),
                        updated_at: cells[6].innerText.trim()
                    };
                    
                    if (rowData.id) {
                        data.push(rowData);
                    }
                }
            });
            
            // Generate JSON
            const jsonString = JSON.stringify(data, null, 2);
            
            // Create download link
            const blob = new Blob([jsonString], { type: 'application/json' });
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'backup_mails_' + new Date().toISOString().split('T')[0] + '.json';
            
            // Trigger download
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
        }
        <?php endif; ?>

        // Auto-refresh page every 5 minutes (optional)
        // setInterval(() => {
        //     location.reload();
        // }, 5 * 60 * 1000);
    </script>
</body>
</html>
