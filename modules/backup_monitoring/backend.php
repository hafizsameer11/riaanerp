<?php
session_start();
include_once '../../config.php';
include_once '../components/permissioncheck.php';

// Check if user has access to backup monitoring
if (!hasPermission('billing', 'backup_monitoring')) {
    http_response_code(403);
    die(json_encode(['error' => 'Access denied']));
}

$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

// Helper function for logging create job errors (defined early)
function logCreateJobError($message, $data = []) {
    $logFile = __DIR__ . '/logs/create_job_error.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    if (!empty($data)) {
        $logMessage .= " | Data: " . json_encode($data);
    }
    $logMessage .= "\n";
    @file_put_contents($logFile, $logMessage, FILE_APPEND);
}

function moveUndefinedMailsToLogs($conn, $jobId, $deviceType, $backupSource) {
    $movedCount = 0;
    $selectStmt = $conn->prepare("SELECT id, source, status, backup_date, backup_size, email_subject, email_body FROM undefined_mails WHERE device_type = ? AND source = ?");
    if (!$selectStmt) {
        logCreateJobError("moveUndefinedMailsToLogs: prepare select failed", ['error' => $conn->error]);
        return 0;
    }
    $selectStmt->bind_param("ss", $deviceType, $backupSource);
    $selectStmt->execute();
    $result = $selectStmt->get_result();

    $checkStmt = $conn->prepare("SELECT id FROM backup_logs WHERE backup_job_id = ? AND email_subject = ? AND backup_date <=> ? LIMIT 1");
    if (!$checkStmt) {
        logCreateJobError("moveUndefinedMailsToLogs: prepare check failed", ['error' => $conn->error]);
        $selectStmt->close();
        return 0;
    }

    $insertStmt = $conn->prepare("INSERT INTO backup_logs (backup_job_id, source, status, backup_date, backup_size, email_subject, email_body, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    if (!$insertStmt) {
        logCreateJobError("moveUndefinedMailsToLogs: prepare insert failed", ['error' => $conn->error]);
        $checkStmt->close();
        $selectStmt->close();
        return 0;
    }

    $deleteStmt = $conn->prepare("DELETE FROM undefined_mails WHERE id = ?");
    if (!$deleteStmt) {
        logCreateJobError("moveUndefinedMailsToLogs: prepare delete failed", ['error' => $conn->error]);
        $insertStmt->close();
        $checkStmt->close();
        $selectStmt->close();
        return 0;
    }

    while ($row = $result->fetch_assoc()) {
        $logStatusRaw = trim((string)($row['status'] ?? ''));
        if ($logStatusRaw === '') {
            $logStatus = 'UNKNOWN';
        } else {
            $logStatus = ucfirst(strtolower($logStatusRaw));
            if ($logStatus === 'Failed') {
                $logStatus = 'Error';
            }
        }

        $backupDate = $row['backup_date'] ?? null;
        $checkStmt->bind_param("iss", $jobId, $row['email_subject'], $backupDate);
        $checkStmt->execute();
        $existingLog = $checkStmt->get_result()->fetch_assoc();

        if (!$existingLog) {
            $insertStmt->bind_param(
                "issssss",
                $jobId,
                $row['source'],
                $logStatus,
                $backupDate,
                $row['backup_size'],
                $row['email_subject'],
                $row['email_body']
            );
            if ($insertStmt->execute()) {
                $movedCount++;
            }
        }

        $deleteStmt->bind_param("i", $row['id']);
        $deleteStmt->execute();
    }

    $deleteStmt->close();
    $insertStmt->close();
    $checkStmt->close();
    $selectStmt->close();

    $latestStmt = $conn->prepare("SELECT status, backup_date, backup_size, created_at FROM backup_logs WHERE backup_job_id = ? ORDER BY backup_date DESC, created_at DESC LIMIT 1");
    if ($latestStmt) {
        $latestStmt->bind_param("i", $jobId);
        $latestStmt->execute();
        $latestRow = $latestStmt->get_result()->fetch_assoc();
        $latestStmt->close();

        if ($latestRow) {
            $updateStmt = $conn->prepare("UPDATE backup_jobs SET latest_status = ?, latest_backup_at = ?, latest_backup_size = ?, last_email_received_at = ? WHERE id = ?");
            if ($updateStmt) {
                $updateStmt->bind_param(
                    "ssssi",
                    $latestRow['status'],
                    $latestRow['backup_date'],
                    $latestRow['backup_size'],
                    $latestRow['backup_date'],
                    $jobId
                );
                $updateStmt->execute();
                $updateStmt->close();
            }
        }
    }

    return $movedCount;
}

// Helper function to clean UTF-8 BOM and encoding artifacts from email body
function cleanEmailBody($body) {
    if (empty($body)) {
        return $body;
    }
    
    // Remove UTF-8 BOM (Byte Order Mark) and encoding artifacts
    // UTF-8 BOM is \xEF\xBB\xBF which can appear as "Â" when not properly handled
    $body = preg_replace('/^\xEF\xBB\xBF/', '', $body); // Remove BOM at start
    $body = preg_replace('/\xEF\xBB\xBF/', '', $body); // Remove BOM anywhere
    // Remove standalone "Â" characters that are encoding artifacts (UTF-8 0xC2 0x80-0xBF range issues)
    $body = preg_replace('/\xC2\xA0/', ' ', $body); // Replace non-breaking space
    $body = preg_replace('/\x{00A0}/u', ' ', $body); // Replace non-breaking space (Unicode)
    // Remove standalone "Â" character (U+00C2) when it's not part of a valid UTF-8 sequence
    $body = preg_replace('/\xC2(?![\x80-\xBF])/', '', $body); // Remove invalid C2 sequences
    $body = preg_replace('/Â(?![\x80-\xBF])/u', '', $body); // Remove standalone Â in UTF-8 mode
    // More aggressive: remove Â followed by space or punctuation (common artifact)
    $body = preg_replace('/Â[\s\.,;:!?]/u', '', $body);
    
    return $body;
}

// Fetch undefined mails with pagination
if ($action === 'fetch_undefined') {
    $type = $_POST['type'] ?? 'UNDEFINED';
    $source = $_POST['source'] ?? 'UNDEFINED';
    $start = $_POST['start'] ?? '';
    $end = $_POST['end'] ?? '';
    $search = $_POST['search'] ?? '';
    $status = $_POST['status'] ?? '';
    $page = intval($_POST['page'] ?? 1);
    $limit = intval($_POST['limit'] ?? 50);
    $offset = ($page - 1) * $limit;
    
    // Build query
    $where = ["1=1"];
    $params = [];
    $types = [];
    
    if (!empty($start)) {
        $where[] = "DATE(backup_date) >= ?";
        $params[] = $start;
        $types[] = "s";
    }
    
    if (!empty($end)) {
        $where[] = "DATE(backup_date) <= ?";
        $params[] = $end;
        $types[] = "s";
    }
    
    if (!empty($search)) {
        $where[] = "(device_type LIKE ? OR email_subject LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types[] = "s";
        $types[] = "s";
    }
    
    if (!empty($status)) {
        // When filtering for "Failed", match both "Error" and "Failed" in database
        if ($status === 'Failed') {
            $where[] = "status IN ('Error', 'Failed')";
        } else {
            $where[] = "status = ?";
            $params[] = $status;
            $types[] = "s";
        }
    }
    
    $whereClause = implode(" AND ", $where);
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM undefined_mails WHERE {$whereClause}";
    $countStmt = $conn->prepare($countQuery);
    if (!empty($params)) {
        $countStmt->bind_param(implode("", $types), ...$params);
    }
    $countStmt->execute();
    $totalResult = $countStmt->get_result();
    $totalRow = $totalResult->fetch_assoc();
    $total = $totalRow['total'];
    $countStmt->close();
    
    // Get paginated data
    $query = "SELECT id, source, device_type, status, backup_date, backup_size, email_subject, email_body, created_at 
              FROM undefined_mails 
              WHERE {$whereClause} 
              ORDER BY backup_date DESC, created_at DESC 
              LIMIT ? OFFSET ?";
    
    $types[] = "i";
    $types[] = "i";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param(implode("", $types), ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $html = '';
    $rowCount = 0;
    
    while ($row = $result->fetch_assoc()) {
        $rowCount++;
        
        // Status badge
        $status = strtolower(trim($row['status'] ?? ''));
        $statusClass = 'secondary';
        if ($status === 'success') $statusClass = 'success';
        elseif ($status === 'warning') $statusClass = 'warning';
        elseif (in_array($status, ['error', 'failed', 'unknown'])) $statusClass = 'danger';
        
        // Convert "Error" to "Failed" for display
        $displayStatus = $status === 'error' ? 'Failed' : ($row['status'] ?? 'UNKNOWN');
        $statusBadge = '<span class="badge bg-' . $statusClass . '">' . htmlspecialchars($displayStatus) . '</span>';
        
        // Format dates
        $backupDate = $row['backup_date'] ? date('Y-m-d H:i', strtotime($row['backup_date'])) : 'N/A';
        
        // Backup size - show actual value or N/A
        $backupSize = !empty($row['backup_size']) ? htmlspecialchars($row['backup_size']) : 'N/A';
        
        // Truncate email subject
        $emailSubject = htmlspecialchars($row['email_subject'] ?? '');
        if (strlen($emailSubject) > 50) {
            $emailSubjectDisplay = substr($emailSubject, 0, 50) . '...';
        } else {
            $emailSubjectDisplay = $emailSubject;
        }
        
        $html .= "<tr>";
        $html .= "<td style='width: 50px;'><input type='checkbox' class='form-check-input undefined-checkbox' data-id='{$row['id']}' onchange='updateCheckboxSelection(this)'></td>";
        $html .= "<td><span class='badge bg-info'>" . htmlspecialchars($row['source'] ?? 'N/A') . "</span></td>";
        $html .= "<td>" . htmlspecialchars($row['device_type'] ?? 'N/A') . "</td>";
        $html .= "<td>{$statusBadge}</td>";
        $html .= "<td><small>{$backupDate}</small></td>";
        $html .= "<td><small>{$backupSize}</small></td>";
        $html .= "<td><small title='" . htmlspecialchars($emailSubject) . "'>" . $emailSubjectDisplay . "</small></td>";
        $html .= "<td>";
        $html .= "<button class='btn btn-sm btn-primary me-1' onclick='openViewUndefined({$row['id']})' title='View Details'><i class='fas fa-eye'></i></button>";
        $html .= "<button class='btn btn-sm btn-success me-1' onclick='openEditUndefined({$row['id']})' title='Create Job'><i class='fas fa-plus'></i></button>";
        $html .= "<button class='btn btn-sm btn-danger' onclick='openDeleteUndefined({$row['id']})' title='Delete'><i class='fas fa-trash'></i></button>";
        $html .= "</td>";
        $html .= "</tr>";
    }
    
    if ($rowCount === 0) {
        $html = "<tr><td colspan='8' class='text-center text-muted py-4'>";
        $html .= "<i class='fas fa-inbox me-2'></i>No undefined emails found";
        $html .= "</td></tr>";
    }
    
    $stmt->close();
    
    // Calculate pagination
    $totalPages = ceil($total / $limit);
    $startRow = $offset + 1;
    $endRow = min($offset + $limit, $total);
    
    echo json_encode([
        'html' => $html,
        'pagination' => [
            'page' => $page,
            'pages' => $totalPages,
            'total' => $total,
            'start' => $startRow,
            'end' => $endRow
        ]
    ]);
    exit;
}

// Delete single backup log entry
if ($action === 'delete_backup_log') {
    header('Content-Type: application/json');
    $id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
    if ($id === 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid log ID']);
        exit;
    }

    $logStmt = $conn->prepare("SELECT id, backup_job_id FROM backup_logs WHERE id = ?");
    $logStmt->bind_param("i", $id);
    $logStmt->execute();
    $logResult = $logStmt->get_result();
    $logRow = $logResult->fetch_assoc();
    $logStmt->close();

    if (!$logRow) {
        echo json_encode(['success' => false, 'error' => 'Backup log not found']);
        exit;
    }

    $jobId = (int)$logRow['backup_job_id'];

    $deleteStmt = $conn->prepare("DELETE FROM backup_logs WHERE id = ?");
    $deleteStmt->bind_param("i", $id);

    if (!$deleteStmt->execute()) {
        echo json_encode(['success' => false, 'error' => 'Error deleting backup log: ' . $deleteStmt->error]);
        $deleteStmt->close();
        exit;
    }
    $deleteStmt->close();

    // Refresh latest job fields based on remaining logs
    $latestStmt = $conn->prepare("SELECT status, backup_date, backup_size, created_at FROM backup_logs WHERE backup_job_id = ? ORDER BY backup_date DESC, created_at DESC LIMIT 1");
    $latestStmt->bind_param("i", $jobId);
    $latestStmt->execute();
    $latestResult = $latestStmt->get_result();
    $latest = $latestResult->fetch_assoc();
    $latestStmt->close();

    if ($latest) {
        $latestStatus = $latest['status'] ?? null;
        if ($latestStatus === 'Error') {
            $latestStatus = 'Failed';
        }
        $latestBackupAt = $latest['backup_date'] ?? null;
        $latestBackupSize = $latest['backup_size'] ?? null;
        // Use backup_date for last_email_received_at to match latest_backup_at
        $lastEmailReceivedAt = $latest['backup_date'] ?? null;

        $updateStmt = $conn->prepare("UPDATE backup_jobs SET latest_status = ?, latest_backup_at = ?, latest_backup_size = ?, last_email_received_at = ? WHERE id = ?");
        $updateStmt->bind_param("ssssi", $latestStatus, $latestBackupAt, $latestBackupSize, $lastEmailReceivedAt, $jobId);
        $updateStmt->execute();
        $updateStmt->close();
    } else {
        $clearStmt = $conn->prepare("UPDATE backup_jobs SET latest_status = NULL, latest_backup_at = NULL, latest_backup_size = NULL, last_email_received_at = NULL WHERE id = ?");
        $clearStmt->bind_param("i", $jobId);
        $clearStmt->execute();
        $clearStmt->close();
    }

    echo json_encode(['success' => true, 'message' => 'Backup log deleted successfully']);
    exit;
}

// Get undefined mail details for viewing
if ($action === 'get_undefined') {
    $id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
    
    $stmt = $conn->prepare("SELECT * FROM undefined_mails WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if (!$row) {
        echo json_encode(['error' => 'Undefined mail not found']);
        exit;
    }
    
    // Decode email body if it's base64 encoded
    $emailBody = $row['email_body'] ?? '';
    
    // Check if it's base64 encoded
    if (!empty($emailBody)) {
        // Try to detect if it's base64
        $decoded = @base64_decode($emailBody, true);
        if ($decoded !== false && base64_encode($decoded) === $emailBody) {
            // It's base64 encoded, decode it
            $emailBody = $decoded;
        }
        
        // Clean up UTF-8 BOM and encoding artifacts
        $emailBody = cleanEmailBody($emailBody);
        
        // Try to decode HTML entities
        $emailBody = html_entity_decode($emailBody, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // If it looks like HTML, try to extract text or display as HTML
        if (strip_tags($emailBody) !== $emailBody) {
            // It's HTML, we can display it
            $emailBodyDisplay = $emailBody;
        } else {
            // Plain text
            $emailBodyDisplay = nl2br(htmlspecialchars($emailBody));
        }
    } else {
        $emailBodyDisplay = 'No email body available';
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $row['id'],
            'source' => $row['source'],
            'device_type' => $row['device_type'],
            'status' => $row['status'],
            'backup_date' => $row['backup_date'],
            'backup_size' => $row['backup_size'],
            'email_subject' => $row['email_subject'],
            'email_body' => $emailBodyDisplay,
            'created_at' => $row['created_at']
        ]
    ]);
    exit;
}

// Edit undefined mail (create/update backup job)
if ($action === 'edit_undefined') {
    header('Content-Type: application/json');
    $id = intval($_POST['id'] ?? 0);
    $deviceType = $_POST['device_type'] ?? '';
    $backupSource = $_POST['backup_source'] ?? '';
    $clientId = !empty($_POST['client_id']) ? intval($_POST['client_id']) : null;
    $contactPerson = $_POST['contact_person'] ?? null;
    try {
        if (empty($deviceType) || empty($backupSource)) {
            echo json_encode(['error' => 'Device type and backup source are required']);
            exit;
        }
        
        // If client_id is provided but contact_person is not, fetch it from clients table
        if ($clientId && empty($contactPerson)) {
            $contactStmt = $conn->prepare("SELECT contact_person FROM clients WHERE id = ?");
            if (!$contactStmt) {
                logCreateJobError("edit_undefined: prepare contact failed", ['error' => $conn->error]);
                echo json_encode(['error' => 'Database error: ' . $conn->error]);
                exit;
            }
            $contactStmt->bind_param("i", $clientId);
            $contactStmt->execute();
            $contactResult = $contactStmt->get_result();
            $contactRow = $contactResult->fetch_assoc();
            $contactStmt->close();
            
            if ($contactRow) {
                $contactPerson = $contactRow['contact_person'] ?? null;
            }
        }
        
        // Get undefined mail to get backup size and other info
        $stmt = $conn->prepare("SELECT * FROM undefined_mails WHERE id = ?");
        if (!$stmt) {
            logCreateJobError("edit_undefined: prepare mail failed", ['error' => $conn->error]);
            echo json_encode(['error' => 'Database error: ' . $conn->error]);
            exit;
        }
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $undefinedMail = $result->fetch_assoc();
        $stmt->close();
        
        if (!$undefinedMail) {
            echo json_encode(['error' => 'Undefined mail not found']);
            exit;
        }
        
        // Check if backup job already exists
        $checkStmt = $conn->prepare("SELECT id FROM backup_jobs WHERE device_type = ? AND backup_source = ?");
        if (!$checkStmt) {
            logCreateJobError("edit_undefined: prepare check failed", ['error' => $conn->error]);
            echo json_encode(['error' => 'Database error: ' . $conn->error]);
            exit;
        }
        $checkStmt->bind_param("ss", $deviceType, $backupSource);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();
        
        $statusRaw = trim((string)($undefinedMail['status'] ?? ''));
        $normalizedStatus = $statusRaw !== '' ? ucfirst(strtolower($statusRaw)) : '';
        if ($normalizedStatus === 'Error') {
            $normalizedStatus = 'Failed';
        }
        if (!in_array($normalizedStatus, ['Success', 'Warning', 'Failed'], true)) {
            $normalizedStatus = 'Failed';
        }

        if ($existing) {
            // Update existing job - include contact_name
            $updateStmt = $conn->prepare("UPDATE backup_jobs SET client_id = ?, contact_name = ?, latest_status = ?, latest_backup_at = ?, latest_backup_size = ?, last_email_received_at = ? WHERE id = ?");
            if (!$updateStmt) {
                logCreateJobError("edit_undefined: prepare update failed", ['error' => $conn->error]);
                echo json_encode(['error' => 'Database error: ' . $conn->error]);
                exit;
            }
            $status = $normalizedStatus;
            $backupDate = $undefinedMail['backup_date'] ?? null;
            $backupSize = $undefinedMail['backup_size'] ?? null;
            $jobId = $existing['id'];
            
            // Handle null contactPerson
            if (empty($contactPerson)) {
                $contactPerson = null;
            }
            $updateStmt->bind_param("isssssi", $clientId, $contactPerson, $status, $backupDate, $backupSize, $backupDate, $jobId);
            
            if ($updateStmt->execute()) {
                moveUndefinedMailsToLogs($conn, $jobId, $deviceType, $backupSource);
                echo json_encode(['success' => true, 'message' => 'Backup job updated and matching undefined mails moved to logs']);
            } else {
                echo json_encode(['error' => 'Error updating backup job: ' . $updateStmt->error]);
            }
            $updateStmt->close();
        } else {
            // Create new backup job - include contact_name
            $insertStmt = $conn->prepare("INSERT INTO backup_jobs (client_id, contact_name, device_type, backup_source, latest_status, latest_backup_at, latest_backup_size, last_email_received_at, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())");
            if (!$insertStmt) {
                logCreateJobError("edit_undefined: prepare insert failed", ['error' => $conn->error]);
                echo json_encode(['error' => 'Database error: ' . $conn->error]);
                exit;
            }
            $status = $normalizedStatus;
            $backupDate = $undefinedMail['backup_date'] ?? null;
            $backupSize = $undefinedMail['backup_size'] ?? null;
            
            // Handle null contactPerson
            if (empty($contactPerson)) {
                $contactPerson = null;
            }
            $insertStmt->bind_param("isssssss", $clientId, $contactPerson, $deviceType, $backupSource, $status, $backupDate, $backupSize, $backupDate);
            
            if ($insertStmt->execute()) {
                $jobId = $conn->insert_id;

                moveUndefinedMailsToLogs($conn, $jobId, $deviceType, $backupSource);
                echo json_encode(['success' => true, 'message' => 'Backup job created and matching undefined mails moved to logs']);
            } else {
                echo json_encode(['error' => 'Error creating backup job: ' . $insertStmt->error]);
            }
            $insertStmt->close();
        }
    } catch (Throwable $e) {
        logCreateJobError("edit_undefined: exception", [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    }
    exit;
}

// Delete undefined mail
if ($action === 'delete_undefined') {
    $id = intval($_POST['id'] ?? 0);
    
    $stmt = $conn->prepare("DELETE FROM undefined_mails WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Error deleting: ' . $stmt->error]);
    }
    $stmt->close();
    exit;
}

// Delete multiple undefined mails (bulk delete)
if ($action === 'delete_undefined_bulk') {
    header('Content-Type: application/json');
    
    $ids = $_POST['ids'] ?? [];
    if (empty($ids) || !is_array($ids)) {
        echo json_encode(['success' => false, 'message' => 'No items selected']);
        exit;
    }
    
    // Sanitize IDs
    $ids = array_map('intval', $ids);
    $ids = array_filter($ids, function($id) { return $id > 0; });
    
    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'No valid items to delete']);
        exit;
    }
    
    // Build placeholder string for IN clause
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    
    // Build query
    $deleteQuery = "DELETE FROM undefined_mails WHERE id IN ({$placeholders})";
    $deleteStmt = $conn->prepare($deleteQuery);
    
    if (!$deleteStmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }
    
    // Bind parameters
    $deleteStmt->bind_param($types, ...$ids);
    
    if ($deleteStmt->execute()) {
        $deletedCount = $deleteStmt->affected_rows;
        echo json_encode([
            'success' => true,
            'deleted' => $deletedCount,
            'message' => "Successfully deleted {$deletedCount} item(s)"
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting items: ' . $deleteStmt->error]);
    }
    
    $deleteStmt->close();
    exit;
}

// Get clients list for dropdown
if ($action === 'get_clients') {
    header('Content-Type: application/json');
    $clients = [];
    $result = $conn->query("SELECT id, client_name, contact_person FROM clients ORDER BY client_name ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $clients[] = [
                'id' => (int)$row['id'],
                'client_name' => $row['client_name'] ?? '',
                'contact_person' => $row['contact_person'] ?? ''
            ];
        }
    }
    echo json_encode($clients);
    exit;
}

// Get client contact person by ID
if ($action === 'get_client_contact') {
    $clientId = intval($_POST['client_id'] ?? $_GET['client_id'] ?? 0);
    if ($clientId > 0) {
        $stmt = $conn->prepare("SELECT contact_person FROM clients WHERE id = ?");
        $stmt->bind_param("i", $clientId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if ($row) {
            echo json_encode(['success' => true, 'contact_person' => $row['contact_person'] ?? '']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Client not found']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid client ID']);
    }
    exit;
}

// Get backup job details for editing
if ($action === 'get_job') {
    header('Content-Type: application/json');
    $id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
    if ($id === 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid job ID']);
        exit;
    }
    
    $stmt = $conn->prepare("SELECT bj.*, c.client_name, c.contact_person FROM backup_jobs bj LEFT JOIN clients c ON bj.client_id = c.id WHERE bj.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $job = $result->fetch_assoc();
    $stmt->close();
    
    if (!$job) {
        echo json_encode(['success' => false, 'error' => 'Job not found']);
        exit;
    }
    
    // Get retention_days - check multiple possible column names
    $retentionDays = $job['retention_days'] ?? $job['days'] ?? 30;
    
    // Get contact person - prioritize contact_name from backup_jobs, then from clients table
    $contactPerson = $job['contact_name'] ?? $job['contact_person'] ?? '';
    
    echo json_encode([
        'success' => true,
        'job' => [
            'id' => (int)$job['id'],
            'client_id' => $job['client_id'] ? (int)$job['client_id'] : null,
            'device_type' => $job['device_type'] ?? '',
            'backup_source' => $job['backup_source'] ?? '',
            'retention_days' => (int)$retentionDays,
            'days' => (int)$retentionDays,
            'contact_name' => $contactPerson,
            'contact_person' => $contactPerson,
            'client_name' => $job['client_name'] ?? '',
            'latest_status' => $job['latest_status'] ?? '',
            'latest_backup_at' => $job['latest_backup_at'] ?? null,
            'latest_backup_size' => $job['latest_backup_size'] ?? null,
            'is_active' => $job['is_active'] ?? 1
        ]
    ]);
    exit;
}

// Add new backup job
if ($action === 'add_job') {
    header('Content-Type: application/json');
    $clientId = !empty($_POST['client_id']) ? intval($_POST['client_id']) : null;
    $contactPerson = $_POST['contact_person'] ?? null;
    $deviceType = trim($_POST['device_type'] ?? '');
    $backupSource = trim($_POST['backup_source'] ?? '');
    $days = !empty($_POST['days']) ? intval($_POST['days']) : 30;
    
    if (empty($deviceType) || empty($backupSource)) {
        echo json_encode(['success' => false, 'error' => 'Device type and backup source are required']);
        exit;
    }
    
    // If client_id is provided but contact_person is not, fetch it from clients table
    if ($clientId && empty($contactPerson)) {
        $contactStmt = $conn->prepare("SELECT contact_person FROM clients WHERE id = ?");
        $contactStmt->bind_param("i", $clientId);
        $contactStmt->execute();
        $contactResult = $contactStmt->get_result();
        $contactRow = $contactResult->fetch_assoc();
        $contactStmt->close();
        
        if ($contactRow) {
            $contactPerson = $contactRow['contact_person'] ?? null;
        }
    }
    
    // Check if job already exists
    $checkStmt = $conn->prepare("SELECT id FROM backup_jobs WHERE device_type = ? AND backup_source = ?");
    $checkStmt->bind_param("ss", $deviceType, $backupSource);
    $checkStmt->execute();
    $existing = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();
    
    if ($existing) {
        echo json_encode(['success' => false, 'error' => 'A backup job with this device type and source already exists']);
        exit;
    }
    
    // Insert new job
    $insertStmt = $conn->prepare("INSERT INTO backup_jobs (client_id, contact_name, device_type, backup_source, retention_days, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    if (empty($contactPerson)) {
        $contactPerson = null;
    }
    $insertStmt->bind_param("isssi", $clientId, $contactPerson, $deviceType, $backupSource, $days);
    
    if ($insertStmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Backup job added successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error adding backup job: ' . $insertStmt->error]);
    }
    $insertStmt->close();
    exit;
}

// Update existing backup job
if ($action === 'update_job') {
    header('Content-Type: application/json');
    $jobId = intval($_POST['job_id'] ?? 0);
    $clientId = !empty($_POST['client_id']) ? intval($_POST['client_id']) : null;
    $contactPerson = $_POST['contact_person'] ?? null;
    $deviceType = trim($_POST['device_type'] ?? '');
    $backupSource = trim($_POST['backup_source'] ?? '');
    $days = !empty($_POST['days']) ? intval($_POST['days']) : 30;
    
    if ($jobId === 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid job ID']);
        exit;
    }
    
    if (empty($deviceType) || empty($backupSource)) {
        echo json_encode(['success' => false, 'error' => 'Device type and backup source are required']);
        exit;
    }
    
    // If client_id is provided but contact_person is not, fetch it from clients table
    if ($clientId && empty($contactPerson)) {
        $contactStmt = $conn->prepare("SELECT contact_person FROM clients WHERE id = ?");
        $contactStmt->bind_param("i", $clientId);
        $contactStmt->execute();
        $contactResult = $contactStmt->get_result();
        $contactRow = $contactResult->fetch_assoc();
        $contactStmt->close();
        
        if ($contactRow) {
            $contactPerson = $contactRow['contact_person'] ?? null;
        }
    }
    
    // Update job
    $updateStmt = $conn->prepare("UPDATE backup_jobs SET client_id = ?, contact_name = ?, device_type = ?, backup_source = ?, retention_days = ? WHERE id = ?");
    if (empty($contactPerson)) {
        $contactPerson = null;
    }
    $updateStmt->bind_param("isssii", $clientId, $contactPerson, $deviceType, $backupSource, $days, $jobId);
    
    if ($updateStmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Backup job updated successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error updating backup job: ' . $updateStmt->error]);
    }
    $updateStmt->close();
    exit;
}

// Get job details with statistics and logs for view modal
if ($action === 'get_job_details') {
    header('Content-Type: application/json');
    $id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
    if ($id === 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid job ID']);
        exit;
    }
    
    // Get job details
    $stmt = $conn->prepare("SELECT bj.*, c.client_name, c.contact_person FROM backup_jobs bj LEFT JOIN clients c ON bj.client_id = c.id WHERE bj.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $job = $result->fetch_assoc();
    $stmt->close();
    
    if (!$job) {
        echo json_encode(['success' => false, 'error' => 'Job not found']);
        exit;
    }
    
    // Get statistics
    $statsStmt = $conn->prepare("SELECT 
        COUNT(*) as total_logs,
        SUM(CASE WHEN status = 'Success' THEN 1 ELSE 0 END) as success_count,
        SUM(CASE WHEN status = 'Warning' THEN 1 ELSE 0 END) as warning_count,
        SUM(CASE WHEN status IN ('Error', 'Failed') THEN 1 ELSE 0 END) as error_count,
        MAX(backup_date) as last_backup,
        MIN(backup_date) as first_backup
        FROM backup_logs WHERE backup_job_id = ?");
    $statsStmt->bind_param("i", $id);
    $statsStmt->execute();
    $statsResult = $statsStmt->get_result();
    $statistics = $statsResult->fetch_assoc();
    $statsStmt->close();
    
    // Get recent logs (last 50)
    $logsStmt = $conn->prepare("SELECT id, status, backup_date, backup_size, email_subject, email_body, source, created_at 
        FROM backup_logs 
        WHERE backup_job_id = ? 
        ORDER BY backup_date DESC, created_at DESC 
        LIMIT 50");
    $logsStmt->bind_param("i", $id);
    $logsStmt->execute();
    $logsResult = $logsStmt->get_result();
    $logs = [];
    while ($log = $logsResult->fetch_assoc()) {
        // Decode email_body if it's base64 encoded
        $emailBody = $log['email_body'] ?? '';
        if (!empty($emailBody)) {
            // Try to detect if it's base64
            $decoded = @base64_decode($emailBody, true);
            if ($decoded !== false && base64_encode($decoded) === $emailBody) {
                // It's base64 encoded, decode it
                $emailBody = $decoded;
            }
            // Clean up UTF-8 BOM and encoding artifacts
            $emailBody = cleanEmailBody($emailBody);
            $log['email_body'] = $emailBody;
        }
        $logs[] = $log;
    }
    $logsStmt->close();
    
    echo json_encode([
        'success' => true,
        'job' => [
            'id' => (int)$job['id'],
            'client_id' => $job['client_id'] ? (int)$job['client_id'] : null,
            'client_name' => $job['client_name'] ?? 'N/A',
            'contact_name' => $job['contact_name'] ?? $job['contact_person'] ?? 'N/A',
            'device_type' => $job['device_type'] ?? '',
            'backup_source' => $job['backup_source'] ?? '',
            'retention_days' => $job['retention_days'] ?? $job['days'] ?? 30,
            'latest_status' => $job['latest_status'] ?? 'UNKNOWN',
            'latest_backup_at' => $job['latest_backup_at'] ?? null,
            'latest_backup_size' => $job['latest_backup_size'] ?? null,
            // Use latest_backup_at as fallback if last_email_received_at is missing
            'last_email_received_at' => $job['last_email_received_at'] ?? $job['latest_backup_at'] ?? null,
            'is_active' => isset($job['is_active']) ? (int)$job['is_active'] : 1,
            'created_at' => $job['created_at'] ?? null
        ],
        'statistics' => $statistics ? [
            'total_logs' => (int)$statistics['total_logs'],
            'success_count' => (int)$statistics['success_count'],
            'warning_count' => (int)$statistics['warning_count'],
            'error_count' => (int)$statistics['error_count'],
            'last_backup' => $statistics['last_backup'],
            'first_backup' => $statistics['first_backup']
        ] : [
            'total_logs' => 0,
            'success_count' => 0,
            'warning_count' => 0,
            'error_count' => 0,
            'last_backup' => null,
            'first_backup' => null
        ],
        'logs' => $logs
    ]);
    exit;
}

// Create job from undefined mail
if ($action === 'create_job_from_undefined') {
    header('Content-Type: application/json');
    
    try {
        logCreateJobError("=== CREATE JOB FROM UNDEFINED START ===", $_POST);
        
        // Handle undefined_mail_id - it might be empty string or not set
        $undefinedMailId = isset($_POST['undefined_mail_id']) && $_POST['undefined_mail_id'] !== '' ? intval($_POST['undefined_mail_id']) : null;
        $clientId = !empty($_POST['client_id']) ? intval($_POST['client_id']) : null;
        $contactPerson = $_POST['contact_person'] ?? null;
        $deviceType = trim($_POST['device_type'] ?? '');
        $backupSource = trim($_POST['backup_source'] ?? '');
        $days = !empty($_POST['days']) ? intval($_POST['days']) : 30;
        
        logCreateJobError("Parsed values", [
            'undefined_mail_id' => $undefinedMailId,
            'client_id' => $clientId,
            'contact_person' => $contactPerson,
            'device_type' => $deviceType,
            'backup_source' => $backupSource,
            'days' => $days
        ]);
        
        if (empty($deviceType) || empty($backupSource)) {
            logCreateJobError("Validation failed: device_type or backup_source empty");
            echo json_encode(['success' => false, 'error' => 'Device type and backup source are required']);
            exit;
        }
    
        // If client_id is provided but contact_person is not, fetch it from clients table
        if ($clientId && empty($contactPerson)) {
            logCreateJobError("Fetching contact_person for client_id: $clientId");
            $contactStmt = $conn->prepare("SELECT contact_person FROM clients WHERE id = ?");
            if (!$contactStmt) {
                logCreateJobError("Failed to prepare contact statement", ['error' => $conn->error]);
                throw new Exception("Database error: " . $conn->error);
            }
            $contactStmt->bind_param("i", $clientId);
            $contactStmt->execute();
            $contactResult = $contactStmt->get_result();
            $contactRow = $contactResult->fetch_assoc();
            $contactStmt->close();
            
            if ($contactRow) {
                $contactPerson = $contactRow['contact_person'] ?? null;
                logCreateJobError("Fetched contact_person", ['contact_person' => $contactPerson]);
            }
        }
        
        // Check if job already exists
        logCreateJobError("Checking for existing job", ['device_type' => $deviceType, 'backup_source' => $backupSource]);
        $checkStmt = $conn->prepare("SELECT id FROM backup_jobs WHERE device_type = ? AND backup_source = ?");
        if (!$checkStmt) {
            logCreateJobError("Failed to prepare check statement", ['error' => $conn->error]);
            throw new Exception("Database error: " . $conn->error);
        }
        $checkStmt->bind_param("ss", $deviceType, $backupSource);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();
        
        if ($existing) {
            logCreateJobError("Job already exists", ['existing_id' => $existing['id']]);
            echo json_encode(['success' => false, 'error' => 'A backup job with this device type and source already exists']);
            exit;
        }
        
        // Get undefined mail data if undefined_mail_id is provided
        $undefinedMail = null;
        if ($undefinedMailId) {
            logCreateJobError("Fetching undefined mail", ['undefined_mail_id' => $undefinedMailId]);
            $mailStmt = $conn->prepare("SELECT * FROM undefined_mails WHERE id = ?");
            if (!$mailStmt) {
                logCreateJobError("Failed to prepare mail statement", ['error' => $conn->error]);
                throw new Exception("Database error: " . $conn->error);
            }
            $mailStmt->bind_param("i", $undefinedMailId);
            $mailStmt->execute();
            $mailResult = $mailStmt->get_result();
            $undefinedMail = $mailResult->fetch_assoc();
            $mailStmt->close();
            
            if ($undefinedMail) {
                logCreateJobError("Fetched undefined mail", ['mail_id' => $undefinedMailId, 'has_data' => true]);
            } else {
                logCreateJobError("Undefined mail not found", ['undefined_mail_id' => $undefinedMailId]);
            }
        } else {
            logCreateJobError("No undefined_mail_id provided, creating job without mail data");
        }
        
        // Use undefined mail data if available, otherwise use safe defaults
        if ($undefinedMail) {
            $status = trim($undefinedMail['status'] ?? '');
            $backupDate = $undefinedMail['backup_date'] ?? null;
            $backupSize = $undefinedMail['backup_size'] ?? null;
        } else {
            $status = '';
            $backupDate = null;
            $backupSize = null;
        }
        
        // Ensure we always have a readable status string for the job
        if ($status === '') {
            $status = 'UNKNOWN';
        }
        
        logCreateJobError("Prepared values for insert", [
            'status' => $status,
            'backup_date' => $backupDate,
            'backup_size' => $backupSize
        ]);
        
        // Handle null values for database
        if (empty($contactPerson)) {
            $contactPerson = null;
        }
        if (empty($backupDate)) {
            $backupDate = null;
        }
        if (empty($backupSize)) {
            $backupSize = null;
        }
        
        // Insert new job - always include latest_status so UI can display it
        logCreateJobError("Preparing INSERT statement with latest_status");
        $insertStmt = $conn->prepare("INSERT INTO backup_jobs (client_id, contact_name, device_type, backup_source, retention_days, latest_status, latest_backup_at, latest_backup_size, last_email_received_at, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())");
        
        if (!$insertStmt) {
            logCreateJobError("Failed to prepare INSERT statement", ['error' => $conn->error]);
            throw new Exception("Database error: " . $conn->error);
        }
        
        logCreateJobError("Binding parameters (with status)", [
            'client_id' => $clientId,
            'contact_name' => $contactPerson,
            'device_type' => $deviceType,
            'backup_source' => $backupSource,
            'retention_days' => $days,
            'latest_status' => $status,
            'latest_backup_at' => $backupDate,
            'latest_backup_size' => $backupSize,
            'last_email_received_at' => $backupDate
        ]);
        
        $insertStmt->bind_param("isssissss", $clientId, $contactPerson, $deviceType, $backupSource, $days, $status, $backupDate, $backupSize, $backupDate);
        
        logCreateJobError("Executing INSERT statement");
        if ($insertStmt->execute()) {
            $jobId = $conn->insert_id;
            logCreateJobError("Job created successfully", ['job_id' => $jobId]);
            
            // If undefined mail exists, move all matching undefined mails to logs
            if ($undefinedMailId) {
                logCreateJobError("Processing undefined mails", ['mail_id' => $undefinedMailId]);
                moveUndefinedMailsToLogs($conn, $jobId, $deviceType, $backupSource);
            }
            
            logCreateJobError("=== CREATE JOB FROM UNDEFINED SUCCESS ===");
            echo json_encode(['success' => true, 'message' => 'Backup job created successfully' . ($undefinedMail ? ' and undefined mail moved to logs' : '')]);
        } else {
            $errorMsg = 'Error creating backup job: ' . $insertStmt->error;
            logCreateJobError("INSERT failed", ['error' => $errorMsg, 'sql_error' => $conn->error]);
            echo json_encode(['success' => false, 'error' => $errorMsg]);
        }
        $insertStmt->close();
        
    } catch (Exception $e) {
        logCreateJobError("EXCEPTION CAUGHT", [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    } catch (Error $e) {
        logCreateJobError("FATAL ERROR CAUGHT", [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        echo json_encode(['success' => false, 'error' => 'Fatal error: ' . $e->getMessage()]);
    }
    
    exit;
}

// Delete backup job
if ($action === 'delete_job') {
    header('Content-Type: application/json');
    $id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
    if ($id === 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid job ID']);
        exit;
    }
    
    // Delete logs first (safety for schemas without cascade)
    $deleteLogsStmt = $conn->prepare("DELETE FROM backup_logs WHERE backup_job_id = ?");
    $deleteLogsStmt->bind_param("i", $id);
    if (!$deleteLogsStmt->execute()) {
        echo json_encode(['success' => false, 'error' => 'Error deleting backup logs: ' . $deleteLogsStmt->error]);
        $deleteLogsStmt->close();
        exit;
    }
    $deleteLogsStmt->close();

    // Delete the job
    $stmt = $conn->prepare("DELETE FROM backup_jobs WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Backup job deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error deleting job: ' . $stmt->error]);
    }
    $stmt->close();
    exit;
}

// Handle other actions (fetch for regular tabs, etc.)
if ($action === 'fetch') {
    $type = $_POST['type'] ?? '';
    $source = $_POST['source'] ?? '';
    $start = $_POST['start'] ?? '';
    $end = $_POST['end'] ?? '';
    $search = $_POST['search'] ?? '';
    $status = $_POST['status'] ?? '';
    
    // Build query for backup_jobs (use latest log when available)
    $where = ["backup_source = ?"];
    $params = [$source];
    $types = ["s"];
    
    if (!empty($start)) {
        $where[] = "DATE(COALESCE(bl.backup_date, bj.latest_backup_at)) >= ?";
        $params[] = $start;
        $types[] = "s";
    }
    
    if (!empty($end)) {
        $where[] = "DATE(COALESCE(bl.backup_date, bj.latest_backup_at)) <= ?";
        $params[] = $end;
        $types[] = "s";
    }
    
    if (!empty($search)) {
        $where[] = "(device_type LIKE ? OR client_name LIKE ? OR contact_person LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types[] = "s";
        $types[] = "s";
        $types[] = "s";
    }
    
    if (!empty($status)) {
        $where[] = "COALESCE(bl.status, bj.latest_status) = ?";
        $params[] = $status;
        $types[] = "s";
    }
    
    $whereClause = implode(" AND ", $where);
    
        $query = "SELECT bj.*, c.client_name, c.contact_person, 
                   bl.status AS latest_log_status,
                   bl.backup_date AS latest_log_date,
                   bl.backup_size AS latest_log_size
              FROM backup_jobs bj 
              LEFT JOIN clients c ON bj.client_id = c.id 
              LEFT JOIN backup_logs bl ON bl.id = (
                  SELECT id 
                  FROM backup_logs 
                  WHERE backup_job_id = bj.id 
                  ORDER BY backup_date DESC, created_at DESC 
                  LIMIT 1
              )
              WHERE {$whereClause} 
              ORDER BY COALESCE(bl.backup_date, bj.latest_backup_at) DESC, bj.created_at DESC";
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param(implode("", $types), ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $html = '';
    $rowCount = 0;
    
    while ($row = $result->fetch_assoc()) {
        $rowCount++;
        
        // Status badge
        $latestStatus = $row['latest_log_status'] ?? $row['latest_status'] ?? '';
        $normalizedStatus = (strcasecmp(trim($latestStatus), 'Error') === 0) ? 'Failed' : $latestStatus;
        $status = strtolower(trim($normalizedStatus));
        $statusClass = 'secondary';
        if ($status === 'success') $statusClass = 'success';
        elseif ($status === 'warning') $statusClass = 'warning';
        elseif (in_array($status, ['error', 'failed'])) $statusClass = 'danger';
        
        $statusBadge = '<span class="badge bg-' . $statusClass . '">' . htmlspecialchars($normalizedStatus ?: 'N/A') . '</span>';
        
        // Format dates
        $latestBackupAt = $row['latest_log_date'] ?? $row['latest_backup_at'] ?? null;
        $latestBackup = $latestBackupAt ? date('Y-m-d H:i', strtotime($latestBackupAt)) : 'N/A';
        
        // Backup size
        $latestBackupSize = $row['latest_log_size'] ?? $row['latest_backup_size'] ?? null;
        $backupSize = !empty($latestBackupSize) ? htmlspecialchars($latestBackupSize) : 'N/A';
        
        $html .= "<tr>";
        $html .= "<td>" . htmlspecialchars($row['client_name'] ?? 'N/A') . "</td>";
        $html .= "<td>" . htmlspecialchars($row['contact_person'] ?? 'N/A') . "</td>";
        $html .= "<td>" . htmlspecialchars($row['device_type'] ?? 'N/A') . "</td>";
        $html .= "<td>{$statusBadge}</td>";
        $html .= "<td><small>{$latestBackup}</small></td>";
        $html .= "<td><small>{$backupSize}</small></td>";
        $html .= "<td>";
        $html .= "<button class='btn btn-sm btn-primary me-1' onclick='viewDetails({$row['id']})' title='View Details'><i class='fas fa-eye'></i></button>";
        $html .= "<button class='btn btn-sm btn-warning me-1' onclick='editJob({$row['id']})' title='Edit'><i class='fas fa-edit'></i></button>";
        $html .= "<button class='btn btn-sm btn-danger' onclick='deleteJob({$row['id']})' title='Delete'><i class='fas fa-trash'></i></button>";
        $html .= "</td>";
        $html .= "</tr>";
    }
    
    if ($rowCount === 0) {
        $html = "<tr><td colspan='7' class='text-center text-muted py-4'>";
        $html .= "<i class='fas fa-info-circle me-2'></i>No backup jobs found";
        $html .= "</td></tr>";
    }
    
    $stmt->close();
    echo $html;
    exit;
}

// Helper function to send email with Excel attachment
function sendExcelEmail($toEmail, $subject, $body, $excelContent, $filename) {
    // Load PHPMailer
    $phpmailerLoaded = false;
    $baseDir = dirname(dirname(__DIR__)); // Go up to sautech root
    if (file_exists($baseDir . '/vendor/autoload.php')) {
        require_once $baseDir . '/vendor/autoload.php';
        $phpmailerLoaded = class_exists('PHPMailer\PHPMailer\PHPMailer');
    } elseif (file_exists('../../vendor/autoload.php')) {
        require_once '../../vendor/autoload.php';
        $phpmailerLoaded = class_exists('PHPMailer\PHPMailer\PHPMailer');
    } elseif (file_exists('../../../vendor/autoload.php')) {
        require_once '../../../vendor/autoload.php';
        $phpmailerLoaded = class_exists('PHPMailer\PHPMailer\PHPMailer');
    }
    
    if (!$phpmailerLoaded) {
        return ['success' => false, 'message' => 'PHPMailer not loaded'];
    }
    
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Enable verbose debug output (optional, can be removed in production)
        // $mail->SMTPDebug = 2;
        // $mail->Debugoutput = function($str, $level) {
        //     error_log("PHPMailer: $str");
        // };
        
        $mail->isSMTP();
        $mail->Host = 'mail-eu.smtp2go.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'sterp';
        $mail->Password = 'GDJbb45WkGijYD5D';
        $mail->Port = 2525;
        $mail->SMTPSecure = false; // Use false for port 2525
        $mail->SMTPAutoTLS = false;
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Set timeout for SMTP connection
        $mail->Timeout = 30;
        
        $mail->setFrom('support@sautech.net', 'Backup Monitoring System');
        $mail->addAddress($toEmail);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        // Add Excel attachment
        if (!empty($excelContent) && !empty($filename)) {
            $mail->addStringAttachment($excelContent, $filename, 'base64', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        } else {
            error_log("Export email error: Excel content or filename is empty. Content length: " . strlen($excelContent ?? '') . ", Filename: " . ($filename ?? 'empty'));
            return ['success' => false, 'message' => 'Excel file is empty or filename is missing'];
        }
        
        if ($mail->send()) {
            error_log("Export email sent successfully to: $toEmail with file: $filename");
            return ['success' => true, 'message' => 'Email sent successfully'];
        } else {
            $errorMsg = 'Failed to send email: ' . $mail->ErrorInfo;
            error_log("Export email error: $errorMsg");
            return ['success' => false, 'message' => $errorMsg];
        }
    } catch (Exception $e) {
        $errorMsg = 'Email error: ' . $e->getMessage();
        error_log("Export email exception: $errorMsg");
        return ['success' => false, 'message' => $errorMsg];
    }
}

// Report data
if ($action === 'report') {
    header('Content-Type: application/json');

    $clientId = intval($_POST['client_id'] ?? 0);
    $deviceType = trim($_POST['device_type'] ?? '');
    $startDate = trim($_POST['start'] ?? '');
    $endDate = trim($_POST['end'] ?? '');
    $source = trim($_POST['source'] ?? '');

    $where = ["1=1"];
    $params = [];
    $types = '';

    if ($source !== '') {
        $where[] = "bj.backup_source = ?";
        $params[] = $source;
        $types .= 's';
    }

    if ($clientId > 0) {
        $where[] = "bj.client_id = ?";
        $params[] = $clientId;
        $types .= 'i';
    }

    if ($deviceType !== '') {
        $where[] = "bj.device_type = ?";
        $params[] = $deviceType;
        $types .= 's';
    }

    if ($startDate !== '') {
        $where[] = "DATE(bl.backup_date) >= ?";
        $params[] = $startDate;
        $types .= 's';
    }

    if ($endDate !== '') {
        $where[] = "DATE(bl.backup_date) <= ?";
        $params[] = $endDate;
        $types .= 's';
    }

    $whereClause = implode(' AND ', $where);

    $query = "SELECT bl.backup_date, bl.status, bl.backup_size, bj.device_type, c.client_name
              FROM backup_logs bl
              INNER JOIN backup_jobs bj ON bj.id = bl.backup_job_id
              LEFT JOIN clients c ON c.id = bj.client_id
              WHERE {$whereClause}
              ORDER BY bl.backup_date DESC, bl.created_at DESC";

    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();

    echo json_encode($rows);
    exit;
}

// Export to Excel
if ($action === 'export') {
    if (!hasPermission('billing', 'backup_monitoring')) {
        http_response_code(403);
        die('Permission denied');
    }
    
    require_once '../billing/xlsxwriter.class.php';
    
    $type = $_GET['type'] ?? $_POST['type'] ?? 'CLIENT';
    $startDate = $_GET['start'] ?? $_POST['start'] ?? '';
    $endDate = $_GET['end'] ?? $_POST['end'] ?? '';
    $search = $_GET['search'] ?? $_POST['search'] ?? '';
    $email = $_POST['email'] ?? ''; // Email address to send report to
    $sendEmail = !empty($email);
    
    $writer = new XLSXWriter();
    $writer->setAuthor('Sautech');
    
    // Handle undefined mails export
    if ($type === 'UNDEFINED') {
        $whereUndefined = ["1=1"]; // Changed to show all undefined mails, not just warnings/errors
        $params = [];
        $types = '';
        
        if (!empty($startDate)) {
            $whereUndefined[] = "DATE(backup_date) >= ?";
            $params[] = $startDate;
            $types .= 's';
        }
        
        if (!empty($endDate)) {
            $whereUndefined[] = "DATE(backup_date) <= ?";
            $params[] = $endDate;
            $types .= 's';
        }
        
        if (!empty($search)) {
            $whereUndefined[] = "(device_type LIKE ? OR email_subject LIKE ? OR status LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'sss';
        }
        
        $whereClause = implode(' AND ', $whereUndefined);
        
        $query = "SELECT * FROM undefined_mails WHERE $whereClause ORDER BY created_at DESC";
        
        if (!empty($params)) {
            $q = $conn->prepare($query);
            $q->bind_param($types, ...$params);
            $q->execute();
            $res = $q->get_result();
        } else {
            $res = $conn->query($query);
        }
        
        $header = [
            'Source' => 'string',
            'Device Type' => 'string',
            'Status' => 'string',
            'Backup Date' => 'string',
            'Backup Size' => 'string',
            'Email Subject' => 'string',
            'Created At' => 'string'
        ];
        
        $writer->writeSheetHeader('Undefined Mails', $header);
        
        while ($row = $res->fetch_assoc()) {
            $writer->writeSheetRow('Undefined Mails', [
                $row['source'] ?? 'N/A',
                $row['device_type'] ?? 'N/A',
                $row['status'] ?? 'N/A',
                $row['backup_date'] ?? 'N/A',
                $row['backup_size'] ?? 'N/A',
                $row['email_subject'] ?? 'N/A',
                $row['created_at'] ?? 'N/A'
            ]);
        }
        
        $filename = "backup_undefined_mails_" . date('Y-m-d_H-i-s') . ".xlsx";
        $sheetName = 'Undefined Mails';
    } else {
        // Regular backup jobs export
        $where = ["b.backup_source = ?"];
        $params = [$type];
        $types = 's';
        
        if (!empty($startDate)) {
            $where[] = "DATE(b.latest_backup_at) >= ?";
            $params[] = $startDate;
            $types .= 's';
        }
        
        if (!empty($endDate)) {
            $where[] = "DATE(b.latest_backup_at) <= ?";
            $params[] = $endDate;
            $types .= 's';
        }
        
        if (!empty($search)) {
            $where[] = "(c.client_name LIKE ? OR b.contact_name LIKE ? OR b.device_type LIKE ? OR b.latest_status LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'ssss';
        }
        
        $whereClause = implode(' AND ', $where);
        
        $q = $conn->prepare("
            SELECT b.*, c.client_name 
            FROM backup_jobs b
            LEFT JOIN clients c ON c.id = b.client_id
            WHERE $whereClause
            ORDER BY c.client_name ASC, b.device_type ASC
        ");
        
        $q->bind_param($types, ...$params);
        $q->execute();
        $res = $q->get_result();
        
        $header = [
            'Client' => 'string',
            'Contact' => 'string',
            'Device Type' => 'string',
            'Status' => 'string',
            'Latest Backup' => 'string',
            'Backup Size' => 'string',
            'Retention Days' => 'integer',
            'Last Email Received' => 'string'
        ];
        
        $sheetName = ucfirst(strtolower($type)) . ' Backups';
        $writer->writeSheetHeader($sheetName, $header);
        
        while ($row = $res->fetch_assoc()) {
            $writer->writeSheetRow($sheetName, [
                $row['client_name'] ?? 'N/A',
                $row['contact_name'] ?? 'N/A',
                $row['device_type'] ?? 'N/A',
                $row['latest_status'] ?? 'N/A',
                $row['latest_backup_at'] ?? 'N/A',
                $row['latest_backup_size'] ?? 'N/A',
                $row['retention_days'] ?? 'N/A',
                $row['last_email_received_at'] ?? 'N/A'
            ]);
        }
        
        $filename = "backup_jobs_" . strtolower($type) . "_" . date('Y-m-d_H-i-s') . ".xlsx";
    }
    
    $excelContent = $writer->writeToString();
    
    // Log export attempt
    error_log("Export action - Type: $type, Email: " . ($email ?: 'none') . ", Excel size: " . strlen($excelContent) . " bytes");
    
    // If email is provided, send via email
    if ($sendEmail) {
        if (empty($excelContent)) {
            error_log("Export email error: Excel content is empty");
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Excel file is empty. No data to export.']);
                exit;
            } else {
                echo "<script>alert('Error: Excel file is empty. No data to export.'); window.close();</script>";
                exit;
            }
        }
        
        $emailSubject = "Backup Monitoring Report - " . $sheetName . " - " . date('Y-m-d H:i:s');
        $emailBody = "
            <html>
            <body>
                <h2>Backup Monitoring Report</h2>
                <p>Please find attached the backup monitoring report for <strong>" . htmlspecialchars($sheetName) . "</strong>.</p>
                <p><strong>Export Date:</strong> " . date('Y-m-d H:i:s') . "</p>
                " . (!empty($startDate) ? "<p><strong>Start Date:</strong> " . htmlspecialchars($startDate) . "</p>" : "") . "
                " . (!empty($endDate) ? "<p><strong>End Date:</strong> " . htmlspecialchars($endDate) . "</p>" : "") . "
                " . (!empty($search) ? "<p><strong>Search Filter:</strong> " . htmlspecialchars($search) . "</p>" : "") . "
                <p>This is an automated email from the Backup Monitoring System.</p>
            </body>
            </html>
        ";
        
        error_log("Attempting to send export email to: $email with file: $filename");
        $result = sendExcelEmail($email, $emailSubject, $emailBody, $excelContent, $filename);
        error_log("Export email result: " . json_encode($result));
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Return JSON response for AJAX
            header('Content-Type: application/json');
            echo json_encode($result);
            exit;
        } else {
            // Fallback for GET requests
            if ($result['success']) {
                echo "<script>alert('Email sent successfully to " . htmlspecialchars($email) . "'); window.close();</script>";
            } else {
                echo "<script>alert('Error: " . htmlspecialchars($result['message']) . "'); window.close();</script>";
            }
            exit;
        }
    }
    
    // If no email, download as before
    header('Content-disposition: attachment; filename="' . $filename . '"');
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Transfer-Encoding: binary');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    echo $excelContent;
    exit;
}

// Default response
echo json_encode(['error' => 'Invalid action']);
exit;
